<?php
/**
 * THH Uitverkocht: vindbaar, niet in de lijsten
 *
 * - Shop, categorie-, merk- en tagpagina's tonen standaard alleen producten die te koop zijn.
 * - In het filterblok "Beschikbaarheid" komt een optie "Uitverkocht" (?uitverkocht=1) die
 *   de uitverkochte producten weer meeneemt.
 * - Zoekresultaten tonen alles, maar uitverkochte producten onderaan.
 * - Productpagina's van uitverkochte producten blijven online (SEO) en tonen
 *   4 alternatieven die wel op voorraad zijn.
 */
if (!defined('ABSPATH')) return;

if (!function_exists('thh_uv_show_all')) {

function thh_uv_show_all() { return !empty($_GET['uitverkocht']); }
function thh_uv_oos_tt() {
	static $id = null;
	if ($id === null) {
		$t = get_term_by('slug', 'outofstock', 'product_visibility');
		$id = $t ? (int) $t->term_taxonomy_id : 0;
	}
	return $id;
}

/* Lijstpagina's: uitverkocht eruit, tenzij ?uitverkocht=1 */
add_action('woocommerce_product_query', function ($q) {
	if (is_admin() || $q->is_search() || thh_uv_show_all()) return;
	$tax = (array) $q->get('tax_query');
	$tax[] = ['taxonomy' => 'product_visibility', 'field' => 'slug', 'terms' => ['outofstock'], 'operator' => 'NOT IN'];
	$q->set('tax_query', $tax);
}, 99);

/* Zoeken: uitverkocht onderaan */
add_filter('posts_clauses', function ($c, $q) {
	global $wpdb;
	if (is_admin() || !$q->is_main_query() || !$q->is_search()) return $c;
	$pt = $q->get('post_type');
	if ($pt && $pt !== 'product' && !in_array('product', (array) $pt, true)) return $c;
	$tt = thh_uv_oos_tt();
	if (!$tt) return $c;
	$c['fields'] .= ", (SELECT COUNT(*) FROM {$wpdb->term_relationships} thh_uv WHERE thh_uv.object_id = {$wpdb->posts}.ID AND thh_uv.term_taxonomy_id = $tt) AS thh_uv_oos";
	$c['orderby'] = 'thh_uv_oos ASC' . ($c['orderby'] ? ', ' . $c['orderby'] : '');
	return $c;
}, 99, 2);

/* Aantal uitverkochte producten op de huidige lijstpagina (voor het filter) */
function thh_uv_count() {
	$tt = thh_uv_oos_tt();
	if (!$tt) return 0;
	$args = ['post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => false,
		'tax_query' => [['taxonomy' => 'product_visibility', 'field' => 'term_taxonomy_id', 'terms' => [$tt]]]];
	if (is_product_taxonomy() || is_tax()) {
		$o = get_queried_object();
		if ($o && !empty($o->taxonomy)) $args['tax_query'][] = ['taxonomy' => $o->taxonomy, 'field' => 'term_id', 'terms' => [(int) $o->term_id], 'include_children' => true];
	}
	$key = 'thh_uv_c_' . md5(wp_json_encode($args));
	$n = get_transient($key);
	if ($n === false) {
		$q = new WP_Query($args);
		$n = (int) $q->found_posts;
		set_transient($key, $n, HOUR_IN_SECONDS);
	}
	return (int) $n;
}

add_action('wp_footer', function () {
	if (is_admin()) return;
	/* Filteroptie op lijstpagina's */
	if ((function_exists('is_shop') && is_shop()) || is_product_taxonomy() || is_tax('product_brand')) {
		$on = thh_uv_show_all();
		$n = $on ? 0 : thh_uv_count();
		$url = $on ? remove_query_arg(['uitverkocht', 'paged']) : add_query_arg('uitverkocht', '1', remove_query_arg('paged'));
		$url = preg_replace('#/page/\d+/#', '/', $url);
		$label = 'Uitverkocht';
		?>
		<style>.thh-uv-li a{display:flex;align-items:center;gap:8px}.thh-uv-li a:before{content:"";width:16px;height:16px;border:2px solid #c8ccd8;border-radius:4px;flex:0 0 auto;box-sizing:border-box}.thh-uv-li.on a:before{background:#14204a;border-color:#14204a;box-shadow:inset 0 0 0 2px #fff}</style>
		<script>
		(function(){
			function add(){
				var lists = document.querySelectorAll('.thh-av-list');
				if (!lists.length) return false;
				lists.forEach(function(ul){
					if (ul.querySelector('.thh-uv-li')) return;
					var li = document.createElement('li');
					li.className = 'woocommerce-widget-layered-nav-list__item wc-layered-nav-term thh-uv-li<?php echo $on ? ' on chosen' : ''; ?>';
					var a = document.createElement('a');
					a.href = <?php echo wp_json_encode($url); ?>;
					a.rel = 'nofollow';
					a.title = <?php echo wp_json_encode($on ? 'Verberg uitverkochte producten' : 'Toon ook uitverkochte producten'); ?>;
					a.textContent = <?php echo wp_json_encode($label); ?>;
					<?php if (!$on && $n): ?>var s=document.createElement('span');s.className='count';s.textContent='(<?php echo (int) $n; ?>)';a.appendChild(s);<?php endif; ?>
					li.appendChild(a); ul.appendChild(li);
				});
				return true;
			}
			if (!add()) document.addEventListener('DOMContentLoaded', add);
			new MutationObserver(function(){ add(); }).observe(document.body, {childList:true, subtree:true});
		})();
		</script>
		<?php
	}

	/* Productpagina: alternatieven bij uitverkocht */
	if (function_exists('is_product') && is_product()) {
		$p = wc_get_product(get_queried_object_id());
		if (!$p || $p->is_in_stock()) return;
		$alts = thh_uv_alternatives($p, 4);
		if (!$alts) return;
		echo '<div id="thh-uv-alt" style="display:none"><div class="thh-uv-box"><h3>Uitverkocht. Deze zijn wel direct leverbaar:</h3><div class="thh-uv-grid">';
		foreach ($alts as $a) {
			$img = wp_get_attachment_image_url($a->get_image_id(), 'woocommerce_thumbnail') ?: wc_placeholder_img_src();
			echo '<a class="thh-uv-card" href="' . esc_url($a->get_permalink()) . '"><img src="' . esc_url($img) . '" alt="" loading="lazy"><span class="n">' . esc_html($a->get_name()) . '</span><span class="p">' . wp_kses_post($a->get_price_html()) . '</span></a>';
		}
		echo '</div></div></div>';
		?>
		<style>
			.thh-uv-box{margin:18px 0;padding:16px;border:2px solid #14204a;border-radius:14px;background:#f5f6fa}
			.thh-uv-box h3{margin:0 0 12px;font-size:17px;color:#14204a}
			.thh-uv-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
			.thh-uv-card{display:flex;flex-direction:column;gap:6px;background:#fff;border-radius:10px;padding:10px;text-decoration:none;color:#1b1f2e}
			.thh-uv-card img{width:100%;aspect-ratio:1;object-fit:contain}
			.thh-uv-card .n{font-size:13px;line-height:1.3;font-weight:600}
			.thh-uv-card .p{font-size:14px;color:#14204a;font-weight:700}
			@media(max-width:767px){.thh-uv-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
		</style>
		<script>
		(function(){
			var box = document.getElementById('thh-uv-alt'); if (!box) return;
			var anchor = document.querySelector('.summary .stock.out-of-stock, .elementor-widget-woocommerce-product-stock, .stock.out-of-stock, form.cart, .summary .price, .elementor-widget-woocommerce-product-price');
			if (anchor) { anchor.parentNode.insertBefore(box, anchor.nextSibling); box.style.display = ''; }
		})();
		</script>
		<?php
	}
}, 50);

function thh_uv_alternatives($p, $limit = 4) {
	$key = 'thh_uv_alt_' . $p->get_id();
	$ids = get_transient($key);
	if (!is_array($ids)) {
		$ids = [];
		$cats = $p->get_category_ids();
		$brands = wp_get_post_terms($p->get_id(), 'product_brand', ['fields' => 'ids']);
		$vis = ['taxonomy' => 'product_visibility', 'field' => 'slug', 'terms' => ['outofstock', 'exclude-from-catalog'], 'operator' => 'NOT IN'];
		$base = ['post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'no_found_rows' => true, 'post__not_in' => [$p->get_id()],
			'meta_key' => 'total_sales', 'orderby' => 'meta_value_num', 'order' => 'DESC'];
		$tries = [];
		if ($cats && $brands && !is_wp_error($brands)) $tries[] = [$vis, ['taxonomy' => 'product_cat', 'terms' => $cats], ['taxonomy' => 'product_brand', 'terms' => $brands]];
		if ($cats) $tries[] = [$vis, ['taxonomy' => 'product_cat', 'terms' => $cats]];
		foreach ($tries as $tq) {
			if (count($ids) >= $limit) break;
			$found = get_posts($base + ['posts_per_page' => $limit * 2, 'tax_query' => array_merge(['relation' => 'AND'], $tq)]);
			foreach ($found as $f) { if (count($ids) >= $limit) break; if (!in_array($f, $ids, true)) $ids[] = (int) $f; }
		}
		set_transient($key, $ids, 6 * HOUR_IN_SECONDS);
	}
	return array_values(array_filter(array_map('wc_get_product', $ids), function ($x) { return $x && $x->is_in_stock(); }));
}

} // function_exists
