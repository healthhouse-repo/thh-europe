<?php
/**
 * THH Nalevering / vervanging
 *
 * - Meta box op het orderscherm: kies het artikel dat op is, vink alternatieven aan
 *   (andere smaken van hetzelfde product worden voorgesteld, andere producten via zoeken),
 *   optioneel "wachten op nalevering" en "laten vervallen / terugbetalen".
 * - Verstuurt een keuzemail in huisstijl met een knop per optie.
 * - Klant kiest op /?thh_vv=<order>.<token> en bevestigt; daarna past de order zich aan:
 *   artikel vervangen (zelfde prijs, voorraad omgezet), nalevering genoteerd, of
 *   terugbetaling klaargezet voor de klantenservice (geen automatische refund).
 * - Overzicht van alle tickets onder WooCommerce > Naleveringen.
 */
if (!defined('ABSPATH')) return;

if (!function_exists('thh_vv_tickets')) {

function thh_vv_cs_mail() { return 'klantenservice@thehealthhouse.nl'; }
function thh_vv_money($a, $order) { return html_entity_decode(wp_strip_all_tags(wc_price($a, ['currency' => $order->get_currency()])), ENT_QUOTES, 'UTF-8'); }
function thh_vv_logo() { return 'https://thehealthhouse.nl/wp-content/uploads/2026/08/thh-logo-mail-navy-v2.png'; }

function thh_vv_tickets($order) {
	$t = $order ? $order->get_meta('_thh_vv_tickets') : [];
	return is_array($t) ? $t : [];
}
function thh_vv_save($order, $tickets) {
	$order->update_meta_data('_thh_vv_tickets', array_values($tickets));
	$order->save();
	$idx = get_option('thh_vv_index', []);
	if (!is_array($idx)) $idx = [];
	$idx[(int) $order->get_id()] = time();
	arsort($idx);
	update_option('thh_vv_index', array_slice($idx, 0, 1000, true), false);
	thh_vv_status_sync($order, $tickets);
}
/** Order op "Wacht op klant" zolang er een open keuze is; daarna terug naar de vorige status. */
function thh_vv_status_sync($order, $tickets) {
	$open = false;
	foreach ($tickets as $t) if ($t['status'] === 'open') $open = true;
	$st = $order->get_status();
	if ($open && in_array($st, ['processing', 'on-hold'], true)) {
		$order->update_meta_data('_thh_vv_prev_status', $st);
		$order->set_status('wacht-klant', 'Wacht op keuze van de klant (nalevering/vervanging). ');
		$order->save();
	} elseif (!$open && $st === 'wacht-klant') {
		$prev = $order->get_meta('_thh_vv_prev_status') ?: 'processing';
		$order->set_status($prev, 'Keuze klant afgehandeld, order kan verder. ');
		$order->save();
	}
}
function thh_vv_url($order, $t, $choice = null) {
	$args = ['thh_vv' => $order->get_id() . '.' . $t['token']];
	if ($choice !== null) $args['c'] = $choice;
	return add_query_arg($args, home_url('/'));
}
function thh_vv_img($product, $size = 'woocommerce_thumbnail') {
	if (!$product) return '';
	$id = $product->get_image_id();
	if (!$id && $product->get_parent_id()) { $p = wc_get_product($product->get_parent_id()); $id = $p ? $p->get_image_id() : 0; }
	$src = $id ? wp_get_attachment_image_url($id, $size) : '';
	return $src ?: wc_placeholder_img_src($size);
}
function thh_vv_label($o, $t) {
	switch ($o['type']) {
		case 'product':
			$p = wc_get_product($o['id']);
			return $p ? wp_strip_all_tags($p->get_name()) : 'Product #' . (int) $o['id'];
		case 'wait':
			return 'Wachten op nalevering' . (!empty($t['eta']) ? ' (verwacht: ' . $t['eta'] . ')' : '');
		case 'refund':
			return 'Ik wil dit product annuleren en een terugbetaling ontvangen';
	}
	return '';
}
/** In-stock varianten van hetzelfde hoofdproduct (bv. andere smaak) */
function thh_vv_siblings($item) {
	$out = [];
	$vid = $item->get_variation_id();
	if (!$vid) return $out;
	$parent = wc_get_product($item->get_product_id());
	if (!$parent || !$parent->is_type('variable')) return $out;
	foreach ($parent->get_children() as $cid) {
		if ((int) $cid === (int) $vid) continue;
		$c = wc_get_product($cid);
		if (!$c || !$c->is_in_stock() || !$c->is_purchasable()) continue;
		$out[] = ['id' => $cid, 'name' => wp_strip_all_tags($c->get_name()), 'stock' => $c->managing_stock() ? (int) $c->get_stock_quantity() : null];
	}
	return $out;
}

/* ---------- Mail ---------- */
function thh_vv_mail_wrap($heading, $inner) {
	$logo = esc_url(thh_vv_logo());
	return '<!doctype html><html><body style="margin:0;background:#f5f6fa;font-family:Arial,Helvetica,sans-serif;color:#1b1f2e">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f6fa"><tr><td align="center" style="padding:24px 12px">'
		. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:14px;overflow:hidden">'
		. '<tr><td style="background:#14204a;padding:22px 28px" align="left"><img src="' . $logo . '" alt="The Health House" height="34" style="display:block;height:34px;width:auto;border:0"></td></tr>'
		. '<tr><td style="padding:28px 28px 8px"><h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;color:#14204a">' . esc_html($heading) . '</h1>' . $inner . '</td></tr>'
		. '<tr><td style="padding:18px 28px 26px;font-size:13px;line-height:1.6;color:#5a6072;border-top:1px solid #e8eaf1">Vragen? Beantwoord deze mail of mail naar <a href="mailto:' . thh_vv_cs_mail() . '" style="color:#14204a">' . thh_vv_cs_mail() . '</a>.<br>The Health House &middot; <a href="https://thehealthhouse.nl" style="color:#14204a">thehealthhouse.nl</a></td></tr>'
		. '</table></td></tr></table></body></html>';
}
function thh_vv_btn($url, $label, $primary = true) {
	$bg = $primary ? '#14204a' : '#ffffff';
	$fg = $primary ? '#ffffff' : '#14204a';
	return '<a href="' . esc_url($url) . '" style="display:inline-block;background:' . $bg . ';color:' . $fg . ';border:2px solid #14204a;text-decoration:none;font-weight:bold;font-size:15px;padding:11px 20px;border-radius:999px">' . esc_html($label) . '</a>';
}
function thh_vv_send_customer($order, $t, $reminder = false) {
	$to = $order->get_billing_email();
	if (!$to) return false;
	$item = $order->get_item($t['item_id']);
	$first = $order->get_billing_first_name();
	$rows = '';
	foreach ($t['options'] as $i => $o) {
		$img = '';
		$sub = '';
		if ($o['type'] === 'product') {
			$p = wc_get_product($o['id']);
			$img = '<img src="' . esc_url(thh_vv_img($p)) . '" width="64" height="64" alt="" style="display:block;width:64px;height:64px;object-fit:contain;border-radius:8px;background:#f5f6fa">';
			$sub = 'Zonder meerkosten, we sturen dit in plaats van het originele artikel.';
		} elseif ($o['type'] === 'wait') {
			$sub = 'We houden het artikel voor je apart en sturen het na zodra het binnen is.';
		} else {
			$sub = 'We annuleren dit product en storten het bedrag terug.';
		}
		$rows .= '<tr><td style="padding:12px 0;border-top:1px solid #e8eaf1"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
			. ($img ? '<td width="76" valign="middle">' . $img . '</td>' : '')
			. '<td valign="middle" style="font-size:15px;line-height:1.45"><strong>' . esc_html(thh_vv_label($o, $t)) . '</strong><br><span style="font-size:13px;color:#5a6072">' . esc_html($sub) . '</span></td>'
			. '<td valign="middle" align="right" width="120">' . thh_vv_btn(thh_vv_url($order, $t, $i), 'Kies dit', $i === 0) . '</td>'
			. '</tr></table></td></tr>';
	}
	$inner = '<p style="font-size:15px;line-height:1.6;margin:0 0 14px">Hoi' . ($first ? ' ' . esc_html($first) : '') . ',</p>'
		. '<p style="font-size:15px;line-height:1.6;margin:0 0 14px">Bedankt voor je bestelling <strong>#' . esc_html($order->get_order_number()) . '</strong>. Helaas is <strong>' . esc_html($t['item_name']) . '</strong>' . ($t['qty'] > 1 ? ' (' . (int) $t['qty'] . 'x)' : '') . ' op dit moment niet op voorraad. Wat wil je dat we doen?</p>'
		. (!empty($t['note']) ? '<p style="font-size:15px;line-height:1.6;margin:0 0 14px;padding:12px 14px;background:#f5f6fa;border-radius:10px">' . nl2br(esc_html($t['note'])) . '</p>' : '')
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 18px">' . $rows . '</table>'
		. '<p style="font-size:14px;line-height:1.6;margin:0 0 14px;color:#5a6072">De rest van je bestelling loopt gewoon door. Je kunt je keuze op de volgende pagina nog even controleren voordat je bevestigt.</p>';
	$subject = ($reminder ? 'Herinnering: ' : '') . 'Bestelling #' . $order->get_order_number() . ': kies een alternatief voor ' . $t['item_name'];
	if ($reminder) $inner = '<p style="font-size:15px;line-height:1.6;margin:0 0 14px;padding:10px 14px;background:#fff6e5;border-radius:10px">We hebben je keuze nog niet ontvangen. Zodra je kiest, versturen we je bestelling.</p>' . $inner;
	return wp_mail($to, $subject, thh_vv_mail_wrap('Even je keuze nodig', $inner), ['Content-Type: text/html; charset=UTF-8', 'Reply-To: The Health House <' . thh_vv_cs_mail() . '>']);
}

/* ---------- Admin: meta box ---------- */
add_action('add_meta_boxes', function () {
	$screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';
	add_meta_box('thh-vv', 'Nalevering / vervanging', 'thh_vv_metabox', $screen, 'normal', 'high');
});

function thh_vv_ticket_list_html($order) {
	$tickets = thh_vv_tickets($order);
	if (!$tickets) return '<p style="color:#666;margin:6px 0 0">Nog geen naleveringen of vervangingen voor deze order.</p>';
	$h = '<table class="widefat striped" style="margin-top:8px"><thead><tr><th>Artikel</th><th>Opties</th><th>Status</th><th></th></tr></thead><tbody>';
	foreach (array_reverse($tickets) as $t) {
		$opts = [];
		foreach ($t['options'] as $o) $opts[] = esc_html(thh_vv_label($o, $t));
		if ($t['status'] === 'chosen') {
			$st = '<strong style="color:#1a7f37">Gekozen:</strong> ' . esc_html(thh_vv_label($t['options'][$t['choice']], $t)) . '<br><small>' . esc_html(wp_date('d-m-Y H:i', $t['chosen_at'])) . '</small>';
		} elseif ($t['status'] === 'cancelled') {
			$st = '<span style="color:#888">Geannuleerd</span>';
		} else {
			$st = '<strong style="color:#b26a00">Wacht op klant</strong><br><small>mail ' . esc_html(wp_date('d-m-Y H:i', $t['sent_at'])) . (!empty($t['resent']) ? ' (' . (int) $t['resent'] . 'x opnieuw)' : '') . '</small>';
		}
		$act = '';
		if ($t['status'] === 'open') {
			$act = '<button type="button" class="button button-small thh-vv-act" data-act="resend" data-t="' . esc_attr($t['id']) . '">Opnieuw mailen</button> '
				. '<button type="button" class="button button-small thh-vv-act" data-act="cancel" data-t="' . esc_attr($t['id']) . '">Annuleren</button> '
				. '<a class="button button-small" target="_blank" href="' . esc_url(thh_vv_url($order, $t)) . '">Klantpagina</a>';
		}
		$h .= '<tr><td>' . esc_html($t['item_name']) . ($t['qty'] > 1 ? ' &times;' . (int) $t['qty'] : '') . '</td><td>' . implode('<br>', $opts) . '</td><td>' . $st . '</td><td>' . $act . '</td></tr>';
	}
	return $h . '</tbody></table>';
}

function thh_vv_metabox($obj) {
	$order = $obj instanceof WP_Post ? wc_get_order($obj->ID) : $obj;
	if (!$order || !is_a($order, 'WC_Order')) { echo '<p>Sla de order eerst op.</p>'; return; }
	$items = [];
	foreach ($order->get_items() as $iid => $item) {
		$p = $item->get_product();
		$items[] = [
			'id' => $iid,
			'name' => wp_strip_all_tags($item->get_name()),
			'qty' => $item->get_quantity(),
			'instock' => $p ? $p->is_in_stock() : false,
			'sib' => thh_vv_siblings($item),
		];
	}
	$nonce = wp_create_nonce('thh_vv_' . $order->get_id());
	?>
	<div id="thh-vv-box" data-order="<?php echo (int) $order->get_id(); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
		<style>
			#thh-vv-box .row{display:flex;gap:14px;flex-wrap:wrap;margin:8px 0}
			#thh-vv-box .col{flex:1 1 280px}
			#thh-vv-box label.blk{display:block;font-weight:600;margin:0 0 4px}
			#thh-vv-box .sib label{display:block;margin:3px 0}
			#thh-vv-box .muted{color:#666}
			#thh-vv-box .select2-container{width:100%!important}
		</style>
		<div class="row">
			<div class="col">
				<label class="blk" for="thh-vv-item">Welk artikel is op?</label>
				<select id="thh-vv-item" style="width:100%">
					<option value="">Kies artikel…</option>
					<?php foreach ($items as $it): ?>
						<option value="<?php echo (int) $it['id']; ?>"><?php echo esc_html($it['name'] . ($it['qty'] > 1 ? ' ×' . $it['qty'] : '') . ($it['instock'] ? '' : ' (op)')); ?></option>
					<?php endforeach; ?>
				</select>
				<div class="sib" id="thh-vv-sib" style="margin-top:8px"></div>
			</div>
			<div class="col">
				<label class="blk">Ander product als alternatief</label>
				<select id="thh-vv-prod" class="wc-product-search" multiple="multiple" data-placeholder="Zoek product of smaak…" data-action="woocommerce_json_search_products_and_variations" data-exclude_type="variable"></select>
				<div style="margin-top:10px">
					<label><input type="checkbox" id="thh-vv-wait" checked> Optie: wachten op nalevering</label>
					<input type="text" id="thh-vv-eta" placeholder="verwacht, bv. volgende week" style="width:100%;margin-top:4px">
					<label style="display:block;margin-top:6px"><input type="checkbox" id="thh-vv-refund" checked> Optie: laten vervallen en terugbetalen</label>
				</div>
			</div>
		</div>
		<label class="blk" for="thh-vv-note">Persoonlijke toelichting in de mail (optioneel)</label>
		<textarea id="thh-vv-note" rows="2" style="width:100%"></textarea>
		<p style="margin:8px 0 0"><button type="button" class="button button-primary" id="thh-vv-send">Keuzemail naar klant sturen</button> <span class="muted" id="thh-vv-msg"></span></p>
		<div id="thh-vv-list"><?php echo thh_vv_ticket_list_html($order); ?></div>
	</div>
	<script>
	jQuery(function($){
		var items = <?php echo wp_json_encode($items); ?>, box = $('#thh-vv-box');
		$(document.body).trigger('wc-enhanced-select-init');
		$('#thh-vv-item').on('change', function(){
			var id = +this.value, it = items.find(function(x){return x.id === id;}), h = '';
			if (it && it.sib.length) {
				h = '<label class="blk">Andere smaken/varianten op voorraad</label>';
				it.sib.forEach(function(s){ h += '<label><input type="checkbox" class="thh-vv-s" value="'+s.id+'"> '+$('<span>').text(s.name).html()+(s.stock!==null?' <span class="muted">('+s.stock+' op voorraad)</span>':'')+'</label>'; });
			} else if (it) { h = '<span class="muted">Geen andere varianten op voorraad; zoek rechts een alternatief.</span>'; }
			$('#thh-vv-sib').html(h);
		});
		function post(data, cb){
			data.action = 'thh_vv'; data.order = box.data('order'); data._n = box.data('nonce');
			$.post(ajaxurl, data).done(function(r){
				if (r && r.success) { $('#thh-vv-list').html(r.data.html); cb && cb(r.data); }
				else { $('#thh-vv-msg').text((r && r.data) || 'Er ging iets mis'); }
			}).fail(function(){ $('#thh-vv-msg').text('Er ging iets mis'); });
		}
		$('#thh-vv-send').on('click', function(){
			var item = $('#thh-vv-item').val();
			var prods = $('.thh-vv-s:checked').map(function(){return this.value;}).get().concat($('#thh-vv-prod').val() || []);
			if (!item) { $('#thh-vv-msg').text('Kies eerst het artikel dat op is.'); return; }
			if (!prods.length && !$('#thh-vv-wait').is(':checked') && !$('#thh-vv-refund').is(':checked')) { $('#thh-vv-msg').text('Kies minstens één optie.'); return; }
			if (!confirm('Keuzemail nu naar de klant sturen?')) return;
			$(this).prop('disabled', true); $('#thh-vv-msg').text('Versturen…');
			var btn = $(this);
			post({act:'create', item:item, prods:prods, wait:$('#thh-vv-wait').is(':checked')?1:0, refund:$('#thh-vv-refund').is(':checked')?1:0, eta:$('#thh-vv-eta').val(), note:$('#thh-vv-note').val()}, function(d){
				$('#thh-vv-msg').text(d.msg); $('#thh-vv-note,#thh-vv-eta').val(''); $('#thh-vv-prod').val(null).trigger('change'); $('.thh-vv-s').prop('checked', false);
			});
			setTimeout(function(){ btn.prop('disabled', false); }, 1500);
		});
		box.on('click', '.thh-vv-act', function(){
			var a = $(this).data('act');
			if (a === 'cancel' && !confirm('Dit ticket annuleren? De links in de mail werken dan niet meer.')) return;
			if (a === 'resend' && !confirm('Mail opnieuw sturen?')) return;
			post({act:a, t:$(this).data('t')}, function(d){ $('#thh-vv-msg').text(d.msg); });
		});
	});
	</script>
	<?php
}

add_action('wp_ajax_thh_vv', function () {
	$oid = absint($_POST['order'] ?? 0);
	if (!current_user_can('edit_shop_orders') || !wp_verify_nonce($_POST['_n'] ?? '', 'thh_vv_' . $oid)) wp_send_json_error('Geen rechten');
	$order = wc_get_order($oid);
	if (!$order) wp_send_json_error('Order niet gevonden');
	$tickets = thh_vv_tickets($order);
	$act = sanitize_key($_POST['act'] ?? '');
	$user = wp_get_current_user()->display_name;

	if ($act === 'create') {
		$iid = absint($_POST['item'] ?? 0);
		$item = $order->get_item($iid);
		if (!$item || !is_a($item, 'WC_Order_Item_Product')) wp_send_json_error('Artikel hoort niet bij deze order');
		$opts = [];
		$seen = [];
		foreach ((array) ($_POST['prods'] ?? []) as $pid) {
			$pid = absint($pid);
			if (!$pid || isset($seen[$pid]) || $pid == ($item->get_variation_id() ?: $item->get_product_id())) continue;
			$p = wc_get_product($pid);
			if (!$p || $p->is_type('variable') || !$p->is_purchasable()) continue;
			$seen[$pid] = 1;
			$opts[] = ['type' => 'product', 'id' => $pid];
		}
		if (!empty($_POST['wait'])) $opts[] = ['type' => 'wait'];
		if (!empty($_POST['refund'])) $opts[] = ['type' => 'refund'];
		if (!$opts) wp_send_json_error('Geen geldige opties');
		$t = [
			'id' => strtolower(wp_generate_password(8, false)),
			'token' => wp_generate_password(32, false),
			'item_id' => $iid,
			'item_name' => wp_strip_all_tags($item->get_name()),
			'qty' => (int) $item->get_quantity(),
			'options' => $opts,
			'eta' => sanitize_text_field(wp_unslash($_POST['eta'] ?? '')),
			'note' => sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')),
			'status' => 'open',
			'by' => $user,
			'sent_at' => time(),
		];
		// eerdere open tickets voor hetzelfde artikel vervallen
		foreach ($tickets as &$o) if ($o['item_id'] == $iid && $o['status'] === 'open') $o['status'] = 'cancelled';
		unset($o);
		$tickets[] = $t;
		thh_vv_save($order, $tickets);
		$ok = thh_vv_send_customer($order, $t);
		$labels = array_map(function ($o) use ($t) { return thh_vv_label($o, $t); }, $opts);
		$order->add_order_note('Nalevering/vervanging: keuzemail ' . ($ok ? 'verstuurd' : 'NIET verstuurd (mailfout)') . ' voor ' . $t['item_name'] . '. Opties: ' . implode(', ', $labels) . '. Door ' . $user . '.');
		wp_send_json_success(['html' => thh_vv_ticket_list_html($order), 'msg' => $ok ? 'Mail verstuurd naar ' . $order->get_billing_email() : 'Ticket aangemaakt, maar de mail kon niet worden verstuurd.']);
	}

	foreach ($tickets as $k => $t) {
		if ($t['id'] !== sanitize_key($_POST['t'] ?? '') || $t['status'] !== 'open') continue;
		if ($act === 'cancel') {
			$tickets[$k]['status'] = 'cancelled';
			thh_vv_save($order, $tickets);
			$order->add_order_note('Nalevering/vervanging voor ' . $t['item_name'] . ' geannuleerd door ' . $user . '.');
			wp_send_json_success(['html' => thh_vv_ticket_list_html($order), 'msg' => 'Geannuleerd']);
		}
		if ($act === 'resend') {
			$ok = thh_vv_send_customer($order, $t);
			$tickets[$k]['resent'] = (int) ($t['resent'] ?? 0) + 1;
			thh_vv_save($order, $tickets);
			$order->add_order_note('Keuzemail voor ' . $t['item_name'] . ' opnieuw verstuurd door ' . $user . '.');
			wp_send_json_success(['html' => thh_vv_ticket_list_html($order), 'msg' => $ok ? 'Opnieuw verstuurd' : 'Mail mislukt']);
		}
	}
	wp_send_json_error('Ticket niet gevonden of al afgehandeld');
});

/* ---------- Order aanpassen na keuze ---------- */
function thh_vv_apply($order, &$t, $choice) {
	$o = $t['options'][$choice] ?? null;
	if (!$o) return new WP_Error('x', 'Ongeldige keuze');
	$item = $order->get_item($t['item_id']);
	if (!$item || !is_a($item, 'WC_Order_Item_Product')) return new WP_Error('x', 'Dit artikel staat niet meer in je bestelling. Neem contact met ons op.');
	$cs = thh_vv_cs_mail();

	if ($o['type'] === 'product') {
		$new = wc_get_product($o['id']);
		if (!$new || !$new->is_in_stock()) return new WP_Error('stock', 'Dit alternatief is inmiddels ook uitverkocht. Kies een andere optie.');
		$old = $item->get_product();
		$oldname = $item->get_name();
		$qty = $item->get_quantity();
		$keep = [$item->get_subtotal(), $item->get_subtotal_tax(), $item->get_total(), $item->get_total_tax(), $item->get_taxes()];
		if ($item->get_meta('_reduced_stock')) {
			if ($old) wc_update_product_stock($old, $qty, 'increase');
			wc_update_product_stock($new, $qty, 'decrease');
		}
		foreach ($item->get_meta_data() as $m) {
			$d = $m->get_data();
			if ($d['key'] !== '' && $d['key'][0] !== '_') $item->delete_meta_data($d['key']);
		}
		$item->set_product($new);
		if ($new->is_type('variation')) {
			foreach ($new->get_variation_attributes() as $k => $v) $item->add_meta_data(str_replace('attribute_', '', $k), $v, true);
		}
		$item->set_subtotal($keep[0]); $item->set_subtotal_tax($keep[1]);
		$item->set_total($keep[2]); $item->set_total_tax($keep[3]); $item->set_taxes($keep[4]);
		$item->add_meta_data('Vervangt', $oldname, true);
		$item->save();
		$order->add_order_note('Klant koos vervanging: ' . $oldname . ' → ' . $new->get_name() . ' (zelfde prijs' . ($item->get_meta('_reduced_stock') ? ', voorraad omgezet' : '') . ').');
		$staff = 'Artikel in de order is vervangen door ' . $new->get_name() . '. Pakket kan verder.';
		$cust = 'We sturen <strong>' . esc_html($new->get_name()) . '</strong> in plaats van ' . esc_html($oldname) . ', zonder meerkosten.';
	} elseif ($o['type'] === 'wait') {
		$item->update_meta_data('Nalevering', 'Ja, klant wacht' . (!empty($t['eta']) ? ' (verwacht: ' . $t['eta'] . ')' : ''));
		$item->save();
		$item->update_meta_data('_thh_nalevering_open', 1);
		$item->save();
		$order->update_meta_data('_thh_nalevering_open', 1);
		$order->add_order_note('Klant kiest nalevering voor ' . $t['item_name'] . '. NALEVERING: dit artikel later nasturen.');
		$staff = 'Klant wacht op nalevering van ' . $t['item_name'] . '. De rest van de order kan alvast verstuurd worden.';
		$cust = 'We sturen <strong>' . esc_html($t['item_name']) . '</strong> na zodra het binnen is' . (!empty($t['eta']) ? ' (verwacht: ' . esc_html($t['eta']) . ')' : '') . '. De rest van je bestelling komt alvast je kant op.';
	} else {
		$amt = (float) $item->get_total() + (float) $item->get_total_tax();
		$item->update_meta_data('Terugbetalen', 'Ja, klant laat artikel vervallen (' . thh_vv_money($amt, $order) . ')');
		$item->save();
		$order->add_order_note('Klant laat ' . $t['item_name'] . ' vervallen. TERUGBETALING NOG UITVOEREN: ' . thh_vv_money($amt, $order) . ' via Terugbetalen in deze order.');
		$staff = 'Klant laat ' . $t['item_name'] . ' vervallen. Voer de terugbetaling van ' . thh_vv_money($amt, $order) . ' handmatig uit via "Terugbetalen" in de order.';
		$cust = 'We annuleren <strong>' . esc_html($t['item_name']) . '</strong> en storten ' . wc_price($amt, ['currency' => $order->get_currency()]) . ' terug. Dat zie je binnen een paar werkdagen terug op je rekening.';
	}
	$t['status'] = 'chosen';
	$t['choice'] = (int) $choice;
	$t['chosen_at'] = time();
	$order->save();

	wp_mail($cs, 'Keuze klant order #' . $order->get_order_number() . ': ' . thh_vv_label($o, $t),
		thh_vv_mail_wrap('Klant heeft gekozen', '<p style="font-size:15px;line-height:1.6">Order <strong>#' . esc_html($order->get_order_number()) . '</strong> (' . esc_html($order->get_formatted_billing_full_name()) . ')<br>Niet leverbaar: ' . esc_html($t['item_name']) . '<br>Keuze: <strong>' . esc_html(thh_vv_label($o, $t)) . '</strong></p><p style="font-size:15px;line-height:1.6">' . esc_html($staff) . '</p><p>' . thh_vv_btn($order->get_edit_order_url(), 'Open order') . '</p>'),
		['Content-Type: text/html; charset=UTF-8']);
	if ($order->get_billing_email()) {
		wp_mail($order->get_billing_email(), 'Bestelling #' . $order->get_order_number() . ': je keuze is verwerkt',
			thh_vv_mail_wrap('Bedankt, je keuze is verwerkt', '<p style="font-size:15px;line-height:1.6">' . $cust . '</p>'),
			['Content-Type: text/html; charset=UTF-8', 'Reply-To: The Health House <' . $cs . '>']);
	}
	return $cust;
}

/* ---------- Klantpagina ---------- */
add_action('template_redirect', function () {
	if (empty($_GET['thh_vv'])) return;
	nocache_headers();
	if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
	header('X-Robots-Tag: noindex, nofollow');

	$raw = sanitize_text_field(wp_unslash($_GET['thh_vv']));
	$parts = explode('.', $raw, 2);
	$order = count($parts) === 2 ? wc_get_order(absint($parts[0])) : null;
	$tickets = $order ? thh_vv_tickets($order) : [];
	$k = null;
	foreach ($tickets as $i => $t) if (hash_equals($t['token'], $parts[1] ?? '')) $k = $i;

	$msg = ''; $err = '';
	if ($k !== null && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['thh_vv_c']) && $tickets[$k]['status'] === 'open') {
		$t = $tickets[$k];
		$r = thh_vv_apply($order, $t, absint($_POST['thh_vv_c']));
		if (is_wp_error($r)) { $err = $r->get_error_message(); }
		else { $tickets[$k] = $t; thh_vv_save($order, $tickets); $msg = $r; }
	}

	status_header(200);
	get_header();
	echo '<style>.thvv{max-width:720px;margin:32px auto 64px;padding:0 16px;color:#1b1f2e}.thvv h1{color:#14204a;font-size:28px;line-height:1.25;margin:0 0 10px}.thvv p{font-size:16px;line-height:1.6}.thvv .card{display:flex;gap:14px;align-items:center;background:#fff;border:2px solid #e8eaf1;border-radius:14px;padding:14px;margin:10px 0;cursor:pointer}.thvv .card:has(input:checked){border-color:#14204a;background:#f5f6fa}.thvv .card input{width:20px;height:20px;accent-color:#14204a;flex:0 0 auto}.thvv .card img{width:64px;height:64px;object-fit:contain;border-radius:8px;background:#f5f6fa;flex:0 0 auto}.thvv .card small{display:block;color:#5a6072;font-size:14px}.thvv .btn{display:inline-block;background:#14204a;color:#fff;border:0;border-radius:999px;padding:14px 26px;font-weight:700;font-size:16px;cursor:pointer;margin-top:10px}.thvv .note{background:#f5f6fa;border-radius:12px;padding:14px 16px}.thvv .err{background:#fdecee;color:#9b1c23;border-radius:12px;padding:12px 16px}.thvv .ok{background:#e8f5ec;border-radius:12px;padding:16px 18px}</style>';
	echo '<div class="thvv">';
	if ($k === null) {
		echo '<h1>Link niet gevonden</h1><p>Deze link is ongeldig of verlopen. Neem contact op via <a href="mailto:' . thh_vv_cs_mail() . '">' . thh_vv_cs_mail() . '</a>.</p>';
	} else {
		$t = $tickets[$k];
		if ($t['status'] === 'chosen') {
			echo '<h1>Bedankt, je keuze is verwerkt</h1><div class="ok"><p style="margin:0">' . ($msg ?: 'Je hebt gekozen voor: <strong>' . esc_html(thh_vv_label($t['options'][$t['choice']], $t)) . '</strong>.') . '</p></div><p>Bestelling #' . esc_html($order->get_order_number()) . '. Vragen? Mail <a href="mailto:' . thh_vv_cs_mail() . '">' . thh_vv_cs_mail() . '</a>.</p>';
		} elseif ($t['status'] !== 'open') {
			echo '<h1>Deze keuze is niet meer nodig</h1><p>We hebben dit al op een andere manier geregeld. Vragen? Mail <a href="mailto:' . thh_vv_cs_mail() . '">' . thh_vv_cs_mail() . '</a>.</p>';
		} else {
			$pre = isset($_GET['c']) ? absint($_GET['c']) : 0;
			echo '<h1>Kies een alternatief</h1><p>Bestelling <strong>#' . esc_html($order->get_order_number()) . '</strong>: <strong>' . esc_html($t['item_name']) . '</strong>' . ($t['qty'] > 1 ? ' (' . (int) $t['qty'] . 'x)' : '') . ' is helaas niet op voorraad.</p>';
			if (!empty($t['note'])) echo '<p class="note">' . nl2br(esc_html($t['note'])) . '</p>';
			if ($err) echo '<p class="err">' . esc_html($err) . '</p>';
			echo '<form method="post">';
			foreach ($t['options'] as $i => $o) {
				$img = ''; $sub = '';
				if ($o['type'] === 'product') {
					$p = wc_get_product($o['id']);
					$img = '<img src="' . esc_url(thh_vv_img($p)) . '" alt="">';
					$sub = ($p && !$p->is_in_stock()) ? 'Inmiddels ook uitverkocht' : 'Zonder meerkosten, in plaats van het originele artikel';
				} elseif ($o['type'] === 'wait') {
					$sub = 'We sturen het na zodra het binnen is; de rest komt alvast';
				} else {
					$sub = 'We annuleren dit product en storten het bedrag terug';
				}
				echo '<label class="card"><input type="radio" name="thh_vv_c" value="' . (int) $i . '"' . checked($pre, $i, false) . ' required>' . $img . '<span><strong>' . esc_html(thh_vv_label($o, $t)) . '</strong><small>' . esc_html($sub) . '</small></span></label>';
			}
			echo '<button class="btn" type="submit">Bevestig mijn keuze</button></form>';
		}
	}
	echo '</div>';
	get_footer();
	exit;
});

