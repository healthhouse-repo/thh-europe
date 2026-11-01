<?php
/**
 * THH Meertalig (DE / EN) — eigen vertaallaag voor thehealthhouse.nl
 *
 * - /de/... en /en/... tonen dezelfde pagina, vertaald vanuit het Nederlands.
 * - Vertalingen staan in tabel {prefix}thh_i18n (hash van de NL-tekst -> vertaling).
 * - Teksten zonder vertaling worden verzameld (status "pending") en via REST aangevuld.
 * - NL-verkeer blijft onaangeroerd, behalve hreflang-tags en de taalkiezer (alleen als live).
 * - Zolang de optie thh_i18n_live uit staat, zien alleen beheerders /de/ en /en/.
 *
 * REST (manage_woocommerce):
 *   GET  /wp-json/thh/v1/i18n/stats
 *   GET  /wp-json/thh/v1/i18n/pending?lang=de&limit=200
 *   POST /wp-json/thh/v1/i18n            {lang, items:[{h|src, tr}]}
 *   POST /wp-json/thh/v1/i18n/live       {live:true|false}
 */
if (!defined('ABSPATH')) return;

if (!defined('THH_I18N_LANGS')) {
	define('THH_I18N_LANGS', ['de' => ['de-DE', 'de_DE', 'Deutsch', 'DE'], 'en' => ['en', 'en_GB', 'English', 'EN']]);
}

/* ---------- pure helpers (ook lokaal getest) ---------- */

function thh_i18n_norm($s) {
	$s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$s = str_replace("\xC2\xA0", ' ', $s);
	$s = preg_replace('/\s+/u', ' ', $s);
	return trim((string) $s);
}

function thh_i18n_translatable($n) {
	if ($n === '' || mb_strlen($n) > 4000) return false;
	if (!preg_match('/\p{L}{2,}/u', $n)) return false;           // geen woorden
	if (preg_match('~^(https?:)?//\S+$~', $n)) return false;     // losse url
	if (preg_match('/^[\w.+-]+@[\w-]+\.[\w.]+$/', $n)) return false; // e-mail
	if (preg_match('~^[\d\s.,%/]+MB$~', $n)) return false;           // debug-regels
	return true;
}

/** Zet een interne url om naar de taalversie, of geeft null als hij niet herschreven moet worden. */
function thh_i18n_url($url, $lang, $host) {
	$u = trim($url);
	if ($u === '' || $u[0] === '#') return null;
	if (preg_match('~^(?:https?:)?//' . preg_quote($host, '~') . '(/.*)?$~i', $u, $m)) {
		$prefix = substr($u, 0, strlen($u) - strlen($m[1] ?? ''));
		$rest = $m[1] ?? '/';
	} elseif ($u[0] === '/' && (strlen($u) < 2 || $u[1] !== '/')) {
		$prefix = '';
		$rest = $u;
	} else {
		return null;
	}
	if ($rest === '') $rest = '/';
	$path = parse_url('http://x' . $rest, PHP_URL_PATH) ?: '/';
	$query = (string) parse_url('http://x' . $rest, PHP_URL_QUERY);
	if (preg_match('~^/(wp-admin|wp-json|wp-content|wp-includes|wp-login\.php|xmlrpc\.php|wp-cron\.php|feed|pos|wcpos|de|en)(/|$)~i', $path)) return null;
	if (preg_match('~\.(jpe?g|png|webp|avif|gif|svg|ico|pdf|xml|css|js|txt|zip|mp4|woff2?)$~i', $path)) return null;
	if (strpos($query, 'wc-ajax') !== false || strpos($query, 'add-to-cart') !== false && $path === '/') return null;
	return $prefix . '/' . $lang . $rest;
}

/**
 * Vertaal een HTML-document.
 * $dict: hash => vertaling; $known: set van hashes van bestaande vertalingen (om dubbel vertalen te voorkomen)
 * $miss: wordt gevuld met hash => NL-bron van teksten zonder vertaling.
 */
