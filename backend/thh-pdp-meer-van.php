<?php
/**
 * THH — "Meer van [merk]" en "Meer van [categorie]" onder de productinformatie (zoals Zalando)
 *
 * Twee rijen met productkaarten die je horizontaal doorschuift. Producten op voorraad, best verkocht eerst,
 * zonder het huidige product en zonder dubbelen tussen de twee rijen. Categorie = Yoast-hoofdcategorie of de
 * diepste echte categorie (geen actie-categorieën zoals 2+1 Deals, Best verkocht, Nieuw, Week Deals).
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

function thh_mv_kaart($id) {
	$p = wc_get_product($id);
	if (!$p) return '';
	$merk = thh_mv_merk($id);
	$img = wp_get_attachment_image_url($p->get_image_id(), 'woocommerce_thumbnail') ?: wc_placeholder_img_src();
	return '<a class="thh-mv-kaart" href="' . esc_url($p->get_permalink()) . '">' .
		'<span class="thh-mv-img"><img src="' . esc_url($img) . '" alt="' . esc_attr($p->get_name()) . '" loading="lazy"></span>' .
		($merk ? '<span class="thh-mv-merk">' . esc_html($merk->name) . '</span>' : '') .
		'<span class="thh-mv-naam">' . esc_html($p->get_name()) . '</span>' .
		'<span class="thh-mv-prijs">' . $p->get_price_html() . '</span></a>';
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
	$ck = 'thh_mv_' . $pid;
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
.thh-mv{max-width:1240px;margin:24px auto 8px;padding:0 20px;font-family:inherit}
.thh-mv-rij{margin:0 0 40px}
.thh-mv-kop{display:flex;align-items:baseline;justify-content:space-between;gap:16px;margin-bottom:14px}
.thh-mv-kop h2{margin:0;font-size:22px!important;font-weight:700!important;color:#1b1f2e!important;text-transform:none!important;letter-spacing:0!important}
.thh-mv-kop a{font-size:15px;font-weight:600;color:#1b1f2e!important;text-decoration:underline;text-underline-offset:3px;white-space:nowrap}
.thh-mv-wrap{position:relative}
.thh-mv-lijst{display:grid;grid-auto-flow:column;grid-auto-columns:calc((100% - 4*16px)/5);gap:16px;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;padding-bottom:4px}
.thh-mv-lijst::-webkit-scrollbar{display:none}
.thh-mv-kaart{scroll-snap-align:start;display:flex;flex-direction:column;gap:4px;text-decoration:none!important;color:#1b1f2e!important;min-width:0}
.thh-mv-img{display:block;aspect-ratio:1/1;background:#f1f3f6;border-radius:10px;overflow:hidden;margin-bottom:6px}
.thh-mv-img img{width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply;padding:10px;box-sizing:border-box;transition:transform .25s}
.thh-mv-kaart:hover .thh-mv-img img{transform:scale(1.04)}
.thh-mv-merk{font-size:14px;font-weight:600;color:#1b1f2e}
.thh-mv-naam{font-size:14px;font-weight:300;line-height:1.4;color:#2b2f3a;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.thh-mv-prijs,.thh-mv-prijs .amount{font-size:14px;font-weight:600;color:#1b1f2e}
.thh-mv-prijs del,.thh-mv-prijs del .amount{font-weight:300;color:#6b7280}
.thh-mv-prijs ins{color:#e2373f;text-decoration:none}.thh-mv-prijs ins .amount{color:#e2373f}
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
