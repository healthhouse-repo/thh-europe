<?php
/**
 * THH — Brug pinterminal per winkel → kassa-snelknoppen
 *
 * Snippet 439 (Pinterminals per winkel) bewaart per WCPOS-winkel een eigen Mollie-key en terminal
 * in de optie thh_mtfwc_per_store. Snippet 422 (Kassa PIN/CONTANT snelknoppen) kijkt alleen in
 * de optie thh_pk_terminals (of zijn vaste standaardlijst) en weigert anders met
 * "Geen pinautomaat gekoppeld aan …".
 *
 * Deze brug vult thh_pk_terminals aan met de terminals uit "Pinterminals per winkel", zodat een
 * winkel die daar een terminal heeft (zoals Team Champ) ook in de kassa kan pinnen.
 * Een terminal die al in thh_pk_terminals staat, blijft leidend.
 */
if (!defined('ABSPATH')) return;

if (!function_exists('thh_pk_brug_merge')) {
	function thh_pk_brug_merge($map) {
		if (!function_exists('thh_pk_default_map')) return $map;
		$per = get_option('thh_mtfwc_per_store', []);
		if (!is_array($per) || !$per) return $map;
		$out = is_array($map) ? $map : thh_pk_default_map();
		foreach ($per as $store => $cfg) {
			$t = is_array($cfg) && !empty($cfg['terminal']) ? trim((string) $cfg['terminal']) : '';
			if ($t === '' || !preg_match('/^term_[A-Za-z0-9]+$/', $t)) continue;
			if (empty($out[(int) $store])) $out[(int) $store] = $t;
		}
		return $out;
	}
	add_filter('option_thh_pk_terminals', 'thh_pk_brug_merge', 20);
	add_filter('default_option_thh_pk_terminals', 'thh_pk_brug_merge', 20);

	/**
	 * Bij een automatisch gestarte pinbetaling (snippet 422) de winkel doorgeven aan "Pinterminals per
	 * winkel" (snippet 439), maar alleen als de eigen sleutel van die winkel de terminal ook echt kent.
	 * Anders blijft de hoofdsleutel in gebruik (zoals bij Osdorp, waar de terminal in het hoofdaccount staat).
	 * Resultaat per winkel+terminal wordt een dag onthouden.
	 */
	function thh_pk_brug_force_store($store, $term) {
		$store = (int) $store; $term = (string) $term;
		if (!$store || $term === '') return false;
		$per = get_option('thh_mtfwc_per_store', []);
		$key = is_array($per) && !empty($per[$store]['api_key']) ? (string) $per[$store]['api_key'] : '';
		if ($key === '') return false;
		$ck = 'thh_pk_brug_' . md5($store . '|' . $term . '|' . substr($key, -6));
		$ok = get_transient($ck);
		if ($ok === false) {
			$r = wp_remote_get('https://api.mollie.com/v2/terminals/' . rawurlencode($term), ['headers' => ['Authorization' => 'Bearer ' . $key], 'timeout' => 10]);
			$code = (int) wp_remote_retrieve_response_code($r);
			if (is_wp_error($r) || $code === 0) return false; // netwerkfout: niets forceren, hoofdsleutel gebruiken
			$ok = $code === 200 ? 'ja' : 'nee';
			set_transient($ck, $ok, DAY_IN_SECONDS);
		}
		if ($ok === 'ja') { $GLOBALS['thh_mtfwc_force_store'] = $store; return true; }
		unset($GLOBALS['thh_mtfwc_force_store']);
		return false;
	}
}
