<?php
/**
 * THH Contact v2 — klantenservicepagina in de stijl van de nieuwe homepage (opzet zoals XXL).
 * Shortcode: [thh_contact_v2]. Testpagina /contact-v2/ (noindex zolang het een test is).
 */

add_filter('wp_robots', function ($r) { if (is_page('contact-v2')) { $r['noindex'] = true; $r['nofollow'] = true; } return $r; }, 99);
add_filter('wpseo_robots', function ($r) { return is_page('contact-v2') ? 'noindex, nofollow' : $r; }, 99);

add_shortcode('thh_contact_v2', function () {
	$u = function ($p) { return esc_url(strpos($p, 'http') === 0 ? $p : home_url($p)); };
	$svg = function ($d, $fill = false) { return '<svg viewBox="0 0 24 24" ' . ($fill ? 'fill="currentColor"' : 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"') . ' aria-hidden="true">' . $d . '</svg>'; };
	$wa = '<path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21c5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2zm0 18.15c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 4.54 0 8.24 3.7 8.24 8.24 0 4.55-3.7 8.24-8.24 8.24zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.12-.16.25-.64.81-.78.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.13-.15.17-.25.25-.42.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.18-.06-.1-.22-.16-.47-.28z"/>';
	$arr = '<svg class="ct-arr" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17L17 7M8 7h9v9"/></svg>';
	$chev = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>';

	$tegels = [
		['/faq/', 'Veelgestelde vragen', '<circle cx="12" cy="12" r="9"/><path d="M9.1 9a3 3 0 015.8 1c0 2-3 3-3 3M12 17h.01"/>'],
		['/faq/#bezorging', 'Verzending', '<path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>'],
		['/retourneren/', 'Retourneren', '<path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 010 10h-3"/>'],
		['/betaling/', 'Betaling', '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>'],
		['/my-account/', 'Mijn account', '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/>'],
		['/locaties/', 'Onze winkels', '<path d="M12 21s-7-5.5-7-11a7 7 0 0114 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>'],
		['/zakelijk-account/', 'Zakelijk bestellen', '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/>'],
		['/advies/', 'Gratis advies', '<path d="M21 12a8 8 0 01-11.6 7.1L4 21l1.9-5.4A8 8 0 1121 12z"/>'],
	];

	$faq = [
		['Wat kost verzenden en hoe snel heb ik mijn bestelling?', 'We verzenden met PostNL. In Nederland betaal je € 4,95 en vanaf € 60 is verzenden gratis. Naar België kost het € 12,10. Bestel je voor 22:00, dan is je bestelling binnen 2 werkdagen verzonden.'],
		['Waar blijft mijn pakket?', 'Zodra je pakket bij PostNL is aangemeld, krijg je een track-and-tracecode per mail. Kijk ook even in je spam. Is je pakket na drie werkdagen nog niet binnen? Stuur ons je bestelnummer via WhatsApp of mail, dan zoeken we het voor je uit.'],
		['Kan ik mijn bestelling nog wijzigen of annuleren?', 'Zolang je pakket nog niet is ingepakt, kunnen we het adres aanpassen, iets toevoegen of de bestelling annuleren. Stuur ons dan zo snel mogelijk een WhatsApp met je bestelnummer. Is het pakket al onderweg, dan kun je het weigeren of gebruikmaken van de bedenktijd.'],
		['Hoe kan ik iets retourneren of ruilen?', 'Je hebt 14 dagen na ontvangst om te retourneren, zolang het product ongeopend en verzegeld is. Meld je retour met je bestelnummer bij ons aan, dan krijg je alle informatie en het retouradres. De retourkosten zijn voor jezelf, tenzij wij iets fout hebben gedaan.'],
		['Welke betaalmethoden kan ik gebruiken?', 'Je kunt betalen met iDEAL, creditcard, Klarna (achteraf betalen), in3 (in drie termijnen) en Billie (zakelijk op rekening). In de checkout zie je welke methodes voor jouw bestelling beschikbaar zijn.'],
		['Hoe krijg ik een factuur op mijn bedrijfsnaam?', 'De factuur zit als bijlage bij je orderbevestiging. Wil je hem op je bedrijfsnaam? Vul je bedrijfsgegevens en btw-nummer in bij het bestellen of stuur ze ons na, dan passen we de factuur aan.'],
		['Kan ik iets reserveren of ophalen in de winkel?', 'De voorraad in de webshop en de winkels is niet altijd gelijk. Wil je zeker weten dat iets klaarligt? Stuur ons even een WhatsApp, dan leggen we het voor je apart.'],
		['Kan ik advies krijgen over welk product bij mij past?', 'Zeker. Stuur ons een WhatsApp, vul het adviesformulier in of loop binnen in een van onze winkels. Je kunt ook de keuzehulp op de homepage proberen.'],
	];

	$schema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) { return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]]; }, $faq)];
	$form = function_exists('gravity_form') ? do_shortcode('[gravityform id="1" title="false" description="false" ajax="true"]') : '';

	ob_start(); ?>
