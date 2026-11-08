<?php
/**
 * THH Werken bij v2 — pagina /werken-bij/ (23118) in de stijl van de nieuwe homepage.
 * De Elementor-inhoud wordt vervangen door de shortcode [thh_werkenbij_v2].
 * Het sollicitatieformulier blijft Gravity Forms-formulier 5 (zelfde velden, zelfde mail naar ismael@).
 * Vacatures staan in thh_wb2_vacatures(); aanpassen = die lijst wijzigen.
 */
if (!defined('ABSPATH')) return;

function thh_wb2_page() { return 23118; }

function thh_wb2_vacatures() {
	$medewerker = 'Je bent het gezicht van The Health House. Je kent ons assortiment en helpt klanten met passie op weg naar producten die passen bij hun doelen. Daarnaast houd je de winkel netjes, vul je aan en reken je af aan de kassa.';
	$manager    = 'Je bent eindverantwoordelijk voor de winkel: de resultaten, het team en de uitstraling. Je zorgt dat alles achter de schermen goed loopt, van voorraad en bestellingen tot het rooster, en je staat zelf ook in de winkel.';
	return [
		['Amsterdam', 'Winkelmedewerker', $medewerker, '32-40 uur', 'STORE ADVISOR'],
		['Amsterdam', 'Winkelmanager', $manager, '32-40 uur', 'STORE MANAGER'],
		['Heerhugowaard', 'Winkelmedewerker', $medewerker, '32-40 uur', 'STORE ADVISOR'],
		['Heerhugowaard', 'Winkelmanager', $manager, '32-40 uur', 'STORE MANAGER'],
		['Hoofddorp', 'Winkelmedewerker', $medewerker, 'Vrijdag t/m zondag', 'STORE ADVISOR'],
		['Alle winkels', 'Stage', 'Zit je op het mbo of hbo en zoek je een stage in retail of sportvoeding? Je draait mee in de winkel en leert alles over advies, verkoop, voorraad en de webshop.', 'In overleg', 'STAGAIRE'],
	];
}

add_filter('elementor/frontend/the_content', function ($c) {
	return (is_page(thh_wb2_page()) && in_the_loop()) ? do_shortcode('[thh_werkenbij_v2]') : $c;
}, 999);
add_filter('the_content', function ($c) {
	return (is_page(thh_wb2_page()) && in_the_loop() && !did_action('elementor/frontend/before_render')) ? do_shortcode('[thh_werkenbij_v2]') : $c;
}, 999);
add_action('wp_head', function () {
	if (is_page(thh_wb2_page())) echo '<style>body.page .site-main>.page-header{display:none!important}</style>';
});

