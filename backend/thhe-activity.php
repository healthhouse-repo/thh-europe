<?php
/**
 * THH Europe — klantactiviteit (tracking + lead-API)
 *
 * Wat het doet:
 * 1. POST thhe/v1/track            ingelogde klant stuurt een batch gedragsevents (pagina's, producten, zoeken, winkelwagen, klikken, actieve tijd).
 * 2. POST thhe/v1/admin/activity   beheer (thheurope.com/beheer.html): overzicht per lead + tijdlijn.
 * 3. POST thhe/v1/admin/activity/key   beheer: API-sleutel tonen of opnieuw aanmaken.
 * 4. GET/POST thhe/v1/leads/activity   extern (bijv. Claude): zelfde data, met header X-THHE-Api-Key.
 *
 * Bouwt voort op het B2B-portaal (snippet "THH Europe — B2B-portaal API v1"): thhe_tbl, thhe_token_account,
 * thhe_perm_admin, thhe_acc_get, thhe_order_ids en de bestaande logtabel (login, offerteaanvraag, order, betaling, account_*).
 */
if ( ! defined( 'ABSPATH' ) ) { return; }

define( 'THHE_ACT_DB_VER', '1' );

function thhe_act_install() {
	if ( get_option( 'thhe_act_db_ver' ) === THHE_ACT_DB_VER ) { return; }
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t = $wpdb->prefix . 'thhe_activity';
	dbDelta( "CREATE TABLE $t (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 at datetime NOT NULL,
 account_id bigint(20) unsigned NOT NULL DEFAULT 0,
 session varchar(40) NOT NULL DEFAULT '',
 event varchar(40) NOT NULL DEFAULT '',
 ref varchar(190) NOT NULL DEFAULT '',
 detail text NULL,
 page varchar(190) NOT NULL DEFAULT '',
 device varchar(20) NOT NULL DEFAULT '',
 PRIMARY KEY  (id),
 KEY account_at (account_id, at),
 KEY at (at)
) " . $wpdb->get_charset_collate() . ';' );
	update_option( 'thhe_act_db_ver', THHE_ACT_DB_VER, false );
}

function thhe_act_events() {
	return array( 'session_start', 'page', 'product_view', 'search', 'brand', 'category', 'cart_add', 'cart_remove', 'cart_open',
		'checkout_view', 'checkout_submit', 'quote_request', 'payment_method', 'click', 'download', 'contact_click', 'active', 'invoice_view', 'order_view', 'error' );
}

function thhe_act_device( $ua ) {
	$ua = strtolower( (string) $ua );
	if ( preg_match( '/ipad|tablet/', $ua ) ) { return 'tablet'; }
	if ( preg_match( '/mobi|android|iphone/', $ua ) ) { return 'mobiel'; }
	return 'desktop';
}

function thhe_act_api_key( $regen = false ) {
	$k = get_option( 'thhe_act_api_key' );
	if ( $regen || ! $k ) {
		$k = 'thhe_' . bin2hex( random_bytes( 24 ) );
		update_option( 'thhe_act_api_key', $k, false );
		if ( function_exists( 'thhe_log' ) ) { thhe_log( 'activiteit_api_sleutel', '', $regen ? 'opnieuw aangemaakt' : 'aangemaakt' ); }
	}
	return $k;
}

function thhe_act_perm_key( $req ) {
	$given = (string) $req->get_header( 'x-thhe-api-key' );
	$k = get_option( 'thhe_act_api_key' );
	if ( $k && '' !== $given && hash_equals( $k, $given ) ) { return true; }
	return new WP_Error( 'thhe_auth', 'Ongeldige of ontbrekende API-sleutel (header X-THHE-Api-Key)', array( 'status' => 401 ) );
}

