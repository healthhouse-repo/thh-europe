<?php
/**
 * THH — Club-blok op de productpagina (zoals Zalando Plus) — TESTVERSIE
 *
 * Zichtbaar voor beheerders, of voor iedereen die de pagina één keer opent met ?thh_clubtest=1
 * (dan blijft het 7 dagen zichtbaar in die browser; ?thh_clubtest=0 zet het weer uit).
 *
 * Uitgelogd: blok "Word gratis lid" → popup met aanmelden. Aanmelden schrijft je in bij loyalty
 * (via de bestaande proxy /thh/v1/loyalty-signup) én maakt een webshopaccount aan. Daarna staat
 * het inlogscherm klaar met je gegevens ingevuld: één klik en je bent ingelogd.
 * Ingelogd: blok "Maak gratis gebruik van de club" met "Je kocht dit op …" als dat zo is, en
 * "Boek gratis een intake in de winkel" → popup waarin je je gegevens achterlaat. De aanvraag gaat
 * per mail naar klantenservice@thehealthhouse.nl en wordt bewaard in optie thh_club_intakes.
 */
if (!defined('ABSPATH')) return;

add_action('init', function () {
	if (!isset($_GET['thh_clubtest']) || headers_sent()) return;
	$aan = $_GET['thh_clubtest'] === '1';
	setcookie('thh_clubtest', $aan ? '1' : '', $aan ? time() + 7 * DAY_IN_SECONDS : time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
});

function thh_cb_actief() {
	if (isset($_GET['thh_clubtest'])) return $_GET['thh_clubtest'] === '1';
	if (!empty($_COOKIE['thh_clubtest'])) return true;
	return is_user_logged_in() && current_user_can('manage_woocommerce');
}

function thh_cb_winkels() {
	$w = get_transient('thh_cb_winkels');
	if (is_array($w)) return $w;
	$w = [];
	foreach (get_posts(['post_type' => 'wpsl_stores', 'post_status' => 'publish', 'numberposts' => 30, 'orderby' => 'title', 'order' => 'ASC']) as $p) {
		$plaats = get_post_meta($p->ID, 'wpsl_city', true);
		$w[] = trim(get_the_title($p) . ($plaats && stripos(get_the_title($p), $plaats) === false ? ' (' . $plaats . ')' : ''));
	}
	set_transient('thh_cb_winkels', $w, HOUR_IN_SECONDS);
	return $w;
}

add_action('rest_api_init', function () {
	// aanmelden: loyalty + webshopaccount
	register_rest_route('thh/v1', '/club-aanmelden', ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function (WP_REST_Request $r) {
		$p = (array) $r->get_json_params();
		if (!empty($p['website'])) return ['ok' => true];
		$email = sanitize_email($p['email'] ?? '');
		$pw = (string) ($p['password'] ?? '');
		if (!is_email($email)) return new WP_Error('veld', 'Vul een geldig e-mailadres in.', ['status' => 400]);
		if (strlen($pw) < 6) return new WP_Error('veld', 'Kies een wachtwoord van minimaal 6 tekens.', ['status' => 400]);
		// 1. loyalty via de bestaande proxy
		$req = new WP_REST_Request('POST', '/thh/v1/loyalty-signup');
		$req->set_header('content-type', 'application/json');
		$req->set_body(wp_json_encode(['first_name' => $p['first_name'] ?? '', 'last_name' => $p['last_name'] ?? '', 'email' => $email, 'phone' => $p['phone'] ?? '', 'branch' => $p['branch'] ?? '', 'password' => $pw]));
		$res = rest_do_request($req);
		if ($res->is_error()) {
			$e = $res->as_error();
			$msg = $e->get_error_message();
			if (!preg_match('/bestaat|already|al lid|exists/i', $msg)) return new WP_Error('loyalty', $msg ?: 'Aanmelden is niet gelukt.', ['status' => 400]);
		}
		// 2. webshopaccount (als dat er nog niet is)
		$bestond = (bool) email_exists($email);
		if (!$bestond && function_exists('wc_create_new_customer')) {
			$uid = wc_create_new_customer($email, '', $pw, ['first_name' => sanitize_text_field($p['first_name'] ?? ''), 'last_name' => sanitize_text_field($p['last_name'] ?? '')]);
			if (is_wp_error($uid)) return new WP_Error('account', $uid->get_error_message(), ['status' => 400]);
			if (!empty($p['phone'])) update_user_meta($uid, 'billing_phone', sanitize_text_field($p['phone']));
		}
		return ['ok' => true, 'bestond' => $bestond];
	}]);

	// inloggen vanuit de popup
	register_rest_route('thh/v1', '/club-inloggen', ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function (WP_REST_Request $r) {
		$p = (array) $r->get_json_params();
		$u = wp_signon(['user_login' => sanitize_text_field($p['email'] ?? ''), 'user_password' => (string) ($p['password'] ?? ''), 'remember' => true], is_ssl());
		if (is_wp_error($u)) return new WP_Error('login', 'Inloggen lukt niet met deze gegevens. Controleer je wachtwoord.', ['status' => 400]);
		return ['ok' => true];
	}]);

	// intake aanvragen
	register_rest_route('thh/v1', '/club-intake', ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function (WP_REST_Request $r) {
		$p = (array) $r->get_json_params();
		if (!empty($p['website'])) return ['ok' => true];
		$k = 'thh_cb_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
		if ((int) get_transient($k) >= 5) return new WP_Error('rate', 'Te veel aanvragen. Probeer het later opnieuw.', ['status' => 429]);
		set_transient($k, (int) get_transient($k) + 1, HOUR_IN_SECONDS);
		$s = function ($v, $m = 200) { return mb_substr(trim(sanitize_text_field((string) $v)), 0, $m); };
		$d = ['naam' => $s($p['naam'] ?? '', 120), 'email' => sanitize_email($p['email'] ?? ''), 'telefoon' => $s($p['telefoon'] ?? '', 30), 'winkel' => $s($p['winkel'] ?? '', 120), 'wanneer' => $s($p['wanneer'] ?? '', 120), 'doel' => mb_substr(trim(sanitize_textarea_field((string) ($p['doel'] ?? ''))), 0, 1000), 'product' => $s($p['product'] ?? '', 200), 'user' => get_current_user_id(), 'datum' => current_time('mysql')];
		if ($d['naam'] === '' || (!$d['email'] && !$d['telefoon'])) return new WP_Error('veld', 'Vul je naam en een e-mailadres of telefoonnummer in.', ['status' => 400]);
		$log = get_option('thh_club_intakes', []);
		$log = is_array($log) ? $log : [];
		array_unshift($log, $d);
		update_option('thh_club_intakes', array_slice($log, 0, 500), false);
		$body = "Nieuwe aanvraag voor een gratis intake in de winkel\n\nNaam: {$d['naam']}\nE-mail: {$d['email']}\nTelefoon: {$d['telefoon']}\nWinkel: {$d['winkel']}\nWanneer: {$d['wanneer']}\nDoel / vraag: {$d['doel']}\nVanaf product: {$d['product']}\n";
		$headers = $d['email'] ? ['Reply-To: ' . $d['naam'] . ' <' . $d['email'] . '>'] : [];
		wp_mail('klantenservice@thehealthhouse.nl', 'Intake aangevraagd: ' . $d['naam'] . ($d['winkel'] ? ' (' . $d['winkel'] . ')' : ''), $body, $headers);
		return ['ok' => true];
	}]);
});

