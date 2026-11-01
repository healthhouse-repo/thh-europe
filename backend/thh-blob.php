<?php
/**
 * THH blob-doorgeefluik (tijdelijk hulpmiddel): grote data vanuit de browser opslaan en elders ophalen.
 * POST /wp-json/thh/v1/blob   {k, data}   (beheerder)  — slaat op als optie thh_blob_<k>
 * GET  /wp-json/thh/v1/blob?k=...&t=<token> (openbaar met token; token staat in optie thh_blob_token)
 */
if (!defined('ABSPATH')) return;
add_action('rest_api_init', function () {
	register_rest_route('thh/v1', '/blob', [
		['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function ($r) {
			// Ook bruikbaar vanaf een andere site (no-cors, text/plain) met het token in de body.
			$p = $r->get_json_params();
			if (!$p) { $p = json_decode((string) $r->get_body(), true) ?: []; }
			$tok = get_option('thh_blob_token');
			$ok = current_user_can('manage_options') || ($tok && isset($p['t']) && hash_equals($tok, (string) $p['t']));
			if (!$ok) return new WP_Error('auth', 'geen toegang', ['status' => 403]);
			$k = preg_replace('/[^a-z0-9_-]/i', '', (string) ($p['k'] ?? $r['k']));
			if (!$k) return new WP_Error('k', 'k ontbreekt', ['status' => 400]);
			$data = $p['data'] ?? $r['data'];
			if (!is_string($data)) $data = wp_json_encode($data);
			if (strlen($data) > 20000000) return new WP_Error('size', 'te groot', ['status' => 413]);
			update_option('thh_blob_' . $k, $data, false);
			$tok = get_option('thh_blob_token');
			if (!$tok) { $tok = wp_generate_password(32, false); update_option('thh_blob_token', $tok, false); }
			return ['ok' => true, 'k' => $k, 'bytes' => strlen($data), 'token' => $tok];
		}],
		['methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => function ($r) {
			$k = preg_replace('/[^a-z0-9_-]/i', '', (string) $r['k']);
			$tok = get_option('thh_blob_token');
			if (!$tok || !hash_equals($tok, (string) $r['t'])) return new WP_Error('auth', 'geen toegang', ['status' => 403]);
			$d = get_option('thh_blob_' . $k);
			if ($d === false) return new WP_Error('nf', 'niet gevonden', ['status' => 404]);
			$resp = new WP_REST_Response(json_decode($d, true) ?? $d);
			$resp->header('Cache-Control', 'no-store');
			return $resp;
		}],
	]);
});
