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
		'id' => $p->get_id(), 'naam' => $p->get_name(), 'merk' => (!is_wp_error($merk) && $merk) ? $merk[0] : '',
		'url' => $p->get_permalink(), 'img' => $img, 'prijs' => number_format($prijs, 2, ',', '.'),
		'van' => ($reg > $prijs) ? number_format($reg, 2, ',', '.') : '',
		'vanaf' => $vanaf,
		'direct' => !$var && $p->is_purchasable() && $p->is_in_stock(),
	];
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
		$key = 'thh_kp3_' . md5(wp_json_encode($a));
		$res = get_transient($key);
		if (!$res) { $res = thh_kp_advies($a); set_transient($key, $res, 2 * HOUR_IN_SECONDS); }
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
			<div class="tkh-top">
				<button type="button" class="tkh-back" aria-label="Vorige vraag" hidden><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></button>
				<span class="tkh-brand">Keuzehulp</span>
				<button type="button" class="tkh-x" aria-label="Sluiten">&times;</button>
			</div>
			<div class="tkh-bar"><span></span></div>
			<div class="tkh-body" id="tkhBody"></div>
		</div>
	</div>
	<style>
	.tkh-ov{position:fixed;inset:0;z-index:100060;background:rgba(10,16,40,.55);display:flex;align-items:center;justify-content:center;padding:16px}
	.tkh-ov[hidden]{display:none}
	.tkh-modal{position:relative;width:100%;max-width:680px;max-height:calc(100vh - 32px);display:flex;flex-direction:column;background:#fff;border-radius:18px;box-shadow:0 24px 60px rgba(0,0,0,.25);color:#111827;overflow:hidden}
	.tkh-top{display:flex;align-items:center;gap:10px;padding:14px 16px 10px}
	.tkh-brand{flex:1;font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#E63946}
	.tkh-x,.tkh-back{width:36px;height:36px;border:0;background:#f3f4f6;border-radius:50%;font-size:22px;line-height:1;cursor:pointer;color:#14204A;display:grid;place-items:center;padding:0}
	.tkh-back[hidden]{display:none}
	.tkh-bar{height:4px;background:#eef0f4;margin:0 16px;border-radius:4px;overflow:hidden}.tkh-bar span{display:block;height:100%;width:0;background:#14204A;transition:width .3s}
	.tkh-body{padding:18px 24px 24px;overflow:auto}
	.tkh-q{font-size:24px;font-weight:700;color:#14204A;margin:4px 0 6px;line-height:1.25;letter-spacing:-.01em;text-transform:none}
	.tkh-sub{margin:0 0 18px;color:#6b7280;font-size:14px}
	.tkh-opts{display:grid;grid-template-columns:1fr 1fr;gap:10px}
	.tkh-opt{display:flex;align-items:center;gap:12px;text-align:left;padding:14px 16px;border:1.5px solid #e5e7eb;border-radius:12px;background:#fff;cursor:pointer;font:inherit;color:#111827;transition:border-color .15s,background .15s}
	.tkh-opt:hover{border-color:#14204A;background:#f8f9fc}
	.tkh-opt.is-on{border-color:#14204A;background:#14204A;color:#fff}
	.tkh-ico{font-size:22px;line-height:1;flex:none}
	.tkh-opt strong{display:block;font-size:15px;font-weight:600}
	.tkh-opt small{display:block;font-size:12.5px;color:#6b7280;margin-top:2px}
	.tkh-opt.is-on small{color:rgba(255,255,255,.75)}
	.tkh-intro{background:#f5f5f6;border-radius:12px;padding:14px 16px;font-size:14px;line-height:1.5;margin:0 0 18px;color:#374151}
	.tkh-sec{margin:0 0 20px}
	.tkh-sec h3{font-size:17px;font-weight:700;color:#14204A;margin:0 0 4px;text-transform:none}
	.tkh-sec p{font-size:13.5px;color:#4b5563;margin:0 0 10px;line-height:1.5}
	.tkh-prods{display:grid;grid-template-columns:1fr 1fr;gap:10px}
	.tkh-card{position:relative;display:flex;gap:12px;align-items:center;border:1.5px solid #eef0f4;border-radius:12px;padding:10px;text-decoration:none;color:#111827}
	.tkh-card.is-top{border-color:#14204A}
	.tkh-tag{position:absolute;top:-9px;left:10px;background:#14204A;color:#fff;font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px}
	.tkh-card.is-alt .tkh-tag{background:#9ca3af}
	.tkh-img{width:70px;height:78px;flex:none;background:#f5f5f6;border-radius:8px;display:grid;place-items:center;overflow:hidden}
	.tkh-img img{max-width:88%;max-height:88%;object-fit:contain;mix-blend-mode:multiply}
	.tkh-info{flex:1;min-width:0;display:flex;flex-direction:column;gap:3px}
	.tkh-merk{font-size:11.5px;color:#6b7280}
	.tkh-naam{font-size:13.5px;font-weight:600;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
	.tkh-prijs{font-size:14px;font-weight:700}.tkh-prijs small{font-weight:500;color:#9ca3af;font-size:11.5px}.tkh-prijs del{color:#9ca3af;font-weight:500;font-size:12px;margin-right:4px}
	.tkh-add{align-self:flex-start;margin-top:2px;border:0;background:#14204A;color:#fff;border-radius:999px;padding:6px 12px;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none}
	.tkh-add.is-ok{background:#16a34a}
	.tkh-end{display:flex;flex-wrap:wrap;gap:10px;margin-top:6px;padding-top:16px;border-top:1px solid #eef0f4;align-items:center}
	.tkh-btn{border:0;border-radius:999px;padding:12px 18px;font-weight:600;font-size:14px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
	.tkh-btn-nv{background:#14204A;color:#fff}.tkh-btn-gh{background:#f3f4f6;color:#14204A}
	.tkh-load{padding:40px 0;text-align:center;color:#6b7280}
	@media(max-width:600px){.tkh-ov{padding:0;align-items:flex-end}.tkh-modal{max-height:92vh;border-radius:18px 18px 0 0}.tkh-body{padding:14px 16px 20px}.tkh-q{font-size:21px}.tkh-opts,.tkh-prods{grid-template-columns:1fr}}
	</style>
	<script>
	(function(){
		var ov=document.getElementById('tkhOv'); if(!ov) return;
		var body=document.getElementById('tkhBody'), bar=ov.querySelector('.tkh-bar span'), back=ov.querySelector('.tkh-back');
		var API=<?php echo wp_json_encode($api); ?>, AJAX=<?php echo wp_json_encode($ajax); ?>;
		var V=[
			{k:'doel',q:'Wat is je doel?',s:'Kies wat het beste bij je past.',o:[['spier','💪','Spieren opbouwen','Sterker worden en meer spiermassa'],['afvallen','🔥','Afvallen','Vet verliezen en strakker worden'],['fit','⚡','Fitter voelen','Meer energie en een gezonde basis'],['duur','🏃','Beter presteren','Duursport, vechtsport of teamsport'],['herstel','🌙','Herstel en gezondheid','Gewrichten, slaap en weerstand']]},
			{k:'freq',q:'Hoe vaak train je per week?',s:'',o:[['laag','1️⃣','1 tot 2 keer',''],['mid','2️⃣','3 tot 4 keer',''],['hoog','3️⃣','5 keer of vaker','']]},
			{k:'sport',q:'Wat voor training doe je vooral?',s:'',o:[['kracht','🏋️','Krachttraining','Fitness en gewichten'],['vecht','🥊','Vechtsport','Boksen, kickboksen, MMA'],['cardio','🚴','Cardio of duursport','Hardlopen, fietsen, zwemmen'],['mix','🔄','Een mix','Van alles wat']]},
			{k:'dieet',q:'Let je op iets in je voeding?',s:'Dan houden we daar rekening mee bij de eiwitshake.',o:[['geen','✅','Nee, alles is goed',''],['lactose','🥛','Lactosevrij',''],['vegan','🌱','Vegan','']]},
			{k:'budget',q:'Wat wil je?',s:'',o:[['basis','👌','De basis','Alleen wat je echt nodig hebt'],['compleet','⭐','Een compleet pakket','De basis plus extra ondersteuning']]}
		];
		var st=0, ans={}, last=null;
		function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
		function prog(){ bar.style.width=Math.round((st/V.length)*100)+'%'; back.hidden=st===0; }
		function step(){
			prog(); var v=V[st];
			body.innerHTML='<h2 class="tkh-q" id="tkhTitle">'+esc(v.q)+'</h2>'+(v.s?'<p class="tkh-sub">'+esc(v.s)+'</p>':'<p class="tkh-sub">Vraag '+(st+1)+' van '+V.length+'</p>')+'<div class="tkh-opts">'+v.o.map(function(o){return '<button type="button" class="tkh-opt'+(ans[v.k]===o[0]?' is-on':'')+'" data-v="'+o[0]+'"><span class="tkh-ico" aria-hidden="true">'+o[1]+'</span><span><strong>'+esc(o[2])+'</strong>'+(o[3]?'<small>'+esc(o[3])+'</small>':'')+'</span></button>';}).join('')+'</div>';
		}
		function card(p,i){
			var pr=(p.van?'<del>€ '+esc(p.van)+'</del>':'')+(p.vanaf?'<small>vanaf </small>':'')+'€ '+esc(p.prijs);
			var btn=p.direct?'<button type="button" class="tkh-add" data-id="'+p.id+'">In winkelwagen</button>':'<a class="tkh-add" href="'+esc(p.url)+'">Kies je smaak</a>';
			return '<div class="tkh-card '+(i===0?'is-top':'is-alt')+'"><span class="tkh-tag">'+(i===0?'Onze keuze':'Alternatief')+'</span><a class="tkh-img" href="'+esc(p.url)+'"><img src="'+esc(p.img)+'" alt="" loading="lazy"></a><div class="tkh-info">'+(p.merk?'<span class="tkh-merk">'+esc(p.merk)+'</span>':'')+'<a class="tkh-naam" href="'+esc(p.url)+'" style="color:inherit;text-decoration:none">'+esc(p.naam)+'</a><span class="tkh-prijs">'+pr+'</span>'+btn+'</div></div>';
		}
		function result(){
			st=V.length; prog(); back.hidden=false;
			body.innerHTML='<div class="tkh-load">Even je advies samenstellen...</div>';
			fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(ans)}).then(function(r){return r.json();}).then(function(d){
				var h='<h2 class="tkh-q" id="tkhTitle">Jouw advies</h2>'+(d.intro?'<p class="tkh-intro">'+esc(d.intro)+'</p>':'');
				(d.secties||[]).forEach(function(s){ h+='<div class="tkh-sec"><h3>'+esc(s.titel)+'</h3><p>'+esc(s.waarom)+'</p><div class="tkh-prods">'+s.producten.map(card).join('')+'</div></div>'; });
				if(!(d.secties||[]).length) h+='<p class="tkh-sub">We konden nu geen producten vinden. Stuur ons gerust een WhatsApp, dan denken we mee.</p>';
				h+='<div class="tkh-end"><button type="button" class="tkh-btn tkh-btn-gh" data-act="opnieuw">Opnieuw beginnen</button><a class="tkh-btn tkh-btn-nv" href="https://wa.me/31611789629" target="_blank" rel="noopener">Twijfel je? Vraag het via WhatsApp</a></div>';
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
			var o=e.target.closest('.tkh-opt'); if(o){ ans[V[st].k]=o.getAttribute('data-v'); o.classList.add('is-on'); setTimeout(function(){ st++; if(st<V.length) step(); else result(); },160); return; }
			if(e.target.closest('[data-act="opnieuw"]')){ st=0; ans={}; step(); return; }
			var b=e.target.closest('button.tkh-add'); if(b){ b.disabled=true; b.textContent='Toevoegen...'; var f=new FormData(); f.append('product_id',b.getAttribute('data-id')); f.append('quantity','1');
				fetch(AJAX,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){ if(d&&d.error){ throw 0; } b.textContent='Toegevoegd ✓'; b.classList.add('is-ok'); if(window.jQuery&&d&&d.fragments){ jQuery(document.body).trigger('added_to_cart',[d.fragments,d.cart_hash]); } }).catch(function(){ b.disabled=false; b.textContent='Opnieuw proberen'; });
			}
		});
		document.addEventListener('keydown',function(e){ if(e.key==='Escape'&&!ov.hidden) close(); });
	})();
	</script>
	<?php
}, 61);
