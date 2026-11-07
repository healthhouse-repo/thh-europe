<?php
/**
 * THH — Badge "2+1 GRATIS"
 *
 * Ronde navy badge met witte ring, "2+1" (rode plus) en rode band "GRATIS", als inline SVG.
 * Aanzetten per product: categorie "2+1 Deals" (slug 2-1-deals), vinkje "2+1 gratis badge" in het tabblad
 * Algemeen van het product (meta _thh_2plus1 = 1) of de producttag "2+1 gratis" (slug 2-plus-1).
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
	if (has_term('2-1-deals', 'product_cat', $id)) return true;
	return has_term('2-plus-1', 'product_tag', $id);
}

function thh_b21_svg($size = 72) {
	$s = (int) $size;
	return '<svg class="thh-b21-svg" width="' . $s . '" height="' . $s . '" viewBox="0 0 200 200" role="img" aria-label="2 plus 1 gratis">'
		. '<clipPath id="thhb21c"><circle cx="100" cy="100" r="100"/></clipPath>'
		. '<circle cx="100" cy="100" r="100" fill="#14204a"/>'
		. '<path clip-path="url(#thhb21c)" d="M30 130 L170 20 L200 20 L200 200 Z" fill="#1d2c63" opacity=".75"/>'
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

add_action('wp_head', function () {
	echo '<style>.thh-b21{position:absolute;z-index:5;line-height:0;pointer-events:none}.thh-b21-svg{display:block;border-radius:50%;overflow:hidden}.thh-b21-loop{top:8px;right:8px}.thh-b21-loop .thh-b21-svg{width:64px;height:64px}@media(max-width:640px){.thh-b21-loop .thh-b21-svg{width:52px;height:52px}}.thh-b21-single{top:14px;right:14px}.thh-b21-single .thh-b21-svg{width:96px;height:96px}.woocommerce-product-gallery,li.product,.h2-cimg,.products .product{position:relative}@media(max-width:640px){.thh-b21-single .thh-b21-svg{width:68px;height:68px}}.thh-xxlh-item.is-btn>a{color:#e63946!important;font-weight:700}.thh-xxlh-item.is-btn>a:hover{color:#c62828!important}</style>';
});

/* Productpagina (Elementor-template slaat de WooCommerce-hook over): badge via de voet in de galerij zetten */
add_action('wp_footer', function () {
	if (!function_exists('is_product') || !is_product()) return;
	$p = wc_get_product(get_the_ID());
	if (!thh_b21_actief($p)) return;
	$svg = wp_json_encode('<span class="thh-b21 thh-b21-single">' . thh_b21_svg(88) . '</span>');
	$uitleg = wp_json_encode('<p class="thh-b21-uitleg" style="margin:8px 0 12px;font-weight:600;color:#14204a">2+1 gratis: bestel er 3, de derde verpakking krijg je helemaal gratis. Smaken en maten mag je combineren.</p>');
	echo '<script>(function(){function z(){if(document.querySelector(".thh-b21-single"))return true;var g=document.querySelector(".woocommerce-product-gallery")||document.querySelector(".elementor-widget-woocommerce-product-images");if(!g)return false;g.style.position="relative";g.insertAdjacentHTML("beforeend",' . $svg . ');var p=document.querySelector(".elementor-widget-woocommerce-product-price")||document.querySelector(".summary .price, p.price");if(p&&!document.querySelector(".thh-b21-uitleg"))p.insertAdjacentHTML("afterend",' . $uitleg . ');return true}if(!z()){var n=0,t=setInterval(function(){if(z()||++n>20)clearInterval(t)},300)}})();</script>';
});

