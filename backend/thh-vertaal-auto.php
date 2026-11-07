<?php
/**
 * THH — Automatisch vertalen van de i18n-wachtrij (DE/EN)
 *
 * De meertalige site (snippet Meertalig) verzamelt elke Nederlandse tekst die een bezoeker op /de/ of /en/ tegenkomt
 * in de tabel {prefix}thh_i18n; wat nog geen vertaling heeft, staat daar met tr = NULL. Dit snippet vertaalt die
 * wachtrij in kleine porties via de Anthropic-API, met dezelfde sleutel als "Foto tellen" (WooCommerce › Foto tellen).
 *
 * Instellingen (optie thh_vt): aan (1/0), per_run (strings per taal per beurt), dag_max (strings per dag, alle talen),
 * model. Loopt elke 5 minuten via WP-Cron; de meest recent geziene teksten eerst (dus de pagina's die bezoekers echt
 * openen). Vertalingen komen direct live zoals handmatig ingevoerde vertalingen.
 */
if (!defined('ABSPATH')) return;

if (!function_exists('thh_vt_opts')) {

function thh_vt_opts() {
	$o = get_option('thh_vt', []);
	return wp_parse_args(is_array($o) ? $o : [], ['aan' => 1, 'per_run' => 40, 'dag_max' => 3000, 'model' => 'claude-sonnet-5-5']);
}

add_filter('cron_schedules', function ($s) { $s['thh_5min'] = ['interval' => 300, 'display' => 'Elke 5 minuten (THH)']; return $s; });
add_action('init', function () {
	if (!wp_next_scheduled('thh_vt_tick')) wp_schedule_event(time() + 60, 'thh_5min', 'thh_vt_tick');
});

function thh_vt_taalnaam($lang) { return $lang === 'de' ? 'Duits' : ($lang === 'en' ? 'Engels' : $lang); }

/* Eén portie voor één taal vertalen; geeft aantal opgeslagen vertalingen terug of WP_Error */
function thh_vt_portie($lang, $n) {
	global $wpdb;
	if (!function_exists('thh_vf_opts')) return new WP_Error('key', 'Snippet Foto tellen (API-sleutel) niet actief.');
	$key = (string) (thh_vf_opts()['api_key'] ?? '');
	if ($key === '') return new WP_Error('key', 'Geen API-sleutel ingesteld.');
	$rows = $wpdb->get_results($wpdb->prepare("SELECT h, src FROM {$wpdb->prefix}thh_i18n WHERE lang=%s AND (tr IS NULL OR tr='') AND LENGTH(src) BETWEEN 2 AND 1500 ORDER BY seen DESC LIMIT %d", $lang, $n), ARRAY_A);
	if (!$rows) return 0;
	$items = [];
	foreach ($rows as $i => $r) $items[] = ['i' => $i, 'nl' => $r['src']];
	$doel = thh_vt_taalnaam($lang);
	$prompt = "Vertaal de onderstaande Nederlandse webshopteksten (supplementen, sportvoeding, vechtsportkleding) naar het " . $doel . ". "
		. "Regels: natuurlijk en commercieel, geen letterlijke vertaling; merknamen, productnamen, SKU's, getallen, eenheden (oz, g, ml, kcal), maten en prijzen onveranderd laten; "
		. ($lang === 'de' ? "aanspreekvorm 'du'; decimale komma en 'pro Stück' voor 'per stuk'; " : "British English; decimal point; 'each' for 'per stuk'; ")
		. "een tekst die begint met '/ ' is een kruimelpad: de '/ ' laten staan; teksten die al in de doeltaal staan ongewijzigd teruggeven; geen tekst toevoegen of weglaten. "
		. "Antwoord uitsluitend met JSON: {\"v\":[{\"i\":0,\"tr\":\"...\"}, ...]} met exact dezelfde i-nummers.\n\n" . wp_json_encode($items, JSON_UNESCAPED_UNICODE);
	$body = ['model' => thh_vt_opts()['model'], 'max_tokens' => 8000, 'messages' => [['role' => 'user', 'content' => $prompt]]];
	$res = wp_remote_post('https://api.anthropic.com/v1/messages', ['timeout' => 120, 'headers' => ['x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json'], 'body' => wp_json_encode($body)]);
	if (is_wp_error($res)) return $res;
	$code = (int) wp_remote_retrieve_response_code($res);
	$j = json_decode(wp_remote_retrieve_body($res), true);
	if ($code !== 200) return new WP_Error('api', 'API ' . $code . ': ' . substr((string) wp_remote_retrieve_body($res), 0, 200));
	$txt = '';
	foreach ((array) ($j['content'] ?? []) as $c) if (($c['type'] ?? '') === 'text') $txt .= $c['text'];
	$txt = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($txt)));
	$p = json_decode($txt, true);
	if (!is_array($p) || !isset($p['v']) || !is_array($p['v'])) return new WP_Error('parse', 'Onleesbaar antwoord: ' . substr($txt, 0, 200));
	$now = current_time('mysql'); $saved = 0;
	foreach ($p['v'] as $v) {
		$i = (int) ($v['i'] ?? -1); $tr = trim(wp_strip_all_tags((string) ($v['tr'] ?? '')));
		if (!isset($rows[$i]) || $tr === '') continue;
		$saved += (int) $wpdb->update("{$wpdb->prefix}thh_i18n", ['tr' => $tr, 'updated' => $now], ['h' => $rows[$i]['h'], 'lang' => $lang]);
	}
	if ($saved && function_exists('thh_i18n_flush')) thh_i18n_flush($lang);
	return $saved;
}