/* ---------- 1. events ontvangen ---------- */
function thhe_act_track( $req ) {
	thhe_act_install();
	$acc = thhe_token_account( $req );
	if ( ! $acc ) { return new WP_Error( 'thhe_auth', 'Niet ingelogd', array( 'status' => 401 ) ); }
	if ( 'admin' === $acc->role ) { return array( 'ok' => true, 'stored' => 0, 'skipped' => 'admin' ); }
	// eenvoudige limiet: max 600 events per account per uur
	$rk = 'thhe_act_rl_' . (int) $acc->id;
	$cnt = (int) get_transient( $rk );
	if ( $cnt > 600 ) { return array( 'ok' => true, 'stored' => 0, 'skipped' => 'limiet' ); }
	$p = $req->get_json_params();
	$events = isset( $p['events'] ) && is_array( $p['events'] ) ? array_slice( $p['events'], 0, 60 ) : array();
	$session = substr( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( isset( $p['session'] ) ? $p['session'] : '' ) ), 0, 40 );
	$device = thhe_act_device( $req->get_header( 'user-agent' ) );
	$allowed = thhe_act_events();
	global $wpdb;
	$t = $wpdb->prefix . 'thhe_activity';
	$stored = 0;
	$now = time();
	foreach ( $events as $e ) {
		if ( ! is_array( $e ) ) { continue; }
		$ev = isset( $e['e'] ) ? sanitize_key( $e['e'] ) : '';
		if ( ! in_array( $ev, $allowed, true ) ) { continue; }
		$ts = isset( $e['t'] ) ? (int) ( $e['t'] / 1000 ) : $now;
		if ( $ts > $now + 60 || $ts < $now - 86400 ) { $ts = $now; }
		$detail = isset( $e['d'] ) && is_array( $e['d'] ) ? $e['d'] : null;
		if ( $detail ) {
			$clean = array();
			foreach ( array_slice( $detail, 0, 12, true ) as $k => $v ) {
				if ( is_scalar( $v ) ) { $clean[ substr( sanitize_key( $k ), 0, 30 ) ] = is_string( $v ) ? substr( wp_strip_all_tags( $v ), 0, 200 ) : $v; }
			}
			$detail = $clean ? wp_json_encode( $clean ) : null;
		}
		$wpdb->insert( $t, array(
			'at' => gmdate( 'Y-m-d H:i:s', $ts ),
			'account_id' => (int) $acc->id,
			'session' => $session,
			'event' => $ev,
			'ref' => substr( wp_strip_all_tags( (string) ( isset( $e['r'] ) ? $e['r'] : '' ) ), 0, 190 ),
			'detail' => $detail,
			'page' => substr( wp_strip_all_tags( (string) ( isset( $e['p'] ) ? $e['p'] : '' ) ), 0, 190 ),
			'device' => $device,
		) );
		$stored++;
	}
	set_transient( $rk, $cnt + $stored, HOUR_IN_SECONDS );
	return array( 'ok' => true, 'stored' => $stored );
}

/* ---------- 2. samenvatting per lead ---------- */
function thhe_act_hours_since( $dt ) {
	if ( ! $dt ) { return null; }
	$ts = strtotime( $dt . ' UTC' );
	return $ts ? round( ( time() - $ts ) / 3600, 1 ) : null;
}

