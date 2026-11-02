<?php
/**
 * THH pagina's v2 — Franchise (41993) en Terugroepacties (41090) in de stijl van de nieuwe homepage.
 * De Elementor-inhoud van deze pagina's wordt vervangen door de shortcodes hieronder.
 * Het franchiseformulier post naar het bestaande Elementor-formulier (zelfde mail naar klantenservice@).
 */

function thh_pv2_ids() { return [41993 => 'thh_franchise_v2', 41090 => 'thh_terugroep_v2', 3430 => 'thh_locaties_v2', 5913 => 'thh_over_v2', 5996 => 'thh_contact_v2', 13929 => 'thh_advies_v2']; }

add_filter('elementor/frontend/the_content', function ($c) {
	if (!is_page()) return $c;
	$ids = thh_pv2_ids(); $id = get_queried_object_id();
	return isset($ids[$id]) && in_the_loop() ? do_shortcode('[' . $ids[$id] . ']') : $c;
}, 999);
add_filter('the_content', function ($c) {
	if (!is_page()) return $c;
	$ids = thh_pv2_ids(); $id = get_queried_object_id();
	return (isset($ids[$id]) && in_the_loop() && !did_action('elementor/frontend/before_render')) ? do_shortcode('[' . $ids[$id] . ']') : $c;
}, 999);

add_action('wp_head', function () { if (is_page([3430, 5913, 5996, 13929])) echo '<style>body.page .site-main>.page-header{display:none!important}</style>'; });

