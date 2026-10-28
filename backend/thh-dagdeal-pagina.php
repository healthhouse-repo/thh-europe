<?php
/**
 * THH Dagdeal — pagina /dagdeal/ + blok op de homepage, met afteltimer.
 * Leest het rooster uit snippet "THH Dagdeals — prijs + melding per dag" (thh_dd_rooster / thh_dd_vandaag).
 * Rooster aanpassen blijft daar; deze snippet toont alleen.
 */
if ( ! defined( 'ABSPATH' ) ) { return; }

function thh_ddp_ok() { return function_exists( 'thh_dd_rooster' ) && function_exists( 'thh_dd_vandaag' ) && function_exists( 'thh_dd_lijst' ); }

/** Eén deal omzetten naar weergavegegevens. $deal = [ids, prijs, van, titel, sub] */
function thh_ddp_info( $deal ) {
	$ids = (array) $deal[0];
	$first = (int) reset( $ids );
	$parent = (int) wp_get_post_parent_id( $first );
	$pid = $parent ? $parent : $first;
	$p = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
	$img = '';
	if ( $p ) {
		$iid = $p->get_image_id();
		if ( $iid ) { $img = wp_get_attachment_image_url( $iid, 'woocommerce_single' ); }
	}
	$van = (float) $deal[2];
	$voor = (float) $deal[1];
	$off = $van > 0 ? (int) round( ( 1 - $voor / $van ) * 100 ) : 0;
	$stock = true;
	if ( $p ) {
		if ( $p->is_type( 'variable' ) ) {
			$stock = false;
			foreach ( $ids as $vid ) { $v = wc_get_product( (int) $vid ); if ( $v && $v->is_in_stock() ) { $stock = true; break; } }
		} else {
			$stock = $p->is_in_stock();
		}
	}
	return array(
		'titel' => (string) $deal[3],
		'sub'   => (string) $deal[4],
		'voor'  => $voor,
		'van'   => $van,
		'off'   => $off,
		'url'   => $p ? add_query_arg( array( 'utm_source' => 'site', 'utm_medium' => 'dagdealpagina', 'utm_campaign' => 'dagdeal' ), get_permalink( $pid ) ) : home_url( '/' ),
		'img'   => $img,
		'merk'  => $p ? wp_strip_all_tags( wc_get_product_category_list( $pid, ', ' ) ) : '',
		'naam'  => $p ? $p->get_name() : (string) $deal[3],
		'stock' => $stock,
	);
}

function thh_ddp_eur( $n ) { return '€' . number_format( (float) $n, 2, ',', '.' ); }

/** Eindtijd van de huidige dagdeal: vandaag 23:59 in Amsterdam (zelfde moment als de prijswissel). */
function thh_ddp_eind( $datum ) {
	$tz = new DateTimeZone( 'Europe/Amsterdam' );
	$d = new DateTime( $datum . ' 23:59:00', $tz );
	return $d->format( 'c' );
}

/** Wat er vandaag en de komende dagen in het rooster staat. */
function thh_ddp_data() {
	if ( ! thh_ddp_ok() ) { return null; }
	list( $vandaag, $deals ) = thh_dd_vandaag();
	$rooster = thh_dd_rooster();
	$week = isset( $rooster[ $vandaag ] ) && 'WEEK' === $rooster[ $vandaag ];
	$nu = array();
	foreach ( $deals as $d ) { $nu[] = thh_ddp_info( $d ); }
	$eind = thh_ddp_eind( $vandaag );
	if ( $week ) {
		// weekdeal loopt tot de laatste aaneengesloten WEEK-dag
		$e = $vandaag;
		$t = new DateTime( $vandaag );
		for ( $i = 1; $i < 7; $i++ ) { $t->modify( '+1 day' ); $k = $t->format( 'Y-m-d' ); if ( isset( $rooster[ $k ] ) && 'WEEK' === $rooster[ $k ] ) { $e = $k; } else { break; } }
		$eind = thh_ddp_eind( $e );
	}
	$straks = array();
	$t = new DateTime( $vandaag );
	for ( $i = 1; $i <= 14 && count( $straks ) < 4; $i++ ) {
		$t->modify( '+1 day' );
		$k = $t->format( 'Y-m-d' );
		if ( ! isset( $rooster[ $k ] ) ) { continue; }
		if ( 'WEEK' === $rooster[ $k ] ) { if ( ! $week && ( ! $straks || 'WEEK' !== end( $straks )['type'] ) ) { $straks[] = array( 'datum' => $k, 'type' => 'WEEK' ); } continue; }
		$straks[] = array( 'datum' => $k, 'type' => 'DAG', 'titel' => $rooster[ $k ][3] );
	}
	return array( 'datum' => $vandaag, 'week' => $week, 'deals' => $nu, 'eind' => $eind, 'straks' => $straks );
}

