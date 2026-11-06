<?php
/**
 * THH Dagdeal-uitsnedes — maakt automatisch een scherpe, transparante productfoto (zonder witte randen)
 * voor elke dagdeal in het rooster (vandaag + komende 14 dagen). Opslag als media "dagdeal-cutout-{product-id}".
 * Homepage (#450) en /dagdeal/ (#443) gebruiken die uitsnede automatisch.
 * Draait via WP-cron (elk uur) en is handmatig te starten: POST /wp-json/thh/v1/dagdeal/uitsnedes (admin).
 */

if (!function_exists('thh_ddu_maak')) {
	/** Uitsnede maken voor één product (parent-id). Geeft attachment-id of WP_Error. */
	function thh_ddu_maak($pid, $forceer = false, $opt = []) {
		$pid = (int) $pid;
		$bestaand = get_page_by_path('dagdeal-cutout-' . $pid, OBJECT, 'attachment');
		if ($bestaand && !$forceer) return (int) $bestaand->ID;
		if (!function_exists('imagecreatefromstring')) return new WP_Error('gd', 'GD ontbreekt');
		$p = function_exists('wc_get_product') ? wc_get_product($pid) : null;
		if (!$p || !$p->get_image_id()) return new WP_Error('img', 'Geen productfoto');
		$file = get_attached_file($p->get_image_id());
		if (!$file || !file_exists($file)) return new WP_Error('file', 'Bestand niet gevonden');
		$src = @imagecreatefromstring(file_get_contents($file));
		if (!$src) return new WP_Error('read', 'Foto niet leesbaar');
		$w = imagesx($src); $h = imagesy($src);
		$max = 1400;
		if (max($w, $h) > $max) { $s = $max / max($w, $h); $nw = (int) round($w * $s); $nh = (int) round($h * $s); $t = imagecreatetruecolor($nw, $nh); imagealphablending($t, false); imagesavealpha($t, true); imagecopyresampled($t, $src, 0, 0, 0, 0, $nw, $nh, $w, $h); imagedestroy($src); $src = $t; $w = $nw; $h = $nh; }
		if (!imageistruecolor($src)) imagepalettetotruecolor($src);
		$n = $w * $h;
		$R = new SplFixedArray($n); $G = new SplFixedArray($n); $B = new SplFixedArray($n); $A = new SplFixedArray($n);
		for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) { $c = imagecolorat($src, $x, $y); $i = $y * $w + $x; $R[$i] = ($c >> 16) & 255; $G[$i] = ($c >> 8) & 255; $B[$i] = $c & 255; $A[$i] = 255 - (int) round((($c >> 24) & 127) * 255 / 127); }
		imagedestroy($src);
		// achtergrondkleur = mediaan van de rand
		$rs = $gs = $bs = [];
		for ($x = 0; $x < $w; $x += 5) { foreach ([$x, ($h - 1) * $w + $x] as $i) { $rs[] = $R[$i]; $gs[] = $G[$i]; $bs[] = $B[$i]; } }
		for ($y = 0; $y < $h; $y += 5) { foreach ([$y * $w, $y * $w + $w - 1] as $i) { $rs[] = $R[$i]; $gs[] = $G[$i]; $bs[] = $B[$i]; } }
		sort($rs); sort($gs); sort($bs); $m = count($rs) >> 1; $bg = [$rs[$m], $gs[$m], $bs[$m]];
		$dist = function ($i) use ($R, $G, $B, $bg) { return max(abs($R[$i] - $bg[0]), abs($G[$i] - $bg[1]), abs($B[$i] - $bg[2])); };
		$T1 = (int) ($opt['t1'] ?? 7); $T2 = (int) ($opt['t2'] ?? 110); $BAND = (int) ($opt['band'] ?? 3);
		// flood fill vanaf de rand
		$isbg = new SplFixedArray($n); $st = [];
		for ($x = 0; $x < $w; $x++) { $st[] = $x; $st[] = ($h - 1) * $w + $x; }
		for ($y = 0; $y < $h; $y++) { $st[] = $y * $w; $st[] = $y * $w + $w - 1; }
		while ($st) { $i = array_pop($st); if ($isbg[$i]) continue; if ($A[$i] > 20 && $dist($i) > $T1) continue; $isbg[$i] = 1; $x = $i % $w;
			if ($x > 0) $st[] = $i - 1; if ($x < $w - 1) $st[] = $i + 1; if ($i >= $w) $st[] = $i - $w; if ($i < $n - $w) $st[] = $i + $w; }
		// rand van 4 px: kleur-naar-alfa (haalt witte/grijze randjes weg)
		$near = new SplFixedArray($n); $front = [];
		for ($i = 0; $i < $n; $i++) if ($isbg[$i]) $front[] = $i;
		for ($r = 1; $r <= $BAND; $r++) { $nf = []; foreach ($front as $i) { $x = $i % $w; foreach ([$x > 0 ? $i - 1 : -1, $x < $w - 1 ? $i + 1 : -1, $i >= $w ? $i - $w : -1, $i < $n - $w ? $i + $w : -1] as $q) { if ($q >= 0 && !$isbg[$q] && !$near[$q]) { $near[$q] = $r; $nf[] = $q; } } } $front = $nf; }
		$x0 = $w; $y0 = $h; $x1 = 0; $y1 = 0;
		for ($i = 0; $i < $n; $i++) {
			if ($isbg[$i]) { $A[$i] = 0; continue; }
			if ($near[$i]) { $a = min(1, $dist($i) / $T2); if ($a < 0.06) { $A[$i] = 0; continue; }
				$R[$i] = max(0, min(255, (int) round($bg[0] + ($R[$i] - $bg[0]) / $a))); $G[$i] = max(0, min(255, (int) round($bg[1] + ($G[$i] - $bg[1]) / $a))); $B[$i] = max(0, min(255, (int) round($bg[2] + ($B[$i] - $bg[2]) / $a))); $A[$i] = (int) round(255 * $a); }
			if ($A[$i] >= 10) { $x = $i % $w; $y = intdiv($i, $w); if ($x < $x0) $x0 = $x; if ($x > $x1) $x1 = $x; if ($y < $y0) $y0 = $y; if ($y > $y1) $y1 = $y; }
		}
		if ($x1 <= $x0 || $y1 <= $y0) return new WP_Error('leeg', 'Niets overgebleven na uitsnijden');
		// uitsnede strak om het product: losse spikkels en schaduwresten tellen niet mee voor de rand,
		// anders staat het product scheef in zijn vlak. Rij/kolom telt pas mee met voldoende dekkende pixels.
		$cols = array_fill(0, $w, 0); $rows = array_fill(0, $h, 0);
		for ($i = 0; $i < $n; $i++) if ($A[$i] >= 128) { $cols[$i % $w]++; $rows[intdiv($i, $w)]++; }
		$mc = max(3, (int) round($h * 0.01)); $mr = max(3, (int) round($w * 0.01));
		$fx0 = $x0; $fx1 = $x1; $fy0 = $y0; $fy1 = $y1;
		for ($x = $x0; $x <= $x1; $x++) if ($cols[$x] >= $mc) { $fx0 = $x; break; }
		for ($x = $x1; $x >= $x0; $x--) if ($cols[$x] >= $mc) { $fx1 = $x; break; }
		for ($y = $y0; $y <= $y1; $y++) if ($rows[$y] >= $mr) { $fy0 = $y; break; }
		for ($y = $y1; $y >= $y0; $y--) if ($rows[$y] >= $mr) { $fy1 = $y; break; }
		if ($fx1 > $fx0 && $fy1 > $fy0) { $x0 = $fx0; $x1 = $fx1; $y0 = $fy0; $y1 = $fy1; }
		$pad = 4; $cx0 = max(0, $x0 - $pad); $cy0 = max(0, $y0 - $pad); $cw = min($w, $x1 + $pad + 1) - $cx0; $ch = min($h, $y1 + $pad + 1) - $cy0;
		$out = imagecreatetruecolor($cw, $ch); imagealphablending($out, false); imagesavealpha($out, true);
		for ($y = 0; $y < $ch; $y++) for ($x = 0; $x < $cw; $x++) { $i = ($y + $cy0) * $w + ($x + $cx0); imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $R[$i], $G[$i], $B[$i], 127 - (int) round($A[$i] * 127 / 255))); }
		$up = wp_upload_dir(); $name = wp_unique_filename($up['path'], 'dagdeal-cutout-' . $pid . '.png'); $path = trailingslashit($up['path']) . $name;
		imagepng($out, $path, 6); imagedestroy($out);
		if ($bestaand) { wp_update_post(['ID' => $bestaand->ID, 'post_name' => 'dagdeal-cutout-' . $pid . '-oud-' . time()]); }
		$att = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'dagdeal-cutout-' . $pid, 'post_name' => 'dagdeal-cutout-' . $pid, 'post_status' => 'inherit'], $path);
		if (is_wp_error($att)) return $att;
		update_post_meta($att, '_wp_attachment_image_alt', $p->get_name());
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $path));
		delete_transient('thh_ddcut4_' . $pid);
		return (int) $att;
	}

	/** Producten (parent-ids) uit het rooster voor vandaag t/m +$dagen. */
	function thh_ddu_rooster_pids($dagen = 14) {
		if (!function_exists('thh_dd_rooster')) return [];
		$r = thh_dd_rooster(); $pids = [];
		$t = new DateTime('now', new DateTimeZone('Europe/Amsterdam'));
		for ($i = 0; $i <= $dagen; $i++) {
			$k = $t->format('Y-m-d'); $t->modify('+1 day');
			if (empty($r[$k]) || !is_array($r[$k])) continue;
			$ids = (array) $r[$k][0]; $f = (int) reset($ids); $par = (int) wp_get_post_parent_id($f);
			$pids[$par ?: $f] = true;
		}
		// weekdeals: op een WEEK-dag staat er geen product in het rooster maar een lijst; die deals meenemen
		if (function_exists('thh_dd_vandaag')) {
			list(, $nu) = thh_dd_vandaag();
			foreach ((array) $nu as $d) {
				$ids = (array) ($d[0] ?? []); $f = (int) reset($ids); if (!$f) continue;
				$par = (int) wp_get_post_parent_id($f);
				$pids[$par ?: $f] = true;
			}
		}
		return array_keys($pids);
	}

	function thh_ddu_run($dagen = 14, $max = 3) {
		$res = []; $gedaan = 0;
		foreach (thh_ddu_rooster_pids($dagen) as $pid) {
			if (get_page_by_path('dagdeal-cutout-' . $pid, OBJECT, 'attachment')) { $res[$pid] = 'bestaat'; continue; }
			if ($gedaan >= $max) { $res[$pid] = 'later'; continue; }
			@set_time_limit(120);
			$a = thh_ddu_maak($pid); $gedaan++;
			$res[$pid] = is_wp_error($a) ? 'fout: ' . $a->get_error_message() : 'gemaakt ' . $a;
		}
		update_option('thh_ddu_laatste', ['tijd' => current_time('mysql'), 'resultaat' => $res], false);
		return $res;
	}
}

add_action('init', function () { if (!wp_next_scheduled('thh_ddu_cron')) wp_schedule_event(time() + 120, 'hourly', 'thh_ddu_cron'); });
add_action('thh_ddu_cron', function () { thh_ddu_run(14, 3); });

add_action('rest_api_init', function () {
	register_rest_route('thh/v1', '/dagdeal/uitsnedes', [
		'methods' => 'POST',
		'permission_callback' => function () { return current_user_can('manage_woocommerce'); },
		'callback' => function ($req) {
			$pid = (int) $req->get_param('product');
			if ($pid) { $opt = array_filter(['t1' => $req->get_param('t1'), 't2' => $req->get_param('t2'), 'band' => $req->get_param('band')], function ($v) { return $v !== null; }); $a = thh_ddu_maak($pid, (bool) $req->get_param('opnieuw'), $opt); return is_wp_error($a) ? new WP_Error('ddu', $a->get_error_message(), ['status' => 400]) : ['ok' => true, 'attachment' => $a, 'url' => wp_get_attachment_url($a)]; }
			return ['ok' => true, 'resultaat' => thh_ddu_run((int) ($req->get_param('dagen') ?: 14), (int) ($req->get_param('max') ?: 3))];
		},
	]);
});