function thhe_act_lead( $a, $since, $with_timeline, $tl_limit ) {
	global $wpdb;
	$ta = $wpdb->prefix . 'thhe_activity';
	$tl = thhe_tbl( 'log' );
	$id = (int) $a->id;

	$counts = array();
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT event, COUNT(*) n FROM $ta WHERE account_id=%d GROUP BY event", $id ) ) as $r ) { $counts[ $r->event ] = (int) $r->n; }
	$sessions = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT session) FROM $ta WHERE account_id=%d AND session<>''", $id ) );
	$last_seen = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(at) FROM $ta WHERE account_id=%d", $id ) );
	$first_seen = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(at) FROM $ta WHERE account_id=%d", $id ) );
	$active_min = (int) ( isset( $counts['active'] ) ? $counts['active'] : 0 ); // 1 'active'-tik = 1 actieve minuut

	$logc = array();
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT action, COUNT(*) n, MAX(at) last FROM $tl WHERE account_id=%d GROUP BY action", $id ) ) as $r ) { $logc[ $r->action ] = array( (int) $r->n, $r->last ); }
	$logins = isset( $logc['login'] ) ? $logc['login'][0] : 0;
	$quotes = isset( $logc['offerteaanvraag'] ) ? $logc['offerteaanvraag'][0] : 0;
	$last_quote = isset( $logc['offerteaanvraag'] ) ? $logc['offerteaanvraag'][1] : null;

	// orders + betalingen uit WooCommerce
	$orders = array(); $paid = 0; $revenue = 0.0; $unpaid = array(); $failed = 0; $last_order = null;
	if ( function_exists( 'thhe_order_ids' ) ) {
		foreach ( thhe_order_ids( array( 'account' => $id, 'limit' => 50 ) ) as $oid ) {
			$o = wc_get_order( $oid );
			if ( ! $o ) { continue; }
			$st = $o->get_status();
			$created = $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d H:i:s' ) : '';
			$is_paid = (bool) $o->get_date_paid() || in_array( $st, array( 'processing', 'completed' ), true );
			if ( $is_paid ) { $paid++; $revenue += (float) $o->get_total(); }
			if ( in_array( $st, array( 'pending', 'on-hold' ), true ) && ! $is_paid ) { $unpaid[] = $o->get_order_number(); }
			if ( 'failed' === $st ) { $failed++; }
			if ( ! $last_order || $created > $last_order ) { $last_order = $created; }
			$orders[] = array( 'id' => $o->get_id(), 'no' => $o->get_order_number(), 'status' => $st, 'total' => (float) $o->get_total(), 'paid' => $is_paid, 'created' => $created, 'method' => $o->get_payment_method_title() );
		}
	}

	// producten met de meeste aandacht
	$top = $wpdb->get_results( $wpdb->prepare( "SELECT ref, COUNT(*) n FROM $ta WHERE account_id=%d AND event IN ('product_view','cart_add') AND ref<>'' GROUP BY ref ORDER BY n DESC LIMIT 8", $id ) );
	$searches = $wpdb->get_col( $wpdb->prepare( "SELECT ref FROM $ta WHERE account_id=%d AND event='search' AND ref<>'' ORDER BY at DESC LIMIT 10", $id ) );

	// fase in de funnel
	$stage = 'aangemeld';
	if ( 'approved' === $a->status ) { $stage = 'goedgekeurd'; }
	if ( $logins || $a->last_login ) { $stage = 'ingelogd'; }
	if ( ! empty( $counts['product_view'] ) || ! empty( $counts['page'] ) ) { $stage = 'bekijkt'; }
	if ( ! empty( $counts['cart_add'] ) ) { $stage = 'winkelwagen'; }
	if ( $quotes ) { $stage = 'offerte_aangevraagd'; }
	if ( $orders ) { $stage = 'besteld'; }
	if ( $paid ) { $stage = 'betaald'; }
	if ( in_array( $a->status, array( 'rejected', 'blocked' ), true ) ) { $stage = $a->status; }

	// signalen: waar is actie nodig
	$flags = array();
	$h_appr = thhe_act_hours_since( $a->approved_at );
	$h_seen = thhe_act_hours_since( $last_seen ? $last_seen : $a->last_login );
	if ( 'pending' === $a->status ) { $flags[] = array( 'code' => 'wacht_op_goedkeuring', 'tekst' => 'Account wacht nog op goedkeuring', 'sinds_uur' => thhe_act_hours_since( $a->created_at ) ); }
	if ( 'approved' === $a->status && ! $logins && ! $a->last_login && null !== $h_appr && $h_appr >= 24 ) { $flags[] = array( 'code' => 'nooit_ingelogd', 'tekst' => 'Goedgekeurd maar nog nooit ingelogd', 'sinds_uur' => $h_appr ); }
	if ( ( $logins || $a->last_login ) && ! $quotes && ! $orders && null !== $h_seen && $h_seen >= 72 ) { $flags[] = array( 'code' => 'ingelogd_geen_actie', 'tekst' => 'Wel ingelogd, nog geen offerte of bestelling', 'sinds_uur' => $h_seen ); }
	if ( ! empty( $counts['cart_add'] ) && ! $quotes && ! $orders && null !== $h_seen && $h_seen >= 24 ) { $flags[] = array( 'code' => 'verlaten_winkelwagen', 'tekst' => 'Producten in winkelwagen gezet, niet afgerond', 'sinds_uur' => $h_seen ); }
	if ( $quotes && ! $orders && $last_quote && thhe_act_hours_since( $last_quote ) >= 48 ) { $flags[] = array( 'code' => 'offerte_niet_besteld', 'tekst' => 'Offerte aangevraagd, nog niet besteld', 'sinds_uur' => thhe_act_hours_since( $last_quote ) ); }
	if ( $unpaid ) { $flags[] = array( 'code' => 'onbetaald', 'tekst' => 'Bestelling(en) nog niet betaald: ' . implode( ', ', $unpaid ), 'sinds_uur' => thhe_act_hours_since( $last_order ) ); }
	if ( $failed ) { $flags[] = array( 'code' => 'betaling_mislukt', 'tekst' => $failed . ' mislukte betaling(en)' ); }
	if ( $paid && null !== $h_seen && $h_seen >= 24 * 45 ) { $flags[] = array( 'code' => 'klant_inactief', 'tekst' => 'Betalende klant al meer dan 45 dagen niet actief', 'sinds_uur' => $h_seen ); }

	$out = array(
		'id' => $id, 'email' => $a->email, 'company' => $a->company, 'contact' => $a->contact, 'phone' => $a->phone, 'country' => $a->country,
		'lang' => $a->lang, 'status' => $a->status, 'is_test' => (int) $a->is_test,
		'created_at' => $a->created_at, 'approved_at' => $a->approved_at, 'last_login' => $a->last_login,
		'first_seen' => $first_seen, 'last_seen' => $last_seen,
		'stage' => $stage, 'flags' => $flags,
		'counts' => array(
			'logins' => $logins, 'sessions' => $sessions, 'active_minutes' => $active_min,
			'page_views' => isset( $counts['page'] ) ? $counts['page'] : 0,
			'product_views' => isset( $counts['product_view'] ) ? $counts['product_view'] : 0,
			'searches' => isset( $counts['search'] ) ? $counts['search'] : 0,
			'cart_adds' => isset( $counts['cart_add'] ) ? $counts['cart_add'] : 0,
			'checkout_views' => isset( $counts['checkout_view'] ) ? $counts['checkout_view'] : 0,
			'contact_clicks' => isset( $counts['contact_click'] ) ? $counts['contact_click'] : 0,
			'quote_requests' => $quotes, 'orders' => count( $orders ), 'paid_orders' => $paid, 'revenue' => round( $revenue, 2 ),
		),
		'top_products' => array_map( function ( $r ) { return array( 'product' => $r->ref, 'n' => (int) $r->n ); }, $top ? $top : array() ),
		'recent_searches' => $searches ? $searches : array(),
		'orders' => $orders,
	);

	if ( $with_timeline ) {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"(SELECT at, 'web' src, event, ref, detail, page, device FROM $ta WHERE account_id=%d AND at>=%s AND event<>'active')
			 UNION ALL
			 (SELECT at, 'systeem' src, action event, ref, detail, '' page, '' device FROM $tl WHERE account_id=%d AND at>=%s)
			 ORDER BY at DESC LIMIT %d", $id, $since, $id, $since, $tl_limit ) );
		$out['timeline'] = array_map( function ( $r ) {
			$d = $r->detail; $j = json_decode( (string) $d, true );
			return array( 'at' => $r->at, 'bron' => $r->src, 'event' => $r->event, 'ref' => $r->ref, 'detail' => is_array( $j ) ? $j : $d, 'page' => $r->page, 'device' => $r->device );
		}, $rows ? $rows : array() );
	}
	return $out;
}

