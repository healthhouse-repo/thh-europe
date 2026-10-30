<?php
/**
 * THH POS-bewaker — houdt de kassa (WCPOS Pro) altijd aan.
 *
 * Oorzaak van de storingen (29 en 30 okt 2026): WCPOS Pro werd handmatig bijgewerkt via een zip-upload.
 * Elke upload kwam in een andere map terecht (woocommerce-pos-pro ↔ woocommerce-pos-pro-CRA6Nm9kVCCslgou),
 * waardoor WordPress de plugin als nieuw zag en uitgeschakeld liet. /pos/ viel dan terug op de homepage
 * en die doorverwijzing bleef in de cache hangen.
 *
 * Deze snippet:
 * 1. controleert (max. 1x per minuut) of er een WCPOS-plugin actief is; zo niet, dan zet hij de nieuwste
 *    geïnstalleerde versie weer aan (nooit twee tegelijk);
 * 2. ververst daarna de adresregels (rewrite rules) en leegt de Kinsta-cache;
 * 3. biedt een publieke statuscheck: GET /wp-json/thh/v1/pos-health (voor de automatische controle).
 */
if ( ! defined( 'ABSPATH' ) ) { return; }

function thh_posb_installed() {
	if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
	$out = array();
	foreach ( get_plugins() as $file => $data ) {
		$base = basename( $file );
		if ( 'woocommerce-pos-pro.php' === $base || 'woocommerce-pos.php' === $base ) {
			$out[ $file ] = array( 'version' => $data['Version'], 'pro' => ( 'woocommerce-pos-pro.php' === $base ) );
		}
	}
	return $out;
}

function thh_posb_active_file() {
	if ( ! function_exists( 'is_plugin_active' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
	foreach ( array_keys( thh_posb_installed() ) as $file ) {
		if ( is_plugin_active( $file ) ) { return $file; }
	}
	return '';
}

function thh_posb_heal() {
	if ( thh_posb_active_file() ) { return false; }
	$all = thh_posb_installed();
	if ( ! $all ) { return false; }
	// Voorkeur: Pro boven gratis, daarna hoogste versie.
	uksort( $all, function ( $a, $b ) use ( $all ) {
		if ( $all[ $a ]['pro'] !== $all[ $b ]['pro'] ) { return $all[ $a ]['pro'] ? -1 : 1; }
		return version_compare( $all[ $b ]['version'], $all[ $a ]['version'] );
	} );
	$file = array_key_first( $all );
	$res = activate_plugin( $file, '', false, true ); // silent: geen activation-redirect
	$ok = ! is_wp_error( $res );
	update_option( 'thh_posb_last', array(
		'at' => gmdate( 'c' ), 'file' => $file, 'version' => $all[ $file ]['version'],
		'ok' => $ok, 'error' => $ok ? '' : $res->get_error_message(),
	), false );
	if ( $ok ) { update_option( 'thh_posb_flush', 1, false ); }
	return $ok;
}

// 1. Controle, hooguit eens per minuut.
add_action( 'init', function () {
	if ( get_transient( 'thh_posb_tick' ) ) { return; }
	set_transient( 'thh_posb_tick', 1, 60 );
	thh_posb_heal();
}, 1 );

// 2. Na herstel: rewrite rules verversen (WCPOS registreert /pos/ op init) en Kinsta-cache legen.
add_action( 'init', function () {
	if ( ! get_option( 'thh_posb_flush' ) || ! thh_posb_active_file() ) { return; }
	delete_option( 'thh_posb_flush' );
	flush_rewrite_rules( false );
	global $kinsta_cache;
	if ( isset( $kinsta_cache ) && isset( $kinsta_cache->kinsta_cache_purge ) && method_exists( $kinsta_cache->kinsta_cache_purge, 'purge_complete_caches' ) ) {
		$kinsta_cache->kinsta_cache_purge->purge_complete_caches();
	}
	do_action( 'thh_pos_healed' );
}, 999 );

// Ook direct na een plugin-update of -upload controleren.
add_action( 'upgrader_process_complete', function () {
	delete_transient( 'thh_posb_tick' );
	thh_posb_heal();
}, 99 );

// 3. Statuscheck.
add_action( 'rest_api_init', function () {
	register_rest_route( 'thh/v1', '/pos-health', array(
		'methods' => 'GET',
		'permission_callback' => '__return_true',
		'callback' => function () {
			thh_posb_heal();
			$file = thh_posb_active_file();
			$rules = get_option( 'rewrite_rules' );
			$has_rule = false;
			if ( is_array( $rules ) ) {
				foreach ( array_keys( $rules ) as $re ) { if ( 0 === strpos( $re, 'pos' ) || false !== strpos( $re, '^pos' ) ) { $has_rule = true; break; } }
			}
			$all = thh_posb_installed();
			$r = new WP_REST_Response( array(
				'ok'          => (bool) $file && $has_rule,
				'active'      => (bool) $file,
				'version'     => $file && isset( $all[ $file ] ) ? $all[ $file ]['version'] : null,
				'pos_route'   => $has_rule,
				'copies'      => count( $all ),
				'last_heal'   => get_option( 'thh_posb_last' ) ?: null,
				'checked_at'  => gmdate( 'c' ),
			) );
			$r->header( 'Cache-Control', 'no-store, max-age=0' );
			return $r;
		},
	) );
} );

// Waarschuwing in wp-admin op de pluginpagina.
add_action( 'admin_notices', function () {
	$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $s || 'plugins' !== $s->id ) { return; }
	echo '<div class="notice notice-warning"><p><strong>Kassa (WCPOS Pro):</strong> werk deze plugin bij via de knop <em>Bijwerken</em>, niet door een nieuwe zip te uploaden. De POS-bewaker zet de kassa automatisch weer aan als hij uit staat.</p></div>';
} );
