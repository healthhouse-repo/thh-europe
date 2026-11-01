<?php
/**
 * THH Zakelijk account aanvragen — shortcode [thh_zakelijk_form]
 * - Formulier voor bedrijven (naar voorbeeld van thheurope.com/#word-klant)
 * - Aanvragen worden opgeslagen als privé-item "Zakelijke aanvraag" (wp-admin → Zakelijke aanvragen)
 * - Melding per e-mail naar klantenservice@thehealthhouse.nl (alleen intern, geen mail naar de aanvrager)
 * REST: POST /wp-json/thh/v1/zakelijk-aanvraag
 */
if (!defined('ABSPATH')) return;

if (!defined('THH_ZAK_NOTIFY')) define('THH_ZAK_NOTIFY', 'klantenservice@thehealthhouse.nl');

add_action('init', function () {
	register_post_type('thh_zakelijk', [
		'labels' => ['name' => 'Zakelijke aanvragen', 'singular_name' => 'Zakelijke aanvraag', 'menu_name' => 'Zakelijke aanvragen'],
		'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_position' => 57, 'menu_icon' => 'dashicons-businessman',
		'supports' => ['title', 'editor', 'custom-fields'],
		'capability_type' => 'post',
		'capabilities' => ['create_posts' => 'do_not_allow'],
		'map_meta_cap' => true,
	]);
});

function thh_zak_fields() {
	return [
		'company'  => ['Bedrijfsnaam', true],
		'kvk'      => ['KvK-nummer (of buitenlands registratienummer)', true],
		'vat'      => ['Btw-nummer', false],
		'contact'  => ['Contactpersoon', true],
		'email'    => ['E-mailadres', true],
		'phone'    => ['Telefoonnummer', false],
		'street'   => ['Straat', true],
		'house'    => ['Huisnummer', true],
		'postcode' => ['Postcode', true],
		'city'     => ['Plaats', true],
		'country'  => ['Land', true],
		'btype'    => ['Type bedrijf', false],
		'volume'   => ['Verwachte afname per maand', false],
		'message'  => ['Opmerking', false],
	];
}
function thh_zak_countries() {
	return ['NL' => 'Nederland', 'BE' => 'België', 'LU' => 'Luxemburg', 'DE' => 'Duitsland', 'AT' => 'Oostenrijk', 'FR' => 'Frankrijk',
		'ES' => 'Spanje', 'IT' => 'Italië', 'PT' => 'Portugal', 'IE' => 'Ierland', 'DK' => 'Denemarken', 'SE' => 'Zweden', 'FI' => 'Finland',
		'PL' => 'Polen', 'CZ' => 'Tsjechië', 'SK' => 'Slowakije', 'HU' => 'Hongarije', 'SI' => 'Slovenië', 'HR' => 'Kroatië', 'GR' => 'Griekenland',
		'RO' => 'Roemenië', 'BG' => 'Bulgarije', 'EE' => 'Estland', 'LV' => 'Letland', 'LT' => 'Litouwen', 'CY' => 'Cyprus', 'MT' => 'Malta'];
}

