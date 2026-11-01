<?php
/**
 * THH Loyalty-aanmeldpopup.
 * Elke link met data-thh-loyalty of href eindigend op #loyalty-aanmelden opent een popup met hetzelfde
 * formulier als https://loyalty.thehealthhouse.nl/signup. Versturen gaat via een eigen REST-proxy
 * (POST /thh/v1/loyalty-signup) naar loyalty.thehealthhouse.nl/api/portal (+ /api/auth voor het wachtwoord),
 * zodat er geen CORS-problemen zijn. Wachtwoorden worden niet opgeslagen of gelogd.
 */
if (!defined('ABSPATH')) return;

define('THH_LOYALTY_API', 'https://loyalty.thehealthhouse.nl/api/portal');

add_action('rest_api_init', function () {
	register_rest_route('thh/v1', '/loyalty-signup', ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function (WP_REST_Request $r) {
		$p = (array) $r->get_json_params();
		if (!empty($p['website'])) return ['ok' => true]; // honeypot
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		$k = 'thh_loy_rl_' . md5($ip);
		$n = (int) get_transient($k);
		if ($n >= 8) return new WP_Error('rate', 'Te veel aanmeldingen. Probeer het later opnieuw.', ['status' => 429]);
		set_transient($k, $n + 1, HOUR_IN_SECONDS);

		$s = function ($v, $max = 120) { return mb_substr(trim(sanitize_text_field((string) $v)), 0, $max); };
		$first = $s($p['first_name'] ?? '', 60);
		$last = $s($p['last_name'] ?? '', 80);
		$email = sanitize_email($p['email'] ?? '');
		$phone = $s($p['phone'] ?? '', 30);
		$birthday = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($p['birthday'] ?? '')) ? $p['birthday'] : null;
		$branch = $s($p['branch'] ?? '', 80);
		$pw = (string) ($p['password'] ?? '');
		if ($first === '' || $last === '') return new WP_Error('veld', 'Vul je voor- en achternaam in.', ['status' => 400]);
		if (!$email && !$phone) return new WP_Error('veld', 'Vul minimaal een e-mailadres of telefoonnummer in zodat we je op de hoogte kunnen houden.', ['status' => 400]);
		if ($pw !== '' && strlen($pw) < 6) return new WP_Error('veld', 'Wachtwoord moet minimaal 6 tekens zijn.', ['status' => 400]);

		$res = wp_remote_post(THH_LOYALTY_API, ['timeout' => 20, 'headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode([
			'action' => 'signup', 'first_name' => $first, 'last_name' => $last, 'email' => $email ?: null, 'phone' => $phone ?: null,
			'birthday' => $birthday, 'branch' => $branch ?: null,
		])]);
		if (is_wp_error($res)) return new WP_Error('net', 'Er ging iets mis. Probeer het opnieuw.', ['status' => 502]);
		$code = wp_remote_retrieve_response_code($res);
		$data = json_decode(wp_remote_retrieve_body($res), true) ?: [];
		if ($code < 200 || $code >= 300) return new WP_Error('signup', $data['error'] ?? 'Aanmelden is niet gelukt.', ['status' => $code ?: 500]);

		if ($pw !== '' && $email && !empty($data['loyalty_code'])) {
			wp_remote_post(str_replace('/api/portal', '/api/auth', THH_LOYALTY_API), ['timeout' => 15, 'headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode([
				'action' => 'set_password', 'email' => $email, 'password' => $pw, 'loyalty_code' => $data['loyalty_code'],
			])]);
		}
		return ['ok' => true, 'loyalty_code' => $data['loyalty_code'] ?? ''];
	}]);
});

