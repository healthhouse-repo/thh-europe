<?php
/**
 * THH Keuzehulp-popup (sitebreed).
 * Openen: elke link met data-thh-keuzehulp of href eindigend op #keuzehulp (of URL met #keuzehulp).
 * 5 vragen (doel, frequentie, soort training, voeding, budget) -> persoonlijk pakket:
 * per onderdeel een topkeuze + alternatief, live uit WooCommerce (op voorraad, met foto, best verkocht).
 * REST: POST /thh/v1/keuzehulp-advies {doel, freq, sport, dieet, budget}
 */
if (!defined('ABSPATH')) return;

function thh_kp_cat_ids($slugs) {
	$ids = [];
	foreach ((array) $slugs as $s) { $t = get_term_by('slug', $s, 'product_cat'); if ($t) $ids[] = $t->slug; }
	return $ids;
}

/* Prijs van leverbare, normale varianten (samples/proefzakjes tellen niet mee). Geeft [nu, normaal, vanaf]. */
function thh_kp_prijs($p) {
	if (!$p->is_type('variable')) return [(float) $p->get_price(), (float) $p->get_regular_price(), false];
	$best = null; $prices = [];
	foreach ($p->get_children() as $vid) {
		$v = wc_get_product($vid);
		if (!$v || !$v->is_purchasable() || !$v->is_in_stock() || (float) $v->get_price() <= 0) continue;
		if (preg_match('/sample|proef|tester|30\\s*gram|1\\s*portie|single serv/i', implode(' ', $v->get_attributes()) . ' ' . $v->get_name())) continue;
		$prices[] = (float) $v->get_price();
		if (!$best || (float) $v->get_price() < (float) $best->get_price()) $best = $v;
	}
	if (!$best) return [(float) $p->get_variation_price('min'), (float) $p->get_variation_regular_price('min'), true];
	return [(float) $best->get_price(), (float) $best->get_regular_price(), count(array_unique($prices)) > 1];
}

/* Kies producten uit categorieën: op voorraad, met foto, gesorteerd op verkopen. */
function thh_kp_pick($cats, $opts = []) {
	$uit_merk = function_exists('thh_home_merk_uit') ? thh_home_merk_uit() : ['jennah'];
	$args = ['status' => 'publish', 'stock_status' => 'instock', 'visibility' => 'catalog', 'limit' => 40, 'category' => thh_kp_cat_ids($cats),
		'orderby' => 'meta_value_num', 'meta_key' => 'total_sales', 'order' => 'DESC', 'return' => 'objects'];
	$ps = wc_get_products($args);
	if (!empty($opts['pref'])) {
		$pref = [];
		foreach ((array) $opts['pref'] as $pid) { $pp = wc_get_product($pid); if ($pp && $pp->is_visible() && $pp->is_in_stock() && $pp->get_image_id()) $pref[] = $pp; }
		$ps = array_merge($pref, $ps);
	}
	$niet = '/sample|proef|bundel|bundle|pakket|cadeau|dagdeal|shaker|t-shirt|pet |kipfilet|reep|bar\b|wafel|chips|spread|jerky' . (!empty($opts['niet']) ? '|' . $opts['niet'] : '') . '/i';
	$moet = !empty($opts['moet']) ? $opts['moet'] : '';
	$out = [];
	foreach ($ps as $p) {
		if (!$p->get_image_id()) continue;
		if (thh_kp_prijs($p)[0] < 5) continue;
		if (in_array($p->get_id(), array_map(function ($x) { return $x->get_id(); }, $out), true)) continue;
		$is_pref = !empty($opts['pref']) && in_array($p->get_id(), (array) $opts['pref'], true);
		if (!$is_pref && preg_match($niet, $p->get_name())) continue;
		if (!$is_pref && $moet && !preg_match($moet, $p->get_name() . ' ' . implode(' ', wp_get_post_terms($p->get_id(), 'product_cat', ['fields' => 'slugs'])))) continue;
		$merk = wp_get_post_terms($p->get_id(), 'product_brand', ['fields' => 'slugs']);
		if (!is_wp_error($merk) && array_intersect($merk, $uit_merk)) continue;
		$out[] = $p;
		if (count($out) >= 2) break;
	}
	return $out;
}

function thh_kp_card($p) {
	$img = wp_get_attachment_image_url($p->get_image_id(), 'woocommerce_thumbnail');
	$var = $p->is_type('variable');
	list($prijs, $reg, $vanaf) = thh_kp_prijs($p);
	$merk = wp_get_post_terms($p->get_id(), 'product_brand', ['fields' => 'names']);
	return [
		'id' => $p->get_id(), 'naam' => html_entity_decode($p->get_name(), ENT_QUOTES, 'UTF-8'), 'merk' => (!is_wp_error($merk) && $merk) ? $merk[0] : '',
		'url' => $p->get_permalink(), 'img' => $img, 'prijs' => number_format($prijs, 2, ',', '.'),
		'van' => ($reg > $prijs) ? number_format($reg, 2, ',', '.') : '',
		'vanaf' => $vanaf,
		'direct' => !$var && $p->is_purchasable() && $p->is_in_stock(),
		'varianten' => $var ? thh_kp_varianten($p) : [],
	];
}

