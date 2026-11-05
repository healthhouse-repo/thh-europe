<?php
/**
 * THH Merken beoordelen
 *
 * WooCommerce > Merken beoordelen: per bron (bv. Bardolino, Body & Shape) alle merken met
 * aantal producten, voorraad en verkopen. Per merk kies je "Houden" of "Uitverkocht".
 * Uitverkocht zet alle producten (en variaties) van dat merk uit die bron op out of stock
 * en bewaart de oude voorraadstand per product, zodat "Terugdraaien" alles herstelt.
 */
if (!defined('ABSPATH')) return;

if (!function_exists('thh_mb_sources')) {

function thh_mb_sources() {
	global $wpdb;
	$out = [];
	foreach (['_thh_bron_leverancier', '_thh_bron'] as $k) {
		$rows = $wpdb->get_results($wpdb->prepare("SELECT meta_value v, COUNT(*) n FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id AND p.post_type='product' WHERE meta_key=%s GROUP BY meta_value ORDER BY n DESC", $k));
		foreach ($rows as $r) if ($r->v !== '') $out[$k . '|' . $r->v] = $r->v . ' (' . (int) $r->n . ')';
	}
	return $out;
}
function thh_mb_products($srcKey) {
	[$k, $v] = array_pad(explode('|', $srcKey, 2), 2, '');
	if (!in_array($k, ['_thh_bron_leverancier', '_thh_bron'], true)) return [];
	return get_posts(['post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => $k, 'meta_value' => $v, 'no_found_rows' => true]);
}
function thh_mb_brand_tax() { return taxonomy_exists('product_brand') ? 'product_brand' : (taxonomy_exists('pwb-brand') ? 'pwb-brand' : 'product_brand'); }

function thh_mb_set_out($pid) {
	$p = wc_get_product($pid);
	if (!$p) return 0;
	$ids = $p->is_type('variable') ? array_merge([$pid], $p->get_children()) : [$pid];
	$n = 0;
	foreach ($ids as $id) {
		$x = wc_get_product($id);
		if (!$x) continue;
		if (!get_post_meta($id, '_thh_mb_backup', true)) {
			update_post_meta($id, '_thh_mb_backup', ['m' => $x->get_manage_stock(), 'q' => $x->get_stock_quantity(), 's' => $x->get_stock_status(), 't' => time()]);
		}
		if ($x->is_type('variable')) { $x->set_manage_stock(false); $x->save(); continue; }
		$x->set_manage_stock(false);
		$x->set_stock_status('outofstock');
		$x->save();
		$n++;
	}
	if ($p->is_type('variable')) WC_Product_Variable::sync($pid);
	return $n;
}
function thh_mb_restore($pid) {
	$p = wc_get_product($pid);
	if (!$p) return 0;
	$ids = $p->is_type('variable') ? array_merge($p->get_children(), [$pid]) : [$pid];
	$n = 0;
	foreach ($ids as $id) {
		$b = get_post_meta($id, '_thh_mb_backup', true);
		$x = wc_get_product($id);
		if (!$x || !is_array($b)) continue;
		$x->set_manage_stock((bool) $b['m']);
		if ($b['m']) $x->set_stock_quantity($b['q']);
		else $x->set_stock_status($b['s']);
		$x->save();
		delete_post_meta($id, '_thh_mb_backup');
		$n++;
	}
	if ($p->is_type('variable')) WC_Product_Variable::sync($pid);
	return $n;
}

add_action('admin_menu', function () {
	add_submenu_page('woocommerce', 'Merken beoordelen', 'Merken beoordelen', 'manage_woocommerce', 'thh-merken-beoordelen', 'thh_mb_page');
});

add_action('wp_ajax_thh_mb', function () {
	if (!current_user_can('manage_woocommerce') || !check_ajax_referer('thh_mb', '_n', false)) wp_send_json_error('Geen rechten');
	@set_time_limit(300);
	$src = sanitize_text_field(wp_unslash($_POST['src'] ?? ''));
	$bid = (int) ($_POST['brand'] ?? 0);
	$act = sanitize_key($_POST['act'] ?? '');
	$tax = thh_mb_brand_tax();
	$ids = thh_mb_products($src);
	if ($ids) update_object_term_cache($ids, 'product');
	$mine = [];
	foreach ($ids as $pid) {
		$terms = wp_get_post_terms($pid, $tax, ['fields' => 'ids']);
		if ($bid === 0 ? empty($terms) : in_array($bid, $terms, true)) $mine[] = $pid;
	}
	$n = 0;
	foreach ($mine as $pid) {
		if ($act === 'uit') $n += thh_mb_set_out($pid);
		elseif ($act === 'terug') $n += thh_mb_restore($pid);
	}
	$dec = get_option('thh_merkbeoordeling', []);
	if (!is_array($dec)) $dec = [];
	$who = wp_get_current_user()->display_name;
	if ($act === 'terug') unset($dec[$src][$bid]);
	else $dec[$src][$bid] = ['d' => $act === 'uit' ? 'uitverkocht' : 'houden', 't' => time(), 'by' => $who];
	update_option('thh_merkbeoordeling', $dec, false);
	if (function_exists('wc_delete_product_transients')) wc_delete_product_transients();
	wp_send_json_success(['n' => $n, 'p' => count($mine)]);
});

function thh_mb_page() {
	global $wpdb;
	$sources = thh_mb_sources();
	$src = isset($_GET['src']) ? sanitize_text_field(wp_unslash($_GET['src'])) : '';
	if (!isset($sources[$src])) { $src = isset($sources['_thh_bron_leverancier|Bardolino']) ? '_thh_bron_leverancier|Bardolino' : (string) array_key_first($sources); }
	$tax = thh_mb_brand_tax();
	$ids = thh_mb_products($src);
	$sales90 = [];
	if ($ids) {
		$lookup = $wpdb->prefix . 'wc_order_product_lookup';
		$rows = $wpdb->get_results($wpdb->prepare("SELECT product_id, SUM(product_qty) q FROM $lookup WHERE date_created >= %s GROUP BY product_id", gmdate('Y-m-d', time() - 90 * DAY_IN_SECONDS)));
		foreach ($rows as $r) $sales90[(int) $r->product_id] = (int) $r->q;
	}
	if ($ids) { update_meta_cache('post', $ids); update_object_term_cache($ids, 'product'); }
	$brands = [];
	foreach ($ids as $pid) {
		$terms = wp_get_post_terms($pid, $tax);
		$keys = $terms ? $terms : [(object) ['term_id' => 0, 'name' => '(geen merk)', 'slug' => '']];
		$st = get_post_meta($pid, '_stock_status', true);
		$ts = (int) get_post_meta($pid, 'total_sales', true);
		foreach ($keys as $t) {
			$b = &$brands[$t->term_id];
			if (!$b) $b = ['name' => $t->name, 'slug' => $t->slug, 'n' => 0, 'in' => 0, 'pub' => 0, 'tot' => 0, 's90' => 0, 'img' => [], 'price' => []];
			$b['n']++;
			if ($st === 'instock' || $st === 'onbackorder') $b['in']++;
			if (get_post_status($pid) === 'publish') $b['pub']++;
			$b['tot'] += $ts;
			$b['s90'] += $sales90[$pid] ?? 0;
			$pr = get_post_meta($pid, '_price', true);
			if ($pr !== '') $b['price'][] = (float) $pr;
			if (count($b['img']) < 4 && ($th = get_post_thumbnail_id($pid))) $b['img'][] = wp_get_attachment_image_url($th, 'thumbnail');
			unset($b);
		}
	}
	$dec = get_option('thh_merkbeoordeling', []);
	$dec = is_array($dec) && isset($dec[$src]) ? $dec[$src] : [];
	uasort($brands, function ($a, $b) { return [$b['s90'], $b['tot'], $b['n']] <=> [$a['s90'], $a['tot'], $a['n']]; });
	$done = 0;
	foreach ($brands as $id => $x) if (isset($dec[$id])) $done++;
	$nonce = wp_create_nonce('thh_mb');
	?>
	<div class="wrap" id="thh-mb">
		<h1>Merken beoordelen</h1>
		<form method="get" style="margin:10px 0 14px"><input type="hidden" name="page" value="thh-merken-beoordelen">
			<label>Bron <select name="src" onchange="this.form.submit()"><?php foreach ($sources as $k => $l) echo '<option value="' . esc_attr($k) . '"' . selected($k, $src, false) . '>' . esc_html($l) . '</option>'; ?></select></label>
			<label style="margin-left:14px"><input type="checkbox" id="thh-mb-open"> alleen nog niet beoordeeld</label>
			<strong style="margin-left:14px"><?php echo (int) $done; ?> van <?php echo count($brands); ?> merken beoordeeld</strong>
		</form>
		<p class="description">Gesorteerd op verkopen in de laatste 90 dagen. "Uitverkocht" zet alle producten van dit merk uit deze bron op out of stock; de oude voorraadstand wordt bewaard en "Terugdraaien" zet hem terug. Producten blijven online zichtbaar.</p>
		<style>
			#thh-mb table td{vertical-align:middle}
			#thh-mb .imgs img{width:40px;height:40px;object-fit:contain;background:#f5f6fa;border-radius:6px;margin-right:3px}
			#thh-mb tr.d-houden td{background:#eef8f0}
			#thh-mb tr.d-uitverkocht td{background:#fbeeee}
			#thh-mb .num{text-align:right;white-space:nowrap}
		</style>
		<table class="widefat striped"><thead><tr><th>Merk</th><th></th><th class="num">Producten</th><th class="num">Op voorraad</th><th class="num">Verkocht 90 d</th><th class="num">Verkocht totaal</th><th class="num">Prijs</th><th>Besluit</th><th></th></tr></thead><tbody>
		<?php foreach ($brands as $id => $b):
			$d = $dec[$id] ?? null;
			$pr = $b['price'] ? '€' . number_format(min($b['price']), 2, ',', '.') . ($b['price'] && max($b['price']) > min($b['price']) ? ' – €' . number_format(max($b['price']), 2, ',', '.') : '') : '';
			$front = $b['slug'] ? get_term_link($b['slug'], $tax) : '';
			$adm = admin_url('edit.php?post_type=product&' . $tax . '=' . rawurlencode($b['slug']));
		?>
			<tr class="<?php echo $d ? 'd-' . esc_attr($d['d']) : 'open'; ?>" data-brand="<?php echo (int) $id; ?>">
				<td><strong><?php echo esc_html(html_entity_decode($b['name'])); ?></strong><br><small><?php if ($front && !is_wp_error($front)) echo '<a target="_blank" href="' . esc_url($front) . '">webshop</a> · '; ?><a target="_blank" href="<?php echo esc_url($adm); ?>">producten</a></small></td>
				<td class="imgs"><?php foreach ($b['img'] as $u) echo '<img src="' . esc_url($u) . '" alt="">'; ?></td>
				<td class="num"><?php echo (int) $b['n']; ?></td>
				<td class="num"><?php echo (int) $b['in']; ?></td>
				<td class="num"><strong><?php echo (int) $b['s90']; ?></strong></td>
				<td class="num"><?php echo (int) $b['tot']; ?></td>
				<td class="num"><?php echo esc_html($pr); ?></td>
				<td class="st"><?php echo $d ? '<strong>' . esc_html(ucfirst($d['d'])) . '</strong><br><small>' . esc_html($d['by'] . ', ' . wp_date('d-m H:i', $d['t'])) . '</small>' : '<span style="color:#b26a00">Nog beoordelen</span>'; ?></td>
				<td style="white-space:nowrap">
					<button type="button" class="button thh-mb-a" data-act="houden">Houden</button>
					<button type="button" class="button thh-mb-a" data-act="uit">Uitverkocht</button>
					<?php if ($d && $d['d'] === 'uitverkocht'): ?><button type="button" class="button-link thh-mb-a" data-act="terug">Terugdraaien</button><?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
	</div>
	<script>
	jQuery(function($){
		var src = <?php echo wp_json_encode($src); ?>, n = <?php echo wp_json_encode($nonce); ?>;
		$('#thh-mb-open').on('change', function(){ $('#thh-mb tbody tr').not('.open').toggle(!this.checked); });
		$('#thh-mb').on('click', '.thh-mb-a', function(){
			var tr = $(this).closest('tr'), act = $(this).data('act'), name = tr.find('strong').first().text();
			if (act === 'uit' && !confirm('Alle producten van ' + name + ' uit deze bron op uitverkocht zetten?')) return;
			if (act === 'terug' && !confirm('Voorraadstand van ' + name + ' terugzetten?')) return;
			tr.find('button').prop('disabled', true); tr.find('.st').text('Bezig…');
			$.post(ajaxurl, {action:'thh_mb', _n:n, src:src, brand:tr.data('brand'), act:act}).done(function(r){
				if (!r || !r.success) { tr.find('.st').text((r && r.data) || 'Fout'); tr.find('button').prop('disabled', false); return; }
				var d = act === 'uit' ? 'uitverkocht' : (act === 'houden' ? 'houden' : '');
				tr.removeClass('open d-houden d-uitverkocht').addClass(d ? 'd-' + d : 'open');
				tr.find('.st').html(d ? '<strong>' + (d === 'uit' ? '' : d.charAt(0).toUpperCase() + d.slice(1)) + '</strong>' + (act === 'uit' ? '<br><small>' + r.data.n + ' artikelen op uitverkocht</small>' : '') : 'Teruggezet (' + r.data.n + ')');
				tr.find('button').prop('disabled', false);
				if (act === 'uit' && !tr.find('[data-act=terug]').length) tr.find('td:last').append(' <button type="button" class="button-link thh-mb-a" data-act="terug">Terugdraaien</button>');
				if (act === 'terug') tr.find('[data-act=terug]').remove();
			}).fail(function(){ tr.find('.st').text('Fout, probeer opnieuw'); tr.find('button').prop('disabled', false); });
		});
	});
	</script>
	<?php
}

} // function_exists
