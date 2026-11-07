<?php
/**
 * THH — Foto afrekenen (kassa)
 *
 * Knop "Foto" in de kassa (/pos/). De medewerker fotografeert de producten op de toonbank; de foto's
 * gaan door dezelfde herkenning als "Foto tellen" (snippet Foto tellen, endpoint thh-foto/v1/tel).
 * De herkende regels komen in een controlelijst: product (keuze uit kandidaten, incl. smaak/maat),
 * aantal, zekerheid. Pas na "Zet in winkelwagen" maakt de server een open kassa-order (status pos-open,
 * dezelfde velden als een order die de kassa zelf maakt) en opent de kassa die winkelwagen. Daarna
 * rekent de medewerker gewoon af met PIN of CONTANT. Niets gaat zonder controle de bon op.
 *
 * Winkels: dezelfde instelling als Foto tellen (WooCommerce › Foto tellen).
 */
if (!defined('ABSPATH')) return;

if (!function_exists('thh_kf_order')) {

function thh_kf_order($store, $items, $user_id) {
	$order = wc_create_order(['created_via' => 'woocommerce-pos', 'customer_id' => 0]);
	if (is_wp_error($order)) return $order;
	$n = 0;
	foreach ($items as $it) {
		$pid = (int) ($it['pid'] ?? 0); $vid = (int) ($it['vid'] ?? 0); $qty = max(1, (int) ($it['aantal'] ?? 1));
		$p = wc_get_product($vid ?: $pid);
		if (!$p || !$p->is_purchasable()) continue;
		$item_id = $order->add_product($p, $qty);
		$item = $item_id ? $order->get_item($item_id) : null;
		if ($item) { // zelfde regelvelden als de kassa zelf schrijft
			$item->update_meta_data('_woocommerce_pos_data', ['price' => (string) wc_get_price_to_display($p), 'regular_price' => (string) wc_get_price_to_display($p, ['price' => $p->get_regular_price()]), 'tax_status' => $p->get_tax_status()]);
			$item->update_meta_data('_woocommerce_pos_uuid', wp_generate_uuid4());
			$item->save();
		}
		$n++;
	}
	if (!$n) { $order->delete(true); return new WP_Error('leeg', 'Geen geldige producten.'); }
	$uuid = wp_generate_uuid4();
	$order->update_meta_data('_woocommerce_pos_uuid', $uuid);
	$order->update_meta_data('_woocommerce_pos_version', defined('WCPOS_VERSION') ? WCPOS_VERSION : '1.11.11');
	$order->update_meta_data('_pos_store', (int) $store);
	$order->update_meta_data('_pos_user', (int) $user_id);
	$order->update_meta_data('_pos_user_created', (int) $user_id);
	$order->update_meta_data('_woocommerce_pos_tax_based_on', 'base');
	$order->update_meta_data('_thh_kassa_foto', '1');
	$order->add_order_note('Winkelwagen gevuld via Foto afrekenen; medewerker heeft de regels gecontroleerd.');
	$order->calculate_totals();
	$order->set_status('pos-open');
	$order->save();
	return ['id' => $order->get_id(), 'uuid' => $uuid, 'regels' => $n, 'totaal' => $order->get_total()];
}

add_action('rest_api_init', function () {
	register_rest_route('thh-foto/v1', '/kassa-order', ['methods' => 'POST', 'permission_callback' => function () { return function_exists('thh_vf_can') && thh_vf_can(); }, 'callback' => function ($r) {
		$store = (int) $r->get_param('store');
		if (!$store || !function_exists('thh_vf_stores') || !in_array($store, thh_vf_stores(), true)) return new WP_Error('store', 'Foto afrekenen staat voor deze winkel niet aan.', ['status' => 403]);
		$items = (array) $r->get_param('items');
		if (!$items) return new WP_Error('leeg', 'Geen regels.', ['status' => 400]);
		$res = thh_kf_order($store, $items, get_current_user_id());
		if (is_wp_error($res)) return new WP_Error($res->get_error_code(), $res->get_error_message(), ['status' => 400]);
		return $res;
	}]);
});

/* Knop en scherm in de kassa (/pos/ heeft geen wp_footer; uitvoer opvangen zoals bij Foto tellen) */
add_action('template_redirect', function () {
	$uri = $_SERVER['REQUEST_URI'] ?? '';
	if (!preg_match('#^/pos(/|$|\?)#', $uri) || !function_exists('thh_vf_can') || !thh_vf_can()) return;
	$stores = [];
	foreach (get_posts(['post_type' => 'wcpos_store', 'post_status' => 'publish', 'numberposts' => 50, 'fields' => 'ids']) as $sid) $stores[(int) $sid] = get_the_title($sid);
	$aan = function_exists('thh_vf_stores') ? thh_vf_stores() : [];
	$nonce = wp_create_nonce('wp_rest'); $max = function_exists('thh_vf_opts') ? (int) thh_vf_opts()['max'] : 1600;
	ob_start();
	?>
<style>
.thh-kf-btn{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;margin:0 4px;flex:0 0 auto;background:#14204a;color:#fff;border:0;border-radius:10px;font-size:17px;line-height:1;cursor:pointer;vertical-align:middle}.thh-kf-btn.is-los{position:fixed;left:14px;bottom:142px;z-index:99998;width:auto;height:auto;border-radius:999px;padding:10px 16px;font-weight:700;font-family:inherit;font-size:13px;box-shadow:0 4px 14px rgba(20,32,74,.3);letter-spacing:.04em}
.thh-kf-ov{position:fixed;inset:0;z-index:99999;background:rgba(20,32,74,.55);display:flex;align-items:center;justify-content:center;padding:16px}
.thh-kf-box{background:#fff;border-radius:16px;max-width:760px;width:100%;max-height:92vh;overflow:auto;padding:18px;font-family:Poppins,Inter,Arial,sans-serif;color:#1b1f2e}
.thh-kf-box h3{margin:0 0 6px;color:#14204a;font-size:18px}
.thh-kf-busy{color:#5a6072;padding:8px 0}
.thh-kf-row{display:grid;grid-template-columns:48px 1fr 100px 90px;gap:10px;align-items:center;padding:8px 0;border-top:1px solid #eef0f5}
.thh-kf-row.laag{background:#fff4f4}.thh-kf-row.middel{background:#fffbe9}
.thh-kf-row img{width:48px;height:48px;object-fit:contain;border-radius:8px;background:#f5f6fa}
.thh-kf-row select,.thh-kf-row input{width:100%;font:inherit;padding:8px;border:1px solid #c9ccd6;border-radius:8px;box-sizing:border-box}
.thh-kf-gezien{font-size:12px;color:#5a6072}
.thh-kf-zk{font-size:11px;font-weight:700;text-transform:uppercase;text-align:center;border-radius:999px;padding:3px 6px}
.thh-kf-zk.hoog{background:#dff3e4;color:#1f7a3a}.thh-kf-zk.middel{background:#fde7b0;color:#7a5200}.thh-kf-zk.laag{background:#fbe0e0;color:#8a1c1c}
.thh-kf-acts{display:flex;gap:10px;margin-top:14px}
.thh-kf-acts button{flex:1;border:0;border-radius:12px;padding:14px;font-weight:700;font-family:inherit;font-size:15px;cursor:pointer}
.thh-kf-go{background:#e63946;color:#fff}.thh-kf-cancel{background:#eef0f5;color:#14204a}
@media(max-width:640px){.thh-kf-row{grid-template-columns:40px 1fr 70px}.thh-kf-row .thh-kf-zkc{grid-column:2/4}}
</style>
<script>
(function(){
var NONCE=<?php echo wp_json_encode($nonce); ?>,MAX=<?php echo $max; ?>,STORES=<?php echo wp_json_encode($stores); ?>,AAN=<?php echo wp_json_encode(array_values($aan)); ?>;
function storeId(){if(window.__thhPosStore>0)return +window.__thhPosStore;var t=document.title+' '+(document.querySelector('header,[class*="header"]')||{}).textContent;var best=0,bl=0;Object.keys(STORES).forEach(function(id){var n=STORES[id];if(t.indexOf(n)>-1&&n.length>bl){best=+id;bl=n.length}});return best}
function q(s,r){return (r||document).querySelector(s)}
function init(){
  if(q('.thh-kf-btn'))return true;
  var sid=storeId();if(!sid)return false;
  if(AAN.indexOf(sid)<0){return true}
  var btn=document.createElement('button');btn.type='button';btn.className='thh-kf-btn';btn.title='Foto afrekenen';btn.setAttribute('aria-label','Foto afrekenen');
  var inp=document.createElement('input');inp.type='file';inp.accept='image/*';inp.multiple=true;inp.setAttribute('capture','environment');inp.style.display='none';
  /* klein icoontje naast het zoekveld (bij het scan-icoon); lukt dat niet, dan een losse knop linksonder */
  var zoek=[].slice.call(document.querySelectorAll('input')).filter(function(i){return /zoek|search/i.test(i.placeholder||'')&&i.offsetWidth>120})[0];
  if(zoek){btn.textContent='📷';zoek.insertAdjacentElement('afterend',btn)}
  else{btn.textContent='📷 FOTO AFREKENEN';btn.className+=' is-los';document.body.appendChild(btn)}
  document.body.appendChild(inp);
  btn.addEventListener('click',function(){inp.value='';inp.click()});
  inp.addEventListener('change',function(){if(inp.files&&inp.files.length)verwerk([].slice.call(inp.files),sid)});
  return true;
}
function verklein(file){return new Promise(function(res,rej){var img=new Image();var url=URL.createObjectURL(file);img.onload=function(){var s=Math.min(1,MAX/Math.max(img.width,img.height));var c=document.createElement('canvas');c.width=Math.round(img.width*s);c.height=Math.round(img.height*s);c.getContext('2d').drawImage(img,0,0,c.width,c.height);URL.revokeObjectURL(url);res(c.toDataURL('image/jpeg',0.85))};img.onerror=rej;img.src=url})}
function overlay(){var o=q('.thh-kf-ov');if(!o){o=document.createElement('div');o.className='thh-kf-ov';o.innerHTML='<div class="thh-kf-box"></div>';document.body.appendChild(o)}return q('.thh-kf-box',o)}
function sluit(){var o=q('.thh-kf-ov');if(o)o.remove()}
function esc(s){return String(s||'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function verwerk(files,sid){
  var w=overlay();var alle=[];var i=0;
  function stap(){
    if(i>=files.length){toon(w,alle,sid);return}
    w.innerHTML='<h3>Foto afrekenen</h3><div class="thh-kf-busy">Foto '+(i+1)+' van '+files.length+' herkennen… 10 tot 20 seconden per foto.</div><div class="thh-kf-acts"><button type="button" class="thh-kf-cancel">Annuleren</button></div>';
    q('.thh-kf-cancel',w).addEventListener('click',function(){i=999;sluit()});
    verklein(files[i]).then(function(data){
      return fetch('/wp-json/thh-foto/v1/tel?store='+sid+'&context=kassa',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':NONCE},body:JSON.stringify({foto:data})}).then(function(r){return r.json()});
    }).then(function(d){
      if(i>=999)return;
      if(!d||!d.regels){w.innerHTML='<h3>Foto afrekenen</h3><div class="thh-kf-busy">Mislukt: '+esc((d&&d.message)||'onbekende fout')+'</div><div class="thh-kf-acts"><button type="button" class="thh-kf-cancel">Sluiten</button></div>';q('.thh-kf-cancel',w).addEventListener('click',sluit);return}
      d.regels.forEach(function(r){r.foto=i+1;alle.push(r)});i++;stap();
    }).catch(function(e){w.innerHTML='<h3>Foto afrekenen</h3><div class="thh-kf-busy">Mislukt: '+esc(e.message)+'</div><div class="thh-kf-acts"><button type="button" class="thh-kf-cancel">Sluiten</button></div>';q('.thh-kf-cancel',w).addEventListener('click',sluit)});
  }
  stap();
}
function toon(w,regels,sid){
  if(!regels.length){w.innerHTML='<h3>Foto afrekenen</h3><div class="thh-kf-busy">Geen producten herkend. Leg de producten los van elkaar met het etiket naar de camera en probeer opnieuw.</div><div class="thh-kf-acts"><button type="button" class="thh-kf-cancel">Sluiten</button></div>';q('.thh-kf-cancel',w).addEventListener('click',sluit);return}
  var h='<h3>Controleer de bon · '+regels.length+' regels</h3><div class="thh-kf-busy">Kijk bij elke regel of product, smaak of maat en aantal kloppen. Rode regels zijn onzeker. Zet een regel op "overslaan" als het niet klopt en zoek dat product daarna gewoon op. Pas na "Zet in winkelwagen" komt het op de bon.</div>';
  regels.forEach(function(r,i){
    var opts='<option value="">— overslaan —</option>';(r.kandidaten||[]).forEach(function(k,j){opts+='<option value="'+k.pid+':'+k.vid+'"'+(j===0?' selected':'')+'>'+esc(k.naam)+'</option>'});
    var img=r.kandidaten&&r.kandidaten[0]&&r.kandidaten[0].img?r.kandidaten[0].img:'';
    h+='<div class="thh-kf-row '+r.zekerheid+'" data-i="'+i+'"><img src="'+esc(img)+'" alt=""><div><select>'+opts+'</select><div class="thh-kf-gezien">'+(r.foto?'Foto '+r.foto+' · ':'')+'Gezien: '+esc([r.merk,r.product,r.variant].filter(Boolean).join(' · '))+'</div></div><input type="number" min="1" step="1" value="'+Math.max(1,r.aantal||1)+'"><div class="thh-kf-zkc"><div class="thh-kf-zk '+r.zekerheid+'">'+r.zekerheid+'</div></div></div>';
  });
  h+='<div class="thh-kf-acts"><button type="button" class="thh-kf-cancel">Annuleren</button><button type="button" class="thh-kf-go">Zet in winkelwagen</button></div>';
  w.innerHTML=h;
  w.querySelectorAll('select').forEach(function(sel){sel.addEventListener('change',function(){var row=sel.closest('.thh-kf-row');var i=+row.dataset.i;var k=(regels[i].kandidaten||[]).find(function(x){return x.pid+':'+x.vid===sel.value});row.querySelector('img').src=k&&k.img?k.img:''})});
  q('.thh-kf-cancel',w).addEventListener('click',sluit);
  q('.thh-kf-go',w).addEventListener('click',function(){
    var som={};w.querySelectorAll('.thh-kf-row').forEach(function(row){var v=row.querySelector('select').value;var n=parseInt(row.querySelector('input').value,10);if(!v||!(n>0))return;som[v]=(som[v]||0)+n});
    var items=Object.keys(som).map(function(v){var p=v.split(':');return {pid:+p[0],vid:+p[1],aantal:som[v]}});
    if(!items.length){alert('Geen regels geselecteerd.');return}
    var btn=q('.thh-kf-go',w);btn.disabled=true;btn.textContent='Winkelwagen maken…';
    fetch('/wp-json/thh-foto/v1/kassa-order',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':NONCE},body:JSON.stringify({store:sid,items:items})}).then(function(r){return r.json()}).then(function(d){
      if(!d||!d.uuid){btn.disabled=false;btn.textContent='Zet in winkelwagen';alert('Mislukt: '+((d&&d.message)||'onbekende fout'));return}
      w.innerHTML='<h3>Winkelwagen klaar</h3><div class="thh-kf-busy">'+d.regels+' regels, totaal € '+String(d.totaal).replace('.',',')+'. De kassa opent nu deze winkelwagen; reken daarna af met PIN of CONTANT.</div>';
      setTimeout(function(){window.location.href='/pos/cart/'+d.uuid},800);
    }).catch(function(e){btn.disabled=false;btn.textContent='Zet in winkelwagen';alert('Mislukt: '+e.message)});
  });
}
setInterval(init,1500); /* de kassa-app ververst het body-element na het laden: knop steeds opnieuw zetten als hij weg is */
})();
</script>
	<?php
	$inject = ob_get_clean();
	ob_start(function ($html) use ($inject) {
		$pos = strripos($html, '</body>');
		if ($pos === false) return $html . $inject;
		return substr($html, 0, $pos) . $inject . substr($html, $pos);
	});
}, -100);

} // function_exists
