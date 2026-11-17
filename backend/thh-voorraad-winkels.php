<?php
/**
 * THH Voorraad per winkel — overzicht van alle winkels naast elkaar (18 nov 2026)
 *
 * Adres: thehealthhouse.nl/voorraad-winkels/
 * Toont per product de verwachte stand in elke winkel (uit het voorraadregister /voorraad,
 * snippet 242: laatste telling + ontvangsten − kassaverkopen sinds die telling), plus het totaal
 * en het getal dat de webshop en de kassa nu gebruiken. Alleen lezen, past niets aan.
 * Toegang: dezelfde als het voorraadregister (thh_vrr_user_can), dus ook kassamedewerkers.
 * Uitzetten = deze snippet deactiveren.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'init', function () {
	add_rewrite_rule( '^voorraad-winkels/?$', 'index.php?thh_vrw=1', 'top' );
	if ( get_option( 'thh_vrw_flushed' ) !== '1' ) {
		flush_rewrite_rules( false );
		update_option( 'thh_vrw_flushed', '1' );
	}
} );

add_filter( 'query_vars', function ( $v ) {
	$v[] = 'thh_vrw';
	return $v;
} );

add_action( 'rest_api_init', function () {
	register_rest_route( 'thh-vrw/v1', '/webshop', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return function_exists( 'thh_vrr_user_can' ) ? (bool) thh_vrr_user_can() : current_user_can( 'manage_woocommerce' );
		},
		'callback'            => function ( $req ) {
			$keys = (array) $req->get_param( 'keys' );
			$out  = array();
			foreach ( array_slice( $keys, 0, 2000 ) as $k ) {
				list( $pid, $vid ) = array_map( 'intval', array_pad( explode( ':', (string) $k ), 2, 0 ) );
				$p = wc_get_product( $vid ? $vid : $pid );
				if ( ! $p ) { continue; }
				$out[ $k ] = $p->managing_stock() ? (int) $p->get_stock_quantity() : ( $p->is_in_stock() ? 'op voorraad' : 'uitverkocht' );
			}
			return rest_ensure_response( $out );
		},
	) );
} );

add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'thh_vrw' ) ) { return; }
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( home_url( '/voorraad-winkels/' ) ) );
		exit;
	}
	$ok = function_exists( 'thh_vrr_user_can' ) ? (bool) thh_vrr_user_can() : current_user_can( 'manage_woocommerce' );
	if ( ! $ok ) { wp_die( 'Geen toegang tot de voorraad.', 'Voorraad', array( 'response' => 403 ) ); }
	$stores = array(
		60110 => 'Osdorpplein',
		60108 => 'IJburg',
		92924 => 'The Style Outlets',
		60111 => 'Heerhugowaard',
		60112 => 'Hoofddorp',
		74877 => 'Team Champ',
	);
	nocache_headers();
	?><!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Voorraad per winkel — The Health House</title>
<style>
:root{--navy:#14204a;--red:#e3262f;--line:#e3e6f0;--bg:#f5f6fa;--muted:#6b7190}
*{box-sizing:border-box}body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;background:var(--bg);color:#15192e}
header{background:var(--navy);color:#fff;padding:14px 18px;display:flex;gap:14px;align-items:center;flex-wrap:wrap;position:sticky;top:0;z-index:5}
header h1{font-size:18px;margin:0 12px 0 0}header a{color:#fff;opacity:.85;font-size:14px}
input[type=search]{flex:1 1 260px;max-width:460px;padding:10px 14px;border-radius:999px;border:0;font-size:15px}
label.f{font-size:13px;display:flex;gap:6px;align-items:center}
main{padding:14px}
.info{font-size:13px;color:var(--muted);margin:0 0 10px}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line);border-radius:10px;overflow:hidden;font-size:14px}
th,td{padding:8px 10px;border-bottom:1px solid var(--line);text-align:right;white-space:nowrap}
th{background:#fafbfd;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);position:sticky;top:62px}
td:first-child,th:first-child{text-align:left;white-space:normal}
td small{display:block;color:var(--muted);font-size:11.5px}
td.nul{color:var(--red);font-weight:700}td.leeg{color:#b6bacb}td.tot{font-weight:800}
.wrap{overflow-x:auto}
</style></head><body>
<header><h1>Voorraad per winkel</h1>
<input type="search" id="q" placeholder="Zoek op naam, merk, SKU of EAN…" autofocus>
<label class="f"><input type="checkbox" id="alleenGeteld" checked> alleen getelde producten</label>
<a href="<?php echo esc_url( home_url( '/voorraad/' ) ); ?>">Naar tellen</a></header>
<main><p class="info" id="info">Laden…</p><div class="wrap"><table><thead><tr><th>Product</th>
<?php foreach ( $stores as $id => $nm ) { echo '<th>' . esc_html( $nm ) . '</th>'; } ?>
<th>Totaal winkels</th><th>Webshop / kassa</th></tr></thead><tbody id="rows"></tbody></table></div></main>
<script>
(function(){
var STORES=<?php echo wp_json_encode( array_map( 'strval', array_keys( $stores ) ) ); ?>;
var NONCE=<?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
var API=<?php echo wp_json_encode( esc_url_raw( rest_url() ) ); ?>;
var data={},web={},counted={};
function get(u){return fetch(u,{headers:{'X-WP-Nonce':NONCE},credentials:'same-origin'}).then(function(r){return r.json();});}
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
Promise.all(STORES.map(function(id){return get(API+'thh-vrr/v1/overzicht?store='+id).then(function(j){
 counted[id]=0;(j.items||[]).forEach(function(it){var d=data[it.key]||(data[it.key]={naam:it.naam,merk:it.merk,sku:it.sku,ean:it.ean,s:{}});
 d.s[id]={v:it.verwacht,datum:it.datum};if(it.geteld!==null&&it.geteld!==undefined)counted[id]++;});}).catch(function(){counted[id]=-1;});}))
.then(function(){var keys=Object.keys(data);return fetch(API+'thh-vrw/v1/webshop',{method:'POST',headers:{'X-WP-Nonce':NONCE,'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({keys:keys})}).then(function(r){return r.json();}).then(function(j){web=j||{};});})
.then(render);
function render(){var q=document.getElementById('q').value.toLowerCase().trim(),only=document.getElementById('alleenGeteld').checked;
 var keys=Object.keys(data).sort(function(a,b){return String(data[a].naam).localeCompare(String(data[b].naam));});var html='',n=0;
 keys.forEach(function(k){var d=data[k];if(q&&(d.naam+' '+d.merk+' '+d.sku+' '+d.ean).toLowerCase().indexOf(q)<0)return;
  var tot=0,any=false,cells='';STORES.forEach(function(id){var s=d.s[id];if(s&&s.v!==null&&s.v!==undefined){any=true;tot+=Number(s.v);cells+='<td class="'+(Number(s.v)<=0?'nul':'')+'">'+esc(s.v)+'<small>'+esc(s.datum||'')+'</small></td>';}else cells+='<td class="leeg">–</td>';});
  if(only&&!any)return;n++;if(n>600)return;
  html+='<tr><td>'+esc(d.naam)+'<small>'+esc(d.merk)+(d.sku?' · '+esc(d.sku):'')+'</small></td>'+cells+'<td class="tot">'+(any?tot:'–')+'</td><td>'+esc(web[k]===undefined?'':web[k])+'</td></tr>';});
 document.getElementById('rows').innerHTML=html;
 var c=STORES.map(function(id){return document.querySelectorAll('thead th')[STORES.indexOf(id)+1].textContent+': '+(counted[id]<0?'niet te laden':counted[id]+' geteld');}).join(' · ');
 document.getElementById('info').textContent=n+' producten'+(n>600?' (eerste 600 getoond, zoek verder)':'')+'. '+c+'. Stand = laatste telling + ontvangsten − kassaverkopen sinds die telling; onder het getal de datum van de telling.';}
document.getElementById('q').addEventListener('input',function(){clearTimeout(window._t);window._t=setTimeout(render,150);});
document.getElementById('alleenGeteld').addEventListener('change',render);
})();
</script></body></html><?php
	exit;
} );
