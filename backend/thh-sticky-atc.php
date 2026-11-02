<?php
/**
 * THH Sticky winkelwagenknop op de productpagina.
 * Na de eerste keer scrollen (vanaf 120px) verschijnt rechtsonder een knop
 * met productfoto, naam en prijs. Klik = dezelfde knop als in het formulier (dus met de gekozen
 * smaak/maat). Nog geen variant gekozen? Dan scrolt hij terug naar de keuze.
 * Desktop: zwevende kaart rechtsonder, boven de chatknop. Mobiel: balk onderin, chatknop schuift omhoog.
 */

add_action('wp_footer', function () {
	if (!function_exists('is_product') || !is_product()) return;
	$p = wc_get_product(get_queried_object_id());
	if (!$p || !$p->is_purchasable() || !$p->is_in_stock()) return;
	$img = wp_get_attachment_image_url($p->get_image_id(), 'woocommerce_thumbnail') ?: wc_placeholder_img_src();
	?>
<div id="thhSatc" class="thh-satc" aria-hidden="true">
	<img src="<?php echo esc_url($img); ?>" alt="" class="thh-satc-img">
	<div class="thh-satc-info"><strong><?php echo esc_html($p->get_name()); ?></strong><span class="thh-satc-prijs"><?php echo wp_kses_post($p->get_price_html()); ?></span></div>
	<button type="button" class="thh-satc-btn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 12.4a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 2-1.6L22 7H6"/></svg><span class="thh-satc-lbl">In winkelwagen</span></button>
</div>
<style>
.thh-satc{position:fixed;right:22px;bottom:96px;z-index:9990;display:flex;align-items:center;gap:12px;background:#fff;border-radius:18px;padding:10px 10px 10px 12px;box-shadow:0 14px 40px rgba(20,32,74,.22);max-width:440px;transform:translateY(20px);opacity:0;pointer-events:none;transition:transform .25s ease,opacity .25s ease;font-family:inherit}
.thh-satc.is-on{transform:none;opacity:1;pointer-events:auto}
.thh-satc-img{width:52px;height:52px;object-fit:contain;border-radius:10px;background:#f5f6fa;flex:none}
.thh-satc-info{display:flex;flex-direction:column;gap:2px;min-width:0;flex:1}
.thh-satc-info strong{font-size:14px;line-height:1.25;color:#14204a;font-weight:600;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.thh-satc-prijs{font-size:14px;font-weight:700;color:#14204a}.thh-satc-prijs del{color:#8a93ab;font-weight:500;margin-right:4px}.thh-satc-prijs ins{text-decoration:none}
.thh-satc .thh-satc-btn{flex:none;display:inline-flex;align-items:center;gap:8px;background:#e2373f!important;color:#fff!important;border:0!important;border-radius:999px!important;padding:0 20px!important;min-height:48px;font-weight:700!important;font-size:14px!important;text-transform:none!important;letter-spacing:0!important;cursor:pointer;font-family:inherit;box-shadow:none!important}
.thh-satc .thh-satc-btn:hover{background:#c92c33!important}
.thh-satc .thh-satc-btn.is-busy{opacity:.7;pointer-events:none}
.thh-satc-flash{animation:thhSatcFlash 1.2s ease 1}@keyframes thhSatcFlash{0%,100%{box-shadow:none}30%{box-shadow:0 0 0 4px rgba(226,55,63,.45)}}
@media(max-width:767px){
.thh-satc{left:0;right:0;bottom:0;max-width:none;border-radius:16px 16px 0 0;padding:10px 14px calc(10px + env(safe-area-inset-bottom));box-shadow:0 -8px 24px rgba(20,32,74,.16);transform:translateY(100%)}
.thh-satc-img{width:44px;height:44px}.thh-satc-info strong{font-size:13px;-webkit-line-clamp:1}.thh-satc .thh-satc-btn{padding:0 16px!important}
body.thh-satc-open .thhc-launch{bottom:92px!important;transition:bottom .25s ease}
}
</style>
<script>
(function(){var bar=document.getElementById('thhSatc');if(!bar)return;
var form=document.querySelector('form.cart'),btn=form&&form.querySelector('.single_add_to_cart_button');if(!btn){bar.remove();return;}
var lang=(document.documentElement.lang||'nl').slice(0,2),lbl={nl:'In winkelwagen',de:'In den Warenkorb',en:'Add to cart'}[lang]||'In winkelwagen',kies={nl:'Kies je variant',de:'Variante wählen',en:'Choose option'}[lang]||'Kies je variant';
var b=bar.querySelector('.thh-satc-btn'),l=bar.querySelector('.thh-satc-lbl'),pr=bar.querySelector('.thh-satc-prijs');
function needsChoice(){return btn.classList.contains('disabled')||btn.classList.contains('wc-variation-selection-needed')||btn.disabled;}
function upd(){l.textContent=needsChoice()?kies:lbl;var vp=form.querySelector('.woocommerce-variation-price .price');if(vp&&vp.innerHTML.trim())pr.innerHTML=vp.innerHTML;}
var shown=false;function show(on){if(on===shown)return;shown=on;bar.classList.toggle('is-on',on);bar.setAttribute('aria-hidden',on?'false':'true');document.body.classList.toggle('thh-satc-open',on);if(on)upd();}
var tick=false;function chk(){tick=false;var r=btn.getBoundingClientRect();show(window.scrollY>120);}
window.addEventListener('scroll',function(){if(!tick){tick=true;requestAnimationFrame(chk);}},{passive:true});window.addEventListener('resize',chk);chk();
if(window.jQuery){jQuery(form).on('found_variation reset_data hide_variation show_variation woocommerce_variation_has_changed',function(){setTimeout(upd,30);});jQuery(document.body).on('added_to_cart',function(){b.classList.remove('is-busy');});}
b.addEventListener('click',function(){
 if(needsChoice()){var t=form.querySelector('.variations')||form;t.scrollIntoView({behavior:'smooth',block:'center'});t.classList.remove('thh-satc-flash');void t.offsetWidth;t.classList.add('thh-satc-flash');return;}
 b.classList.add('is-busy');setTimeout(function(){b.classList.remove('is-busy');},4000);btn.click();});
})();
</script>
	<?php
}, 30);
