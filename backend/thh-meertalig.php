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
		if (!isset($known[$h]) && count($miss) < 400) $miss[$h] = $n;
		return null;
	};
	$attr_tr = function ($tag) use ($tr) {
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
		preg_match('/^\s*/u', $raw, $a);
		preg_match('/\s*$/u', $raw, $b);
		return $a[0] . htmlspecialchars($t, ENT_NOQUOTES, 'UTF-8') . $b[0];
	}, $html);
	if ($out === null) return $html; // regex-limiet: liever onvertaald dan kapot
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
	echo '<nav class="thh-lang" aria-label="Language"><!-- thh-i18n -->';
	foreach ($opts as $code => $o) {
		printf('<a href="%s" hreflang="%s" lang="%s" data-thh-nolang%s>%s</a>', esc_url($o[2]), esc_attr($code), esc_attr($code), $code === $cur ? ' aria-current="true"' : '', esc_html($o[0]));
	}
	echo '</nav>';
	echo '<style>.thh-lang{position:fixed;left:12px;bottom:12px;z-index:99990;display:flex;gap:2px;padding:3px;background:#fff;border:1px solid #ddd;border-radius:999px;box-shadow:0 2px 10px rgba(0,0,0,.12);font:600 12px/1 system-ui,sans-serif}.thh-lang a{padding:7px 9px;border-radius:999px;color:#111;text-decoration:none}.thh-lang a[aria-current]{background:#111;color:#fff}@media(max-width:767px){.thh-lang{bottom:76px}}</style>';
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