function thh_i18n_html($html, $lang, $host, array $dict, array $known, array &$miss, $rewrite_links = true) {
	$tr = function ($raw) use ($dict, $known, &$miss) {
		$n = thh_i18n_norm($raw);
		if (!thh_i18n_translatable($n)) return null;
		$h = md5($n);
		if (isset($dict[$h])) return $dict[$h];
		if (isset($known[$h])) return null;
		// Vaste sjablonen: "<naam> | The Health House" en "<naam> Behaal jouw sportdoelen ..."
		foreach ([' | The Health House', ' Behaal jouw sportdoelen met de supplementen van The Health House.'] as $suf) {
			if (strlen($n) > strlen($suf) && substr($n, -strlen($suf)) === $suf) {
				$pre = substr($n, 0, -strlen($suf));
				$ps = $dict[md5($pre)] ?? $pre;
				$ss = $suf[1] === '|' ? $suf : ' ' . ($dict[md5(trim($suf))] ?? trim($suf));
				return $ps . $ss;
			}
		}
		if (count($miss) < 400) $miss[$h] = $n;
		return null;
	};
	$attr_tr = function ($tag) use ($tr) {
		if (stripos($tag, '<link') === 0) return $tag; // feed-titels e.d. zijn onzichtbaar
		$tag = preg_replace_callback('~\s(alt|title|placeholder|aria-label|data-title)="([^"]*)"~i', function ($m) use ($tr) {
			$t = $tr($m[2]);
			return $t === null ? $m[0] : ' ' . $m[1] . '="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '"';
		}, $tag);
		$is_btn = preg_match('~^<input\b[^>]*\btype="(submit|button|reset)"~i', $tag);
		$is_meta = preg_match('~^<meta\b[^>]*\b(name|property)="(description|og:title|og:description|twitter:title|twitter:description|og:image:alt)"~i', $tag);
		if ($is_btn || $is_meta) {
			$k = $is_btn ? 'value' : 'content';
			$tag = preg_replace_callback('~\s' . $k . '="([^"]*)"~i', function ($m) use ($tr, $k) {
				$t = $tr($m[1]);
				return $t === null ? $m[0] : ' ' . $k . '="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '"';
			}, $tag, 1);
		}
		return $tag;
	};
	$link = function ($tag) use ($lang, $host) {
		if (stripos($tag, 'data-thh-nolang') !== false) return $tag;
		if (preg_match('~^<link\b[^>]*\brel="(stylesheet|preload|prefetch|dns-prefetch|preconnect|icon|apple-touch-icon|pingback|EditURI|https://api\.w\.org/|alternate)"~i', $tag)) {
			if (stripos($tag, 'hreflang') !== false || stripos($tag, 'rel="alternate"') !== false) return $tag;
			return $tag;
		}
		$is_ogurl = preg_match('~^<meta\b[^>]*\bproperty="og:url"~i', $tag);
		$attrs = $is_ogurl ? 'content' : '(?:href|action)';
		// Links in data-attributen (bijv. Elementor "hele kolom is een link"), ook JSON-geëscaped.
		if (stripos($tag, ' data-') !== false && stripos($tag, $host) !== false) {
			$tag = preg_replace_callback('~(\sdata-[\w-]+=")([^"]*)"~i', function ($a) use ($lang, $host) {
				if (stripos($a[2], $host) === false) return $a[0];
				$v = preg_replace_callback('~(https?:(?:\\\\)?/(?:\\\\)?/' . preg_quote($host, '~') . ')((?:\\\\)?/)([^"&\s,}\]]*)~i', function ($m) use ($lang) {
					$path = str_replace('\\/', '/', $m[3]);
					if (preg_match('~^(wp-admin|wp-json|wp-content|wp-includes|wp-login|xmlrpc|feed|pos|wcpos|de|en)(/|$|\?)~i', $path)) return $m[0];
					if (preg_match('~\.(jpe?g|png|webp|avif|gif|svg|ico|pdf|xml|css|js|txt|zip|mp4|woff2?)($|\?)~i', $path)) return $m[0];
					return $m[1] . $m[2] . $lang . $m[2] . $m[3];
				}, $a[2]);
				return $v === null ? $a[0] : $a[1] . $v . '"';
			}, $tag) ?? $tag;
		}
		return preg_replace_callback('~\s(' . $attrs . ')="([^"]*)"~i', function ($m) use ($lang, $host) {
			$dec = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
			$new = thh_i18n_url($dec, $lang, $host);
			return $new === null ? $m[0] : ' ' . $m[1] . '="' . htmlspecialchars($new, ENT_QUOTES, 'UTF-8') . '"';
		}, $tag);
	};
	$re = '~(<script\b[^>]*>.*?</script\s*>|<style\b[^>]*>.*?</style\s*>|<svg\b.*?</svg\s*>|<textarea\b.*?</textarea\s*>|<template\b.*?</template\s*>|<!--.*?-->|<![^>]*>|</[^>]*>|<\?.*?\?>)|(<[a-zA-Z][^>]*>)|([^<]+)~s';
	$out = preg_replace_callback($re, function ($m) use ($tr, $attr_tr, $link, $rewrite_links) {
		if (isset($m[1]) && $m[1] !== '') return $m[1];
		if (isset($m[2]) && $m[2] !== '') {
			$t = $attr_tr($m[2]);
			return $rewrite_links ? $link($t) : $t;
		}
		$raw = $m[3];
		$t = $tr($raw);
		if ($t === null) return $raw;
		preg_match('/^(?:\s|&nbsp;|&#0*160;|\x{00A0})*/u', $raw, $a);
		preg_match('/(?:\s|&nbsp;|&#0*160;|\x{00A0})*$/u', $raw, $b);
		return $a[0] . htmlspecialchars($t, ENT_NOQUOTES, 'UTF-8') . $b[0];
	}, $html);
	if ($out === null) return $html; // regex-limiet: liever onvertaald dan kapot
	// Teksten die pas via JavaScript op de pagina komen (bijv. stappen in de checkout):
	// vertalingen meegeven aan de browser, die ze na het opbouwen vervangt.
	$js = [];
	if (preg_match_all('~<script\b(?![^>]*\bsrc=)(?![^>]*application/(?:ld\+)?json)[^>]*>(.*?)</script\s*>~is', $html, $sm)) {
		$code = implode("\n", $sm[1]);
		if (strlen($code) < 3000000 && preg_match_all('~"((?:[^"\\\\\n]|\\\\.){3,600})"|\'((?:[^\'\\\\\n]|\\\\.){3,600})\'~', $code, $lm, PREG_SET_ORDER)) {
			foreach ($lm as $l) {
				$single = isset($l[2]) && $l[2] !== '';
				$lit = $single ? $l[2] : $l[1];
				if (!preg_match('/\p{L}{3,}/u', $lit)) continue;
				if ($single) $lit = str_replace(["\\'", '"'], ["'", '\\"'], $lit);
				$dec = json_decode('"' . $lit . '"');
				if (!is_string($dec)) $dec = stripslashes($lit);
				foreach (preg_split('~<[^>]*>~', $dec) as $seg) {
					$n = thh_i18n_norm($seg);
					if (!thh_i18n_translatable($n)) continue;
					$h = md5($n);
					if (isset($dict[$h])) { $js[$n] = $dict[$h]; }
					elseif (!isset($known[$h]) && strpos($n, ' ') !== false && preg_match('/^[^{}();=<>$]+$/', $n) && count($miss) < 400) { $miss[$h] = $n; }
					if (count($js) > 600) break 2;
				}
			}
		}
	}
	// Altijd: vertaalkaart voor JS-teksten + links die pas in de browser ontstaan in de taal houden.
	$payload = $js ? wp_json_encode($js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) : '{}';
	$script = '<script id="thh-i18n-js">(function(){var M=' . $payload . ',LG="' . $lang . '",HO=location.host,EX=/^\/(wp-admin|wp-json|wp-content|wp-includes|wp-login\.php|xmlrpc\.php|wp-cron\.php|feed|pos|wcpos|de|en)(\/|$)/i,FE=/\.(jpe?g|png|webp|avif|gif|svg|ico|pdf|xml|css|js|txt|zip|mp4|woff2?)$/i;function n(s){return s.replace(/\s+/g," ").trim()}function fx(a){if(!a||!a.getAttribute||a.hasAttribute("data-thh-nolang"))return;var h=a.getAttribute("href");if(!h||h.charAt(0)==="#"||/^(mailto|tel|javascript):/i.test(h))return;var u;try{u=new URL(h,location.href)}catch(e){return}if(u.host!==HO||EX.test(u.pathname)||FE.test(u.pathname)||/wc-ajax|add-to-cart/.test(u.search))return;u.pathname="/"+LG+u.pathname;a.setAttribute("href",u.toString())}function t(el){if(el.nodeName==="A")fx(el);if(el.querySelectorAll)el.querySelectorAll("a[href]").forEach(fx);var w=document.createTreeWalker(el,NodeFilter.SHOW_TEXT,null),x;while(x=w.nextNode()){var p=x.parentNode;if(!p||/^(SCRIPT|STYLE|TEXTAREA)$/.test(p.nodeName))continue;var k=n(x.nodeValue);if(k&&M.hasOwnProperty(k)){var m=x.nodeValue.match(/^\s*/)[0],e=x.nodeValue.match(/\s*$/)[0];x.nodeValue=m+M[k]+e}}if(el.querySelectorAll)el.querySelectorAll("[placeholder],[aria-label],input[type=submit],button[value]").forEach(function(a){["placeholder","aria-label","value"].forEach(function(b){var v=a.getAttribute(b);if(v&&M.hasOwnProperty(n(v)))a.setAttribute(b,M[n(v)])})})}document.addEventListener("click",function(e){var a=e.target&&e.target.closest&&e.target.closest("a[href]");if(a)fx(a)},true);function r(){t(document.body)}if(document.readyState!=="loading")r();else document.addEventListener("DOMContentLoaded",r);var q=0,Q=[];new MutationObserver(function(ms){ms.forEach(function(m){m.addedNodes.forEach(function(a){Q.push(a)})});if(q)return;q=1;requestAnimationFrame(function(){q=0;var L=Q;Q=[];L.forEach(function(a){if(a.nodeType===1)t(a);else if(a.nodeType===3&&a.parentNode)t(a.parentNode)})})}).observe(document.documentElement,{childList:true,subtree:true})})();</script>';
	$pos = strripos($out, '</body>');
	$out = $pos === false ? $out . $script : substr_replace($out, $script, $pos, 0);
	$L = THH_I18N_LANGS[$lang];
	$out = preg_replace('~(<html\b[^>]*\blang=")[^"]*"~i', '${1}' . $L[0] . '"', $out, 1);
	$out = preg_replace('~(<meta\b[^>]*property="og:locale"[^>]*content=")[^"]*"~i', '${1}' . $L[1] . '"', $out, 1);
	return $out;
}

