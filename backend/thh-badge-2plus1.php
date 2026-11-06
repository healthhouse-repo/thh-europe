<?php
/**
 * THH — Badge "2+1 GRATIS"
 *
 * Ronde navy badge met witte ring, "2+1" (rode plus) en rode band "GRATIS", als inline SVG.
 * Aanzetten per product: vinkje "2+1 gratis badge" in het tabblad Algemeen van het product
 * (meta _thh_2plus1 = 1) of de producttag "2+1 gratis" (slug 2-plus-1).
 *
 * Verschijnt op: productkaarten in de shop/categorieën, de productkaarten van de homepage (#450,
 * via filter thh_h2_card_extra) en linksboven op de foto van de productpagina.
 * Alleen de badge: de actie zelf (3e gratis) regel je met een kortingsregel of los.
 */
if (!defined('ABSPATH')) return;

if (!function_exists('thh_b21_actief')) {

function thh_b21_actief($p) {
	if (!$p) return false;
	$id = $p->get_parent_id() ?: $p->get_id();
	if (get_post_meta($id, '_thh_2plus1', true) === '1') return true;
	return has_term('2-plus-1', 'product_tag', $id);
}

function thh_b21_svg($size = 72) {
	$s = (int) $size;
	return '<svg class="thh-b21-svg" width="' . $s . '" height="' . $s . '" viewBox="0 0 200 200" role="img" aria-label="2 plus 1 gratis">'
		. '<circle cx="100" cy="100" r="98" fill="#14204a"/>'
		. '<path d="M30 130 L170 20 A98 98 0 0 1 170 170 Z" fill="#1d2c63" opacity=".75"/>'
		. '<circle cx="100" cy="100" r="88" fill="none" stroke="#fff" stroke-width="3"/>'
		. '<text x="100" y="104" text-anchor="middle" font-family="Poppins,Montserrat,Arial Black,Arial,sans-serif" font-weight="900" font-size="78" fill="#fff" letter-spacing="-2">2<tspan fill="#e63946" font-size="62" dy="-2">+</tspan><tspan dy="2">1</tspan></text>'
		. '<path d="M42 120 h118 l-6 36 H36 Z" fill="#e63946"/>'
		. '<text x="100" y="147" text-anchor="middle" font-family="Poppins,Montserrat,Arial,sans-serif" font-weight="800" font-style="italic" font-size="26" fill="#fff" letter-spacing="1.5">GRATIS</text>'
		. '</svg>';
}

/* Productkaarten in shop/categorie (standaard WooCommerce-loop) */
add_action('woocommerce_before_shop_loop_item_title', function () {
	global $product;
	if (thh_b21_actief($product)) echo '<span class="thh-b21 thh-b21-loop">' . thh_b21_svg(64) . '</span>';
}, 9);

/* Productkaarten homepage (#450) */
add_filter('thh_h2_card_extra', function ($html, $p) {
	return thh_b21_actief($p) ? $html . '<span class="thh-b21 thh-b21-loop">' . thh_b21_svg(64) . '</span>' : $html;
}, 10, 2);

/* Productpagina: op de foto */
add_action('woocommerce_before_single_product_summary', function () {
	global $product;
	if (thh_b21_actief($product)) echo '<span class="thh-b21 thh-b21-single">' . thh_b21_svg(88) . '</span>';
}, 25);

add_action('wp_head', function () {
	echo '<style>.thh-b21{position:absolute;z-index:5;line-height:0;pointer-events:none;filter:drop-shadow(0 4px 10px rgba(20,32,74,.35))}.thh-b21-loop{top:8px;right:8px}.thh-b21-loop .thh-b21-svg{width:64px;height:64px}@media(max-width:640px){.thh-b21-loop .thh-b21-svg{width:52px;height:52px}}.thh-b21-single{top:14px;left:14px}.thh-b21-single .thh-b21-svg{width:88px;height:88px}.woocommerce-product-gallery,li.product,.h2-cimg,.products .product{position:relative}@media(max-width:640px){.thh-b21-single .thh-b21-svg{width:68px;height:68px}}</style>';
});

/* Productpagina: badge in de galerij zetten als het thema de hook elders plaatst */
add_action('wp_footer', function () {
	if (!function_exists('is_product') || !is_product()) return;
	echo '<script>(function(){var b=document.querySelector(".thh-b21-single"),g=document.querySelector(".woocommerce-product-gallery");if(b&&g&&!g.contains(b)){g.style.position="relative";g.appendChild(b)}})();</script>';
});

/* Vinkje in het product (tabblad Algemeen) */
add_action('woocommerce_product_options_general_product_data', function () {
	woocommerce_wp_checkbox(['id' => '_thh_2plus1', 'label' => '2+1 gratis badge', 'description' => 'Toont de ronde badge "2+1 GRATIS" op de productkaart en de productfoto.', 'desc_tip' => false, 'cbvalue' => '1']);
});
add_action('woocommerce_process_product_meta', function ($post_id) {
	if (!empty($_POST['_thh_2plus1'])) update_post_meta($post_id, '_thh_2plus1', '1');
	else delete_post_meta($post_id, '_thh_2plus1');
});

} // function_exists
