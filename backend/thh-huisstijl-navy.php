<?php
/**
 * THH huisstijl: oud navy #021152 → nieuw navy #14204A, overal.
 * 1. Scan:      GET  /wp-json/thh/v1/navy/scan      (beheerder) — waar komt de oude kleur voor
 * 2. Vervangen: POST /wp-json/thh/v1/navy/apply     (beheerder) — Elementor-data + customizer; back-up per post in _thh_navy_backup
 * 3. Terug:     POST /wp-json/thh/v1/navy/revert
 * 4. Vangnet:   CSS-variabelen en theme-regels die de oude kleur hardcoden worden live overschreven (wp_head, laag in de cascade
 *               maar met !important waar het om de bekende regels gaat).
 */
if (!defined('ABSPATH')) return;

define('THH_NAVY_OLD', '#021152');
define('THH_NAVY_NEW', '#14204A');

function thh_navy_variants($hex) {
	$h = ltrim($hex, '#');
	$r = hexdec(substr($h, 0, 2)); $g = hexdec(substr($h, 2, 2)); $b = hexdec(substr($h, 4, 2));
	return ['#' . strtolower($h), '#' . strtoupper($h), "rgb($r, $g, $b)", "rgb($r,$g,$b)", "rgba($r, $g, $b,", "rgba($r,$g,$b,"];
}
function thh_navy_swap($s) {
	$old = thh_navy_variants(THH_NAVY_OLD); $new = thh_navy_variants(THH_NAVY_NEW);
	return str_replace($old, $new, $s);
}
function thh_navy_posts() {
	global $wpdb;
	$like = '%' . $wpdb->esc_like(ltrim(THH_NAVY_OLD, '#')) . '%';
	return $wpdb->get_col($wpdb->prepare("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_elementor_data','_elementor_page_settings') AND meta_value LIKE %s", $like));
}

add_action('rest_api_init', function () {
	$can = function () { return current_user_can('manage_options'); };
	register_rest_route('thh/v1', '/navy/scan', ['methods' => 'GET', 'permission_callback' => $can, 'callback' => function () {
		$ids = thh_navy_posts(); $out = [];
		foreach ($ids as $id) {
			$p = get_post($id); if (!$p) continue;
			$n = substr_count(strtolower((string) get_post_meta($id, '_elementor_data', true)), strtolower(THH_NAVY_OLD));
			$out[] = ['id' => (int) $id, 'type' => $p->post_type, 'status' => $p->post_status, 'title' => $p->post_title, 'hits' => $n, 'backup' => (bool) get_post_meta($id, '_thh_navy_backup', true)];
		}
		$kit = get_option('elementor_active_kit');
		$ks = $kit ? get_post_meta($kit, '_elementor_page_settings', true) : null;
		return ['posts' => $out, 'kit' => $kit, 'kit_has_old' => $ks ? stripos(wp_json_encode($ks), ltrim(THH_NAVY_OLD, '#')) !== false : null, 'theme_mods_has_old' => stripos(wp_json_encode(get_theme_mods()), ltrim(THH_NAVY_OLD, '#')) !== false];
	}]);
	register_rest_route('thh/v1', '/navy/apply', ['methods' => 'POST', 'permission_callback' => $can, 'callback' => function () {
		$done = [];
		foreach (thh_navy_posts() as $id) {
			foreach (['_elementor_data', '_elementor_page_settings'] as $key) {
				$raw = get_post_meta($id, $key, true);
				if (!$raw) continue;
				$json = is_string($raw) ? $raw : wp_json_encode($raw);
				if (stripos($json, ltrim(THH_NAVY_OLD, '#')) === false) continue;
				if (!get_post_meta($id, '_thh_navy_backup_' . $key, true)) add_post_meta($id, '_thh_navy_backup_' . $key, is_string($raw) ? wp_slash($raw) : $raw, true);
				update_post_meta($id, '_thh_navy_backup', 1);
				if (is_string($raw)) update_post_meta($id, $key, wp_slash(thh_navy_swap($raw)));
				else update_post_meta($id, $key, json_decode(thh_navy_swap(wp_json_encode($raw)), true));
				$done[] = (int) $id . ':' . $key;
			}
		}
		$mods = get_theme_mods(); $mj = wp_json_encode($mods);
		if (stripos($mj, ltrim(THH_NAVY_OLD, '#')) !== false) {
			if (!get_option('_thh_navy_backup_theme_mods')) add_option('_thh_navy_backup_theme_mods', $mods, '', false);
			foreach (json_decode(thh_navy_swap($mj), true) as $k => $v) set_theme_mod($k, $v);
			$done[] = 'theme_mods';
		}
		if (class_exists('\Elementor\Plugin')) \Elementor\Plugin::$instance->files_manager->clear_cache();
		if (function_exists('autoptimize_flush_pagecache')) autoptimize_flush_pagecache();
		if (class_exists('autoptimizeCache')) autoptimizeCache::clearall();
		global $kinsta_cache;
		if (isset($kinsta_cache->kinsta_cache_purge) && method_exists($kinsta_cache->kinsta_cache_purge, 'purge_complete_caches')) $kinsta_cache->kinsta_cache_purge->purge_complete_caches();
		update_option('thh_navy_applied', current_time('mysql'), false);
		return ['changed' => $done];
	}]);
	register_rest_route('thh/v1', '/navy/revert', ['methods' => 'POST', 'permission_callback' => $can, 'callback' => function () {
		global $wpdb; $done = [];
		$ids = $wpdb->get_col("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thh_navy_backup'");
		foreach ($ids as $id) {
			foreach (['_elementor_data', '_elementor_page_settings'] as $key) {
				$b = get_post_meta($id, '_thh_navy_backup_' . $key, true);
				if ($b) { update_post_meta($id, $key, is_string($b) ? wp_slash($b) : $b); $done[] = (int) $id . ':' . $key; }
			}
		}
		$m = get_option('_thh_navy_backup_theme_mods');
		if ($m) { foreach ($m as $k => $v) set_theme_mod($k, $v); $done[] = 'theme_mods'; }
		if (class_exists('\Elementor\Plugin')) \Elementor\Plugin::$instance->files_manager->clear_cache();
		if (class_exists('autoptimizeCache')) autoptimizeCache::clearall();
		delete_option('thh_navy_applied');
		return ['reverted' => $done];
	}]);
});

// Vangnet voor hardgecodeerde kleuren in thema-CSS en snippets (werkt direct, ook vóór de data-vervanging).
add_action('wp_head', function () {
	if (is_admin()) return;
	echo '<style id="thh-navy">:root{--xh-bar:' . THH_NAVY_NEW . '}.thh-cb{--navy:' . THH_NAVY_NEW . '}footer{background-color:' . THH_NAVY_NEW . '}footer.thf2-site{background:' . THH_NAVY_NEW . '!important;padding:0!important}</style>';
}, 100);