/* Leverbare varianten (zonder samples) voor de keuzelijst; meest verkochte/goedkoopste eerst. */
function thh_kp_varianten($p) {
	$out = []; $seen = [];
	foreach ($p->get_children() as $vid) {
		$v = wc_get_product($vid);
		if (!$v || !$v->is_purchasable() || !$v->is_in_stock() || (float) $v->get_price() <= 0) continue;
		$attrs = array_values(array_filter(array_map('strval', $v->get_attributes())));
		$lbl = [];
		foreach ($v->get_attributes() as $tax => $val) {
			if ($val === '') continue;
			$t = taxonomy_exists($tax) ? get_term_by('slug', $val, $tax) : null;
			$lbl[] = $t ? $t->name : $val;
		}
		$label = html_entity_decode(implode(' · ', $lbl), ENT_QUOTES, 'UTF-8');
		if (isset($seen[$label])) continue; $seen[$label] = 1;
		if (preg_match('/sample|proef|tester|30\\s*gram|1\\s*portie|single serv/i', $label . ' ' . $v->get_name())) continue;
		$out[] = ['id' => $v->get_id(), 'label' => $label ?: $v->get_name(), 'prijs' => number_format((float) $v->get_price(), 2, ',', '.'), 'p' => (float) $v->get_price(), 's' => (int) $v->get_total_sales()];
	}
	usort($out, function ($a, $b) { return [$a['p'], -$a['s']] <=> [$b['p'], -$b['s']]; });
	// standaard: goedkoopste maat, en daarbinnen de best verkochte smaak
	return array_map(function ($x) { unset($x['p'], $x['s']); return $x; }, array_slice($out, 0, 60));
}

/* Vertaling van advies-teksten (gebruikt het woordenboek van de meertalige laag, snippet #446). */
function thh_kp_t($txt, $lang) {
	if (!$lang || $lang === 'nl' || !function_exists('thh_i18n_dict') || !function_exists('thh_i18n_norm')) return $txt;
	list($dict) = thh_i18n_dict($lang);
	$n = thh_i18n_norm($txt);
	if (isset($dict[md5($n)])) return $dict[md5($n)];
	$parts = preg_split('/(?<=[.!?])\s+/', $n);
	if (count($parts) < 2) return $txt;
	$out = [];
	foreach ($parts as $p) { $out[] = $dict[md5(thh_i18n_norm($p))] ?? $p; }
	return implode(' ', $out);
}

