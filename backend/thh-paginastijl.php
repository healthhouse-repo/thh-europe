<?php
/**
 * THH paginastijl — gewone pagina's (info, service, landingspagina's) in de stijl van de nieuwe homepage.
 * - Oude Elementor-hero (.page-hero) en de themakop (.page-header) worden een afgeronde navy kaart.
 * - Teksten op infopagina's: rustiger koppen, leesbare breedte.
 * - FAQ-pagina (74112) krijgt eigen opmaak.
 */

add_action('wp_head', function () {
	if (is_admin() || is_front_page() || !is_page()) return;
	if (function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page())) return;
	?>
<style id="thh-paginastijl">
:root{--thh-nv:#14204a;--thh-rd:#e2373f;--thh-bg:#f5f6fa;--thh-mut:#5a6072}
/* oude Elementor-hero */
body.page section.page-hero{max-width:1240px!important;margin:16px auto 0!important;border-radius:24px!important;overflow:hidden!important;background:var(--thh-nv)!important;background-image:none!important;min-height:0!important;padding:0!important;position:relative}
body.page section.page-hero:before,body.page section.page-hero:after{display:none!important}
body.page section.page-hero>.elementor-background-overlay,body.page section.page-hero .elementor-shape{display:none!important}
body.page section.page-hero>.elementor-container{min-height:0!important;max-width:none!important;padding:48px 56px!important}
body.page section.page-hero .hero-inner>.elementor-widget-wrap{padding:0!important}
body.page section.page-hero .title-sub{display:inline-block!important;background:rgba(255,255,255,.14)!important;color:#fff!important;font-weight:600!important;font-size:12px!important;letter-spacing:.12em!important;text-transform:uppercase!important;padding:8px 14px!important;border-radius:6px!important;margin:0 0 16px!important}
body.page section.page-hero h1{margin:0!important;color:#fff!important;font-weight:900!important;font-size:clamp(34px,4.6vw,60px)!important;line-height:1.02!important;text-transform:uppercase!important;letter-spacing:-.01em!important}
body.page section.page-hero h1 span{color:transparent!important;-webkit-text-stroke:2px #fff!important}
/* themakop (pagina's zonder eigen hero) */
body.page .site-main>.page-header{max-width:1240px;margin:16px auto 8px!important;background:var(--thh-nv);border-radius:24px;padding:48px 56px!important;box-sizing:border-box;position:relative;overflow:hidden}
body.page .site-main>.page-header:before{content:"The Health House";display:inline-block;background:rgba(255,255,255,.14);color:#fff;font-weight:600;font-size:12px;letter-spacing:.12em;text-transform:uppercase;padding:8px 14px;border-radius:6px;margin:0 0 16px}
body.page .site-main>.page-header .entry-title{display:block!important;margin:0!important;color:#fff!important;font-weight:900!important;font-size:clamp(34px,4.6vw,60px)!important;line-height:1.02!important;text-transform:uppercase!important;letter-spacing:-.01em!important;padding:0!important;max-width:none!important}
/* tekstpagina's: rustiger koppen */
body.thh-ps-tekst .elementor-widget-heading h2.elementor-heading-title,body.thh-ps-tekst .page-content h2{color:var(--thh-nv)!important;font-weight:700!important;text-transform:none!important;letter-spacing:-.01em!important;font-size:clamp(24px,2.6vw,32px)!important;line-height:1.2!important}
body.thh-ps-tekst .elementor-widget-heading h3.elementor-heading-title,body.thh-ps-tekst .page-content h3{color:var(--thh-nv)!important;font-weight:700!important;text-transform:none!important;letter-spacing:-.01em!important}
body.thh-ps-tekst .elementor-widget-text-editor,body.thh-ps-tekst .page-content p,body.thh-ps-tekst .page-content li{color:#1b1f2e;line-height:1.7}
body.thh-ps-tekst .elementor-widget-text-editor a,body.thh-ps-tekst .page-content a{color:var(--thh-nv);text-decoration:underline;text-underline-offset:3px}
@media(max-width:900px){body.page section.page-hero>.elementor-container{padding:32px 28px!important}body.page .site-main>.page-header{padding:32px 28px!important}}
@media(max-width:640px){body.page section.page-hero{margin:12px 14px 0!important;border-radius:18px!important}body.page section.page-hero>.elementor-container{padding:24px 20px!important}body.page .site-main>.page-header{margin:12px 14px 8px!important;border-radius:18px;padding:24px 20px!important}body.page section.page-hero h1,body.page .site-main>.page-header .entry-title{font-size:32px!important}body.page section.page-hero h1 span{-webkit-text-stroke:1.2px #fff!important}}
<?php if (is_page(74112)) : ?>
/* FAQ */
body.page-id-74112 .site-main>.page-header{display:block!important}
body.page-id-74112 .page-header .entry-title{display:block!important}
.thh-faq{--nv:#14204a;--rd:#e2373f;--bg:#f5f6fa;--mut:#5a6072;max-width:1240px;margin:0 auto;padding:24px 20px 56px;color:#1b1f2e;font-family:inherit}
.thh-faq *{box-sizing:border-box}
.thh-faq__intro{max-width:720px;margin:8px 0 20px}.thh-faq__intro h1{display:none}.thh-faq__intro p{margin:0;font-size:16px;line-height:1.6;color:var(--mut)}
.thh-faq__search-wrap{position:relative;margin:0 0 16px;max-width:720px}
.thh-faq__search{width:100%;min-height:52px;padding:12px 48px 12px 18px;border:1px solid #e1e4ee;border-radius:999px;background:#fff;color:var(--nv);font:500 15px/1.4 inherit;outline:none}
.thh-faq__search:focus{border-color:var(--nv);box-shadow:0 0 0 3px rgba(20,32,74,.08)}
.thh-faq__search-icon{position:absolute;right:18px;top:50%;width:20px;height:20px;transform:translateY(-50%);color:var(--nv);pointer-events:none}
.thh-faq__nav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 32px}
.thh-faq .thh-faq__chip{display:inline-flex;align-items:center;border:1px solid #e1e4ee;background:#fff;color:var(--nv);border-radius:999px;padding:8px 14px;font-size:13.5px;font-weight:500;text-decoration:none}
.thh-faq .thh-faq__chip:hover{border-color:var(--nv);background:var(--bg);color:var(--nv)}
.thh-faq__category{margin:0 0 36px;scroll-margin-top:120px}
.thh-faq .thh-faq__category h2{color:var(--nv);font-size:22px;font-weight:700;text-transform:none;letter-spacing:-.01em;margin:0 0 14px}
.thh-faq__items{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:10px 16px;align-items:start}
.thh-faq__item{background:var(--bg);border-radius:14px;padding:0 20px;border:0}
.thh-faq__item summary{list-style:none;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:16px;padding:18px 0;font-weight:600;font-size:15px;color:var(--nv)}
.thh-faq__item summary::-webkit-details-marker{display:none}
.thh-faq__plus{position:relative;width:14px;height:14px;flex:none}.thh-faq__plus:before,.thh-faq__plus:after{content:"";position:absolute;left:0;top:6px;width:14px;height:2px;background:var(--nv);border-radius:2px;transition:transform .2s}.thh-faq__plus:after{transform:rotate(90deg)}.thh-faq__item[open] .thh-faq__plus:after{transform:rotate(0)}
.thh-faq__answer{padding:0 0 18px}.thh-faq__answer p{margin:0;font-size:14.5px;line-height:1.65;color:var(--mut)}.thh-faq__answer a{color:var(--nv);text-decoration:underline}
.thh-faq__empty{color:var(--mut)}
.thh-faq__contact{margin-top:24px;background:var(--nv);color:#fff;border-radius:24px;padding:36px 40px}
.thh-faq .thh-faq__contact h2{color:#fff;font-size:26px;font-weight:700;text-transform:none;margin:0 0 8px}.thh-faq__contact p{color:rgba(255,255,255,.82);margin:0 0 18px;font-size:15px;line-height:1.6}
.thh-faq__contact-links{display:flex;flex-wrap:wrap;gap:12px}
.thh-faq .thh-faq__button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 24px;border-radius:999px;font-weight:600;font-size:15px;text-decoration:none;background:var(--rd);color:#fff;border:0}
.thh-faq .thh-faq__button:nth-child(n+2){background:transparent;border:2px solid rgba(255,255,255,.7);color:#fff}
.thh-faq .thh-faq__button:hover{filter:brightness(.95);color:#fff}
@media(max-width:900px){.thh-faq__items{grid-template-columns:minmax(0,1fr)}}
@media(max-width:640px){.thh-faq{padding:12px 14px 40px}.thh-faq__contact{padding:26px 20px;border-radius:18px}.thh-faq__item{padding:0 16px}}
<?php endif; ?>
</style>
<?php
}, 99);

/* tekstpagina's markeren voor de rustigere koppen */
add_filter('body_class', function ($c) {
	if (is_page(['betaling', 'retourneren', 'privacy-beleid', 'cookies', 'terugroepacties', 'advies', 'werken-bij', 'gratisconsult', 'franchise', 'loyalty', 'membership', 'abonnementen', 'zakelijk-account'])) $c[] = 'thh-ps-tekst';
	return $c;
});
