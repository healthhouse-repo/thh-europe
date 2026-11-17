<?php
/**
 * THH Bedankpagina — confetti na een geslaagde bestelling (18 nov 2026)
 *
 * Op de afrondpagina van de checkout (bestelling ontvangen) valt één keer confetti (één golf)
 * over het scherm. Alleen bij een betaalde of in behandeling zijnde bestelling, niet bij
 * een mislukte betaling. Eén keer per bestelling (ook niet opnieuw bij herladen).
 * Kleuren: navy/rood/wit, tijdens de Black Friday-stijl goud/rood/wit.
 * Bezoekers met "minder beweging" aan krijgen niets. Uitzetten = deze snippet deactiveren.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) ) { return; }
	$order_id = absint( get_query_var( 'order-received' ) );
	$order    = $order_id ? wc_get_order( $order_id ) : false;
	if ( ! $order || $order->has_status( array( 'failed', 'cancelled', 'pending' ) ) ) { return; }
	$key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
	if ( ! $key || ! hash_equals( $order->get_order_key(), $key ) ) { return; }
	$bf     = function_exists( 'thh_bf_active' ) && thh_bf_active();
	$colors = $bf ? array( '#f5c542', '#ffdf7e', '#e3262f', '#ffffff', '#111114' ) : array( '#14204a', '#e3262f', '#ffffff', '#f5c542', '#3d6bff' );
	?>
	<style>
	@keyframes thhTyFall{0%{transform:translate3d(0,-10vh,0) rotate(0);opacity:0}8%{opacity:1}100%{transform:translate3d(var(--dx),110vh,0) rotate(var(--rot));opacity:.95}}
	@keyframes thhTySway{0%,100%{margin-left:0}50%{margin-left:18px}}
	.thh-ty-confetti{position:fixed;inset:0;pointer-events:none;z-index:99999;overflow:hidden}
	.thh-ty-confetti i{position:absolute;top:0;display:block;animation:thhTyFall linear forwards}
	.thh-ty-confetti i b{display:block;width:100%;height:100%;border-radius:inherit;background:inherit;animation:thhTySway 1.4s ease-in-out infinite}
	@media(prefers-reduced-motion:reduce){.thh-ty-confetti{display:none}}
	</style>
	<script>
	(function(){
	if(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches)return;
	var k='thhTyConfetti<?php echo (int) $order_id; ?>';try{if(localStorage.getItem(k))return;localStorage.setItem(k,'1');}catch(e){}
	var cols=<?php echo wp_json_encode( $colors ); ?>;
	function burst(n,delay){var box=document.createElement('div');box.className='thh-ty-confetti';box.setAttribute('aria-hidden','true');
	 for(var i=0;i<n;i++){var p=document.createElement('i'),w=6+Math.random()*7,shape=i%4;
	  p.style.left=(Math.random()*100)+'vw';p.style.background=cols[i%cols.length];
	  p.style.width=(shape===0?w:shape===1?w*0.45:w)+'px';p.style.height=(shape===0?w:shape===1?w*2.6:w*1.5)+'px';
	  if(shape===0)p.style.borderRadius='50%';else p.style.borderRadius='2px';
	  p.style.setProperty('--dx',(Math.random()*36-18)+'vw');p.style.setProperty('--rot',(Math.random()*1080-540)+'deg');
	  p.style.animationDuration=(3+Math.random()*3)+'s';p.style.animationDelay=(delay+Math.random()*1.2)+'s';p.style.opacity='0';
	  p.appendChild(document.createElement('b'));box.appendChild(p);}
	 document.body.appendChild(box);setTimeout(function(){box.remove();},(delay+8)*1000);}
	function go(){burst(160,0.2);}
	if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',go);}else{go();}
	})();
	</script>
	<?php
}, 99 );