/* ---------- WordPress-integratie ---------- */

function thh_i18n_lang() { return $GLOBALS['thh_i18n_lang'] ?? 'nl'; }
function thh_i18n_live() { return (bool) get_option('thh_i18n_live', false); }
function thh_i18n_host() { return (string) parse_url(get_option('home'), PHP_URL_HOST); }

// 1. Taal bepalen en prefix uit de url halen (vóór WordPress de request parseert).
(function () {
	if (defined('WP_CLI') || (defined('DOING_CRON') && DOING_CRON)) return;
	$uri = $_SERVER['REQUEST_URI'] ?? '/';
	if (preg_match('~^/(de|en)(/|\?|$)~', $uri, $m)) {
		$GLOBALS['thh_i18n_lang'] = $m[1];
		$GLOBALS['thh_i18n_orig_uri'] = $uri;
		$_SERVER['REQUEST_URI'] = substr($uri, 3) === '' ? '/' : (substr($uri, 3)[0] === '?' ? '/' . substr($uri, 3) : substr($uri, 3));
		return;
	}
	$is_ajax = strpos($uri, 'wc-ajax=') !== false || strpos($uri, '/wp-admin/admin-ajax.php') === 0 || strpos($uri, '/wp-json/wc/store') === 0;
	if ($is_ajax && !empty($_SERVER['HTTP_REFERER'])) {
		$rp = (string) parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
		if (preg_match('~^/(de|en)(/|$)~', $rp, $m)) $GLOBALS['thh_i18n_ajax_lang'] = $GLOBALS['thh_i18n_lang'] = $m[1];
	}
})();

