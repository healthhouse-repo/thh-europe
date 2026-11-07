<?php
/**
 * THH — Foto tellen (voorraadregister)
 *
 * Extra knop "Foto tellen" in het telscherm van /voorraad (snippet 242). De medewerker fotografeert
 * een stuk schap; de foto gaat naar Claude (Anthropic API), die teruggeeft welke producten erop
 * staan met een geschat aantal en een zekerheid. Elke regel wordt gekoppeld aan een product uit de
 * webshop en als lijst getoond; de medewerker controleert, corrigeert en slaat op. Opslaan loopt via
 * het gewone register-endpoint (thh-vrr/v1/boek, soort "telling", notitie "fototelling"), dus ook de
 * Osdorp→webshop-koppeling werkt gewoon mee. Regels met lage zekerheid staan rood en moeten
 * bevestigd worden.
 *
 * Instellingen: WooCommerce › Foto tellen — API-sleutel (wordt alleen opgeslagen, nooit getoond),
 * model, en voor welke winkels de knop aanstaat (test: 60110 Osdorpplein).
 */
if (!defined('ABSPATH')) return;

if (!function_exists('thh_vf_opts')) {

function thh_vf_opts() {
	$o = get_option('thh_vrr_foto', []);
	return wp_parse_args(is_array($o) ? $o : [], ['api_key' => '', 'model' => 'claude-sonnet-5-5', 'stores' => '60110', 'max' => 1600]);
}
function thh_vf_stores() { return array_filter(array_map('intval', explode(',', (string) thh_vf_opts()['stores']))); }
function thh_vf_store_now($request = null) {
	if (function_exists('thh_vrr_store')) { try { $s = (int) thh_vrr_store($request); if ($s) return $s; } catch (Throwable $e) {} }
	return isset($_COOKIE['thh_kassa_store']) ? (int) $_COOKIE['thh_kassa_store'] : 0;
}
function thh_vf_can() { return is_user_logged_in() && (current_user_can('manage_woocommerce') || current_user_can('publish_shop_orders') || current_user_can('access_woocommerce_pos')); }

/* ---------- instellingen ---------- */
add_action('admin_menu', function () {
	add_submenu_page('woocommerce', 'Foto tellen', 'Foto tellen', 'manage_woocommerce', 'thh-foto-tellen', function () {
		$o = thh_vf_opts();
		if (isset($_POST['thh_vf_save']) && check_admin_referer('thh_vf')) {
			$n = ['model' => sanitize_text_field($_POST['model'] ?? $o['model']), 'stores' => sanitize_text_field($_POST['stores'] ?? $o['stores']), 'max' => max(800, min(2400, (int) ($_POST['max'] ?? 1600))), 'api_key' => $o['api_key']];
			$k = trim((string) ($_POST['api_key'] ?? ''));
			if ($k !== '') $n['api_key'] = $k;
			if (!empty($_POST['wis_key'])) $n['api_key'] = '';
			update_option('thh_vrr_foto', $n, false); $o = $n;
			echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
		}
		$stat = get_option('thh_vrr_foto_stat', ['fotos' => 0, 'regels' => 0, 'laatste' => '']);
		echo '<div class="wrap"><h1>Foto tellen</h1><form method="post">'; wp_nonce_field('thh_vf');
		echo '<table class="form-table">';
		echo '<tr><th>API-sleutel (Anthropic)</th><td><input type="password" name="api_key" class="regular-text" autocomplete="new-password" placeholder="' . ($o['api_key'] ? 'sleutel staat ingesteld (eindigt op ' . esc_attr(substr($o['api_key'], -4)) . ')' : 'sk-ant-…') . '"> <label><input type="checkbox" name="wis_key" value="1"> sleutel wissen</label><p class="description">Alleen invullen om te (ver)nieuwen; de sleutel wordt nooit getoond.</p></td></tr>';
		echo '<tr><th>Model</th><td><input type="text" name="model" value="' . esc_attr($o['model']) . '" class="regular-text"><p class="description">Standaard claude-sonnet-5-5. Goedkoper: claude-haiku-5-5.</p></td></tr>';
		echo '<tr><th>Winkels (kassa-ID\'s)</th><td><input type="text" name="stores" value="' . esc_attr($o['stores']) . '" class="regular-text"><p class="description">Komma-gescheiden. 60110 = Osdorpplein, 60108 = IJburg, 92924 = The Style Outlets, 74877 = Team Champ.</p></td></tr>';
		echo '<tr><th>Fotobreedte (px)</th><td><input type="number" name="max" value="' . (int) $o['max'] . '" min="800" max="2400"></td></tr>';
		echo '</table><p><button class="button button-primary" name="thh_vf_save" value="1">Opslaan</button></p></form>';
		echo '<p>Gebruik tot nu toe: ' . (int) $stat['fotos'] . ' foto\'s, ' . (int) $stat['regels'] . ' herkende regels' . ($stat['laatste'] ? ', laatste op ' . esc_html($stat['laatste']) : '') . '.</p></div>';
	});
}, 100);

/* ---------- AI-herkenning ---------- */
function thh_vf_herken($jpeg_b64, $store_naam, $context = 'schap') {
	$o = thh_vf_opts();
	if ($o['api_key'] === '') return new WP_Error('key', 'Geen API-sleutel ingesteld (WooCommerce › Foto tellen).');
	$prompt = ($context === 'kassa'
			? "Je ziet een foto van producten die een klant op de toonbank heeft gelegd in een Nederlandse supplementen- en sportvoedingswinkel (" . $store_naam . "). Elk stuk moet op de bon komen, dus tel exact. "
			: "Je ziet een foto van een winkelschap in een Nederlandse supplementen- en sportvoedingswinkel (" . $store_naam . "). ")
		. "Geef een lijst van alle verkoopproducten die zichtbaar zijn, gegroepeerd per product én variant (smaak/maat). Per regel: "
		. "merk, productnaam, variant (smaak of inhoud, leeg als niet leesbaar), aantal zichtbare stuks (tel voorzichtig; potten achter elkaar tel je alleen als je ze echt ziet), "
		. "en zekerheid 'hoog', 'middel' of 'laag' (laag als smaak/maat niet leesbaar is of het aantal onzeker is). "
		. "Antwoord uitsluitend met JSON: {\"regels\":[{\"merk\":\"\",\"product\":\"\",\"variant\":\"\",\"aantal\":0,\"zekerheid\":\"hoog\"}]}. Geen tekst eromheen.";
	$body = [
		'model' => $o['model'], 'max_tokens' => 4000,
		'messages' => [['role' => 'user', 'content' => [
			['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => $jpeg_b64]],
			['type' => 'text', 'text' => $prompt],
		]]],
	];
	$r = wp_remote_post('https://api.anthropic.com/v1/messages', ['timeout' => 60, 'headers' => ['content-type' => 'application/json', 'x-api-key' => $o['api_key'], 'anthropic-version' => '2023-06-01'], 'body' => wp_json_encode($body)]);
	if (is_wp_error($r)) return $r;
	$code = (int) wp_remote_retrieve_response_code($r);
	$j = json_decode(wp_remote_retrieve_body($r), true);
	if ($code !== 200) return new WP_Error('api', 'AI-fout (' . $code . '): ' . ($j['error']['message'] ?? 'onbekend'));
	$txt = '';
	foreach ((array) ($j['content'] ?? []) as $c) if (($c['type'] ?? '') === 'text') $txt .= $c['text'];
	update_option('thh_vrr_foto_last', ['tijd' => current_time('mysql'), 'stop' => $j['stop_reason'] ?? '', 'tekst' => mb_substr($txt, 0, 4000)], false);
	$txt = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($txt));
	if (preg_match('/\{.*\}/s', $txt, $m)) $txt = $m[0];
	$d = json_decode($txt, true);
	if (!is_array($d) || !isset($d['regels'])) return new WP_Error('parse', 'AI gaf geen leesbare lijst terug (' . ($j['stop_reason'] ?? '') . '): ' . mb_substr(wp_strip_all_tags($txt), 0, 200));
	$out = [];
	foreach ((array) $d['regels'] as $x) {
		$out[] = ['merk' => sanitize_text_field($x['merk'] ?? ''), 'product' => sanitize_text_field($x['product'] ?? ''), 'variant' => sanitize_text_field($x['variant'] ?? ''), 'aantal' => max(0, (int) ($x['aantal'] ?? 0)), 'zekerheid' => in_array($x['zekerheid'] ?? '', ['hoog', 'middel', 'laag'], true) ? $x['zekerheid'] : 'laag'];
	}
	return $out;
}

/* ---------- koppelen aan webshopproducten ---------- */
function thh_vf_kandidaten($merk, $product, $variant) {
	$product = trim(preg_replace('/\s+/', ' ', preg_replace('/\([^)]*\)|[:,]/', ' ', $product))); // toelichting tussen haakjes weg
	$merk = trim(preg_replace('/\b(nutrition|supplements?|labs?)\b/i', '', $merk));
	$w = array_values(array_filter(explode(' ', $product), function ($x) { return strlen($x) > 1; }));
	$cands = [];
	$zoek = function ($s) {
		$s = trim($s); if ($s === '') return [];
		$ids = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 6, 's' => $s, 'fields' => 'ids']);
		return array_map('intval', $ids);
	};
	$pogingen = [trim($merk . ' ' . $product), $product, trim($merk . ' ' . implode(' ', array_slice($w, 0, 2))), implode(' ', array_slice($w, 0, 2)), trim($merk . ' ' . ($w[0] ?? '')), $merk];
	$ids = [];
	foreach (array_unique(array_filter($pogingen)) as $s) { $ids = $zoek($s); if ($ids) break; }
	$v = strtolower($variant);
	foreach (array_slice($ids, 0, 4) as $pid) {
		$p = wc_get_product($pid); if (!$p) continue;
		if ($p->is_type('variable')) {
			$best = null; $bestscore = -1;
			foreach ($p->get_children() as $vid) {
				$var = wc_get_product($vid); if (!$var) continue;
				$label = implode(' ', array_values($var->get_attributes()));
				$score = 0;
				if ($v !== '') foreach (preg_split('/[\s\/,-]+/', $v) as $tok) if (strlen($tok) > 1 && stripos($label, $tok) !== false) $score++;
				$cands[] = ['pid' => $pid, 'vid' => $vid, 'naam' => $p->get_name() . ' – ' . $label, 'score' => $score, 'img' => wp_get_attachment_image_url($var->get_image_id() ?: $p->get_image_id(), 'thumbnail')];
				if ($score > $bestscore) { $bestscore = $score; $best = $vid; }
			}
		} else {
			$cands[] = ['pid' => $pid, 'vid' => 0, 'naam' => $p->get_name(), 'score' => 0, 'img' => wp_get_attachment_image_url($p->get_image_id(), 'thumbnail')];
		}
	}
	usort($cands, function ($a, $b) { return $b['score'] <=> $a['score']; });
	return array_slice($cands, 0, 8);
}

