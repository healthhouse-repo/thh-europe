<?php
/**
 * THH — Productpagina: volgorde en look & feel zoals Zalando
 *
 * Rechterkolom: merk → titel → prijs (incl. btw) → actie-uitleg → maat/smaak → [aantal] In winkelwagen →
 * keuzehulp → club-blok → infokaart (verkocht en verzonden door THH, gratis verzending, afhalen) →
 * beschrijving (accordeon). Korte omschrijving staat direct onder de titel.
 * Weg: categorie-tags en "Merk:"-regel (d5043d7) en de USP-balk (7d8a0da).
 * Widget-ID's uit de single-product-template: b8900ef merk, 4b8cb73 titel, edb6a19 prijs, 6a35e17 winkelwagen,
 * 7d8a0da USP's, 59159bc afhalen, 115e858 korte omschrijving, 500d427 accordeon, d5043d7 tags + merk.
 */
if (!defined('ABSPATH')) return;

// infokaart onder het club-blok
add_action('woocommerce_after_add_to_cart_form', function () {
	if (!function_exists('is_product') || !is_product()) return; ?>
<div class="thh-zk-info">
	<div class="thh-zk-rij">Verkocht en verzonden door <b>The Health House</b>.</div>
	<div class="thh-zk-rij thh-zk-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="17.5" r="1.5"/><circle cx="17" cy="17.5" r="1.5"/></svg><b>Gratis verzending vanaf € 60</b></div>
	<div class="thh-zk-rij thh-zk-ico thh-zk-afhalen"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l1.5-5h15L21 9M3 9h18v11H3zM9 20v-6h6v6"/></svg><b>Ook op te halen in de winkel</b></div>
</div>
<?php }, 40);