/* ---------- Overzicht ---------- */
add_action('admin_menu', function () {
	add_submenu_page('woocommerce', 'Naleveringen', 'Naleveringen', 'edit_shop_orders', 'thh-naleveringen', function () {
		$idx = get_option('thh_vv_index', []);
		$rows = [];
		foreach ((array) $idx as $oid => $ts) {
			$o = wc_get_order($oid);
			if (!$o) continue;
			foreach (thh_vv_tickets($o) as $t) $rows[] = [$o, $t];
		}
		usort($rows, function ($a, $b) {
			$sa = $a[1]['status'] === 'open' ? 0 : 1; $sb = $b[1]['status'] === 'open' ? 0 : 1;
			return $sa <=> $sb ?: ($b[1]['sent_at'] <=> $a[1]['sent_at']);
		});
		echo '<div class="wrap"><h1>Naleveringen en vervangingen</h1><p>Maak een nieuwe aan via het blok "Nalevering / vervanging" in een order.</p><table class="widefat striped"><thead><tr><th>Order</th><th>Klant</th><th>Artikel</th><th>Opties</th><th>Status</th><th>Verstuurd</th></tr></thead><tbody>';
		if (!$rows) echo '<tr><td colspan="6">Nog niets.</td></tr>';
		foreach (array_slice($rows, 0, 300) as [$o, $t]) {
			$opts = array_map(function ($x) use ($t) { return esc_html(thh_vv_label($x, $t)); }, $t['options']);
			$st = $t['status'] === 'chosen' ? '<strong style="color:#1a7f37">' . esc_html(thh_vv_label($t['options'][$t['choice']], $t)) . '</strong>' : ($t['status'] === 'open' ? '<strong style="color:#b26a00">Wacht op klant</strong>' : 'Geannuleerd');
			echo '<tr><td><a href="' . esc_url($o->get_edit_order_url()) . '">#' . esc_html($o->get_order_number()) . '</a></td><td>' . esc_html($o->get_formatted_billing_full_name()) . '</td><td>' . esc_html($t['item_name']) . '</td><td>' . implode('<br>', $opts) . '</td><td>' . $st . '</td><td>' . esc_html(wp_date('d-m-Y H:i', $t['sent_at'])) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	});
});


/* ---------- Status "Wacht op klant" ---------- */
add_action('init', function () {
	register_post_status('wc-wacht-klant', [
		'label' => 'Wacht op klant', 'public' => false, 'exclude_from_search' => false,
		'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true,
		'label_count' => _n_noop('Wacht op klant <span class="count">(%s)</span>', 'Wacht op klant <span class="count">(%s)</span>'),
	]);
});
add_filter('wc_order_statuses', function ($s) {
	$out = [];
	foreach ($s as $k => $v) { $out[$k] = $v; if ($k === 'wc-processing') $out['wc-wacht-klant'] = 'Wacht op klant'; }
	if (!isset($out['wc-wacht-klant'])) $out['wc-wacht-klant'] = 'Wacht op klant';
	return $out;
});
add_filter('woocommerce_order_is_paid_statuses', function ($s) { $s[] = 'wacht-klant'; return $s; });
add_filter('woocommerce_reports_order_statuses', function ($s) { if (is_array($s)) $s[] = 'wacht-klant'; return $s; });
add_filter('woocommerce_analytics_excluded_statuses', function ($s) { return array_diff((array) $s, ['wacht-klant']); });
add_filter('woocommerce_valid_order_statuses_for_payment_complete', function ($s) { $s[] = 'wacht-klant'; return $s; });
add_action('admin_head', function () {
	echo '<style>.order-status.status-wacht-klant{background:#fde7b0;color:#7a5200}.thh-vv-pill{display:inline-block;padding:3px 9px;border-radius:999px;font-size:12px;font-weight:600;line-height:1.5;margin:1px 0}.thh-vv-pill.w{background:#fde7b0;color:#7a5200}.thh-vv-pill.n{background:#dfe8ff;color:#14204a}.thh-vv-pill.r{background:#fbe0e0;color:#8a1c1c}</style>';
});

/* ---------- Kolom in het orderoverzicht ---------- */
function thh_vv_col_html($order) {
	if (!$order) return '';
	$h = '';
	foreach (thh_vv_tickets($order) as $t) if ($t['status'] === 'open') { $h .= '<span class="thh-vv-pill w" title="' . esc_attr($t['item_name']) . '">Keuze klant</span><br>'; break; }
	foreach ($order->get_items() as $it) {
		if ($it->get_meta('_thh_nalevering_open')) $h .= '<span class="thh-vv-pill n" title="' . esc_attr($it->get_name()) . '">Nalevering: ' . esc_html(wp_trim_words($it->get_name(), 4, '…')) . '</span><br>';
		if ($it->get_meta('Terugbetalen') && !$it->get_meta('_thh_refund_done') && !$order->get_total_refunded()) $h .= '<span class="thh-vv-pill r">Terugbetalen</span><br>';
	}
	return $h;
}
$thh_vv_cols = function ($cols) {
	$out = [];
	foreach ($cols as $k => $v) { $out[$k] = $v; if ($k === 'order_status') $out['thh_vv'] = 'Nalevering'; }
	if (!isset($out['thh_vv'])) $out['thh_vv'] = 'Nalevering';
	return $out;
};
add_filter('manage_woocommerce_page_wc-orders_columns', $thh_vv_cols);
add_filter('manage_edit-shop_order_columns', $thh_vv_cols);
add_action('manage_woocommerce_page_wc-orders_custom_column', function ($col, $order) { if ($col === 'thh_vv') echo thh_vv_col_html($order); }, 10, 2);
add_action('manage_shop_order_posts_custom_column', function ($col, $id) { if ($col === 'thh_vv') echo thh_vv_col_html(wc_get_order($id)); }, 10, 2);

/* ---------- Nalevering afmelden ---------- */
add_action('admin_post_thh_vv_nageleverd', function () {
	$oid = absint($_GET['order'] ?? 0); $iid = absint($_GET['item'] ?? 0);
	if (!current_user_can('edit_shop_orders') || !wp_verify_nonce($_GET['_n'] ?? '', 'thh_vv_nl_' . $oid . '_' . $iid)) wp_die('Geen rechten');
	$order = wc_get_order($oid);
	$item = $order ? $order->get_item($iid) : null;
	if ($item) {
		$item->delete_meta_data('_thh_nalevering_open');
		$item->update_meta_data('Nalevering', 'Nageleverd op ' . wp_date('d-m-Y'));
		$item->save();
		$still = false;
		foreach ($order->get_items() as $it) if ($it->get_meta('_thh_nalevering_open')) $still = true;
		if (!$still) $order->delete_meta_data('_thh_nalevering_open');
		$order->add_order_note($item->get_name() . ' nageleverd (afgemeld door ' . wp_get_current_user()->display_name . ').');
		$order->save();
	}
	wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=thh-naleveringen'));
	exit;
});
function thh_vv_open_naleveringen() {
	$rows = [];
	foreach ((array) get_option('thh_vv_index', []) as $oid => $ts) {
		$o = wc_get_order($oid);
		if (!$o) continue;
		foreach ($o->get_items() as $iid => $it) if ($it->get_meta('_thh_nalevering_open')) $rows[] = [$o, $iid, $it];
	}
	return $rows;
}
add_action('admin_notices', function () {
	if (($_GET['page'] ?? '') !== 'thh-naleveringen') return;
	$rows = thh_vv_open_naleveringen();
	echo '<div class="wrap"><h2>Nog na te leveren (' . count($rows) . ')</h2><table class="widefat striped" style="margin-bottom:20px"><thead><tr><th>Order</th><th>Klant</th><th>Artikel</th><th>Aantal</th><th>Orderstatus</th><th></th></tr></thead><tbody>';
	if (!$rows) echo '<tr><td colspan="6">Niets na te leveren.</td></tr>';
	foreach ($rows as [$o, $iid, $it]) {
		$url = wp_nonce_url(admin_url('admin-post.php?action=thh_vv_nageleverd&order=' . $o->get_id() . '&item=' . $iid), 'thh_vv_nl_' . $o->get_id() . '_' . $iid, '_n');
		echo '<tr><td><a href="' . esc_url($o->get_edit_order_url()) . '">#' . esc_html($o->get_order_number()) . '</a></td><td>' . esc_html($o->get_formatted_billing_full_name()) . '</td><td>' . esc_html($it->get_name()) . '</td><td>' . (int) $it->get_quantity() . '</td><td>' . esc_html(wc_get_order_status_name($o->get_status())) . '</td><td><a class="button button-small" href="' . esc_url($url) . '" onclick="return confirm(\'Is dit artikel nagestuurd?\')">Nageleverd</a></td></tr>';
	}
	echo '</tbody></table></div>';
});

/* ---------- Herinnering na 2 dagen, melding klantenservice na 4 dagen ---------- */
add_action('init', function () {
	if (!wp_next_scheduled('thh_vv_cron')) wp_schedule_event(time() + 300, 'hourly', 'thh_vv_cron');
});
add_action('thh_vv_cron', function () {
	$now = time();
	foreach ((array) get_option('thh_vv_index', []) as $oid => $ts) {
		if ($now - (int) $ts > 30 * DAY_IN_SECONDS) continue;
		$o = wc_get_order($oid);
		if (!$o) continue;
		$tickets = thh_vv_tickets($o);
		$changed = false;
		foreach ($tickets as $k => $t) {
			if ($t['status'] !== 'open') continue;
			$age = $now - (int) $t['sent_at'];
			if ($age > 2 * DAY_IN_SECONDS && empty($t['reminded'])) {
				thh_vv_send_customer($o, $t, true);
				$tickets[$k]['reminded'] = $now; $changed = true;
				$o->add_order_note('Automatische herinnering keuzemail verstuurd voor ' . $t['item_name'] . '.');
			} elseif ($age > 4 * DAY_IN_SECONDS && empty($t['escalated'])) {
				wp_mail(thh_vv_cs_mail(), 'Geen keuze van klant, order #' . $o->get_order_number(),
					thh_vv_mail_wrap('Klant heeft nog niet gekozen', '<p style="font-size:15px;line-height:1.6">Order <strong>#' . esc_html($o->get_order_number()) . '</strong> (' . esc_html($o->get_formatted_billing_full_name()) . ', ' . esc_html($o->get_billing_phone()) . ') wacht al 4 dagen op een keuze voor ' . esc_html($t['item_name']) . '. Bel of mail de klant, of kies zelf via de klantpagina.</p><p>' . thh_vv_btn($o->get_edit_order_url(), 'Open order') . ' ' . thh_vv_btn(thh_vv_url($o, $t), 'Klantpagina', false) . '</p>'),
					['Content-Type: text/html; charset=UTF-8']);
				$tickets[$k]['escalated'] = $now; $changed = true;
			}
		}
		if ($changed) { $o->update_meta_data('_thh_vv_tickets', array_values($tickets)); $o->save(); }
	}
});

} // function_exists
