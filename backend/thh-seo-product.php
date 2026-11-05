<?php
/**
 * THH SEO productpagina's (4 nov 2026)
 * 1. Unieke meta-omschrijving per product: staat er in Yoast geen eigen omschrijving (of alleen het
 *    standaardsjabloon "Behaal jouw sportdoelen ..."), dan maken we er een uit de productgegevens
 *    (wat het is, merk, kerninfo, aantal smaken/varianten, prijs). Ook voor og:/twitter-omschrijving.
 *    Een handmatig ingevulde Yoast-omschrijving gaat altijd voor.
 * 2. Het WooCommerce-veld "GTIN, UPC, EAN of ISBN" heet in de beheeromgeving "EAN-code (streepjescode)".
 *    De code komt in het product-schema (snippet 161) als gtin8/12/13/14.
 */

if (!function_exists('thh_seo_meta_gen')) {
	function thh_seo_meta_gen($p) {
		if (!$p) return '';
		static $c = [];
		$id = $p->get_id();
		$lang = function_exists('thh_i18n_lang') ? thh_i18n_lang() : 'nl';
		if (!in_array($lang, ['de', 'en'], true)) $lang = 'nl';
		if (isset($c[$id . $lang])) return $c[$id . $lang];
		$schoon = function ($s) { return trim(preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags((string) $s), ENT_QUOTES, 'UTF-8'))); };
		$naam = $schoon($p->get_name());

		// merk
		$merk = '';
		$t = get_the_terms($id, 'product_brand');
		if ($t && !is_wp_error($t)) $merk = $schoon($t[0]->name);

		// soort product (primaire categorie)
		$cat = '';
		$prim = (int) get_post_meta($id, '_yoast_wpseo_primary_product_cat', true);
		$term = $prim ? get_term($prim, 'product_cat') : null;
		if (!$term || is_wp_error($term)) { $ts = get_the_terms($id, 'product_cat'); if ($ts && !is_wp_error($ts)) $term = $ts[0]; }
		if ($term && !is_wp_error($term) && !in_array($term->slug, ['nieuw', 'best-verkocht', 'week-deals', 'bundels', 'in-store-deals'], true)) $cat = mb_strtolower($schoon($term->name));

		// kerninfo: eerste zin uit de korte of lange omschrijving die niet alleen de naam herhaalt
		$kern = '';
		$lang_desc = $p->get_description();
		$bronnen = str_word_count(wp_strip_all_tags($lang_desc)) > 150 ? [$lang_desc, $p->get_short_description()] : [$p->get_short_description(), $lang_desc];
		// anderstalig: neem de vertaalde eerste alinea van de omschrijving
		if ($lang !== 'nl' && function_exists('thh_i18n_dict')) {
			$bronnen = [];
			[$dict] = thh_i18n_dict($lang);
			if (preg_match_all('~<p[^>]*>(.*?)</p>~is', $lang_desc, $mm)) {
				foreach (array_slice($mm[1], 0, 3) as $par) {
					foreach (preg_split('~</?strong>~i', $par) as $stuk) {
						$k = md5(thh_i18n_norm(wp_strip_all_tags($stuk)));
						if (isset($dict[$k])) $bronnen[] = $dict[$k];
					}
				}
			}
		}
		foreach ($bronnen as $bron) {
			$tekst = $schoon($bron);
			if ($tekst === '') continue;
			$kort = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $naam));
			$tekst = preg_replace('/^(' . preg_quote($naam, '/') . '|' . preg_quote($kort, '/') . ')\s*[:\-–—]?\s*/iu', '', $tekst);
			foreach (preg_split('/(?<=[.!?])\s+/u', $tekst) as $zin) {
				$zin = trim($zin);
				$zin = preg_replace('/^(is|zijn|ist|sind|are|is a|is an)\s+/u', '', $zin, 1, $hadWerkw);
				if ($hadWerkw) $zin = preg_replace('/^(een|ein|eine|an|a)\s+/iu', '', $zin);
				if ($hadWerkw) $zin = mb_strtoupper(mb_substr($zin, 0, 1)) . mb_substr($zin, 1);
				if (mb_strlen($zin) < 25 || mb_strlen($zin) > 120) continue;
				if (preg_match('/kopen doe je bij|in unseren geschäften|in our stores|the health house|behaal jouw|bestel(len)? (je )?(nu|vandaag)|gratis verzending|\bonze\b|\bbekijk\b|klik|hieronder|praktische keuze|inmiddels|beschikbaar in varianten|actieve routine|^(als|ben|wil|zoek|op zoek|heb|wist)\b/iu', $zin)) continue;
				if (preg_match('/^[a-z]/u', $zin)) continue;
				if (mb_stripos($zin, $naam) === 0) continue;
				$kern = rtrim($zin, '.!? ');
				break 2;
			}
		}

		// varianten
		$extra = '';
		if ($p->is_type('variable')) {
			$attrs = $p->get_variation_attributes();
			foreach ($attrs as $k => $vals) {
				$n = count(array_filter((array) $vals));
				if ($n < 2) continue;
				$lab = mb_strtolower(wc_attribute_label($k, $p));
				$w = ['nl' => ['smaken', 'kleuren', 'maten', 'varianten'], 'de' => ['Geschmacksrichtungen', 'Farben', 'Größen', 'Varianten'], 'en' => ['flavours', 'colours', 'sizes', 'variants']][$lang];
				$in = $lang === 'en' ? 'Available in ' : ($lang === 'de' ? 'Erhältlich in ' : 'In ');
				if (preg_match('/smaak|flavou?r/i', $lab)) { $extra = $in . $n . ' ' . $w[0]; break; }
				if (preg_match('/kleur|colou?r/i', $lab)) { $extra = $in . $n . ' ' . $w[1]; break; }
				if (preg_match('/maat|size/i', $lab)) { $extra = $in . $n . ' ' . $w[2]; break; }
				if (!$extra) $extra = $in . $n . ' ' . $w[3];
			}
		}

		// prijs
		$prijs = $p->is_type('variable') ? (float) $p->get_variation_price('min', true) : (float) wc_get_price_to_display($p);
		$vanaf = $p->is_type('variable') && (float) $p->get_variation_price('max', true) > $prijs;
		$wrd = ['nl' => ['vanaf ', 'voor ', ' kopen', ' van ', 'Gratis verzending vanaf €60 of ophalen in onze winkels.'], 'de' => ['ab ', 'für ', ' kaufen', ' von ', 'Kostenloser Versand ab 60 € oder Abholung in unseren Geschäften.'], 'en' => ['from ', 'for ', '', ' by ', 'Free shipping from €60 or collect in our stores.']][$lang];
		$bedrag = $lang === 'en' ? '€' . number_format($prijs, 2, '.', ',') : ($lang === 'de' ? number_format($prijs, 2, ',', '.') . ' €' : '€' . number_format($prijs, 2, ',', '.'));
		$prijstekst = $prijs > 0 ? ($vanaf ? $wrd[0] : $wrd[1]) . $bedrag : '';

		$delen = [];
		$kop = $naam;
		if ($merk && mb_stripos($naam, $merk) === false) $kop .= $wrd[3] . $merk;
		$delen[] = ($lang === 'en' ? 'Buy ' : '') . $kop . $wrd[2] . ($prijstekst ? ' ' . $prijstekst : '') . '.';
		if ($kern) $delen[] = $kern . '.';
		if ($extra) $delen[] = $extra . '.';
		$delen[] = $wrd[4];

		$uit = '';
		foreach ($delen as $d) {
			$probeer = trim($uit . ' ' . $d);
			if (mb_strlen($probeer) <= 158) { $uit = $probeer; continue; }
			if ($uit === '') $uit = rtrim(mb_substr($d, 0, 155), ' ,.;:') . '…';
		}
		return $c[$id . $lang] = $uit;
	}
}

if (!function_exists('thh_seo_meta_nodig')) {
	function thh_seo_meta_nodig($huidig) {
		if (!function_exists('is_product') || !is_product()) return false;
		$eigen = trim((string) get_post_meta(get_queried_object_id(), '_yoast_wpseo_metadesc', true));
		if ($eigen === '' || stripos($eigen, 'Behaal jouw sportdoelen') !== false) return true;
		return false;
	}
}

foreach (['wpseo_metadesc', 'wpseo_opengraph_desc', 'wpseo_twitter_description'] as $thh_f) {
	add_filter($thh_f, function ($desc) {
		if (!thh_seo_meta_nodig($desc)) return $desc;
		$g = thh_seo_meta_gen(wc_get_product(get_queried_object_id()));
		return $g !== '' ? $g : $desc;
	}, 20);
}

// EAN-label in de beheeromgeving
add_filter('gettext', function ($t, $text, $domain) {
	if ($domain === 'woocommerce' && is_admin() && in_array($text, ['GTIN, UPC, EAN, or ISBN', 'GTIN, UPC, EAN or ISBN'], true)) return 'EAN-code (streepjescode)';
	return $t;
}, 20, 3);
