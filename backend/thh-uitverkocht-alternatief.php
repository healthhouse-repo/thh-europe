// THH: toon alternatief (upsells) op uitverkochte productpagina's
add_action('wp_footer', function () {
	if (!function_exists('is_product') || !is_product()) return;
	$p = wc_get_product(get_queried_object_id());
	if (!$p || $p->is_in_stock()) return;
	$ids = array_filter(array_map('intval', $p->get_upsell_ids()));
	$items = '';
	foreach ($ids as $id) {
		$a = wc_get_product($id);
		if (!$a || !$a->is_visible() || !$a->is_in_stock()) continue;
		$img = wp_get_attachment_image_url($a->get_image_id(), 'woocommerce_thumbnail');
		$items .= '<a class="thh-alt-item" href="' . esc_url($a->get_permalink()) . '">'
			. ($img ? '<img src="' . esc_url($img) . '" alt="">' : '')
			. '<span><strong>' . esc_html($a->get_name()) . '</strong><em>' . wp_kses_post($a->get_price_html()) . '</em><u>Bekijk alternatief</u></span></a>';
	}
	if (!$items) return;
	echo '<template id="thh-alt-tpl"><div class="thh-alt"><div class="thh-alt-h">Dit product is uitverkocht. Probeer dit alternatief:</div>' . $items . '</div></template>';
	?>
	<style>.thh-alt{margin:16px 0;padding:14px;border:2px solid #111;border-radius:14px;background:#fff}.thh-alt-h{font-weight:700;margin-bottom:10px}.thh-alt-item{display:flex;gap:12px;align-items:center;text-decoration:none;color:inherit;padding:6px 0}.thh-alt-item img{width:72px;height:72px;object-fit:contain;border-radius:10px;background:#fff}.thh-alt-item span{display:flex;flex-direction:column;gap:2px}.thh-alt-item em{font-style:normal}.thh-alt-item u{font-weight:600}</style>
	<script>(function(){var t=document.getElementById('thh-alt-tpl');if(!t)return;var n=t.content.firstElementChild.cloneNode(true);var a=document.querySelector('form.cart,.stock.out-of-stock,.elementor-widget-woocommerce-product-add-to-cart,.elementor-widget-woocommerce-product-price,.product_title');if(a){a.parentNode.insertBefore(n,a.nextSibling);}else{(document.querySelector('main')||document.body).prepend(n);}})();</script>
	<?php
}, 20);
