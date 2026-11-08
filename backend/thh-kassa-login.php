<?php
/**
 * THH — Eigen inlogpagina voor de kassa
 *
 * - /kassa-login/ is een losse inlogpagina in THH-stijl (gebruikersnaam of e-mail + wachtwoord).
 * - Na inloggen gaat de medewerker direct naar /pos/ (of naar de kassapagina waar hij vandaan kwam).
 * - Wie /pos/ opent zonder ingelogd te zijn, komt op /kassa-login/ en niet meer op de gewone WordPress-login.
 * - Wie al ingelogd is en /kassa-login/ opent, gaat meteen door naar /pos/.
 * De gewone WordPress-login en de rechten van gebruikers veranderen niet.
 */
if (!defined('ABSPATH')) return;

function thh_kl_pad() {
	$p = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
	return '/' . trim((string) $p, '/');
}

function thh_kl_doel($ruw) {
	$doel = home_url('/pos/');
	if (is_string($ruw) && $ruw !== '') {
		$ruw = wp_unslash($ruw);
		$pad = parse_url($ruw, PHP_URL_PATH);
		$host = parse_url($ruw, PHP_URL_HOST);
		if ($pad && preg_match('#^/pos(/|$)#', $pad) && (!$host || $host === parse_url(home_url(), PHP_URL_HOST))) {
			$doel = home_url($pad);
		}
	}
	return $doel;
}

function thh_kl_toon($fout, $naam, $doel) {
	status_header(200);
	header('Content-Type: text/html; charset=utf-8');
	header('X-Robots-Tag: noindex, nofollow');
	$actie = esc_url(add_query_arg('redirect_to', rawurlencode(wp_parse_url($doel, PHP_URL_PATH)), home_url('/kassa-login/')));
	?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Kassa inloggen · The Health House</title>
<style>
*{box-sizing:border-box}
html,body{margin:0;height:100%}
body{font-family:Poppins,Inter,Arial,sans-serif;background:#14204a;color:#1b1f2e;display:flex;align-items:center;justify-content:center;padding:16px}
.kaart{width:100%;max-width:380px;background:#fff;border-radius:16px;padding:32px 28px;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.logo{font-weight:700;font-size:18px;letter-spacing:.04em;color:#14204a;text-align:center;margin-bottom:4px}
.sub{text-align:center;color:#5a6072;font-size:14px;margin-bottom:24px}
label{display:block;font-size:13px;color:#5a6072;margin:0 0 6px}
input[type=text],input[type=password]{width:100%;font:inherit;font-size:16px;padding:12px 14px;border:1.5px solid #c9ccd6;border-radius:10px;margin-bottom:16px}
input:focus{outline:none;border-color:#14204a}
.onthoud{display:flex;align-items:center;gap:8px;font-size:14px;color:#1b1f2e;margin-bottom:20px}
button{width:100%;font:inherit;font-size:16px;font-weight:700;padding:14px;border:0;border-radius:10px;background:#14204a;color:#fff;cursor:pointer}
button:hover{background:#0d1533}
.fout{background:#fdecee;color:#a31b2b;border-radius:10px;padding:10px 12px;font-size:14px;margin-bottom:16px}
</style>
</head>
<body>
<form class="kaart" method="post" action="<?php echo $actie; ?>">
	<div class="logo">THE HEALTH HOUSE</div>
	<div class="sub">Inloggen op de kassa</div>
	<?php if ($fout) : ?><div class="fout"><?php echo esc_html($fout); ?></div><?php endif; ?>
	<label for="log">Gebruikersnaam of e-mail</label>
	<input type="text" id="log" name="log" value="<?php echo esc_attr($naam); ?>" autocomplete="username" autocapitalize="off" required autofocus>
	<label for="pwd">Wachtwoord</label>
	<input type="password" id="pwd" name="pwd" autocomplete="current-password" required>
	<label class="onthoud"><input type="checkbox" name="rememberme" value="1" checked> Ingelogd blijven op dit apparaat</label>
	<?php wp_nonce_field('thh_kassa_login', 'thh_kl_nonce'); ?>
	<button type="submit">Inloggen</button>
</form>
</body>
</html>
	<?php
}

// Niet ingelogd op /pos → naar de kassa-login
add_action('init', function () {
	if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) return;
	$pad = thh_kl_pad();
	if (!preg_match('#^/pos(/|$)#', $pad)) return;
	if (is_user_logged_in()) return;
	nocache_headers();
	wp_safe_redirect(add_query_arg('redirect_to', rawurlencode($pad), home_url('/kassa-login/')));
	exit;
}, 1);

// De inlogpagina zelf
add_action('init', function () {
	if (thh_kl_pad() !== '/kassa-login') return;
	if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
	nocache_headers();

	$doel = thh_kl_doel(isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : '');
	if (is_user_logged_in()) { wp_safe_redirect($doel); exit; }

	$fout = '';
	$naam = '';
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$naam = isset($_POST['log']) ? sanitize_text_field(wp_unslash($_POST['log'])) : '';
		if (!isset($_POST['thh_kl_nonce']) || !wp_verify_nonce($_POST['thh_kl_nonce'], 'thh_kassa_login')) {
			$fout = 'De pagina was verlopen. Probeer het opnieuw.';
		} else {
			// Een andere plugin stuurt mislukte logins door naar /login/; hier blijft de medewerker op de kassa-login
			add_action('wp_login_failed', function () use ($naam, $doel) {
				thh_kl_toon('Gebruikersnaam of wachtwoord klopt niet.', $naam, $doel);
				exit;
			}, -1000);
			$user = wp_signon(array(
				'user_login'    => $naam,
				'user_password' => isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '',
				'remember'      => !empty($_POST['rememberme']),
			), is_ssl());
			if (is_wp_error($user)) {
				$fout = 'Gebruikersnaam of wachtwoord klopt niet.';
			} else {
				wp_safe_redirect($doel);
				exit;
			}
		}
	}

	thh_kl_toon($fout, $naam, $doel);
	exit;
}, 2);