// 2. Tabel
add_action('init', function () {
	if (get_option('thh_i18n_db') === '1') return;
	global $wpdb;
	$t = $wpdb->prefix . 'thh_i18n';
	$wpdb->query("CREATE TABLE IF NOT EXISTS `$t` (
		h CHAR(32) NOT NULL, lang VARCHAR(5) NOT NULL, src MEDIUMTEXT NOT NULL, tr MEDIUMTEXT NULL,
		url VARCHAR(255) NULL, seen DATETIME NULL, updated DATETIME NULL,
		PRIMARY KEY (h, lang), KEY lang_tr (lang, h)
	) " . $wpdb->get_charset_collate());
	update_option('thh_i18n_db', '1', true);
}, 1);

function thh_i18n_dict($lang) {
	static $c = [];
	if (isset($c[$lang])) return $c[$lang];
	$d = wp_cache_get('dict_' . $lang, 'thh_i18n');
	if (!is_array($d)) {
		global $wpdb;
		$rows = $wpdb->get_results($wpdb->prepare("SELECT h, tr FROM {$wpdb->prefix}thh_i18n WHERE lang=%s AND tr IS NOT NULL AND tr<>''", $lang), ARRAY_N);
		$dict = []; $known = [];
		foreach ((array) $rows as $r) { $dict[$r[0]] = $r[1]; $known[md5(thh_i18n_norm($r[1]))] = 1; }
		$d = [$dict, $known];
		wp_cache_set('dict_' . $lang, $d, 'thh_i18n', 6 * HOUR_IN_SECONDS);
	}
	return $c[$lang] = $d;
}

