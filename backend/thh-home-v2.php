<?php
/**
 * THH Homepage v2 — shortcode [thh_home_v2]
 * Nieuwe homepage-opzet (mix XXL Nutrition / Ultimate Nutrition in de THH-stijl van de weekdeals-pagina).
 * Alle productdata komt live uit WooCommerce. Eerst op /home-test/ (privé), daarna op de echte homepage.
 *
 * Hero-campagne (bijv. Black Friday) wissel je met attributen:
 * [thh_home_v2 kicker="Black Friday" titel="TOT 50% KORTING" titel2="OP ALLES" sub="Alleen deze week" cta="Shop Black Friday" cta_url="/deals/" img="https://.../bf.png" thema="zwart"]
 */
if (!defined('ABSPATH')) return;

function thh_h2_card($p, $tag = '') {
	if (!$p || !$p->is_visible()) return '';
	$img = $p->get_image('woocommerce_thumbnail', ['loading' => 'lazy', 'class' => 'h2-pimg']);
	$brand = '';
	$terms = wp_get_post_terms($p->get_id(), 'pa_merk', ['fields' => 'names']);
	if (!is_wp_error($terms) && $terms) $brand = $terms[0];
	if (!$brand) { $b = wc_get_product_terms($p->get_id(), 'product_brand', ['fields' => 'names']); if (!is_wp_error($b) && $b) $brand = $b[0]; }
	$badge = '';
	if ($p->is_on_sale() && $p->get_regular_price() && $p->get_sale_price()) {
		$off = round((1 - (float) $p->get_sale_price() / (float) $p->get_regular_price()) * 100);
		if ($off > 0) $badge = '<span class="h2-badge">-' . $off . '%</span>';
	} elseif ($tag) $badge = '<span class="h2-badge h2-badge-nv">' . esc_html($tag) . '</span>';
	$btn = $p->is_type('variable') ? 'Kies je smaak' : ($p->is_in_stock() ? 'In winkelwagen' : 'Bekijk product');
	$url = $p->is_type('variable') || !$p->is_in_stock() ? $p->get_permalink() : $p->add_to_cart_url();
	return '<article class="h2-card">' . $badge .
		'<a class="h2-cimg" href="' . esc_url($p->get_permalink()) . '">' . $img . '</a>' .
		'<div class="h2-cbody">' . ($brand ? '<span class="h2-brand">' . esc_html($brand) . '</span>' : '') .
		'<a class="h2-cname" href="' . esc_url($p->get_permalink()) . '">' . esc_html($p->get_name()) . '</a>' .
		'<div class="h2-price">' . $p->get_price_html() . '</div>' .
		'<a class="h2-cbtn" href="' . esc_url($url) . '"' . ($url === $p->add_to_cart_url() ? ' data-product_id="' . $p->get_id() . '"' : '') . '>' . $btn . '</a></div></article>';
}

function thh_h2_products($args) {
	$base = ['status' => 'publish', 'limit' => 8, 'stock_status' => 'instock', 'visibility' => 'catalog', 'return' => 'objects'];
	$ps = wc_get_products(array_merge($base, $args));
	return array_filter($ps, function ($p) { return $p && $p->get_image_id() && !preg_match('/sample|plastic tas|cadeau/i', $p->get_name()); });
}

