<?php
/**
 * THH Over ons v2 — "Over The Health House" in de stijl van de nieuwe homepage.
 * Shortcode: [thh_over_v2]. Testpagina /over-the-health-house-v2/ (noindex zolang het een test is).
 */

add_filter('wp_robots', function ($r) { if (is_page('over-the-health-house-v2')) { $r['noindex'] = true; $r['nofollow'] = true; } return $r; }, 99);
add_filter('wpseo_robots', function ($r) { return is_page('over-the-health-house-v2') ? 'noindex, nofollow' : $r; }, 99);

add_shortcode('thh_over_v2', function ($a) {
	$a = shortcode_atts([
		'img' => 'https://thehealthhouse.nl/wp-content/uploads/2026/11/thh-over-winkel-advies.webp',
	], $a, 'thh_over_v2');
	$u = function ($p) { return esc_url(strpos($p, 'http') === 0 ? $p : home_url($p)); };
	$svg = function ($d) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>'; };

	$waarden = [
		['<path d="M20.8 4.6a5.5 5.5 0 00-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 00-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 000-7.8z"/>', 'Gezondheid voorop', 'We kijken eerst naar wat jij nodig hebt. Pas daarna naar wat we kunnen verkopen. Daarom is ons advies altijd afgestemd op jouw doel.'],
		['<circle cx="12" cy="9" r="6"/><path d="M8.5 14.2L7 22l5-3 5 3-1.5-7.8"/>', 'Kwaliteit boven alles', 'Elk product in ons assortiment kiezen we zelf uit. Wat we niet zelf zouden gebruiken, komt niet in de winkel.'],
		['<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>', 'De beste merken', 'Naast ons eigen merk verkopen we merken uit Europa en de VS. Alleen wat na een strenge selectie overblijft, krijgt een plek.'],
	];

	$winkels = [
		['Amsterdam', 'Advies en je bestelling ophalen'],
		['Hoofddorp', 'Advies en je bestelling ophalen'],
		['Heerhugowaard', 'Advies en je bestelling ophalen'],
		['Uithoorn', 'Advies en je bestelling ophalen'],
		['Halfweg', 'Outletwinkel, binnenkort open'],
	];

	ob_start(); ?>
<div class="ov">
	<section class="ov-hero">
		<div class="ov-herotxt">
			<span class="ov-kicker">Sinds 2020</span>
			<h1>Over The <em>Health House</em></h1>
			<p>We zijn begonnen met één idee: een gezond leven moet voor iedereen haalbaar zijn. Met eerlijk advies, een winkel waar je je thuis voelt en verstand van supplementen helpen we iedereen die binnenloopt.</p>
			<div class="ov-ctas"><a class="ov-btn ov-btn-red" href="<?php echo $u('/locaties/'); ?>">Bekijk onze winkels</a><a class="ov-btn ov-btn-ghost" href="<?php echo $u('/advies/'); ?>">Vraag gratis advies</a></div>
		</div>
		<div class="ov-herovis"><img src="<?php echo esc_url($a['img']); ?>" alt="Medewerker van The Health House helpt een klant in de winkel" loading="eager" fetchpriority="high"></div>
	</section>

	<section class="ov-story">
		<div class="ov-storyhead"><span class="ov-label">Ons verhaal</span><h2>Van sportvoeding naar alles voor een gezondere leefstijl</h2></div>
		<div class="ov-storytxt">
			<p>The Health House is ontstaan uit een echte passie voor sport en sportvoeding. We wilden onze klanten, en onszelf, de beste producten kunnen geven. Die producten zijn inmiddels een begrip onder fanatieke en recreatieve sporters.</p>
			<p>Ons assortiment is de afgelopen jaren flink gegroeid. We verkopen niet meer alleen sportproducten, maar ook producten voor iedereen die gezonder wil leven. Denk aan producten voor je weerstand, je gewrichten en je organen, en producten die helpen met focus en energie.</p>
			<p>Naast ons eigen merk hebben we een groeiend aanbod van andere merken. Die selecteren we streng: alleen de beste supplementen komen in onze winkels en webshop.</p>
		</div>
	</section>

	<section class="ov-gal" aria-label="Foto's uit onze winkels">
		<figure class="ov-g1"><img src="https://thehealthhouse.nl/wp-content/uploads/2026/11/thh-over-winkel-klant.webp" alt="Klant in een winkel van The Health House" loading="lazy"></figure>
		<figure class="ov-g2"><img src="https://thehealthhouse.nl/wp-content/uploads/2026/11/thh-over-kiosk-vier-meren.webp" alt="The Health House kiosk in winkelcentrum Vier Meren" loading="lazy"></figure>
		<figure class="ov-g3"><img src="https://thehealthhouse.nl/wp-content/uploads/2026/11/thh-over-advies-2.webp" alt="Klant krijgt advies over supplementen" loading="lazy"></figure>
		<figure class="ov-g4"><img src="https://thehealthhouse.nl/wp-content/uploads/2026/11/thh-over-winkel-binnen.webp" alt="Binnenkant van een The Health House winkel" loading="lazy"></figure>
		<figure class="ov-g5"><img src="https://thehealthhouse.nl/wp-content/uploads/2026/11/thh-over-schap.webp" alt="Klant kiest producten uit het schap" loading="lazy"></figure>
	</section>

	<section class="ov-sec">
		<div class="ov-sechead"><h2>Waar we voor staan</h2></div>
		<div class="ov-values">
			<?php foreach ($waarden as $w) : ?><div class="ov-value"><span class="ov-ico"><?php echo $svg($w[0]); ?></span><h3><?php echo esc_html($w[1]); ?></h3><p><?php echo esc_html($w[2]); ?></p></div><?php endforeach; ?>
		</div>
	</section>

	<section class="ov-motto" aria-label="Ons motto">
		<span>Overcome</span><i></i><span>Achieve</span><i></i><span>Conquer</span>
	</section>

	<section class="ov-band">
		<div class="ov-bandtxt"><span class="ov-kicker">Onze winkels</span><h2>Kom gerust <em>langs</em></h2><p>In onze winkels krijg je persoonlijk advies van mensen die zelf trainen. Je kunt er ook je online bestelling ophalen. Liever eerst een vraag stellen? Stuur ons een WhatsApp.</p><div class="ov-ctas"><a class="ov-btn ov-btn-red" href="<?php echo $u('/locaties/'); ?>">Vind een winkel</a><a class="ov-btn ov-btn-ghost" href="https://wa.me/31611789629" target="_blank" rel="noopener nofollow">WhatsApp ons</a></div></div>
		<div class="ov-stores">
			<?php foreach ($winkels as $w) : ?><a href="<?php echo $u('/locaties/'); ?>"><span class="ov-pin"><?php echo $svg('<path d="M12 21s-7-5.5-7-11a7 7 0 0114 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>'); ?></span><span><strong><?php echo esc_html($w[0]); ?></strong><small><?php echo esc_html($w[1]); ?></small></span><span class="ov-arr">→</span></a><?php endforeach; ?>
		</div>
	</section>

	<section class="ov-sec">
		<div class="ov-links">
			<a href="<?php echo $u('/loyalty/'); ?>" data-thh-loyalty><span class="ov-ico"><?php echo $svg('<path d="M12 2l2.9 6.3 6.9.7-5.2 4.6 1.6 6.8L12 16.9 5.8 20.4l1.6-6.8L2.2 9l6.9-.7z"/>'); ?></span><strong>Loyalty club</strong><small>Spaar punten bij elke bestelling, ook in de winkel</small></a>
			<a href="<?php echo $u('/werken-bij/'); ?>"><span class="ov-ico"><?php echo $svg('<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/>'); ?></span><strong>Werken bij</strong><small>Bekijk onze vacatures</small></a>
			<a href="<?php echo $u('/franchise/'); ?>"><span class="ov-ico"><?php echo $svg('<path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6"/>'); ?></span><strong>Franchise</strong><small>Begin je eigen The Health House</small></a>
			<a href="<?php echo $u('/merken/'); ?>"><span class="ov-ico"><?php echo $svg('<path d="M20.6 13.4l-7.2 7.2a2 2 0 01-2.8 0L3 13V3h10l7.6 7.6a2 2 0 010 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>'); ?></span><strong>Onze merken</strong><small>Alle merken die we verkopen</small></a>
		</div>
	</section>
</div>
<style>
.ov{--nv:#14204a;--rd:#e2373f;--bg:#f5f6fa;--tx:#1b1f2e;--mut:#5a6072;max-width:1240px;margin:0 auto;padding:16px 20px 48px;color:var(--tx);font-family:inherit;overflow-x:clip}
.ov a{text-decoration:none}.ov h1,.ov h2,.ov h3{margin:0;color:var(--nv);line-height:1.15;letter-spacing:-.01em;text-transform:none}
.ov-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:15px 26px;font-weight:600;font-size:15px;min-height:48px}
.ov .ov-btn-red{background:var(--rd);color:#fff}.ov .ov-btn-red:hover{background:#c92c33;color:#fff}
.ov .ov-btn-ghost{border:2px solid rgba(255,255,255,.7);color:#fff}.ov .ov-btn-ghost:hover{background:rgba(255,255,255,.12);color:#fff}
.ov-ctas{display:flex;gap:12px;flex-wrap:wrap}
.ov-kicker{display:inline-block;align-self:flex-start;background:rgba(255,255,255,.14);color:#fff;font-weight:600;font-size:12px;letter-spacing:.12em;text-transform:uppercase;padding:8px 14px;border-radius:6px}
.ov-hero{background:var(--nv);color:#fff;border-radius:24px;display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:24px;min-height:460px;padding:48px 56px;position:relative;overflow:hidden}
.ov-hero:before{z-index:0;content:"";position:absolute;right:-10%;top:-20%;width:60%;height:140%;background:linear-gradient(120deg,transparent 40%,rgba(255,255,255,.06) 40%,rgba(255,255,255,.06) 55%,transparent 55%);pointer-events:none}
.ov-herotxt{display:flex;flex-direction:column;justify-content:center;gap:18px;position:relative;z-index:3}
.ov .ov-hero h1{color:#fff;font-size:clamp(38px,5vw,68px);font-weight:900;text-transform:uppercase;line-height:1.02}.ov-hero h1 em{display:block;font-style:normal;color:transparent;-webkit-text-stroke:2px #fff}
.ov-hero p{margin:0;font-size:17px;line-height:1.55;max-width:520px;color:rgba(255,255,255,.85)}
.ov-herovis{position:relative;z-index:1;margin:-48px -56px -48px 0;min-height:100%}.ov-herovis img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:50% 35%}
.ov-gal{margin-top:40px;display:grid;grid-template-columns:1fr 1.4fr 1fr;grid-template-rows:220px 220px;gap:12px}.ov-gal figure{margin:0;border-radius:18px;overflow:hidden;background:var(--bg)}.ov-gal img{width:100%;height:100%;object-fit:cover;display:block}
.ov-g1{grid-row:1/3}.ov-g5{grid-row:1/3;grid-column:3}.ov-g2{grid-column:2}.ov-g3{display:none}.ov-g4{grid-column:2}
.ov-story{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:48px;margin-top:64px;align-items:start}
.ov-label{display:block;font-size:12px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--rd);margin-bottom:12px}
.ov .ov-storyhead h2{font-size:clamp(26px,3vw,36px);font-weight:700}
.ov-storytxt p{margin:0 0 16px;font-size:16px;line-height:1.7;color:var(--tx)}.ov-storytxt p:last-child{margin:0}
.ov-sec{margin-top:56px}.ov-sechead{margin-bottom:20px}.ov .ov-sechead h2{font-size:24px;font-weight:700}
.ov-values{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
.ov-value{background:var(--bg);border-radius:18px;padding:28px;display:flex;flex-direction:column;gap:10px}
.ov .ov-value h3{font-size:18px;font-weight:600}.ov-value p{margin:0;font-size:15px;line-height:1.6;color:var(--mut)}
.ov-ico{width:44px;height:44px;border-radius:12px;background:#fff;display:grid;place-items:center;color:var(--rd);flex:none}.ov-ico svg{width:22px;height:22px}
.ov-motto{margin-top:56px;display:flex;align-items:center;justify-content:center;gap:24px;flex-wrap:wrap;padding:28px 20px;border-top:1px solid #e6e8f0;border-bottom:1px solid #e6e8f0}
.ov-motto span{font-size:clamp(24px,4vw,48px);font-weight:900;text-transform:uppercase;color:var(--nv);letter-spacing:-.01em}.ov-motto span:nth-of-type(2){color:transparent;-webkit-text-stroke:1.5px var(--nv)}
.ov-motto i{width:10px;height:10px;border-radius:50%;background:var(--rd)}
.ov-band{margin-top:56px;background:var(--nv);color:#fff;border-radius:24px;padding:48px 56px;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:40px;align-items:center}
.ov-bandtxt{display:flex;flex-direction:column;gap:16px}.ov .ov-band h2{color:#fff;font-size:36px;font-weight:700}.ov-band h2 em{font-style:normal;color:transparent;-webkit-text-stroke:1.5px #fff}
.ov-band p{margin:0;font-size:16px;line-height:1.6;color:rgba(255,255,255,.82);max-width:520px}
.ov-stores{display:flex;flex-direction:column;gap:10px}
.ov .ov-stores a{color:#fff;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);border-radius:14px;padding:14px 18px;display:flex;align-items:center;gap:14px}
.ov .ov-stores a:hover{background:rgba(255,255,255,.14);color:#fff}
.ov-pin{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.12);display:grid;place-items:center;flex:none}.ov-pin svg{width:18px;height:18px}
.ov-stores a>span:nth-child(2){display:flex;flex-direction:column;gap:2px;flex:1;min-width:0}.ov-stores strong{font-size:16px;font-weight:600}.ov-stores small{font-size:13px;color:rgba(255,255,255,.72)}
.ov-arr{opacity:.7}
.ov-links{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.ov .ov-links a{background:var(--bg);border-radius:16px;padding:20px;display:flex;flex-direction:column;gap:6px;color:var(--nv);transition:transform .15s}
.ov .ov-links a:hover{transform:translateY(-2px);color:var(--nv)}.ov-links .ov-ico{margin-bottom:6px}
.ov-links strong{font-size:16px;font-weight:600}.ov-links small{font-size:13px;color:var(--mut);line-height:1.45}
@media(max-width:900px){.ov-hero{grid-template-columns:minmax(0,1fr);padding:32px 28px}.ov-herovis{margin:16px -28px -32px;height:300px}.ov-gal{grid-template-columns:1fr 1fr;grid-template-rows:200px 200px 200px}.ov-g1{grid-row:1/3}.ov-g5{grid-row:auto;grid-column:auto}.ov-g2{grid-column:2}.ov-g4{grid-column:2}.ov-g3{display:block;grid-column:1}.ov-story{grid-template-columns:minmax(0,1fr);gap:16px;margin-top:40px}.ov-values{grid-template-columns:minmax(0,1fr)}.ov-band{grid-template-columns:minmax(0,1fr);padding:32px 28px;gap:24px}.ov-links{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){.ov{padding:12px 14px 32px}.ov-hero{border-radius:18px;padding:24px 20px;min-height:0;gap:12px}.ov .ov-hero h1{font-size:34px}.ov-hero p{font-size:15px}.ov-ctas{flex-direction:column;align-items:stretch}.ov-btn{min-height:44px;padding:10px 16px;font-size:14px}.ov-herovis{order:-1;margin:-24px -20px 4px;height:230px}.ov-gal{grid-template-rows:150px 150px 150px;gap:8px}.ov-gal figure{border-radius:12px}.ov-sec,.ov-motto,.ov-band{margin-top:40px}.ov-value{padding:22px}.ov-motto{gap:12px}.ov-band{border-radius:18px;padding:26px 20px}.ov .ov-band h2{font-size:28px}.ov-links{grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:10px}.ov .ov-links a{padding:16px}}
</style>
<?php
	return ob_get_clean();
});
