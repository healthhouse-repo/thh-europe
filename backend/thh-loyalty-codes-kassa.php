<?php
/**
 * THH Loyalty — eenmalige loyaltycodes aan de kassa en in de webshop (16 nov 2026)
 *
 * Loyaltycodes (THH-V-xxxxxx) staan in Supabase (tabel vouchers) en als WooCommerce-kortingscode.
 * Deze snippet houdt die twee gelijk:
 *
 * 1. Bij invoeren van de code (kassa of webshop) controleert hij in Supabase of de code nog
 *    actief is. Al gebruikt of verlopen = duidelijke melding, de korting gaat er niet af.
 * 2. De e-mailcontrole van WooCommerce blijft staan zolang de oude inwisselkoppeling van het
 *    klantportaal codes zonder inlog teruggeeft (zie voorstel loyalty in de kassa).
 * 3. Zodra de order betaald is, boekt hij de code af in Supabase met ordernummer, winkel en
 *    medewerker (thh_use_voucher). Annulering, mislukte betaling of volledige terugbetaling zet
 *    de code weer op actief (thh_release_voucher).
 *
 * Een korting groter dan de bon: WooCommerce laat het totaal nooit onder € 0 komen.
 * Het restbedrag wordt als ordernotitie vastgelegd (beslissing restcode volgt).
 *
 * Gebruikt thh_loy_api() uit snippet "THH Loyalty - punten in webshop" (Supabase-koppeling).
 * Staat die snippet uit, dan doet deze niets en werkt alles zoals voorheen.
 * Uitzetten = deze snippet deactiveren.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'thh_lc_is_code' ) ) {
	function thh_lc_is_code( $code ) {
		return (bool) preg_match( '/^THH-V-[A-Z0-9]{4,}$/i', trim( (string) $code ) );
	}
}

if ( ! function_exists( 'thh_lc_ready' ) ) {
	function thh_lc_ready() {
		return function_exists( 'thh_loy_api' ) && defined( 'THH_LOY_URL' );
	}
}

if ( ! function_exists( 'thh_lc_is_pos_request' ) ) {
	function thh_lc_is_pos_request() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		return false !== stripos( $uri, '/wcpos/' ) || false !== stripos( $uri, 'wcpos' ) || ! empty( $_SERVER['HTTP_X_WCPOS'] );
	}
}

if ( ! function_exists( 'thh_lc_check' ) ) {
	/** Status van een code in Supabase, 30 s in cache. null = onbekend/onbereikbaar. */
	function thh_lc_check( $code, $fresh = false ) {
		$code = strtoupper( trim( $code ) );
		$key  = 'thh_lc_' . md5( $code );
		if ( ! $fresh ) {
			$c = get_transient( $key );
			if ( is_array( $c ) ) { return $c; }
		}
		if ( ! thh_lc_ready() ) { return null; }
		$r = thh_loy_api( 'rpc/thh_check_voucher', 'POST', array( 'p_code' => $code ) );
		if ( empty( $r['ok'] ) || ! is_array( $r['data'] ) ) { return null; }
		set_transient( $key, $r['data'], 30 );
		return $r['data'];
	}
}

if ( ! function_exists( 'thh_lc_order_context' ) ) {
	function thh_lc_order_context( $order ) {
		$store_id = (int) $order->get_meta( '_pos_store' );
		$user_id  = (int) $order->get_meta( '_pos_user' );
		$branch   = $store_id ? trim( wp_strip_all_tags( get_the_title( $store_id ) ) ) : 'Webshop';
		if ( $store_id && '' === $branch ) { $branch = 'Kassa ' . $store_id; }
		$actor = '';
		if ( $user_id ) {
			$u     = get_userdata( $user_id );
			$actor = $u ? $u->user_login : (string) $user_id;
		}
		return array( 'pos' => (bool) $store_id, 'branch' => $branch, 'actor' => $actor );
	}
}

