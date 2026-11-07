<?php
/**
 * THH — 2+1 gratis in de kassa
 *
 * De webshop rekent 2+1 uit in de winkelwagen (snippet Badge 2+1). De kassa bouwt de bon direct als order,
 * dus daar doen we het op de order: bij elke wijziging van een open kassabon (status pos-open, gemaakt door
 * de kassa) tellen we per 2+1-product de stuks (smaken en maten samen) en zetten per 3 stuks een
 * kortingsregel "2+1 gratis: <product>" ter waarde van de goedkoopste op de bon.
 *
 * Verwijderen mag: haalt de medewerker de kortingsregel van de bon (kruisje), dan komt hij niet terug zolang
 * het aantal van dat product gelijk blijft. Verandert het aantal, dan wordt opnieuw gerekend.
 * Zelfde productlijst als online: thh_b21_actief() uit de badge-snippet.
 */
if (!defined('ABSPATH')) return;

if (!function_exists('thh_b21k_pas_toe')) {

function thh_b21k_is_kassabon($order) {
	return $order instanceof WC_Order && $order->get_created_via() === 'woocommerce-pos' && $order->get_status() === 'pos-open';
}

/* Groepen per 2+1-product: aantal, prijzen (excl. btw zoals op de regel), korting per 3 */
function thh_b21k_groepen($order) {
	$g = [];
	foreach ($order->get_items('line_item') as $item) {
		$p = $item->get_product();
		if (!$p || !function_exists('thh_b21_actief') || !thh_b21_actief($p)) continue;
		$pid = $p->get_parent_id() ?: $p->get_id();
		$qty = (int) $item->get_quantity(); if ($qty < 1) continue;
		$unit = $qty ? (float) $item->get_subtotal() / $qty : 0; // regelprijs excl. btw (incl. kassakorting op de regel niet: subtotal is vóór korting)
		$g[$pid]['naam'] = $p->get_parent_id() ? (wc_get_product($pid) ? wc_get_product($pid)->get_name() : $p->get_name()) : $p->get_name();
		$g[$pid]['tax'] = $item->get_tax_class();
		$g[$pid]['qty'] = ($g[$pid]['qty'] ?? 0) + $qty;
		for ($i = 0; $i < $qty; $i++) $g[$pid]['prijzen'][] = $unit;
	}
	foreach ($g as &$x) { sort($x['prijzen']); $x['gratis'] = intdiv($x['qty'], 3); $x['korting'] = round(array_sum(array_slice($x['prijzen'], 0, $x['gratis'])), 2); }
	return $g;
}

/* Kortingsregels bijwerken; geeft true terug als er iets veranderd is */
function thh_b21k_pas_toe($order) {
	$g = thh_b21k_groepen($order);
	$stand = $order->get_meta('_thh_b21_kassa'); $stand = is_array($stand) ? $stand : []; // pid => ['qty'=>, 'uit'=>bool]
	$aanwezig = [];
	foreach ($order->get_items('fee') as $fid => $fee) {
		$pid = (int) $fee->get_meta('_thh_b21');
		if ($pid) $aanwezig[$pid] = $fid;
	}
	$veranderd = false;
	foreach ($g as $pid => $x) {
		$vorig = $stand[$pid] ?? null;
		$uit = $vorig && !empty($vorig['uit']) && (int) $vorig['qty'] === (int) $x['qty'];
		// regel stond erop bij dit aantal en is nu weg → medewerker heeft hem verwijderd
		if ($vorig && empty($vorig['uit']) && (int) $vorig['qty'] === (int) $x['qty'] && !empty($vorig['fee']) && !isset($aanwezig[$pid])) { $uit = true; }
		if ($x['gratis'] < 1 || $x['korting'] <= 0 || $uit) {
			if (isset($aanwezig[$pid])) { $order->remove_item($aanwezig[$pid]); $veranderd = true; }
			$stand[$pid] = ['qty' => $x['qty'], 'uit' => $uit, 'fee' => false];
			continue;
		}
		$naam = '2+1 gratis: ' . $x['naam'] . ($x['gratis'] > 1 ? ' (' . $x['gratis'] . 'x)' : '');
		if (isset($aanwezig[$pid])) {
			$fee = $order->get_item($aanwezig[$pid]);
			if ($fee && (abs((float) $fee->get_total() + $x['korting']) > 0.005 || $fee->get_name() !== $naam)) {
				$fee->set_name($naam); $fee->set_amount(-$x['korting']); $fee->set_total(-$x['korting']); $fee->set_tax_class($x['tax']); $fee->save(); $veranderd = true;
			}
		} else {
			$fee = new WC_Order_Item_Fee();
			$fee->set_name($naam); $fee->set_amount(-$x['korting']); $fee->set_total(-$x['korting']); $fee->set_tax_status('taxable'); $fee->set_tax_class($x['tax']);
			$fee->add_meta_data('_thh_b21', (string) $pid, true);
			$fee->add_meta_data('_woocommerce_pos_uuid', wp_generate_uuid4(), true);
			$order->add_item($fee); $veranderd = true;
		}
		$stand[$pid] = ['qty' => $x['qty'], 'uit' => false, 'fee' => true];
	}
	// 2+1-regels van producten die niet meer op de bon staan
	foreach ($aanwezig as $pid => $fid) if (!isset($g[$pid])) { $order->remove_item($fid); unset($stand[$pid]); $veranderd = true; }
	foreach (array_keys($stand) as $pid) if (!isset($g[$pid])) unset($stand[$pid]);
	if ($stand !== ($order->get_meta('_thh_b21_kassa') ?: [])) { $order->update_meta_data('_thh_b21_kassa', $stand); $veranderd = true; }
	return $veranderd;
}

/* Na elke opslag van een open kassabon (de kassa slaat bij elke wijziging op via REST) */
add_action('woocommerce_after_order_object_save', function ($order) {
	static $bezig = false;
	if ($bezig || !thh_b21k_is_kassabon($order)) return;
	$bezig = true;
	try {
		if (thh_b21k_pas_toe($order)) { $order->calculate_totals(); $order->save(); }
		elseif ($order->get_id() && count($order->get_items('fee'))) {
			// de kassa zet na het opslaan zelf nog totalen (zonder onze kortingsregel mee te tellen): controleren en zo nodig herrekenen
			$vers = wc_get_order($order->get_id());
			if ($vers) { $vers->calculate_totals(); if (abs((float) $vers->get_total() - (float) $order->get_total()) > 0.005) { $vers->save(); $order->set_total($vers->get_total()); $order->set_total_tax($vers->get_total_tax()); } }
		}
	} catch (Throwable $e) {}
	$bezig = false;
}, 20);

/* Zorgen dat het antwoord van de REST-call de bijgewerkte bon bevat (de kassa neemt dat antwoord over) */
add_filter('woocommerce_rest_prepare_shop_order_object', function ($response, $order) {
	if (!thh_b21k_is_kassabon($order) || !($response instanceof WP_REST_Response)) return $response;
	$data = $response->get_data();
	if (!is_array($data) || empty($data['id'])) return $response;
	$vers = wc_get_order($data['id']);
	if ($vers && count($vers->get_items('fee')) !== count($order->get_items('fee'))) {
		$data['fee_lines'] = [];
		foreach ($vers->get_items('fee') as $fid => $fee) {
			$data['fee_lines'][] = ['id' => $fid, 'name' => $fee->get_name(), 'tax_class' => $fee->get_tax_class(), 'tax_status' => $fee->get_tax_status(), 'amount' => wc_format_decimal($fee->get_amount(), 6), 'total' => wc_format_decimal($fee->get_total(), 6), 'total_tax' => wc_format_decimal($fee->get_total_tax(), 6), 'taxes' => [], 'meta_data' => array_map(function ($m) { return ['id' => $m->id, 'key' => $m->key, 'value' => $m->value, 'display_key' => $m->key, 'display_value' => $m->value]; }, $fee->get_meta_data())];
		}
		foreach (['total', 'total_tax', 'discount_total'] as $k) $data[$k] = wc_format_decimal($vers->{'get_' . $k}(), 6);
		$response->set_data($data);
	}
	return $response;
}, 20, 2);

} // function_exists
