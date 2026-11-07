<?php
/**
 * THH — Zoeken zonder koppelteken/spatie
 *
 * "celltech" vindt "Cell-Tech", "c4ultimate" vindt "C4 Ultimate", "preworkout" vindt "Pre-Workout".
 *
 * 1. FiboSearch (zoekbalk + zoekresultatenpagina): via de filter dgwt/wcas/phrase wordt een
 *    aaneengeschreven zoekterm omgezet naar de schrijfwijze zoals die in een producttitel staat
 *    (celltech → Cell-Tech), zodat de gewone zoekopdracht hem vindt.
 * 2. WP_Query-zoekopdrachten (kassa, admin-ajax, REST): extra OR op de titel/SKU zonder
 *    koppeltekens, spaties en punten.
 */
if (!defined('ABSPATH')) return;

if (!function_exists('thh_zk_herschrijf')) {
/** Zoekterm zonder scheidingstekens → schrijfwijze uit een producttitel, of de term zelf. */
function thh_zk_herschrijf($phrase) {
	$phrase = trim((string) $phrase);
	if (strlen($phrase) < 4 || preg_match('/[\s\-]/', $phrase)) return $phrase;
	$compact = strtolower(preg_replace('/[\.]+/u', '', $phrase));
	$cache = get_transient('thh_zk_' . md5($compact));
	if ($cache !== false) return $cache === '' ? $phrase : $cache;
	global $wpdb;
	$like = '%' . $wpdb->esc_like($compact) . '%';
	$titles = $wpdb->get_col($wpdb->prepare("SELECT post_title FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' AND LOWER(REPLACE(REPLACE(REPLACE(post_title,'-',''),' ',''),'.','')) LIKE %s AND LOWER(post_title) NOT LIKE %s LIMIT 30", $like, $like));
	$found = '';
	foreach ($titles as $t) {
		$tok = preg_split('/\s+/u', $t);
		$n = count($tok);
		for ($i = 0; $i < $n && !$found; $i++) {
			for ($len = 1; $len <= 3 && $i + $len <= $n; $len++) {
				$win = implode(' ', array_slice($tok, $i, $len));
				$c = strtolower(preg_replace('/[\s\-\.\x{2122}\x{00AE}]+/u', '', $win));
				if ($c === $compact || ($len > 1 && strpos($c, $compact) !== false && strlen($c) <= strlen($compact) + 2)) { $found = trim($win, " \t\n\r\0\x0B.,;:"); break; }
			}
		}
		if ($found) break;
	}
	set_transient('thh_zk_' . md5($compact), $found, 6 * HOUR_IN_SECONDS);
	return $found !== '' ? $found : $phrase;
}
add_filter('dgwt/wcas/phrase', 'thh_zk_herschrijf', 10, 1);

add_filter('posts_search', function ($search, $q) {
	global $wpdb;
	if (!$search || is_admin() && !wp_doing_ajax()) return $search;
	$s = trim((string) $q->get('s'));
	if (strlen($s) < 4) return $search;
	$pt = (array) $q->get('post_type');
	if ($pt && !in_array('product', $pt, true) && !in_array('any', $pt, true)) return $search;
	$compact = preg_replace('/[\s\-\.]+/u', '', $s);
	if ($compact === '') return $search;
	$like = '%' . $wpdb->esc_like(strtolower($compact)) . '%';
	$extra = $wpdb->prepare(
		" OR LOWER(REPLACE(REPLACE(REPLACE({$wpdb->posts}.post_title,'-',''),' ',''),'.','')) LIKE %s"
		. " OR {$wpdb->posts}.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND LOWER(REPLACE(REPLACE(meta_value,'-',''),' ','')) LIKE %s)",
		$like, $like
	);
	$pos = strrpos($search, ')');
	if ($pos === false) return $search;
	return substr($search, 0, $pos) . $extra . substr($search, $pos);
}, 999, 2);
}