add_shortcode('thh_home_v2', function ($a) {
	$a = shortcode_atts([
		'kicker' => '', 'titel' => 'ALLES VOOR', 'titel2' => 'JOUW DOEL', 'sub' => 'Eiwitten, creatine, pre-workouts en vitamines van de merken die je kent. Online besteld, morgen in huis — of haal het op in één van onze winkels.',
		'cta' => 'Shop de bestsellers', 'cta_url' => '/product-category/best-verkocht/', 'cta2' => 'Start de keuzehulp', 'cta2_url' => '/advies/',
		'img' => 'https://thh-outlet.vercel.app/outlet/assets/weekdeals/hero-weekdeals.png', 'thema' => 'navy',
		'banner' => 'https://thehealthhouse.nl/wp-content/uploads/2026/11/homepage-banner-clear-whey.webp', 'banner_url' => '/product/clear-whey-isolate/', 'banner_alt' => 'Nieuw: Clear Whey Isolate met collageen – 20% korting',
	], $a, 'thh_home_v2');
	$u = function ($p) { return esc_url(strpos($p, 'http') === 0 ? $p : home_url($p)); };
	$up = 'https://thehealthhouse.nl/wp-content/uploads/';

	// --- data ---
	$ico = [
		'eiwitten' => '<path d="M8 3h8l1 4H7zM7 7h10l1 13a1 1 0 01-1 1H7a1 1 0 01-1-1z"/><path d="M9 12h6"/>',
		'creatine' => '<path d="M5 8h14v11a2 2 0 01-2 2H7a2 2 0 01-2-2z"/><path d="M8 8V5h8v3"/><path d="M12 12v5M9.5 14.5h5"/>',
		'pre-workout' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
		'eiwitrepen' => '<rect x="3" y="8" width="18" height="8" rx="2"/><path d="M8 8v8M13 8v8"/>',
		'vitamines' => '<rect x="3" y="9" width="18" height="7" rx="3.5" transform="rotate(-35 12 12.5)"/><path d="M9 15.5l5.5-3.8"/>',
		'fatburners' => '<path d="M12 3c1 3 4 4.5 4 8.5A4 4 0 018 11.5c0-1.5.5-2.5 1-3.5.5 2 2 2.5 2 2.5s-1-3 1-7.5z"/><path d="M8 21h8"/>',
		'fightgear' => '<path d="M7 10V7a3 3 0 013-3h4a3 3 0 013 3v6a5 5 0 01-5 5h-1a5 5 0 01-5-5v-3z"/><path d="M7 11H5a1 1 0 00-1 1v2a3 3 0 003 3"/>',
		'accessoires-shakers' => '<path d="M9 3h6l-1 2H10zM8 5h8l1 15a1 1 0 01-1 1H8a1 1 0 01-1-1z"/><path d="M8 11h8M8 15h8"/>',
	];
	$cats = [
		['Eiwitten', 'eiwitten'], ['Creatine', 'creatine'], ['Pre-workout', 'pre-workout'], ['Repen & snacks', 'eiwitrepen'],
		['Vitamines', 'vitamines'], ['Afvallen', 'fatburners'], ['Vechtsport', 'fightgear'], ['Shakers', 'accessoires-shakers'],
	];
	$cat_html = '';
	foreach ($cats as [$name, $slug]) {
		$t = get_term_by('slug', $slug, 'product_cat');
		if (!$t) continue;
		$cat_html .= '<a class="h2-cat" href="' . esc_url(get_term_link($t)) . '"><span class="h2-catico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $ico[$slug] . '</svg></span><span class="h2-catname">' . esc_html($name) . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></span></a>';
	}

	$deal = function_exists('thh_ddp_data') ? thh_ddp_data() : null;
	$deals_html = '';
	if ($deal && !empty($deal['deals'])) {
		$i = $deal['deals'][0];
		$week = !empty($deal['week']);
		$deals_html .= '<a class="h2-deal" href="' . esc_url($week ? home_url('/weekdeals/') : home_url('/dagdeal/')) . '">' .
			'<div class="h2-dealtxt"><span class="h2-pill">' . ($week ? 'Weekdeals' : 'Dagdeal van vandaag') . '</span>' .
			'<h3>' . esc_html($week ? 'Alle dagdeals nog één keer' : $i['titel']) . '</h3>' .
			(!empty($i['sub']) && !$week ? '<p>' . esc_html($i['sub']) . '</p>' : '') .
			'<div class="h2-dealprice">' . (!empty($i['van']) ? '<s>' . esc_html($i['van']) . '</s> ' : '') . '<strong>' . esc_html($i['voor'] ?? '') . '</strong>' . (!empty($i['off']) ? '<span class="h2-badge">-' . esc_html($i['off']) . '%</span>' : '') . '</div>' .
			'<div class="h2-timer" data-h2-timer><span><b>00</b><small>uur</small></span><span><b>00</b><small>min</small></span><span><b>00</b><small>sec</small></span></div>' .
			'<span class="h2-btn h2-btn-red">Pak de deal</span></div>' .
			(!empty($i['img']) ? '<img class="h2-dealimg" src="' . esc_url($i['img']) . '" alt="" loading="lazy">' : '') . '</a>';
	}
	$wk = thh_h2_products(['category' => ['week-deals'], 'limit' => 3, 'orderby' => 'popularity']);
	if (count($wk) < 3) $wk = thh_h2_products(['limit' => 3, 'orderby' => 'popularity', 'on_sale' => true]);
	$wk_html = '';
	foreach ($wk as $p) $wk_html .= thh_h2_card($p, 'Deal');

	$best_html = '';
	foreach (thh_h2_products(['limit' => 8, 'orderby' => 'popularity']) as $p) $best_html .= thh_h2_card($p);
	$new_html = '';
	$nw = thh_h2_products(['category' => ['nieuw'], 'limit' => 4, 'orderby' => 'date', 'order' => 'DESC']);
	if (count($nw) < 4) $nw = thh_h2_products(['limit' => 4, 'orderby' => 'date', 'order' => 'DESC']);
	foreach ($nw as $p) $new_html .= thh_h2_card($p, 'Nieuw');

	$wish = ['XXL Nutrition', 'Applied Nutrition', 'Optimum Nutrition', 'Cellucor', 'Quamtrax', 'Kosso Nutrition', 'Grenade', 'Barebells', 'Per4m', 'USN', 'Nutrend', 'The Health House'];
	$brands = [];
	foreach ($wish as $w) { $t = get_term_by('name', $w, 'pa_merk'); if ($t && !is_wp_error($t)) $brands[] = $t; }
	if (count($brands) < 6) $brands = get_terms(['taxonomy' => 'pa_merk', 'orderby' => 'count', 'order' => 'DESC', 'number' => 12, 'hide_empty' => true]);
	$brand_html = '';
	if (!is_wp_error($brands)) foreach ($brands as $b) { $lid = (int) get_term_meta($b->term_id, 'thumbnail_id', true); $logo = $lid ? wp_get_attachment_image($lid, 'medium', false, ['loading' => 'lazy', 'alt' => $b->name]) : ''; $brand_html .= '<a class="h2-brand-tile" href="' . esc_url(get_term_link($b)) . '">' . ($logo ?: '<span>' . esc_html($b->name) . '</span>') . '</a>'; }

	$posts = get_posts(['numberposts' => 3, 'post_status' => 'publish']);
	$blog_html = '';
	foreach ($posts as $po) $blog_html .= '<a class="h2-post" href="' . esc_url(get_permalink($po)) . '">' . get_the_post_thumbnail($po, 'medium_large', ['loading' => 'lazy']) . '<span><em>' . esc_html(get_the_date('j F Y', $po)) . '</em><strong>' . esc_html(get_the_title($po)) . '</strong></span></a>';

	$hero_img = $a['img'] ? '<img class="h2-heroimg" src="' . esc_url($a['img']) . '" alt="">' :
		'<div class="h2-cluster" aria-hidden="true"><img src="' . $up . '2026/10/dagdeal-cutout-56911.png" alt="" class="h2-c1"><img src="' . $up . '2026/10/dagdeal-cutout-46857.png" alt="" class="h2-c2"><img src="' . $up . '2026/10/dagdeal-cutout-64204.png" alt="" class="h2-c3"></div>';

	ob_start(); ?>
<div class="h2 h2-<?php echo esc_attr($a['thema']); ?>">

	<section class="h2-hero">
		<div class="h2-herotxt">
			<?php if ($a['kicker']) : ?><span class="h2-kicker"><?php echo esc_html($a['kicker']); ?></span><?php endif; ?>
			<h1><?php echo esc_html($a['titel']); ?><br><em><?php echo esc_html($a['titel2']); ?></em></h1>
			<p><?php echo esc_html($a['sub']); ?></p>
			<div class="h2-ctas"><a class="h2-btn h2-btn-red" href="<?php echo $u($a['cta_url']); ?>"><?php echo esc_html($a['cta']); ?></a><?php if ($a['cta2']) : ?><a class="h2-btn h2-btn-ghost" href="<?php echo $u($a['cta2_url']); ?>"><?php echo esc_html($a['cta2']); ?></a><?php endif; ?></div>
			<div class="h2-herousp"><span>★ Klanten geven ons 9,3 / 10</span><span>Gratis verzending vanaf € 60 (NL)</span></div>
		</div>
		<div class="h2-herovis"><?php echo $hero_img; ?></div>
	</section>

	<section class="h2-cats"><?php echo $cat_html; ?></section>

	<section class="h2-usps">
		<div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg><strong>Gratis verzending vanaf € 60</strong><span>Voor 16:00 besteld, snel in huis</span></div>
		<div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-5.5-7-11a7 7 0 0114 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><strong>Winkels in heel Nederland</strong><span>Online bestellen, gratis ophalen</span></div>
		<div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a8 8 0 01-11.6 7.1L4 21l1.9-5.4A8 8 0 1121 12z"/></svg><strong>Persoonlijk advies</strong><span>Via WhatsApp, chat of in de winkel</span></div>
		<div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2l2.9 6.3 6.9.7-5.2 4.6 1.6 6.8L12 16.9 5.8 20.4l1.6-6.8L2.2 9l6.9-.7z"/></svg><strong>Spaar punten bij elke bestelling</strong><span>Gratis lid van de THH Club</span></div>
	</section>

	<?php if ($deals_html || $wk_html) : ?>
	<section class="h2-sec">
		<div class="h2-sechead"><h2>Deals van vandaag</h2><a href="<?php echo $u('/deals/'); ?>">Alle deals →</a></div>
		<div class="h2-dealgrid"><?php echo $deals_html; ?><div class="h2-grid h2-grid3"><?php echo $wk_html; ?></div></div>
	</section>
	<?php endif; ?>

	<?php if ($a['banner']) : ?><section class="h2-sec h2-bannersec"><a class="h2-banner" href="<?php echo $u($a['banner_url']); ?>"><img src="<?php echo esc_url($a['banner']); ?>" alt="<?php echo esc_attr($a['banner_alt']); ?>" loading="lazy"></a></section><?php endif; ?>

	<section class="h2-sec">
		<div class="h2-sechead"><h2>Populair bij onze klanten</h2><a href="<?php echo $u('/product-category/best-verkocht/'); ?>">Bekijk de top 25 →</a></div>
		<div class="h2-grid h2-grid4"><?php echo $best_html; ?></div>
	</section>

	<section class="h2-band">
		<div class="h2-bandtxt"><span class="h2-kicker">Meer dan een webshop</span><h2>Echte winkels, eerlijk advies, <em>geen onzin</em></h2><p>Van één winkel naar winkels door heel Nederland en een webshop met ruim 1.800 producten. Onze specialisten denken met je mee — online of in de winkel. Advies dat past bij jouw doel, je niveau en je budget.</p><div class="h2-ctas"><a class="h2-btn h2-btn-red" href="<?php echo $u('/advies/'); ?>">Gratis advies</a><a class="h2-btn h2-btn-ghost" href="<?php echo $u('/locaties/'); ?>">Vind een winkel</a></div></div>
		<div class="h2-bandcards">
			<a href="<?php echo $u('/locaties/'); ?>"><strong>Winkels</strong><span>Amsterdam, Hoofddorp, Heerhugowaard, Uithoorn en binnenkort Halfweg (outlet)</span></a>
			<a href="<?php echo $u('/loyalty/'); ?>"><strong>THH Club</strong><span>Voor elke € 10 een punt. 10 punten = € 5 korting</span></a>
			<a href="<?php echo $u('/gratis-tools/'); ?>"><strong>Gratis tools</strong><span>Caloriebehoefte, eiwitbehoefte en meer</span></a>
		</div>
	</section>

	<section class="h2-sec">
		<div class="h2-sechead"><h2>Nieuw binnen</h2><a href="<?php echo $u('/product-category/nieuw/'); ?>">Alle nieuwe producten →</a></div>
		<div class="h2-grid h2-grid4"><?php echo $new_html; ?></div>
	</section>

	<?php if ($brand_html) : ?><section class="h2-sec"><div class="h2-sechead"><h2>Onze merken</h2></div><div class="h2-brands"><?php echo $brand_html; ?></div><div class="h2-center"><a class="h2-btn h2-btn-nv" href="<?php echo $u('/merken/'); ?>">Bekijk alle merken</a></div></section><?php endif; ?>

	<?php if ($blog_html) : ?>
	<section class="h2-sec">
		<div class="h2-sechead"><h2>De no-nonsense blog</h2><a href="<?php echo $u('/blog/'); ?>">Alle artikelen →</a></div>
		<div class="h2-posts"><?php echo $blog_html; ?></div>
	</section>
	<?php endif; ?>
</div>
<style>
.h2{--nv:#14204a;--rd:#e2373f;--bg:#f5f6fa;--tx:#1b1f2e;--mut:#5a6072;max-width:1240px;margin:0 auto;padding:16px 20px 40px;color:var(--tx);font-family:inherit}
.h2 a{text-decoration:none}.h2 a:not([class]){color:inherit}.h2 h1,.h2 h2,.h2 h3{margin:0;color:var(--nv);line-height:1.15;letter-spacing:-.01em}
.h2-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:15px 26px;font-weight:600;font-size:15px;min-height:48px}
.h2 .h2-btn-red{background:var(--rd);color:#fff}.h2 .h2-btn-red:hover{background:#c92c33;color:#fff}.h2 .h2-btn-ghost{border:2px solid rgba(255,255,255,.7);color:#fff}.h2 .h2-btn-ghost:hover{background:rgba(255,255,255,.12);color:#fff}
.h2 .h2-btn-nv{background:var(--nv);color:#fff}.h2 .h2-btn-nv:hover{background:#0d1538;color:#fff}.h2-center{text-align:center;margin-top:20px}
.h2-kicker{display:inline-block;align-self:flex-start;background:rgba(255,255,255,.14);color:#fff;font-weight:600;font-size:12px;letter-spacing:.12em;text-transform:uppercase;padding:8px 14px;border-radius:6px}
/* hero */
.h2-hero{background:var(--nv);color:#fff;border-radius:24px;display:grid;grid-template-columns:1fr 1fr;gap:24px;min-height:520px;padding:48px 56px;position:relative;overflow:hidden}
.h2-hero:before{content:"";position:absolute;right:-10%;top:-20%;width:60%;height:140%;background:linear-gradient(120deg,transparent 40%,rgba(255,255,255,.06) 40%,rgba(255,255,255,.06) 55%,transparent 55%);pointer-events:none}
.h2-zwart .h2-hero{background:#0b0b0f}.h2-zwart .h2-kicker{background:var(--rd)}
.h2-herotxt{display:flex;flex-direction:column;justify-content:center;gap:18px;position:relative;z-index:1}
.h2-hero h1{color:#fff;font-size:clamp(42px,5.6vw,76px);font-weight:900;text-transform:uppercase;line-height:1.02}.h2-hero h1 em{font-style:normal;color:transparent;-webkit-text-stroke:2px #fff}
.h2-hero p{margin:0;font-size:17px;line-height:1.55;max-width:500px;color:rgba(255,255,255,.85)}
.h2-ctas{display:flex;gap:12px;flex-wrap:wrap}.h2-herousp{display:flex;gap:22px;flex-wrap:wrap;font-size:13px;font-weight:500;color:rgba(255,255,255,.75)}
.h2-herovis{position:relative;min-height:320px;display:flex;align-items:center;justify-content:center;padding:8px 0}
.h2-heroimg{width:100%;max-height:460px;object-fit:contain;filter:drop-shadow(0 30px 40px rgba(0,0,0,.45))}
/* categorie-tegels */
.h2-cats{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:12px;margin-top:16px}
.h2 .h2-cat{color:var(--nv);background:#fff;border:1px solid #e6e8f0;border-radius:16px;padding:18px 14px 14px;display:flex;flex-direction:column;gap:14px;align-items:flex-start;transition:transform .15s,box-shadow .15s}
.h2-cat:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(20,32,74,.12)}
.h2-catico{width:44px;height:44px;border-radius:12px;background:var(--bg);display:grid;place-items:center;color:var(--nv)}.h2-catico svg{width:24px;height:24px}
.h2-catname{display:flex;justify-content:space-between;align-items:center;width:100%;font-weight:500;font-size:14px;color:var(--nv)}.h2-catname svg{color:var(--mut)}
/* usp */
.h2-usps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:16px;background:var(--bg);border-radius:18px;padding:18px 22px}
.h2-usps div{display:grid;grid-template-columns:30px 1fr;grid-template-rows:22px 20px;column-gap:12px;align-items:center}.h2-usps svg{width:26px;height:26px;grid-row:1/3;color:var(--rd)}
.h2-usps strong{font-size:14px;font-weight:600;color:var(--nv);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.h2-usps span{font-size:12.5px;color:var(--mut);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
/* secties */
.h2-sec{margin-top:56px}.h2-sechead{display:flex;justify-content:space-between;align-items:end;gap:16px;margin-bottom:20px}
.h2 .h2-sechead h2{font-size:28px;font-weight:600;text-transform:none}.h2 .h2-band h2{text-transform:none}.h2 .h2-sechead a{font-weight:500;color:var(--nv);white-space:nowrap;text-decoration:underline;text-underline-offset:3px}
.h2-grid{display:grid;gap:16px}.h2-grid4{grid-template-columns:repeat(4,minmax(0,1fr))}.h2-grid3{grid-template-columns:repeat(3,minmax(0,1fr))}
.h2-card{background:#fff;border:1px solid #e6e8f0;border-radius:18px;padding:16px;position:relative;display:flex;flex-direction:column;gap:10px;transition:transform .15s,box-shadow .15s}
.h2-card:hover{transform:translateY(-3px);box-shadow:0 14px 30px rgba(20,32,74,.12)}
.h2-badge{position:absolute;top:14px;left:14px;z-index:2;background:var(--rd);color:#fff;font-weight:600;font-size:12px;padding:5px 10px;border-radius:999px}.h2-badge-nv{background:var(--nv)}
.h2-cimg{display:grid;place-items:center;aspect-ratio:1;overflow:hidden}.h2-cimg img{max-height:88%;width:auto;max-width:88%;object-fit:contain}
.h2-cbody{display:flex;flex-direction:column;gap:6px;flex:1}.h2-brand{font-size:12px;color:var(--mut)}
.h2 .h2-cname{font-weight:500;color:var(--nv);font-size:15px;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.h2-price{font-weight:600;font-size:18px;color:var(--nv);margin-top:auto}.h2-price del{color:#999;font-weight:400;font-size:14px;margin-right:6px}.h2-price ins{text-decoration:none}
.h2 .h2-cbtn{display:block;text-align:center;background:var(--nv);color:#fff;font-weight:500;border-radius:999px;padding:12px;font-size:14px}.h2 .h2-cbtn:hover{background:#0d1538;color:#fff}
/* deals */
.h2-dealgrid{display:grid;grid-template-columns:1fr 1.6fr;gap:16px;align-items:stretch}
.h2 .h2-deal,.h2 .h2-deal strong,.h2 .h2-deal h3{color:#fff}.h2-deal{background:var(--nv);color:#fff;border-radius:20px;padding:28px;display:grid;grid-template-columns:1fr 42%;gap:16px;position:relative;overflow:hidden;min-height:360px}
.h2-dealtxt{display:flex;flex-direction:column;gap:12px;justify-content:center;z-index:1}.h2-pill{display:inline-block;background:var(--rd);color:#fff;font-weight:600;font-size:11px;letter-spacing:.12em;text-transform:uppercase;padding:7px 12px;border-radius:999px;width:max-content}
.h2-deal h3{color:#fff;font-size:24px;font-weight:700}.h2-deal p{margin:0;font-size:13px;color:rgba(255,255,255,.75)}
.h2-dealprice{font-size:28px;font-weight:700;display:flex;align-items:center;gap:10px}.h2-dealprice s{color:rgba(255,255,255,.55);font-size:16px;font-weight:400}.h2-dealprice .h2-badge{position:static}
.h2-timer{display:flex;gap:8px}.h2-timer span{background:rgba(255,255,255,.1);border-radius:10px;padding:8px 10px;min-width:56px;text-align:center;display:flex;flex-direction:column}.h2-timer b{font-size:20px;font-weight:600}.h2-timer small{font-size:10px;letter-spacing:.1em;text-transform:uppercase;opacity:.7}
.h2-dealimg{position:absolute;right:6%;top:50%;transform:translateY(-50%);width:36%;height:72%;object-fit:contain;filter:drop-shadow(0 24px 30px rgba(0,0,0,.45))}
/* banner */
.h2-bannersec{margin-top:40px}.h2-banner{display:block;border-radius:20px;overflow:hidden}.h2-banner img{display:block;width:100%;height:auto}
/* band */
.h2-band{margin-top:56px;background:var(--nv);color:#fff;border-radius:24px;padding:48px 56px;display:grid;grid-template-columns:1.2fr 1fr;gap:40px;align-items:center}
.h2-bandtxt{display:flex;flex-direction:column;gap:16px}.h2-band h2{color:#fff;font-size:36px;font-weight:700}.h2-band h2 em{font-style:normal;color:transparent;-webkit-text-stroke:1.5px #fff}
.h2-band p{margin:0;font-size:16px;line-height:1.6;color:rgba(255,255,255,.82);max-width:560px}
.h2-bandcards{display:flex;flex-direction:column;gap:12px}.h2 .h2-bandcards a{color:#fff}.h2-bandcards a{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);border-radius:16px;padding:18px 20px;display:flex;flex-direction:column;gap:4px}
.h2-bandcards a:hover{background:rgba(255,255,255,.14)}.h2-bandcards strong{font-size:16px;font-weight:600}.h2-bandcards span{font-size:13px;color:rgba(255,255,255,.75)}
/* merken */
.h2-brands{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}
.h2 .h2-brand-tile{color:var(--nv);background:#fff;border:1px solid #e6e8f0;border-radius:14px;height:88px;display:grid;place-items:center;padding:14px;text-align:center}
.h2-brand-tile img{max-height:48px;max-width:80%;object-fit:contain;filter:grayscale(1);opacity:.8}.h2-brand-tile:hover img{filter:none;opacity:1}
.h2-brand-tile span{font-weight:700;font-size:15px;letter-spacing:.04em;text-transform:uppercase;color:#6b7187}.h2-brand-tile:hover span{color:var(--nv)}
/* blog */
.h2-posts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
.h2 .h2-post{color:var(--nv)}.h2-post{display:flex;flex-direction:column;gap:12px}.h2-post img{width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:16px}
.h2-post span{display:flex;flex-direction:column;gap:4px}.h2-post em{font-style:normal;font-size:12px;color:var(--mut)}.h2-post strong{font-size:16px;font-weight:500;color:var(--nv);line-height:1.4}
@media(max-width:1024px){.h2-cats{grid-template-columns:repeat(4,minmax(0,1fr))}.h2-usps{grid-template-columns:1fr 1fr}.h2-grid4{grid-template-columns:repeat(3,minmax(0,1fr))}.h2-brands{grid-template-columns:repeat(4,minmax(0,1fr))}.h2-dealgrid{grid-template-columns:1fr}.h2-band{grid-template-columns:1fr;padding:36px}.h2-hero{grid-template-columns:1fr;padding:36px 28px;min-height:0}.h2-herovis{min-height:260px}}
@media(max-width:640px){.h2{padding:12px 14px 32px}.h2-cats{grid-template-columns:1fr 1fr}.h2-usps{grid-template-columns:1fr}.h2-grid4,.h2-grid3{grid-template-columns:1fr 1fr;gap:10px}.h2-brands{grid-template-columns:repeat(3,minmax(0,1fr))}.h2-posts{grid-template-columns:1fr}.h2-deal{grid-template-columns:1fr;padding:22px}.h2-dealimg{position:static;transform:none;width:70%;height:200px;margin:0 auto;order:-1}.h2-sechead h2{font-size:22px}.h2-band h2{font-size:28px}.h2-hero h1{font-size:40px}.h2-card{padding:12px}.h2-cbtn{padding:11px;font-size:13px}}
</style>
<script>
(function(){var t=document.querySelector('[data-h2-timer]');if(!t)return;var b=t.querySelectorAll('b');function tick(){var n=new Date(),e=new Date(n);e.setHours(23,59,59,999);var s=Math.max(0,Math.floor((e-n)/1000));b[0].textContent=('0'+Math.floor(s/3600)).slice(-2);b[1].textContent=('0'+Math.floor(s%3600/60)).slice(-2);b[2].textContent=('0'+s%60).slice(-2)}tick();setInterval(tick,1000)})();
</script>
<?php
	return ob_get_clean();
});
