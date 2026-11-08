<?php
/**
 * THH — Eigen merk als eerste product op categoriepagina's
 *
 * Staat er in een productcategorie een product van ons eigen merk (The Health House), dan komt het
 * best verkochte daarvan (op voorraad) als eerste product in de lijst, in een opvallend blauw vak in de
 * stijl van de dagdeal op de homepage. Het product komt dan niet nog een keer los in de lijst.
 * Alleen op de eerste pagina van een categorie. Merk: taxonomie product_brand (2176) of brands (142).
 */
if (!defined('ABSPATH')) return;

function thh_em_product_voor_cat($term_id) {
	$ck = 'thh_em_cat_' . (int) $term_id;
	$pid = get_transient($ck);
	if ($pid !== false) return (int) $pid;
	$q = new WP_Query([
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => 'total_sales',
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',
		'tax_query'      => [
			'relation' => 'AND',
			['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => [(int) $term_id], 'include_children' => true],
			['relation' => 'OR',
				['taxonomy' => 'product_brand', 'field' => 'term_id', 'terms' => [2176]],
				['taxonomy' => 'brands', 'field' => 'term_id', 'terms' => [142]],
			],
			['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => ['outofstock', 'exclude-from-catalog'], 'operator' => 'NOT IN'],
		],
	]);
	$pid = $q->posts ? (int) $q->posts[0] : 0;
	set_transient($ck, $pid, HOUR_IN_SECONDS);
	return $pid;
}

function thh_em_huidig() {
	static $pid = null;
	if ($pid !== null) return $pid;
	$pid = 0;
	if (is_admin() || !function_exists('is_product_category') || !is_product_category() || is_paged()) return $pid;
	$t = get_queried_object();
	if ($t && !empty($t->term_id)) $pid = thh_em_product_voor_cat($t->term_id);
	return $pid;
}

// niet dubbel in de lijst
add_action('pre_get_posts', function ($q) {
	if (is_admin() || !$q->is_main_query() || !$q->is_tax('product_cat') || $q->is_paged()) return;
	$t = get_term_by('slug', $q->get('product_cat'), 'product_cat');
	if (!$t) return;
	$pid = thh_em_product_voor_cat($t->term_id);
	if ($pid) $q->set('post__not_in', array_merge((array) $q->get('post__not_in'), [$pid]));
}, 30);

// als eerste item in het productoverzicht
add_filter('woocommerce_product_loop_start', function ($html) {
	static $klaar = false;
	if ($klaar) return $html;
	$pid = thh_em_huidig();
	if (!$pid) return $html;
	$p = wc_get_product($pid);
	if (!$p || !$p->is_visible()) return $html;
	$klaar = true;
	$img = wp_get_attachment_image_url($p->get_image_id(), 'woocommerce_single') ?: wc_placeholder_img_src('woocommerce_single');
	$kort = wp_strip_all_tags($p->get_short_description());
	if (mb_strlen($kort) > 140) $kort = rtrim(mb_substr($kort, 0, 137)) . '…';
	$knop = $p->is_type('simple') && $p->is_purchasable() && $p->is_in_stock()
		? '<a href="' . esc_url($p->add_to_cart_url()) . '" data-quantity="1" data-product_id="' . $pid . '" data-product_sku="' . esc_attr($p->get_sku()) . '" class="thh-em-btn button add_to_cart_button ajax_add_to_cart" rel="nofollow">In winkelwagen</a>'
		: '<a href="' . esc_url($p->get_permalink()) . '" class="thh-em-btn">Bekijk product</a>';
	ob_start(); ?>
<li class="product thh-em">
	<div class="thh-em-in">
		<div class="thh-em-txt">
			<span class="thh-em-pill">Ons eigen merk</span>
			<a class="thh-em-titel" href="<?php echo esc_url($p->get_permalink()); ?>"><?php echo esc_html($p->get_name()); ?></a>
			<?php if ($kort) : ?><p><?php echo esc_html($kort); ?></p><?php endif; ?>
			<div class="thh-em-prijs"><?php echo $p->get_price_html(); ?></div>
			<?php echo $knop; ?>
		</div>
		<a class="thh-em-img" href="<?php echo esc_url($p->get_permalink()); ?>"><img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($p->get_name()); ?>" loading="eager"></a>
	</div>
</li>
<?php
	return $html . ob_get_clean();
}, 20);

add_action('wp_head', function () {
	if (!thh_em_huidig()) return; ?>
<style>
ul.products li.product.thh-em,.elementor ul.products li.product.thh-em,.woocommerce ul.products li.product.thh-em{grid-column:span 2;background:#14204a!important;border-radius:20px;padding:0!important;overflow:hidden;position:relative;border:0!important;box-shadow:none}
.thh-em-in{background:#14204a;border-radius:20px;display:grid;grid-template-columns:minmax(0,1fr) 42%;gap:16px;height:100%;padding:28px}
.thh-em-txt{display:flex;flex-direction:column;gap:12px;justify-content:center;color:#fff;min-width:0}
.thh-em-pill{display:inline-block;background:#e2373f;color:#fff;font-weight:600;font-size:11px;letter-spacing:.12em;text-transform:uppercase;padding:7px 12px;border-radius:999px;width:max-content}
ul.products li.thh-em a.thh-em-titel{color:#fff!important;font-size:22px;font-weight:700;line-height:1.2;text-decoration:none}
.thh-em-txt p{margin:0;font-size:13.5px;line-height:1.5;color:rgba(255,255,255,.75)}
.thh-em-prijs,.thh-em-prijs .amount,.thh-em-prijs ins{color:#fff!important;font-size:26px;font-weight:700;text-decoration:none}
.thh-em-prijs del,.thh-em-prijs del .amount{color:rgba(255,255,255,.55)!important;font-size:16px;font-weight:400}
ul.products li.thh-em a.thh-em-btn{display:inline-flex;align-items:center;justify-content:center;background:#e2373f!important;color:#fff!important;border-radius:999px;min-height:46px;padding:0 26px;font-weight:600;font-size:14px;text-transform:uppercase;width:max-content;max-width:100%;margin:0;border:0}
ul.products li.thh-em a.thh-em-btn:hover{background:#c92c33!important}
ul.products li.thh-em a.added_to_cart{color:#fff!important;font-size:13px}
.thh-em-img{background:#fff;border-radius:16px;display:flex;align-items:center;justify-content:center;padding:14px}
.thh-em-img img{width:100%;height:100%;max-height:300px;object-fit:contain;margin:0!important}
@media(max-width:767px){ul.products li.product.thh-em{grid-column:1/-1}.thh-em-in{grid-template-columns:minmax(0,1fr) 40%;padding:18px;gap:12px}ul.products li.thh-em a.thh-em-titel{font-size:17px}.thh-em-prijs,.thh-em-prijs .amount,.thh-em-prijs ins{font-size:21px}.thh-em-txt p{display:none}ul.products li.thh-em a.thh-em-btn{min-height:40px;padding:0 16px;font-size:12px}}
</style>
<?php });