function thh_pv2_css() {
	static $done = false; if ($done) return ''; $done = true;
	return '<style>
body.page-id-41993 .site-main>.page-header,body.page-id-41090 .site-main>.page-header,body.page-id-3430 .site-main>.page-header{display:none!important}
.pv{--nv:#14204a;--rd:#e2373f;--bg:#f5f6fa;--tx:#1b1f2e;--mut:#5a6072;max-width:1240px;margin:0 auto;padding:16px 20px 56px;color:var(--tx);font-family:inherit;overflow-x:clip}
.pv *{box-sizing:border-box}.pv a,.pv a:hover{text-decoration:none!important}.pv h1,.pv h2,.pv h3{margin:0;color:var(--nv);line-height:1.15;letter-spacing:-.01em;text-transform:none}
.pv-hero{background:var(--nv);color:#fff;border-radius:24px;padding:52px 56px;position:relative;overflow:hidden}
.pv-hero:before{content:"";position:absolute;right:-10%;top:-20%;width:60%;height:140%;background:linear-gradient(120deg,transparent 40%,rgba(255,255,255,.06) 40%,rgba(255,255,255,.06) 55%,transparent 55%);pointer-events:none}
.pv-hero>*{position:relative;z-index:1}
.pv-kicker{display:inline-block;background:rgba(255,255,255,.14);color:#fff;font-weight:600;font-size:12px;letter-spacing:.12em;text-transform:uppercase;padding:8px 14px;border-radius:6px;margin-bottom:16px}
.pv .pv-hero h1{color:#fff;font-size:clamp(36px,5vw,64px);font-weight:900;text-transform:uppercase;line-height:1.02}.pv-hero h1 em{display:block;font-style:normal;color:transparent;-webkit-text-stroke:2px #fff}
.pv .pv-hero p{margin:18px 0 0;font-size:17px;line-height:1.55;color:rgba(255,255,255,.85)!important;max-width:620px}
.pv-ctas{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}
.pv .pv-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:0 26px;min-height:48px;font-weight:600;font-size:15px;border:0;cursor:pointer;font-family:inherit;text-transform:none;letter-spacing:0}
.pv .pv-btn-red{background:var(--rd);color:#fff!important}.pv .pv-btn-red:hover{background:#c92c33;color:#fff}
.pv .pv-btn-ghost{background:transparent;border:2px solid rgba(255,255,255,.7);color:#fff!important}.pv .pv-btn-ghost:hover{background:rgba(255,255,255,.12);color:#fff}
.pv-sec{margin-top:56px}.pv .pv-sec>h2{font-size:26px;font-weight:700;margin-bottom:18px}
.pv-two{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:48px;align-items:start}
.pv-label{display:block;font-size:12px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--rd);margin-bottom:12px}
.pv .pv-two h2{font-size:clamp(26px,3vw,36px);font-weight:700}
.pv-txt p{margin:0 0 16px;font-size:16px;line-height:1.7}.pv-txt p:last-child{margin:0}
.pv-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
.pv-card{background:var(--bg);border-radius:18px;padding:26px;display:flex;flex-direction:column;gap:10px}
.pv .pv-card h3{font-size:18px;font-weight:600}.pv-card p{margin:0;font-size:15px;line-height:1.6;color:var(--mut)}
.pv-ico{width:44px;height:44px;border-radius:12px;background:#fff;display:grid;place-items:center;color:var(--rd)}.pv-ico svg{width:22px;height:22px}
.pv-formwrap{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.2fr);gap:24px;align-items:stretch}
.pv-formintro{background:var(--bg);border-radius:24px;padding:36px 40px;display:flex;flex-direction:column;gap:14px}
.pv .pv-formintro h2{font-size:28px;font-weight:700}.pv-formintro p{margin:0;font-size:15.5px;line-height:1.65;color:var(--tx)}
.pv .pv-steps{list-style:none;margin:6px 0 0!important;padding:0!important;display:flex;flex-direction:column;gap:10px}
.pv-steps li{display:flex;gap:12px;align-items:flex-start;font-size:15px;line-height:1.5}.pv-steps b{flex:none;width:26px;height:26px;border-radius:50%;background:var(--nv);color:#fff;display:grid;place-items:center;font-size:13px}
.pv-form{background:var(--nv);border-radius:24px;padding:36px 40px;color:#fff;display:flex;flex-direction:column;gap:14px}
.pv .pv-form h3{color:#fff!important;font-size:22px;font-weight:700}.pv .pv-form label{color:#fff!important}
.pv-grid2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px}
.pv-form label{display:flex;flex-direction:column;gap:6px;font-size:14px;font-weight:500;color:#fff}
.pv .pv-form input,.pv .pv-form textarea{width:100%;background:#fff;border:1px solid transparent;border-radius:12px;padding:12px 14px;font-size:15px;color:var(--tx);font-family:inherit;box-shadow:none;outline:none}
.pv .pv-form textarea{min-height:130px;resize:vertical}.pv .pv-form input:focus,.pv .pv-form textarea:focus{border-color:var(--rd)}
.pv-hp{position:absolute!important;left:-9999px!important;opacity:0}
.pv-msg{font-size:15px;line-height:1.5;border-radius:12px;padding:12px 14px;display:none}.pv-msg.ok{display:block;background:#e7f6ee;color:#14532d}.pv-msg.err{display:block;background:#fdecee;color:#9b1c22}
.pv-recall{border:1px solid #e1e4ee;border-radius:20px;padding:28px 32px;display:grid;grid-template-columns:minmax(0,1fr) 260px;gap:32px;background:#fff}
.pv-recall+.pv-recall{margin-top:16px}
.pv-rtag{display:inline-flex;gap:8px;align-items:center;margin-bottom:10px}.pv-rtag span{font-size:12px;font-weight:600;padding:5px 10px;border-radius:999px;background:var(--bg);color:var(--nv)}.pv-rtag span.nu{background:#fdecee;color:var(--rd)}
.pv .pv-recall h3{font-size:22px;font-weight:700;margin-bottom:10px}
.pv-recall p{margin:0 0 12px;font-size:15.5px;line-height:1.65}.pv-recall p:last-child{margin:0}
.pv-recall ul{margin:0 0 12px;padding-left:20px}.pv-recall li{font-size:15px;line-height:1.6}
.pv-ract{background:var(--bg);border-radius:16px;padding:20px;display:flex;flex-direction:column;gap:10px;align-self:start}
.pv-ract strong{color:var(--nv);font-size:15px}.pv-ract p{font-size:14px;color:var(--mut);margin:0}
.pv-claim{display:flex;flex-direction:column;gap:8px}.pv .pv-claim input{width:100%;background:#fff;border:1px solid #e1e4ee;border-radius:10px;padding:10px 12px;font-size:14px;font-family:inherit;color:var(--tx);box-shadow:none;outline:none}.pv .pv-claim input:focus{border-color:var(--nv)}.pv .pv-claim .pv-hp{position:absolute!important;left:-9999px!important}
.pv .pv-ract .pv-btn{min-height:44px;font-size:14px;padding:0 18px;width:100%}
.pv .pv-ract .pv-btn-nv{background:var(--nv);color:#fff!important}.pv .pv-ract .pv-btn-nv:hover{background:#0d1538;color:#fff}
.pv .pv-ract .pv-btn-wa{background:#25D366;color:#fff!important}.pv .pv-ract .pv-btn-wa:hover{background:#1fb457;color:#fff}
@media(max-width:900px){.pv-hero{padding:32px 28px}.pv-two,.pv-formwrap,.pv-recall{grid-template-columns:minmax(0,1fr);gap:20px}.pv-cards{grid-template-columns:minmax(0,1fr)}}
@media(max-width:640px){.pv{padding:12px 14px 40px}.pv-hero{border-radius:18px;padding:24px 20px}.pv .pv-hero h1{font-size:32px}.pv-hero h1 em{-webkit-text-stroke:1.2px #fff}.pv-hero p{font-size:15px}.pv-sec{margin-top:40px}.pv-formintro,.pv-form{padding:24px 20px;border-radius:18px}.pv-grid2{grid-template-columns:minmax(0,1fr)}.pv-recall{padding:22px 20px}.pv-ctas{flex-direction:column;align-items:stretch}}
</style>';
}

function thh_pv2_svg($d) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>'; }

add_shortcode('thh_franchise_v2', function () {
	$u = function ($p) { return esc_url(home_url($p)); };
	$cards = [
		['<path d="M12 2l2.9 6.3 6.9.7-5.2 4.6 1.6 6.8L12 16.9 5.8 20.4l1.6-6.8L2.2 9l6.9-.7z"/>', 'Een merk dat sporters kennen', 'Onze producten zijn een begrip onder fanatieke en recreatieve sporters. Je begint dus niet bij nul.'],
		['<circle cx="12" cy="9" r="6"/><path d="M8.5 14.2L7 22l5-3 5 3-1.5-7.8"/>', 'Kwaliteit voorop', 'Ons eigen merk en een streng geselecteerd aanbod van andere merken. Producten waar we zelf achter staan.'],
		['<path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6"/>', 'Hulp vanaf de basis', 'We helpen je om je eigen The Health House stap voor stap op te bouwen, van de eerste gesprekken tot de opening.'],
	];
	ob_start();
	echo thh_pv2_css(); ?>
<div class="pv">
	<section class="pv-hero">
		<span class="pv-kicker">Franchise</span>
		<h1>Begin je eigen <em>The Health House</em></h1>
		<p>Heb je een passie voor sport en gezond leven en wil je een eigen winkel? Bouw samen met ons een The Health House op in jouw stad.</p>
		<div class="pv-ctas"><a class="pv-btn pv-btn-red" href="#aanmelden">Meld je aan</a><a class="pv-btn pv-btn-ghost" href="<?php echo $u('/locaties/'); ?>">Bekijk onze winkels</a></div>
	</section>

	<section class="pv-sec pv-two">
		<div><span class="pv-label">Jouw eigen winkel</span><h2>De one-stop supplementenshop in jouw stad</h2></div>
		<div class="pv-txt">
			<p>The Health House is ontstaan uit een echte passie voor sport en sportvoeding. We willen onze klanten, en onszelf, de beste producten geven. Die producten zijn inmiddels een begrip onder sporters.</p>
			<p>Wil je een eigen The Health House starten? Dan ben je bij ons aan het goede adres. We helpen je om je winkel vanaf de basis op te bouwen. Vul het formulier in, dan nemen we contact met je op.</p>
		</div>
	</section>

	<section class="pv-sec">
		<h2>Waarom The Health House</h2>
		<div class="pv-cards"><?php foreach ($cards as $c) : ?><div class="pv-card"><span class="pv-ico"><?php echo thh_pv2_svg($c[0]); ?></span><h3><?php echo esc_html($c[1]); ?></h3><p><?php echo esc_html($c[2]); ?></p></div><?php endforeach; ?></div>
	</section>

	<section class="pv-sec pv-formwrap" id="aanmelden">
		<div class="pv-formintro">
			<span class="pv-label">Groei mee</span>
			<h2>Zo werkt het</h2>
			<ul class="pv-steps">
				<li><b>1</b><span>Je vult het formulier in en vertelt waarom je bij ons past.</span></li>
				<li><b>2</b><span>We nemen contact met je op voor een eerste gesprek.</span></li>
				<li><b>3</b><span>Klikt het? Dan bespreken we samen de volgende stappen.</span></li>
			</ul>
		</div>
		<form class="pv-form" id="pvFranchise" novalidate>
			<h3>Meld je aan</h3>
			<input type="hidden" name="action" value="elementor_pro_forms_send_form">
			<input type="hidden" name="post_id" value="41993">
			<input type="hidden" name="form_id" value="aecf96b">
			<input type="hidden" name="referer_title" value="Franchise">
			<input type="hidden" name="queried_id" value="41993">
			<div class="pv-grid2">
				<label>Volledige naam<input type="text" name="form_fields[name]" autocomplete="name" required></label>
				<label>Telefoonnummer<input type="tel" name="form_fields[field_f392297]" autocomplete="tel"></label>
			</div>
			<label>E-mail<input type="email" name="form_fields[email]" autocomplete="email" required></label>
			<label>Waarom ben jij een match met The Health House?<textarea name="form_fields[message]"></textarea></label>
			<div class="pv-msg" role="status"></div>
			<div><button class="pv-btn pv-btn-red" type="submit">Verstuur aanmelding</button></div>
		</form>
	</section>
</div>
<script>
(function(){var f=document.getElementById('pvFranchise');if(!f)return;var m=f.querySelector('.pv-msg'),b=f.querySelector('button[type=submit]');
f.addEventListener('submit',function(e){e.preventDefault();m.className='pv-msg';
var n=f.querySelector('[name="form_fields[name]"]').value.trim(),em=f.querySelector('[name="form_fields[email]"]').value.trim();
if(!n||!/^\S+@\S+\.\S+$/.test(em)){m.textContent='Vul je naam en een geldig e-mailadres in.';m.className='pv-msg err';return;}
b.disabled=true;b.textContent='Bezig...';
fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,{method:'POST',body:new FormData(f),credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
if(j&&j.success){f.reset();m.textContent='Bedankt voor je aanmelding! We nemen zo snel mogelijk contact met je op.';m.className='pv-msg ok';}
else{m.textContent='Er ging iets mis. Probeer het nog eens of stuur ons een WhatsApp.';m.className='pv-msg err';}
}).catch(function(){m.textContent='Er ging iets mis. Probeer het nog eens of stuur ons een WhatsApp.';m.className='pv-msg err';}).then(function(){b.disabled=false;b.textContent='Verstuur aanmelding';});});})();
</script>
<?php
	return ob_get_clean();
});

add_shortcode('thh_terugroep_v2', function () {
	$u = function ($p) { return esc_url(home_url($p)); };
	ob_start();
	echo thh_pv2_css(); ?>
<div class="pv">
	<section class="pv-hero">
		<span class="pv-kicker">Klantenservice</span>
		<h1>Terugroep<em>acties</em></h1>
		<p>Hier vind je de producten die we uit de verkoop hebben gehaald en wat je moet doen als je zo'n product hebt gekocht.</p>
	</section>

	<section class="pv-sec">
		<article class="pv-recall" id="jennah">
			<div>
				<div class="pv-rtag"><span class="nu">Nieuw</span><span>Oktober 2026</span></div>
				<h3>Jennah Organics</h3>
				<p>De NVWA heeft ons laten weten dat de samenstelling en de claims van producten van Jennah Organics niet voldoen aan de regels voor voedingssupplementen. Daarnaast missen de etiketten verplichte Nederlandse informatie. We hebben daarom alle producten van Jennah Organics uit de verkoop gehaald.</p>
				<p>Het gaat om alle producten van dit merk die bij ons zijn gekocht, online en in de winkel, waaronder:</p>
				<ul><li>Colon Support</li><li>Crave Control</li><li>Magic Magnesium</li><li>Collagen Care</li><li>Hormone Support</li><li>Burn Boost</li><li>Hair Growth</li><li>de bundels en de eiwitshake van Jennah Organics</li></ul>
				<p>Heb je een van deze producten? Gebruik het niet meer. Wij kunnen het aankoopbedrag niet zelf terugbetalen, maar we dienen wel een claim in bij Jennah Organics. Laat via het formulier je gegevens achter, dan nemen we je mee in die claim en houden we je op de hoogte.</p>
			</div>
			<aside class="pv-ract"><strong>Laat je gegevens achter</strong>
				<form class="pv-claim" id="pvClaim" novalidate>
					<input type="text" name="naam" placeholder="Naam" autocomplete="name" required>
					<input type="email" name="email" placeholder="E-mailadres" autocomplete="email" required>
					<input type="tel" name="telefoon" placeholder="Telefoonnummer" autocomplete="tel">
					<input type="text" name="product" placeholder="Welk product, en waar gekocht?">
					<input type="text" name="bestelnummer" placeholder="Bestelnummer of datum aankoopbon">
					<input type="text" name="website" class="pv-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
					<div class="pv-msg" role="status"></div>
					<button class="pv-btn pv-btn-nv" type="submit">Verstuur</button>
				</form>
			</aside>
		</article>
<script>
(function(){var f=document.getElementById('pvClaim');if(!f)return;var m=f.querySelector('.pv-msg'),b=f.querySelector('button');
f.addEventListener('submit',function(e){e.preventDefault();m.className='pv-msg';var d={};new FormData(f).forEach(function(v,k){d[k]=v;});
if(!d.naam||!/^\S+@\S+\.\S+$/.test(d.email||'')){m.textContent='Vul je naam en een geldig e-mailadres in.';m.className='pv-msg err';return;}
b.disabled=true;fetch(<?php echo wp_json_encode(rest_url('thh/v1/jennah-claim')); ?>,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)}).then(function(r){return r.json().then(function(j){return {ok:r.ok,j:j};});}).then(function(x){
if(x.ok){f.reset();m.textContent='Bedankt. We hebben je gegevens ontvangen en houden je op de hoogte.';m.className='pv-msg ok';}else{m.textContent=(x.j&&x.j.message)||'Er ging iets mis. Probeer het later nog eens.';m.className='pv-msg err';}}).catch(function(){m.textContent='Er ging iets mis. Probeer het later nog eens.';m.className='pv-msg err';}).then(function(){b.disabled=false;});});})();
</script>

		<article class="pv-recall" id="prime">
			<div>
				<div class="pv-rtag"><span>2024</span></div>
				<h3>PRIME Pre-workout (THT 2023/2024)</h3>
				<p>We hebben PRIME Pre-workout met een THT van 2023/2024 uit de schappen gehaald. Door een fout in de productie bevat dit product Alpha-GPC, dat niet meer is toegestaan.</p>
				<p>Na vragen van de NVWA adviseren we je dit product niet te gebruiken en het terug te brengen naar een van onze winkels. Bij inlevering krijg je het aankoopbedrag terug. Onze excuses voor het ongemak.</p>
			</div>
			<aside class="pv-ract"><strong>Terugbrengen</strong><p>Breng het product naar een van onze winkels of neem contact met ons op.</p><a class="pv-btn pv-btn-nv" href="<?php echo $u('/locaties/'); ?>">Vind een winkel</a><a class="pv-btn pv-btn-wa" href="https://wa.me/31611789629" target="_blank" rel="noopener nofollow">WhatsApp ons</a></aside>
		</article>
	</section>
</div>
<?php
	return ob_get_clean();
});

/* Jennah-claim: gegevens van klanten opslaan en doorsturen naar klantenservice */
add_action('rest_api_init', function () {
	register_rest_route('thh/v1', '/jennah-claim', [
		'methods' => 'POST',
		'permission_callback' => '__return_true',
		'callback' => function ($req) {
			if (trim((string) $req->get_param('website')) !== '') return ['ok' => true];
			$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
			$k = 'thh_jc_' . md5($ip); $n = (int) get_transient($k);
			if ($n >= 6) return new WP_Error('te_vaak', 'Je hebt al een paar keer iets verstuurd. Probeer het later nog eens.', ['status' => 429]);
			set_transient($k, $n + 1, HOUR_IN_SECONDS);
			$d = [];
			foreach (['naam', 'email', 'telefoon', 'product', 'bestelnummer'] as $f) $d[$f] = sanitize_text_field(mb_substr((string) $req->get_param($f), 0, 200));
			if ($d['naam'] === '' || !is_email($d['email'])) return new WP_Error('ongeldig', 'Vul je naam en een geldig e-mailadres in.', ['status' => 400]);
			$d['tijd'] = current_time('mysql');
			$lijst = get_option('thh_jennah_claims', []); if (!is_array($lijst)) $lijst = [];
			$lijst[] = $d; update_option('thh_jennah_claims', $lijst, false);
			$body = "Nieuwe aanmelding voor de Jennah Organics-claim (terugroepactie):\n\n";
			foreach ($d as $k2 => $v) $body .= ucfirst($k2) . ': ' . $v . "\n";
			$body .= "\nTotaal aantal aanmeldingen: " . count($lijst) . "\n";
			wp_mail('klantenservice@thehealthhouse.nl', 'Jennah-claim: ' . $d['naam'], $body, ['Reply-To: ' . $d['naam'] . ' <' . $d['email'] . '>']);
			return ['ok' => true];
		},
	]);
	register_rest_route('thh/v1', '/jennah-claim/lijst', [
		'methods' => 'GET',
		'permission_callback' => function () { return current_user_can('manage_woocommerce'); },
		'callback' => function () { $l = get_option('thh_jennah_claims', []); return is_array($l) ? $l : []; },
	]);
});

add_shortcode('thh_advies_v2', function () {
	$u = function ($p) { return esc_url(home_url($p)); };
	$s = 'thh_pv2_svg';
	$onderwerpen = [
		['<path d="M3 12h4l3-8 4 16 3-8h4"/>', 'Afvallen', 'Gezond gewicht verliezen zonder je spieren kwijt te raken. We kijken naar je voeding, je ritme en wat wel en niet werkt.'],
		['<path d="M6.5 6.5h11M6.5 17.5h11M4 9v6M20 9v6M2 10.5v3M22 10.5v3"/>', 'Spieropbouw en aankomen', 'Meer kracht en spiermassa. Hoeveel eiwit je nodig hebt, wanneer je het neemt en welke producten echt iets toevoegen.'],
		['<path d="M10.5 20.5 3.5 13.5a5 5 0 017-7l7 7a5 5 0 01-7 7z"/><path d="m8.5 8.5 7 7"/>', 'De juiste supplementen', 'Door het aanbod zie je soms de producten niet meer. We helpen je kiezen wat past bij jouw doel en budget, en vertellen ook wat je niet nodig hebt.'],
		['<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>', 'Training en herstel', 'Veel van onze coaches trainen zelf. Ze denken mee over je schema, je herstel en hoe je sterker, sneller en fitter wordt.'],
	];
	$form = function_exists('gravity_form') ? do_shortcode('[gravityform id="2" title="false" description="false" ajax="true"]') : '';
	ob_start();
	echo thh_pv2_css(); ?>
<div class="pv av">
	<section class="pv-hero av-hero">
		<div class="av-herotxt">
			<span class="pv-kicker">Gratis en vrijblijvend</span>
			<h1>Persoonlijk <em>advies</em></h1>
			<p>Wil je afvallen, aankomen of weet je niet welk supplement bij je past? Onze specialisten helpen je gratis, in de winkel en online.</p>
			<div class="pv-ctas"><a class="pv-btn pv-btn-red" href="#advies-formulier">Stel je vraag</a><a class="pv-btn pv-btn-ghost" href="https://wa.me/31611789629" target="_blank" rel="noopener nofollow">WhatsApp ons</a></div>
		</div>
		<figure class="av-herovis"><img src="https://thehealthhouse.nl/wp-content/uploads/2026/11/thh-over-advies-2.webp" alt="Specialist van The Health House geeft een klant advies" loading="eager" fetchpriority="high"></figure>
	</section>

	<section class="pv-sec">
		<h2>Waar we je mee helpen</h2>
		<div class="pv-cards av-cards">
			<?php foreach ($onderwerpen as $o) : ?><div class="pv-card"><span class="pv-ico"><?php echo $s($o[0]); ?></span><h3><?php echo esc_html($o[1]); ?></h3><p><?php echo esc_html($o[2]); ?></p></div><?php endforeach; ?>
		</div>
	</section>

	<section class="pv-sec pv-two av-story">
		<div><span class="pv-label">Speciaal voor jou</span><h2>Advies van mensen die zelf trainen</h2></div>
		<div class="pv-txt">
			<p>In al onze winkels en online staan onze medewerkers klaar om je te helpen. Wil je weten hoe je op een gezonde manier afvalt of aankomt? Onze coaches adviseren je altijd vrijblijvend.</p>
			<p>Naast advies over voeding en supplementen geven veel van onze coaches ook advies over training. Wil je weten hoe je sterker, sneller en fitter wordt? Kom eens langs in een van onze winkels of stel je vraag hieronder.</p>
			<p>We verkopen alleen producten waar we zelf achter staan: ons eigen merk en een strenge selectie van de beste merken uit Europa en de VS.</p>
		</div>
	</section>

	<section class="pv-sec pv-formwrap" id="advies-formulier">
		<div class="pv-formintro">
			<span class="pv-label">Je hoeft het niet alleen te doen</span>
			<h2>Stel je vraag aan een specialist</h2>
			<p>Vertel ons kort wat je doel is. Je krijgt zo snel mogelijk persoonlijk antwoord van een van onze specialisten.</p>
			<ol class="pv-steps">
				<li><b>1</b><span>Vul het formulier in met je doel of vraag.</span></li>
				<li><b>2</b><span>Een specialist neemt contact met je op, per mail of telefoon.</span></li>
				<li><b>3</b><span>Je krijgt advies op maat. Liever face to face? Kom langs in een van <a href="<?php echo $u('/locaties/'); ?>">onze winkels</a>.</span></li>
			</ol>
		</div>
		<div class="pv-form av-form"><h3>Adviesformulier</h3><?php echo $form; ?></div>
	</section>
</div>
<style>
.av-hero{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:24px;min-height:440px;padding:0 0 0 56px!important}
.av-herotxt{display:flex;flex-direction:column;justify-content:center;padding:48px 0}.av-herotxt .pv-kicker{align-self:flex-start}
.av-herovis{margin:0;position:relative;min-height:100%}.av-herovis img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:30% 30%}
.av-cards{grid-template-columns:repeat(4,minmax(0,1fr))}
.pv-steps a{color:var(--rd);font-weight:600}
.pv .av-form{background:var(--bg);color:var(--tx)}.pv .av-form h3{color:var(--nv)!important}.av-form .gform_wrapper form,.av-form .gform-theme,.av-form>.gform_wrapper{box-shadow:0 10px 30px rgba(20,32,74,.08)!important}
.av-form .gform_wrapper{margin:0!important}.av-form .gform_wrapper .gform_fields{row-gap:14px!important}
.av-form .gform_wrapper .gfield_label,.av-form .gform_wrapper legend{color:#fff!important;font-weight:500!important;font-size:14px!important;text-transform:none!important;letter-spacing:0!important}
.av-form .gform_wrapper input:not([type=submit]):not([type=checkbox]):not([type=radio]),.av-form .gform_wrapper textarea{width:100%!important;background:#fff!important;border:1px solid transparent!important;border-radius:12px!important;padding:12px 14px!important;font-size:15px!important;color:var(--tx)!important;box-shadow:none!important;font-family:inherit!important}
.av-form .gform_wrapper textarea{min-height:130px!important}.av-form .gform_wrapper input:focus,.av-form .gform_wrapper textarea:focus{border-color:var(--rd)!important;outline:none!important}
.av-form .gform_wrapper .gfield_description,.av-form .gform_wrapper .gfield_required{color:rgba(255,255,255,.75)!important}
.av-form .gform_wrapper .gform_footer{padding:0!important;margin:16px 0 0!important}
.av-form .gform_wrapper .gform_footer input[type=submit],.av-form .gform_wrapper .gform_footer button{background:var(--rd)!important;color:#fff!important;border:0!important;border-radius:999px!important;padding:14px 30px!important;font-weight:600!important;font-size:15px!important;text-transform:none!important;letter-spacing:0!important;cursor:pointer;width:auto!important}
.av-form .gform_wrapper .gform_footer input[type=submit]:hover{background:#c92c33!important}
.av-form .gform_confirmation_message{color:#fff;font-size:16px}
@media(max-width:1100px){.av-cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:900px){.av-hero{grid-template-columns:minmax(0,1fr);padding:0!important;min-height:0}.av-herotxt{padding:28px 28px 32px}.av-herovis{order:-1;height:280px}}
@media(max-width:640px){.av-herotxt{padding:22px 20px 26px}.av-herovis{height:220px}.av-cards{grid-template-columns:minmax(0,1fr)}}
</style>
<?php
	return ob_get_clean();
});