function thh_kp_advies($a) {
	$doel = $a['doel'] ?? 'spier'; $freq = $a['freq'] ?? 'mid'; $sport = $a['sport'] ?? 'kracht'; $dieet = $a['dieet'] ?? 'geen'; $budget = $a['budget'] ?? 'basis';
	$eiwit_cats = ['eiwitten']; $eiwit_opts = ['niet' => 'weight ?gainer|mass|vegan|plant|drink|lemonade|milkshake|pudding|pindakaas|peanut', 'pref' => [45196, 49348, 49356]];
	if ($dieet === 'vegan') { $eiwit_cats = ['eiwitten-vegan']; $eiwit_opts = ['niet' => 'drink|reep|bar']; }
	if ($dieet === 'lactose') { $eiwit_cats = ['eiwitten-isolaat', 'clear-whey']; $eiwit_opts = ['pref' => [50068, 49357], 'niet' => 'drink|lemonade']; }

	$S = []; // [titel, waarom, cats, opts]
	$eiwit_waarom = [
		'spier' => 'Genoeg eiwit is de basis voor spieropbouw. Een shake na de training maakt het makkelijk om aan je dagelijkse hoeveelheid te komen.',
		'afvallen' => 'Eiwit houdt je langer verzadigd en helpt je spiermassa te behouden terwijl je minder eet.',
		'fit' => 'Een shake is een makkelijke manier om genoeg eiwit binnen te krijgen, ook op drukke dagen.',
		'duur' => 'Na een lange training helpt eiwit bij het herstel van je spieren.',
		'herstel' => 'Eiwit helpt je lichaam herstellen na inspanning.',
	];
	if ($dieet === 'lactose') $eiwit_waarom[$doel] .= ' Isolaat en clear whey bevatten nauwelijks lactose.';
	if ($dieet === 'vegan') $eiwit_waarom[$doel] .= ' Deze shakes zijn volledig plantaardig.';

	switch ($doel) {
		case 'afvallen':
			$S[] = ['Eiwitshake', $eiwit_waarom['afvallen'], $dieet === 'geen' ? ['eiwitten-isolaat', 'clear-whey', 'eiwitten'] : $eiwit_cats, $eiwit_opts];
			$S[] = ['Fatburner', 'Geeft extra energie en ondersteunt je als je wilt afvallen. Werkt alleen samen met goed eten en bewegen.', ['fatburners'], ['niet' => 'thee|tea|drink', 'pref' => [49506, 92541]]];
			if ($budget === 'compleet') $S[] = ['L-carnitine', 'Populair bij afvallen, zonder cafeïne. Goed te combineren met een fatburner.', ['l-carnitine-2'], ['pref' => [57011, 92562]]];
			$S[] = ['Multivitamine', 'Als je minder eet, krijg je ook minder vitamines binnen. Een multivitamine vult dat aan.', ['multivitamine', 'vitamines'], ['moet' => '/multi/i', 'pref' => [45972, 49498], 'niet' => 'gumm|bruis|vrouw']];
			break;
		case 'fit':
			$S[] = ['Multivitamine', 'Een goede basis voor iedereen die actief is: alle belangrijke vitamines en mineralen in één.', ['multivitamine', 'vitamines'], ['moet' => '/multi/i', 'pref' => [45972, 49498], 'niet' => 'gumm|bruis|vrouw']];
			$S[] = ['Omega-3', 'Visolie met EPA en DHA, goed voor hart en hersenen. Veel mensen eten te weinig vette vis.', ['omega-3'], ['pref' => [45968, 49497]]];
			$S[] = ['Eiwitshake', $eiwit_waarom['fit'], $eiwit_cats, $eiwit_opts];
			if ($budget === 'compleet') $S[] = ['Magnesium', 'Helpt bij ontspanning van je spieren en een goede nachtrust.', ['magnesium'], ['pref' => [49500, 56165]]];
			break;
		case 'duur':
			$S[] = ['Elektrolyten', 'Bij lang of intensief sporten verlies je veel zout via zweet. Elektrolyten vullen dat aan.', ['elektrolyten', 'hydratie'], ['pref' => [49453]]];
			$S[] = ['EAA', 'Essentiële aminozuren voor tijdens of na de training, licht en snel opgenomen.', ['eaa'], ['pref' => [57007, 92520]]];
			$S[] = ['Eiwitshake', $eiwit_waarom['duur'], $eiwit_cats, $eiwit_opts];
			if ($budget === 'compleet' || $sport === 'vecht') $S[] = ['Creatine', 'Helpt bij korte, explosieve inspanningen, zoals sprints en combinaties.', ['creatine'], ['niet' => 'gumm', 'pref' => [45304, 50071]]];
			break;
		case 'herstel':
			$S[] = ['Omega-3', 'Visolie met EPA en DHA, een goede basis voor je gezondheid.', ['omega-3'], ['pref' => [45968, 49497]]];
			$S[] = ['Magnesium', 'Helpt bij ontspanning van je spieren en een goede nachtrust.', ['magnesium'], ['pref' => [49500, 56165]]];
			$S[] = ['Gewrichten', 'Voor wie veel traint of last heeft van stijve gewrichten, bijvoorbeeld met collageen of glucosamine.', ['joints-gewrichten'], ['pref' => [49943, 49912]]];
			if ($budget === 'compleet') $S[] = ['Slaap en herstel', 'Een goede nachtrust is het belangrijkste deel van je herstel.', ['slaap', 'nachtrust-en-herstel'], ['pref' => [46857, 49540]]];
			break;
		default: // spier
			$S[] = ['Eiwitshake', $eiwit_waarom['spier'], $eiwit_cats, $eiwit_opts];
			$S[] = ['Creatine', 'Het best onderzochte supplement voor kracht en spiermassa. Neem dagelijks 3 tot 5 gram.', ['creatine-monohydraat', 'creatine'], ['niet' => 'gumm|hmb|stack', 'pref' => [45304, 50071]]];
			if ($freq !== 'laag' || $budget === 'compleet') $S[] = ['Pre-workout', 'Voor extra energie en focus tijdens je training. Neem hem niet te laat op de dag.', ['pre-workout'], ['niet' => 'caps|capsul|tablet|shot|o\\.r\\.s|rehydrat', 'pref' => [47127, 56361]]];
			if ($budget === 'compleet') $S[] = ['Multivitamine', 'Als je veel traint, heb je meer vitamines en mineralen nodig.', ['multivitamine', 'vitamines'], ['moet' => '/multi/i', 'pref' => [45972, 49498], 'niet' => 'gumm|bruis|vrouw']];
	}
	if ($sport === 'vecht' && $doel !== 'duur') $S[] = ['Elektrolyten', 'Bij vechtsport zweet je veel. Elektrolyten helpen je fris te blijven tijdens lange trainingen.', ['elektrolyten', 'hydratie'], ['pref' => [49453]]];

	$secties = []; $gezien = [];
	foreach ($S as [$titel, $waarom, $cats, $opts]) {
		$ps = array_values(array_filter(thh_kp_pick($cats, $opts), function ($p) use (&$gezien) { return !isset($gezien[$p->get_id()]); }));
		if (!$ps) continue;
		foreach ($ps as $p) $gezien[$p->get_id()] = 1;
		$secties[] = ['titel' => $titel, 'waarom' => $waarom, 'producten' => array_map('thh_kp_card', $ps)];
	}
	$intro = [
		'spier' => 'Voor spieropbouw draait het om genoeg eiwit, goed trainen en voldoende rust. Deze producten helpen je daarbij.',
		'afvallen' => 'Bij afvallen is je voeding het belangrijkst. Deze producten maken het makkelijker om vol te houden.',
		'fit' => 'Voor een fitter gevoel begin je met een goede basis. Hiermee zit je goed.',
		'duur' => 'Bij duursport en lange trainingen zijn vocht, zout en herstel het belangrijkst.',
		'herstel' => 'Voor herstel en gezondheid kies je voor een paar goede basisproducten.',
	][$doel] ?? '';
	return ['intro' => $intro, 'secties' => $secties];
}

add_action('rest_api_init', function () {
	register_rest_route('thh/v1', '/keuzehulp-advies', ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function (WP_REST_Request $r) {
		$p = (array) $r->get_json_params();
		$ok = ['doel' => ['spier', 'afvallen', 'fit', 'duur', 'herstel'], 'freq' => ['laag', 'mid', 'hoog'], 'sport' => ['kracht', 'vecht', 'cardio', 'mix'], 'dieet' => ['geen', 'lactose', 'vegan'], 'budget' => ['basis', 'compleet']];
		$a = [];
		foreach ($ok as $k => $vals) $a[$k] = in_array($p[$k] ?? '', $vals, true) ? $p[$k] : $vals[0];
		$lang = in_array($p['lang'] ?? '', ['de', 'en'], true) ? $p['lang'] : 'nl';
		$key = 'thh_kp6_' . md5(wp_json_encode($a) . $lang);
		$res = get_transient($key);
		if (!$res) {
			$res = thh_kp_advies($a);
			if ($lang !== 'nl') {
				$res['intro'] = thh_kp_t($res['intro'], $lang);
				foreach ($res['secties'] as &$sec) {
					$sec['titel'] = thh_kp_t($sec['titel'], $lang);
					$sec['waarom'] = thh_kp_t($sec['waarom'], $lang);
					foreach ($sec['producten'] as &$pp) {
						$pp['naam'] = thh_kp_t($pp['naam'], $lang);
						if (function_exists('thh_i18n_url')) { $nu = thh_i18n_url($pp['url'], $lang, thh_i18n_host()); if ($nu) $pp['url'] = $nu; }
						foreach ($pp['varianten'] as &$vv) { $vv['label'] = implode(' · ', array_map(function ($x) use ($lang) { return thh_kp_t($x, $lang); }, explode(' · ', $vv['label']))); }
						unset($vv);
					}
					unset($pp);
				}
				unset($sec);
			}
			set_transient($key, $res, 2 * HOUR_IN_SECONDS);
		}
		return $res;
	}]);
});

