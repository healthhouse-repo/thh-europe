<?php
/**
 * THH Black Friday-stijl voor de hele site (17 nov 2026)
 *
 * Zet de webshop in Black Friday-stijl: zwarte balk met afteller bovenaan, zwart/goud menu,
 * zwarte hero en banden op de homepage, goud/rode actieprijzen, "BLACK FRIDAY"-labels op
 * afgeprijsde producten en een zwarte footer. Raakt geen prijzen of producten aan.
 *
 * Standen (optie thh_bf_mode):
 *   'preview' = alleen zichtbaar voor beheerders, of voor iedereen met ?bf=1 in de link (proef).
 *   'live'    = voor alle bezoekers, tussen thh_bf_start en thh_bf_end (Amsterdamse tijd).
 *   'off'     = uit.
 * Na thh_bf_end gaat de stijl vanzelf uit. De kassa (/pos/), wp-admin en de voorraadpagina
 * worden nooit aangepast.
 *
 * Aanpassen kan via WooCommerce › Black Friday (stand, data, tekst, link).
 * Uitzetten = stand op 'off' of deze snippet deactiveren.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'thh_bf_settings' ) ) {
	function thh_bf_settings() {
		return array(
			'mode'  => get_option( 'thh_bf_mode', 'preview' ),
			'start' => get_option( 'thh_bf_start', '2026-11-17 00:00' ),
			'end'   => get_option( 'thh_bf_end', '2026-12-01 00:00' ),
			'title' => get_option( 'thh_bf_title', 'BLACK FRIDAY' ),
			'text'  => get_option( 'thh_bf_text', 'De scherpste deals van het jaar, online en in al onze winkels' ),
			'cta'   => get_option( 'thh_bf_cta', 'Bekijk alle deals' ),
			'link'  => get_option( 'thh_bf_link', '/product-category/week-deals/' ),
		);
	}
}

if ( ! function_exists( 'thh_bf_ts' ) ) {
	function thh_bf_ts( $local ) {
		try {
			$d = new DateTime( $local, new DateTimeZone( 'Europe/Amsterdam' ) );
			return $d->getTimestamp();
		} catch ( Exception $e ) {
			return 0;
		}
	}
}

if ( ! function_exists( 'thh_bf_active' ) ) {
	function thh_bf_active() {
		static $active = null;
		if ( null !== $active ) { return $active; }
		$active = false;
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) { return $active; }
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( preg_match( '#/(pos|wcpos|voorraad|kassa)(/|\?|$)#i', $uri ) ) { return $active; }
		$s   = thh_bf_settings();
		$now = time();
		$end = thh_bf_ts( $s['end'] );
		if ( 'off' === $s['mode'] || ( $end && $now >= $end ) ) { return $active; }
		if ( 'live' === $s['mode'] ) {
			$active = ( $now >= thh_bf_ts( $s['start'] ) );
		} else {
			$active = current_user_can( 'manage_woocommerce' ) || ( isset( $_GET['bf'] ) && '1' === $_GET['bf'] );
		}
		return $active;
	}
}

add_filter( 'body_class', function ( $classes ) {
	if ( thh_bf_active() ) { $classes[] = 'thh-bf'; }
	return $classes;
} );

/* Balk bovenaan met afteller. */
add_action( 'wp_body_open', function () {
	if ( ! thh_bf_active() ) { return; }
	$s   = thh_bf_settings();
	$end = thh_bf_ts( $s['end'] );
	?>
	<div class="thh-bf-bar" role="region" aria-label="Black Friday">
		<a class="thh-bf-bar-in" href="<?php echo esc_url( $s['link'] ); ?>">
			<span class="thh-bf-word"><?php echo esc_html( $s['title'] ); ?></span>
			<span class="thh-bf-txt"><?php echo esc_html( $s['text'] ); ?></span>
			<span class="thh-bf-clock" data-end="<?php echo (int) $end * 1000; ?>" aria-live="off">
				<span class="thh-bf-u"><b data-u="d">0</b><i>dagen</i></span>
				<span class="thh-bf-u"><b data-u="h">00</b><i>uur</i></span>
				<span class="thh-bf-u"><b data-u="m">00</b><i>min</i></span>
				<span class="thh-bf-u"><b data-u="s">00</b><i>sec</i></span>
			</span>
			<span class="thh-bf-cta"><?php echo esc_html( $s['cta'] ); ?> &rarr;</span>
		</a>
	</div>
	<?php
}, 1 );

