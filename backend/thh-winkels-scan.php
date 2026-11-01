<?php
/**
 * THH winkel-scan (tijdelijk hulpmiddel): waar staan de winkellijsten op de site?
 * GET /wp-json/thh/v1/winkels/scan?zoek=Osdorpplein  (beheerder)
 */
if (!defined('ABSPATH')) return;
add_action('rest_api_init', function () {
	register_rest_route('thh/v1', '/winkels/scan', ['methods' => 'GET', 'permission_callback' => function () { return current_user_can('manage_options'); }, 'callback' => function ($r) {
		global $wpdb;
		$z = sanitize_text_field($r['zoek'] ?: 'Osdorpplein');
		$like = '%' . $wpdb->esc_like($z) . '%';
		$rows = $wpdb->get_results($wpdb->prepare("SELECT p.ID, p.post_type, p.post_status, p.post_title, (p.post_content LIKE %s) AS in_content, (m.meta_value LIKE %s) AS in_elementor
			FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
			WHERE p.post_status IN ('publish','private') AND p.post_type NOT IN ('revision','attachment','product','product_variation','shop_order','wpsl_stores')
			AND (p.post_content LIKE %s OR m.meta_value LIKE %s)", $like, $like, $like, $like), ARRAY_A);
		$out = [];
		foreach ($rows as $row) {
			$ctx = [];
			$src = $row['in_elementor'] ? (string) get_post_meta($row['ID'], '_elementor_data', true) : get_post($row['ID'])->post_content;
			$src = html_entity_decode(wp_strip_all_tags(str_replace(['\\n', '\\/'], [' ', '/'], $src)));
			if (preg_match_all('/.{0,70}' . preg_quote($z, '/') . '.{0,70}/u', $src, $m)) $ctx = array_slice(array_unique($m[0]), 0, 3);
			$out[] = ['id' => (int) $row['ID'], 'type' => $row['post_type'], 'status' => $row['post_status'], 'title' => $row['post_title'], 'elementor' => (bool) $row['in_elementor'], 'ctx' => $ctx];
		}
		$menus = [];
		foreach (wp_get_nav_menus() as $menu) foreach (wp_get_nav_menu_items($menu) as $it) if (stripos($it->title, $z) !== false || stripos($it->title, 'winkel') !== false || stripos($it->title, 'locatie') !== false) $menus[] = $menu->name . ' > ' . $it->title . ' (' . $it->url . ')';
		return ['zoek' => $z, 'posts' => $out, 'menu_items' => $menus, 'snippets' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}snippets WHERE code LIKE %s AND active = 1", $like))];
	}]);
});

// Boomstructuur van een Elementor-template (om te zien hoe winkelblokken zijn opgebouwd)
add_action('rest_api_init', function () {
	register_rest_route('thh/v1', '/winkels/boom', ['methods' => 'GET', 'permission_callback' => function () { return current_user_can('manage_options'); }, 'callback' => function ($r) {
		$id = (int) $r['id'];
		$data = json_decode((string) get_post_meta($id, '_elementor_data', true), true);
		if (!$data) return new WP_Error('leeg', 'geen elementor data', ['status' => 404]);
		$walk = function ($els, $depth, $path) use (&$walk) {
			$out = [];
			foreach ($els as $i => $el) {
				$s = $el['settings'] ?? [];
				$txt = $s['title'] ?? ($s['description_text'] ?? ($s['editor'] ?? ($s['text'] ?? ($s['title_text'] ?? ''))));
				$txt = is_string($txt) ? trim(wp_strip_all_tags($txt)) : '';
				$out[] = str_repeat('  ', $depth) . $path . $i . ' ' . $el['elType'] . ($el['widgetType'] ?? '' ? ':' . $el['widgetType'] : '') . ' #' . $el['id'] . ($txt !== '' ? ' "' . mb_substr($txt, 0, 60) . '"' : '') . (!empty($s['_css_classes']) ? ' .' . $s['_css_classes'] : '');
				if (!empty($el['elements'])) $out = array_merge($out, $walk($el['elements'], $depth + 1, $path . $i . '.'));
			}
			return $out;
		};
		return $walk($data, 0, '');
	}]);
});

// Merk toekennen aan geïmporteerde producten (op basis van meta _thh_un_src_id) + overzicht
add_action('rest_api_init', function () {
	register_rest_route('thh/v1', '/import/merk', ['methods' => 'POST', 'permission_callback' => function () { return current_user_can('manage_woocommerce'); }, 'callback' => function ($r) {
		$meta = sanitize_key($r['meta'] ?: '_thh_un_src_id');
		$term = (int) $r['term'];
		$tax = taxonomy_exists('product_brand') ? 'product_brand' : 'brands';
		$ids = get_posts(['post_type' => 'product', 'post_status' => ['publish', 'draft', 'private'], 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => $meta]);
		$n = 0;
		foreach ($ids as $id) { if ($term) wp_set_object_terms($id, [$term], $tax, false); $n++; }
		return ['products' => count($ids), 'set' => $n, 'tax' => $tax];
	}]);
});
