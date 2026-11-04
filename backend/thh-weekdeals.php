<?php
/**
 * THH Weekdeals-pagina (/weekdeals/, pagina 91310) — automatisch uit het dagdeal-rooster (snippet #437).
 * Shortcode: [thh_weekdeals]. De opmaak (CSS) staat in de pagina zelf (klassen wd-*).
 *
 * - Staat er een weekend met 'WEEK' in thh_dd_rooster() (nu of binnenkort), dan toont de pagina die
 *   weekdeals (thh_dd_week()) met de echte datums en een afteller.
 * - Is er geen weekdeal-weekend gepland, dan toont de pagina alle dagdeals van deze week
 *   (maandag t/m zondag), met per deal de dag en of hij vandaag, voorbij of nog komend is.
 */

if (!function_exists('thh_wd_img')) {
	function thh_wd_img($pid) {
		$p = get_page_by_path('dagdeal-cutout-' . (int) $pid, OBJECT, 'attachment');
		if ($p) { $u = wp_get_attachment_image_url($p->ID, 'large'); if ($u) return $u; }
		$prod = wc_get_product($pid);
		if ($prod && $prod->get_image_id()) return wp_get_attachment_image_url($prod->get_image_id(), 'woocommerce_single');
		return function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src() : '';
	}
}