add_action('rest_api_init', function () {
	register_rest_route('thh-foto/v1', '/tel', ['methods' => 'POST', 'permission_callback' => 'thh_vf_can', 'callback' => function ($r) {
		$store = thh_vf_store_now($r);
		if (!in_array($store, thh_vf_stores(), true)) return new WP_Error('store', 'Foto tellen staat voor deze winkel niet aan.', ['status' => 403]);
		$b64 = (string) $r->get_param('foto');
		$b64 = preg_replace('#^data:image/\w+;base64,#', '', $b64);
		if (strlen($b64) < 1000 || strlen($b64) > 8000000) return new WP_Error('foto', 'Geen bruikbare foto.', ['status' => 400]);
		$naam = get_the_title($store) ?: 'winkel';
		$regels = thh_vf_herken($b64, $naam, $r->get_param('context') === 'kassa' ? 'kassa' : 'schap');
		if (is_wp_error($regels)) return new WP_Error($regels->get_error_code(), $regels->get_error_message(), ['status' => 502]);
		$out = [];
		foreach ($regels as $x) { $x['kandidaten'] = thh_vf_kandidaten($x['merk'], $x['product'], $x['variant']); $out[] = $x; }
		$stat = get_option('thh_vrr_foto_stat', ['fotos' => 0, 'regels' => 0, 'laatste' => '']);
		$stat['fotos']++; $stat['regels'] += count($out); $stat['laatste'] = current_time('d-m-Y H:i'); update_option('thh_vrr_foto_stat', $stat, false);
		return ['store' => $store, 'regels' => $out];
	}]);
});

