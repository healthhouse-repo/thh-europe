<?php
/**
 * THH – Sticky productfoto (desktop), versie 2 (2 nov 2026)
 * ------------------------------------------------------------------
 * De fotokolom links blijft in beeld terwijl de tekstkolom rechts doorscrollt.
 *
 * Versie 1 zette de sticky alleen aan als de hele galerij op het scherm paste, en zette
 * hem tijdens het laden/varianten kiezen aan en uit. Daardoor schoot de foto weg (galerij
 * net te hoog) of sprong hij (aan/uit-wissel). Versie 2:
 *   - altijd sticky op desktop (>= 1025px) zolang de tekstkolom langer is dan de foto;
 *   - is de galerij hoger dan het scherm, dan "plakt" hij aan de onderkant: hij scrolt mee
 *     tot de onderkant van de galerij in beeld is en blijft dan staan (geen sprong);
 *   - geen transform/will-change meer (gaf haperen tijdens scrollen);
 *   - galerij wisselt foto zonder schuifanimatie (Safari-tekenfout uit v1 blijft opgelost).
 *
 * Uitzetten per bezoek: ?stickyfoto=0. Helemaal uit: THH_STICKY_FOTO_LIVE op false.
 */

if (!defined('THH_STICKY_FOTO_LIVE')) define('THH_STICKY_FOTO_LIVE', true);

if (!function_exists('thh_sticky_foto_actief')) {
	function thh_sticky_foto_actief() {
		if (!function_exists('is_product') || !is_product()) return false;
		if (isset($_GET['stickyfoto'])) return '0' !== (string) $_GET['stickyfoto'];
		return (bool) THH_STICKY_FOTO_LIVE;
	}
}

add_action('wp_head', function () {
	if (!thh_sticky_foto_actief()) return;
	?>
<style id="thh-sticky-foto-css">
@media (min-width:1025px){
	.elementor-location-single .thh-sticky-rij{align-items:flex-start}
	.elementor-location-single .thh-sticky-kolom{align-self:flex-start}
	body.thh-sticky-foto .elementor-location-single .thh-sticky-kolom{position:sticky;top:var(--thh-sf-top,24px);align-self:flex-start}
	.elementor-location-single .woocommerce-product-gallery .flex-viewport,
	.elementor-location-single .woocommerce-product-gallery__wrapper,
	.elementor-location-single .woocommerce-product-gallery__wrapper > *{-webkit-transition:none!important;transition:none!important}
}
</style>
	<?php
}, 99);

add_action('wp_footer', function () {
	if (!thh_sticky_foto_actief()) return;
	?>
<script id="thh-sticky-foto-js">
(function(){
	function start(){
		var galerij=document.querySelector('.elementor-location-single .woocommerce-product-gallery');
		if(!galerij)return;
		var kolom=galerij.closest('.elementor-column');
		if(!kolom||!kolom.parentElement)return;
		var rij=kolom.parentElement,tekst=null;
		for(var i=0;i<rij.children.length;i++){if(rij.children[i]!==kolom){tekst=rij.children[i];break;}}
		if(!tekst)return;
		kolom.classList.add('thh-sticky-kolom');rij.classList.add('thh-sticky-rij');
		var adminbar=document.getElementById('wpadminbar'),aan=null,laatsteTop=null;
		function meet(){
			var desk=window.innerWidth>=1025;
			var kh=kolom.getBoundingClientRect().height,th=tekst.getBoundingClientRect().height;
			var moet=desk&&kh>0&&th>kh+60;
			if(moet!==aan){aan=moet;document.body.classList.toggle('thh-sticky-foto',moet);}
			if(!moet)return;
			var boven=(adminbar?adminbar.offsetHeight:0)+24;
			var top=(kh+boven+24>window.innerHeight)?Math.round(window.innerHeight-kh-24):boven;
			if(top!==laatsteTop){laatsteTop=top;kolom.style.setProperty('--thh-sf-top',top+'px');}
		}
		var t=null;function straks(){clearTimeout(t);t=setTimeout(meet,80);}
		meet();
		window.addEventListener('load',meet);
		window.addEventListener('resize',straks);
		if(window.ResizeObserver){var ro=new ResizeObserver(straks);ro.observe(galerij);ro.observe(tekst);}
	}
	if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
})();
</script>
	<?php
}, 99);
