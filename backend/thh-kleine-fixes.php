<?php
/**
 * THH kleine fixes (1 nov 2026)
 * - Winkelwagen: cadeauregel (snippet #193) toont een productfoto met GRATIS-badge i.p.v. alleen een rood rondje.
 * - Chat (snippet #82): lange vragen lopen niet meer buiten beeld (thema zet nowrap op buttons).
 */
if (!defined('ABSPATH')) return;

add_filter('woocommerce_cart_item_thumbnail', function ($html, $item = null) {
	if (!is_array($item) || empty($item['thh_gratis_shaker'])) return $html;
	$ids = [];
	if (!empty($item['thh_cadeau_gekozen'])) $ids[] = (int) $item['thh_cadeau_gekozen'];
	$ids[] = (int) $item['thh_gratis_shaker'];
	$ids[] = 65065; // XXL shaker als terugval
	$img = '';
	foreach ($ids as $id) {
		$p = wc_get_product($id);
		if ($p && $p->get_image_id()) { $img = wp_get_attachment_image_url($p->get_image_id(), 'woocommerce_thumbnail'); break; }
		if ($p && $p->get_parent_id()) { $pp = wc_get_product($p->get_parent_id()); if ($pp && $pp->get_image_id()) { $img = wp_get_attachment_image_url($pp->get_image_id(), 'woocommerce_thumbnail'); break; } }
	}
	if (!$img) return $html;
	$label = function_exists('thh_i18n_lang') && thh_i18n_lang() === 'en' ? 'FREE' : 'GRATIS';
	return '<span class="thh-gift-thumb"><img src="' . esc_url($img) . '" alt="" loading="lazy"><b>' . $label . '</b></span>';
}, 30, 2);

add_action('wp_head', function () {
	echo '<style id="thh-kleine-fixes">'
		. '.thh-gift-thumb{position:relative;display:inline-grid;place-items:center;width:84px;height:84px;background:#f5f5f6;border-radius:12px;overflow:visible;margin:4px}'
		. '.thh-gift-thumb img{max-width:82%!important;max-height:82%!important;width:auto!important;height:auto!important;object-fit:contain;mix-blend-mode:multiply;border-radius:0!important}'
		. '.thh-gift-thumb b{position:absolute;top:-8px;right:-10px;background:#E63946;color:#fff;font-size:10.5px;font-weight:800;letter-spacing:.06em;padding:4px 8px;border-radius:999px;box-shadow:0 2px 6px rgba(0,0,0,.18);line-height:1}'
		. '.thhc-card{white-space:normal!important;min-width:0}'
		. '.thhc-card>span:not(.thhc-card-ico):not(.thhc-card-arrow){flex:1 1 auto;min-width:0;white-space:normal!important;overflow-wrap:anywhere;text-align:left}'
		. '</style>';
}, 99);