function thh_i18n_flush($lang) { wp_cache_delete('dict_' . $lang, 'thh_i18n'); }

function thh_i18n_store_missing($lang, array $miss, $url) {
	if (!$miss) return;
	global $wpdb;
	$now = current_time('mysql');
	$vals = []; $args = [];
	foreach (array_slice($miss, 0, 400, true) as $h => $src) {
		$vals[] = '(%s,%s,%s,%s,%s)';
		array_push($args, $h, $lang, $src, substr($url, 0, 255), $now);
	}
	$wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}thh_i18n (h,lang,src,url,seen) VALUES " . implode(',', $vals), $args));
}

// 3. Niet live? Dan alleen beheerders. Anders terug naar NL.
add_action('template_redirect', function () {
	$lang = thh_i18n_lang();
	if ($lang === 'nl' || !empty($GLOBALS['thh_i18n_ajax_lang'])) return;
	if (!thh_i18n_live() && !current_user_can('manage_woocommerce')) {
		$GLOBALS['thh_i18n_noprefix'] = true;
		nocache_headers();
		wp_redirect(home_url($_SERVER['REQUEST_URI']), 302);
		exit;
	}
	remove_action('template_redirect', 'redirect_canonical');
	add_filter('show_admin_bar', '__return_false');
	ob_start(function ($html) use ($lang) {
		if (stripos($html, '<html') === false) return $html;
		[$dict, $known] = thh_i18n_dict($lang);
		$miss = [];
		$out = thh_i18n_html($html, $lang, thh_i18n_host(), $dict, $known, $miss);
		$GLOBALS['thh_i18n_miss'] = $miss;
		return $out;
	});
}, 0);

add_action('shutdown', function () {
	if (!empty($GLOBALS['thh_i18n_miss'])) {
		thh_i18n_store_missing(thh_i18n_lang(), $GLOBALS['thh_i18n_miss'], $GLOBALS['thh_i18n_orig_uri'] ?? '');
	}
}, 1);

// 4. Redirects en Woo-urls in de juiste taal houden.
function thh_i18n_prefix_url($url) {
	$lang = thh_i18n_lang();
	if ($lang === 'nl' || !empty($GLOBALS['thh_i18n_noprefix']) || is_admin() && !wp_doing_ajax()) return $url;
	$new = thh_i18n_url($url, $lang, thh_i18n_host());
	return $new ?? $url;
}
add_filter('wp_redirect', 'thh_i18n_prefix_url', 99);
foreach (['woocommerce_get_checkout_order_received_url', 'woocommerce_get_cart_url', 'woocommerce_get_checkout_url', 'woocommerce_get_return_url'] as $f) {
	add_filter($f, 'thh_i18n_prefix_url', 99);
}
add_filter('woocommerce_cart_fragment_name', function ($n) { return thh_i18n_lang() === 'nl' ? $n : $n . '_' . thh_i18n_lang(); });

// 5. AJAX-fragmenten (mini-cart, checkout) vertalen.
function thh_i18n_fragments($frags) {
	$lang = thh_i18n_lang();
	if ($lang === 'nl' || !is_array($frags)) return $frags;
	[$dict, $known] = thh_i18n_dict($lang);
	$miss = [];
	foreach ($frags as $k => $v) {
		if (is_string($v)) $frags[$k] = thh_i18n_html($v, $lang, thh_i18n_host(), $dict, $known, $miss);
	}
	$GLOBALS['thh_i18n_miss'] = ($GLOBALS['thh_i18n_miss'] ?? []) + $miss;
	return $frags;
}
add_filter('woocommerce_add_to_cart_fragments', 'thh_i18n_fragments', 999);
add_filter('woocommerce_update_order_review_fragments', 'thh_i18n_fragments', 999);