/* Opmaak + afteller. */
add_action( 'wp_head', function () {
	if ( ! thh_bf_active() ) { return; }
	?>
	<style id="thh-bf-css">
	body.thh-bf{--bf-black:#050505;--bf-ink:#111114;--bf-gold:#f5c542;--bf-gold2:#ffdf7e;--bf-red:#e3262f;--bf-line:#2a2a30}
	/* Balk */
	.thh-bf-bar{background:var(--bf-black);border-bottom:2px solid var(--bf-gold);position:relative;z-index:60;overflow:hidden}
	.thh-bf-bar:before{content:"";position:absolute;inset:0;background:linear-gradient(110deg,transparent 30%,rgba(245,197,66,.18) 45%,transparent 60%);background-size:250% 100%;animation:thhBfShine 4.5s linear infinite;pointer-events:none}
	@keyframes thhBfShine{0%{background-position:150% 0}100%{background-position:-100% 0}}
	.thh-bf-bar-in{position:relative;display:flex;align-items:center;justify-content:center;gap:18px;flex-wrap:wrap;max-width:1320px;margin:0 auto;padding:9px 16px;color:#fff!important;text-decoration:none!important;font-size:14px}
	.thh-bf-word{font-weight:900;letter-spacing:.14em;font-size:17px;background:linear-gradient(90deg,var(--bf-gold),var(--bf-gold2),var(--bf-gold));-webkit-background-clip:text;background-clip:text;color:transparent}
	.thh-bf-txt{color:#e9e9ee}
	.thh-bf-clock{display:inline-flex;gap:6px}
	.thh-bf-u{display:inline-flex;flex-direction:column;align-items:center;min-width:38px;padding:3px 6px;border:1px solid var(--bf-line);border-radius:6px;background:#141418;line-height:1.05}
	.thh-bf-u b{font-size:15px;color:var(--bf-gold);font-variant-numeric:tabular-nums}
	.thh-bf-u i{font-style:normal;font-size:9.5px;text-transform:uppercase;letter-spacing:.06em;color:#9a9aa6}
	.thh-bf-cta{background:var(--bf-red);color:#fff;font-weight:800;border-radius:999px;padding:6px 14px;font-size:13px;white-space:nowrap}
	.thh-bf-bar-in:hover .thh-bf-cta{background:#c81e27}
	@media(max-width:760px){.thh-bf-txt{display:none}.thh-bf-bar-in{gap:10px;padding:7px 10px}.thh-bf-word{font-size:14px}.thh-bf-u{min-width:32px;padding:2px 4px}.thh-bf-u b{font-size:13px}.thh-bf-cta{padding:5px 10px;font-size:12px}}
	@media(prefers-reduced-motion:reduce){.thh-bf-bar:before{animation:none}}
	/* Header en menu */
	body.thh-bf .thh-xxlh-usp{background:var(--bf-ink)!important;border-bottom:1px solid var(--bf-line)}
	body.thh-bf .thh-xxlh-usp,body.thh-bf .thh-xxlh-usp *{color:#d7d7de!important}
	body.thh-bf .thh-xxlh-usp svg,body.thh-bf .thh-xxlh-usp svg path{fill:var(--bf-gold)!important}
	body.thh-bf .thh-xxlh-wrap,body.thh-bf .thh-xxlh-drawer{background:var(--bf-black)!important}
	body.thh-bf .thh-xxlh-cat{background:var(--bf-ink)!important;border-bottom:2px solid var(--bf-gold)}
	body.thh-bf .thh-xxlh-cat > * a,body.thh-bf .thh-xxlh-cat a{color:#f1f1f4!important}
	body.thh-bf .thh-xxlh-cat a:hover{color:var(--bf-gold)!important}
	body.thh-bf .thh-xxlh-cat a[href*="week-deals"],body.thh-bf .thh-xxlh-cat a[href*="2-1-deals"]{color:var(--bf-gold)!important;font-weight:800}
	/* Homepage */
	body.thh-bf .h2-hero,body.thh-bf .h2-band{background:radial-gradient(1200px 500px at 85% 30%,rgba(245,197,66,.20),transparent 60%),radial-gradient(900px 400px at 10% 100%,rgba(227,38,47,.22),transparent 60%),var(--bf-black)!important;border-radius:inherit}
	body.thh-bf .h2-hero{position:relative;overflow:hidden}
	body.thh-bf .h2-herotxt:before{content:"BLACK FRIDAY";display:inline-block;margin:0 0 14px;padding:5px 12px;border-radius:999px;background:var(--bf-gold);color:#000;font-weight:900;letter-spacing:.14em;font-size:13px}
	body.thh-bf .h2-herotxt h1 em{color:var(--bf-gold)!important;-webkit-text-stroke:0!important}
	body.thh-bf .h2-ctas a:first-child{background:var(--bf-gold)!important;color:#000!important;border-color:var(--bf-gold)!important}
	body.thh-bf .h2-usps{background:var(--bf-ink)!important}
	body.thh-bf .h2-usps,body.thh-bf .h2-usps *{color:#ececf1!important}
	/* Prijzen en labels (webshop) */
	body.thh-bf .price ins,body.thh-bf .price ins .amount,body.thh-bf .wc-block-components-product-price__value.is-discounted{color:var(--bf-red)!important;text-decoration:none!important;font-weight:800}
	body.thh-bf .price del,body.thh-bf .price del .amount{color:#8c8c96!important}
	body.thh-bf span.onsale,body.thh-bf .wc-block-components-product-sale-badge{background:var(--bf-black)!important;color:var(--bf-gold)!important;border:1px solid var(--bf-gold)!important;font-size:0!important;border-radius:6px!important;min-width:0!important;min-height:0!important;padding:5px 8px!important;line-height:1!important}
	body.thh-bf span.onsale:after,body.thh-bf .wc-block-components-product-sale-badge:after{content:"BLACK FRIDAY";font-size:11px;font-weight:900;letter-spacing:.08em}
	body.thh-bf .single_add_to_cart_button,body.thh-bf .checkout-button,body.thh-bf #place_order{background:var(--bf-black)!important;color:#fff!important;border:2px solid var(--bf-gold)!important}
	body.thh-bf .single_add_to_cart_button:hover,body.thh-bf .checkout-button:hover,body.thh-bf #place_order:hover{background:var(--bf-gold)!important;color:#000!important}
	/* Footer */
	body.thh-bf footer.thf2-site{background:var(--bf-black)!important;border-top:3px solid var(--bf-gold)}
	</style>
	<script>
	(function(){function pad(n){return(n<10?'0':'')+n}
	function tick(){var c=document.querySelectorAll('.thh-bf-clock');for(var i=0;i<c.length;i++){var end=+c[i].getAttribute('data-end'),left=Math.max(0,end-Date.now()),s=Math.floor(left/1000);
	var v={d:Math.floor(s/86400),h:pad(Math.floor(s%86400/3600)),m:pad(Math.floor(s%3600/60)),s:pad(s%60)};
	for(var k in v){var b=c[i].querySelector('[data-u="'+k+'"]');if(b)b.textContent=v[k];}}}
	document.addEventListener('DOMContentLoaded',function(){tick();setInterval(tick,1000);});})();
	</script>
	<?php
}, 99 );

/* Instellingen: WooCommerce › Black Friday. */
add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'Black Friday', 'Black Friday', 'manage_woocommerce', 'thh-black-friday', function () {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
		$keys = array( 'mode', 'start', 'end', 'title', 'text', 'cta', 'link' );
		if ( isset( $_POST['thh_bf_save'] ) && check_admin_referer( 'thh_bf_save' ) ) {
			foreach ( $keys as $k ) {
				if ( isset( $_POST[ 'thh_bf_' . $k ] ) ) {
					$v = sanitize_text_field( wp_unslash( $_POST[ 'thh_bf_' . $k ] ) );
					if ( 'mode' === $k && ! in_array( $v, array( 'off', 'preview', 'live' ), true ) ) { $v = 'preview'; }
					update_option( 'thh_bf_' . $k, $v );
				}
			}
			echo '<div class="updated"><p>Opgeslagen.</p></div>';
		}
		$s = thh_bf_settings();
		echo '<div class="wrap"><h1>Black Friday-stijl</h1><form method="post">';
		wp_nonce_field( 'thh_bf_save' );
		echo '<table class="form-table">';
		echo '<tr><th>Stand</th><td><select name="thh_bf_mode">';
		foreach ( array( 'off' => 'Uit', 'preview' => 'Proef (alleen beheerders of met ?bf=1)', 'live' => 'Live voor iedereen' ) as $v => $l ) {
			echo '<option value="' . esc_attr( $v ) . '"' . selected( $s['mode'], $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
		$labels = array( 'start' => 'Start (jjjj-mm-dd uu:mm)', 'end' => 'Einde, afteller loopt hierheen', 'title' => 'Titel in de balk', 'text' => 'Tekst in de balk', 'cta' => 'Knoptekst', 'link' => 'Link' );
		foreach ( $labels as $k => $l ) {
			echo '<tr><th>' . esc_html( $l ) . '</th><td><input class="regular-text" name="thh_bf_' . esc_attr( $k ) . '" value="' . esc_attr( $s[ $k ] ) . '"></td></tr>';
		}
		echo '</table><p><button class="button button-primary" name="thh_bf_save" value="1">Opslaan</button></p></form></div>';
	} );
}, 99 );