add_action('wp_head', function () {
	if (!function_exists('is_product') || !is_product()) return; ?>
<style>
/* ---------- volgorde ---------- */
body.single-product .elementor-widget-wrap:has(>.elementor-element-4b8cb73)>*{order:11}
body.single-product .elementor-element-b8900ef{order:1!important}
body.single-product .elementor-element-4b8cb73{order:2!important}
body.single-product .elementor-element-edb6a19{order:3!important}
body.single-product .thh-b21-uitleg{order:3!important}
body.single-product .elementor-element-6a35e17{order:4!important}
body.single-product .elementor-element-115e858{order:2!important}
body.single-product .elementor-element-500d427{order:9!important}
body.single-product .elementor-element-d5043d7,body.single-product .elementor-element-7d8a0da,body.single-product .elementor-element-59159bc{display:none!important}

/* ---------- typografie ---------- */
body.single-product .elementor-element-b8900ef,body.single-product .elementor-element-b8900ef a{font-size:16px!important;font-weight:700!important;color:#1b1f2e!important;text-decoration:underline;text-underline-offset:3px}
body.single-product .elementor-element-4b8cb73 .product_title{font-size:clamp(22px,2.2vw,28px)!important;line-height:1.25!important;margin:2px 0 0!important;font-weight:700!important;text-transform:none!important}
body.single-product .elementor-element-edb6a19{margin-top:6px}
body.single-product .elementor-element-edb6a19 .price{font-size:22px!important;font-weight:700!important;color:#1b1f2e!important}
body.single-product .elementor-element-edb6a19 .price del{font-size:16px!important;font-weight:400!important;color:#6b7280!important}
body.single-product .elementor-element-edb6a19 .price ins{color:#e2373f!important;text-decoration:none}
body.single-product .elementor-element-edb6a19 .price:after{content:"inclusief btw";display:block;font-size:13px;font-weight:400;color:#6b7280;margin-top:2px}
body.single-product .thh-b21-uitleg{margin:6px 0 10px;font-size:14.5px!important;line-height:1.5!important;font-weight:500!important;background:#fdecee;color:#9b1c22!important;border-radius:12px;padding:12px 14px}
body.single-product .thh-b21-uitleg *{color:#9b1c22!important;font-size:14.5px!important}

/* ---------- maat/smaak en aantal ---------- */
body.single-product .elementor-element-6a35e17 .variations th,body.single-product .elementor-element-6a35e17 .variations .label,body.single-product .elementor-element-6a35e17 .variations label{font-size:14px!important;font-weight:500!important;color:#1b1f2e!important;text-transform:none!important}
body.single-product .elementor-element-6a35e17 .variable-items-wrapper .variable-item:not(.radio-variable-item){border-radius:10px!important;box-shadow:0 0 0 1.5px #c9ccd6!important;min-height:48px;min-width:72px}
body.single-product .elementor-element-6a35e17 .variable-items-wrapper .variable-item.selected:not(.radio-variable-item){box-shadow:0 0 0 2px #1b1f2e!important;background:#1b1f2e!important}
body.single-product .elementor-element-6a35e17 .variations select{border:1.5px solid #1b1f2e!important;border-radius:10px!important;min-height:52px;font-size:16px!important;padding:0 14px!important;background-color:#fff}
body.single-product .elementor-element-6a35e17 .reset_variations{font-size:13px;color:#6b7280!important}
body.single-product .thh-bundel-optie{border-radius:12px!important}
body.single-product .thh-bundel-optie.is-actief{border-color:#1b1f2e!important;box-shadow:0 0 0 1px #1b1f2e!important}

/* ---------- smaak/maat als dropdown over de volle breedte ---------- */
body.single-product .elementor-element-6a35e17 table.variations,body.single-product .elementor-element-6a35e17 table.variations tbody,body.single-product .elementor-element-6a35e17 table.variations tr,body.single-product .elementor-element-6a35e17 table.variations th,body.single-product .elementor-element-6a35e17 table.variations td{display:block!important;width:100%!important;padding:0!important;border:0!important;background:none!important}
body.single-product .elementor-element-6a35e17 table.variations tr{margin:0 0 14px!important}
body.single-product .elementor-element-6a35e17 table.variations th.label{margin:0 0 6px!important}
body.single-product .elementor-element-6a35e17 .variations .variable-items-wrapper,body.single-product .elementor-element-6a35e17 .reset_variations{display:none!important}
body.single-product .elementor-element-6a35e17 .variations select,body.single-product .elementor-element-6a35e17 .variations select.woo-variation-raw-select{display:block!important;visibility:visible!important;position:static!important;opacity:1!important;width:100%!important;max-width:none!important;height:56px!important;border:1.5px solid #1b1f2e!important;border-radius:12px!important;padding:0 44px 0 16px!important;font-size:16px!important;font-weight:400!important;color:#1b1f2e!important;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%231b1f2e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 14px center/22px!important;-webkit-appearance:none!important;appearance:none!important;cursor:pointer;box-shadow:none!important}
body.single-product .elementor-element-6a35e17 .variations select:focus{outline:2px solid #14204a;outline-offset:2px}
body.single-product .elementor-element-6a35e17 .thh-bundel{width:100%!important;max-width:none!important}
body.single-product .elementor-element-6a35e17 .thh-bundel-optie{width:100%!important;max-width:none!important}

/* ---------- knop In winkelwagen: breed, rond, donker ---------- */
body.single-product .elementor-element-6a35e17 form.cart:not(.variations_form),body.single-product .elementor-element-6a35e17 .woocommerce-variation-add-to-cart{display:flex!important;flex-wrap:wrap;gap:10px;align-items:stretch;width:100%}
body.single-product .elementor-element-6a35e17 form.cart>*:not(.quantity):not(.single_add_to_cart_button),body.single-product .elementor-element-6a35e17 .woocommerce-variation-add-to-cart>*:not(.quantity):not(.single_add_to_cart_button){flex:0 0 100%}
body.single-product .elementor-element-6a35e17 .quantity{margin:0!important;flex:0 0 auto}
body.single-product .elementor-element-6a35e17 .quantity .qty{height:56px!important;width:64px!important;border:1.5px solid #c9ccd6!important;border-radius:999px!important;font-size:16px!important;text-align:center}
body.single-product .elementor-element-6a35e17 .single_add_to_cart_button{flex:1 1 auto!important;width:auto!important;min-height:56px!important;border-radius:999px!important;background:#14204a!important;color:#fff!important;font-size:17px!important;font-weight:700!important;text-transform:none!important;letter-spacing:0!important;margin:0!important;border:0!important}
body.single-product .elementor-element-6a35e17 .single_add_to_cart_button:hover{background:#0b1433!important}
body.single-product .elementor-element-6a35e17 .single_add_to_cart_button.disabled{opacity:.45}
body.single-product .elementor-element-6a35e17 form.cart.thh-klaar .single_add_to_cart_button:not(.disabled){background:#e2373f!important}
body.single-product .elementor-element-6a35e17 form.cart.thh-klaar .single_add_to_cart_button:not(.disabled):hover{background:#c92c33!important}
body.single-product .elementor-element-6a35e17 .single_add_to_cart_button{transition:background .25s}

/* ---------- keuzehulp, club en infokaart ---------- */
body.single-product .thh-kh-balk{margin:16px 0 0!important;border-radius:16px!important;width:100%!important;max-width:none!important}
body.single-product .thh-clb{margin:16px 0 0}
body.single-product .thh-clb-box{border:2px solid #1b1f2e!important;border-radius:14px!important;padding:24px!important}
body.single-product .thh-clb-logo{border-color:#1b1f2e!important;color:#1b1f2e!important}
.thh-zk-info{margin:28px 0 8px;border:1.5px solid #1b1f2e;border-radius:14px;overflow:hidden;background:#fff}
.thh-zk-rij{padding:18px 22px;font-size:15px;line-height:1.45;color:#1b1f2e;border-top:1.5px solid #1b1f2e}
.thh-zk-rij:first-child{border-top:0}
.thh-zk-rij b{font-weight:700}
.thh-zk-ico{display:flex;align-items:center;gap:14px}
.thh-zk-ico svg{width:26px;height:26px;flex:none}
.thh-zk-afhalen a{color:#1b1f2e!important;text-decoration:none}
.thh-zk-afhalen{cursor:pointer}

/* ---------- lichtere, rustige tekst (zoals Zalando) ---------- */
body.single-product .elementor-element-115e858,body.single-product .elementor-element-115e858 p,
body.single-product .elementor-element-500d427 .elementor-tab-content,body.single-product .elementor-element-500d427 .elementor-tab-content p,
body.single-product .elementor-element-500d427 .elementor-tab-content li,body.single-product .elementor-element-500d427 .elementor-tab-content td{font-size:15px!important;font-weight:300!important;line-height:1.75!important;color:#2b2f3a!important;letter-spacing:0!important}
body.single-product .elementor-element-500d427 .elementor-tab-content strong,body.single-product .elementor-element-500d427 .elementor-tab-content b{font-weight:500!important;color:#1b1f2e!important}
body.single-product .elementor-element-500d427 .elementor-tab-content h2,body.single-product .elementor-element-500d427 .elementor-tab-content h3,body.single-product .elementor-element-500d427 .elementor-tab-content h4{font-size:16px!important;font-weight:600!important;text-transform:none!important;letter-spacing:0!important;line-height:1.4!important;color:#1b1f2e!important;margin:22px 0 6px!important}
body.single-product .elementor-element-500d427 .elementor-tab-content ul{padding-left:18px!important;margin:6px 0 12px!important}
body.single-product .elementor-element-500d427 .elementor-tab-content li{margin:2px 0!important}
body.single-product .elementor-element-500d427 .elementor-tab-content p{margin:0 0 12px!important}
body.single-product .thh-b21-uitleg,body.single-product .thh-b21-uitleg *{font-weight:400!important}
body.single-product .thh-clb-box p{font-weight:300!important;color:#2b2f3a!important}
body.single-product .thh-clb-box strong{font-weight:600!important}
.thh-zk-rij{font-weight:300}.thh-zk-rij b{font-weight:600!important}

/* ---------- accordeon zoals Zalando ---------- */
body.single-product .elementor-element-500d427 .elementor-accordion-item{border:0!important;border-top:1.5px solid #1b1f2e!important;margin:0!important}
body.single-product .elementor-element-500d427 .elementor-accordion-item:last-child{border-bottom:1.5px solid #1b1f2e!important}
body.single-product .elementor-element-500d427 .elementor-tab-title{position:relative;display:flex!important;align-items:center;justify-content:space-between;padding:26px 48px!important;border:1.5px solid transparent!important;border-radius:6px;margin:0!important;cursor:pointer;background:none!important}
body.single-product .elementor-element-500d427 .elementor-tab-title,body.single-product .elementor-element-500d427 .elementor-tab-title a{font-size:18px!important;font-weight:400!important;text-transform:uppercase!important;letter-spacing:.01em!important;color:#1b1f2e!important;text-decoration:none!important}
body.single-product .elementor-element-500d427 .elementor-tab-title:hover{background:#f1f3f6!important}
body.single-product .elementor-element-500d427 .elementor-tab-title.elementor-active{background:#d9dde3!important;border-color:#1b1f2e!important}
body.single-product .elementor-element-500d427 .elementor-accordion-icon{display:none!important}
body.single-product .elementor-element-500d427 .elementor-tab-title:after{content:"";width:12px;height:12px;border-right:1.6px solid #1b1f2e;border-bottom:1.6px solid #1b1f2e;transform:rotate(45deg);margin-top:-6px;transition:transform .2s;flex:none;order:2}
body.single-product .elementor-element-500d427 .elementor-tab-title.elementor-active:after{transform:rotate(-135deg);margin-top:6px}
body.single-product .elementor-element-500d427 .elementor-tab-content{border:0!important;padding:24px 48px 32px!important}
@media(max-width:767px){body.single-product .elementor-element-500d427 .elementor-tab-title{padding:20px 18px!important}body.single-product .elementor-element-500d427 .elementor-tab-title,body.single-product .elementor-element-500d427 .elementor-tab-title a{font-size:16px!important}body.single-product .elementor-element-500d427 .elementor-tab-content{padding:18px 18px 24px!important}}

/* ---------- hoofdletters zoals Zalando: alleen de accordeon-koppen in kapitalen, de rest gewone zinnen ---------- */
body.single-product .elementor-element-6a35e17 .thh-bundel-kop,body.single-product .elementor-element-6a35e17 .thh-bundel-kop *{text-transform:none!important;letter-spacing:0!important;font-size:15px!important;font-weight:600!important;color:#1b1f2e!important}
body.single-product .thh-bundel-optie [class*=badge],body.single-product .thh-bundel-optie [class*=label],body.single-product .thh-bundel-optie em,body.single-product .thh-bundel-optie small:not(.thh-bundel-stuk){text-transform:none!important;letter-spacing:0!important}
body.single-product .thh-kh-balk a,body.single-product .thh-kh-balk button,body.single-product .thh-kh-balk .elementor-button{text-transform:none!important;letter-spacing:0!important;font-size:15px!important}
body.single-product .onsale,body.single-product .elementor-widget-woocommerce-product-images .onsale{text-transform:none!important;letter-spacing:0!important}
body.single-product .elementor-element-6a35e17 .variations label,body.single-product .elementor-element-6a35e17 .variations th{text-transform:none!important;letter-spacing:0!important}

/* ---------- onderin ---------- */
body.single-product .elementor-element-115e858{margin:10px 0 4px}
body.single-product .elementor-element-115e858 p{font-size:15px;line-height:1.6;color:#1b1f2e}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
	var n=0,t=setInterval(function(){n++;
		var kh=document.querySelector('.thh-kh-balk'),doel=document.querySelector('.elementor-element-6a35e17 .thh-clb')||document.querySelector('.elementor-element-6a35e17 .thh-zk-info');
		if(kh&&doel&&kh.nextElementSibling!==doel){doel.parentNode.insertBefore(kh,doel);}
		if((kh&&doel)||n>40)clearInterval(t);
	},150);
	// dropdowns: 'Kies je smaak' i.p.v. 'Kies een optie'; stap-nummer voor 'Kies je voordeel'
	var stappen=0;
	[].forEach.call(document.querySelectorAll('.elementor-element-6a35e17 table.variations tr'),function(tr){
		stappen++;var lab=tr.querySelector('th.label label'),sel=tr.querySelector('select');if(!lab||!sel||!sel.options.length)return;
		var naam=(lab.textContent||'').replace(/^\s*\d+\s*/,'').trim().toLowerCase();if(naam)sel.options[0].text='Kies je '+naam;
	});
	var m=0,ts=setInterval(function(){m++;
		var kop=document.querySelector('.elementor-element-6a35e17 .thh-bundel-kop');
		if(kop&&stappen&&!kop.querySelector('.thh-stap')){var b=document.createElement('span');b.className='thh-stap';b.textContent=stappen+1;kop.insertBefore(b,kop.firstChild);}
		if(kop||m>40)clearInterval(ts);
	},150);
	// knop wordt rood zodra smaak/maat en aantal gekozen zijn
	var form=document.querySelector('.elementor-element-6a35e17 form.cart');
	if(form){
		var heeftKeuze=!!form.querySelector('table.variations select, .thh-bundel'),aangeraakt=!heeftKeuze;
		var check=function(){var btn=form.querySelector('.single_add_to_cart_button');var ok=aangeraakt&&btn&&!btn.classList.contains('disabled');form.classList.toggle('thh-klaar',!!ok);};
		form.addEventListener('change',function(){aangeraakt=true;setTimeout(check,50);});
		form.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('.thh-bundel-optie')){aangeraakt=true;setTimeout(check,50);}});
		if(window.jQuery){jQuery(form).on('found_variation reset_data show_variation hide_variation',function(){setTimeout(check,50);});}
		check();
	}
	// afhalen: gebruik de bestaande popup-link
	var a=document.querySelector('.elementor-element-59159bc a'),r=document.querySelector('.thh-zk-afhalen');
	if(a&&r){r.addEventListener('click',function(){a.click();});}
});
</script>
<?php }, 99);