// Losse Woo-meldingen (ook in JS-teksten en AJAX-antwoorden): alleen opzoeken.
add_filter('gettext', function ($t, $text, $domain) {
	if (thh_i18n_lang() === 'nl' || is_admin() && !wp_doing_ajax()) return $t;
	if ($domain !== 'woocommerce' && $domain !== 'mollie-payments-for-woocommerce') return $t;
	$n = thh_i18n_norm($t);
	if (!thh_i18n_translatable($n)) return $t;
	$d = thh_i18n_dict(thh_i18n_lang())[0];
	$h = md5($n);
	if (isset($d[$h])) return $d[$h];
	if (wp_doing_ajax() && count($GLOBALS['thh_i18n_miss'] ?? []) < 400) $GLOBALS['thh_i18n_miss'][$h] = $n;
	return $t;
}, 999, 3);

// Taal van de bestelling vastleggen (voor latere klantmails / rapportage).
add_action('woocommerce_checkout_create_order', function ($order) {
	if (thh_i18n_lang() !== 'nl') $order->update_meta_data('_thh_lang', thh_i18n_lang());
});

// 6. hreflang + taalkiezer.
function thh_i18n_nl_url() {
	$uri = $_SERVER['REQUEST_URI'] ?? '/';
	$uri = preg_replace('~[?&](utm_[a-z]+|fbclid|gclid)=[^&]*~', '', $uri);
	return home_url($uri);
}
function thh_i18n_visible() { return thh_i18n_live() || current_user_can('manage_woocommerce'); }

add_action('wp_head', function () {
	if (!thh_i18n_visible() || is_404() || is_search() || is_cart() || is_checkout() || is_account_page()) return;
	$nl = thh_i18n_nl_url();
	$path = substr($nl, strlen(home_url()));
	echo '<link rel="alternate" hreflang="nl" href="' . esc_url($nl) . '" data-thh-nolang>' . "\n";
	echo '<link rel="alternate" hreflang="nl-BE" href="' . esc_url($nl) . '" data-thh-nolang>' . "\n";
	foreach (THH_I18N_LANGS as $code => $L) {
		echo '<link rel="alternate" hreflang="' . esc_attr($code) . '" href="' . esc_url(home_url('/' . $code . ($path ?: '/'))) . '" data-thh-nolang>' . "\n";
	}
	echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($nl) . '" data-thh-nolang>' . "\n";
}, 2);