add_action('wp_footer', function () {
	if (is_admin()) return;
	$api = esc_url_raw(rest_url('thh/v1/keuzehulp-advies'));
	$ajax = esc_url_raw(add_query_arg('wc-ajax', 'add_to_cart', home_url('/')));
	?>
	<div class="tkh-ov" id="tkhOv" hidden>
		<div class="tkh-modal" role="dialog" aria-modal="true" aria-labelledby="tkhTitle">
			<div class="tkh-head">
				<div class="tkh-top">
					<button type="button" class="tkh-back" aria-label="Vorige vraag" hidden><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></button>
					<span class="tkh-brand"><b>Keuzehulp</b><span class="tkh-step" id="tkhStep"></span></span>
					<button type="button" class="tkh-x" aria-label="Sluiten">&times;</button>
				</div>
				<div class="tkh-bar"><span></span></div>
			</div>
			<div class="tkh-body" id="tkhBody"></div>
		</div>
	</div>
	<style>
	#tkhOv{--nv:#14204A;--nv2:#1d2b5e;--rd:#E63946;--bd:#e6e8ef;--mut:#6b7280;--bg:#f5f6f9}
	.tkh-ov{position:fixed;inset:0;z-index:100060;background:rgba(10,16,40,.6);backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:16px}
	.tkh-ov[hidden]{display:none}
	.tkh-modal{position:relative;width:100%;max-width:720px;max-height:calc(100vh - 32px);display:flex;flex-direction:column;background:#fff;border-radius:22px;box-shadow:0 30px 80px rgba(8,14,40,.35);color:#111827;overflow:hidden;animation:tkhIn .22s ease-out}
	@keyframes tkhIn{from{opacity:0;transform:translateY(12px) scale(.98)}to{opacity:1;transform:none}}
	.tkh-head{background:var(--nv);color:#fff;padding:14px 18px 16px}
	.tkh-top{display:flex;align-items:center;gap:10px}
	.tkh-brand{flex:1;display:flex;align-items:baseline;gap:10px}
	.tkh-brand b{font-size:15px;font-weight:700;letter-spacing:.02em}
	.tkh-step{font-size:12.5px;color:rgba(255,255,255,.7)}
	.tkh-x,.tkh-back{width:36px;height:36px;border:0;border-radius:50%;font-size:22px;line-height:1;cursor:pointer;display:grid;place-items:center;padding:0}
	.tkh-back[hidden]{display:none}
	.tkh-bar{height:5px;background:rgba(255,255,255,.18);margin-top:12px;border-radius:5px;overflow:hidden}.tkh-bar span{display:block;height:100%;width:0;background:var(--rd);border-radius:5px;transition:width .35s ease}
	.tkh-body{padding:24px 26px 26px;overflow:auto}
	.tkh-q{font-size:26px;font-weight:700;color:var(--nv);margin:0 0 6px;line-height:1.2;letter-spacing:-.015em;text-transform:none}
	.tkh-sub{margin:0 0 20px;color:var(--mut);font-size:14.5px}
	.tkh-opts{display:grid;grid-template-columns:1fr 1fr;gap:12px}
	.tkh-opts.is-3{grid-template-columns:repeat(3,1fr)}
	.tkh-opt{position:relative;display:flex;align-items:center;gap:14px;text-align:left;padding:16px;border:1.5px solid var(--bd);border-radius:14px;background:#fff;cursor:pointer;font:inherit;color:#111827;transition:border-color .15s,background .15s,transform .15s,box-shadow .15s}
	.tkh-opts.is-3 .tkh-opt{flex-direction:column;text-align:center;justify-content:center;padding:20px 12px}
	.tkh-ic{width:46px;height:46px;flex:none;border-radius:12px;background:var(--bg);color:var(--nv);display:grid;place-items:center;transition:background .15s,color .15s}
	.tkh-ic svg{width:24px;height:24px}
	.tkh-ic.txt{font-weight:800;font-size:15px;letter-spacing:-.02em}
	.tkh-opt strong{display:block;font-size:15.5px;font-weight:600;line-height:1.25}
	.tkh-opt small{display:block;font-size:12.5px;margin-top:3px;line-height:1.35}
	.tkh-check{position:absolute;top:10px;right:10px;width:22px;height:22px;border-radius:50%;border:1.5px solid var(--bd);display:grid;place-items:center;background:#fff;transition:all .15s}
	.tkh-check svg{width:13px;height:13px;opacity:0}
	.tkh-intro{display:flex;gap:14px;align-items:flex-start;background:linear-gradient(135deg,var(--nv),var(--nv2));color:#fff;border-radius:16px;padding:18px;margin:0 0 22px}
	.tkh-intro p{margin:0;font-size:14.5px;line-height:1.55;color:rgba(255,255,255,.92)}
	.tkh-chips{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 10px}
	.tkh-chip{font-size:12px;font-weight:600;background:rgba(255,255,255,.14);color:#fff;border-radius:999px;padding:4px 10px}
	.tkh-sec{margin:0 0 22px}
	.tkh-sech{display:flex;align-items:center;gap:10px;margin:0 0 6px}
	.tkh-num{width:26px;height:26px;border-radius:50%;background:var(--nv);color:#fff;font-size:13px;font-weight:700;display:grid;place-items:center;flex:none}
	.tkh-sec h3{font-size:18px;font-weight:700;color:var(--nv);margin:0;text-transform:none;letter-spacing:-.01em}
	.tkh-sec p{font-size:13.5px;color:#4b5563;margin:0 0 12px 36px;line-height:1.5}
	.tkh-prods{display:grid;grid-template-columns:1.15fr 1fr;gap:12px;margin-left:36px}
	.tkh-card{position:relative;display:flex;gap:12px;align-items:center;border:1.5px solid var(--bd);border-radius:14px;padding:12px;background:#fff}
	.tkh-card.is-top{border-color:var(--nv);box-shadow:0 8px 22px rgba(20,32,74,.10)}
	.tkh-tag{position:absolute;top:-10px;left:12px;background:var(--rd);color:#fff;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;letter-spacing:.02em}
	.tkh-card.is-alt .tkh-tag{background:#9ca3af}
	.tkh-img{width:76px;height:86px;flex:none;background:var(--bg);border-radius:10px;display:grid;place-items:center;overflow:hidden}
	.tkh-img img{max-width:86%;max-height:86%;object-fit:contain;mix-blend-mode:multiply}
	.tkh-info{flex:1;min-width:0;display:flex;flex-direction:column;gap:3px}
	.tkh-merk{font-size:11.5px;color:var(--mut)}
	.tkh-naam{font-size:13.5px;font-weight:600;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-decoration:none}
	.tkh-prijs{font-size:15px;font-weight:700}.tkh-prijs small{font-weight:500;color:#9ca3af;font-size:11.5px}.tkh-prijs del{color:#9ca3af;font-weight:500;font-size:12px;margin-right:4px}
	.tkh-add{align-self:flex-start;margin-top:4px;border-radius:999px;padding:7px 14px;font-size:12.5px;font-weight:600;cursor:pointer;line-height:1.2}
	.tkh-total{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--bg);border-radius:14px;padding:14px 16px;margin:4px 0 14px}
	.tkh-total span{font-size:13.5px;color:#374151}.tkh-total strong{font-size:20px;color:var(--nv)}
	.tkh-note{font-size:12.5px;color:var(--mut);margin:-6px 0 14px}
	.tkh-var{width:100%;height:34px;border:1.5px solid var(--bd);border-radius:8px;padding:0 8px;font-size:12.5px;background:#fff;color:#111827;margin-top:4px;max-width:260px}
	.tkh-var:focus{outline:0;border-color:var(--nv)}
	.tkh-end{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
	.tkh-btn{border-radius:999px;padding:12px 18px;font-weight:600;font-size:14px;cursor:pointer;display:inline-flex;align-items:center;gap:8px;line-height:1.2}
	.tkh-load{padding:50px 0;text-align:center;color:var(--mut)}
	.tkh-spin{width:34px;height:34px;border:3px solid var(--bg);border-top-color:var(--nv);border-radius:50%;margin:0 auto 12px;animation:tkhSpin .8s linear infinite}
	@keyframes tkhSpin{to{transform:rotate(360deg)}}
	@media(max-width:600px){.tkh-ov{padding:0;align-items:flex-end}.tkh-modal{max-height:94vh;border-radius:20px 20px 0 0}.tkh-body{padding:18px 16px 22px}.tkh-q{font-size:22px}.tkh-opts,.tkh-opts.is-3{grid-template-columns:1fr}.tkh-opts.is-3 .tkh-opt{flex-direction:row;text-align:left;justify-content:flex-start;padding:14px}.tkh-prods{grid-template-columns:1fr;margin-left:0}.tkh-sec p{margin-left:0}}
	/* vaste kleuren (thema-hovers neutraliseren) */
	#tkhOv .tkh-x,#tkhOv .tkh-back,#tkhOv .tkh-x:hover,#tkhOv .tkh-back:hover,#tkhOv .tkh-x:focus,#tkhOv .tkh-back:focus{background:rgba(255,255,255,.12)!important;color:#fff!important;border:0!important;box-shadow:none!important}
	#tkhOv .tkh-x:hover,#tkhOv .tkh-back:hover{background:rgba(255,255,255,.22)!important}
	#tkhOv .tkh-opt,#tkhOv .tkh-opt:focus{color:#111827!important;background:#fff!important;border-color:var(--bd)!important;box-shadow:none!important;text-decoration:none!important}
	#tkhOv .tkh-opt small{color:var(--mut)!important}
	#tkhOv .tkh-opt:hover,#tkhOv .tkh-opt:focus-visible{border-color:var(--nv)!important;background:#fff!important;color:#111827!important;transform:translateY(-2px);box-shadow:0 10px 24px rgba(20,32,74,.10)!important}
	#tkhOv .tkh-opt:hover .tkh-ic{background:var(--nv);color:#fff}
	#tkhOv .tkh-opt.is-on,#tkhOv .tkh-opt.is-on:hover{border-color:var(--nv)!important;background:#f3f5fb!important;color:#111827!important}
	#tkhOv .tkh-opt.is-on .tkh-ic{background:var(--nv);color:#fff}
	#tkhOv .tkh-opt.is-on .tkh-check{background:var(--nv);border-color:var(--nv)}#tkhOv .tkh-opt.is-on .tkh-check svg{opacity:1}
	#tkhOv .tkh-add,#tkhOv .tkh-add:hover,#tkhOv .tkh-add:focus,#tkhOv .tkh-add:visited{background:var(--nv)!important;color:#fff!important;border:0!important;text-decoration:none!important;box-shadow:none!important}
	#tkhOv .tkh-add:hover{background:#0d1538!important}
	#tkhOv .tkh-add.is-ok,#tkhOv .tkh-add.is-ok:hover{background:#16a34a!important}
	#tkhOv .tkh-btn-nv,#tkhOv .tkh-btn-nv:hover,#tkhOv .tkh-btn-nv:visited,#tkhOv .tkh-btn-nv:focus{background:#25D366!important;color:#fff!important;text-decoration:none!important;border:0!important}
	#tkhOv .tkh-btn-nv:hover{background:#1eb85a!important}
	#tkhOv .tkh-btn-all,#tkhOv .tkh-btn-all:hover,#tkhOv .tkh-btn-all:focus{background:var(--rd)!important;color:#fff!important;border:0!important;white-space:nowrap}
	#tkhOv .tkh-btn-all:hover{background:#c92f3b!important}#tkhOv .tkh-btn-all.is-ok{background:#16a34a!important}
	#tkhOv .tkh-btn-gh,#tkhOv .tkh-btn-gh:hover,#tkhOv .tkh-btn-gh:focus{background:var(--bg)!important;color:var(--nv)!important;border:0!important;box-shadow:none!important}
	#tkhOv .tkh-btn-gh:hover{background:#e7e9f0!important}
	#tkhOv .tkh-naam,#tkhOv .tkh-naam:hover,#tkhOv .tkh-naam:visited{color:#111827!important}#tkhOv .tkh-naam:hover{text-decoration:underline!important}
	</style>
	<script>
	(function(){
		var ov=document.getElementById('tkhOv'); if(!ov) return;
		var body=document.getElementById('tkhBody'), bar=ov.querySelector('.tkh-bar span'), back=ov.querySelector('.tkh-back');
		var API=<?php echo wp_json_encode($api); ?>, AJAX=<?php echo wp_json_encode($ajax); ?>;
		var IC={"spier": "<path d=\"M6.5 6.5v11M17.5 6.5v11M3 9.5v5M21 9.5v5M6.5 12h11\"/>", "afvallen": "<path d=\"M12 22c4 0 7-2.8 7-7 0-3.5-2-5.5-3.5-7.5-.5 2-1.5 3-3 3.5.5-3.5-.5-6.5-3-9 0 4-5.5 6.5-5.5 13 0 4.2 3.5 7 8 7z\"/><path d=\"M12 22c-1.7 0-3-1.3-3-3 0-1.8 1.4-2.8 3-5 1.6 2.2 3 3.2 3 5 0 1.7-1.3 3-3 3z\"/>", "fit": "<path d=\"M13 2L4 14h7l-1 8 9-12h-7z\"/>", "duur": "<path d=\"M3 12h4l3-8 4 16 3-8h4\"/>", "herstel": "<path d=\"M20 14.5A8 8 0 019.5 4a8 8 0 1010.5 10.5z\"/>", "kracht": "<path d=\"M6.5 6.5v11M17.5 6.5v11M3 9.5v5M21 9.5v5M6.5 12h11\"/>", "vecht": "<path d=\"M7 11V8a4 4 0 014-4h3a4 4 0 014 4v5a5 5 0 01-5 5h-1a5 5 0 01-5-5z\"/><path d=\"M7 12H5.5A1.5 1.5 0 004 13.5 3.5 3.5 0 007.5 17M8 20h9\"/>", "cardio": "<circle cx=\"6\" cy=\"17\" r=\"3.5\"/><circle cx=\"18\" cy=\"17\" r=\"3.5\"/><path d=\"M6 17l4-7h5l3 7M10 10L8.5 6H6M14 6h2\"/>", "mix": "<path d=\"M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5\"/>", "geen": "<path d=\"M20 6L9 17l-5-5\"/>", "lactose": "<path d=\"M8 2h8M9 2v3l-2 4v11a2 2 0 002 2h6a2 2 0 002-2V9l-2-4V2M7 12h10\"/><path d=\"M4 4l16 16\"/>", "vegan": "<path d=\"M11 20A7 7 0 014 13c0-6 7-9 16-9 0 9-3 16-9 16z\"/><path d=\"M4 20c4-5 7-8 11-10\"/>", "basis": "<path d=\"M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8\"/>", "compleet": "<path d=\"M12 2l3 6.5 7 .8-5.2 4.8 1.4 7L12 17.5 5.8 21l1.4-7L2 9.3l7-.8z\"/>"};
		var V=[
			{k:'doel',q:'Wat is je doel?',s:'Kies wat het beste bij je past.',o:[['spier','Spieren opbouwen','Sterker worden en meer spiermassa'],['afvallen','Afvallen','Vet verliezen en strakker worden'],['fit','Fitter voelen','Meer energie en een gezonde basis'],['duur','Beter presteren','Duursport, vechtsport of teamsport'],['herstel','Herstel en gezondheid','Gewrichten, slaap en weerstand']]},
			{k:'freq',q:'Hoe vaak train je per week?',s:'Dan weten we hoeveel ondersteuning je nodig hebt.',n:1,o:[['laag','1 tot 2 keer','','1-2'],['mid','3 tot 4 keer','','3-4'],['hoog','5 keer of vaker','','5+']]},
			{k:'sport',q:'Wat voor training doe je vooral?',s:'',o:[['kracht','Krachttraining','Fitness en gewichten'],['vecht','Vechtsport','Boksen, kickboksen, MMA'],['cardio','Cardio of duursport','Hardlopen, fietsen, zwemmen'],['mix','Een mix','Van alles wat']]},
			{k:'dieet',q:'Let je op iets in je voeding?',s:'Daar houden we rekening mee bij de eiwitshake.',n:1,o:[['geen','Nee, alles is goed',''],['lactose','Lactosevrij',''],['vegan','Vegan','']]},
			{k:'budget',q:'Wat zoek je?',s:'',o:[['basis','De basis','Alleen wat je echt nodig hebt'],['compleet','Een compleet pakket','De basis plus extra ondersteuning']]}
		];
		var LBL={}; V.forEach(function(v){ v.o.forEach(function(o){ LBL[v.k+':'+o[0]]=o[1]; }); });
		var CHECK='<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>';
		function icon(k,t){ if(t) return '<span class="tkh-ic txt">'+t+'</span>'; return '<span class="tkh-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+(IC[k]||'')+'</svg></span>'; }
		var st=0, ans={}, last=null;
		function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
		var stepEl=document.getElementById('tkhStep');
		function prog(){ bar.style.width=Math.round((Math.min(st+1,V.length)/V.length)*100)+'%'; back.hidden=st===0; stepEl.textContent=st<V.length?('Stap '+(st+1)+' van '+V.length):'Jouw advies'; }
		function step(){
			prog(); var v=V[st];
			body.innerHTML='<h2 class="tkh-q" id="tkhTitle">'+esc(v.q)+'</h2>'+(v.s?'<p class="tkh-sub">'+esc(v.s)+'</p>':'<p class="tkh-sub">&nbsp;</p>')+'<div class="tkh-opts'+(v.n?' is-3':'')+'">'+v.o.map(function(o){return '<button type="button" class="tkh-opt'+(ans[v.k]===o[0]?' is-on':'')+'" data-v="'+o[0]+'">'+icon(o[0],o[3])+'<span><strong>'+esc(o[1])+'</strong>'+(o[2]?'<small>'+esc(o[2])+'</small>':'')+'</span><span class="tkh-check" aria-hidden="true">'+CHECK+'</span></button>';}).join('')+'</div>';
			body.scrollTop=0;
		}
		function card(p,i){
			var pr=(p.van?'<del>€ '+esc(p.van)+'</del>':'')+(p.vanaf?'<small>vanaf </small>':'')+'€ '+esc(p.prijs);
			var vs=p.varianten||[], btn='';
			if(p.direct) btn='<button type="button" class="tkh-add" data-id="'+p.id+'">In winkelwagen</button>';
			else if(vs.length) btn='<select class="tkh-var" aria-label="Kies smaak of maat">'+vs.map(function(v){return '<option value="'+v.id+'">'+esc(v.label)+' · € '+esc(v.prijs)+'</option>';}).join('')+'</select><button type="button" class="tkh-add" data-var="1">In winkelwagen</button>';
			else btn='<a class="tkh-add" href="'+esc(p.url)+'">Bekijk product</a>';
			return '<div class="tkh-card '+(i===0?'is-top':'is-alt')+'"><span class="tkh-tag">'+(i===0?'Onze keuze':'Of kies')+'</span><a class="tkh-img" href="'+esc(p.url)+'"><img src="'+esc(p.img)+'" alt="" loading="lazy"></a><div class="tkh-info">'+(p.merk?'<span class="tkh-merk">'+esc(p.merk)+'</span>':'')+'<a class="tkh-naam" href="'+esc(p.url)+'" style="color:inherit;text-decoration:none">'+esc(p.naam)+'</a><span class="tkh-prijs">'+pr+'</span>'+btn+'</div></div>';
		}
		function result(){
			st=V.length; prog(); back.hidden=false;
			body.innerHTML='<div class="tkh-load"><div class="tkh-spin"></div>Even je advies samenstellen...</div>';
			fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({lang:(location.pathname.match(/^\/(de|en)(\/|$)/)||[])[1]||'nl'},ans))}).then(function(r){return r.json();}).then(function(d){
				var chips=V.map(function(v){ return ans[v.k]?'<span class="tkh-chip">'+esc(LBL[v.k+':'+ans[v.k]])+'</span>':''; }).join('');
				var h='<h2 class="tkh-q" id="tkhTitle">Jouw advies</h2><div class="tkh-intro"><div><div class="tkh-chips">'+chips+'</div>'+(d.intro?'<p>'+esc(d.intro)+'</p>':'')+'</div></div>';
				var tot=0;
				(d.secties||[]).forEach(function(s,i){ if(s.producten[0]) tot+=parseFloat(String(s.producten[0].prijs).replace(/\./g,'').replace(',','.'))||0; h+='<div class="tkh-sec"><div class="tkh-sech"><span class="tkh-num">'+(i+1)+'</span><h3>'+esc(s.titel)+'</h3></div><p>'+esc(s.waarom)+'</p><div class="tkh-prods">'+s.producten.map(card).join('')+'</div></div>'; });
				if(!(d.secties||[]).length) h+='<p class="tkh-sub">We konden nu geen producten vinden. Stuur ons gerust een WhatsApp, dan denken we mee.</p>';
				if(tot>0) h+='<div class="tkh-total"><div><span>Je pakket met onze keuzes, vanaf</span><br><strong>€ '+tot.toFixed(2).replace('.',',')+'</strong></div><button type="button" class="tkh-btn tkh-btn-all" data-act="alles">Alles in winkelwagen</button></div><p class="tkh-note">Smaak of maat kun je hierboven kiezen, of later nog aanpassen in je winkelwagen.</p>';
				h+='<div class="tkh-end"><button type="button" class="tkh-btn tkh-btn-gh" data-act="opnieuw">Opnieuw beginnen</button><a class="tkh-btn tkh-btn-nv" href="https://wa.me/31611789629" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm5.3 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.3-.3.6-.3.8-.3h.6c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6-.4.4c-.1.1-.3.3-.1.6.2.3.7 1.2 1.6 2 1.1 1 2 1.3 2.3 1.4.3.1.5.1.6-.1l.9-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.7-.1 1.2z"/></svg>Twijfel je? Vraag het via WhatsApp</a></div>';
				body.innerHTML=h; body.scrollTop=0;
			}).catch(function(){ body.innerHTML='<p class="tkh-sub">Er ging iets mis. Probeer het opnieuw.</p>'; });
		}
		function open(e){ if(e) e.preventDefault(); last=document.activeElement; st=0; ans={}; step(); ov.hidden=false; document.documentElement.style.overflow='hidden'; }
		function close(){ ov.hidden=true; document.documentElement.style.overflow=''; if(last&&last.focus) last.focus(); }
		document.addEventListener('click',function(e){ var a=e.target.closest('[data-thh-keuzehulp],a[href$="#keuzehulp"]'); if(a) open(e); });
		if(location.hash==='#keuzehulp') open();
		ov.addEventListener('click',function(e){
			if(e.target===ov||e.target.closest('.tkh-x')) return close();
			if(e.target.closest('.tkh-back')){ if(st>0){ st--; step(); } return; }
			var o=e.target.closest('.tkh-opt'); if(o){ ans[V[st].k]=o.getAttribute('data-v'); o.classList.add('is-on'); setTimeout(function(){ st++; if(st<V.length) step(); else result(); },220); return; }
			if(e.target.closest('[data-act="opnieuw"]')){ st=0; ans={}; step(); return; }
			var all=e.target.closest('[data-act="alles"]'); if(all){ var btns=[].slice.call(body.querySelectorAll('.tkh-card.is-top button.tkh-add:not(.is-ok)')); all.disabled=true; all.textContent='Toevoegen...'; var i=0; (function next(){ if(i>=btns.length){ all.textContent='Alles toegevoegd ✓'; all.classList.add('is-ok'); setTimeout(function(){ location.href=<?php echo wp_json_encode(wc_get_cart_url()); ?>; },700); return; } var bb=btns[i++]; bb.click(); var t=setInterval(function(){ if(bb.classList.contains('is-ok')||!bb.disabled){ clearInterval(t); next(); } },150); })(); return; }
			var b=e.target.closest('button.tkh-add'); if(b){ var pid=b.getAttribute('data-var')?(b.parentNode.querySelector('.tkh-var')||{}).value:b.getAttribute('data-id'); if(!pid) return; b.disabled=true; b.textContent='Toevoegen...'; var f=new FormData(); f.append('product_id',pid); f.append('quantity','1');
				fetch(AJAX,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){ if(d&&d.error){ throw 0; } b.textContent='Toegevoegd ✓'; b.classList.add('is-ok'); if(window.jQuery&&d&&d.fragments){ jQuery(document.body).trigger('added_to_cart',[d.fragments,d.cart_hash]); } }).catch(function(){ b.disabled=false; b.textContent='Opnieuw proberen'; });
			}
		});
		document.addEventListener('keydown',function(e){ if(e.key==='Escape'&&!ov.hidden) close(); });
	})();
	</script>
	<?php
}, 61);

/* ---------- Winkelwagen: smaak/maat wijzigen bij variabele producten ---------- */
add_action('woocommerce_after_cart_item_name', function ($item, $key) {
	if (empty($item['variation_id']) || empty($item['product_id'])) return;
	$parent = wc_get_product($item['product_id']);
	if (!$parent || !$parent->is_type('variable')) return;
	$opts = thh_kp_varianten($parent);
	if (count($opts) < 2) return;
	echo '<div class="thh-cartvar"><label>Smaak/maat wijzigen: <select data-key="' . esc_attr($key) . '">';
	$found = false;
	foreach ($opts as $o) { $sel = (int) $o['id'] === (int) $item['variation_id']; $found = $found || $sel; echo '<option value="' . (int) $o['id'] . '"' . selected($sel, true, false) . '>' . esc_html($o['label'] . ' · € ' . $o['prijs']) . '</option>'; }
	if (!$found) echo '<option value="' . (int) $item['variation_id'] . '" selected>Huidige keuze</option>';
	echo '</select></label></div>';
}, 20, 2);

add_action('wp_ajax_thh_cart_wissel', 'thh_cart_wissel');
add_action('wp_ajax_nopriv_thh_cart_wissel', 'thh_cart_wissel');
function thh_cart_wissel() {
	check_ajax_referer('thh_cartvar', 'nonce');
	$key = sanitize_text_field(wp_unslash($_POST['key'] ?? ''));
	$vid = (int) ($_POST['vid'] ?? 0);
	$cart = WC()->cart; $item = $key ? $cart->get_cart_item($key) : null;
	$v = wc_get_product($vid);
	if (!$item || !$v || !$v->is_type('variation') || (int) $v->get_parent_id() !== (int) $item['product_id'] || !$v->is_in_stock()) wp_send_json_error('ongeldig', 400);
	$qty = (int) $item['quantity'];
	$cart->remove_cart_item($key);
	$ok = $cart->add_to_cart($v->get_parent_id(), $qty, $vid, $v->get_variation_attributes());
	if (!$ok) { $cart->restore_cart_item($key); wp_send_json_error('niet gelukt', 400); }
	wp_send_json_success();
}

add_action('wp_footer', function () {
	if (!function_exists('is_cart') || !is_cart()) return;
	?>
	<style>.thh-cartvar{margin-top:6px;font-size:13px}.thh-cartvar label{display:flex;flex-wrap:wrap;align-items:center;gap:6px;color:#6b7280;font-weight:500}.thh-cartvar select{height:34px;border:1.5px solid #e5e7eb;border-radius:8px;padding:0 8px;font-size:13px;color:#111827;background:#fff;max-width:280px}</style>
	<script>
	document.addEventListener('change',function(e){
		var s=e.target.closest('.thh-cartvar select'); if(!s) return;
		s.disabled=true;
		var f=new FormData(); f.append('action','thh_cart_wissel'); f.append('nonce',<?php echo wp_json_encode(wp_create_nonce('thh_cartvar')); ?>); f.append('key',s.getAttribute('data-key')); f.append('vid',s.value);
		fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){ if(d&&d.success){ location.reload(); } else { s.disabled=false; alert('Wijzigen is niet gelukt.'); } }).catch(function(){ s.disabled=false; });
	});
	</script>
	<?php
}, 70);