<div class="ct">
	<section class="ct-top">
		<div class="ct-intro">
			<h1>Contact</h1>
			<p>Een vraag over je bestelling, een product of je doelen? We helpen je graag. Via WhatsApp heb je meestal het snelst antwoord.</p>
			<ul class="ct-list">
				<li><a href="https://wa.me/31611789629" target="_blank" rel="noopener nofollow"><span class="ct-ic ct-ic-wa"><?php echo $svg($wa, true); ?></span><strong>WhatsApp</strong><span>06 11 78 96 29</span><?php echo $arr; ?></a></li>
				<li><a href="mailto:klantenservice@thehealthhouse.nl"><span class="ct-ic"><?php echo $svg('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>'); ?></span><strong>E-mail</strong><span>klantenservice@thehealthhouse.nl</span><?php echo $arr; ?></a></li>
				<li><a href="<?php echo $u('/locaties/'); ?>"><span class="ct-ic"><?php echo $svg('<path d="M12 21s-7-5.5-7-11a7 7 0 0114 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>'); ?></span><strong>In de winkel</strong><span>Bekijk onze winkels</span><?php echo $arr; ?></a></li>
			</ul>
		</div>
		<figure class="ct-foto"><img src="https://thehealthhouse.nl/wp-content/uploads/2026/11/thh-over-advies-3.webp" alt="Medewerker van The Health House helpt een klant" loading="eager"></figure>
	</section>

	<section class="ct-sec">
		<h2>Of kunnen we je hiermee direct helpen?</h2>
		<div class="ct-tiles">
			<?php foreach ($tegels as $t) : ?><a href="<?php echo $u($t[0]); ?>"><span class="ct-tic"><?php echo $svg($t[2]); ?></span><strong><?php echo esc_html($t[1]); ?></strong><?php echo $chev; ?></a><?php endforeach; ?>
		</div>
	</section>

	<section class="ct-sec ct-faqwrap">
		<div class="ct-faqhead"><h2>Veelgestelde vragen</h2><a href="<?php echo $u('/faq/'); ?>">Alle vragen →</a></div>
		<div class="ct-faq">
			<?php foreach ($faq as $i => $f) : ?><details><summary><?php echo esc_html($f[0]); ?><span class="ct-plus" aria-hidden="true"></span></summary><p><?php echo esc_html($f[1]); ?></p></details><?php endforeach; ?>
		</div>
	</section>

	<section class="ct-sec ct-split">
		<div class="ct-formcard">
			<h2>Stuur ons een bericht</h2>
			<p>Vul het formulier in en we nemen zo snel mogelijk contact met je op. Heb je een vraag over een bestelling? Zet je bestelnummer er dan bij.</p>
			<?php echo $form; ?>
		</div>
		<div class="ct-side">
			<div class="ct-box"><h3>Adres</h3><p>The Health House<br>Overhoeksplein 1<br>1031 KS Amsterdam</p><small>Dit is geen winkel. Voor advies of ophalen kun je naar een van onze <a href="<?php echo $u('/locaties/'); ?>">winkels</a>.</small></div>
			<div class="ct-box"><h3>Zakelijk</h3><p>Bestel je voor je sportschool, winkel of bedrijf? Vraag een zakelijk account aan.</p><a class="ct-link" href="<?php echo $u('/zakelijk-account/'); ?>">Zakelijk account aanvragen →</a></div>
			<div class="ct-box"><h3>Franchise</h3><p>Zelf een The Health House beginnen?</p><a class="ct-link" href="<?php echo $u('/franchise/'); ?>">Meer over franchise →</a></div>
		</div>
	</section>
