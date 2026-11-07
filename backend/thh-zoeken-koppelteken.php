<?php
/**
 * THH — Zoeken zonder koppelteken/spatie
 *
 * "celltech" vindt "Cell-Tech", "preworkout" vindt "Pre-Workout", "c4ultimate" vindt "C4 Ultimate".
 * Werkt voor de sitezoeker (FiboSearch gratis, via WP_Query/posts_search), de gewone WooCommerce-
 * zoekpagina en de kassa-zoekopdrachten die via WP_Query lopen.
 * Alleen voor zoektermen van minimaal 4 tekens; vergelijkt de titel (en SKU) met koppeltekens,
 * spaties en punten verwijderd.
 */
if (!defined('ABSPATH')) return;

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
	// $search heeft de vorm " AND (((...)))" — de extra OR binnen de buitenste haakjes zetten
	$pos = strrpos($search, ')');
	if ($pos === false) return $search;
	return substr($search, 0, $pos) . $extra . substr($search, $pos);
}, 999, 2);
