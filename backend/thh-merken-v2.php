<?php
/**
 * THH Merkenpagina v2 — vervangt [merkenpagina] (en biedt [thh_merken]).
 * Opzet naar thheurope.com/#merken: zoekveld, aantal, letter-navigatie, witte logo-tegels.
 * Logo-bron (in volgorde): term-meta thumbnail_id (mediabibliotheek) → THH-logo → logo-repo op jsDelivr → merknaam als tekst.
 * REST (admin): GET /thh/v1/merken/status, POST /thh/v1/merken/logo {term_id|slug, url} (sideload naar media + koppelen),
 *               POST /thh/v1/merken/logo-batch {items:[{slug,url}]}, POST /thh/v1/merken/logo-remove {term_id}.
 */

if (!function_exists('thh_brand_taxonomy')) {
	function thh_brand_taxonomy() {
		foreach (['product_brand', 'brands', 'pa_merk'] as $tx) if (taxonomy_exists($tx)) return $tx;
		return 'product_brand';
	}
}

if (!function_exists('thh_brand_logo_src')) {
	/** Geeft [url, bron] voor een merk-term; bron = media|thh|repo. */
	function thh_brand_logo_src($term) {
		$lid = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
		$src = $lid ? wp_get_attachment_image_url($lid, 'medium') : '';
		if ($src) return [$src, 'media'];
		$k = mb_strtolower(trim($term->name));
		if ($k === 'the health house') return ['https://thehealthhouse.nl/wp-content/uploads/2026/08/thh-logo-mail-navy-v2.png', 'thh'];
		$alias = ['huge supplements' => 'huge', 'nutri+' => 'nutri', "lenny & larry's" => 'lenny-larrys', '5% nutrition rich piana' => '5-nutrition', "doctor's best" => 'docters-best'];
		$slug = $alias[$k] ?? preg_replace('/^-+|-+$/', '', preg_replace('/[^a-z0-9]+/', '-', str_replace(['\'', '’', '`', '&'], '', remove_accents($k))));
		return ['https://cdn.jsdelivr.net/gh/healthhouse-repo/thh-xxl-images@main/brand-logos/' . $slug . '.png', 'repo'];
	}
}

