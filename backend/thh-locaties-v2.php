<?php
/**
 * THH Locaties v2 — winkeloverzicht in de stijl van de nieuwe homepage.
 * Shortcode: [thh_locaties_v2]. Gegevens komen live uit WP Store Locator (wpsl_stores).
 * Testpagina /locaties-v2/ (noindex zolang het een test is).
 */

add_filter('wp_robots', function ($r) { if (is_page('locaties-v2')) { $r['noindex'] = true; $r['nofollow'] = true; } return $r; }, 99);
add_filter('wpseo_robots', function ($r) { return is_page('locaties-v2') ? 'noindex, nofollow' : $r; }, 99);

/** Foto per winkel (post-id => url). Aan te vullen via optie thh_loc_fotos. */
function thh_loc_fotos() {
	$u = 'https://thehealthhouse.nl/wp-content/uploads/2026/11/';
	$def = [45949 => $u . 'thh-over-kiosk-vier-meren.webp'];
	$opt = get_option('thh_loc_fotos', []);
	return (is_array($opt) ? $opt : []) + $def;
}

function thh_loc_winkels() {
	$posts = get_posts(['post_type' => 'wpsl_stores', 'post_status' => 'publish', 'numberposts' => 50, 'orderby' => 'menu_order title', 'order' => 'ASC']);
	$foto = thh_loc_fotos();
	$out = [];
	foreach ($posts as $p) {
		$m = function ($k) use ($p) { return trim((string) get_post_meta($p->ID, 'wpsl_' . $k, true)); };
		$hours = get_post_meta($p->ID, 'wpsl_hours', true);
		$out[] = [
			'id' => $p->ID, 'naam' => get_the_title($p), 'adres' => $m('address'), 'adres2' => $m('address2'),
			'pc' => $m('zip'), 'plaats' => $m('city'), 'tel' => $m('phone'), 'lat' => (float) $m('lat'), 'lng' => (float) $m('lng'),
			'uren' => is_array($hours) ? $hours : [], 'foto' => $foto[$p->ID] ?? '',
		];
	}
	// outlet en kiosk achteraan houden we gewoon in titelvolgorde; Halfweg zonder uren = binnenkort open
	return $out;
}

