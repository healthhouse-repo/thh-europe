<?php
/**
 * THH In-store deals — pagina /in-store-deals/ in dezelfde stijl als /weekdeals/.
 * Shortcode: [thh_instore_deals]. Toont alle producten (op voorraad) uit de productcategorie
 * "In-Store Deals" (slug in-store-deals). Producten toevoegen = categorie aanvinken in WooCommerce.
 * De opmaak (CSS) wordt overgenomen van de weekdealspagina (pagina 91310), zodat beide pagina's gelijk blijven.
 */

add_shortcode('thh_instore_deals', function () {
	$u = function ($p) { return esc_url(home_url($p)); };
	$pid = get_queried_object_id();

	// CSS van de weekdealspagina hergebruiken
	$css = '';
	$wd = (string) get_post_field('post_content', 91310);
	if ($wd && preg_match('#<style>(.*?)</style>#s', $wd, $m)) $css = str_replace('page-id-91310', 'page-id-' . (int) $pid, $m[1]);

	$q = function_exists('wc_get_products') ? wc_get_products(['status' => 'publish', 'limit' => 60, 'category' => ['in-store-deals'], 'stock_status' => 'instock', 'orderby' => 'menu_order', 'order' => 'ASC']) : [];
	$cards = '';
	$min = null;
	foreach ($q as $p) {
		$reg = (float) $p->get_regular_price();
		$prijs = (float) $p->get_price();
		if ($p->is_type('variable')) { $reg = (float) $p->get_variation_regular_price('min'); $prijs = (float) $p->get_variation_price('min'); }
		if ($prijs > 0 && ($min === null || $prijs < $min)) $min = $prijs;
		$off = ($reg > 0 && $prijs > 0 && $prijs < $reg) ? round((1 - $prijs / $reg) * 100) : 0;
		$img = wp_get_attachment_image_url($p->get_image_id(), 'woocommerce_single') ?: wc_placeholder_img_src();
		$merk = '';
		$t = get_the_terms($p->get_id(), 'product_brand'); if ($t && !is_wp_error($t)) $merk = $t[0]->name;
		$link = add_query_arg(['utm_source' => 'site', 'utm_medium' => 'instorepagina', 'utm_campaign' => 'instore-deals'], get_permalink($p->get_id()));
		$cards .= '<a class="wd-card" href="' . esc_url($link) . '"><div class="wd-img"><img src="' . esc_url($img) . '" alt="' . esc_attr($p->get_name()) . '" loading="lazy">'
			. ($off ? '<span class="wd-off">-' . $off . '%</span>' : '')
			. '<span class="wd-pr"><small>In de winkel</small><b>' . wp_strip_all_tags(wc_price($prijs)) . '</b></span></div>'
			. '<div class="wd-tag">In-store deal</div><h3>' . esc_html($p->get_name()) . '</h3>'
			. ($merk ? '<p class="wd-s">' . esc_html($merk) . '</p>' : '')
			. '<p class="wd-p">' . ($off ? '<s>' . wp_strip_all_tags(wc_price($reg)) . '</s>' : '') . '<strong>' . wp_strip_all_tags(wc_price($prijs)) . '</strong></p>'
			. '<span class="wd-btn">Bekijk product</span></a>';
	}

	ob_start(); ?>
<div class="wd isd">
	<section class="wd-hero">
		<div class="wd-hi">
			<div>
				<span class="wd-lab">In-store deals</span>
				<h1>Deals in<br><span>de winkel</span></h1>
				<p class="wd-sub">Extra voordeel dat je alleen in onze winkels krijgt, met gratis advies erbij</p>
				<div class="isd-ctas"><a class="wd-cta" href="<?php echo $cards ? '#wd-deals' : $u('/locaties/'); ?>"><?php echo $cards ? 'Bekijk de deals' : 'Vind een winkel'; ?></a><a class="isd-ghost" href="<?php echo $u('/locaties/'); ?>">Onze winkels</a></div>
			</div>
			<div class="wd-art isd-art"><img src="https://thehealthhouse.nl/wp-content/uploads/2026/11/thh-over-winkel-binnen.webp" alt="Binnenkant van een The Health House winkel" loading="eager"><?php if ($min) : ?><div class="wd-badge"><span>vanaf</span><strong><?php echo wp_strip_all_tags(wc_price($min)); ?></strong></div><?php endif; ?></div>
		</div>
	</section>
	<section class="wd-body" id="wd-deals">
		<h2 class="wd-h2">De in-store deals</h2>
		<p class="wd-intro">Deze deals gelden in al onze winkels, zolang de voorraad strekt. Kom langs, laat je adviseren en neem je deal direct mee.</p>
		<?php if ($cards) : ?>
		<div class="wd-grid"><?php echo $cards; ?></div>
		<?php else : ?>
		<div class="isd-leeg"><strong>Er staan op dit moment geen in-store deals online.</strong><p>In de winkel is er vaak toch iets te halen. Kom langs of stuur ons een WhatsApp, dan vertellen we je wat er nu in de aanbieding is.</p><div class="isd-ctas"><a class="wd-cta" href="<?php echo $u('/locaties/'); ?>">Vind een winkel</a><a class="isd-wa" href="https://wa.me/31611789629" target="_blank" rel="noopener nofollow">WhatsApp ons</a></div></div>
		<?php endif; ?>
		<div class="isd-more"><a href="<?php echo $u('/dagdeal/'); ?>"><strong>Dagdeal</strong><span>Elke dag een nieuwe deal</span></a><a href="<?php echo $u('/weekdeals/'); ?>"><strong>Weekdeals</strong><span>Alle deals van deze week</span></a><a href="<?php echo $u('/locaties/'); ?>"><strong>Onze winkels</strong><span>Advies en je bestelling ophalen</span></a></div>
	</section>
</div>
<style>
<?php echo $css; ?>
body.page-id-<?php echo (int) $pid; ?> .page-header{display:none}
body.page-id-<?php echo (int) $pid; ?> .site-main,body.page-id-<?php echo (int) $pid; ?> .page-content{max-width:none!important;width:100%!important;padding:0!important;margin:0!important}
.isd .isd-art img{width:100%;height:auto;aspect-ratio:4/3;object-fit:cover;border-radius:20px;display:block}
.isd-ctas{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:8px}
.isd .isd-ghost{display:inline-flex;align-items:center;min-height:48px;padding:0 26px;border:2px solid rgba(255,255,255,.7);border-radius:999px;color:#fff!important;font-weight:600;text-decoration:none!important}
.isd .isd-ghost:hover{background:rgba(255,255,255,.12)}
.isd-leeg{background:#fff;border-radius:20px;padding:36px;text-align:center;max-width:720px;margin:8px auto 0}
.isd-leeg strong{display:block;font-size:20px;color:#03124F;margin-bottom:8px}.isd-leeg p{margin:0 auto 18px;max-width:520px;line-height:1.6;color:#4a5378}
.isd-leeg .isd-ctas{justify-content:center}
.isd .isd-wa{display:inline-flex;align-items:center;min-height:48px;padding:0 26px;border-radius:999px;background:#25D366;color:#fff!important;font-weight:600;text-decoration:none!important}
.isd-more{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:40px}
.isd .isd-more a{background:#fff;border-radius:16px;padding:20px;display:flex;flex-direction:column;gap:4px;color:#03124F!important;text-decoration:none!important;transition:transform .15s}
.isd .isd-more a:hover{transform:translateY(-2px)}.isd-more strong{font-size:16px}.isd-more span{font-size:13px;color:#4a5378}
@media(max-width:700px){.isd-more{grid-template-columns:minmax(0,1fr)}.isd-ctas{flex-direction:column;align-items:stretch}.isd .isd-ghost,.isd .isd-wa{justify-content:center}.isd-leeg{padding:26px 20px}}
</style>
<?php
	return ob_get_clean();
});
