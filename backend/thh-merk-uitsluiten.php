<?php
/**
 * THH: merken uitsluiten van de homepage (o.a. Jennah i.v.m. dispuut).
 * - Productlijsten op de homepage en /home-test/ (Elementor/WooCommerce-widgets, WP_Query, wc_get_products).
 * - Merk-link in het megamenu (sitebreed).
 * Lijst aanpassen: option thh_home_merk_uit (array met merk-slugs), standaard ['jennah'].
 */
if (!defined('ABSPATH')) return;

function thh_home_merk_uit() {
	$l = get_option('thh_home_merk_uit', ['jennah']);
	return array_values(array_filter(array_map('sanitize_title', (array) $l)));
}
function thh_home_is_home() {
	return !is_admin() && (is_front_page() || is_page(91746));
}
function thh_home_brand_tax() {
	foreach (['product_brand', 'brands', 'pa_merk'] as $tx) if (taxonomy_exists($tx)) return $tx;
	return 'product_brand';
}

/* Alle secundaire productquery's op de homepage */
add_action('pre_get_posts', function ($q) {
	if ($q->is_main_query() || !thh_home_is_home()) return;
	$pt = (array) $q->get('post_type');
	if (!in_array('product', $pt, true) && !in_array('product_variation', $pt, true)) return;
	$uit = thh_home_merk_uit(); if (!$uit) return;
	$tq = (array) $q->get('tax_query');
	$tq[] = ['taxonomy' => thh_home_brand_tax(), 'field' => 'slug', 'terms' => $uit, 'operator' => 'NOT IN'];
	$q->set('tax_query', $tq);
}, 99);

/* WooCommerce [products]-shortcode (o.a. Elementor productwidget) */
add_filter('woocommerce_shortcode_products_query', function ($args) {
	if (!thh_home_is_home()) return $args;
	$uit = thh_home_merk_uit(); if (!$uit) return $args;
	$args['tax_query'][] = ['taxonomy' => thh_home_brand_tax(), 'field' => 'slug', 'terms' => $uit, 'operator' => 'NOT IN'];
	return $args;
}, 99);

/* wc_get_products() (o.a. nieuwe homepage) */
add_filter('woocommerce_product_data_store_cpt_get_products_query', function ($query, $qv) {
	if (!thh_home_is_home()) return $query;
	$uit = thh_home_merk_uit(); if (!$uit) return $query;
	$query['tax_query'][] = ['taxonomy' => thh_home_brand_tax(), 'field' => 'slug', 'terms' => $uit, 'operator' => 'NOT IN'];
	return $query;
}, 99, 2);

/* Megamenu-merkenlijst: link naar uitgesloten merken verbergen (sitebreed) */
add_action('wp_head', function () {
	$uit = thh_home_merk_uit(); if (!$uit) return;
	$sel = [];
	foreach ($uit as $s) { $sel[] = '.thh-xxlh-brandgrid a[href*="/merk/' . esc_attr($s) . '/"]'; $sel[] = '.thh-xxlh-brandgrid a[href$="/merk/' . esc_attr($s) . '"]'; }
	echo '<style id="thh-merk-uit">' . implode(',', $sel) . '{display:none!important}</style>';
}, 99);