add_shortcode('thh_locaties_v2', function () {
	$w = thh_loc_winkels();
	$u = function ($p) { return esc_url(strpos($p, 'http') === 0 ? $p : home_url($p)); };
	$dagen = ['monday' => 'Maandag', 'tuesday' => 'Dinsdag', 'wednesday' => 'Woensdag', 'thursday' => 'Donderdag', 'friday' => 'Vrijdag', 'saturday' => 'Zaterdag', 'sunday' => 'Zondag'];
	$pin = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-5.5-7-11a7 7 0 0114 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>';
	$route = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l18-8-8 18-2-8z"/></svg>';
	$clock = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
	$kaart = [];
	ob_start(); ?>
<div class="lc">
	<section class="lc-hero">
		<div class="lc-herotxt">
			<span class="lc-kicker">Onze winkels</span>
			<h1>Kom langs <em>in de winkel</em></h1>
			<p>Persoonlijk advies, alles even vasthouden en proeven, of je online bestelling ophalen. Kies hieronder de winkel die het dichtst bij je is.</p>
		</div>
		<div class="lc-herostats"><div><b><?php echo count($w); ?></b><span>locaties</span></div><div><b>Gratis</b><span>ophalen</span></div><div><b>Advies</b><span>van mensen die zelf trainen</span></div></div>
	</section>

	<section class="lc-wrap">
		<div class="lc-list">
			<?php foreach ($w as $s) :
				$adres = trim($s['adres'] . ', ' . $s['pc'] . ' ' . $s['plaats'], ', ');
				$dest = rawurlencode('The Health House ' . $s['adres'] . ' ' . $s['pc'] . ' ' . $s['plaats']);
				$heeft_uren = false; $rows = '';
				$uren_js = [];
				foreach ($dagen as $k => $nl) {
					$sl = isset($s['uren'][$k]) && is_array($s['uren'][$k]) ? $s['uren'][$k] : [];
					$txt = $sl ? implode(' en ', array_map(function ($x) { return str_replace(',', ' – ', $x); }, $sl)) : 'Gesloten';
					if ($sl) $heeft_uren = true;
					$uren_js[] = array_map(function ($x) { return explode(',', $x); }, $sl);
					$rows .= '<li><span>' . $nl . '</span><span>' . esc_html($txt) . '</span></li>';
				}
				$kaart[] = ['id' => $s['id'], 'n' => $s['naam'], 'lat' => $s['lat'], 'lng' => $s['lng'], 'a' => $adres];
			?>
			<article class="lc-card" id="winkel-<?php echo (int) $s['id']; ?>" data-id="<?php echo (int) $s['id']; ?>" data-uren='<?php echo esc_attr(wp_json_encode($uren_js)); ?>'>
				<div class="lc-foto"><?php if ($s['foto']) : ?><img src="<?php echo esc_url($s['foto']); ?>" alt="<?php echo esc_attr($s['naam']); ?>" loading="lazy"><?php else : ?><span class="lc-ph"><?php echo $pin; ?></span><?php endif; ?></div>
				<div class="lc-info">
					<div class="lc-top"><h2><?php echo esc_html($s['naam']); ?></h2><?php if ($heeft_uren) : ?><span class="lc-status" data-status></span><?php else : ?><span class="lc-status lc-soon">Binnenkort open</span><?php endif; ?></div>
					<p class="lc-adres"><?php echo esc_html($s['adres']); ?><?php if ($s['adres2']) echo ' · ' . esc_html($s['adres2']); ?><br><?php echo esc_html(trim($s['pc'] . ' ' . $s['plaats'])); ?></p>
					<?php if ($heeft_uren) : ?><details class="lc-uren"><summary><?php echo $clock; ?><span data-vandaag>Openingstijden</span></summary><ul><?php echo $rows; ?></ul></details><?php endif; ?>
					<div class="lc-btns">
						<a class="lc-btn lc-btn-nv" href="https://www.google.com/maps/dir/?api=1&amp;destination=<?php echo $dest; ?>" target="_blank" rel="noopener nofollow"><?php echo $route; ?>Route</a>
						<button type="button" class="lc-btn lc-btn-line" data-kaart="<?php echo (int) $s['id']; ?>"><?php echo $pin; ?>Op de kaart</button>
						<?php if ($s['tel']) : ?><a class="lc-btn lc-btn-line" href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $s['tel'])); ?>">Bel</a><?php endif; ?>
					</div>
				</div>
			</article>
			<?php endforeach; ?>
		</div>
		<div class="lc-mapcol"><div id="lcMap" class="lc-map" aria-label="Kaart met onze winkels"></div></div>
	</section>

	<section class="lc-band">
		<div><span class="lc-kicker">Liever online?</span><h2>Bestel online, haal op in de winkel</h2><p>Kies bij het afrekenen voor ophalen. Je krijgt een bericht zodra je bestelling klaarligt. Vragen? Stuur ons een WhatsApp.</p></div>
		<div class="lc-ctas"><a class="lc-btn lc-btn-red" href="<?php echo $u('/shop/'); ?>">Naar de webshop</a><a class="lc-btn lc-btn-ghost" href="https://wa.me/31611789629" target="_blank" rel="noopener nofollow">WhatsApp ons</a></div>
	</section>

	<section class="lc-fr">
		<div><strong>Zelf een The Health House beginnen?</strong><span>Lees hoe franchise bij ons werkt.</span></div><a class="lc-btn lc-btn-nv" href="<?php echo $u('/franchise/'); ?>">Meer over franchise</a>
	</section>
