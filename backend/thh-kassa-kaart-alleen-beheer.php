<?php
/**
 * THH Kassa — betaaloptie "Kaart" alleen voor beheerders (14 nov 2026)
 *
 * "Kaart" (pos_card) rondt een kassabon af zonder terminalbetaling. Sinds de kassa-update van
 * 12 nov werd die optie gebruikt als de pinterminal niet startte, waardoor bonnen op afgerond
 * kwamen zonder gekoppelde betaling. Medewerkers (kassa-accounts) zien de optie niet meer en
 * pinnen via "Pinnen (terminal)". Beheerders houden "Kaart" als noodknop.
 * Uitzetten = deze snippet deactiveren.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_filter( 'woocommerce_available_payment_gateways', function ( $gateways ) {
	if ( ! is_array( $gateways ) || ! isset( $gateways['pos_card'] ) ) { return $gateways; }
	if ( ! is_user_logged_in() || current_user_can( 'manage_woocommerce' ) ) { return $gateways; }
	unset( $gateways['pos_card'] );
	return $gateways;
}, 99 );
