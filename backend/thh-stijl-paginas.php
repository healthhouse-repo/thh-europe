<?php
/**
 * THH huisstijl voor oudere pagina's (zelfde rustige stijl als de nieuwe homepage).
 * - Adviesformulier (Gravity Form 2, sectie .section-niet-alleen): witte kaart, gewone letters, navy knop.
 * - Over The Health House: USP-blok uitgelijnd en in huisstijl (zie thh_stijl_over_css).
 */
if (!defined('ABSPATH')) return;

add_action('wp_head', function () {
	?>
	<style id="thh-stijl-advies">
	.section-niet-alleen{background-color:#14204A!important;background-image:none!important}
	.section-niet-alleen::after,.section-niet-alleen::before,.section-niet-alleen>.elementor-background-overlay{display:none!important;background:none!important}
	.section-niet-alleen .gform_title,.section-niet-alleen .elementor-heading-title{text-transform:none!important;font-size:34px!important;font-weight:700!important;letter-spacing:-.01em!important;line-height:1.2!important;color:#fff!important}
	.section-niet-alleen .intro-text{font-size:16px!important;color:rgba(255,255,255,.82)!important}
	#gform_wrapper_2{background:#fff;border-radius:18px;padding:28px!important;max-width:560px;margin:8px auto 0!important;box-shadow:0 24px 60px rgba(0,0,0,.25);text-align:left}
	#gform_wrapper_2 .gform_fields{display:grid!important;grid-template-columns:1fr 1fr!important;gap:12px!important;row-gap:12px!important}
	#gform_wrapper_2 .gfield{grid-column:1/-1!important;margin:0!important;padding:0!important;width:auto!important;max-width:none!important}
	#gform_wrapper_2 #field_2_4,#gform_wrapper_2 #field_2_3{grid-column:span 1!important}
	#gform_wrapper_2 input[type=text],#gform_wrapper_2 input[type=email],#gform_wrapper_2 input[type=tel],#gform_wrapper_2 textarea{width:100%!important;height:46px!important;border:1.5px solid #e5e7eb!important;border-radius:10px!important;padding:0 14px!important;font-size:15px!important;font-weight:400!important;text-transform:none!important;letter-spacing:0!important;background:#fff!important;color:#111827!important;box-shadow:none!important;margin:0!important;font-family:inherit!important}
	#gform_wrapper_2 textarea{height:130px!important;padding:12px 14px!important;resize:vertical}
	#gform_wrapper_2 input:focus,#gform_wrapper_2 textarea:focus{outline:0!important;border-color:#14204A!important}
	#gform_wrapper_2 ::placeholder{text-transform:none!important;font-weight:400!important;color:#9ca3af!important;letter-spacing:0!important;opacity:1}
	#gform_wrapper_2 .gform_footer,#gform_wrapper_2 .gform-footer{position:static!important;margin:14px 0 0!important;padding:0!important;display:block!important;width:100%!important}
	#gform_wrapper_2 .gform_footer button,#gform_wrapper_2 .gform_footer input[type=submit],#gform_wrapper_2 .gform_button{position:static!important;transform:none!important;width:100%!important;height:48px!important;border:0!important;border-radius:999px!important;background:#14204A!important;color:#fff!important;font-size:15px!important;font-weight:600!important;text-transform:none!important;letter-spacing:0!important;margin:0!important;padding:0 20px!important;cursor:pointer;box-shadow:none!important}
	#gform_wrapper_2 .gform_footer button:hover,#gform_wrapper_2 .gform_button:hover{background:#0d1538!important}
	#gform_wrapper_2 .gfield_label,#gform_wrapper_2 .gform_required_legend{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(0 0 0 0)!important}
	#gform_wrapper_2 .validation_message,#gform_wrapper_2 .gfield_validation_message{background:#fdecee!important;color:#b42318!important;border:0!important;border-radius:8px!important;padding:6px 10px!important;margin-top:6px!important;font-size:13px!important;text-transform:none!important}
	#gform_wrapper_2 .gform_validation_errors{border-radius:10px!important;margin-bottom:14px!important}
	#gform_confirmation_wrapper_2,.section-niet-alleen .gform_confirmation_wrapper{background:#fff;border-radius:18px;padding:28px;max-width:560px;margin:8px auto 0;color:#14204A;font-size:16px;text-align:center}
	@media(max-width:600px){#gform_wrapper_2{padding:20px!important}#gform_wrapper_2 #field_2_4,#gform_wrapper_2 #field_2_3{grid-column:1/-1!important}.section-niet-alleen .gform_title,.section-niet-alleen .elementor-heading-title{font-size:26px!important}}
	</style>
	<style id="thh-stijl-over">
	.section-about-usp{padding:64px 16px!important}
	.section-about-usp>.elementor-container{max-width:1240px!important;gap:16px;align-items:stretch!important}
	.section-about-usp .elementor-column{display:flex}
	.section-about-usp .elementor-column>.elementor-widget-wrap{background:#f5f5f6;border-radius:18px;padding:28px 26px!important;display:flex!important;flex-direction:column!important;align-items:flex-start!important;align-content:flex-start!important;justify-content:flex-start!important;gap:14px;height:100%;text-align:left!important}
	.section-about-usp .elementor-widget{margin:0!important;width:100%;text-align:left!important}
	.section-about-usp .elementor-widget-image img{width:52px!important;height:52px!important;max-width:none!important;margin:0!important}
	.section-about-usp .elementor-widget-html *{text-align:left!important}
	.section-about-usp .text-bold{font-size:18px!important;font-weight:700!important;color:#14204A!important;margin:0 0 8px!important;line-height:1.3!important;text-transform:none!important}
	.section-about-usp p:not(.text-bold){font-size:15px!important;line-height:1.6!important;color:#4b5563!important;margin:0!important}
	@media(max-width:767px){.section-about-usp>.elementor-container{flex-direction:column}.section-about-usp .elementor-column{width:100%!important}}
	</style>
	<?php
}, 99);
