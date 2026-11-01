<?php
/**
 * THH: outletwinkel "Amsterdam The Style Outlets" (Halfweg) overal toevoegen.
 * POST /wp-json/thh/v1/winkels/outlet  (beheerder) — idempotent, maakt back-ups van gewijzigde content (_thh_outlet_backup_*).
 * POST /wp-json/thh/v1/winkels/outlet-revert
 */
if (!defined('ABSPATH')) return;

function thh_outlet_info() {
	return [
		'naam' => 'Amsterdam The Style Outlets', 'kort' => 'Halfweg (outlet)',
		'adres' => 'SugarCity Ring 1', 'postcode' => '1165 PB', 'plaats' => 'Halfweg',
		'lat' => '52.386095', 'lng' => '4.739200',
		'status' => 'Opening november 2026',
	];
}

add_action('rest_api_init', function () {
	$can = function () { return current_user_can('manage_options'); };
	register_rest_route('thh/v1', '/winkels/outlet', ['methods' => 'POST', 'permission_callback' => $can, 'callback' => function () {
		$o = thh_outlet_info(); $done = [];

		// 1. Store locator (WP Store Locator)
		$ex = get_posts(['post_type' => 'wpsl_stores', 'post_status' => 'any', 's' => 'Style Outlets', 'numberposts' => 1]);
		if (!$ex) {
			$sid = wp_insert_post(['post_type' => 'wpsl_stores', 'post_status' => 'publish', 'post_title' => 'Amsterdam, The Style Outlets', 'post_content' => $o['status'] . '. Openingstijden volgen.']);
			if ($sid && !is_wp_error($sid)) {
				foreach (['wpsl_address' => $o['adres'], 'wpsl_city' => $o['plaats'], 'wpsl_zip' => $o['postcode'], 'wpsl_country' => 'Nederland', 'wpsl_country_iso' => 'NL', 'wpsl_lat' => $o['lat'], 'wpsl_lng' => $o['lng']] as $k => $v) update_post_meta($sid, $k, $v);
				update_post_meta($sid, '_thh_outlet', 1);
				$done[] = 'wpsl:' . $sid;
			}
		}

		// 2. Loyalty-pagina (49133): vestigingen-grid
		$p = get_post(49133);
		if ($p && strpos($p->post_content, 'Style Outlets') === false) {
			$needle = '<div class="branch-pill coming-soon"><div class="pin">📍</div><h4>Amsterdam IJburg</h4><p>NIEUW</p></div>';
			$ins = '<div class="branch-pill coming-soon"><div class="pin">📍</div><h4>' . $o['naam'] . '</h4><p>' . $o['adres'] . ', ' . $o['plaats'] . ' — ' . $o['status'] . '</p></div>';
			if (strpos($p->post_content, $needle) !== false) {
				add_post_meta(49133, '_thh_outlet_backup_content', $p->post_content, true);
				wp_update_post(['ID' => 49133, 'post_content' => str_replace($needle, $needle . $ins, $p->post_content)]);
				$done[] = 'page:49133';
			}
		}

		// 3. Abonnementen-pagina (49138): locatie-tags
		$p = get_post(49138);
		if ($p && strpos($p->post_content, 'Style Outlets') === false && preg_match('~<div class="location-tag"[^>]*><span class="loc-dot"[^>]*></span> Amsterdam IJburg[^<]*</div>~', $p->post_content, $m)) {
			add_post_meta(49138, '_thh_outlet_backup_content', $p->post_content, true);
			wp_update_post(['ID' => 49138, 'post_content' => str_replace($m[0], $m[0] . '<div class="location-tag"><span class="loc-dot"></span> ' . $o['naam'] . ' — binnenkort</div>', $p->post_content)]);
			$done[] = 'page:49138';
		}

		// 4. Elementor-template "Locaties afhalen" (36245): blok IJburg klonen
		$raw = (string) get_post_meta(36245, '_elementor_data', true);
		if ($raw && strpos($raw, 'Style Outlets') === false) {
			$data = json_decode($raw, true);
			$col =& $data[0]['elements'][1]['elements'];
			$found = null;
			foreach ($col as $i => $el) if (($el['elType'] ?? '') === 'widget' && ($el['widgetType'] ?? '') === 'heading' && strpos($el['settings']['title'] ?? '', 'IJburg') !== false) $found = $i;
			if ($found !== null && isset($col[$found + 1])) {
				$newid = function (&$el) use (&$newid) { $el['id'] = substr(md5(uniqid('', true)), 0, 7); if (!empty($el['elements'])) foreach ($el['elements'] as &$c) $newid($c); };
				$div = null; foreach ($col as $el) if (($el['widgetType'] ?? '') === 'divider') { $div = $el; break; }
				$head = $col[$found]; $sec = $col[$found + 1];
				$head['settings']['title'] = $o['naam'];
				$sec['elements'][0]['elements'][0]['settings']['description_text'] = $o['adres'] . '<br>' . $o['postcode'] . ' ' . $o['plaats'] . '<br><strong>' . $o['status'] . '</strong>';
				$newid($head); $newid($sec); $add = [];
				if ($div) { $newid($div); $add[] = $div; }
				$add[] = $head; $add[] = $sec;
				array_splice($col, $found + 2, 0, $add);
				add_post_meta(36245, '_thh_outlet_backup_elementor', wp_slash($raw), true);
				update_post_meta(36245, '_elementor_data', wp_slash(wp_json_encode($data)));
				if (class_exists('\Elementor\Plugin')) \Elementor\Plugin::$instance->files_manager->clear_cache();
				$done[] = 'elementor:36245';
			}
		}

		global $kinsta_cache;
		if (isset($kinsta_cache->kinsta_cache_purge) && method_exists($kinsta_cache->kinsta_cache_purge, 'purge_complete_caches')) $kinsta_cache->kinsta_cache_purge->purge_complete_caches();
		return ['done' => $done];
	}]);
	register_rest_route('thh/v1', '/winkels/outlet-revert', ['methods' => 'POST', 'permission_callback' => $can, 'callback' => function () {
		$done = [];
		foreach ([49133, 49138] as $id) { $b = get_post_meta($id, '_thh_outlet_backup_content', true); if ($b) { wp_update_post(['ID' => $id, 'post_content' => $b]); $done[] = 'page:' . $id; } }
		$b = get_post_meta(36245, '_thh_outlet_backup_elementor', true);
		if ($b) { update_post_meta(36245, '_elementor_data', wp_slash($b)); if (class_exists('\Elementor\Plugin')) \Elementor\Plugin::$instance->files_manager->clear_cache(); $done[] = 'elementor:36245'; }
		foreach (get_posts(['post_type' => 'wpsl_stores', 'post_status' => 'any', 'meta_key' => '_thh_outlet', 'numberposts' => 5]) as $s) { wp_trash_post($s->ID); $done[] = 'wpsl:' . $s->ID; }
		return ['reverted' => $done];
	}]);
});