add_shortcode('thh_werkenbij_v2', function () {
	$svg = function ($d) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>'; };
	$kaarten = [
		['<path d="M6.5 6.5h11M6.5 17.5h11M4 9v6M20 9v6M2 11v2M22 11v2"/>', 'Sport is je ding', 'Je werkt elke dag met producten waar je zelf ook mee bezig bent, tussen klanten die net zo gedreven zijn als jij.'],
		['<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>', 'Advies dat telt', 'Je helpt mensen echt verder met hun doelen. Geen verkooppraatje, maar eerlijk advies over voeding en supplementen.'],
		['<path d="M3 17l6-6 4 4 8-8M14 7h7v7"/>', 'Doorgroeien', 'Van medewerker naar manager: wie wil groeien, krijgt de ruimte en verantwoordelijkheid om dat te doen.'],
	];
	ob_start();
	if (function_exists('thh_pv2_css')) echo thh_pv2_css(); ?>
<style>
.pv-vac{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.pv-vk{border:1px solid #e1e4ee;border-radius:20px;padding:26px 28px;background:#fff;display:flex;flex-direction:column;gap:10px}
.pv-vk .pv-plaats{font-size:12px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#e2373f}
.pv .pv-vk h3{font-size:22px;font-weight:700}
.pv-vk p{margin:0;font-size:15px;line-height:1.65;color:#1b1f2e}
.pv-vk .pv-vvoet{margin-top:auto;padding-top:8px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.pv-uren{display:inline-flex;align-items:center;gap:6px;background:#f5f6fa;color:#14204a;font-size:13px;font-weight:600;border-radius:999px;padding:7px 12px}.pv-uren svg{width:15px;height:15px}
.pv .pv-vk .pv-btn{min-height:42px;font-size:14px;padding:0 20px;background:#14204a;color:#fff!important}.pv .pv-vk .pv-btn:hover{background:#0d1538}
.pv-gf{background:#14204a;border-radius:24px;padding:36px 40px;color:#fff}
.pv .pv-gf h3{color:#fff!important;font-size:22px;font-weight:700;margin-bottom:6px}
.pv-gf .pv-gsub{margin:0 0 14px;font-size:14.5px;line-height:1.55;color:rgba(255,255,255,.8)}
.pv-gf .gform_wrapper{margin:0!important}.pv-gf .gform_heading{display:none}
.pv-gf .gform_wrapper .gfield{margin-bottom:12px!important}
.pv-gf .gform_wrapper input[type=text],.pv-gf .gform_wrapper input[type=email],.pv-gf .gform_wrapper input[type=tel],.pv-gf .gform_wrapper select,.pv-gf .gform_wrapper textarea{width:100%!important;background:#fff!important;border:1px solid transparent!important;border-radius:12px!important;padding:12px 14px!important;font-size:15px!important;color:#1b1f2e!important;font-family:inherit!important;box-shadow:none!important}
.pv-gf .gform_wrapper textarea{min-height:120px}
.pv-gf .gform_wrapper .gfield_label,.pv-gf .gform_wrapper legend{color:#fff!important;font-size:14px!important;font-weight:500!important}
.pv-gf .gform_wrapper .gfield input,.pv-gf .gform_wrapper .gfield select,.pv-gf .gform_wrapper .gfield textarea,.pv-gf .gform_wrapper .ginput_container{max-width:none!important;width:100%!important}
.pv-gf .gform_wrapper textarea{height:150px!important}
.pv-gf #field_5_11>label{display:block!important;color:#fff!important;font-size:15px!important;font-weight:600!important;margin:6px 0 8px!important}
.pv-gf #field_5_11 .gform_drop_area{display:flex!important;flex-direction:column;align-items:center;gap:10px;border:2px dashed rgba(255,255,255,.55)!important;border-radius:16px!important;background:rgba(255,255,255,.06)!important;padding:22px 18px!important;text-align:center}
.pv-gf #field_5_11 .gform_drop_area:before{content:"";width:40px;height:40px;background:no-repeat center/28px url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'/%3E%3Cpath d='M14 2v6h6M12 18v-6M9 15l3-3 3 3'/%3E%3C/svg%3E")}
.pv-gf #field_5_11 .gform_drop_instructions{display:block!important;color:#fff!important;font-size:15px!important;margin:0!important}
.pv-gf .gform_button_select_files{visibility:visible!important;line-height:1.2!important;background:#fff!important;color:#14204a!important;border:0!important;border-radius:999px!important;min-height:44px;padding:0 24px!important;font-weight:600!important;font-size:15px!important;width:auto!important}
.pv-gf .gform_button_select_files:after{content:none!important}
.pv-gf #field_5_11 .gform_fileupload_rules{display:block!important;color:rgba(255,255,255,.75)!important;font-size:13px!important;margin-top:8px!important;text-align:center}
.pv-gf #field_5_11 .ginput_preview{display:flex!important;align-items:center;gap:8px;background:#e7f6ee;color:#14532d!important;border-radius:10px;padding:10px 12px;margin-top:10px;font-size:14px}
.pv-gf #field_5_11 .ginput_preview *{color:#14532d!important}
.pv-gf .gform_drop_instructions,.pv-gf .gform_fileupload_rules,.pv-gf .ginput_preview{color:rgba(255,255,255,.8)!important}
.pv-gf .gform_footer input[type=submit],.pv-gf .gform_footer button{background:#e2373f!important;color:#fff!important;border:0!important;border-radius:999px!important;min-height:50px;padding:0 30px!important;font-weight:600!important;font-size:15px!important;width:100%}
.pv-gf .gform_confirmation_message{background:#e7f6ee;color:#14532d;border-radius:12px;padding:14px 16px;font-size:15px;line-height:1.5}
.pv-gf .gfield_validation_message,.pv-gf .gform_validation_errors{background:#fdecee!important;color:#9b1c22!important;border-radius:10px}
@media(max-width:900px){.pv-vac{grid-template-columns:minmax(0,1fr)}}
@media(max-width:640px){.pv-gf{padding:24px 20px;border-radius:18px}.pv-vk{padding:22px 20px}}
</style>
<div class="pv">
	<section class="pv-hero">
		<span class="pv-kicker">Werken bij</span>
		<h1>Werk bij <em>The Health House</em></h1>
		<p>Sport en gezonde voeding zijn je passie en je helpt graag mensen verder? Kom ons team versterken in een van onze winkels.</p>
		<div class="pv-ctas"><a class="pv-btn pv-btn-red" href="#vacatures">Bekijk vacatures</a><a class="pv-btn pv-btn-ghost" href="#werkenbijformulier">Direct solliciteren</a></div>
	</section>

	<section class="pv-sec">
		<h2>Waarom bij ons</h2>
		<div class="pv-cards"><?php foreach ($kaarten as $k) : ?><div class="pv-card"><span class="pv-ico"><?php echo $svg($k[0]); ?></span><h3><?php echo esc_html($k[1]); ?></h3><p><?php echo esc_html($k[2]); ?></p></div><?php endforeach; ?></div>
	</section>

	<section class="pv-sec" id="vacatures">
		<h2>Vacatures</h2>
		<div class="pv-vac"><?php foreach (thh_wb2_vacatures() as $v) : ?>
			<div class="pv-vk">
				<span class="pv-plaats"><?php echo esc_html($v[0]); ?></span>
				<h3><?php echo esc_html($v[1]); ?></h3>
				<p><?php echo esc_html($v[2]); ?></p>
				<div class="pv-vvoet"><span class="pv-uren"><?php echo $svg('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'); ?><?php echo esc_html($v[3]); ?></span><a class="pv-btn pv-wb-sol" href="#werkenbijformulier" data-functie="<?php echo esc_attr($v[4]); ?>">Solliciteren</a></div>
			</div>
		<?php endforeach; ?></div>
	</section>

	<section class="pv-sec pv-formwrap" id="werkenbijformulier">
		<div class="pv-formintro">
			<span class="pv-label">Solliciteren</span>
			<h2>Zo werkt het</h2>
			<ul class="pv-steps">
				<li><b>1</b><span>Kies de functie, vertel kort waarom je bij ons past en upload je cv.</span></li>
				<li><b>2</b><span>We nemen binnen 2 werkdagen contact met je op.</span></li>
				<li><b>3</b><span>Klikt het? Dan nodigen we je uit voor een gesprek en een proefdag in de winkel.</span></li>
			</ul>
			<p>Staat jouw functie of winkel er niet tussen? Stuur dan een open sollicitatie via het formulier.</p>
		</div>
		<div class="pv-gf">
			<h3>Sollicitatieformulier</h3>
			<p class="pv-gsub">Velden met * zijn verplicht. We gebruiken je gegevens alleen voor je sollicitatie.</p>
			<?php echo function_exists('gravity_form') ? gravity_form(5, false, false, false, null, true, 0, false) : ''; ?>
		</div>
	</section>
</div>
<script>
(function(){function t(){var f=document.getElementById('field_5_11');if(!f)return;var l=f.querySelector('label');if(l&&!l.dataset.thh){l.dataset.thh=1;var r=l.querySelector('.gfield_required');l.firstChild&&(l.querySelector('.gform-field-label__text')||l).childNodes.length;var tx=l.querySelector('.gform-field-label__text');if(tx)tx.textContent='Je cv ';if(r)r.textContent='*';}var d=f.querySelector('.gform_drop_instructions');if(d)d.textContent='Sleep je cv hierheen of';var b=f.querySelector('.gform_button_select_files');if(b)b.textContent='Kies een bestand';var u=f.querySelector('.gform_fileupload_rules');if(u)u.textContent='Pdf, Word of foto, maximaal 12 MB';}t();document.addEventListener('gform_post_render',t);if(window.jQuery)jQuery(document).on('gform_post_render',t);})();
document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('.pv-wb-sol');if(!a)return;var s=document.querySelector('#gform_5 select[name="input_10"]');if(s){s.value=a.getAttribute('data-functie');s.dispatchEvent(new Event('change',{bubbles:true}));}});
</script>
	<?php
	return ob_get_clean();
});