function thhe_act_report( $req ) {
	thhe_act_install();
	global $wpdb;
	$p = array_merge( (array) $req->get_query_params(), (array) $req->get_json_params() );
	$days = isset( $p['days'] ) ? max( 1, min( 365, (int) $p['days'] ) ) : 30;
	$since = ! empty( $p['since'] ) && strtotime( $p['since'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $p['since'] ) ) : gmdate( 'Y-m-d H:i:s', time() - $days * 86400 );
	$ta = thhe_tbl( 'accounts' );
	$where = "role='customer'";
	$args = array();
	if ( ! empty( $p['account'] ) ) {
		if ( is_numeric( $p['account'] ) ) { $where .= ' AND id=%d'; $args[] = (int) $p['account']; }
		else { $where .= ' AND email=%s'; $args[] = sanitize_email( $p['account'] ); }
	}
	if ( empty( $p['include_test'] ) ) { $where .= ' AND is_test=0'; }
	if ( ! empty( $p['status'] ) ) { $where .= ' AND status=%s'; $args[] = sanitize_key( $p['status'] ); }
	$limit = isset( $p['limit'] ) ? max( 1, min( 500, (int) $p['limit'] ) ) : 200;
	$sql = "SELECT * FROM $ta WHERE $where ORDER BY COALESCE(approved_at, created_at) DESC LIMIT $limit";
	$accs = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql );
	$timeline = ! empty( $p['account'] ) || ! empty( $p['timeline'] );
	$tl_limit = isset( $p['timeline_limit'] ) ? max( 10, min( 1000, (int) $p['timeline_limit'] ) ) : 200;
	$leads = array();
	foreach ( (array) $accs as $a ) { $leads[] = thhe_act_lead( $a, $since, $timeline, $tl_limit ); }
	if ( ! empty( $p['only_flagged'] ) ) { $leads = array_values( array_filter( $leads, function ( $l ) { return ! empty( $l['flags'] ); } ) ); }
	return array(
		'generated_at' => gmdate( 'c' ), 'timezone' => 'UTC', 'since' => $since,
		'stages' => array( 'aangemeld', 'goedgekeurd', 'ingelogd', 'bekijkt', 'winkelwagen', 'offerte_aangevraagd', 'besteld', 'betaald' ),
		'count' => count( $leads ), 'flagged' => count( array_filter( $leads, function ( $l ) { return ! empty( $l['flags'] ); } ) ),
		'leads' => $leads,
	);
}