function thh_ddp_dagnaam( $datum ) {
	$dagen = array( 'zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag' );
	$maanden = array( '', 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december' );
	$t = strtotime( $datum . ' 12:00:00' );
	return $dagen[ (int) gmdate( 'w', $t ) ] . ' ' . (int) gmdate( 'j', $t ) . ' ' . $maanden[ (int) gmdate( 'n', $t ) ];
}

function thh_ddp_css() {
	return '<style>
body.thh-dagdeal-page .page-header{display:none}
body.thh-dagdeal-page .site-main,body.thh-dagdeal-page .page-content{max-width:none!important;width:100%!important;padding:0!important;margin:0!important}
.dd{font-family:Poppins,system-ui,sans-serif;color:#03124F;background:#f2f4fa;overflow:hidden}
.dd *{box-sizing:border-box}
.dd-hero{background:linear-gradient(118deg,#03124F 0%,#03124F 58%,#1E2A66 58%,#1E2A66 100%);color:#fff}
.dd-hi{max-width:1240px;margin:0 auto;padding:56px 24px 64px;display:grid;grid-template-columns:1.05fr 1fr;gap:32px;align-items:center}
.dd-lab{display:inline-block;background:#fff;color:#111;padding:9px 18px;font-weight:500;font-size:14px;letter-spacing:3px;text-transform:uppercase}
.dd-hero h1{margin:20px 0 0!important;color:#fff!important;font-weight:900!important;font-size:clamp(38px,5.6vw,72px)!important;line-height:.95!important;text-transform:uppercase;letter-spacing:0}
.dd-hero h1 span{display:inline-block;margin-top:.3em!important;color:transparent;-webkit-text-stroke:2px #fff}
.dd-sub{margin:20px 0 0;font-weight:500;font-size:15px;letter-spacing:2.5px;text-transform:uppercase;color:#dfe4ff}
.dd-price{display:flex;align-items:baseline;gap:14px;margin-top:22px}.dd-price s{font-size:22px;color:#b8c0ea}.dd-price strong{font-size:48px;font-weight:900;line-height:1}
.dd-cd{display:flex;gap:10px;margin-top:26px;align-items:center;flex-wrap:wrap}
.dd-cdl{font-size:13px;letter-spacing:2px;text-transform:uppercase;font-weight:600;color:#fff;width:100%}
.dd-cdb{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:10px;min-width:70px;padding:10px 8px;text-align:center}
.dd-cdb b{display:block;font-size:28px;font-weight:900;line-height:1}.dd-cdb small{font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#c9d0f5}
.dd-cta{display:inline-block;margin-top:28px;background:#D4364A;color:#fff!important;text-decoration:none!important;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;font-size:15px;padding:16px 34px;border-radius:40px}
.dd-cta.off{background:#5b6180;pointer-events:none}
.dd-art{position:relative;aspect-ratio:1/1;border-radius:24px;background:radial-gradient(circle at 50% 45%,#ffffff 0%,#dfe5f7 75%);display:flex;align-items:center;justify-content:center}
.dd-art img{width:78%!important;height:78%!important;object-fit:contain;filter:drop-shadow(0 18px 22px rgba(3,18,79,.35))}
.dd-badge{position:absolute;right:-2%;top:-4%;width:130px;height:130px;border-radius:50%;background:#D4364A;color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;transform:rotate(8deg);font-weight:900;text-transform:uppercase;line-height:.95;box-shadow:0 10px 24px rgba(0,0,20,.35)}
.dd-badge span{font-size:14px}.dd-badge strong{font-size:30px;margin-top:4px}
.dd-off{position:absolute;left:16px;top:16px;background:#03124F;color:#fff;font-weight:800;font-size:16px;padding:7px 14px;border-radius:24px}
.dd-body{max-width:1240px;margin:0 auto;padding:48px 24px 64px}
.dd-h2{font-weight:900;text-transform:uppercase;font-size:clamp(26px,3.4vw,38px);margin:0 0 6px;color:#03124F}
.dd-intro{margin:0 0 28px;color:#5b6180;font-size:16px}
.dd-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
.dd-card{position:relative;display:flex;flex-direction:column;background:#fff;border-radius:18px;padding:14px 14px 18px;text-decoration:none!important;color:#03124F!important;box-shadow:0 2px 0 rgba(3,18,79,.04);transition:transform .2s,box-shadow .2s}
a.dd-card:hover{transform:translateY(-4px);box-shadow:0 14px 30px rgba(3,18,79,.14)}
.dd-img{position:relative;border-radius:12px;aspect-ratio:1/1;background:radial-gradient(circle at 50% 42%,#ffffff 0%,#e8ecf8 78%);display:flex;align-items:center;justify-content:center;overflow:hidden}
.dd-img img{width:74%!important;height:78%!important;object-fit:contain;filter:drop-shadow(0 14px 16px rgba(3,18,79,.25))}
.dd-q{font-size:64px;font-weight:900;color:#c3cae6}
.dd-pr{position:absolute;right:8px;top:8px;width:74px;height:74px;border-radius:50%;background:#D4364A;color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;transform:rotate(8deg);font-weight:900;line-height:1}
.dd-pr small{font-size:9px;letter-spacing:1px;text-transform:uppercase}.dd-pr b{font-size:17px;margin-top:2px}
.dd-tag{margin-top:14px;color:#D4364A;font-weight:800;font-size:11px;letter-spacing:2px;text-transform:uppercase}
.dd-card h3{font-size:17px!important;line-height:1.2!important;margin:4px 0 2px!important;color:#03124F!important;font-weight:800!important}
.dd-s{font-size:13px!important;line-height:1.4!important;color:#5b6180;margin:0 0 10px!important}
.dd-p{margin:auto 0 12px;display:flex;align-items:baseline;gap:8px;line-height:1.1}.dd-p s{color:#8a90ab;font-size:14px}.dd-p strong{font-size:24px;font-weight:900}
.dd-btn{display:block;text-align:center;background:#03124F;color:#fff;font-weight:700;font-size:14px;border-radius:30px;padding:11px}a.dd-card:hover .dd-btn{background:#D4364A}
.dd-how{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:40px}
.dd-how div{background:#fff;border-radius:18px;padding:20px}.dd-how b{display:block;font-size:17px;margin-bottom:4px}.dd-how p{margin:0;color:#5b6180;font-size:14px}
.dd-note{margin:28px 0 0;font-size:13px;color:#5b6180}
.ddh{font-family:Poppins,system-ui,sans-serif;max-width:1240px;margin:24px auto;padding:0 16px}
.ddh a{display:grid;grid-template-columns:150px 1fr auto;gap:22px;align-items:center;background:linear-gradient(118deg,#03124F 0%,#03124F 62%,#1E2A66 62%);color:#fff!important;text-decoration:none!important;border-radius:20px;padding:18px 26px;position:relative;overflow:hidden}
.ddh-img{width:150px;height:150px;border-radius:14px;background:radial-gradient(circle,#fff 0%,#dfe5f7 75%);display:flex;align-items:center;justify-content:center}
.ddh-img img{width:82%!important;height:82%!important;object-fit:contain}
.ddh-lab{display:inline-block;background:#D4364A;color:#fff;font-weight:800;font-size:12px;letter-spacing:2px;text-transform:uppercase;padding:5px 12px;border-radius:20px}
.ddh h3{color:#fff!important;font-weight:900!important;font-size:clamp(22px,2.6vw,32px)!important;margin:8px 0 4px!important;text-transform:uppercase;line-height:1.05!important}
.ddh-p{display:flex;gap:10px;align-items:baseline}.ddh-p s{color:#b8c0ea}.ddh-p strong{font-size:28px;font-weight:900}
.ddh-r{text-align:center}.ddh-cd{display:flex;gap:6px;justify-content:center;margin-bottom:12px}
.ddh-cd div{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:8px;min-width:52px;padding:6px 4px}.ddh-cd b{display:block;font-size:20px;font-weight:900;line-height:1}.ddh-cd small{font-size:9px;letter-spacing:1px;text-transform:uppercase;color:#c9d0f5}
.ddh-btn{display:inline-block;background:#D4364A;color:#fff;font-weight:800;letter-spacing:1px;text-transform:uppercase;font-size:14px;padding:12px 26px;border-radius:30px}
@media(max-width:900px){.dd-hi{grid-template-columns:1fr;padding:36px 16px 44px}.dd-art{max-width:420px;width:100%;margin:0 auto}.dd-grid{grid-template-columns:repeat(2,1fr);gap:12px}.dd-how{grid-template-columns:1fr}.dd-body{padding:36px 16px 48px}.dd-cdb{min-width:60px}.dd-badge{width:100px;height:100px}.dd-badge strong{font-size:24px}
.ddh a{grid-template-columns:96px 1fr;padding:16px}.ddh-img{width:96px;height:96px}.ddh-r{grid-column:1/-1}}
</style>';
}

function thh_ddp_timer_js() {
	return '<script>(function(){function p(n){return(n<10?"0":"")+n}document.querySelectorAll("[data-dd-eind]").forEach(function(box){var e=new Date(box.getAttribute("data-dd-eind")),lab=box.querySelector("[data-dd-lab]"),b=box.querySelector("[data-dd-blocks]"),cls=box.getAttribute("data-dd-cls")||"dd-cdb";function t(){var x=e-new Date();if(x<=0){if(lab)lab.textContent="Deze deal is voorbij — ververs de pagina voor de nieuwe deal";b.style.display="none";return}var h=Math.floor(x/36e5),m=Math.floor(x%36e5/6e4),s=Math.floor(x%6e4/1e3),parts=[[h,"uur"],[m,"min"],[s,"sec"]];if(h>=24){parts=[[Math.floor(h/24),"dagen"],[h%24,"uur"],[m,"min"],[s,"sec"]]}b.innerHTML=parts.map(function(v){return"<div class=\""+cls+"\"><b>"+p(v[0])+"</b><small>"+v[1]+"</small></div>"}).join("")}t();setInterval(t,1000)})})();</script>';
}

function thh_ddp_card( $i, $tag ) {
	$h  = '<a class="dd-card" href="' . esc_url( $i['url'] ) . '"><div class="dd-img">';
	$h .= $i['img'] ? '<img src="' . esc_url( $i['img'] ) . '" alt="' . esc_attr( $i['titel'] ) . '" loading="lazy">' : '';
	$h .= $i['off'] > 0 ? '<span class="dd-off" style="font-size:13px;padding:5px 10px;left:10px;top:10px">-' . (int) $i['off'] . '%</span>' : '';
	$h .= '<span class="dd-pr"><small>' . esc_html( $tag ) . '</small><b>' . esc_html( thh_ddp_eur( $i['voor'] ) ) . '</b></span></div>';
	$h .= '<div class="dd-tag">' . esc_html( $tag ) . '</div><h3>' . esc_html( $i['titel'] ) . '</h3><p class="dd-s">' . esc_html( $i['naam'] ) . '</p>';
	$h .= '<p class="dd-p"><s>' . esc_html( thh_ddp_eur( $i['van'] ) ) . '</s><strong>' . esc_html( thh_ddp_eur( $i['voor'] ) ) . '</strong></p><span class="dd-btn">' . ( $i['stock'] ? 'Bekijk deal' : 'Uitverkocht' ) . '</span></a>';
	return $h;
}

function thh_ddp_pagina() {
	$d = thh_ddp_data();
	if ( ! $d ) { return ''; }
	$h = thh_ddp_css() . '<div class="dd">';
	$cd = '<div class="dd-cd" data-dd-eind="' . esc_attr( $d['eind'] ) . '"><div class="dd-cdl" data-dd-lab>' . ( $d['week'] ? 'De weekdeals stoppen over' : 'Deze dagdeal stopt over' ) . '</div><div data-dd-blocks style="display:flex;gap:10px"></div></div>';

	if ( ! $d['deals'] ) {
		$h .= '<section class="dd-hero"><div class="dd-hi"><div><span class="dd-lab">Dagdeal · ' . esc_html( thh_ddp_dagnaam( $d['datum'] ) ) . '</span><h1>Elke dag<br><span>een nieuwe deal</span></h1><p class="dd-sub">Vandaag even geen dagdeal. Kijk morgen weer.</p><a class="dd-cta" href="/winkel/">Naar de webshop</a></div></div></section>';
	} elseif ( $d['week'] ) {
		$h .= '<section class="dd-hero"><div class="dd-hi"><div><span class="dd-lab">Weekdeals · ' . esc_html( thh_ddp_dagnaam( $d['datum'] ) ) . '</span><h1>Alle dagdeals<br><span>nog één keer</span></h1><p class="dd-sub">Alleen dit weekend, zolang de voorraad strekt</p>' . $cd . '<a class="dd-cta" href="#dd-deals">Bekijk alle deals</a></div></div></section>';
		$h .= '<section class="dd-body" id="dd-deals"><h2 class="dd-h2">De weekdeals</h2><p class="dd-intro">Een dagdeal gemist deze week? Dit weekend krijg je ze allemaal nog één keer, online en in al onze winkels.</p><div class="dd-grid">';
		foreach ( $d['deals'] as $i ) { $h .= thh_ddp_card( $i, 'Weekdeal' ); }
		$h .= '</div></section>';
	} else {
		$i = $d['deals'][0];
		$h .= '<section class="dd-hero"><div class="dd-hi"><div><span class="dd-lab">Dagdeal · ' . esc_html( thh_ddp_dagnaam( $d['datum'] ) ) . '</span><h1>' . esc_html( $i['titel'] ) . '<br><span>alleen vandaag</span></h1>';
		$h .= '<div class="dd-price"><s>' . esc_html( thh_ddp_eur( $i['van'] ) ) . '</s><strong>' . esc_html( thh_ddp_eur( $i['voor'] ) ) . '</strong></div>';
		$h .= '<p class="dd-sub">' . esc_html( $i['sub'] ) . '</p>' . $cd;
		$h .= $i['stock'] ? '<a class="dd-cta" href="' . esc_url( $i['url'] ) . '">Pak de deal</a>' : '<span class="dd-cta off">Uitverkocht</span>';
		$h .= '</div><div class="dd-art">' . ( $i['img'] ? '<img src="' . esc_url( $i['img'] ) . '" alt="' . esc_attr( $i['titel'] ) . '">' : '' ) . ( $i['off'] > 0 ? '<span class="dd-off">-' . (int) $i['off'] . '%</span>' : '' ) . '<div class="dd-badge"><span>Nu</span><strong>' . esc_html( thh_ddp_eur( $i['voor'] ) ) . '</strong></div></div></div></section>';
	}

	$h .= '<section class="dd-body" style="padding-top:' . ( $d['week'] ? '0' : '48px' ) . '">';
	if ( $d['straks'] ) {
		$h .= '<h2 class="dd-h2">Binnenkort</h2><p class="dd-intro">Elke dag om middernacht een nieuwe deal. Zet een herinnering en kom morgen terug.</p><div class="dd-grid">';
		foreach ( $d['straks'] as $s ) {
			$titel = 'WEEK' === $s['type'] ? 'Weekend: alle deals nog één keer' : $s['titel'];
			$h .= '<div class="dd-card"><div class="dd-img"><span class="dd-q">?</span></div><div class="dd-tag">' . esc_html( thh_ddp_dagnaam( $s['datum'] ) ) . '</div><h3>' . esc_html( $titel ) . '</h3><p class="dd-s">Prijs wordt om 00:00 bekend</p></div>';
		}
		$h .= '</div>';
	}
	$h .= '<div class="dd-how"><div><b>Elke dag een nieuwe deal</b><p>Om middernacht wisselt de deal. Wat vandaag scherp geprijsd is, is morgen weer normaal.</p></div><div><b>Online én in de winkel</b><p>De dagdeal geldt in de webshop en in al onze winkels, zolang de voorraad strekt.</p></div><div><b>In het weekend: weekdeals</b><p>Een dagdeal gemist? In het weekend komen ze allemaal nog één keer terug.</p></div></div>';
	$h .= '<p class="dd-note">Dagdeals gelden zolang de voorraad strekt en zijn niet te combineren met andere kortingen.</p></section></div>' . thh_ddp_timer_js();
	return $h;
}
add_shortcode( 'thh_dagdeal_pagina', 'thh_ddp_pagina' );

/** Compact blok (homepage). */
function thh_ddp_blok() {
	$d = thh_ddp_data();
	if ( ! $d || ! $d['deals'] ) { return ''; }
	$i = $d['deals'][0];
	$lab = $d['week'] ? 'Weekdeals' : 'Dagdeal van vandaag';
	$titel = $d['week'] ? 'Alle dagdeals nog één keer' : $i['titel'];
	$url = $d['week'] ? home_url( '/weekdeals/' ) : home_url( '/dagdeal/' );
	$h  = '<div class="ddh"><a href="' . esc_url( $url ) . '"><div class="ddh-img">' . ( $i['img'] ? '<img src="' . esc_url( $i['img'] ) . '" alt="' . esc_attr( $titel ) . '">' : '' ) . '</div>';
	$h .= '<div><span class="ddh-lab">' . esc_html( $lab ) . '</span><h3>' . esc_html( $titel ) . '</h3>';
	$h .= $d['week'] ? '<div class="ddh-p"><span>vanaf</span><strong>' . esc_html( thh_ddp_eur( min( array_column( $d['deals'], 'voor' ) ) ) ) . '</strong></div>' : '<div class="ddh-p"><s>' . esc_html( thh_ddp_eur( $i['van'] ) ) . '</s><strong>' . esc_html( thh_ddp_eur( $i['voor'] ) ) . '</strong></div>';
	$h .= '</div><div class="ddh-r" data-dd-eind="' . esc_attr( $d['eind'] ) . '" data-dd-cls="x"><div class="ddh-cd" data-dd-blocks></div><span class="ddh-btn">' . ( $d['week'] ? 'Bekijk de deals' : 'Pak de deal' ) . '</span></div></a></div>';
	return $h;
}
add_shortcode( 'thh_dagdeal_blok', function () { return thh_ddp_css() . thh_ddp_blok() . thh_ddp_timer_js(); } );

// REST: actuele blok-HTML, niet gecachet (de homepage zelf mag gecachet blijven)
add_action( 'rest_api_init', function () {
	register_rest_route( 'thh/v1', '/dagdeal-blok', array(
		'methods' => 'GET',
		'permission_callback' => '__return_true',
		'callback' => function () {
			$r = new WP_REST_Response( array( 'html' => thh_ddp_blok() ) );
			$r->header( 'Cache-Control', 'no-store, max-age=0' );
			return $r;
		},
	) );
} );

// Homepage: blok direct onder de header laden (via REST, zodat paginacache geen oude deal toont)
add_action( 'wp_footer', function () {
	if ( ! is_front_page() || ! thh_ddp_ok() ) { return; }
	echo thh_ddp_css(); // phpcs:ignore
	?>
<script>(function(){fetch("<?php echo esc_url_raw( rest_url( 'thh/v1/dagdeal-blok' ) ); ?>",{cache:"no-store"}).then(function(r){return r.json()}).then(function(j){if(!j||!j.html)return;var w=document.createElement("div");w.innerHTML=j.html;var el=w.firstChild;var hdr=document.querySelector(".elementor-location-header")||document.querySelector("body > header, #masthead, .site-header");if(hdr&&hdr.parentNode){hdr.parentNode.insertBefore(el,hdr.nextSibling)}else{var main=document.querySelector("main, #content, .site-main")||document.body;main.insertBefore(el,main.firstChild)};try{var nx=el.nextElementSibling,f=nx&&nx.querySelector(".elementor-top-section, .e-con");var mt=f?parseFloat(getComputedStyle(f).marginTop)||0:0;if(mt<0){el.style.marginBottom=(24-mt)+"px"}el.style.position="relative";el.style.zIndex="3"}catch(e){}var s=document.createElement("script");s.textContent=<?php echo wp_json_encode( preg_replace( '#^<script>|</script>$#', '', thh_ddp_timer_js() ) ); ?>;document.body.appendChild(s)}).catch(function(){})})();</script>
	<?php
}, 50 );

// Dagdealpagina: body-class + niet cachen
add_filter( 'body_class', function ( $c ) { if ( is_page( 'dagdeal' ) ) { $c[] = 'thh-dagdeal-page'; } return $c; } );
add_action( 'template_redirect', function () {
	if ( is_page( 'dagdeal' ) ) { if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); } nocache_headers(); }
}, 1 );