add_action('wp_footer', function () {
	if (!thh_i18n_visible() || is_admin()) return;
	$path = substr(thh_i18n_nl_url(), strlen(home_url())) ?: '/';
	$cur = thh_i18n_lang();
	$opts = ['nl' => ['NL', 'Nederlands', home_url($path)]];
	foreach (THH_I18N_LANGS as $code => $L) $opts[$code] = [$L[3], $L[2], home_url('/' . $code . $path)];
	$flags = [
		'nl' => '<svg viewBox="0 0 30 20" aria-hidden="true"><path fill="#AE1C28" d="M0 0h30v6.7H0z"/><path fill="#fff" d="M0 6.7h30v6.6H0z"/><path fill="#21468B" d="M0 13.3h30V20H0z"/></svg>',
		'de' => '<svg viewBox="0 0 30 20" aria-hidden="true"><path d="M0 0h30v6.7H0z"/><path fill="#D00" d="M0 6.7h30v6.6H0z"/><path fill="#FFCE00" d="M0 13.3h30V20H0z"/></svg>',
		'en' => '<svg viewBox="0 0 60 40" aria-hidden="true"><path fill="#012169" d="M0 0h60v40H0z"/><path stroke="#fff" stroke-width="8" d="M0 0l60 40M60 0L0 40"/><path stroke="#C8102E" stroke-width="4" d="M0 0l60 40M60 0L0 40"/><path stroke="#fff" stroke-width="12" d="M30 0v40M0 20h60"/><path stroke="#C8102E" stroke-width="7" d="M30 0v40M0 20h60"/></svg>',
	];
	echo '<div class="thh-lang" data-thh-nolang><!-- thh-i18n --><button type="button" class="thh-lang-btn" aria-haspopup="true" aria-expanded="false" aria-label="Taal / Language"><span class="thh-flag">' . ($flags[$cur] ?? '') . '</span><span class="thh-lang-code">' . esc_html($opts[$cur][0] ?? strtoupper($cur)) . '</span><svg class="thh-chev" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button><nav class="thh-lang-menu" aria-label="Language">';
	foreach ($opts as $code => $o) {
		printf('<a href="%s" hreflang="%s" lang="%s" data-thh-nolang%s><span class="thh-flag">%s</span>%s</a>', esc_url($o[2]), esc_attr($code), esc_attr($code), $code === $cur ? ' aria-current="true"' : '', $flags[$code] ?? '', esc_html($o[1]));
	}
	echo '</nav></div>';
	echo '<style>.thh-lang{position:fixed;left:12px;bottom:12px;z-index:99990;font:600 14px/1 inherit}.thh-lang.in-header{position:relative;left:auto;bottom:auto;z-index:50;display:flex;align-items:center}.thh-lang-btn{display:flex;align-items:center;gap:6px;background:none;border:0;padding:6px 4px;color:#fff;cursor:pointer;font:inherit;font-weight:500;font-size:15px;line-height:1}.thh-lang:not(.in-header) .thh-lang-btn{background:#fff;color:#111;border:1px solid #ddd;border-radius:999px;padding:7px 10px;box-shadow:0 2px 10px rgba(0,0,0,.12)}.thh-flag{display:inline-flex;width:22px;height:15px;border-radius:3px;overflow:hidden;flex:none;box-shadow:0 0 0 1px rgba(0,0,0,.08)}.thh-flag svg{width:100%;height:100%;display:block}.thh-chev{transition:transform .15s}.thh-lang.open .thh-chev{transform:rotate(180deg)}.thh-lang-menu{display:none;position:absolute;right:0;top:calc(100% + 10px);min-width:180px;background:#fff;border-radius:12px;box-shadow:0 14px 34px rgba(0,0,0,.18);padding:6px;z-index:100000}.thh-lang:not(.in-header) .thh-lang-menu{right:auto;left:0;top:auto;bottom:calc(100% + 8px)}.thh-lang.open .thh-lang-menu{display:block}.thh-lang-menu a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;color:#111827;text-decoration:none;font-size:14px;font-weight:500}.thh-lang-menu a:hover{background:#f3f4f6}.thh-lang-menu a[aria-current]{font-weight:700}@media(max-width:767px){.thh-lang:not(.in-header){bottom:76px}.thh-lang.in-header .thh-lang-code{display:none}}.thh-lang .thh-lang-btn,.thh-lang .thh-lang-btn:hover,.thh-lang .thh-lang-btn:focus,.thh-lang .thh-lang-btn:active{background:transparent!important;color:#fff!important;border:0!important;box-shadow:none!important;outline:0}.thh-lang.in-header .thh-lang-btn:hover,.thh-lang.in-header.open .thh-lang-btn{background:rgba(255,255,255,.12)!important;border-radius:8px!important}.thh-lang:not(.in-header) .thh-lang-btn,.thh-lang:not(.in-header) .thh-lang-btn:hover{background:#fff!important;color:#111!important;border:1px solid #ddd!important}.thh-lang .thh-lang-menu a,.thh-lang .thh-lang-menu a:visited,.thh-lang .thh-lang-menu a:focus{color:#111827!important;background:transparent!important;text-decoration:none!important}.thh-lang .thh-lang-menu a{display:flex!important;width:100%!important;box-sizing:border-box!important}.thh-lang .thh-lang-menu a:hover{color:#14204A!important;background:#f3f4f6!important}.thh-lang .thh-lang-menu a[aria-current]{color:#14204A!important;background:#eef1f8!important}</style>';
	echo '<script>(function(){var w=document.querySelector(".thh-lang");if(!w)return;var b=w.querySelector(".thh-lang-btn");b.addEventListener("click",function(e){e.stopPropagation();var o=w.classList.toggle("open");b.setAttribute("aria-expanded",o?"true":"false");});document.addEventListener("click",function(e){if(!w.contains(e.target)){w.classList.remove("open");b.setAttribute("aria-expanded","false");}});document.addEventListener("keydown",function(e){if(e.key==="Escape")w.classList.remove("open");});function place(){var icons=document.querySelector(".thh-xxlh-icons");if(!icons)return false;var cart=icons.querySelector("a[href*=\"winkelwagen\"],a[href*=\"/cart\"],.thh-xxlh-cart");if(cart&&cart.parentNode===icons){icons.insertBefore(w,cart);}else{icons.appendChild(w);}w.classList.add("in-header");return true;}if(!place()){document.addEventListener("DOMContentLoaded",place);}})();</script>';
}, 50);