add_action('rest_api_init', function () {
	register_rest_route('thh/v1', '/zakelijk-aanvraag', ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function (WP_REST_Request $r) {
		$p = $r->get_json_params() ?: $r->get_body_params();
		if (!empty($p['website'])) return ['ok' => true]; // honeypot
		$ip = preg_replace('/[^0-9a-f:.]/i', '', $_SERVER['REMOTE_ADDR'] ?? '');
		$key = 'thh_zak_' . md5($ip);
		$n = (int) get_transient($key);
		if ($n >= 5) return new WP_Error('te_vaak', 'Te veel aanvragen. Probeer het later opnieuw.', ['status' => 429]);
		$data = []; $err = [];
		foreach (thh_zak_fields() as $k => [$label, $req]) {
			$v = isset($p[$k]) ? (string) $p[$k] : '';
			$v = $k === 'message' ? sanitize_textarea_field(mb_substr($v, 0, 2000)) : sanitize_text_field(mb_substr($v, 0, 200));
			if ($req && $v === '') $err[] = $label;
			$data[$k] = $v;
		}
		if ($data['email'] !== '' && !is_email($data['email'])) $err[] = 'E-mailadres (ongeldig)';
		if ($data['country'] !== '' && !isset(thh_zak_countries()[$data['country']])) $err[] = 'Land (ongeldig)';
		if (empty($p['privacy'])) $err[] = 'Akkoord privacyverklaring';
		if ($err) return new WP_Error('velden', 'Vul deze velden in: ' . implode(', ', $err), ['status' => 400]);
		set_transient($key, $n + 1, HOUR_IN_SECONDS);

		$lines = [];
		foreach (thh_zak_fields() as $k => [$label]) {
			$val = $k === 'country' ? thh_zak_countries()[$data[$k]] . ' (' . $data[$k] . ')' : $data[$k];
			$lines[] = $label . ': ' . ($val !== '' ? $val : '-');
		}
		$lang = function_exists('thh_i18n_lang') ? thh_i18n_lang() : 'nl';
		$lines[] = 'Taal van de site: ' . $lang;
		$id = wp_insert_post([
			'post_type' => 'thh_zakelijk', 'post_status' => 'private',
			'post_title' => $data['company'] . ' — ' . $data['contact'],
			'post_content' => implode("\n", array_map('esc_html', $lines)),
		]);
		if (is_wp_error($id) || !$id) return new WP_Error('opslaan', 'Opslaan mislukt, probeer het later opnieuw.', ['status' => 500]);
		foreach ($data as $k => $v) update_post_meta($id, '_thh_' . $k, $v);
		update_post_meta($id, '_thh_lang', $lang);
		update_post_meta($id, '_thh_status', 'nieuw');

		wp_mail(THH_ZAK_NOTIFY, 'Nieuwe zakelijke aanvraag: ' . $data['company'],
			"Er is een nieuwe aanvraag voor een zakelijk account binnengekomen.\n\n" . implode("\n", $lines) .
			"\n\nBekijk in WordPress: " . admin_url('post.php?post=' . $id . '&action=edit'),
			['Reply-To: ' . $data['contact'] . ' <' . $data['email'] . '>']);
		return ['ok' => true];
	}]);
});