add_action('thh_vt_tick', function () {
	$o = thh_vt_opts();
	if (empty($o['aan'])) return;
	$dag = date('Y-m-d'); $tel = get_option('thh_vt_teller', []);
	if (!is_array($tel) || ($tel['dag'] ?? '') !== $dag) $tel = ['dag' => $dag, 'n' => 0, 'fout' => ''];
	if ($tel['n'] >= (int) $o['dag_max']) return;
	foreach (['de', 'en'] as $lang) {
		$r = thh_vt_portie($lang, max(5, min(60, (int) $o['per_run'])));
		if (is_wp_error($r)) { $tel['fout'] = $lang . ' ' . current_time('H:i') . ' ' . $r->get_error_message(); break; }
		$tel['n'] += (int) $r; $tel['laatst'] = current_time('H:i');
	}
	update_option('thh_vt_teller', $tel, false);
});

/* Stand opvragen: GET /wp-json/thh/v1/vertaal-auto (zelfde rechten als i18n) */
add_action('rest_api_init', function () {
	register_rest_route('thh/v1', '/vertaal-auto', ['methods' => 'GET', 'permission_callback' => function () { return current_user_can('manage_woocommerce'); }, 'callback' => function () {
		return ['opties' => thh_vt_opts(), 'teller' => get_option('thh_vt_teller', []), 'volgende' => wp_next_scheduled('thh_vt_tick') ? wp_date('H:i', wp_next_scheduled('thh_vt_tick')) : null];
	}]);
	register_rest_route('thh/v1', '/vertaal-auto', ['methods' => 'POST', 'permission_callback' => function () { return current_user_can('manage_woocommerce'); }, 'callback' => function ($r) {
		$o = thh_vt_opts();
		foreach (['aan', 'per_run', 'dag_max'] as $k) if ($r->get_param($k) !== null) $o[$k] = (int) $r->get_param($k);
		if ($r->get_param('model')) $o['model'] = sanitize_text_field($r->get_param('model'));
		update_option('thh_vt', $o, false);
		if ($r->get_param('nu')) do_action('thh_vt_tick');
		return ['opties' => $o, 'teller' => get_option('thh_vt_teller', [])];
	}]);
});

} // function_exists