</div>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script>
(function(){
	var W=<?php echo wp_json_encode($kaart); ?>;
	function pad(n){return (n<10?'0':'')+n}
	function nu(){var d=new Date(new Date().toLocaleString('en-US',{timeZone:'Europe/Amsterdam'}));return d}
	function status(){
		var d=nu(),dag=(d.getDay()+6)%7,min=d.getHours()*60+d.getMinutes();
		document.querySelectorAll('.lc-card[data-uren]').forEach(function(c){
			var u;try{u=JSON.parse(c.getAttribute('data-uren'))}catch(e){return}
			var el=c.querySelector('[data-status]'),vd=c.querySelector('[data-vandaag]');if(!el)return;
			var sl=u[dag]||[],open=false,tot='';
			sl.forEach(function(s){var a=s[0].split(':'),b=s[1].split(':'),f=+a[0]*60+ +a[1],t=+b[0]*60+ +b[1];if(min>=f&&min<t){open=true;tot=s[1]}});
			el.textContent=open?'Nu open':'Gesloten';el.className='lc-status '+(open?'lc-open':'lc-dicht');
			if(vd)vd.textContent=sl.length?('Vandaag '+sl.map(function(s){return s[0]+' – '+s[1]}).join(', ')):'Vandaag gesloten';
			var li=c.querySelectorAll('.lc-uren li');if(li[dag])li[dag].classList.add('lc-today');
		});
	}
	status();
	function kaart(){
		if(!window.L||!document.getElementById('lcMap'))return setTimeout(kaart,150);
		var m=L.map('lcMap',{scrollWheelZoom:false}),mk={},b=[];
		L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap',maxZoom:19,className:'lc-tiles'}).addTo(m);
		var ic=L.divIcon({className:'lc-mk',html:'<span></span>',iconSize:[30,38],iconAnchor:[15,36],popupAnchor:[0,-30]});
		W.forEach(function(s){if(!s.lat)return;var x=L.marker([s.lat,s.lng],{icon:ic}).addTo(m).bindPopup('<strong>'+s.n+'</strong><br>'+s.a);mk[s.id]=x;b.push([s.lat,s.lng])});
		if(b.length)m.fitBounds(b,{padding:[30,30]});
		document.querySelectorAll('[data-kaart]').forEach(function(btn){btn.addEventListener('click',function(){var x=mk[btn.getAttribute('data-kaart')];if(!x)return;var mc=document.querySelector('.lc-mapcol');if(window.innerWidth<900)mc.scrollIntoView({behavior:'smooth',block:'center'});m.setView(x.getLatLng(),14);x.openPopup()})});
	}
	if(document.readyState!=='loading')kaart();else document.addEventListener('DOMContentLoaded',kaart);
})();
</script>
<style>
.lc{--nv:#14204a;--rd:#e2373f;--bg:#f5f6fa;--tx:#1b1f2e;--mut:#5a6072;--gr:#1f9d55;max-width:1240px;margin:0 auto;padding:16px 20px 48px;color:var(--tx);font-family:inherit;overflow-x:clip}
.lc a{text-decoration:none}.lc h1,.lc h2{margin:0;color:var(--nv);line-height:1.15;letter-spacing:-.01em;text-transform:none}
.lc-kicker{display:inline-block;align-self:flex-start;background:rgba(255,255,255,.14);color:#fff;font-weight:600;font-size:12px;letter-spacing:.12em;text-transform:uppercase;padding:8px 14px;border-radius:6px}
.lc-hero{background:var(--nv);color:#fff;border-radius:24px;padding:44px 56px;display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:32px;align-items:center;position:relative;overflow:hidden}
.lc-hero:before{content:"";position:absolute;right:-10%;top:-20%;width:60%;height:140%;background:linear-gradient(120deg,transparent 40%,rgba(255,255,255,.06) 40%,rgba(255,255,255,.06) 55%,transparent 55%);pointer-events:none}
.lc-herotxt{display:flex;flex-direction:column;gap:16px;position:relative;z-index:1}
.lc .lc-hero h1{color:#fff;font-size:clamp(34px,4.6vw,60px);font-weight:900;text-transform:uppercase;line-height:1.02}.lc-hero h1 em{display:block;font-style:normal;color:transparent;-webkit-text-stroke:2px #fff}
.lc-hero p{margin:0;font-size:16px;line-height:1.55;color:rgba(255,255,255,.85);max-width:520px}
.lc-herostats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;position:relative;z-index:1}
.lc-herostats div{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:4px}
.lc-herostats b{font-size:22px;font-weight:700;color:#fff}.lc-herostats span{font-size:12.5px;color:rgba(255,255,255,.75);line-height:1.35}
.lc-wrap{margin-top:32px;display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:24px;align-items:start}
.lc-list{display:flex;flex-direction:column;gap:14px}
.lc-card{display:grid;grid-template-columns:150px minmax(0,1fr);gap:18px;background:#fff;border:1px solid #e6e8f0;border-radius:18px;padding:14px;scroll-margin-top:120px}
.lc-foto{border-radius:12px;overflow:hidden;background:var(--bg);min-height:150px}.lc-foto img{width:100%;height:100%;object-fit:cover;display:block}
.lc-ph{display:grid;place-items:center;width:100%;height:100%;color:#c3c8d8}.lc-ph svg{width:40px;height:40px}
.lc-info{display:flex;flex-direction:column;gap:8px;min-width:0;padding:4px 4px 2px 0}
.lc-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}.lc .lc-top h2{font-size:18px;font-weight:700}
.lc-status{flex:none;font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;background:var(--bg);color:var(--mut);white-space:nowrap}
.lc-status:empty{display:none}.lc-open{background:#e7f6ee;color:var(--gr)}.lc-dicht{background:#fdecee;color:var(--rd)}.lc-soon{background:#fff4e0;color:#a15c00}
.lc-adres{margin:0;font-size:14px;line-height:1.5;color:var(--mut)}
.lc-uren summary{list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-size:13.5px;font-weight:500;color:var(--nv)}.lc-uren summary::-webkit-details-marker{display:none}
.lc-uren summary svg{width:16px;height:16px}.lc-uren summary:after{content:"";width:7px;height:7px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);margin:-3px 0 0 4px;transition:transform .15s}.lc-uren[open] summary:after{transform:rotate(-135deg);margin-top:3px}
.lc-uren ul{list-style:none;margin:8px 0 0;padding:10px 12px;background:var(--bg);border-radius:10px;display:flex;flex-direction:column;gap:3px}
.lc-uren li{display:flex;justify-content:space-between;gap:12px;font-size:13px;color:var(--mut)}.lc-uren li.lc-today{color:var(--nv);font-weight:600}
.lc-btns{display:flex;flex-wrap:wrap;gap:8px;margin-top:auto;padding-top:4px}
.lc .lc-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border-radius:999px;padding:0 18px;min-height:40px;font-weight:600;font-size:14px;line-height:1;white-space:nowrap;cursor:pointer;font-family:inherit;text-transform:none;letter-spacing:0;border:0;box-shadow:none}
.lc .lc-btn svg{width:16px;height:16px;flex:none}
.lc .lc-btn-nv{background:var(--nv);color:#fff}.lc .lc-btn-nv:hover,.lc .lc-btn-nv:focus{background:#0d1538;color:#fff}
.lc .lc-btn-line{background:#fff;color:var(--nv);border:1.5px solid #d5d9e6}.lc .lc-btn-line:hover,.lc .lc-btn-line:focus{background:var(--bg);color:var(--nv);border-color:var(--nv)}
.lc .lc-btn-red{background:var(--rd);color:#fff;min-height:48px;padding:0 26px;font-size:15px}.lc .lc-btn-red:hover{background:#c92c33;color:#fff}
.lc .lc-btn-ghost{background:transparent;border:2px solid rgba(255,255,255,.7);color:#fff;min-height:48px;padding:0 26px;font-size:15px}.lc .lc-btn-ghost:hover{background:rgba(255,255,255,.12);color:#fff}
.lc-mapcol{position:sticky;top:110px}.lc-map{height:640px;border-radius:18px;overflow:hidden;background:var(--bg);border:1px solid #e6e8f0;z-index:0}
.lc-mk span{display:block;width:30px;height:30px;border-radius:50% 50% 50% 0;background:var(--rd,#e2373f);transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 4px 10px rgba(0,0,0,.25)}
.lc .lc-tiles{filter:saturate(.55) contrast(.95)}.lc-card:not(:has(.lc-foto img)){grid-template-columns:minmax(0,1fr);padding:18px 20px}.lc-card:not(:has(.lc-foto img)) .lc-foto{display:none}
.lc .leaflet-popup-content{font-family:inherit;font-size:13px;color:#1b1f2e}
.lc-band{margin-top:48px;background:var(--nv);color:#fff;border-radius:24px;padding:40px 56px;display:grid;grid-template-columns:minmax(0,1.3fr) auto;gap:32px;align-items:center}
.lc-band>div:first-child{display:flex;flex-direction:column;gap:12px}.lc .lc-band h2{color:#fff;font-size:30px;font-weight:700}.lc-band p{margin:0;font-size:15.5px;line-height:1.6;color:rgba(255,255,255,.82);max-width:560px}
.lc-ctas{display:flex;gap:12px;flex-wrap:wrap}
.lc-fr{margin-top:16px;background:var(--bg);border-radius:18px;padding:22px 28px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.lc-fr div{display:flex;flex-direction:column;gap:2px}.lc-fr strong{color:var(--nv);font-size:17px}.lc-fr span{color:var(--mut);font-size:14px}
@media(max-width:900px){.lc-hero{grid-template-columns:minmax(0,1fr);padding:32px 28px}.lc-wrap{grid-template-columns:minmax(0,1fr)}.lc-mapcol{position:static;order:-1}.lc-map{height:340px}.lc-band{grid-template-columns:minmax(0,1fr);padding:32px 28px}}
@media(max-width:640px){.lc{padding:12px 14px 32px}.lc-hero{border-radius:18px;padding:24px 20px;gap:18px}.lc .lc-hero h1{font-size:32px}.lc-hero p{font-size:15px}.lc-herostats{gap:8px}.lc-herostats div{padding:12px 10px}.lc-herostats b{font-size:17px}.lc-herostats span{font-size:11.5px}
.lc-card{grid-template-columns:minmax(0,1fr);gap:12px;padding:12px}.lc-foto{min-height:0;aspect-ratio:16/8}.lc-foto:has(.lc-ph){display:none}.lc-btns .lc-btn{flex:1 1 auto}.lc-map{height:280px}
.lc-band{border-radius:18px;padding:26px 20px}.lc .lc-band h2{font-size:24px}.lc-ctas{flex-direction:column;align-items:stretch}.lc-fr{padding:18px 20px}.lc-fr .lc-btn{width:100%}}
</style>
<?php
	return ob_get_clean();
});