function thhe_act_key_route( $req ) {
	$p = (array) $req->get_json_params();
	$k = thhe_act_api_key( ! empty( $p['regenerate'] ) );
	return array( 'key' => $k, 'base_url' => rest_url( 'thhe/v1/leads/activity' ), 'header' => 'X-THHE-Api-Key' );
}

add_action( 'rest_api_init', function () {
	if ( ! function_exists( 'thhe_token_account' ) ) { return; } // portaal-snippet moet actief zijn
	register_rest_route( 'thhe/v1', '/track', array( 'methods' => 'POST', 'callback' => 'thhe_act_track', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'thhe/v1', '/admin/activity', array( 'methods' => 'POST', 'callback' => 'thhe_act_report', 'permission_callback' => 'thhe_perm_admin' ) );
	register_rest_route( 'thhe/v1', '/admin/activity/key', array( 'methods' => 'POST', 'callback' => 'thhe_act_key_route', 'permission_callback' => 'thhe_perm_admin' ) );
	register_rest_route( 'thhe/v1', '/leads/activity', array( 'methods' => array( 'GET', 'POST' ), 'callback' => 'thhe_act_report', 'permission_callback' => 'thhe_act_perm_key' ) );
} );

// opruimen: activiteit ouder dan 18 maanden
add_action( 'thhe_act_cleanup', function () {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'thhe_activity WHERE at < %s', gmdate( 'Y-m-d H:i:s', time() - 548 * 86400 ) ) );
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'thhe_act_cleanup' ) ) { wp_schedule_event( time() + 3600, 'daily', 'thhe_act_cleanup' ); }
} );
