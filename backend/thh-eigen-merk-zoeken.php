<?php
/**
 * THH — Eigen merk bovenaan in de zoekresultaten
 *
 * 1. Zoekresultatenpagina (?s=…&post_type=product, ook die van FiboSearch): producten van The Health House eerst,
 *    daarna de rest in de gewone volgorde.
 * 2. Zoeksuggesties onder de zoekbalk (FiboSearch): passende producten van The Health House komen bovenaan de
 *    productsuggesties, ook als ze anders buiten de eerste suggesties zouden vallen. Het aantal suggesties blijft gelijk.
 * Merk: product_brand 2176 of brands 142.
 */
if (!defined('ABSPATH')) return;

function thh_ez_tt_ids() {
	static $ids = null;
	if ($ids !== null) return $ids;
	$ids = [];
	foreach ([[2176, 'product_brand'], [142, 'brands']] as $t) {
		$term = get_term($t[0], $t[1]);
		if ($term && !is_wp_error($term)) $ids[] = (int) $term->term_taxonomy_id;
	}
	return $ids;
}

function thh_ez_is_eigen($pid) {
	return has_term(2176, 'product_brand', $pid) || has_term(142, 'brands', $pid);
}

// 1. zoekresultatenpagina
add_filter('posts_clauses', function ($c, $q) {
	if (is_admin() || !$q->is_main_query() || !$q->is_search()) return $c;
	$pt = $q->get('post_type');
	if ($pt && $pt !== 'product' && !(is_array($pt) && in_array('product', $pt, true))) return $c;
	$ids = thh_ez_tt_ids();
	if (!$ids) return $c;
	global $wpdb;
	$c['join'] .= " LEFT JOIN (SELECT DISTINCT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode(',', array_map('intval', $ids)) . ")) thh_ez ON thh_ez.object_id = {$wpdb->posts}.ID";
	$c['orderby'] = '(thh_ez.object_id IS NULL) ASC' . (trim($c['orderby']) !== '' ? ', ' . $c['orderby'] : '');
	return $c;
}, 99, 2);

// 2. zoeksuggesties (FiboSearch)
add_filter('dgwt/wcas/search_results/output', function ($out) {
	if (empty($out['suggestions']) || !is_array($out['suggestions'])) return $out;
	$s = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
	if (mb_strlen($s) < 2) return $out;
	$sug = $out['suggestions'];
	$prod_idx = [];
	foreach ($sug as $i => $x) if (isset($x['type']) && $x['type'] === 'product') $prod_idx[] = $i;
	if (!$prod_idx) return $out;
	$aanwezig = array_map(function ($i) use ($sug) { return (int) ($sug[$i]['post_id'] ?? 0); }, $prod_idx);

	// eigen producten die bij de zoekterm passen
	$q = new WP_Query([
		'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 10, 'fields' => 'ids', 'no_found_rows' => true,
		's' => $s, 'search_columns' => ['post_title'], 'meta_key' => 'total_sales', 'orderby' => 'meta_value_num', 'order' => 'DESC',
		'tax_query' => [
			'relation' => 'AND',
			['relation' => 'OR', ['taxonomy' => 'product_brand', 'field' => 'term_id', 'terms' => [2176]], ['taxonomy' => 'brands', 'field' => 'term_id', 'terms' => [142]]],
			['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => ['exclude-from-search'], 'operator' => 'NOT IN'],
		],
	]);
	// alleen als alle zoekwoorden in de productnaam staan
	$woorden = array_filter(preg_split('/[\s\-]+/u', mb_strtolower($s)), function ($w) { return mb_strlen($w) > 1; });
	$eigen = array_values(array_filter($q->posts, function ($pid) use ($woorden) {
		$naam = mb_strtolower(str_replace('-', ' ', get_the_title($pid)));
		foreach ($woorden as $w) if (mb_strpos($naam, $w) === false) return false;
		return true;
	}));
	$eigen = array_slice($eigen, 0, 3);
	foreach ($aanwezig as $pid) if ($pid && !in_array($pid, $eigen, true) && thh_ez_is_eigen($pid)) $eigen[] = $pid;
	if (!$eigen) return $out;

	$bestaand = [];
	foreach ($prod_idx as $i) $bestaand[(int) ($sug[$i]['post_id'] ?? 0)] = $sug[$i];
	$nieuw = [];
	foreach ($eigen as $pid) {
		if (isset($bestaand[$pid])) { $nieuw[] = $bestaand[$pid]; continue; }
		$p = wc_get_product($pid);
		if (!$p) continue;
		$nieuw[] = ['post_id' => $pid, 'value' => $p->get_name(), 'url' => $p->get_permalink(), 'type' => 'product',
			'thumb_html' => '<img src="' . esc_url(wp_get_attachment_image_url($p->get_image_id(), [64, 64]) ?: wc_placeholder_img_src()) . '" alt="' . esc_attr($p->get_name()) . '" />'];
	}
	foreach ($prod_idx as $i) { $pid = (int) ($sug[$i]['post_id'] ?? 0); if (!in_array($pid, $eigen, true)) $nieuw[] = $sug[$i]; }
	$nieuw = array_slice($nieuw, 0, count($prod_idx));

	$res = []; $gedaan = false;
	foreach ($sug as $i => $x) {
		if (in_array($i, $prod_idx, true)) { if (!$gedaan) { foreach ($nieuw as $n) $res[] = $n; $gedaan = true; } continue; }
		$res[] = $x;
	}
	$out['suggestions'] = $res;
	return $out;
}, 20);
