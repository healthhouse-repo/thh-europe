<?php
/**
 * THH — Productpagina: volgorde zoals Zalando
 *
 * Volgorde rechterkolom: merk → titel → prijs (incl. btw) → maat/smaak + in winkelwagen (+ club-blok) →
 * levering → afhalen in de winkel → keuzehulp → korte omschrijving → beschrijving (accordeon) → categorieën.
 * Alleen CSS op de bestaande Elementor-widgets (single product template), dus niets aan de inhoud veranderd.
 * Widget-ID's uit de template: b8900ef merk, 4b8cb73 titel, edb6a19 prijs, 6a35e17 winkelwagen, 7d8a0da levering,
 * 59159bc afhalen, 115e858 korte omschrijving, 500d427 accordeon, d5043d7 categorieën + merk.
 */
if (!defined('ABSPATH')) return;

add_action('wp_head', function () {
	if (!function_exists('is_product') || !is_product()) return; ?>
<style>
body.single-product .elementor-widget-wrap:has(>.elementor-element-4b8cb73)>*{order:11}
body.single-product .elementor-element-b8900ef{order:1}
body.single-product .elementor-element-4b8cb73{order:2}
body.single-product .elementor-element-edb6a19{order:3}
body.single-product .thh-b21-uitleg{order:3;margin:4px 0 8px}
body.single-product .elementor-element-6a35e17{order:4}
body.single-product .elementor-element-7d8a0da{order:5}
body.single-product .elementor-element-59159bc{order:6}
body.single-product .thh-kh-balk{order:7}
body.single-product .elementor-element-115e858{order:8}
body.single-product .elementor-element-500d427{order:9}
body.single-product .elementor-element-d5043d7{order:10}
/* merk als link boven de titel, titel iets rustiger */
body.single-product .elementor-element-b8900ef a,body.single-product .elementor-element-b8900ef{font-size:16px!important;font-weight:700!important;color:#14204a!important;text-decoration:underline;text-underline-offset:3px}
body.single-product .elementor-element-4b8cb73 .product_title{font-size:clamp(24px,2.4vw,32px)!important;line-height:1.2!important;margin:2px 0 0!important}
/* prijs met 'inclusief btw' eronder */
body.single-product .elementor-element-edb6a19{margin-top:4px}
body.single-product .elementor-element-edb6a19 .price:after{content:"inclusief btw";display:block;font-size:13px;font-weight:400;color:#5a6072;margin-top:2px;letter-spacing:0}
/* onderin: korte omschrijving en categorieën klein */
body.single-product .elementor-element-115e858{margin-top:8px}
body.single-product .elementor-element-115e858 p{font-size:15px;line-height:1.6;color:#1b1f2e}
body.single-product .elementor-element-d5043d7{margin-top:10px;opacity:.85}
body.single-product .elementor-element-d5043d7 a{font-size:12px!important;padding:4px 10px!important}
/* afhalen in de winkel als regel bij de levering */
body.single-product .elementor-element-59159bc{margin:-4px 0 6px}
</style>
<?php }, 99);