</div>
<script type="application/ld+json"><?php echo wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
<style>
.ct{--nv:#14204a;--rd:#e2373f;--bg:#f5f6fa;--tx:#1b1f2e;--mut:#5a6072;max-width:1240px;margin:0 auto;padding:24px 20px 56px;color:var(--tx);font-family:inherit;overflow-x:clip}
.ct a{text-decoration:none}.ct h1,.ct h2,.ct h3{margin:0;color:var(--nv);line-height:1.15;letter-spacing:-.01em;text-transform:none}
.ct-top{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:48px;align-items:center}
.ct-intro{display:flex;flex-direction:column;gap:16px}
.ct .ct-intro h1{font-size:clamp(36px,4.4vw,56px);font-weight:800}
.ct-intro>p{margin:0;font-size:16px;line-height:1.6;color:var(--mut);max-width:520px}
.ct-list{list-style:none;margin:8px 0 0;padding:6px 20px;background:var(--bg);border-radius:18px}
.ct-list li+li{border-top:1px solid #e1e4ee}
.ct .ct-list a{display:grid;grid-template-columns:36px 120px minmax(0,1fr) 18px;align-items:center;gap:12px;padding:16px 0;color:var(--nv)}
.ct-list strong{font-size:15px;font-weight:600}.ct-list span:not(.ct-ic){font-size:15px;overflow-wrap:anywhere}
.ct-arr{width:18px;height:18px;opacity:.7}.ct .ct-list a:hover .ct-arr{opacity:1}
.ct-ic{width:36px;height:36px;border-radius:50%;background:var(--nv);color:#fff;display:grid;place-items:center}.ct-ic svg{width:18px;height:18px}.ct-ic-wa{background:#25D366}.ct-ic-wa svg{width:20px;height:20px}
.ct-foto{margin:0;border-radius:20px;overflow:hidden;aspect-ratio:4/3;background:var(--bg)}.ct-foto img{width:100%;height:100%;object-fit:cover;display:block}
.ct-sec{margin-top:56px}.ct .ct-sec h2{font-size:24px;font-weight:700;margin-bottom:18px}
.ct-tiles{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.ct .ct-tiles a{display:flex;align-items:center;gap:12px;border:1px solid #e1e4ee;border-radius:14px;padding:16px 16px;color:var(--nv);background:#fff;transition:border-color .15s,box-shadow .15s}
.ct .ct-tiles a:hover{border-color:var(--nv);box-shadow:0 6px 18px rgba(20,32,74,.08);color:var(--nv)}
.ct-tic{width:40px;height:40px;border-radius:10px;background:var(--bg);display:grid;place-items:center;color:var(--rd);flex:none}.ct-tic svg{width:22px;height:22px}
.ct-tiles strong{flex:1;font-size:15px;font-weight:600}.ct-tiles a>svg{width:18px;height:18px;flex:none}
.ct-faqhead{display:flex;justify-content:space-between;align-items:baseline;gap:12px}.ct .ct-faqhead a{color:var(--nv);font-weight:500;text-decoration:underline;text-underline-offset:3px;white-space:nowrap}
.ct-faq{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:10px 16px;align-items:start}.ct .ct-formcard select{text-transform:none!important;letter-spacing:0!important}
.ct-faq details{background:var(--bg);border-radius:14px;padding:0 20px}
.ct-faq summary{list-style:none;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:16px;padding:18px 0;font-weight:600;font-size:15px;color:var(--nv)}.ct-faq summary::-webkit-details-marker{display:none}
.ct-plus{position:relative;width:14px;height:14px;flex:none}.ct-plus:before,.ct-plus:after{content:"";position:absolute;left:0;top:6px;width:14px;height:2px;background:var(--nv);border-radius:2px;transition:transform .2s}.ct-plus:after{transform:rotate(90deg)}details[open] .ct-plus:after{transform:rotate(0)}
.ct-faq p{margin:0 0 18px;font-size:14.5px;line-height:1.65;color:var(--mut)}
.ct-split{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:24px;align-items:start}
.ct-formcard{background:var(--nv);border-radius:24px;padding:36px 40px;color:#fff}
.ct .ct-formcard h2{color:#fff;margin-bottom:8px}.ct-formcard>p{margin:0 0 20px;color:rgba(255,255,255,.8);font-size:15px;line-height:1.6}
.ct-side{display:flex;flex-direction:column;gap:12px}
.ct-box{border:1px solid #e1e4ee;border-radius:16px;padding:20px 22px;display:flex;flex-direction:column;gap:8px}
.ct .ct-box h3{font-size:17px;font-weight:700}.ct-box p{margin:0;font-size:14.5px;line-height:1.6;color:var(--tx)}.ct-box small{font-size:13px;color:var(--mut);line-height:1.5}.ct .ct-box small a{color:var(--nv);text-decoration:underline}
.ct .ct-link{color:var(--nv);font-weight:600;font-size:14.5px}
/* Gravity Forms in de donkere kaart */
.ct-formcard .gform_wrapper{margin:0!important}
.ct-formcard .gform_wrapper .gfield_label,.ct-formcard .gform_wrapper legend{color:#fff!important;font-weight:500!important;font-size:14px!important;text-transform:none!important;letter-spacing:0!important}
.ct-formcard .gform_wrapper input:not([type=submit]):not([type=checkbox]):not([type=radio]),.ct-formcard .gform_wrapper textarea,.ct-formcard .gform_wrapper select{width:100%!important;background:#fff!important;border:1px solid transparent!important;border-radius:12px!important;padding:12px 14px!important;font-size:15px!important;color:var(--tx)!important;box-shadow:none!important;font-family:inherit!important}
.ct-formcard .gform_wrapper textarea{min-height:130px!important}
.ct-formcard .gform_wrapper input:focus,.ct-formcard .gform_wrapper textarea:focus{border-color:var(--rd)!important;outline:none!important}
.ct-formcard .gform_wrapper .gfield_description,.ct-formcard .gform_wrapper .gfield_required{color:rgba(255,255,255,.75)!important}
.ct-formcard .gform_wrapper .gform_footer input[type=submit],.ct-formcard .gform_wrapper .gform_footer button{background:var(--rd)!important;color:#fff!important;border:0!important;border-radius:999px!important;padding:14px 30px!important;font-weight:600!important;font-size:15px!important;text-transform:none!important;letter-spacing:0!important;cursor:pointer;width:auto!important}
.ct-formcard .gform_wrapper .gform_footer input[type=submit]:hover{background:#c92c33!important}
.ct-formcard .gform_confirmation_message{color:#fff;font-size:16px}
@media(max-width:900px){.ct-top{grid-template-columns:minmax(0,1fr);gap:24px}.ct-foto{order:-1;aspect-ratio:16/9}.ct-tiles{grid-template-columns:repeat(2,minmax(0,1fr))}.ct-faq{grid-template-columns:minmax(0,1fr)}.ct-split{grid-template-columns:minmax(0,1fr)}}
@media(max-width:640px){.ct{padding:12px 14px 36px}.ct .ct-list a{grid-template-columns:36px minmax(0,1fr) 18px;row-gap:2px}.ct-list a>span:nth-of-type(2){grid-column:2}.ct-list a>.ct-arr{grid-column:3;grid-row:1}.ct-list{padding:4px 16px}.ct-sec{margin-top:40px}.ct-tiles{gap:8px}.ct .ct-tiles a{padding:12px;gap:10px}.ct-tic{width:34px;height:34px}.ct-tiles strong{font-size:14px}.ct-tiles a>svg{display:none}.ct-formcard{padding:26px 20px;border-radius:18px}.ct-faq details{padding:0 16px}}
</style>
<?php
	return ob_get_clean();
});
