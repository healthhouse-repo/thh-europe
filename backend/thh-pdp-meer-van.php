<?php
/**
 * THH — "Meer van [merk]" en "Meer van [categorie]" onder de productinformatie (zoals Zalando)
 *
 * Twee rijen met productkaarten die je horizontaal doorschuift. Producten op voorraad, best verkocht eerst,
 * zonder het huidige product en zonder dubbelen tussen de twee rijen. Categorie = Yoast-hoofdcategorie of de
 * diepste echte categorie (geen actie-categorieën zoals 2+1 Deals, Best verkocht, Nieuw, Week Deals).
 * Kaarten zijn dezelfde tegels als op de homepage. De oude sectie 'Anderen kochten ook' is verborgen.
 * Wordt in de footer gerenderd en met JS direct onder de productsectie gezet. Cache per product 6 uur.
 */
if (!defined('ABSPATH')) return;

function thh_mv_actiecats() { return ['2-1-deals', 'best-verkocht', 'nieuw', 'week-deals', 'acties', 'aanbiedingen', 'sale', 'top-25', 'dagdeal', 'dagdeals', 'weekdeals']; }

function thh_mv_categorie($pid) {
	$prim = (int) get_post_meta($pid, '_yoast_wpseo_primary_product_cat', true);
	if ($prim) { $t = get_term($prim, 'product_cat'); if ($t && !is_wp_error($t) && !in_array($t->slug, thh_mv_actiecats(), true)) return $t; }
	$best = null; $diep = -1;
	foreach ((array) get_the_terms($pid, 'product_cat') as $t) {
		if (!$t || is_wp_error($t) || in_array($t->slug, thh_mv_actiecats(), true)) continue;
		$d = count(get_ancestors($t->term_id, 'product_cat'));
		if ($d > $diep || ($d === $diep && $best && $t->count < $best->count)) { $best = $t; $diep = $d; }
	}
	return $best;
}

function thh_mv_merk($pid) {
	foreach (['product_brand', 'brands'] as $tax) {
		$t = get_the_terms($pid, $tax);
		if ($t && !is_wp_error($t)) return $t[0];
	}
	return null;
}

