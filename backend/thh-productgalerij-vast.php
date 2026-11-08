<?php
/**
 * THH — Productfoto blijft in zijn kader
 *
 * De schuif-galerij (FlexSlider) op productpagina's schoof de foto soms half uit beeld tijdens het scrollen.
 * We zetten de schuif-functie uit: de hoofdfoto staat vast, de andere foto's staan als kleine fotootjes
 * eronder. Klik op een fotootje en het wordt de grote foto. Bij het kiezen van een maat/smaak wisselt
 * WooCommerce de hoofdfoto zoals voorheen.
 */
if (!defined('ABSPATH')) return;

add_action('after_setup_theme', function () {
	remove_theme_support('wc-product-gallery-slider');
}, 100);

add_action('wp_footer', function () {
	if (!function_exists('is_product') || !is_product()) return; ?>
<style>
.woocommerce-product-gallery{opacity:1!important}
.woocommerce-product-gallery .woocommerce-product-gallery__wrapper{display:flex;flex-wrap:wrap;gap:10px;transform:none!important;width:auto!important;margin:0}
.woocommerce-product-gallery .woocommerce-product-gallery__image{flex:0 0 72px;width:72px!important;height:72px;border:1.5px solid #e1e4ee;border-radius:10px;overflow:hidden;cursor:pointer;background:#fff;margin:0!important}
.woocommerce-product-gallery .woocommerce-product-gallery__image img{width:100%!important;height:100%!important;object-fit:contain}
.woocommerce-product-gallery .woocommerce-product-gallery__image.thh-groot{flex:0 0 100%;width:100%!important;height:auto;border:0;border-radius:0;cursor:default;order:-1}
.woocommerce-product-gallery .woocommerce-product-gallery__image.thh-groot img{height:auto!important;max-height:560px}
.woocommerce-product-gallery .woocommerce-product-gallery__image.thh-actief:not(.thh-groot){border-color:#14204a}
.woocommerce-product-gallery .woocommerce-product-gallery__wrapper:has(.woocommerce-product-gallery__image:only-child) .woocommerce-product-gallery__image{border:0}
</style>
<script>
(function(){
	var g=document.querySelector('.woocommerce-product-gallery');if(!g)return;
	var w=g.querySelector('.woocommerce-product-gallery__wrapper');if(!w)return;
	var items=[].slice.call(w.querySelectorAll('.woocommerce-product-gallery__image'));if(!items.length)return;
	items[0].classList.add('thh-groot','thh-actief');
	if(items.length<2)return;
	// fotootjes: een kopie van elke foto onder de hoofdfoto
	items.forEach(function(it,i){
		var img=it.querySelector('img');if(!img)return;
		var t=document.createElement('div');t.className='woocommerce-product-gallery__image thh-duim'+(i===0?' thh-actief':'');
		var ti=document.createElement('img');ti.src=img.getAttribute('data-src')||img.currentSrc||img.src;ti.alt='';t.appendChild(ti);
		t.addEventListener('click',function(){
			var groot=items[0].querySelector('img');var a=items[0].querySelector('a');
			var big=img.getAttribute('data-large_image')||img.getAttribute('data-src')||img.src;
			groot.src=big;groot.removeAttribute('srcset');if(a)a.href=big;
			[].forEach.call(w.querySelectorAll('.thh-duim'),function(d){d.classList.remove('thh-actief');});t.classList.add('thh-actief');
		});
		w.appendChild(t);
		if(i>0)it.style.display='none';
	});
	items[0].querySelector('a')&&items[0].querySelector('a').addEventListener('click',function(e){e.preventDefault();});
})();
</script>
<?php }, 99);