/* ---------- shortcode ---------- */
function thh_merken_v2_shortcode($atts = []) {
	$a = shortcode_atts(['titel' => 'Alle merken', 'sub' => 'Van eiwitten tot fightgear: dit zijn de merken die je bij The Health House vindt.', 'populair' => 12], $atts);
	$tx = thh_brand_taxonomy();
	$terms = get_terms(['taxonomy' => $tx, 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC']);
	if (is_wp_error($terms) || !$terms) return '';
	$terms = array_values(array_filter($terms, function ($t) { return trim($t->name) !== '' && $t->count > 0; }));
	usort($terms, function ($x, $y) { return strcasecmp(remove_accents($x->name), remove_accents($y->name)); });

	$letter = function ($n) { $c = mb_strtoupper(mb_substr(remove_accents(trim($n)), 0, 1)); return preg_match('/[A-Z]/', $c) ? $c : '#'; };
	$tile = function ($t, $cls = '') use ($letter) {
		list($src, $bron) = thh_brand_logo_src($t);
		$n = $t->count;
		$img = '<img src="' . esc_url($src) . '" alt="' . esc_attr($t->name) . '" loading="lazy" data-name="' . esc_attr($t->name) . '" data-bron="' . esc_attr($bron) . '" onload="thhMkLogo(this)" onerror="thhMkFail(this)">';
		return '<a class="mk-tile ' . $cls . '" href="' . esc_url(get_term_link($t)) . '" data-naam="' . esc_attr(mb_strtolower(remove_accents($t->name))) . '" data-letter="' . esc_attr($letter($t->name)) . '" title="' . esc_attr($t->name) . '">'
			. '<span class="mk-logo">' . $img . '</span>'
			. '<span class="mk-name">' . esc_html($t->name) . '</span>'
			. '<span class="mk-count">' . (int) $n . ' ' . ($n === 1 ? 'product' : 'producten') . '</span></a>';
	};

	$letters = [];
	foreach ($terms as $t) $letters[$letter($t->name)] = true;
	$pop = $terms;
	usort($pop, function ($x, $y) { return $y->count - $x->count; });
	$pop = array_slice($pop, 0, max(0, (int) $a['populair']));

	ob_start(); ?>
	<div class="thh-mk" id="thh-merken">
		<div class="mk-head">
			<h1 class="mk-title"><?php echo esc_html($a['titel']); ?></h1>
			<p class="mk-sub"><?php echo esc_html($a['sub']); ?></p>
			<div class="mk-tools">
				<label class="mk-search"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" id="mkFilter" placeholder="Zoek een merk…" autocomplete="off" aria-label="Zoek een merk"></label>
				<span class="mk-total" id="mkTotal"><?php echo count($terms); ?> merken</span>
			</div>
			<div class="mk-letters" id="mkLetters">
				<?php foreach (array_merge(range('A', 'Z'), ['#']) as $L) : ?>
					<?php if (isset($letters[$L])) : ?><button type="button" data-l="<?php echo esc_attr($L); ?>"><?php echo esc_html($L); ?></button><?php else : ?><span><?php echo esc_html($L); ?></span><?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php if ($pop) : ?>
		<section class="mk-sec mk-pop" id="mkPop">
			<h2>Populaire merken</h2>
			<div class="mk-grid mk-grid-pop"><?php foreach ($pop as $t) echo $tile($t, 'mk-tile-pop'); ?></div>
		</section>
		<?php endif; ?>
		<section class="mk-sec">
			<h2>Alle merken van A tot Z</h2>
			<div class="mk-grid" id="mkGrid"><?php foreach ($terms as $t) echo $tile($t); ?></div>
			<p class="mk-empty" id="mkEmpty" hidden>Geen merk gevonden. Probeer een andere zoekterm.</p>
		</section>
	</div>
	<style id="thh-mk-css">
	.thh-mk{--nv:#14204A;--mut:#6b7187;--bd:#e6e8f0;max-width:1240px;margin:0 auto;padding:28px 16px 56px;color:#1b1f2e}
	.thh-mk *{box-sizing:border-box}
	.thh-mk .mk-title{font-size:38px;font-weight:800;letter-spacing:-.01em;margin:0 0 6px;color:var(--nv);text-transform:none}
	.thh-mk .mk-sub{margin:0 0 20px;color:var(--mut);font-size:16px;max-width:620px}
	.thh-mk .mk-tools{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:14px}
	.thh-mk .mk-search{display:flex;align-items:center;gap:10px;flex:1;min-width:220px;max-width:440px;border:2px solid var(--bd);border-radius:12px;padding:0 14px;background:#fff;color:var(--mut)}
	.thh-mk .mk-search:focus-within{border-color:var(--nv);color:var(--nv)}
	.thh-mk .mk-search input{border:0;outline:0;background:transparent;flex:1;height:46px;font-size:15px;color:#1b1f2e;box-shadow:none;margin:0;padding:0}
	.thh-mk .mk-total{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--mut)}
	.thh-mk .mk-letters{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px}
	.thh-mk .mk-letters button,.thh-mk .mk-letters span{width:32px;height:32px;display:grid;place-items:center;border-radius:8px;font-size:13px;font-weight:700;border:1px solid var(--bd);background:#fff;color:var(--nv);cursor:pointer;padding:0;line-height:1}
	.thh-mk .mk-letters span{color:#c3c7d3;cursor:default}
	.thh-mk .mk-letters button:hover,.thh-mk .mk-letters button.is-on{background:var(--nv);color:#fff;border-color:var(--nv)}
	.thh-mk .mk-sec{margin-top:34px}
	.thh-mk .mk-sec h2{font-size:22px;font-weight:600;margin:0 0 14px;color:var(--nv);text-transform:none}
	.thh-mk .mk-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}
	.thh-mk .mk-grid-pop{grid-template-columns:repeat(6,minmax(0,1fr))}
	.thh-mk .mk-tile{display:flex;flex-direction:column;align-items:center;justify-content:space-between;gap:8px;min-height:132px;padding:16px 12px 12px;background:#fff;border:1px solid var(--bd);border-radius:14px;text-decoration:none;color:var(--nv);text-align:center;transition:box-shadow .15s,transform .15s}
	.thh-mk .mk-tile:hover{box-shadow:0 12px 28px rgba(20,32,74,.10);transform:translateY(-2px);color:var(--nv)}
	.thh-mk .mk-tile.is-hidden{display:none}
	.thh-mk .mk-logo{flex:1;display:flex;align-items:center;justify-content:center;width:100%;min-height:60px}
	.thh-mk .mk-logo img{max-width:100%;max-height:56px;object-fit:contain}
	.thh-mk .mk-logo img.mk-icon{max-height:34px}
	.thh-mk .mk-logo .mk-txt{font-weight:800;font-size:15px;letter-spacing:.03em;text-transform:uppercase;color:var(--nv);line-height:1.15;word-break:break-word}
	.thh-mk .mk-name{font-size:13px;font-weight:600;line-height:1.2;color:#1b1f2e}
	.thh-mk .mk-logo .mk-txt + .mk-hide,.thh-mk .mk-tile.has-txt .mk-name{display:none}
	.thh-mk .mk-count{font-size:11px;color:var(--mut);font-weight:600;text-transform:uppercase;letter-spacing:.06em}
	.thh-mk .mk-tile-pop{min-height:150px}.thh-mk .mk-tile-pop .mk-logo img{max-height:64px}
	.thh-mk .mk-empty{color:var(--mut);margin:12px 0}
	@media(max-width:1024px){.thh-mk .mk-grid,.thh-mk .mk-grid-pop{grid-template-columns:repeat(4,minmax(0,1fr))}.thh-mk .mk-title{font-size:30px}}
	@media(max-width:640px){.thh-mk{padding:18px 14px 40px}.thh-mk .mk-grid,.thh-mk .mk-grid-pop{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.thh-mk .mk-tile{min-height:118px;padding:12px 10px 10px}.thh-mk .mk-title{font-size:26px}.thh-mk .mk-letters button,.thh-mk .mk-letters span{width:28px;height:28px;font-size:12px}}
	</style>
	<script>
	window.thhMkFail=function(i){var t=i.closest('.mk-tile');var w=i.parentNode;if(w){w.innerHTML='<span class="mk-txt">'+(i.getAttribute('data-name')||'').replace(/</g,'&lt;')+'</span>';}if(t)t.classList.add('has-txt');};
	window.thhMkLogo=function(i){var w=i.naturalWidth,h=i.naturalHeight;if(i.getAttribute('data-bron')==='repo'){if(w&&w===h&&w>=200){thhMkFail(i);return;}if(w&&w<=64){i.classList.add('mk-icon');}}};
	(function(){var f=document.getElementById('mkFilter'),g=document.getElementById('mkGrid'),e=document.getElementById('mkEmpty'),p=document.getElementById('mkPop'),tot=document.getElementById('mkTotal'),L=document.getElementById('mkLetters');if(!f||!g)return;var tiles=[].slice.call(g.querySelectorAll('.mk-tile')),all=tiles.length;
	function norm(s){return (s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');}
	function run(){var q=norm(f.value.trim()),n=0;tiles.forEach(function(t){var ok=!q||t.getAttribute('data-naam').indexOf(q)!==-1;t.classList.toggle('is-hidden',!ok);if(ok)n++;});if(e)e.hidden=n>0;if(p)p.style.display=q?'none':'';if(tot)tot.textContent=(q?n+' van '+all:all)+' merken';}
	f.addEventListener('input',run);
	if(L){L.addEventListener('click',function(ev){var b=ev.target.closest('button');if(!b)return;f.value='';run();var l=b.getAttribute('data-l');var first=tiles.filter(function(t){return t.getAttribute('data-letter')===l;})[0];[].forEach.call(L.querySelectorAll('button'),function(x){x.classList.toggle('is-on',x===b);});if(first){var y=first.getBoundingClientRect().top+window.pageYOffset-110;window.scrollTo({top:y,behavior:'smooth'});}});}
	})();
	</script>
	<?php
	return ob_get_clean();
}
add_action('init', function () {
	remove_shortcode('merkenpagina');
	add_shortcode('merkenpagina', 'thh_merken_v2_shortcode');
	add_shortcode('thh_merken', 'thh_merken_v2_shortcode');
}, 99);

/* Verberg de losse Elementor-kop "Merken" boven de shortcode (de pagina heeft nu een eigen titel). */
add_action('wp_head', function () {
	if (!is_page('merken')) return;
	echo '<style id="thh-mk-page">.merken-pagina-titel{display:none!important}</style>';
});

/* ---------- REST: logo-beheer ---------- */
add_action('rest_api_init', function () {
	$adm = function () { return current_user_can('manage_woocommerce'); };
	register_rest_route('thh/v1', '/merken/status', ['methods' => 'GET', 'permission_callback' => $adm, 'callback' => function () {
		$tx = thh_brand_taxonomy();
		$terms = get_terms(['taxonomy' => $tx, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC']);
		$out = [];
		foreach ($terms as $t) { list($src, $bron) = thh_brand_logo_src($t); $out[] = ['id' => $t->term_id, 'slug' => $t->slug, 'name' => $t->name, 'count' => $t->count, 'bron' => $bron, 'src' => $src]; }
		return $out;
	}]);
	$set_logo = function ($term, $url) {
		if (!function_exists('media_sideload_image')) { require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/image.php'; }
		$url = esc_url_raw(trim($url));
		if (!preg_match('#^https?://#i', $url)) return new WP_Error('url', 'ongeldige url');
		// bestandsnaam: logo-<slug>.<ext> (svg wordt via download_url + wp_handle_sideload toegestaan als mime het toelaat)
		$ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
		if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'], true)) $ext = 'png';
		$tmp = download_url($url, 30);
		if (is_wp_error($tmp)) return $tmp;
		if ($ext === 'png' && function_exists('mime_content_type')) { $m = mime_content_type($tmp); $map = ['image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'image/gif' => 'gif', 'image/png' => 'png']; if (isset($map[$m])) $ext = $map[$m]; }
		$file = ['name' => 'logo-' . $term->slug . '.' . $ext, 'tmp_name' => $tmp];
		$allow = function ($mimes) { $mimes['svg'] = 'image/svg+xml'; return $mimes; };
		add_filter('upload_mimes', $allow);
		$id = media_handle_sideload($file, 0, 'Logo ' . $term->name);
		remove_filter('upload_mimes', $allow);
		if (is_wp_error($id)) { @unlink($tmp); return $id; }
		update_post_meta($id, '_wp_attachment_image_alt', $term->name);
		$old = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
		if ($old && $old !== $id) update_term_meta($term->term_id, '_thh_logo_prev', $old);
		update_term_meta($term->term_id, 'thumbnail_id', $id);
		update_term_meta($term->term_id, '_thh_logo_src', $url);
		return ['term' => $term->slug, 'attachment' => $id, 'url' => wp_get_attachment_image_url($id, 'medium')];
	};
	$find = function ($p) {
		$tx = thh_brand_taxonomy();
		if (!empty($p['term_id'])) return get_term((int) $p['term_id'], $tx);
		if (!empty($p['slug'])) return get_term_by('slug', sanitize_title($p['slug']), $tx);
		return null;
	};
	register_rest_route('thh/v1', '/merken/logo', ['methods' => 'POST', 'permission_callback' => $adm, 'callback' => function ($r) use ($set_logo, $find) {
		$t = $find($r->get_json_params());
		if (!$t || is_wp_error($t)) return new WP_Error('term', 'merk niet gevonden', ['status' => 404]);
		$res = $set_logo($t, (string) $r->get_json_params()['url']);
		if (is_wp_error($res)) return new WP_Error('logo', $res->get_error_message(), ['status' => 400]);
		return $res;
	}]);
	register_rest_route('thh/v1', '/merken/logo-batch', ['methods' => 'POST', 'permission_callback' => $adm, 'callback' => function ($r) use ($set_logo, $find) {
		$out = [];
		foreach ((array) ($r->get_json_params()['items'] ?? []) as $it) {
			$t = $find($it);
			if (!$t || is_wp_error($t)) { $out[] = ['slug' => $it['slug'] ?? '', 'error' => 'merk niet gevonden']; continue; }
			$res = $set_logo($t, (string) ($it['url'] ?? ''));
			$out[] = is_wp_error($res) ? ['slug' => $t->slug, 'error' => $res->get_error_message()] : $res;
		}
		if (isset($GLOBALS['kinsta_cache']) && !empty($GLOBALS['kinsta_cache']->kinsta_cache_purge)) $GLOBALS['kinsta_cache']->kinsta_cache_purge->purge_complete_caches();
		return $out;
	}]);
	/* Hulp bij het zoeken van logo's: haalt de homepage van een merksite op en geeft logo-kandidaten terug (alleen admins). */
	register_rest_route('thh/v1', '/merken/logo-zoek', ['methods' => 'POST', 'permission_callback' => $adm, 'callback' => function ($r) {
		$out = [];
		foreach (array_slice((array) ($r->get_json_params()['sites'] ?? []), 0, 15) as $site) {
			$site = esc_url_raw(trim($site));
			if (!preg_match('#^https?://#i', $site)) continue;
			$res = wp_remote_get($site, ['timeout' => 15, 'redirection' => 5, 'user-agent' => 'Mozilla/5.0 (compatible; THH-logo-check)']);
			if (is_wp_error($res)) { $out[$site] = ['error' => $res->get_error_message()]; continue; }
			$html = (string) wp_remote_retrieve_body($res);
			$base = $site;
			if (preg_match('#<base[^>]+href=["\']([^"\']+)#i', $html, $m)) $base = $m[1];
			$abs = function ($u) use ($base) {
				$u = html_entity_decode(trim($u));
				if (preg_match('#^(https?:)?//#i', $u)) return preg_match('#^//#', $u) ? 'https:' . $u : $u;
				if (strpos($u, 'data:') === 0) return '';
				$p = parse_url($base);
				$root = $p['scheme'] . '://' . $p['host'];
				return $u[0] === '/' ? $root . $u : rtrim($base, '/') . '/' . $u;
			};
			$cands = [];
			if (preg_match_all('#<img[^>]+>#i', $html, $imgs)) {
				foreach ($imgs[0] as $tag) {
					if (!preg_match('/logo|brand|wordmark/i', $tag)) continue;
					foreach (['data-src', 'srcset', 'data-srcset', 'src'] as $at) {
						if (preg_match('#' . $at . '=["\']([^"\']+)#i', $tag, $m)) { $v = $m[1]; if ($at === 'srcset' || $at === 'data-srcset') $v = trim(explode(' ', trim(explode(',', $v)[0]))[0]); $u = $abs($v); if ($u) $cands[] = $u; }
					}
				}
			}
			if (preg_match_all('#<(?:svg|img)[^>]*class=["\'][^"\']*logo[^"\']*["\'][^>]*>#i', $html, $m2)) $cands[] = 'inline-svg:' . count($m2[0]);
			if (preg_match('#property=["\']og:image["\'][^>]+content=["\']([^"\']+)#i', $html, $m) || preg_match('#content=["\']([^"\']+)["\'][^>]+property=["\']og:image#i', $html, $m)) $cands[] = 'og:' . $abs($m[1]);
			if (preg_match('#<link[^>]+rel=["\'][^"\']*icon[^"\']*["\'][^>]+href=["\']([^"\']+)#i', $html, $m)) $cands[] = 'icon:' . $abs($m[1]);
			$out[$site] = array_values(array_unique($cands));
		}
		return $out;
	}]);
	register_rest_route('thh/v1', '/merken/logo-remove', ['methods' => 'POST', 'permission_callback' => $adm, 'callback' => function ($r) use ($find) {
		$t = $find($r->get_json_params());
		if (!$t || is_wp_error($t)) return new WP_Error('term', 'merk niet gevonden', ['status' => 404]);
		$id = (int) get_term_meta($t->term_id, 'thumbnail_id', true);
		delete_term_meta($t->term_id, 'thumbnail_id'); // koppeling weg; de afbeelding blijft in de mediabibliotheek
		return ['term' => $t->slug, 'unlinked' => $id];
	}]);
});