function thh_mv_ids($tax, $term_id, $uitsluiten, $n = 12) {
	$q = new WP_Query([
		'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => $n, 'fields' => 'ids', 'no_found_rows' => true,
		'post__not_in' => $uitsluiten, 'meta_key' => 'total_sales', 'orderby' => 'meta_value_num', 'order' => 'DESC',
		'tax_query' => [
			'relation' => 'AND',
			['taxonomy' => $tax, 'field' => 'term_id', 'terms' => [(int) $term_id]],
			['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => ['outofstock', 'exclude-from-catalog'], 'operator' => 'NOT IN'],
		],
	]);
	return $q->posts;
}

function thh_mv_eur($v) {
	$v = number_format((float) $v, 2, ',', '');
	list($e, $c) = explode(',', $v);
	return '<span class="h2-eur">' . $e . ',<sup>' . $c . '</sup></span>';
}

function thh_mv_card_h2($p, $tag = '') {
	if (!$p || !$p->is_visible()) return '';
	$img = $p->get_image('woocommerce_single', ['loading' => 'lazy', 'class' => 'h2-pimg', 'sizes' => '(max-width: 640px) 50vw, 320px']);
	$badge = '';
	$vanaf = false;
	if ($p->is_type('variable')) {
		// laagste prijs van leverbare, normale varianten (samples/proefzakjes tellen niet mee)
		$best = null; $prices = [];
		foreach ($p->get_children() as $vid) {
			$v = wc_get_product($vid);
			if (!$v || !$v->is_purchasable() || !$v->is_in_stock() || (float) $v->get_price() <= 0) continue;
			if (preg_match('/sample|proef|tester|30\s*gram|1\s*portie|single serv/i', implode(' ', $v->get_attributes()) . ' ' . $v->get_name())) continue;
			$prices[] = (float) $v->get_price();
			if (!$best || (float) $v->get_price() < (float) $best->get_price()) $best = $v;
		}
		if (!$best) { $reg = (float) $p->get_variation_regular_price('min'); $now = (float) $p->get_variation_price('min'); }
		else { $reg = (float) $best->get_regular_price(); $now = (float) $best->get_price(); }
		$vanaf = count(array_unique($prices)) > 1 || (!$best && (float) $p->get_variation_price('min') !== (float) $p->get_variation_price('max'));
	} else {
		$reg = (float) $p->get_regular_price();
		$now = (float) $p->get_price();
	}
	$sale = $p->is_on_sale() && $reg > $now && $now > 0;
	if ($sale) {
		$off = round((1 - $now / $reg) * 100);
		if ($off > 0) $badge = '<span class="h2-badge">-' . $off . '%</span>';
	} elseif ($tag) $badge = '<span class="h2-badge h2-badge-nv">' . esc_html($tag) . '</span>';
	$price = ($vanaf ? '<span class="h2-vanaf">Vanaf</span> ' : '') . ($sale ? '<del>' . thh_mv_eur($reg) . '</del> <span class="h2-now h2-now-sale">' . thh_mv_eur($now) . '</span>' : '<span class="h2-now">' . thh_mv_eur($now) . '</span>');
	$rating = '';
	if ($p->get_rating_count() > 0) {
		$r = (float) $p->get_average_rating();
		$rating = '<div class="h2-rating"><span class="h2-stars" style="--r:' . esc_attr(round($r / 5 * 100)) . '%">★★★★★</span> ' . esc_html(number_format($r, 1, ',', '')) . ' (' . (int) $p->get_rating_count() . ')</div>';
	}
	$direct = !$p->is_type('variable') && $p->is_purchasable() && $p->is_in_stock();
	$cart_url = $direct ? $p->add_to_cart_url() : $p->get_permalink();
	$cart_lbl = $direct ? 'In winkelwagen' : 'Kies je smaak';
	$cart = '<a class="h2-cart' . ($direct ? ' add_to_cart_button ajax_add_to_cart' : '') . '" href="' . esc_url($cart_url) . '" aria-label="' . esc_attr($cart_lbl . ': ' . $p->get_name()) . '"' . ($direct ? ' data-product_id="' . $p->get_id() . '" data-quantity="1" rel="nofollow"' : '') . '><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3h2.6l2.4 11.2a1.6 1.6 0 0 0 1.6 1.3h8.6a1.6 1.6 0 0 0 1.6-1.2L21 7H6"/></svg></a>';
	return '<article class="h2-card">' .
		'<div class="h2-cimg">' . $badge . $img . $cart . apply_filters('thh_h2_card_extra', '', $p) . '</div>' .
		'<div class="h2-cbody">' . $rating .
		'<a class="h2-cname" href="' . esc_url($p->get_permalink()) . '">' . esc_html($p->get_name()) . '</a>' .
		'<div class="h2-price">' . $price . '</div></div></article>';
}

function thh_mv_kaart($id) {
	return thh_mv_card_h2(wc_get_product($id));
}

function thh_mv_rij($titel, $link, $ids) {
	if (count($ids) < 2) return '';
	$h = '<section class="thh-mv-rij"><div class="thh-mv-kop"><h2>' . esc_html($titel) . '</h2>' . ($link ? '<a href="' . esc_url($link) . '">Bekijk alles</a>' : '') . '</div>' .
		'<div class="thh-mv-wrap"><button type="button" class="thh-mv-pijl thh-mv-l" aria-label="Vorige">‹</button><div class="thh-mv-lijst">';
	foreach ($ids as $id) $h .= thh_mv_kaart($id);
	return $h . '</div><button type="button" class="thh-mv-pijl thh-mv-r" aria-label="Volgende">›</button></div></section>';
}

add_action('wp_footer', function () {
	if (!function_exists('is_product') || !is_product()) return;
	$pid = get_queried_object_id();
	$ck = 'thh_mv2_' . $pid;
	$html = get_transient($ck);
	if ($html === false) {
		$html = '';
		$gehad = [$pid];
		$merk = thh_mv_merk($pid);
		if ($merk) {
			$ids = thh_mv_ids($merk->taxonomy, $merk->term_id, $gehad);
			$gehad = array_merge($gehad, $ids);
			$html .= thh_mv_rij('Meer van ' . $merk->name, get_term_link($merk), $ids);
		}
		$cat = thh_mv_categorie($pid);
		if ($cat) {
			$ids = thh_mv_ids('product_cat', $cat->term_id, $gehad);
			$html .= thh_mv_rij('Meer van ' . $cat->name, get_term_link($cat), $ids);
		}
		set_transient($ck, $html, 6 * HOUR_IN_SECONDS);
	}
	if (!$html) return; ?>
<div id="thhMeerVan" class="thh-mv"><?php echo $html; ?></div>
<style>
.elementor-element-4e4df1e,.elementor-element-10e1a5a{display:none!important}
.thh-mv{max-width:1240px;margin:24px auto 8px;padding:0 20px;font-family:inherit}
.thh-mv-rij{margin:0 0 40px}
.thh-mv-kop{display:flex;align-items:baseline;justify-content:space-between;gap:16px;margin-bottom:14px}
.thh-mv-kop h2{margin:0;font-size:22px!important;font-weight:500!important;color:#1b1f2e!important;text-transform:uppercase!important;letter-spacing:.01em!important}
.thh-mv-kop a{font-size:15px;font-weight:600;color:#1b1f2e!important;text-decoration:underline;text-underline-offset:3px;white-space:nowrap}
.thh-mv-wrap{position:relative}
.thh-mv-lijst{display:grid;grid-auto-flow:column;grid-auto-columns:calc((100% - 3*16px)/4);gap:16px;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;padding-bottom:4px}
.thh-mv-lijst::-webkit-scrollbar{display:none}
.thh-mv{--nv:#14204a;--rd:#e2373f}
.thh-mv .h2-card{position:relative;display:flex;flex-direction:column;gap:10px;background:none;border:0;padding:0;scroll-snap-align:start;min-width:0}
.thh-mv .h2-cimg{position:relative;display:grid;place-items:center;aspect-ratio:1/1.22;background:#f5f5f6;border-radius:10px;overflow:hidden}
.thh-mv .h2-cimg img{position:absolute;inset:0;margin:auto;width:78%;height:74%;max-width:none;max-height:none;object-fit:contain;mix-blend-mode:multiply;transition:transform .25s}
.thh-mv .h2-card:hover .h2-cimg img{transform:scale(1.04)}
.thh-mv .h2-cimg>:not(img){z-index:2}
.thh-mv .h2-badge{position:absolute;top:12px;left:12px;z-index:3;background:var(--rd);color:#fff;font-weight:600;font-size:12px;padding:4px 9px;border-radius:999px}.thh-mv .h2-badge-nv{background:var(--nv)}
.thh-mv .h2-cart{position:absolute;right:12px;bottom:12px;z-index:3;width:44px;height:44px;border-radius:50%;background:var(--nv);color:#fff!important;display:grid;place-items:center;box-shadow:0 4px 12px rgba(20,32,74,.18);transition:transform .15s,background .15s}
.thh-mv .h2-cart:hover{transform:scale(1.07);background:#0d1538}.thh-mv .h2-cart.added::after,.thh-mv .h2-card .added_to_cart{display:none!important}
.thh-mv .h2-cart.loading{opacity:.6}
.thh-mv .h2-cbody{display:flex;flex-direction:column;gap:4px;flex:1;padding:0 2px}
.thh-mv .h2-cname{font-weight:600;color:#111827!important;font-size:14px;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-decoration:none}
.thh-mv .h2-cname::after{content:"";position:absolute;inset:0;z-index:1}
.thh-mv .h2-card:hover .h2-cname{text-decoration:underline;text-underline-offset:2px}
.thh-mv .h2-rating{font-size:12px;color:#4b5563;display:flex;align-items:center;gap:4px}.thh-mv .h2-stars{font-size:13px;letter-spacing:1px;background:linear-gradient(90deg,#f5b301 var(--r),#d9dbe1 var(--r));-webkit-background-clip:text;background-clip:text;color:transparent}
.thh-mv .h2-price{margin-top:2px;display:flex;align-items:baseline;gap:6px;flex-wrap:wrap;color:#111827}
.thh-mv .h2-vanaf{font-size:12px;font-weight:500;color:#a5a2a2}
.thh-mv .h2-now{font-size:17px;font-weight:700}.thh-mv .h2-now-sale{color:var(--rd)}
.thh-mv .h2-eur sup{font-size:.62em;top:-.45em;position:relative;vertical-align:baseline;margin-left:1px}
.thh-mv .h2-price del{font-size:12px;color:#a5a2a2;font-weight:500}.thh-mv .h2-price del .h2-eur{font-size:12px}
.thh-mv-pijl{position:absolute;top:calc(50% - 60px);z-index:2;width:44px;height:44px;border-radius:50%;border:1.5px solid #1b1f2e!important;background:#fff!important;color:#1b1f2e!important;font-size:26px;line-height:1;display:flex;align-items:center;justify-content:center;cursor:pointer;padding:0!important;box-shadow:0 2px 10px rgba(0,0,0,.08)}
.thh-mv-l{left:-14px}.thh-mv-r{right:-14px}
.thh-mv-pijl[disabled]{opacity:0;pointer-events:none}
@media(max-width:1024px){.thh-mv-lijst{grid-auto-columns:calc((100% - 2*16px)/3)}}
@media(max-width:640px){.thh-mv{padding:0 14px}.thh-mv-lijst{grid-auto-columns:42%;gap:12px}.thh-mv-pijl{display:none}.thh-mv-kop h2{font-size:19px!important}}
</style>
<script>
(function(){
	var mv=document.getElementById('thhMeerVan');if(!mv)return;
	var t=document.querySelector('.product_title');var sec=t&&t.closest('.elementor-top-section, .e-con.e-parent, section');
	if(sec&&sec.parentNode){sec.parentNode.insertBefore(mv,sec.nextSibling);}
	[].forEach.call(mv.querySelectorAll('.thh-mv-wrap'),function(w){
		var l=w.querySelector('.thh-mv-lijst'),a=w.querySelector('.thh-mv-l'),b=w.querySelector('.thh-mv-r');
		function upd(){a.disabled=l.scrollLeft<5;b.disabled=l.scrollLeft+l.clientWidth>=l.scrollWidth-5;}
		a.addEventListener('click',function(){l.scrollBy({left:-l.clientWidth*.8,behavior:'smooth'});});
		b.addEventListener('click',function(){l.scrollBy({left:l.clientWidth*.8,behavior:'smooth'});});
		l.addEventListener('scroll',upd,{passive:true});upd();
	});
})();
</script>
<?php }, 20);
