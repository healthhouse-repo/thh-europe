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

/* Chat: placeholder en aria-label van het vraagveld in de juiste taal. */
add_action('wp_footer', function () {
	$lang = function_exists('thh_i18n_lang') ? thh_i18n_lang() : 'nl';
	if ($lang === 'nl') return;
	$t = ['de' => ['Stell mir eine Frage', 'Stell deine Frage', 'Schreib deine Nachricht…'], 'en' => ['Ask me a question', 'Ask your question', 'Type your message…']][$lang] ?? null;
	if (!$t) return;
	echo '<script>(function(){function f(){document.querySelectorAll("#thhcAsk,#thhcInput").forEach(function(el){var ph=el.getAttribute("placeholder")||"";if(/Typ je bericht/i.test(ph))el.setAttribute("placeholder",' . wp_json_encode($t[2]) . ');else if(/Stel me een vraag|Stel je vraag|Typ je vraag/i.test(ph))el.setAttribute("placeholder",' . wp_json_encode($t[0]) . ');if(/Stel je vraag/i.test(el.getAttribute("aria-label")||""))el.setAttribute("aria-label",' . wp_json_encode($t[1]) . ');});}if(document.readyState!=="loading")f();else document.addEventListener("DOMContentLoaded",f);setTimeout(f,1500);})();</script>';
}, 99);

/* Chat: sluitknop (en nieuw-gesprek) duidelijk zichtbaar rechtsboven */
add_action('wp_head', function () {
	echo '<style id="thh-chat-knoppen">#thhcPanel .thhc-head-actions{display:flex!important;gap:8px!important;align-items:center}#thhcPanel #thhcClose,#thhcPanel #thhcReset{width:38px!important;height:38px!important;min-width:38px;padding:0!important;margin:0!important;border:0!important;border-radius:50%!important;background:rgba(255,255,255,.14)!important;color:#fff!important;display:grid!important;place-items:center!important;cursor:pointer;box-shadow:none!important;position:relative}#thhcPanel #thhcClose:hover,#thhcPanel #thhcReset:hover{background:rgba(255,255,255,.26)!important}#thhcPanel #thhcReset svg{width:18px!important;height:18px!important;display:block!important;color:#fff!important;stroke:#fff!important}#thhcPanel #thhcClose svg{display:none!important}#thhcPanel #thhcClose:before,#thhcPanel #thhcClose:after{content:"";position:absolute;left:50%;top:50%;width:16px;height:2px;background:#fff;border-radius:2px;transform:translate(-50%,-50%) rotate(45deg)}#thhcPanel #thhcClose:after{transform:translate(-50%,-50%) rotate(-45deg)}</style>';
}, 99);

