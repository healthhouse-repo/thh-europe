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
			'title' => get_option( 'thh_bf_title', 'BLACK NOVEMBER' ),
			'bar'   => get_option( 'thh_bf_bar_live', '1' ),
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

if ( ! function_exists( 'thh_bf_bar_active' ) ) {
	/** Alleen de zwarte balk bovenaan: los live te zetten (optie thh_bf_bar_live), ook als de rest nog proef is. */
	function thh_bf_bar_active() {
		if ( thh_bf_active() ) { return true; }
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) { return false; }
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( preg_match( '#/(pos|wcpos|voorraad|kassa)(/|\?|$)#i', $uri ) ) { return false; }
		$s = thh_bf_settings();
		if ( '1' !== (string) $s['bar'] || 'off' === $s['mode'] ) { return false; }
		$now = time();
		return $now >= thh_bf_ts( $s['start'] ) && $now < thh_bf_ts( $s['end'] );
	}
}

add_filter( 'body_class', function ( $classes ) {
	if ( thh_bf_active() ) { $classes[] = 'thh-bfx'; }
	return $classes;
} );

/* Balk bovenaan met afteller. */
add_action( 'wp_body_open', function () {
	if ( ! thh_bf_bar_active() ) { return; }
	$s   = thh_bf_settings();
	$end = thh_bf_ts( $s['end'] );
	?>
	<div class="thh-bfx-bar" role="region" aria-label="Black Friday">
		<a class="thh-bfx-bar-in" href="<?php echo esc_url( $s['link'] ); ?>">
			<span class="thh-bfx-word"><?php echo esc_html( $s['title'] ); ?></span>
			<span class="thh-bfx-txt"><?php echo esc_html( $s['text'] ); ?></span>
			<span class="thh-bfx-clock" data-end="<?php echo (int) $end * 1000; ?>" aria-live="off">
				<span class="thh-bfx-u"><b data-u="d">0</b><i>dagen</i></span>
				<span class="thh-bfx-u"><b data-u="h">00</b><i>uur</i></span>
				<span class="thh-bfx-u"><b data-u="m">00</b><i>min</i></span>
				<span class="thh-bfx-u"><b data-u="s">00</b><i>sec</i></span>
			</span>
			<span class="thh-bfx-cta"><?php echo esc_html( $s['cta'] ); ?> &rarr;</span>
		</a>
	</div>
	<?php
}, 1 );