add_action('wp_footer', function () {
	if (is_admin()) return;
	$api = esc_url_raw(rest_url('thh/v1/loyalty-signup'));
	$branches = [
		'Amsterdam West' => 'Amsterdam – Osdorpplein 125',
		'Amsterdam – IJburglaan 837' => 'Amsterdam – IJburglaan 837',
		'Middenwaard' => 'Heerhugowaard – Middenwaard 4C',
		'Hoofddorp' => 'Hoofddorp – Polderplein',
		'Uithoorn' => 'Team Champ – Uithoorn, Wiegerbruinlaan 81A',
	];
	?>
	<div class="thl-ov" id="thlOv" hidden>
		<div class="thl-modal" role="dialog" aria-modal="true" aria-labelledby="thlTitle">
			<button type="button" class="thl-x" aria-label="Sluiten">&times;</button>
			<div id="thlForm">
				<span class="thl-kicker">The Health House Loyalty</span>
				<h2 id="thlTitle">Meld je gratis aan</h2>
				<p class="thl-sub">Voor elke € 10 een punt. 10 punten = € 5 korting, online en in de winkel.</p>
				<form novalidate>
					<input type="text" name="website" tabindex="-1" autocomplete="off" class="thl-hp" aria-hidden="true">
					<div class="thl-row">
						<label>Voornaam *<input name="first_name" required placeholder="Jan" autocomplete="given-name"></label>
						<label>Achternaam *<input name="last_name" required placeholder="de Vries" autocomplete="family-name"></label>
					</div>
					<label>E-mail<input type="email" name="email" placeholder="jan@voorbeeld.nl" autocomplete="email"></label>
					<div class="thl-row">
						<label>Telefoon<input type="tel" name="phone" placeholder="+31 6 12345678" autocomplete="tel"></label>
						<label>Geboortedatum<input type="date" name="birthday" autocomplete="bday"></label>
					</div>
					<label>Voorkeursvestiging<select name="branch"><option value="">Kies je vestiging</option><?php foreach ($branches as $v => $t) echo '<option value="' . esc_attr($v) . '">' . esc_html($t) . '</option>'; ?></select></label>
					<div class="thl-row">
						<label>Wachtwoord <small>(optioneel, min. 6 tekens)</small><input type="password" name="password" placeholder="Kies een wachtwoord" autocomplete="new-password"></label>
						<label>Wachtwoord bevestigen<input type="password" name="password2" placeholder="Herhaal je wachtwoord" autocomplete="new-password"></label>
					</div>
					<p class="thl-err" hidden></p>
					<button type="submit" class="thl-btn">Aanmelden</button>
					<p class="thl-note">Vul minimaal een e-mailadres of telefoonnummer in. Met een wachtwoord kun je inloggen op <a href="https://loyalty.thehealthhouse.nl" target="_blank" rel="noopener">loyalty.thehealthhouse.nl</a>.</p>
				</form>
			</div>
			<div id="thlOk" hidden>
				<span class="thl-kicker">Gelukt!</span>
				<h2>Welkom bij de club</h2>
				<p class="thl-sub">Je bent aangemeld. Laat deze code zien in de winkel of gebruik hem online:</p>
				<div class="thl-code" id="thlCode"></div>
				<button type="button" class="thl-btn thl-close2">Verder winkelen</button>
			</div>
		</div>
	</div>
	<style>
	.thl-ov{position:fixed;inset:0;z-index:100050;background:rgba(10,16,40,.55);display:flex;align-items:center;justify-content:center;padding:16px}
	.thl-ov[hidden]{display:none}
	.thl-modal{position:relative;width:100%;max-width:520px;max-height:calc(100vh - 32px);overflow:auto;background:#fff;border-radius:18px;padding:28px;box-shadow:0 24px 60px rgba(0,0,0,.25);color:#111827;font-size:15px}
	.thl-x{position:absolute;top:10px;right:12px;width:36px;height:36px;border:0;background:#f3f4f6;border-radius:50%;font-size:22px;line-height:1;cursor:pointer;color:#14204A}
	.thl-kicker{display:inline-block;font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#e63946;margin-bottom:6px}
	.thl-modal h2{font-size:24px;font-weight:700;margin:0 0 6px;color:#14204A;text-transform:none;letter-spacing:-.01em}
	.thl-sub{margin:0 0 18px;color:#4b5563;font-size:14px}
	.thl-modal form{display:flex;flex-direction:column;gap:12px}
	.thl-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
	.thl-modal label{display:flex;flex-direction:column;gap:5px;font-size:13px;font-weight:600;color:#14204A}
	.thl-modal label small{font-weight:400;color:#6b7280}
	.thl-modal input,.thl-modal select{height:44px;border:1.5px solid #e5e7eb;border-radius:10px;padding:0 12px;font-size:15px;font-weight:400;color:#111827;background:#fff;width:100%;box-sizing:border-box;margin:0}
	.thl-modal input:focus,.thl-modal select:focus{outline:0;border-color:#14204A}
	.thl-hp{position:absolute!important;left:-9999px!important;height:1px!important;width:1px!important}
	.thl-btn{height:48px;border:0;border-radius:999px;background:#14204A;color:#fff;font-weight:600;font-size:15px;cursor:pointer;margin-top:4px;width:100%}
	.thl-btn:hover{background:#0d1538}.thl-btn[disabled]{opacity:.6;cursor:default}
	.thl-err{margin:0;padding:10px 12px;border-radius:10px;background:#fdecee;color:#b42318;font-size:13px}
	.thl-note{margin:0;font-size:12px;color:#6b7280}.thl-note a{color:#14204A;text-decoration:underline}
	.thl-code{font-size:26px;font-weight:800;letter-spacing:.12em;text-align:center;padding:16px;border:2px dashed #14204A;border-radius:12px;margin:6px 0 18px;color:#14204A}
	@media(max-width:520px){.thl-row{grid-template-columns:1fr}.thl-modal{padding:22px 18px}}
	</style>
	<script>
	(function(){
		var ov=document.getElementById('thlOv'); if(!ov) return;
		var form=ov.querySelector('form'), err=ov.querySelector('.thl-err'), btn=ov.querySelector('.thl-btn[type=submit]'), last=null;
		function open(e){ if(e) e.preventDefault(); last=document.activeElement; ov.hidden=false; document.documentElement.style.overflow='hidden'; setTimeout(function(){ var f=ov.querySelector('input[name=first_name]'); if(f && !document.getElementById('thlForm').hidden) f.focus(); },50); }
		function close(){ ov.hidden=true; document.documentElement.style.overflow=''; if(last && last.focus) last.focus(); }
		document.addEventListener('click',function(e){ var a=e.target.closest('[data-thh-loyalty],a[href$="#loyalty-aanmelden"]'); if(a) open(e); });
		if(location.hash==='#loyalty-aanmelden') open();
		ov.addEventListener('click',function(e){ if(e.target===ov || e.target.closest('.thl-x,.thl-close2')) close(); });
		document.addEventListener('keydown',function(e){ if(e.key==='Escape' && !ov.hidden) close(); });
		function fail(m){ err.textContent=m; err.hidden=false; }
		form.addEventListener('submit',function(e){
			e.preventDefault(); err.hidden=true;
			var d={}; new FormData(form).forEach(function(v,k){ d[k]=String(v).trim(); });
			if(!d.first_name||!d.last_name) return fail('Vul je voor- en achternaam in.');
			if(!d.email&&!d.phone) return fail('Vul minimaal een e-mailadres of telefoonnummer in zodat we je op de hoogte kunnen houden.');
			if(d.password&&d.password.length<6) return fail('Wachtwoord moet minimaal 6 tekens zijn.');
			if(d.password&&d.password!==d.password2) return fail('Wachtwoorden komen niet overeen.');
			delete d.password2;
			btn.disabled=true; btn.textContent='Bezig met aanmelden...';
			fetch(<?php echo wp_json_encode($api); ?>,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)})
				.then(function(r){ return r.json().then(function(j){ return {ok:r.ok,j:j}; }); })
				.then(function(x){
					if(!x.ok) throw new Error((x.j&&x.j.message)||'Aanmelden is niet gelukt.');
					document.getElementById('thlForm').hidden=true; document.getElementById('thlOk').hidden=false;
					var c=document.getElementById('thlCode'); if(x.j.loyalty_code){ c.textContent=x.j.loyalty_code; } else { c.style.display='none'; }
					form.reset();
				})
				.catch(function(er){ fail(er.message||'Er ging iets mis. Probeer het opnieuw.'); })
				.then(function(){ btn.disabled=false; btn.textContent='Aanmelden'; });
		});
	})();
	</script>
	<?php
}, 60);