add_shortcode('thh_zakelijk_form', function () {
	$c = '';
	foreach (thh_zak_countries() as $k => $v) $c .= '<option value="' . esc_attr($k) . '"' . ($k === 'NL' ? ' selected' : '') . '>' . esc_html($v) . '</option>';
	$url = esc_url(rest_url('thh/v1/zakelijk-aanvraag'));
	ob_start(); ?>
<div class="thz">
	<div class="thz-intro">
		<span class="thz-kicker">Zakelijk</span>
		<h1>Vraag een zakelijk account aan</h1>
		<p>Heb je een sportschool, webshop, winkel of ander bedrijf? Meld je bedrijf aan. Na goedkeuring (meestal binnen één werkdag) nemen we contact met je op over je zakelijke account en voorwaarden.</p>
		<ul class="thz-usp">
			<li>Zakelijke prijzen en vaste contactpersoon</li>
			<li>Levering in heel Nederland en de EU</li>
			<li>Bedrijven in de EU met een geldig btw-nummer krijgen een factuur met btw verlegd</li>
		</ul>
		<p class="thz-small">We leveren zakelijk alleen aan bedrijven met een geldige inschrijving bij de Kamer van Koophandel of een vergelijkbaar register.</p>
	</div>
	<form class="thz-form" id="thzForm" novalidate>
		<div class="thz-grid">
			<label class="thz-full">Bedrijfsnaam *<input name="company" required autocomplete="organization"></label>
			<label>KvK-nummer *<input name="kvk" required></label>
			<label>Btw-nummer<input name="vat" placeholder="bijv. NL123456789B01"></label>
			<label>Contactpersoon *<input name="contact" required autocomplete="name"></label>
			<label>E-mailadres *<input name="email" type="email" required autocomplete="email"></label>
			<label>Telefoonnummer<input name="phone" type="tel" autocomplete="tel"></label>
			<label>Land *<select name="country" required><?php echo $c; ?></select></label>
			<label class="thz-wide">Straat *<input name="street" required autocomplete="address-line1"></label>
			<label>Huisnummer *<input name="house" required></label>
			<label>Postcode *<input name="postcode" required autocomplete="postal-code"></label>
			<label>Plaats *<input name="city" required autocomplete="address-level2"></label>
			<label>Type bedrijf<select name="btype"><option value="">Kies…</option><option>Sportschool</option><option>Webshop</option><option>Winkel</option><option>Personal trainer / coach</option><option>Horeca</option><option>Anders</option></select></label>
			<label>Verwachte afname per maand<select name="volume"><option value="">Kies…</option><option>Tot € 500</option><option>€ 500 tot € 1.500</option><option>€ 1.500 tot € 5.000</option><option>Meer dan € 5.000</option><option>Nog niet bekend</option></select></label>
			<label class="thz-full">Opmerking<textarea name="message" rows="4"></textarea></label>
			<label class="thz-hp" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
			<label class="thz-check thz-full"><input type="checkbox" name="privacy" value="1" required> Ik ga akkoord met de <a href="<?php echo esc_url(home_url('/privacy-beleid/')); ?>" target="_blank">privacyverklaring</a>. *</label>
		</div>
		<p class="thz-error" id="thzError" role="alert" hidden></p>
		<button type="submit" class="thz-btn">Aanvraag versturen</button>
	</form>
	<div class="thz-done" id="thzDone" hidden>
		<h2>Bedankt voor je aanvraag!</h2>
		<p>We controleren je gegevens en nemen zo snel mogelijk contact met je op, meestal binnen één werkdag.</p>
	</div>
</div>
<style>
.thz{--nv:#14204a;--rd:#e2373f;max-width:1200px;margin:40px auto;padding:0 16px;display:grid;grid-template-columns:1fr 1.3fr;gap:48px;align-items:start}
.thz-kicker{display:inline-block;background:var(--rd);color:#fff;font-weight:700;font-size:12px;letter-spacing:.08em;text-transform:uppercase;padding:6px 12px;border-radius:999px}
.thz h1{color:var(--nv);font-size:38px;line-height:1.1;margin:14px 0 16px}.thz-intro p{font-size:16px;line-height:1.6;color:#333}
.thz-usp{list-style:none;padding:0;margin:20px 0}.thz-usp li{padding:8px 0 8px 28px;position:relative;color:var(--nv);font-weight:600}
.thz-usp li:before{content:"✓";position:absolute;left:0;color:var(--rd);font-weight:800}.thz-small{font-size:13px!important;color:#666!important}
.thz-form,.thz-done{background:#f5f6fa;border-radius:18px;padding:28px}
.thz-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.thz-grid label{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:700;color:var(--nv)}
.thz-full{grid-column:1/-1}.thz-wide{grid-column:span 2}
.thz-grid input,.thz-grid select,.thz-grid textarea{border:1px solid #d7dae6;border-radius:10px;padding:12px 14px;font-size:15px;font-weight:400;background:#fff;color:#111;width:100%;box-sizing:border-box}
.thz-check{flex-direction:row!important;align-items:center;gap:10px!important;font-weight:400!important}.thz-check input{width:auto}
.thz-hp{position:absolute!important;left:-9999px}
.thz-btn{margin-top:18px;border:0;border-radius:999px;background:var(--rd);color:#fff;font-weight:700;padding:16px 28px;font-size:16px;cursor:pointer}
.thz-btn[disabled]{opacity:.6}.thz-error{color:#b00020;font-weight:600;margin:14px 0 0}
@media(max-width:900px){.thz{grid-template-columns:1fr;gap:24px}.thz-grid{grid-template-columns:1fr 1fr}.thz h1{font-size:30px}}
@media(max-width:520px){.thz-grid{grid-template-columns:1fr}.thz-wide{grid-column:auto}}
</style>
<script>
(function(){var f=document.getElementById('thzForm');if(!f)return;f.addEventListener('submit',function(e){e.preventDefault();var er=document.getElementById('thzError');er.hidden=true;var miss=[].filter.call(f.querySelectorAll('[required]'),function(i){return i.type==='checkbox'?!i.checked:!i.value.trim()});if(miss.length){er.textContent=<?php echo wp_json_encode('Vul alle verplichte velden (*) in.'); ?>;er.hidden=false;miss[0].focus();return}var d={};new FormData(f).forEach(function(v,k){d[k]=v});var b=f.querySelector('button');b.disabled=true;fetch(<?php echo wp_json_encode($url); ?>,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)}).then(function(r){return r.json().then(function(j){return{ok:r.ok,j:j}})}).then(function(x){if(x.ok&&x.j.ok){f.hidden=true;document.getElementById('thzDone').hidden=false;window.scrollTo({top:f.closest('.thz').offsetTop-80,behavior:'smooth'})}else{er.textContent=(x.j&&x.j.message)||<?php echo wp_json_encode('Er ging iets mis. Probeer het opnieuw.'); ?>;er.hidden=false;b.disabled=false}}).catch(function(){er.textContent=<?php echo wp_json_encode('Er ging iets mis. Probeer het opnieuw.'); ?>;er.hidden=false;b.disabled=false})})})();
</script>
<?php
	return ob_get_clean();
});