/* Opmaak + afteller. */
add_action( 'wp_head', function () {
	if ( ! thh_bf_bar_active() ) { return; }
	$full = thh_bf_active();
	?>
	<style id="thh-bfx-css">
	body.thh-bfx,.thh-bfx-bar{--bf-black:#050505;--bf-ink:#111114;--bf-gold:#f5c542;--bf-gold2:#ffdf7e;--bf-red:#e3262f;--bf-line:#2a2a30}
	/* Balk */
	.thh-bfx-bar{width:100%;flex:0 0 100%;box-sizing:border-box;background:var(--bf-black);border-bottom:2px solid var(--bf-red);position:relative;z-index:60;overflow:hidden}
	.thh-bfx-bar:before{content:"";position:absolute;inset:0;background:linear-gradient(110deg,transparent 30%,rgba(245,197,66,.18) 45%,transparent 60%);background-size:250% 100%;animation:thhBfShine 4.5s linear infinite;pointer-events:none}
	@keyframes thhBfShine{0%{background-position:150% 0}100%{background-position:-100% 0}}
	.thh-bfx-bar-in{position:relative;display:flex;align-items:center;justify-content:center;gap:18px;flex-wrap:wrap;max-width:1320px;margin:0 auto;padding:9px 16px;color:#fff!important;text-decoration:none!important;font-size:14px}
	.thh-bfx-word{font-weight:900;letter-spacing:.14em;font-size:17px;color:var(--bf-red)}
	.thh-bfx-txt{color:#e9e9ee}
	.thh-bfx-clock{display:inline-flex;gap:6px}
	.thh-bfx-u{display:inline-flex;flex-direction:column;align-items:center;min-width:38px;padding:3px 6px;border:1px solid var(--bf-line);border-radius:6px;background:#141418;line-height:1.05}
	.thh-bfx-u b{font-size:15px;color:var(--bf-red);font-variant-numeric:tabular-nums}
	.thh-bfx-u i{font-style:normal;font-size:9.5px;text-transform:uppercase;letter-spacing:.06em;color:#9a9aa6}
	.thh-bfx-cta{background:var(--bf-red);color:#fff;font-weight:800;border-radius:999px;padding:6px 14px;font-size:13px;white-space:nowrap}
	.thh-bfx-bar-in:hover .thh-bfx-cta{background:#c81e27}
	@media(max-width:760px){.thh-bfx-txt{display:none}.thh-bfx-bar-in{gap:10px;padding:7px 10px}.thh-bfx-word{font-size:14px}.thh-bfx-u{min-width:32px;padding:2px 4px}.thh-bfx-u b{font-size:13px}.thh-bfx-cta{padding:5px 10px;font-size:12px}}
	@media(prefers-reduced-motion:reduce){.thh-bfx-bar:before{animation:none}}
	</style>
	<?php if ( $full ) : ?>
	<style id="thh-bfx-css-full">
	/* Header en menu */
	body.thh-bfx .thh-xxlh-usp{background:var(--bf-ink)!important;border-bottom:1px solid var(--bf-line)}
	body.thh-bfx .thh-xxlh-usp,body.thh-bfx .thh-xxlh-usp *{color:#d7d7de!important}
	body.thh-bfx .thh-xxlh-usp svg,body.thh-bfx .thh-xxlh-usp svg path{fill:var(--bf-gold)!important}
	body.thh-bfx .thh-xxlh-wrap,body.thh-bfx .thh-xxlh-drawer{background:var(--bf-black)!important}
	body.thh-bfx .thh-xxlh-cat{background:var(--bf-ink)!important;border-bottom:2px solid var(--bf-gold)}
	body.thh-bfx .thh-xxlh-cat > * a,body.thh-bfx .thh-xxlh-cat a{color:#f1f1f4!important}
	body.thh-bfx .thh-xxlh-cat a:hover{color:var(--bf-gold)!important}
	body.thh-bfx .thh-xxlh-cat a[href*="week-deals"],body.thh-bfx .thh-xxlh-cat a[href*="2-1-deals"]{color:var(--bf-gold)!important;font-weight:800}
	/* Homepage */
	body.thh-bfx .h2-hero,body.thh-bfx .h2-band{background:radial-gradient(1200px 500px at 85% 30%,rgba(245,197,66,.20),transparent 60%),radial-gradient(900px 400px at 10% 100%,rgba(227,38,47,.22),transparent 60%),var(--bf-black)!important;border-radius:inherit}
	body.thh-bfx .h2-hero{position:relative;overflow:hidden}
	body.thh-bfx .h2-herotxt:before{content:"BLACK FRIDAY";display:inline-block;align-self:flex-start;width:max-content;margin:0 0 14px;padding:5px 12px;border-radius:999px;background:var(--bf-gold);color:#000;font-weight:900;letter-spacing:.14em;font-size:13px}
	body.thh-bfx .h2-herotxt h1 em{color:var(--bf-gold)!important;-webkit-text-stroke:0!important}
	body.thh-bfx .h2-ctas a:first-child{background:var(--bf-gold)!important;color:#000!important;border-color:var(--bf-gold)!important}
	body.thh-bfx .h2-usps{background:var(--bf-ink)!important}
	body.thh-bfx .h2-usps,body.thh-bfx .h2-usps *{color:#ececf1!important}
	/* Prijzen en labels (webshop) */
	body.thh-bfx .price ins,body.thh-bfx .price ins .amount,body.thh-bfx .wc-block-components-product-price__value.is-discounted{color:var(--bf-red)!important;text-decoration:none!important;font-weight:800}
	body.thh-bfx .price del,body.thh-bfx .price del .amount{color:#8c8c96!important}
	html body.thh-bfx .woocommerce span.onsale.onsale,html body.thh-bfx span.onsale.onsale,html body.thh-bfx .wc-block-components-product-sale-badge{background:var(--bf-black)!important;color:var(--bf-gold)!important;border:1px solid var(--bf-gold)!important;font-size:0!important;border-radius:6px!important;min-width:0!important;min-height:0!important;padding:5px 8px!important;line-height:1!important}
	html body.thh-bfx span.onsale.onsale:after,html body.thh-bfx .wc-block-components-product-sale-badge:after{content:"BLACK FRIDAY";font-size:11px;font-weight:900;letter-spacing:.08em}
	body.thh-bfx .single_add_to_cart_button,body.thh-bfx .checkout-button,body.thh-bfx #place_order{background:var(--bf-black)!important;color:#fff!important;border:2px solid var(--bf-gold)!important}
	body.thh-bfx .single_add_to_cart_button:hover,body.thh-bfx .checkout-button:hover,body.thh-bfx #place_order:hover{background:var(--bf-gold)!important;color:#000!important}
	/* Footer */
	body.thh-bfx footer.thf2-site{background:var(--bf-black)!important;border-top:3px solid var(--bf-gold)}
	body.thh-bfx footer.elementor-location-footer{border-top:3px solid var(--bf-gold)}
	/* Blauw wordt zwart (aangevuld door het script hieronder) */
	html body.thh-bfx .thh-bfx-black.thh-bfx-black{background-color:var(--bf-black)!important}
	html body.thh-bfx .thh-bfx-blackgrad.thh-bfx-blackgrad{background-image:linear-gradient(135deg,#17171b 0%,#050505 100%)!important}
	html body.thh-bfx .thh-bfx-ink.thh-bfx-ink{color:#0b0b0d!important}
	/* Animaties */
	@keyframes thhBfxShake{0%,100%{transform:translate(0,0) rotate(0)}10%{transform:translate(-9px,3px) rotate(-.6deg)}20%{transform:translate(8px,-4px) rotate(.6deg)}30%{transform:translate(-7px,2px) rotate(-.4deg)}40%{transform:translate(6px,-2px) rotate(.4deg)}50%{transform:translate(-4px,2px)}60%{transform:translate(3px,-1px)}70%{transform:translate(-2px,1px)}80%{transform:translate(1px,0)}}
	@keyframes thhBfxPulse{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(245,197,66,.55)}50%{transform:scale(1.05);box-shadow:0 0 0 12px rgba(245,197,66,0)}}
	@keyframes thhBfxWiggle{0%,86%,100%{transform:rotate(0)}88%{transform:rotate(-6deg)}90%{transform:rotate(6deg)}92%{transform:rotate(-4deg)}94%{transform:rotate(4deg)}96%{transform:rotate(0)}}
	@keyframes thhBfxGlitch{0%,92%,100%{text-shadow:none;transform:none}93%{text-shadow:-3px 0 var(--bf-red),3px 0 #3ff;transform:skewX(-8deg)}95%{text-shadow:3px 0 var(--bf-red),-3px 0 #3ff;transform:skewX(6deg)}97%{text-shadow:none;transform:none}}
	@keyframes thhBfxFall{0%{transform:translate3d(0,-10vh,0) rotate(0);opacity:0}10%{opacity:1}100%{transform:translate3d(var(--dx),110vh,0) rotate(720deg);opacity:.9}}
	body.thh-bfx.thh-bfx-shake{animation:thhBfxShake .7s cubic-bezier(.36,.07,.19,.97) both}
	body.thh-bfx .thh-bfx-word{display:inline-block;animation:thhBfxGlitch 4s steps(1) infinite}
	body.thh-bfx .thh-bfx-cta,body.thh-bfx .h2-ctas a:first-child{animation:thhBfxPulse 1.8s ease-in-out infinite}
	body.thh-bfx span.onsale.onsale{animation:thhBfxWiggle 5s ease-in-out infinite;transform-origin:20% 50%}
	body.thh-bfx .h2-herotxt:before{animation:thhBfxWiggle 4s ease-in-out infinite}
	.thh-bfx-confetti{position:fixed;inset:0;pointer-events:none;z-index:99998;overflow:hidden}
	.thh-bfx-confetti i{position:absolute;top:0;width:9px;height:14px;border-radius:2px;animation:thhBfxFall linear forwards}
	@media(prefers-reduced-motion:reduce){body.thh-bfx *,body.thh-bfx.thh-bfx-shake{animation:none!important}.thh-bfx-confetti{display:none}}
	</style>
	<?php endif; ?>
	<script>
	(function(){function pad(n){return(n<10?'0':'')+n}
	function tick(){var c=document.querySelectorAll('.thh-bfx-clock');for(var i=0;i<c.length;i++){var end=+c[i].getAttribute('data-end'),left=Math.max(0,end-Date.now()),s=Math.floor(left/1000);
	var v={d:Math.floor(s/86400),h:pad(Math.floor(s%86400/3600)),m:pad(Math.floor(s%3600/60)),s:pad(s%60)};
	for(var k in v){var b=c[i].querySelector('[data-u="'+k+'"]');if(b)b.textContent=v[k];}}}
	function go(){tick();setInterval(tick,1000);}if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',go);}else{go();}})();
	</script>
	<?php if ( $full ) : ?>
	<script>
	/* Alle blauwe (navy) vlakken en verlopen zwart maken, ook in later geladen onderdelen. */
	(function(){
	var NAVY=/rgba?\((\d+),\s*(\d+),\s*(\d+)/g;
	function isNavy(r,g,b){var mx=Math.max(r,g,b),mn=Math.min(r,g,b),l=(mx+mn)/510;if(b<=r||b<=g||l>.42||mx-mn<25)return false;return b>=g;}
	function navyIn(str){var m;NAVY.lastIndex=0;while((m=NAVY.exec(str))){if(isNavy(+m[1],+m[2],+m[3]))return true;}return false;}
	function skip(el){return el.closest&&(el.closest('#wpadminbar')||el.closest('.thh-bfx-bar'));}
	function fix(el){if(el.nodeType!==1||skip(el))return;var cs=getComputedStyle(el);
	 var bg=cs.backgroundColor,m=/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?/.exec(bg);
	 if(m&&(m[4]===undefined||+m[4]>.5)&&isNavy(+m[1],+m[2],+m[3]))el.classList.add('thh-bfx-black');
	 if(cs.backgroundImage.indexOf('gradient')>-1&&navyIn(cs.backgroundImage))el.classList.add('thh-bfx-blackgrad');
	 var c=/rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(cs.color);
	 if(c&&isNavy(+c[1],+c[2],+c[3])&&!el.closest('.thh-bfx-black,.thh-bfx-blackgrad,.h2-hero,.h2-band'))el.classList.add('thh-bfx-ink');}
	function scan(root){fix(root);var all=root.querySelectorAll?root.querySelectorAll('*'):[];for(var i=0;i<all.length;i++)fix(all[i]);}
	var queue=[],timer=null;function flush(){var q=queue;queue=[];timer=null;for(var i=0;i<q.length;i++)if(q[i].isConnected)scan(q[i]);}
	function start(){scan(document.body);if(window.MutationObserver){new MutationObserver(function(ms){for(var i=0;i<ms.length;i++)for(var j=0;j<ms[i].addedNodes.length;j++){var n=ms[i].addedNodes[j];if(n.nodeType===1)queue.push(n);}if(queue.length&&!timer)timer=setTimeout(flush,250);}).observe(document.body,{childList:true,subtree:true});}
	 window.addEventListener('load',function(){scan(document.body);});}
	if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',start);}else{start();}
	})();
	/* Effect bij binnenkomst: scherm schudt en gouden confetti valt (1x per bezoek). */
	(function(){
	if(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches)return;
	var seen=false;try{seen=sessionStorage.getItem('thhBfxIntro')==='1';sessionStorage.setItem('thhBfxIntro','1');}catch(e){}
	function confetti(n){var box=document.createElement('div');box.className='thh-bfx-confetti';box.setAttribute('aria-hidden','true');
	 var cols=['#f5c542','#ffdf7e','#e3262f','#ffffff','#111114'];for(var i=0;i<n;i++){var p=document.createElement('i');
	 p.style.left=(Math.random()*100)+'vw';p.style.background=cols[i%cols.length];p.style.setProperty('--dx',(Math.random()*30-15)+'vw');
	 p.style.animationDuration=(2.6+Math.random()*2.4)+'s';p.style.animationDelay=(Math.random()*0.9)+'s';p.style.opacity='0';
	 if(i%3===0){p.style.width='7px';p.style.height='7px';p.style.borderRadius='50%';}box.appendChild(p);}
	 document.body.appendChild(box);setTimeout(function(){box.remove();},6500);}
	function shake(){document.body.classList.remove('thh-bfx-shake');void document.body.offsetWidth;document.body.classList.add('thh-bfx-shake');setTimeout(function(){document.body.classList.remove('thh-bfx-shake');},800);}
	function intro(){if(!seen){setTimeout(function(){shake();confetti(90);},350);}
	 var cta=document.querySelectorAll('.thh-bfx-cta,.h2-ctas a:first-child');for(var i=0;i<cta.length;i++){cta[i].addEventListener('mouseenter',function(){confetti(25);},{once:true});}}
	if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',intro);}else{intro();}
	})();
	</script>
	<?php endif; ?>
	<?php
}, 99 );

/* Instellingen: WooCommerce › Black Friday. */
add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'Black Friday', 'Black Friday', 'manage_woocommerce', 'thh-black-friday', function () {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
		$keys = array( 'mode', 'start', 'end', 'title', 'text', 'cta', 'link' );
		if ( isset( $_POST['thh_bf_save'] ) && check_admin_referer( 'thh_bf_save' ) ) { update_option( 'thh_bf_bar_live', empty( $_POST['thh_bf_bar'] ) ? '0' : '1' ); }
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
		echo '<tr><th>Balk bovenaan</th><td><label><input type="checkbox" name="thh_bf_bar" value="1"' . checked( $s['bar'], '1', false ) . '> Balk met afteller live voor iedereen (ook als de rest nog op proef staat)</label></td></tr>';
		$labels = array( 'start' => 'Start (jjjj-mm-dd uu:mm)', 'end' => 'Einde, afteller loopt hierheen', 'title' => 'Titel in de balk', 'text' => 'Tekst in de balk', 'cta' => 'Knoptekst', 'link' => 'Link' );
		foreach ( $labels as $k => $l ) {
			echo '<tr><th>' . esc_html( $l ) . '</th><td><input class="regular-text" name="thh_bf_' . esc_attr( $k ) . '" value="' . esc_attr( $s[ $k ] ) . '"></td></tr>';
		}
		echo '</table><p><button class="button button-primary" name="thh_bf_save" value="1">Opslaan</button></p></form></div>';
	} );
}, 99 );