/* Winkelwagen: per 3 stuks van een 2+1-product is de goedkoopste gratis (smaken/maten tellen samen) */
function thh_b21_groepen($cart) {
	$g = [];
	foreach ($cart->get_cart() as $key => $item) {
		$p = $item['data'] ?? null;
		if (!$p || !thh_b21_actief($p)) continue;
		$pid = $p->get_parent_id() ?: $p->get_id();
		$qty = (int) $item['quantity'];
		$unit = (float) $p->get_price();
		$g[$pid]['naam'] = $p->get_parent_id() ? wc_get_product($pid)->get_name() : $p->get_name();
		$g[$pid]['tax'] = $p->get_tax_class();
		$g[$pid]['qty'] = ($g[$pid]['qty'] ?? 0) + $qty;
		$g[$pid]['keys'][] = $key;
		for ($i = 0; $i < $qty; $i++) $g[$pid]['prijzen'][] = $unit;
	}
	foreach ($g as &$x) { sort($x['prijzen']); $x['gratis'] = intdiv($x['qty'], 3); $x['korting'] = array_sum(array_slice($x['prijzen'], 0, $x['gratis'])); }
	return $g;
}
add_action('woocommerce_cart_calculate_fees', function ($cart) {
	if (is_admin() && !defined('DOING_AJAX')) return;
	foreach (thh_b21_groepen($cart) as $x) {
		if ($x['gratis'] < 1 || $x['korting'] <= 0) continue;
		$cart->add_fee('2+1 gratis: ' . $x['naam'] . ($x['gratis'] > 1 ? ' (' . $x['gratis'] . 'x)' : ''), -$x['korting'], true, $x['tax']);
	}
}, 20);
/* Melding bij de regel in de winkelwagen */
add_filter('woocommerce_cart_item_name', function ($name, $item, $key) {
	$p = $item['data'] ?? null;
	if (!$p || !thh_b21_actief($p) || !WC()->cart) return $name;
	$pid = $p->get_parent_id() ?: $p->get_id();
	$g = thh_b21_groepen(WC()->cart);
	if (empty($g[$pid])) return $name;
	$q = $g[$pid]['qty']; $rest = $q % 3;
	$txt = $g[$pid]['gratis'] ? $g[$pid]['gratis'] . ' gratis, al verrekend' : '';
	if ($rest === 2) $txt = ($txt ? $txt . ' · ' : '') . 'nog 1 erbij en die is gratis';
	elseif ($rest === 1) $txt = ($txt ? $txt . ' · ' : '') . 'nog 2 erbij en de 3e is gratis';
	return $name . ($txt ? '<br><small style="color:#14204a;font-weight:600">2+1 gratis: ' . esc_html($txt) . '</small>' : '');
}, 10, 3);
/* Melding bovenaan winkelwagen en afrekenen */
function thh_b21_melding() {
	if (!WC()->cart || WC()->cart->is_empty()) return;
	foreach (thh_b21_groepen(WC()->cart) as $x) {
		$rest = $x['qty'] % 3;
		if ($rest === 2) wc_print_notice('<strong>2+1 gratis:</strong> bestel je ' . esc_html($x['naam']) . ' nog een keer, dan ontvang je een derde verpakking helemaal gratis!', 'notice');
		elseif ($rest === 1) wc_print_notice('<strong>2+1 gratis:</strong> bestel je ' . esc_html($x['naam']) . ' nog twee keer, dan ontvang je de derde verpakking helemaal gratis!', 'notice');
	}
}
add_action('woocommerce_before_cart', 'thh_b21_melding', 5);
add_action('woocommerce_before_checkout_form', 'thh_b21_melding', 5);

/* Staffelkorting (Bundelkiezer 1-2-3, snippet 143) uit voor 2+1-producten: ze gaan in de uitsluitlijst thh_bundel_excl */
function thh_b21_ids() {
	$ids = get_transient('thh_b21_ids');
	if ($ids !== false) return $ids;
	global $wpdb;
	$ids = array_map('intval', $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thh_2plus1' AND meta_value = '1'"));
	$tag = get_posts(['post_type' => 'product', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any', 'tax_query' => [['taxonomy' => 'product_tag', 'field' => 'slug', 'terms' => '2-plus-1']]]);
	$cat = get_posts(['post_type' => 'product', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any', 'tax_query' => [['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => '2-1-deals']]]);
	$ids = array_values(array_unique(array_merge($ids, array_map('intval', $tag), array_map('intval', $cat))));
	set_transient('thh_b21_ids', $ids, HOUR_IN_SECONDS);
	return $ids;
}
add_filter('option_thh_bundel_excl', function ($excl) {
	$excl = is_array($excl) ? $excl : [];
	return array_values(array_unique(array_merge(array_map('strval', $excl), array_map('strval', thh_b21_ids()))));
});
add_filter('default_option_thh_bundel_excl', function ($d) { return array_map('strval', thh_b21_ids()); });
add_action('woocommerce_process_product_meta', function () { delete_transient('thh_b21_ids'); }, 5);
add_action('set_object_terms', function ($object_id, $terms, $tt_ids, $taxonomy) { if ($taxonomy === 'product_tag' || $taxonomy === 'product_cat') delete_transient('thh_b21_ids'); }, 10, 4);

/* Vinkje in het product (tabblad Algemeen) */
add_action('woocommerce_product_options_general_product_data', function () {
	woocommerce_wp_checkbox(['id' => '_thh_2plus1', 'label' => '2+1 gratis badge', 'description' => 'Toont de ronde badge "2+1 GRATIS" op de productkaart en de productfoto.', 'desc_tip' => false, 'cbvalue' => '1']);
});
add_action('woocommerce_process_product_meta', function ($post_id) {
	if (!empty($_POST['_thh_2plus1'])) update_post_meta($post_id, '_thh_2plus1', '1');
	else delete_post_meta($post_id, '_thh_2plus1');
});

} // function_exists