add_shortcode('thh_weekdeals', function () {
	if (!function_exists('thh_dd_rooster') || !function_exists('wc_get_product')) return '';
	$tz = wp_timezone();
	$nu = new DateTime('now', $tz);
	$vandaag = $nu->format('Y-m-d');
	$r = thh_dd_rooster();
	ksort($r);
	$fmt = function ($d, $f) use ($tz) { return date_i18n($f, (new DateTime($d . ' 12:00', $tz))->getTimestamp()); };
	$eur = function ($v) { return '€' . number_format((float) $v, 2, ',', '.'); };

	// 1. eerstvolgend (of lopend) weekdeal-blok zoeken
	$blok = null; $cur = null;
	foreach ($r as $d => $v) {
		if ($v !== 'WEEK') { if ($cur) { if ($cur[1] >= $vandaag) { $blok = $cur; break; } $cur = null; } continue; }
		if ($cur && (new DateTime($cur[1], $tz))->modify('+1 day')->format('Y-m-d') === $d) $cur[1] = $d; else { if ($cur && $cur[1] >= $vandaag) { $blok = $cur; break; } $cur = [$d, $d]; }
	}
	if (!$blok && $cur && $cur[1] >= $vandaag) $blok = $cur;

	$kaarten = []; // [pid, titel, prijs, van, tag, status]
	if ($blok) {
		$modus = 'week';
		foreach (thh_dd_week() as $it) $kaarten[] = [$it, 'Weekdeal', 'actief'];
		$start = new DateTime($blok[0] . ' 00:00', $tz); $eind = new DateTime($blok[1] . ' 23:59:59', $tz);
		$label = 'Weekdeals · ' . ($blok[0] === $blok[1] ? $fmt($blok[0], 'l j F') : $fmt($blok[0], 'D j') . ' t/m ' . $fmt($blok[1], 'D j F'));
		$titel = 'Alle dagdeals<br><span>nog één keer</span>';
		$sub = 'Alleen ' . ($blok[0] === $blok[1] ? 'deze dag' : 'dit weekend') . ', zolang de voorraad strekt';
		$h2 = 'De weekdeals';
		$intro = 'Een dagdeal gemist deze week? ' . ($blok[0] === $blok[1] ? 'Vandaag' : 'Dit weekend') . ' krijg je ze allemaal nog één keer, online en in al onze winkels.';
	} else {
		$modus = 'dag';
		$ma = (clone $nu)->modify('monday this week'); $zo = (clone $ma)->modify('+6 days');
		for ($i = 0; $i < 7; $i++) {
			$d = (clone $ma)->modify("+$i days")->format('Y-m-d');
			if (empty($r[$d]) || !is_array($r[$d])) continue;
			$st = $d < $vandaag ? 'voorbij' : ($d === $vandaag ? 'vandaag' : 'komt');
			$tag = $st === 'vandaag' ? 'Dagdeal · vandaag' : ($st === 'voorbij' ? 'Was dagdeal op ' . $fmt($d, 'l') : 'Dagdeal op ' . $fmt($d, 'l j F'));
			$kaarten[] = [$r[$d], $tag, $st];
		}
		$start = null; $eind = new DateTime($vandaag . ' 23:59:59', $tz);
		$label = 'Dagdeals · ' . $fmt($ma->format('Y-m-d'), 'j') . ' t/m ' . $fmt($zo->format('Y-m-d'), 'j F');
		$titel = 'Alle dagdeals<br><span>van deze week</span>';
		$sub = 'Elke dag een nieuwe deal, alleen op die dag';
		$h2 = 'De dagdeals van deze week';
		$intro = 'Elke dag een andere topdeal, online en in al onze winkels. Hieronder zie je wat er deze week langskomt.';
	}

	$min = null; $html = '';
	foreach ($kaarten as $k) {
		[$it, $tag, $st] = $k;
		$ids = (array) $it[0]; $prijs = (float) $it[1]; $van = (float) $it[2]; $naam = (string) $it[3];
		$p = wc_get_product((int) $ids[0]); if (!$p) continue;
		$pid = $p->get_parent_id() ?: $p->get_id();
		$parent = wc_get_product($pid); if (!$parent) continue;
		if ($st !== 'voorbij' && ($min === null || $prijs < $min)) $min = $prijs;
		$off = ($van > 0 && $prijs < $van) ? round((1 - $prijs / $van) * 100) : 0;
		$merk = ''; $t = get_the_terms($pid, 'product_brand'); if ($t && !is_wp_error($t)) $merk = $t[0]->name;
		$link = add_query_arg(['utm_source' => 'site', 'utm_medium' => 'weekdealpagina', 'utm_campaign' => $modus === 'week' ? 'weekdeals' : 'dagdeals-week'], get_permalink($pid));
		$html .= '<a class="wd-card wd-st-' . esc_attr($st) . '" href="' . esc_url($link) . '"><div class="wd-img"><img src="' . esc_url(thh_wd_img($pid)) . '" alt="' . esc_attr($naam) . '" loading="lazy">'
			. ($off ? '<span class="wd-off">-' . $off . '%</span>' : '')
			. '<span class="wd-pr"><small>' . ($modus === 'week' ? 'Weekdeal' : 'Dagdeal') . '</small><b>' . esc_html($eur($prijs)) . '</b></span></div>'
			. '<div class="wd-tag">' . esc_html($tag) . '</div><h3>' . esc_html($naam) . '</h3>'
			. ($merk ? '<p class="wd-s">' . esc_html($merk) . '</p>' : '')
			. '<p class="wd-p">' . ($off ? '<s>' . esc_html($eur($van)) . '</s>' : '') . '<strong>' . esc_html($eur($prijs)) . '</strong></p>'
			. '<span class="wd-btn">' . ($st === 'voorbij' ? 'Bekijk product' : 'Bekijk deal') . '</span></a>';
	}
	if ($html === '') $html = '<p class="wd-intro">Er staan op dit moment geen deals gepland. Kijk morgen weer, of bekijk <a href="' . esc_url(home_url('/deals/')) . '">al onze aanbiedingen</a>.</p>';

	ob_start(); ?>
<div class="wd"><section class="wd-hero"><div class="wd-hi"><div>
	<span class="wd-lab"><?php echo esc_html($label); ?></span>
	<h1><?php echo wp_kses($titel, ['br' => [], 'span' => []]); ?></h1>
	<p class="wd-sub"><?php echo esc_html($sub); ?></p>
	<div class="wd-cd"><div class="wd-cdl" id="wdcdl"></div><div id="wdcd" style="display:flex;gap:10px"></div></div>
	<a class="wd-cta" href="#wd-deals">Bekijk alle deals</a>
</div><div class="wd-art"><img src="https://thh-outlet.vercel.app/outlet/assets/weekdeals/hero-weekdeals.png?v=6" alt="Deals van The Health House"><?php if ($min !== null) : ?><div class="wd-badge"><span>vanaf</span><strong><?php echo esc_html(str_replace(',00', '', $eur($min))); ?></strong></div><?php endif; ?></div></div></section>
<section class="wd-body" id="wd-deals"><h2 class="wd-h2"><?php echo esc_html($h2); ?></h2><p class="wd-intro"><?php echo esc_html($intro); ?></p><div class="wd-grid"><?php echo $html; ?></div></section></div>
<style>.wd-card.wd-st-voorbij{opacity:.55}.wd-card.wd-st-voorbij .wd-pr{background:#8a93ab}.wd-card.wd-st-vandaag{box-shadow:0 0 0 3px #D4364A}</style>
<script>(function(){var s=<?php echo $start ? (int) $start->getTimestamp() * 1000 : 0; ?>,e=<?php echo (int) $eind->getTimestamp() * 1000 + 1000; ?>,mod=<?php echo wp_json_encode($modus); ?>;var l=document.getElementById('wdcdl'),b=document.getElementById('wdcd');if(!l||!b)return;function p(n){return(n<10?'0':'')+n}function t(){var n=Date.now(),x,lab;if(s&&n<s){x=s-n;lab='De weekdeals starten over'}else if(n<e){x=e-n;lab=mod==='week'?'De weekdeals stoppen over':'De dagdeal van vandaag stopt over'}else{l.textContent=mod==='week'?'De weekdeals zijn voorbij':'';b.style.display='none';return}l.textContent=lab;var d=Math.floor(x/864e5),h=Math.floor(x%864e5/36e5),m=Math.floor(x%36e5/6e4),sc=Math.floor(x%6e4/1e3);var a=[[h,'uur'],[m,'min'],[sc,'sec']];if(d>0)a.unshift([d,'dagen']);b.innerHTML=a.map(function(v){return'<div class="wd-cdb"><b>'+p(v[0])+'</b><small>'+v[1]+'</small></div>'}).join('')}t();setInterval(t,1000)})();</script>
<?php
	return ob_get_clean();
});