/* 1. Controle bij invoeren van de code. */
add_filter( 'woocommerce_coupon_is_valid', function ( $valid, $coupon ) {
	if ( ! $valid || ! is_a( $coupon, 'WC_Coupon' ) || ! thh_lc_is_code( $coupon->get_code() ) ) { return $valid; }
	$s = thh_lc_check( $coupon->get_code(), true );
	if ( null === $s ) { return $valid; } // Supabase onbereikbaar: WooCommerce (1x bruikbaar) beslist.
	if ( empty( $s['found'] ) ) { return $valid; } // Oude code die niet in Supabase staat.
	$status = isset( $s['status'] ) ? $s['status'] : '';
	if ( 'used' === $status ) {
		$waar = ! empty( $s['used_at_branch'] ) ? ' (' . $s['used_at_branch'] . ')' : '';
		throw new Exception( 'Deze loyaltycode is al gebruikt' . $waar . '.' );
	}
	if ( 'expired' === $status ) { throw new Exception( 'Deze loyaltycode is verlopen.' ); }
	if ( 'active' !== $status ) { throw new Exception( 'Deze loyaltycode is niet geldig.' ); }
	return $valid;
}, 20, 2 );

/* 3. Afboeken na betaling, terugzetten bij annulering of terugbetaling. */
if ( ! function_exists( 'thh_lc_sync_order' ) ) {
	function thh_lc_sync_order( $order_id, $use ) {
		if ( ! thh_lc_ready() ) { return; }
		$order = wc_get_order( $order_id );
		if ( ! $order ) { return; }
		$codes = array_filter( $order->get_coupon_codes(), 'thh_lc_is_code' );
		if ( ! $codes ) { return; }
		$done = $order->get_meta( '_thh_lc_used' );
		$done = is_array( $done ) ? $done : array();
		$ctx  = thh_lc_order_context( $order );
		foreach ( $codes as $code ) {
			$code = strtoupper( $code );
			if ( $use && ! in_array( $code, $done, true ) ) {
				$r = thh_loy_api( 'rpc/thh_use_voucher', 'POST', array(
					'p_code'     => $code,
					'p_order_id' => (string) $order->get_id(),
					'p_branch'   => $ctx['branch'],
					'p_actor'    => $ctx['actor'],
					'p_channel'  => $ctx['pos'] ? 'kassa' : 'webshop',
				) );
				$d = ( ! empty( $r['ok'] ) && is_array( $r['data'] ) ) ? $r['data'] : array();
				if ( ! empty( $d['ok'] ) ) {
					$done[] = $code;
					$note   = sprintf( 'Loyaltycode %s afgeboekt in het loyaltysysteem (%s, %s).', $code, $ctx['branch'], $ctx['actor'] ? $ctx['actor'] : 'online' );
					$korting = 0.0;
					foreach ( $order->get_items( 'coupon' ) as $ci ) {
						if ( strtoupper( $ci->get_code() ) === $code ) { $korting = (float) $ci->get_discount() + (float) $ci->get_discount_tax(); }
					}
					if ( isset( $d['amount'] ) && $korting + 0.01 < (float) $d['amount'] ) {
						$note .= sprintf( ' Codewaarde € %s, gebruikt € %s: restbedrag € %s.', number_format( (float) $d['amount'], 2, ',', '.' ), number_format( $korting, 2, ',', '.' ), number_format( (float) $d['amount'] - $korting, 2, ',', '.' ) );
					}
					$order->add_order_note( $note );
				} elseif ( ! empty( $d['error'] ) && 'onbekend' !== $d['error'] ) {
					$order->add_order_note( sprintf( 'Let op: loyaltycode %s kon niet worden afgeboekt (%s).', $code, $d['error'] ) );
				}
			} elseif ( ! $use && in_array( $code, $done, true ) ) {
				$r = thh_loy_api( 'rpc/thh_release_voucher', 'POST', array( 'p_code' => $code, 'p_order_id' => (string) $order->get_id() ) );
				if ( ! empty( $r['ok'] ) ) {
					$done = array_values( array_diff( $done, array( $code ) ) );
					$order->add_order_note( sprintf( 'Loyaltycode %s is weer actief voor de klant (order geannuleerd of terugbetaald).', $code ) );
				}
			}
			delete_transient( 'thh_lc_' . md5( $code ) );
		}
		$order->update_meta_data( '_thh_lc_used', array_values( array_unique( $done ) ) );
		$order->save_meta_data();
	}
}

add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to ) {
	if ( in_array( $to, array( 'processing', 'completed' ), true ) ) {
		thh_lc_sync_order( $order_id, true );
	} elseif ( in_array( $to, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
		thh_lc_sync_order( $order_id, false );
	}
}, 20, 3 );