add_action('woocommerce_after_add_to_cart_form', function () {
	if (!thh_cb_actief()) return;
	global $product;
	$ingelogd = is_user_logged_in();
	$u = wp_get_current_user();
	$gekocht = '';
	if ($ingelogd && $product && function_exists('wc_get_orders')) {
		$orders = wc_get_orders(['customer_id' => $u->ID, 'status' => ['wc-completed', 'wc-processing'], 'limit' => 20, 'orderby' => 'date', 'order' => 'DESC']);
		foreach ($orders as $o) {
			foreach ($o->get_items() as $it) {
				if ((int) $it->get_product_id() === (int) $product->get_id()) {
					$var = $it->get_variation_id() ? wc_get_product($it->get_variation_id()) : null;
					$maat = $var ? implode(', ', array_values($var->get_variation_attributes(false))) : '';
					$gekocht = 'Je kocht ' . ($maat ? '<b>' . esc_html($maat) . '</b> ' : 'dit product ') . 'op ' . esc_html(wc_format_datetime($o->get_date_created(), 'j F Y'));
					break 2;
				}
			}
		}
	}
	$naam = $ingelogd ? trim($u->first_name . ' ' . $u->last_name) : '';
	$tel = $ingelogd ? get_user_meta($u->ID, 'billing_phone', true) : '';
	?>
<div class="thh-cb" data-product="<?php echo esc_attr($product ? $product->get_name() : ''); ?>">
	<?php if ($gekocht) : ?><p class="thh-cb-gekocht"><?php echo $gekocht; ?> · <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>">Bekijk je bestellingen</a></p><?php endif; ?>
	<div class="thh-cb-box">
		<span class="thh-cb-logo">+Club</span>
		<?php if ($ingelogd) : ?>
			<strong>Maak gratis gebruik van de club<?php echo $u->first_name ? ', ' . esc_html($u->first_name) : ''; ?>!</strong>
			<p>Spaar punten bij elke aankoop, in de webshop én in onze winkels, en laat je gratis persoonlijk adviseren door onze specialisten.</p>
			<button type="button" class="thh-cb-link" data-thh-cb="intake">Boek gratis een intake in de winkel</button>
		<?php else : ?>
			<strong>Spaar punten en krijg persoonlijk advies</strong>
			<p>Word gratis lid van de club: je spaart punten op elke aankoop in de webshop en in onze winkels, en je boekt gratis een intake met een specialist.</p>
			<button type="button" class="thh-cb-link" data-thh-cb="aanmelden">Word gratis lid</button>
			<span class="thh-cb-al">Al lid? <button type="button" class="thh-cb-link thh-cb-klein" data-thh-cb="inloggen">Log in</button></span>
		<?php endif; ?>
	</div>
</div>
<div class="thh-cb-pop" id="thhCbPop" hidden>
	<div class="thh-cb-dlg" role="dialog" aria-modal="true" aria-labelledby="thhCbTitel">
		<button type="button" class="thh-cb-x" data-thh-cb="sluit" aria-label="Sluiten">×</button>
		<!-- aanmelden -->
		<form class="thh-cb-stap" data-stap="aanmelden" novalidate>
			<span class="thh-cb-logo">+Club</span>
			<h3 id="thhCbTitel">Word gratis lid</h3>
			<p class="thh-cb-sub">Spaar punten op elke aankoop en boek gratis een intake in de winkel.</p>
			<div class="thh-cb-g2"><label>Voornaam *<input name="first_name" autocomplete="given-name" required></label><label>Achternaam *<input name="last_name" autocomplete="family-name" required></label></div>
			<label>E-mail *<input type="email" name="email" autocomplete="email" required></label>
			<label>Telefoonnummer<input type="tel" name="phone" autocomplete="tel"></label>
			<label>Wachtwoord * <small>(minimaal 6 tekens)</small><input type="password" name="password" autocomplete="new-password" minlength="6" required></label>
			<label>Je vaste winkel<select name="branch"><option value="">Kies een winkel (mag later)</option><?php foreach (thh_cb_winkels() as $w) echo '<option>' . esc_html($w) . '</option>'; ?></select></label>
			<input type="text" name="website" class="thh-cb-hp" tabindex="-1" autocomplete="off">
			<div class="thh-cb-msg"></div>
			<button type="submit" class="thh-cb-btn">Meld me gratis aan</button>
			<p class="thh-cb-klein-t">Al lid? <button type="button" class="thh-cb-link thh-cb-klein" data-thh-cb="inloggen">Log in</button></p>
		</form>
		<!-- inloggen -->
		<form class="thh-cb-stap" data-stap="inloggen" novalidate hidden>
			<span class="thh-cb-logo">+Club</span>
			<h3>Inloggen</h3>
			<p class="thh-cb-sub thh-cb-welkom" hidden>Welkom bij de club! Je account is klaar, log direct in.</p>
			<label>E-mail<input type="email" name="email" autocomplete="username" required></label>
			<label>Wachtwoord<input type="password" name="password" autocomplete="current-password" required></label>
			<div class="thh-cb-msg"></div>
			<button type="submit" class="thh-cb-btn">Inloggen</button>
			<p class="thh-cb-klein-t"><a href="<?php echo esc_url(wp_lostpassword_url()); ?>">Wachtwoord vergeten?</a></p>
		</form>
		<!-- intake -->
		<form class="thh-cb-stap" data-stap="intake" novalidate hidden>
			<span class="thh-cb-logo">+Club</span>
			<h3>Boek gratis een intake</h3>
			<p class="thh-cb-sub">Laat je gegevens achter. Een specialist neemt contact met je op om een moment in de winkel af te spreken.</p>
			<label>Naam *<input name="naam" autocomplete="name" value="<?php echo esc_attr($naam); ?>" required></label>
			<div class="thh-cb-g2"><label>E-mail<input type="email" name="email" autocomplete="email" value="<?php echo esc_attr($ingelogd ? $u->user_email : ''); ?>"></label><label>Telefoonnummer<input type="tel" name="telefoon" autocomplete="tel" value="<?php echo esc_attr($tel); ?>"></label></div>
			<label>Welke winkel?<select name="winkel"><option value="">Maakt niet uit</option><?php foreach (thh_cb_winkels() as $w) echo '<option>' . esc_html($w) . '</option>'; ?></select></label>
			<label>Wanneer kan je het beste?<input name="wanneer" placeholder="bijv. zaterdagochtend of na 17:00"></label>
			<label>Waar wil je advies over?<textarea name="doel" rows="3" placeholder="bijv. spieropbouw, afvallen, beter herstel"></textarea></label>
			<input type="text" name="website" class="thh-cb-hp" tabindex="-1" autocomplete="off">
			<div class="thh-cb-msg"></div>
			<button type="submit" class="thh-cb-btn">Intake aanvragen</button>
		</form>
		<div class="thh-cb-stap thh-cb-klaar" data-stap="klaar" hidden>
			<span class="thh-cb-logo">+Club</span>
			<h3>Aanvraag ontvangen</h3>
			<p class="thh-cb-sub">Bedankt! We nemen binnen 2 werkdagen contact met je op om je intake in te plannen.</p>
			<button type="button" class="thh-cb-btn" data-thh-cb="sluit">Sluiten</button>
		</div>
	</div>
</div>
<style>
.thh-cb{margin:18px 0 6px;font-family:inherit}
.thh-cb-gekocht{margin:0 0 12px;font-size:15px;color:#1b1f2e}.thh-cb-gekocht a{color:#14204a;text-decoration:underline}
.thh-cb-box{border:2px solid #14204a;border-radius:16px;padding:22px 24px;display:flex;flex-direction:column;align-items:flex-start;gap:10px;background:#fff}
.thh-cb-logo{display:inline-block;border:2px solid #14204a;border-radius:999px;padding:3px 12px;font-weight:800;font-size:16px;color:#14204a;letter-spacing:-.01em}
.thh-cb-box strong{font-size:17px;line-height:1.35;color:#14204a}
.thh-cb-box p{margin:0;font-size:15px;line-height:1.55;color:#1b1f2e}
.thh-cb button.thh-cb-link,.thh-cb-pop button.thh-cb-link{background:none!important;border:0!important;padding:0!important;margin:4px 0 0;font:inherit;font-weight:700;font-size:15px;color:#14204a!important;text-decoration:underline;text-underline-offset:4px;cursor:pointer;box-shadow:none!important;text-transform:none;letter-spacing:0;min-height:0}
.thh-cb button.thh-cb-klein,.thh-cb-pop button.thh-cb-klein{font-size:14px;margin:0;font-weight:600}
.thh-cb-al{font-size:14px;color:#5a6072}
.thh-cb-pop{position:fixed;inset:0;z-index:99999;background:rgba(10,16,40,.55);display:flex;align-items:center;justify-content:center;padding:16px}
.thh-cb-pop[hidden]{display:none}
.thh-cb-dlg{background:#fff;border-radius:20px;width:100%;max-width:460px;max-height:92vh;overflow:auto;padding:28px 28px 24px;position:relative;box-shadow:0 30px 80px rgba(0,0,0,.35)}
.thh-cb-x{position:absolute;right:12px;top:10px;background:none!important;border:0!important;font-size:28px;line-height:1;color:#5a6072!important;cursor:pointer;padding:6px!important}
.thh-cb-stap{display:flex;flex-direction:column;gap:12px;margin:0}
.thh-cb-stap[hidden]{display:none}
.thh-cb-stap h3{margin:4px 0 0;font-size:22px;color:#14204a;text-transform:none;letter-spacing:0}
.thh-cb-sub{margin:0;font-size:14.5px;line-height:1.5;color:#5a6072}
.thh-cb-stap label{display:flex;flex-direction:column;gap:5px;font-size:13.5px;font-weight:500;color:#1b1f2e}
.thh-cb-stap label small{font-weight:400;color:#5a6072}
.thh-cb-stap input,.thh-cb-stap select,.thh-cb-stap textarea{width:100%;font:inherit;font-size:15px;padding:11px 12px;border:1.5px solid #c9ccd6;border-radius:10px;background:#fff;color:#1b1f2e;box-shadow:none}
.thh-cb-stap input:focus,.thh-cb-stap select:focus,.thh-cb-stap textarea:focus{outline:none;border-color:#14204a}
.thh-cb-g2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:10px}
.thh-cb-hp{position:absolute!important;left:-9999px!important;opacity:0}
.thh-cb-btn{background:#14204a!important;color:#fff!important;border:0!important;border-radius:999px!important;min-height:48px;font:inherit;font-weight:700;font-size:15px;cursor:pointer;margin-top:4px;text-transform:none}
.thh-cb-btn:hover{background:#0d1533!important}.thh-cb-btn[disabled]{opacity:.6}
.thh-cb-msg:empty{display:none}.thh-cb-msg{background:#fdecee;color:#9b1c22;border-radius:10px;padding:10px 12px;font-size:14px}
.thh-cb-klein-t{margin:0;font-size:14px;color:#5a6072;text-align:center}.thh-cb-klein-t a{color:#14204a}
@media(max-width:480px){.thh-cb-g2{grid-template-columns:minmax(0,1fr)}.thh-cb-dlg{padding:22px 18px}}
</style>
<script>
(function(){
	var pop=document.getElementById('thhCbPop');if(!pop||pop.dataset.ok)return;pop.dataset.ok=1;document.body.appendChild(pop);
	var api='<?php echo esc_js(rest_url('thh/v1/')); ?>';
	function toon(stap){[].forEach.call(pop.querySelectorAll('.thh-cb-stap'),function(s){s.hidden=s.getAttribute('data-stap')!==stap;});pop.hidden=false;var f=pop.querySelector('[data-stap="'+stap+'"] input:not([type=hidden]):not(.thh-cb-hp)');if(f&&!f.value)setTimeout(function(){f.focus();},50);}
	function sluit(){pop.hidden=true;}
	document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('[data-thh-cb]');if(!b)return;e.preventDefault();var a=b.getAttribute('data-thh-cb');if(a==='sluit')sluit();else toon(a);});
	pop.addEventListener('click',function(e){if(e.target===pop)sluit();});
	document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!pop.hidden)sluit();});
	function data(f){var o={};[].forEach.call(f.elements,function(el){if(el.name)o[el.name]=el.value;});return o;}
	function post(pad,body){return fetch(api+pad,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.message||'Er ging iets mis.');return j;});});}
	function bezig(f,aan){var b=f.querySelector('.thh-cb-btn');if(b){b.disabled=aan;}f.querySelector('.thh-cb-msg').textContent='';}
	var fa=pop.querySelector('[data-stap="aanmelden"]'),fi=pop.querySelector('[data-stap="inloggen"]'),fk=pop.querySelector('[data-stap="intake"]');
	fa.addEventListener('submit',function(e){e.preventDefault();var d=data(fa);
		if(!d.first_name||!d.last_name||!d.email||!d.password){fa.querySelector('.thh-cb-msg').textContent='Vul je naam, e-mail en een wachtwoord in.';return;}
		if(d.password.length<6){fa.querySelector('.thh-cb-msg').textContent='Kies een wachtwoord van minimaal 6 tekens.';return;}
		bezig(fa,true);post('club-aanmelden',d).then(function(){fi.email.value=d.email;fi.password.value=d.password;fi.querySelector('.thh-cb-welkom').hidden=false;toon('inloggen');fi.querySelector('.thh-cb-btn').focus();}).catch(function(err){fa.querySelector('.thh-cb-msg').textContent=err.message;}).then(function(){bezig(fa,false);});});
	fi.addEventListener('submit',function(e){e.preventDefault();var d=data(fi);bezig(fi,true);post('club-inloggen',d).then(function(){location.reload();}).catch(function(err){fi.querySelector('.thh-cb-msg').textContent=err.message;bezig(fi,false);});});
	fk.addEventListener('submit',function(e){e.preventDefault();var d=data(fk);var box=document.querySelector('.thh-cb');d.product=box?box.getAttribute('data-product'):'';
		if(!d.naam||(!d.email&&!d.telefoon)){fk.querySelector('.thh-cb-msg').textContent='Vul je naam en een e-mailadres of telefoonnummer in.';return;}
		bezig(fk,true);post('club-intake',d).then(function(){toon('klaar');}).catch(function(err){fk.querySelector('.thh-cb-msg').textContent=err.message;}).then(function(){bezig(fk,false);});});
})();
</script>
	<?php
}, 30);
