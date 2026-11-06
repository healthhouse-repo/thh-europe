<?php
/**
 * THH — Telling Osdorp → webshop op voorraad
 *
 * Telt Osdorpplein (WCPOS-store 60110) in het voorraadregister (/voorraad, snippet 242) een
 * product of variatie met aantal > 0, dan zet deze snippet dat artikel in de webshop op
 * "op voorraad":
 *   - beheert het artikel voorraad per aantal en staat het lager dan geteld → aantal = geteld;
 *   - anders → stock_status instock;
 *   - de vlag "niet leverbaar" (Leverbaar-switch, snippet 206) gaat eraf, anders wordt het
 *     artikel direct weer op uitverkocht gezet.
 * Telling 0 verandert niets aan de webshop (er kan voorraad in een andere winkel liggen).
 *
 * Werkt via het REST-antwoord van thh-vrr/v1/boek, zodat snippet 242 zelf ongemoeid blijft.
 * Schakelaar: option thh_vrr_osdorp_sync ('1' = aan, standaard aan). Winkels: option
 * thh_vrr_sync_stores (komma-gescheiden store-ID's, standaard 60110).
 */
if (!defined('ABSPATH')) return;

if (!function_exists('thh_vrr_sync_webshop')) {

function thh_vrr_sync_stores() {
	$s = get_option('thh_vrr_sync_stores', '60110');
	return array_filter(array_map('intval', explode(',', (string) $s)));
}

function thh_vrr_sync_webshop($pid, $vid, $aantal) {
	$id = $vid ?: $pid;
	$p = wc_get_product($id);
	if (!$p || $aantal <= 0) return null;
	$was = $p->get_stock_status() . '/' . ($p->get_manage_stock() ? $p->get_stock_quantity() : '-');
	// Leverbaar-switch uit, anders forceert die weer outofstock
	foreach (array_unique([$id, $pid]) as $x) {
		if (get_post_meta($x, '_thh_niet_leverbaar', true)) delete_post_meta($x, '_thh_niet_leverbaar');
	}
	if ($p->get_manage_stock()) {
		if ((float) $p->get_stock_quantity() < $aantal) $p->set_stock_quantity($aantal);
		if ($p->get_stock_quantity() > 0) $p->set_stock_status('instock');
	} else {
		$p->set_stock_status('instock');
	}
	$p->save();
	if ($vid) {
		$parent = wc_get_product($pid);
		if ($parent && $parent->is_type('variable')) {
			if ($parent->get_manage_stock() && (float) $parent->get_stock_quantity() <= 0) { $parent->set_manage_stock(false); $parent->save(); }
			WC_Product_Variable::sync($pid);
		}
	}
	if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($pid);
	return $was . ' → ' . wc_get_product($id)->get_stock_status();
}

add_filter('rest_request_after_callbacks', function ($response, $handler, $request) {
	if (get_option('thh_vrr_osdorp_sync', '1') !== '1') return $response;
	if ($request->get_method() !== 'POST' || $request->get_route() !== '/thh-vrr/v1/boek') return $response;
	if (is_wp_error($response) || !($response instanceof WP_REST_Response)) return $response;
	$data = $response->get_data();
	if (empty($data['ok'])) return $response;
	if (($request->get_param('soort') ?: 'telling') !== 'telling') return $response;
	$store = function_exists('thh_vrr_store') ? (int) thh_vrr_store($request) : 0;
	if (!$store || !in_array($store, thh_vrr_sync_stores(), true)) return $response;
	$aantal = (float) str_replace(',', '.', (string) $request->get_param('aantal'));
	$pid = (int) $request->get_param('pid');
	$vid = (int) $request->get_param('vid');
	if ($pid && $aantal > 0) {
		$r = thh_vrr_sync_webshop($pid, $vid, $aantal);
		if ($r) { $data['webshop'] = $r; $response->set_data($data); }
	}
	return $response;
}, 10, 3);

} // function_exists
