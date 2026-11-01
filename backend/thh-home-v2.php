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
		'kicker' => 'The Health House', 'titel' => 'ALLES VOOR', 'titel2' => 'JOUW DOEL', 'sub' => 'Eiwitten, creatine, pre-workouts en vitamines van de merken die je kent. Online besteld, morgen in huis — of haal het op in één van onze winkels.',
		'cta' => 'Shop de bestsellers', 'cta_url' => '/product-category/best-verkocht/', 'cta2' => 'Start de keuzehulp', 'cta2_url' => '/advies/',
		'img' => '', 'thema' => 'navy',
	], $a, 'thh_home_v2');
	$u = function ($p) { return esc_url(strpos($p, 'http') === 0 ? $p : home_url($p)); };
	$up = 'https://thehealthhouse.nl/wp-content/uploads/';

	// --- data ---
	$cats = [
		['Eiwitten', 'eiwitten'], ['Creatine', 'creatine'], ['Pre-workout', 'pre-workout'], ['Repen & snacks', 'eiwitrepen'],
		['Vitamines', 'vitamines'], ['Afvallen', 'fatburners'], ['Vechtsport', 'fightgear'], ['Shakers', 'accessoires-shakers'],
	];
	$cat_html = '';
	foreach ($cats as [$name, $slug]) {
		$t = get_term_by('slug', $slug, 'product_cat');
		if (!$t) continue;
		$img = '';
		$tid = get_term_meta($t->term_id, 'thumbnail_id', true);
		if ($tid) $img = wp_get_attachment_image($tid, 'woocommerce_thumbnail', false, ['loading' => 'lazy']);
		if (!$img) { $pp = thh_h2_products(['category' => [$slug], 'limit' => 1, 'orderby' => 'popularity']); $pp = array_values($pp); if ($pp) $img = $pp[0]->get_image('woocommerce_thumbnail', ['loading' => 'lazy']); }
		$cat_html .= '<a class="h2-cat" href="' . esc_url(get_term_link($t)) . '"><span class="h2-catimg">' . $img . '</span><span class="h2-catname">' . esc_html($name) . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></span></a>';
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

	$goals = [
		['Spieropbouw', 'Meer eiwit, creatine en gainers', '/product-category/spieropbouw/', $up . '2026/08/supplementenstack-spiermassa.jpg'],
		['Afvallen', 'Fatburners, eiwit en slimme snacks', '/product-category/afvallen/', $up . '2026/08/afvallen-zonder-spierverlies.jpg'],
		['Conditie & duursport', 'Energie, elektrolyten en herstel', '/product-category/duursport/', $up . '2026/10/trainen-hardlopen-kou-winter-header.jpg'],
		['Vechtsport', 'Fightgear, kleding en bescherming', '/product-category/fightgear/', $up . '2026/10/vechtsport-fitness-gym-amsterdam-vergeleken-header.jpg'],
	];
	$goal_html = '';
	foreach ($goals as [$n, $s, $l, $im]) $goal_html .= '<a class="h2-goal" href="' . $u($l) . '" style="background-image:url(' . esc_url($im) . ')"><span><strong>' . esc_html($n) . '</strong><em>' . esc_html($s) . '</em></span></a>';

	$best_html = '';
	foreach (thh_h2_products(['limit' => 8, 'orderby' => 'popularity']) as $p) $best_html .= thh_h2_card($p);
	$new_html = '';
	foreach (thh_h2_products(['limit' => 4, 'orderby' => 'date', 'order' => 'DESC']) as $p) $new_html .= thh_h2_card($p, 'Nieuw');

	$brands = get_terms(['taxonomy' => 'pa_merk', 'orderby' => 'count', 'order' => 'DESC', 'number' => 14, 'hide_empty' => true]);
	$brand_html = '';
	if (!is_wp_error($brands)) foreach ($brands as $b) $brand_html .= '<a href="' . esc_url(get_term_link($b)) . '">' . esc_html($b->name) . '</a>';

	$posts = get_posts(['numberposts' => 3, 'post_status' => 'publish']);
	$blog_html = '';
	foreach ($posts as $po) $blog_html .= '<a class="h2-post" href="' . esc_url(get_permalink($po)) . '">' . get_the_post_thumbnail($po, 'medium_large', ['loading' => 'lazy']) . '<span><em>' . esc_html(get_the_date('j F Y', $po)) . '</em><strong>' . esc_html(get_the_title($po)) . '</strong></span></a>';

	$hero_img = $a['img'] ? '<img class="h2-heroimg" src="' . esc_url($a['img']) . '" alt="">' :
		'<div class="h2-cluster" aria-hidden="true"><img src="' . $up . '2026/10/dagdeal-cutout-56911.png" alt="" class="h2-c1"><img src="' . $up . '2026/10/dagdeal-cutout-46857.png" alt="" class="h2-c2"><img src="' . $up . '2026/10/dagdeal-cutout-64204.png" alt="" class="h2-c3"></div>';

	ob_start(); ?>
<div class="h2 h2-<?php echo esc_attr($a['thema']); ?>">

	<section class="h2-hero">
		<div class="h2-herotxt">
			<span class="h2-kicker"><?php echo esc_html($a['kicker']); ?></span>
			<h1><?php echo esc_html($a['titel']); ?><br><em><?php echo esc_html($a['titel2']); ?></em></h1>
			<p><?php echo esc_html($a['sub']); ?></p>
			<div class="h2-ctas"><a class="h2-btn h2-btn-red" href="<?php echo $u($a['cta_url']); ?>"><?php echo esc_html($a['cta']); ?></a><?php if ($a['cta2']) : ?><a class="h2-btn h2-btn-ghost" href="<?php echo $u($a['cta2_url']); ?>"><?php echo esc_html($a['cta2']); ?></a><?php endif; ?></div>
			<div class="h2-herousp"><span>★ Klanten geven ons 9,3 / 10</span><span>Gratis verzending vanaf € 60 (NL)</span></div>
		</div>
		<div class="h2-herovis"><?php echo $hero_img; ?></div>
	</section>

	<section class="h2-cats"><?php echo $cat_html; ?></section>

	<section class="h2-usps">
		<div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg><strong>Gratis verzending vanaf € 60</strong><span>Voor 16:00 besteld, binnen 2 werkdagen in huis</span></div>
		<div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-5.5-7-11a7 7 0 0114 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><strong>Winkels in heel Nederland</strong><span>Online bestellen en gratis ophalen in de winkel</span></div>
		<div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a8 8 0 01-11.6 7.1L4 21l1.9-5.4A8 8 0 1121 12z"/></svg><strong>Persoonlijk advies</strong><span>Via WhatsApp, chat of in de winkel</span></div>
		<div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2l2.9 6.3 6.9.7-5.2 4.6 1.6 6.8L12 16.9 5.8 20.4l1.6-6.8L2.2 9l6.9-.7z"/></svg><strong>Spaar punten bij elke bestelling</strong><span>Gratis lid van de THH Club</span></div>
	</section>

	<?php if ($deals_html || $wk_html) : ?>
	<section class="h2-sec">
		<div class="h2-sechead"><h2>Deals van vandaag</h2><a href="<?php echo $u('/deals/'); ?>">Alle deals →</a></div>
		<div class="h2-dealgrid"><?php echo $deals_html; ?><div class="h2-grid h2-grid3"><?php echo $wk_html; ?></div></div>
	</section>
	<?php endif; ?>

	<section class="h2-sec">
		<div class="h2-sechead"><h2>Ontdek de beste producten voor jouw doel</h2></div>
		<div class="h2-goals"><?php echo $goal_html; ?></div>
	</section>

	<section class="h2-sec">
		<div class="h2-sechead"><h2>Populair bij onze klanten</h2><a href="<?php echo $u('/product-category/best-verkocht/'); ?>">Bekijk de top 25 →</a></div>
		<div class="h2-grid h2-grid4"><?php echo $best_html; ?></div>
	</section>

	<section class="h2-band">
		<div class="h2-bandtxt"><span class="h2-kicker">Meer dan een webshop</span><h2>Echte winkels, eerlijk advies, <em>geen onzin</em></h2><p>Van één winkel naar winkels door heel Nederland en een webshop met ruim 1.800 producten. Onze specialisten denken met je mee — online of in de winkel. Advies dat past bij jouw doel, je niveau en je budget.</p><div class="h2-ctas"><a class="h2-btn h2-btn-red" href="<?php echo $u('/advies/'); ?>">Gratis advies</a><a class="h2-btn h2-btn-ghost" href="<?php echo $u('/locaties/'); ?>">Vind een winkel</a></div></div>
		<div class="h2-bandcards">
			<a href="<?php echo $u('/locaties/'); ?>"><strong>Winkels</strong><span>Amsterdam, Hoofddorp, Heerhugowaard en Uithoorn</span></a>
			<a href="<?php echo $u('/loyalty/'); ?>"><strong>THH Club</strong><span>Voor elke € 10 een punt. 10 punten = € 5 korting</span></a>
			<a href="<?php echo $u('/gratis-tools/'); ?>"><strong>Gratis tools</strong><span>Caloriebehoefte, eiwitbehoefte en meer</span></a>
		</div>
	</section>

	<section class="h2-sec">
		<div class="h2-sechead"><h2>Nieuw binnen</h2><a href="<?php echo $u('/product-category/nieuw/'); ?>">Alle nieuwe producten →</a></div>
		<div class="h2-grid h2-grid4"><?php echo $new_html; ?></div>
	</section>

	<?php if ($brand_html) : ?><section class="h2-brands"><span>Merken</span><?php echo $brand_html; ?><a class="h2-brands-all" href="<?php echo $u('/merken/'); ?>">Alle merken →</a></section><?php endif; ?>

	<?php if ($blog_html) : ?>
	<section class="h2-sec">
		<div class="h2-sechead"><h2>De no-nonsense blog</h2><a href="<?php echo $u('/blog/'); ?>">Alle artikelen →</a></div>
		<div class="h2-posts"><?php echo $blog_html; ?></div>
	</section>
	<?php endif; ?>
</div>
<style>
.h2{--nv:#14204a;--rd:#e2373f;--bg:#f5f6fa;--tx:#1b1f2e;max-width:1240px;margin:0 auto;padding:16px 20px 40px;color:var(--tx);font-family:inherit}
.h2 a{text-decoration:none}.h2 a:not([class]){color:inherit}.h2 h1,.h2 h2,.h2 h3{margin:0;color:var(--nv);line-height:1.05;text-transform:uppercase;letter-spacing:-.01em}
.h2-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:16px 28px;font-weight:800;font-size:15px;text-transform:uppercase;letter-spacing:.04em;min-height:48px}
.h2 .h2-btn-red{background:var(--rd);color:#fff}.h2 .h2-btn-red:hover{background:#c92c33;color:#fff}.h2 .h2-btn-ghost{border:2px solid rgba(255,255,255,.7);color:#fff}.h2 .h2-btn-ghost:hover{background:rgba(255,255,255,.12);color:#fff}
.h2-kicker{display:inline-block;align-self:flex-start;background:rgba(255,255,255,.14);color:#fff;font-weight:700;font-size:12px;letter-spacing:.14em;text-transform:uppercase;padding:8px 14px;border-radius:6px}
/* hero */
.h2-hero{background:var(--nv);color:#fff;border-radius:24px;display:grid;grid-template-columns:1.05fr .95fr;gap:24px;min-height:540px;padding:48px 56px;position:relative;overflow:hidden}
.h2-hero:before{content:"";position:absolute;right:-10%;top:-20%;width:60%;height:140%;background:linear-gradient(120deg,transparent 40%,rgba(255,255,255,.06) 40%,rgba(255,255,255,.06) 55%,transparent 55%);pointer-events:none}
.h2-zwart .h2-hero{background:#0b0b0f}.h2-zwart .h2-kicker{background:var(--rd)}
.h2-herotxt{display:flex;flex-direction:column;justify-content:center;gap:18px;position:relative;z-index:1}
.h2-hero h1{color:#fff;font-size:clamp(44px,6vw,84px);font-weight:900}.h2-hero h1 em{font-style:normal;color:transparent;-webkit-text-stroke:2px #fff}
.h2-hero p{margin:0;font-size:17px;line-height:1.55;max-width:520px;color:rgba(255,255,255,.85)}
.h2-ctas{display:flex;gap:12px;flex-wrap:wrap}.h2-herousp{display:flex;gap:22px;flex-wrap:wrap;font-size:13px;font-weight:600;color:rgba(255,255,255,.75)}
.h2-herovis{position:relative;min-height:320px;display:flex;align-items:center;justify-content:center}
.h2-heroimg{max-width:100%;max-height:480px;object-fit:contain;filter:drop-shadow(0 30px 40px rgba(0,0,0,.45))}
.h2-cluster{position:relative;width:100%;height:100%;min-height:420px}.h2-cluster img{position:absolute;filter:drop-shadow(0 30px 40px rgba(0,0,0,.45));object-fit:contain}
.h2-c1{left:0;bottom:4%;width:48%;height:70%;transform:rotate(-8deg)}.h2-c2{left:30%;top:0;width:52%;height:80%;z-index:2}.h2-c3{right:0;bottom:8%;width:44%;height:62%;transform:rotate(7deg)}
/* categorie-tegels */
.h2-cats{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:12px;margin-top:16px}
.h2 .h2-cat{color:var(--nv);background:#fff;border:1px solid #e6e8f0;border-radius:16px;padding:14px 12px 12px;display:flex;flex-direction:column;gap:10px;transition:transform .15s,box-shadow .15s}
.h2-cat:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(20,32,74,.12)}
.h2-catimg{display:block;aspect-ratio:1;display:grid;place-items:center}.h2-catimg img{max-height:100px;width:auto;max-width:100%;object-fit:contain}
.h2-catname{display:flex;justify-content:space-between;align-items:center;font-weight:800;font-size:14px;color:var(--nv)}
/* usp */
.h2-usps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:16px;background:var(--bg);border-radius:18px;padding:18px}
.h2-usps div{display:grid;grid-template-columns:28px 1fr;grid-template-rows:auto auto;column-gap:12px;align-items:center}.h2-usps svg{width:26px;height:26px;grid-row:1/3;color:var(--rd)}
.h2-usps strong{font-size:14px;color:var(--nv)}.h2-usps span{font-size:12.5px;color:#555}
/* secties */
.h2-sec{margin-top:56px}.h2-sechead{display:flex;justify-content:space-between;align-items:end;gap:16px;margin-bottom:20px}
.h2-sechead h2{font-size:30px;font-weight:900}.h2 .h2-sechead a{font-weight:700;color:var(--rd);white-space:nowrap}
.h2-grid{display:grid;gap:16px}.h2-grid4{grid-template-columns:repeat(4,minmax(0,1fr))}.h2-grid3{grid-template-columns:repeat(3,minmax(0,1fr))}
.h2-card{background:#fff;border:1px solid #e6e8f0;border-radius:18px;padding:14px;position:relative;display:flex;flex-direction:column;gap:10px;transition:transform .15s,box-shadow .15s}
.h2-card:hover{transform:translateY(-3px);box-shadow:0 14px 30px rgba(20,32,74,.12)}
.h2-badge{position:absolute;top:14px;left:14px;z-index:2;background:var(--rd);color:#fff;font-weight:800;font-size:12px;padding:5px 9px;border-radius:999px}.h2-badge-nv{background:var(--nv)}
.h2-cimg{display:grid;place-items:center;background:var(--bg);border-radius:12px;aspect-ratio:1;overflow:hidden}.h2-cimg img{max-height:85%;width:auto;max-width:85%;object-fit:contain;mix-blend-mode:multiply}
.h2-cbody{display:flex;flex-direction:column;gap:6px;flex:1}.h2-brand{font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--rd)}
.h2 .h2-cname{font-weight:800;color:var(--nv);font-size:15px;line-height:1.25;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.h2-price{font-weight:900;font-size:20px;color:var(--nv);margin-top:auto}.h2-price del{color:#999;font-weight:500;font-size:14px;margin-right:6px}.h2-price ins{text-decoration:none}
.h2 .h2-cbtn{display:block;text-align:center;background:var(--nv);color:#fff;font-weight:700;border-radius:12px;padding:13px;font-size:14px}.h2 .h2-cbtn:hover{background:#0d1538;color:#fff}
/* deals */
.h2-dealgrid{display:grid;grid-template-columns:1fr 1.6fr;gap:16px;align-items:stretch}
.h2 .h2-deal,.h2 .h2-deal strong,.h2 .h2-deal h3{color:#fff}.h2-deal{background:var(--nv);color:#fff;border-radius:20px;padding:28px;display:grid;grid-template-columns:1fr 46%;gap:12px;position:relative;overflow:hidden;min-height:360px}
.h2-dealtxt{display:flex;flex-direction:column;gap:12px;justify-content:center;z-index:1}.h2-pill{display:inline-block;background:var(--rd);color:#fff;font-weight:800;font-size:11px;letter-spacing:.14em;text-transform:uppercase;padding:7px 12px;border-radius:999px;width:max-content}
.h2-deal h3{color:#fff;font-size:26px;font-weight:900}.h2-deal p{margin:0;font-size:13px;color:rgba(255,255,255,.75)}
.h2-dealprice{font-size:30px;font-weight:900;display:flex;align-items:center;gap:10px}.h2-dealprice s{color:rgba(255,255,255,.55);font-size:16px;font-weight:600}.h2-dealprice .h2-badge{position:static}
.h2-timer{display:flex;gap:8px}.h2-timer span{background:rgba(255,255,255,.1);border-radius:10px;padding:8px 10px;min-width:56px;text-align:center;display:flex;flex-direction:column}.h2-timer b{font-size:20px}.h2-timer small{font-size:10px;letter-spacing:.1em;text-transform:uppercase;opacity:.7}
.h2-dealimg{position:absolute;right:-4%;bottom:-6%;width:52%;height:92%;object-fit:contain;filter:drop-shadow(0 24px 30px rgba(0,0,0,.45))}
/* doelen */
.h2-goals{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
.h2 .h2-goal{color:#fff}.h2-goal{aspect-ratio:3/4;border-radius:20px;background-size:cover;background-position:center;display:flex;align-items:flex-end;padding:22px;position:relative;overflow:hidden;color:#fff}
.h2-goal:before{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(20,32,74,.95) 0%,rgba(20,32,74,.2) 55%,transparent 100%)}
.h2-goal span{position:relative;display:flex;flex-direction:column;gap:4px}.h2-goal strong{font-size:24px;font-weight:900;text-transform:uppercase;line-height:1.05}.h2-goal em{font-style:normal;font-size:13px;color:rgba(255,255,255,.85)}
.h2-goal:hover{outline:3px solid var(--rd);outline-offset:-3px}
/* band */
.h2-band{margin-top:56px;background:var(--nv);color:#fff;border-radius:24px;padding:48px 56px;display:grid;grid-template-columns:1.2fr 1fr;gap:40px;align-items:center}
.h2-bandtxt{display:flex;flex-direction:column;gap:16px}.h2-band h2{color:#fff;font-size:40px;font-weight:900}.h2-band h2 em{font-style:normal;color:transparent;-webkit-text-stroke:1.5px #fff}
.h2-band p{margin:0;font-size:16px;line-height:1.6;color:rgba(255,255,255,.82);max-width:560px}
.h2-bandcards{display:flex;flex-direction:column;gap:12px}.h2 .h2-bandcards a{color:#fff}.h2-bandcards a{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);border-radius:16px;padding:18px 20px;display:flex;flex-direction:column;gap:4px}
.h2-bandcards a:hover{background:rgba(255,255,255,.14)}.h2-bandcards strong{font-size:17px}.h2-bandcards span{font-size:13px;color:rgba(255,255,255,.75)}
/* merken */
.h2-brands{margin-top:56px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;background:var(--bg);border-radius:18px;padding:18px 22px}
.h2-brands>span{font-weight:800;font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:var(--nv);margin-right:8px}
.h2 .h2-brands a{color:var(--nv)}.h2-brands a{background:#fff;border:1px solid #e6e8f0;border-radius:999px;padding:9px 14px;font-weight:700;font-size:13px;color:var(--nv)}.h2-brands a:hover{border-color:var(--nv)}.h2-brands-all{background:var(--nv)!important;color:#fff!important}
/* blog */
.h2-posts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
.h2 .h2-post{color:var(--nv)}.h2-post{display:flex;flex-direction:column;gap:12px}.h2-post img{width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:16px}
.h2-post span{display:flex;flex-direction:column;gap:4px}.h2-post em{font-style:normal;font-size:12px;color:#777}.h2-post strong{font-size:17px;color:var(--nv);line-height:1.3}
@media(max-width:1024px){.h2-cats{grid-template-columns:repeat(4,minmax(0,1fr))}.h2-usps{grid-template-columns:1fr 1fr}.h2-grid4{grid-template-columns:repeat(3,minmax(0,1fr))}.h2-goals{grid-template-columns:1fr 1fr}.h2-dealgrid{grid-template-columns:1fr}.h2-band{grid-template-columns:1fr;padding:36px}.h2-hero{grid-template-columns:1fr;padding:36px 28px;min-height:0}.h2-herovis{min-height:300px}.h2-cluster{min-height:300px}}
@media(max-width:640px){.h2{padding:12px 14px 32px}.h2-cats{grid-template-columns:1fr 1fr}.h2-usps{grid-template-columns:1fr}.h2-grid4,.h2-grid3{grid-template-columns:1fr 1fr;gap:10px}.h2-goals{grid-template-columns:1fr 1fr;gap:10px}.h2-goal{padding:14px}.h2-goal strong{font-size:17px}.h2-posts{grid-template-columns:1fr}.h2-deal{grid-template-columns:1fr;padding:22px}.h2-dealimg{position:static;width:100%;height:220px;order:-1}.h2-sechead h2{font-size:22px}.h2-band h2{font-size:30px}.h2-hero h1{font-size:40px}.h2-card{padding:10px}.h2-cbtn{padding:11px;font-size:13px}.h2-brands{padding:14px}}
</style>
<script>
(function(){var t=document.querySelector('[data-h2-timer]');if(!t)return;var b=t.querySelectorAll('b');function tick(){var n=new Date(),e=new Date(n);e.setHours(23,59,59,999);var s=Math.max(0,Math.floor((e-n)/1000));b[0].textContent=('0'+Math.floor(s/3600)).slice(-2);b[1].textContent=('0'+Math.floor(s%3600/60)).slice(-2);b[2].textContent=('0'+s%60).slice(-2)}tick();setInterval(tick,1000)})();
</script>
<?php
	return ob_get_clean();
});