/* ---------- knop en scherm in /voorraad ---------- */
/* De /voorraad-pagina wordt door snippet 242 in template_redirect uitgeschreven en eindigt met exit; er is geen
 * wp_footer. Daarom vangen we de uitvoer op en zetten we het script vlak voor </body>. */
add_action('template_redirect', function () {
	$uri = $_SERVER['REQUEST_URI'] ?? '';
	if (strpos($uri, '/voorraad') !== 0 || !thh_vf_can()) return;
	if (!in_array(thh_vf_store_now(), thh_vf_stores(), true)) return;
	$inject = thh_vf_inject(); // vooraf opbouwen: in een buffer-callback mag geen ob_start meer
	ob_start(function ($html) use ($inject) {
		$pos = strripos($html, '</body>');
		if ($pos === false) return $html . $inject;
		return substr($html, 0, $pos) . $inject . substr($html, $pos);
	});
}, -100);
function thh_vf_inject() {
	$nonce = wp_create_nonce('wp_rest'); $max = (int) thh_vf_opts()['max'];
	ob_start();
	?>
<style>
.thh-vf-btn{background:#14204a;color:#fff;border:0;border-radius:12px;padding:0 18px;font-weight:700;font-family:inherit;font-size:15px;cursor:pointer;height:58px;margin-left:8px;white-space:nowrap}
.thh-vf-wrap{margin-top:14px;border:1px solid #e3e6ef;border-radius:14px;padding:14px;background:#fff}
.thh-vf-wrap h3{margin:0 0 8px;color:#14204a;font-size:16px}
.thh-vf-busy{color:#5a6072;padding:10px 0}
.thh-vf-row{display:grid;grid-template-columns:48px 1fr 110px 90px;gap:10px;align-items:center;padding:8px 0;border-top:1px solid #eef0f5}
.thh-vf-row.laag{background:#fff4f4}.thh-vf-row.middel{background:#fffbe9}
.thh-vf-row img{width:48px;height:48px;object-fit:contain;border-radius:8px;background:#f5f6fa}
.thh-vf-row select,.thh-vf-row input{width:100%;font:inherit;padding:8px;border:1px solid #c9ccd6;border-radius:8px}
.thh-vf-gezien{font-size:12px;color:#5a6072}
.thh-vf-zk{font-size:11px;font-weight:700;text-transform:uppercase;text-align:center;border-radius:999px;padding:3px 6px}
.thh-vf-zk.hoog{background:#dff3e4;color:#1f7a3a}.thh-vf-zk.middel{background:#fde7b0;color:#7a5200}.thh-vf-zk.laag{background:#fbe0e0;color:#8a1c1c}
.thh-vf-acts{display:flex;gap:10px;margin-top:12px}
.thh-vf-acts button{flex:1;border:0;border-radius:12px;padding:14px;font-weight:700;font-family:inherit;font-size:15px;cursor:pointer}
.thh-vf-save{background:#e63946;color:#fff}.thh-vf-cancel{background:#eef0f5;color:#14204a}
.thh-vf-skip{font-size:12px;color:#8a1c1c;cursor:pointer;text-decoration:underline}
@media(max-width:640px){.thh-vf-row{grid-template-columns:40px 1fr 80px;}.thh-vf-row .thh-vf-zkc{grid-column:2/4}}
</style>
<script>
(function(){
var NONCE=<?php echo wp_json_encode($nonce); ?>,MAX=<?php echo $max; ?>,SYNC=<?php echo in_array(thh_vf_store_now(), array_filter(array_map('intval', explode(',', (string) get_option('thh_vrr_sync_stores', '60110')))), true) ? 'true' : 'false'; ?>;
function q(s,r){return (r||document).querySelector(s)}
function fixTekst(){if(!SYNC)return;var walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);var n;while((n=walker.nextNode())){if(n.nodeValue.indexOf('verandert niets aan de webshop')>-1){n.nodeValue=n.nodeValue.replace('en verandert niets aan de webshop','en wordt óók de voorraad van de webshop (geteld aantal = webshopaantal)');}}}
function init(){
  fixTekst();
  var scan=[].slice.call(document.querySelectorAll('button')).find(function(b){return /scan etiket/i.test(b.textContent)});
  if(!scan||q('.thh-vf-btn'))return false;
  var btn=document.createElement('button');btn.type='button';btn.className='thh-vf-btn';btn.textContent='📷 Foto tellen';
  var inp=document.createElement('input');inp.type='file';inp.accept='image/*';inp.multiple=true;inp.setAttribute('capture','environment');inp.style.display='none';
  scan.parentNode.insertBefore(btn,scan.nextSibling);scan.parentNode.appendChild(inp);
  btn.addEventListener('click',function(){inp.value='';inp.click()});
  inp.addEventListener('change',function(){if(inp.files&&inp.files.length)verwerk([].slice.call(inp.files),scan)});
  return true;
}
function verklein(file){return new Promise(function(res,rej){var img=new Image();var url=URL.createObjectURL(file);img.onload=function(){var s=Math.min(1,MAX/Math.max(img.width,img.height));var c=document.createElement('canvas');c.width=Math.round(img.width*s);c.height=Math.round(img.height*s);c.getContext('2d').drawImage(img,0,0,c.width,c.height);URL.revokeObjectURL(url);res(c.toDataURL('image/jpeg',0.85))};img.onerror=rej;img.src=url})}
function box(scan){var w=q('.thh-vf-wrap');if(!w){w=document.createElement('div');w.className='thh-vf-wrap';var card=scan.closest('div');(card.parentNode||document.body).insertBefore(w,card.nextSibling)}return w}
function verwerk(files,scan){
  var w=box(scan);var alle=[];var i=0;
  function stap(){
    if(i>=files.length){toon(w,alle);return}
    w.innerHTML='<h3>Foto tellen</h3><div class="thh-vf-busy">Foto '+(i+1)+' van '+files.length+' herkennen… 10 tot 20 seconden per foto.</div>';
    verklein(files[i]).then(function(data){
      return fetch('/wp-json/thh-foto/v1/tel',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':NONCE},body:JSON.stringify({foto:data})}).then(function(r){return r.json()});
    }).then(function(d){
      if(!d||!d.regels){w.innerHTML='<h3>Foto tellen</h3><div class="thh-vf-busy">Mislukt bij foto '+(i+1)+': '+((d&&d.message)||'onbekende fout')+'</div>';return}
      d.regels.forEach(function(r){r.foto=i+1;alle.push(r)});i++;stap();
    }).catch(function(e){w.innerHTML='<h3>Foto tellen</h3><div class="thh-vf-busy">Mislukt: '+e.message+'</div>'});
  }
  stap();
}
function esc(s){return String(s||'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function toon(w,regels){
  if(!regels.length){w.innerHTML='<h3>Foto tellen</h3><div class="thh-vf-busy">Geen producten herkend. Probeer dichterbij, recht van voren en met goed licht.</div>';return}
  var h='<h3>Foto tellen · '+regels.length+' regels herkend</h3><div class="thh-vf-busy">Controleer product en aantal per regel. Rode regels zijn onzeker: zelf nakijken of scannen. Regels die je niet wilt bewaren, zet je op "overslaan". Tip: een hele wand? Maak per plank of per meter een foto en kies ze in één keer; dubbele producten tel je dan bij elkaar op.</div>';
  regels.forEach(function(r,i){
    var opts='<option value="">— overslaan —</option>';r.kandidaten.forEach(function(k,j){opts+='<option value="'+k.pid+':'+k.vid+'"'+(j===0?' selected':'')+'>'+esc(k.naam)+'</option>'});
    var img=r.kandidaten[0]&&r.kandidaten[0].img?r.kandidaten[0].img:'';
    h+='<div class="thh-vf-row '+r.zekerheid+'" data-i="'+i+'"><img src="'+esc(img)+'" alt=""><div><select>'+opts+'</select><div class="thh-vf-gezien">'+(r.foto?'Foto '+r.foto+' · ':'')+'Gezien: '+esc([r.merk,r.product,r.variant].filter(Boolean).join(' · '))+'</div></div><input type="number" min="0" step="1" value="'+(r.aantal||0)+'"><div class="thh-vf-zkc"><div class="thh-vf-zk '+r.zekerheid+'">'+r.zekerheid+'</div></div></div>';
  });
  h+='<div class="thh-vf-acts"><button type="button" class="thh-vf-cancel">Annuleren</button><button type="button" class="thh-vf-save">Opslaan in register</button></div>';
  w.innerHTML=h;
  w.querySelectorAll('select').forEach(function(sel){sel.addEventListener('change',function(){var row=sel.closest('.thh-vf-row');var i=+row.dataset.i;var k=regels[i].kandidaten.find(function(x){return x.pid+':'+x.vid===sel.value});row.querySelector('img').src=k&&k.img?k.img:''})});
  q('.thh-vf-cancel',w).addEventListener('click',function(){w.remove()});
  q('.thh-vf-save',w).addEventListener('click',function(){opslaan(w)});
}
function opslaan(w){
  var rows=[].slice.call(w.querySelectorAll('.thh-vf-row')),todo=[];
  var som={};rows.forEach(function(row){var v=row.querySelector('select').value;var n=parseInt(row.querySelector('input').value,10);if(!v||isNaN(n))return;som[v]=(som[v]||0)+n});Object.keys(som).forEach(function(v){var p=v.split(':');todo.push({pid:+p[0],vid:+p[1],soort:'telling',aantal:som[v],notitie:'fototelling'})});
  if(!todo.length){alert('Niets geselecteerd.');return}
  var btn=q('.thh-vf-save',w);btn.disabled=true;btn.textContent='Opslaan… 0/'+todo.length;var ok=0,fout=0;
  (function next(i){if(i>=todo.length){w.innerHTML='<h3>Foto tellen</h3><div class="thh-vf-busy">'+ok+' regels opgeslagen'+(fout?', '+fout+' mislukt':'')+'. Maak de volgende foto of zoek een product.</div>';return}
    fetch('/wp-json/thh-vrr/v1/boek',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':NONCE},body:JSON.stringify(todo[i])}).then(function(r){return r.json()}).then(function(d){if(d&&d.ok)ok++;else fout++}).catch(function(){fout++}).then(function(){btn.textContent='Opslaan… '+(i+1)+'/'+todo.length;next(i+1)});
  })(0);
}
var n=0,t=setInterval(function(){if(init()||++n>40)clearInterval(t)},500);
})();
</script>
	<?php
	return ob_get_clean();
}

} // function_exists
