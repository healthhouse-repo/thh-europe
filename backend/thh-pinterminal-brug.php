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
}