// 7. REST voor beheer van vertalingen.
add_action('rest_api_init', function () {
	$can = function () { return current_user_can('manage_woocommerce'); };
	$lang_ok = function ($l) { return isset(THH_I18N_LANGS[$l]); };
	register_rest_route('thh/v1', '/i18n/stats', ['methods' => 'GET', 'permission_callback' => $can, 'callback' => function () {
		global $wpdb;
		$rows = $wpdb->get_results("SELECT lang, SUM(tr IS NOT NULL AND tr<>'') done, SUM(tr IS NULL OR tr='') pending FROM {$wpdb->prefix}thh_i18n GROUP BY lang", ARRAY_A);
		return ['live' => thh_i18n_live(), 'langs' => array_keys(THH_I18N_LANGS), 'counts' => $rows];
	}]);
	register_rest_route('thh/v1', '/i18n/pending', ['methods' => 'GET', 'permission_callback' => $can, 'callback' => function ($r) use ($lang_ok) {
		global $wpdb;
		$lang = (string) $r['lang'];
		if (!$lang_ok($lang)) return new WP_Error('lang', 'onbekende taal', ['status' => 400]);
		$lim = min(500, max(1, (int) ($r['limit'] ?: 200)));
		return $wpdb->get_results($wpdb->prepare("SELECT h, src, url FROM {$wpdb->prefix}thh_i18n WHERE lang=%s AND (tr IS NULL OR tr='') ORDER BY seen DESC LIMIT %d", $lang, $lim), ARRAY_A);
	}]);
	register_rest_route('thh/v1', '/i18n', ['methods' => 'POST', 'permission_callback' => $can, 'callback' => function ($r) use ($lang_ok) {
		global $wpdb;
		$lang = (string) $r['lang'];
		if (!$lang_ok($lang)) return new WP_Error('lang', 'onbekende taal', ['status' => 400]);
		$n = 0; $now = current_time('mysql');
		foreach ((array) $r['items'] as $it) {
			$tr = isset($it['tr']) ? trim(wp_strip_all_tags((string) $it['tr'])) : '';
			if (!empty($it['src'])) { $src = thh_i18n_norm((string) $it['src']); $h = md5($src); }
			elseif (!empty($it['h']) && preg_match('/^[a-f0-9]{32}$/', $it['h'])) { $h = $it['h']; $src = null; }
			else continue;
			if ($src === null) {
				$n += (int) $wpdb->update("{$wpdb->prefix}thh_i18n", ['tr' => $tr, 'updated' => $now], ['h' => $h, 'lang' => $lang]);
			} else {
				$n += (int) $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}thh_i18n (h,lang,src,tr,updated) VALUES (%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE tr=VALUES(tr), updated=VALUES(updated)", $h, $lang, $src, $tr, $now)) ? 1 : 0;
			}
		}
		thh_i18n_flush($lang);
		if (function_exists('do_action')) do_action('thh_i18n_updated', $lang);
		return ['saved' => $n];
	}]);
	register_rest_route('thh/v1', '/i18n/live', ['methods' => 'POST', 'permission_callback' => $can, 'callback' => function ($r) {
		update_option('thh_i18n_live', $r['live'] ? 1 : 0, true);
		return ['live' => thh_i18n_live()];
	}]);
});

// 8. Na nieuwe vertalingen (en live): Kinsta-cache legen zodat /de/ en /en/ direct bijgewerkt zijn.
add_action('thh_i18n_updated', function () {
	if (!thh_i18n_live()) return;
	global $kinsta_cache;
	if (isset($kinsta_cache) && isset($kinsta_cache->kinsta_cache_purge) && method_exists($kinsta_cache->kinsta_cache_purge, 'purge_complete_caches')) {
		$kinsta_cache->kinsta_cache_purge->purge_complete_caches();
	}
});
