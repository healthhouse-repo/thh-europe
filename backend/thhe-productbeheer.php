<?php
/**
 * THH Europe — productbeheer & prijzen v1 (aanvulling op snippet 369)        11 okt 2026
 *
 * Productcatalogus van thheurope.com in een eigen tabel (wp_thhe_products), beheerd vanuit
 * thheurope.com/beheer.html → Producten. De site leest de catalogus via /catalog/products.
 * Prijzen en voorraad per variant blijven lopen via WooCommerce-items + wp_thhe_prices (snippet 369),
 * zodat bestellen, Mollie, WooCommerce-orders en Moneybird-facturen ongewijzigd werken.
 *
 * Routes (namespace thhe/v1, alle POST):
 *   publiek   /catalog/products            productlijst voor de site (met versie, zodat de browser kan cachen)
 *   beheer    /admin/products/list         lijst met zoeken/filters
 *             /admin/products/get          één product + varianten met prijs, inkoop, voorraad
 *             /admin/products/save         product opslaan (+ variantprijzen/inkoop/actief)
 *             /admin/products/bulk         bulk upsert (migratie en invoer via code)
 *             /admin/products/delete       product verwijderen (zacht: status "verwijderd")
 *             /admin/products/wc-search    WooCommerce-producten zoeken om te koppelen
 *             /admin/prices/add            WooCommerce-producten aan de prijstabel toevoegen
 *             /admin/prices/cost           inkoopprijzen in bulk (item_id, sku of leveranciers-artikelnummer)
 *
 * Beheerrechten: beheer-token (zoals snippet 369) óf ingelogde WordPress-gebruiker met manage_woocommerce.
 * Inkoop wordt geschreven naar het productveld _thh_inkoopprijs_excl_btw (zelfde veld als de webshop).
 * Snippet 369 blijft ongewijzigd. Uitzetten = deze snippet deactiveren (de tabel blijft bestaan).
 * Bron: github.com/healthhouse-repo/thh-europe → backend/thhe-productbeheer.php
 */
if ( ! defined( 'ABSPATH' ) ) { return; }

if ( ! defined( 'THHP_DB_VER' ) ) { define( 'THHP_DB_VER', '1' ); }

function thhp_ready() {
	return function_exists( 'thhe_prices_build' ) && function_exists( 'thhe_tbl' ) && function_exists( 'thhe_ok' );
}

function thhp_tbl() { global $wpdb; return $wpdb->prefix . 'thhe_products'; }

function thhp_install() {
	if ( THHP_DB_VER === get_option( 'thhp_db_ver' ) ) { return; }
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();
	dbDelta( 'CREATE TABLE ' . thhp_tbl() . " (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 wc_id bigint(20) unsigned NULL,
 slug varchar(200) NOT NULL DEFAULT '',
 brand varchar(190) NOT NULL DEFAULT '',
 name varchar(255) NOT NULL DEFAULT '',
 category varchar(80) NOT NULL DEFAULT '',
 unit varchar(80) NOT NULL DEFAULT '',
 descr text NULL,
 img varchar(600) NOT NULL DEFAULT '',
 gallery longtext NULL,
 flavors longtext NULL,
 flavor_images longtext NULL,
 status varchar(20) NOT NULL DEFAULT 'actief',
 sort int(11) NOT NULL DEFAULT 0,
 created_at datetime NOT NULL,
 updated_at datetime NOT NULL,
 updated_by varchar(190) NOT NULL DEFAULT '',
 PRIMARY KEY  (id),
 UNIQUE KEY slug (slug),
 KEY wc_id (wc_id),
 KEY status (status),
 KEY brand (brand)
) $c;" );
	update_option( 'thhp_db_ver', THHP_DB_VER, false );
}

/* ------------------------------------------------------------------ */
/* Rechten + hulpfuncties                                              */
/* ------------------------------------------------------------------ */

function thhp_perm_admin( $req ) {
	thhp_install();
	if ( is_user_logged_in() && current_user_can( 'manage_woocommerce' ) ) { return true; }
	return function_exists( 'thhe_perm_admin' ) ? thhe_perm_admin( $req ) : new WP_Error( 'thhe_auth', 'Geen beheerder', array( 'status' => 401 ) );
}
function thhp_perm_public() { thhp_install(); return true; }

function thhp_actor( $req ) {
	if ( is_user_logged_in() ) { $u = wp_get_current_user(); return 'wp:' . $u->user_email; }
	return function_exists( 'thhe_actor' ) ? thhe_actor( $req ) : 'beheer';
}

function thhp_flush() {
	delete_option( 'thhp_cat_v' );
	if ( function_exists( 'thhe_catalog_flush' ) ) { thhe_catalog_flush(); }
}

function thhp_json_list( $v ) {
	if ( is_string( $v ) ) { $d = json_decode( $v, true ); $v = is_array( $d ) ? $d : array_filter( array_map( 'trim', explode( "\n", $v ) ) ); }
	$out = array();
	foreach ( (array) $v as $x ) { $x = thhp_txt( $x, 600 ); if ( '' !== $x ) { $out[] = $x; } }
	return array_values( array_unique( $out ) );
}
function thhp_url_list( $v ) {
	if ( is_string( $v ) ) { $d = json_decode( $v, true ); $v = is_array( $d ) ? $d : explode( "\n", $v ); }
	$out = array();
	foreach ( (array) $v as $x ) { $x = thhp_url( $x ); if ( '' !== $x ) { $out[] = $x; } }
	return array_values( array_unique( $out ) );
}
function thhp_json_map( $v ) {
	if ( is_string( $v ) ) { $d = json_decode( $v, true ); $v = is_array( $d ) ? $d : array(); }
	$out = array();
	foreach ( (array) $v as $k => $x ) {
		$k = thhp_txt( $k, 120 ); $x = thhp_url( $x );
		if ( '' !== $k && '' !== $x ) { $out[ $k ] = $x; }
	}
	return $out;
}

function thhp_slugify( $s ) {
	$s = (string) $s;
	// Bestaande, al nette slugs exact bewaren (ook met een streepje aan het eind): links en winkelwagens gebruiken ze.
	if ( preg_match( '/^[a-z0-9][a-z0-9-]*$/', $s ) ) { return substr( $s, 0, 190 ); }
	$s = sanitize_title( remove_accents( $s ) );
	return substr( $s, 0, 190 );
}
function thhp_unique_slug( $slug, $except_id = 0 ) {
	global $wpdb;
	$base = $slug ? $slug : 'product';
	$try  = $base; $n = 2;
	while ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . thhp_tbl() . ' WHERE slug=%s AND id<>%d', $try, (int) $except_id ) ) ) {
		$try = substr( $base, 0, 180 ) . '-' . $n; $n++;
	}
	return $try;
}

function thhp_statuses() { return array( 'actief', 'verborgen', 'concept', 'verwijderd' ); }

/** Tekst voor de site: geen HTML-tekens. URL: geen quotes, spaties of HTML-tekens, geen javascript:. */
function thhp_txt( $s, $max ) {
	return substr( trim( str_replace( array( '<', '>' ), '', wp_strip_all_tags( html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) ) ) ), 0, $max );
}
function thhp_url( $s ) {
	$s = trim( preg_replace( '/[\s"\'<>\x60]/', '', (string) $s ) );
	if ( preg_match( '/^(javascript|data|vbscript):/i', $s ) ) { return ''; }
	return substr( $s, 0, 600 );
}

/** Product (DB-rij) → vorm zoals de site hem gebruikt (zelfde velden als de oude vaste lijst). */
function thhp_public_shape( $r ) {
	$fl = json_decode( (string) $r->flavors, true );
	$ga = json_decode( (string) $r->gallery, true );
	$fi = json_decode( (string) $r->flavor_images, true );
	$o  = array(
		'slug' => $r->slug, 'brand' => $r->brand, 'name' => $r->name, 'category' => $r->category,
		'img' => $r->img, 'flavors' => is_array( $fl ) ? $fl : array(), 'unit' => $r->unit,
		'gallery' => is_array( $ga ) && $ga ? $ga : ( $r->img ? array( $r->img ) : array() ),
		'flavorImages' => is_array( $fi ) && $fi ? $fi : new stdClass(),
	);
	if ( $r->wc_id ) { $o['id'] = (int) $r->wc_id; }
	if ( '' !== trim( (string) $r->descr ) ) { $o['desc'] = (string) $r->descr; }
	return $o;
}

/** Velden uit een verzoek → kolommen. Alleen meegegeven velden worden aangepast. */
function thhp_fields_from( $p ) {
	$f = array();
	if ( array_key_exists( 'wc_id', $p ) ) { $w = (int) $p['wc_id']; $f['wc_id'] = $w > 0 ? $w : null; }
	if ( array_key_exists( 'id', $p ) && ! array_key_exists( 'wc_id', $p ) && is_numeric( $p['id'] ) && ! empty( $p['_site_shape'] ) ) { $f['wc_id'] = (int) $p['id'] ?: null; }
	foreach ( array( 'brand' => 190, 'name' => 255, 'category' => 80, 'unit' => 80 ) as $k => $max ) {
		if ( array_key_exists( $k, $p ) ) { $f[ $k ] = thhp_txt( $p[ $k ], $max ); }
	}
	if ( array_key_exists( 'desc', $p ) ) { $f['descr'] = trim( wp_kses_post( (string) $p['desc'] ) ); }
	if ( array_key_exists( 'descr', $p ) ) { $f['descr'] = trim( wp_kses_post( (string) $p['descr'] ) ); }
	if ( array_key_exists( 'img', $p ) ) { $f['img'] = thhp_url( $p['img'] ); }
	if ( array_key_exists( 'gallery', $p ) ) { $f['gallery'] = wp_json_encode( thhp_url_list( $p['gallery'] ) ); }
	if ( array_key_exists( 'flavors', $p ) ) { $f['flavors'] = wp_json_encode( thhp_json_list( $p['flavors'] ) ); }
	if ( array_key_exists( 'flavorImages', $p ) ) { $f['flavor_images'] = wp_json_encode( (object) thhp_json_map( $p['flavorImages'] ) ); }
	if ( array_key_exists( 'flavor_images', $p ) ) { $f['flavor_images'] = wp_json_encode( (object) thhp_json_map( $p['flavor_images'] ) ); }
	if ( array_key_exists( 'status', $p ) && in_array( (string) $p['status'], thhp_statuses(), true ) ) { $f['status'] = (string) $p['status']; }
	if ( array_key_exists( 'sort', $p ) ) { $f['sort'] = (int) $p['sort']; }
	if ( array_key_exists( 'slug', $p ) && '' !== trim( (string) $p['slug'] ) ) { $f['slug'] = thhp_slugify( $p['slug'] ); }
	return $f;
}

/** Eén product opslaan. $match: 'id' | 'slug' | 'wc_id' | 'new' (altijd nieuw). Geeft [id, 'nieuw'|'bijgewerkt'|'ongewijzigd'] of WP_Error. */
function thhp_upsert( $p, $actor, $match = 'id' ) {
	global $wpdb;
	$t   = thhp_tbl();
	$f   = thhp_fields_from( $p );
	$row = null;
	if ( 'id' === $match && ! empty( $p['pid'] ) ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%d", (int) $p['pid'] ) );
		if ( ! $row ) { return new WP_Error( 'notfound', 'Product niet gevonden' ); }
	} elseif ( 'wc_id' === $match && ! empty( $f['wc_id'] ) ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE wc_id=%d ORDER BY id LIMIT 1", (int) $f['wc_id'] ) );
	} elseif ( 'slug' === $match && ! empty( $f['slug'] ) ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE slug=%s", $f['slug'] ) );
	}
	$now = current_time( 'mysql' );
	if ( ! $row ) {
		if ( empty( $f['name'] ) ) { return new WP_Error( 'name', 'Naam is verplicht' ); }
		$f['slug'] = thhp_unique_slug( ! empty( $f['slug'] ) ? $f['slug'] : thhp_slugify( ( isset( $f['brand'] ) ? $f['brand'] . ' ' : '' ) . $f['name'] ) );
		$f = array_merge( array( 'brand' => '', 'category' => 'Other', 'unit' => '', 'img' => '', 'gallery' => '[]', 'flavors' => '[]', 'flavor_images' => '{}', 'status' => 'actief', 'sort' => 0 ), $f );
		$f['created_at'] = $now; $f['updated_at'] = $now; $f['updated_by'] = substr( $actor, 0, 190 );
		if ( false === $wpdb->insert( $t, $f ) ) { return new WP_Error( 'db', 'Opslaan mislukt: ' . $wpdb->last_error ); }
		return array( (int) $wpdb->insert_id, 'nieuw' );
	}
	if ( isset( $f['slug'] ) ) { $f['slug'] = thhp_unique_slug( $f['slug'], $row->id ); }
	$changed = array();
	foreach ( $f as $k => $v ) {
		$cur = $row->$k;
		if ( ( null === $v && null !== $cur ) || ( null !== $v && (string) $cur !== (string) $v ) ) { $changed[ $k ] = $v; }
	}
	if ( ! $changed ) { return array( (int) $row->id, 'ongewijzigd' ); }
	$changed['updated_at'] = $now; $changed['updated_by'] = substr( $actor, 0, 190 );
	if ( false === $wpdb->update( $t, $changed, array( 'id' => (int) $row->id ) ) ) { return new WP_Error( 'db', 'Opslaan mislukt: ' . $wpdb->last_error ); }
	return array( (int) $row->id, 'bijgewerkt' );
}

/* ------------------------------------------------------------------ */
/* Publieke catalogus voor de site                                     */
/* ------------------------------------------------------------------ */

function thhp_cat_version() {
	$v = get_option( 'thhp_cat_v' );
	if ( $v ) { return $v; }
	global $wpdb;
	$t   = thhp_tbl();
	$sig = $wpdb->get_row( "SELECT COUNT(*) n, MAX(updated_at) m, SUM(id) s FROM $t WHERE status='actief'" );
	$v   = substr( md5( wp_json_encode( $sig ) . THHP_DB_VER ), 0, 12 );
	update_option( 'thhp_cat_v', $v, false );
	return $v;
}

function thhp_r_catalog( $req ) {
	global $wpdb;
	$b = function_exists( 'thhe_body' ) ? thhe_body( $req ) : array();
	$v = thhp_cat_version();
	if ( isset( $b['v'] ) && (string) $b['v'] === $v ) { return thhe_ok( array( 'v' => $v, 'same' => 1 ) ); }
	$key  = 'thhp_cat_' . $v;
	$list = get_transient( $key );
	if ( ! is_array( $list ) ) {
		$rows = $wpdb->get_results( 'SELECT * FROM ' . thhp_tbl() . " WHERE status='actief' ORDER BY sort, brand, name" );
		$list = array();
		foreach ( $rows as $r ) { $list[] = thhp_public_shape( $r ); }
		set_transient( $key, $list, DAY_IN_SECONDS );
	}
	return thhe_ok( array( 'v' => $v, 'products' => $list ) );
}

/* ------------------------------------------------------------------ */
/* Beheer: lijst, ophalen, opslaan                                     */
/* ------------------------------------------------------------------ */

function thhp_r_list( $req ) {
	global $wpdb;
	$b    = thhe_body( $req );
	$t    = thhp_tbl();
	$pt   = thhe_tbl( 'prices' );
	$per  = max( 10, min( 200, (int) ( isset( $b['per'] ) ? $b['per'] : 50 ) ) );
	$page = max( 1, (int) ( isset( $b['page'] ) ? $b['page'] : 1 ) );
	$w    = array( '1=1' ); $a = array();
	$q    = trim( (string) ( isset( $b['q'] ) ? $b['q'] : '' ) );
	if ( '' !== $q ) {
		$l   = '%' . $wpdb->esc_like( $q ) . '%';
		$w[] = '(p.name LIKE %s OR p.brand LIKE %s OR p.slug LIKE %s OR p.wc_id=%d OR p.id=%d)';
		array_push( $a, $l, $l, $l, (int) $q, (int) $q );
	}
	foreach ( array( 'brand', 'category' ) as $k ) {
		if ( ! empty( $b[ $k ] ) ) { $w[] = "p.$k=%s"; $a[] = (string) $b[ $k ]; }
	}
	$status = isset( $b['status'] ) ? (string) $b['status'] : '';
	if ( in_array( $status, thhp_statuses(), true ) ) { $w[] = 'p.status=%s'; $a[] = $status; } else { $w[] = "p.status<>'verwijderd'"; }
	$filter = isset( $b['filter'] ) ? (string) $b['filter'] : '';
	if ( 'zonder_prijs' === $filter ) { $w[] = '(pr.n IS NULL OR pr.n_active=0)'; }
	if ( 'zonder_koppeling' === $filter ) { $w[] = 'p.wc_id IS NULL'; }
	if ( 'inkoop_geschat' === $filter ) { $w[] = 'pr.n_est>0'; }
	if ( 'signaal' === $filter ) { $w[] = 'pr.n_flag>0'; }
	$join  = "LEFT JOIN (SELECT product_id, COUNT(*) n, SUM(active) n_active, MIN(CASE WHEN active=1 THEN price END) pmin, MAX(CASE WHEN active=1 THEN price END) pmax,
		SUM(cost_src='schatting') n_est, SUM(flag<>'') n_flag FROM $pt GROUP BY product_id) pr ON pr.product_id=p.wc_id";
	$where = implode( ' AND ', $w );
	$sql   = "SELECT SQL_CALC_FOUND_ROWS p.id, p.wc_id, p.slug, p.brand, p.name, p.category, p.unit, p.img, p.status, p.updated_at,
		pr.n, pr.n_active, pr.pmin, pr.pmax, pr.n_est, pr.n_flag FROM $t p $join WHERE $where ORDER BY p.sort, p.brand, p.name LIMIT %d OFFSET %d";
	$a[]   = $per; $a[] = ( $page - 1 ) * $per;
	$rows  = $wpdb->get_results( $wpdb->prepare( $sql, $a ) );
	$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );
	$out   = array();
	foreach ( $rows as $r ) {
		$out[] = array(
			'pid' => (int) $r->id, 'wc_id' => $r->wc_id ? (int) $r->wc_id : null, 'slug' => $r->slug, 'brand' => $r->brand, 'name' => $r->name,
			'category' => $r->category, 'unit' => $r->unit, 'img' => $r->img, 'status' => $r->status, 'updated_at' => $r->updated_at,
			'variants' => (int) $r->n, 'active' => (int) $r->n_active, 'pmin' => null === $r->pmin ? null : (float) $r->pmin,
			'pmax' => null === $r->pmax ? null : (float) $r->pmax, 'est' => (int) $r->n_est, 'flags' => (int) $r->n_flag,
		);
	}
	$facets = array(
		'brands'     => $wpdb->get_col( "SELECT brand FROM $t WHERE status<>'verwijderd' AND brand<>'' GROUP BY brand ORDER BY brand" ),
		'categories' => $wpdb->get_col( "SELECT category FROM $t WHERE status<>'verwijderd' AND category<>'' GROUP BY category ORDER BY category" ),
		'counts'     => $wpdb->get_results( "SELECT status, COUNT(*) n FROM $t GROUP BY status", ARRAY_A ),
	);
	return thhe_ok( array( 'rows' => $out, 'total' => $total, 'page' => $page, 'per' => $per, 'facets' => $facets ) );
}

/** Varianten (WooCommerce-items) van een gekoppeld product, met prijsregel, inkoop en voorraad. */
function thhp_variants( $wc_id ) {
	global $wpdb;
	if ( ! $wc_id ) { return array(); }
	$rows = $wpdb->get_results( $wpdb->prepare( thhe_prices_sql( ' AND p.product_id=%d' ) . ' ORDER BY p.item_id', (int) $wc_id ) );
	$out  = array(); $have = array();
	foreach ( $rows as $r ) {
		$o = thhe_price_row_out( $r, true );
		$it = wc_get_product( (int) $r->item_id );
		$o['stock_qty'] = ( $it && $it->managing_stock() ) ? $it->get_stock_quantity() : null;
		$own = get_post_meta( (int) $r->item_id, '_thh_inkoopprijs_excl_btw', true );
		$o['cost_own'] = ( '' === $own || null === $own ) ? null : (float) str_replace( ',', '.', (string) $own );
		$o['in_prices'] = 1;
		$out[] = $o; $have[ (int) $r->item_id ] = 1;
	}
	foreach ( thhe_items_of( (int) $wc_id ) as $it ) {
		if ( isset( $have[ $it->get_id() ] ) ) { continue; }
		$rule = thhe_price_rule( $it );
		$out[] = array(
			'item_id' => $it->get_id(), 'product_id' => (int) $wc_id, 'label' => thhe_item_label( $it ), 'sku' => (string) $it->get_sku(),
			'price' => $rule ? $rule['price'] : null, 'cost' => $rule ? $rule['cost'] : null, 'cost_src' => $rule ? $rule['cost_src'] : '',
			'retail' => $rule ? $rule['retail'] : null, 'floor' => $rule ? $rule['floor'] : null, 'flag' => $rule ? $rule['flag'] : '',
			'vat' => $rule ? (float) $rule['vat'] : null, 'retail_ex' => $rule ? thhe_r2( $rule['retail'] / ( 1 + $rule['vat'] / 100 ) ) : null,
			'margin' => ( $rule && $rule['price'] > 0 && null !== $rule['cost'] ) ? round( ( $rule['price'] - $rule['cost'] ) / $rule['price'] * 100, 1 ) : null,
			'active' => 0, 'source' => '', 'stock' => $it->get_stock_status(), 'stock_qty' => $it->managing_stock() ? $it->get_stock_quantity() : null,
			'cost_own' => null, 'in_prices' => 0,
		);
	}
	return $out;
}

function thhp_r_get( $req ) {
	global $wpdb;
	$b   = thhe_body( $req );
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . thhp_tbl() . ' WHERE id=%d', (int) ( isset( $b['pid'] ) ? $b['pid'] : 0 ) ) );
	if ( ! $row ) { return thhe_err( 'notfound', 'Product niet gevonden', 404 ); }
	$p = thhp_public_shape( $row );
	$p['pid'] = (int) $row->id; $p['wc_id'] = $row->wc_id ? (int) $row->wc_id : null; $p['status'] = $row->status; $p['sort'] = (int) $row->sort;
	$p['descr'] = (string) $row->descr; $p['updated_at'] = $row->updated_at; $p['updated_by'] = $row->updated_by;
	$wc = $row->wc_id ? wc_get_product( (int) $row->wc_id ) : null;
	$p['wc'] = $wc ? array( 'name' => html_entity_decode( $wc->get_name(), ENT_QUOTES, 'UTF-8' ), 'status' => get_post_status( $wc->get_id() ), 'type' => $wc->get_type(), 'edit' => admin_url( 'post.php?post=' . $wc->get_id() . '&action=edit' ) ) : null;
	return thhe_ok( array( 'product' => $p, 'variants' => thhp_variants( (int) $row->wc_id ) ) );
}

/** Variantwijzigingen: inkoop (productveld) → herberekenen → prijs/actief/regel. */
function thhp_apply_variants( $wc_id, $vars, $actor ) {
	global $wpdb;
	$res = array(); $rebuild = false;
	$pt  = thhe_tbl( 'prices' );
	foreach ( (array) $vars as $v ) {
		$item = isset( $v['item_id'] ) ? (int) $v['item_id'] : 0;
		$it   = $item ? wc_get_product( $item ) : null;
		if ( ! $it ) { continue; }
		$parent = $it->get_parent_id() ? (int) $it->get_parent_id() : $item;
		if ( (int) $parent !== (int) $wc_id ) { $res[ $item ] = 'hoort niet bij dit product'; continue; }
		if ( array_key_exists( 'cost', $v ) && '' !== (string) $v['cost'] && null !== $v['cost'] ) {
			$c = thhe_num( $v['cost'] );
			if ( $c > 0 && $c < 10000 ) { update_post_meta( $item, '_thh_inkoopprijs_excl_btw', number_format( $c, 2, '.', '' ) ); $rebuild = true; $res[ $item ] = 'inkoop'; }
		}
		if ( ! empty( $v['add'] ) && ! $wpdb->get_var( $wpdb->prepare( "SELECT item_id FROM $pt WHERE item_id=%d", $item ) ) ) { $rebuild = true; }
	}
	if ( $rebuild ) { thhe_prices_build( array( (int) $wc_id ), false, $actor ); }
	foreach ( (array) $vars as $v ) {
		$item = isset( $v['item_id'] ) ? (int) $v['item_id'] : 0;
		if ( ! $item ) { continue; }
		if ( ! empty( $v['reset'] ) ) {
			$wpdb->update( $pt, array( 'source' => 'regel' ), array( 'item_id' => $item ) );
			thhe_prices_build( array( (int) $wc_id ), false, $actor );
			$res[ $item ] = 'regel';
			continue;
		}
		$price  = ( array_key_exists( 'price', $v ) && '' !== (string) $v['price'] && null !== $v['price'] ) ? thhe_num( $v['price'] ) : null;
		$active = array_key_exists( 'active', $v ) ? (int) $v['active'] : null;
		if ( null !== $price || null !== $active ) { $res[ $item ] = thhe_price_set( $item, $price, $active, $actor ); }
	}
	thhp_flush();
	return $res;
}

function thhp_r_save( $req ) {
	$b = thhe_body( $req );
	$p = isset( $b['product'] ) && is_array( $b['product'] ) ? $b['product'] : array();
	$actor = thhp_actor( $req );
	$r = thhp_upsert( $p, $actor, ! empty( $p['pid'] ) ? 'id' : 'new' );
	if ( is_wp_error( $r ) ) { return thhe_err( $r->get_error_code(), $r->get_error_message(), 400 ); }
	list( $id, $state ) = $r;
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . thhp_tbl() . ' WHERE id=%d', $id ) );
	$vres = array();
	if ( $row && $row->wc_id && ! empty( $b['variants'] ) ) { $vres = thhp_apply_variants( (int) $row->wc_id, $b['variants'], $actor ); }
	if ( $row && $row->wc_id && ! empty( $b['add_prices'] ) ) { thhe_prices_build( array( (int) $row->wc_id ), false, $actor ); }
	thhp_flush();
	if ( function_exists( 'thhe_log' ) ) { thhe_log( 'product_' . $state, (string) $id, $row ? $row->name : '', 0, $actor ); }
	return thhe_ok( array( 'pid' => $id, 'state' => $state, 'variants' => $vres ) );
}

function thhp_r_delete( $req ) {
	global $wpdb;
	$b  = thhe_body( $req );
	$id = (int) ( isset( $b['pid'] ) ? $b['pid'] : 0 );
	if ( ! $id ) { return thhe_err( 'missing', 'Geen product', 400 ); }
	$wpdb->update( thhp_tbl(), array( 'status' => 'verwijderd', 'updated_at' => current_time( 'mysql' ), 'updated_by' => substr( thhp_actor( $req ), 0, 190 ) ), array( 'id' => $id ) );
	thhp_flush();
	return thhe_ok( array( 'pid' => $id, 'status' => 'verwijderd' ) );
}

/** Bulk: tot 500 producten per verzoek. match = slug | wc_id. dry = alleen tellen. */
function thhp_r_bulk( $req ) {
	@set_time_limit( 300 );
	$b     = thhe_body( $req );
	$list  = isset( $b['products'] ) && is_array( $b['products'] ) ? $b['products'] : array();
	$match = isset( $b['match'] ) && in_array( $b['match'], array( 'slug', 'wc_id' ), true ) ? $b['match'] : 'slug';
	$site  = ! empty( $b['site_shape'] );
	if ( ! $list ) { return thhe_err( 'empty', 'Geen producten', 400 ); }
	if ( count( $list ) > 500 ) { return thhe_err( 'too_many', 'Maximaal 500 per verzoek', 400 ); }
	$actor = thhp_actor( $req );
	$st = array( 'nieuw' => 0, 'bijgewerkt' => 0, 'ongewijzigd' => 0, 'fout' => 0 ); $err = array();
	foreach ( $list as $i => $p ) {
		if ( ! is_array( $p ) ) { continue; }
		if ( $site ) { $p['_site_shape'] = 1; }
		$r = thhp_upsert( $p, $actor, $match );
		if ( is_wp_error( $r ) ) { $st['fout']++; $err[] = ( isset( $p['slug'] ) ? $p['slug'] : '#' . $i ) . ': ' . $r->get_error_message(); continue; }
		$st[ $r[1] ]++;
	}
	thhp_flush();
	if ( function_exists( 'thhe_log' ) ) { thhe_log( 'producten_bulk', '', $st, 0, $actor ); }
	return thhe_ok( array( 'stats' => $st, 'errors' => array_slice( $err, 0, 50 ) ) );
}

/* ------------------------------------------------------------------ */
/* WooCommerce koppelen, prijstabel, inkoop in bulk                    */
/* ------------------------------------------------------------------ */

function thhp_brand_of( $pid ) {
	$b = wp_get_post_terms( $pid, 'product_brand', array( 'fields' => 'names' ) );
	return ( ! is_wp_error( $b ) && $b ) ? html_entity_decode( (string) $b[0], ENT_QUOTES, 'UTF-8' ) : '';
}

function thhp_r_wc_search( $req ) {
	global $wpdb;
	$b = thhe_body( $req );
	$q = trim( (string) ( isset( $b['q'] ) ? $b['q'] : '' ) );
	if ( strlen( $q ) < 2 ) { return thhe_err( 'short', 'Typ minimaal 2 tekens', 400 ); }
	$ids = array();
	if ( ctype_digit( $q ) ) { $ids[] = (int) $q; }
	$like = '%' . $wpdb->esc_like( $q ) . '%';
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status IN ('publish','private') AND post_title LIKE %s ORDER BY post_title LIMIT 60", $like ) ) as $id ) { $ids[] = (int) $id; }
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_parent, p.post_type FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE m.meta_key='_sku' AND m.meta_value LIKE %s AND p.post_type IN ('product','product_variation') LIMIT 60", $like ) ) as $r ) {
		$ids[] = ( 'product_variation' === $r->post_type ) ? (int) $r->post_parent : (int) $r->ID;
	}
	$t = thhp_tbl(); $pt = thhe_tbl( 'prices' ); $out = array();
	foreach ( array_values( array_unique( array_filter( $ids ) ) ) as $pid ) {
		if ( count( $out ) >= 40 ) { break; }
		$p = wc_get_product( $pid );
		if ( ! $p || $p->is_type( 'variation' ) ) { continue; }
		$img = wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' );
		$out[] = array(
			'wc_id' => (int) $pid, 'name' => html_entity_decode( $p->get_name(), ENT_QUOTES, 'UTF-8' ), 'brand' => thhp_brand_of( $pid ),
			'sku' => (string) $p->get_sku(), 'type' => $p->get_type(), 'status' => get_post_status( $pid ), 'img' => $img ? $img : '',
			'items' => count( thhe_items_of( $pid ) ),
			'in_prices' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $pt WHERE product_id=%d", $pid ) ),
			'linked_to' => $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE wc_id=%d AND status<>'verwijderd' LIMIT 1", $pid ) ),
		);
	}
	return thhe_ok( array( 'results' => $out ) );
}

function thhp_r_prices_add( $req ) {
	$b   = thhe_body( $req );
	$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) ( isset( $b['ids'] ) ? $b['ids'] : array() ) ) ) ) );
	if ( ! $ids ) { return thhe_err( 'missing', 'Geen producten gekozen', 400 ); }
	if ( count( $ids ) > 400 ) { return thhe_err( 'too_many', 'Maximaal 400 per verzoek', 400 ); }
	$ok = array();
	foreach ( $ids as $pid ) { if ( 'product' === get_post_type( $pid ) && in_array( get_post_status( $pid ), array( 'publish', 'private' ), true ) ) { $ok[] = $pid; } }
	if ( ! $ok ) { return thhe_err( 'none', 'Geen WooCommerce-producten gevonden', 400 ); }
	$stats = thhe_prices_build( $ok, false, thhp_actor( $req ) );
	thhp_flush();
	return thhe_ok( array( 'added' => count( $ok ), 'stats' => $stats ) );
}

/** Items bij een leveranciers-artikelnummer: SKU's die eindigen op "-<art>" (zoals THHX-0004-9229 voor XXL 9229). */
function thhp_items_by_art( $art ) {
	global $wpdb;
	$art = preg_replace( '/[^A-Za-z0-9_.]/', '', (string) $art );
	if ( '' === $art ) { return array(); }
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
		"SELECT m.post_id FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id
		 WHERE m.meta_key='_sku' AND ( m.meta_value LIKE %s OR m.meta_value=%s ) AND p.post_type IN ('product','product_variation') AND p.post_status IN ('publish','private') LIMIT 50",
		'%-' . $wpdb->esc_like( $art ), $art
	) ) );
}

function thhp_r_cost( $req ) {
	global $wpdb;
	@set_time_limit( 300 );
	$b    = thhe_body( $req );
	$dry  = ! empty( $b['dry'] );
	$rows = (array) ( isset( $b['rows'] ) ? $b['rows'] : array() );
	if ( ! $rows ) { return thhe_err( 'empty', 'Geen regels', 400 ); }
	if ( count( $rows ) > 5000 ) { return thhe_err( 'too_many', 'Maximaal 5.000 regels per keer', 400 ); }
	$pt = thhe_tbl( 'prices' ); $res = array(); $rebuild = array(); $seen = array();
	$st = array( 'gewijzigd' => 0, 'ongewijzigd' => 0, 'onbekend' => 0, 'ongeldig' => 0, 'buiten_prijstabel' => 0 );
	foreach ( $rows as $r ) {
		if ( ! is_array( $r ) ) { continue; }
		$raw = isset( $r['cost'] ) ? trim( (string) $r['cost'] ) : '';
		if ( '' === $raw ) { continue; }
		$items = array(); $ref = '';
		if ( ! empty( $r['item_id'] ) ) { $items = array( (int) $r['item_id'] ); $ref = '#' . (int) $r['item_id']; }
		elseif ( isset( $r['sku'] ) && '' !== trim( (string) $r['sku'] ) ) { $ref = trim( (string) $r['sku'] ); $id = (int) wc_get_product_id_by_sku( $ref ); if ( $id ) { $items = array( $id ); } }
		elseif ( isset( $r['art'] ) && '' !== trim( (string) $r['art'] ) ) { $ref = 'art ' . trim( (string) $r['art'] ); $items = thhp_items_by_art( $r['art'] ); }
		if ( ! $items ) { $st['onbekend']++; $res[] = array( 'ref' => $ref, 'status' => 'onbekend' ); continue; }
		$cost = thhe_num( $raw );
		if ( ! ( $cost > 0 ) || $cost > 10000 ) { $st['ongeldig']++; $res[] = array( 'ref' => $ref, 'status' => 'ongeldig', 'waarde' => $raw ); continue; }
		foreach ( $items as $item ) {
			if ( isset( $seen[ $item ] ) ) { continue; }
			$seen[ $item ] = 1;
			$p = wc_get_product( $item );
			if ( ! $p ) { $st['onbekend']++; $res[] = array( 'ref' => $ref, 'status' => 'onbekend' ); continue; }
			$pid  = $p->get_parent_id() ? (int) $p->get_parent_id() : (int) $item;
			$own  = get_post_meta( $item, '_thh_inkoopprijs_excl_btw', true );
			$ownf = ( '' === $own || null === $own ) ? null : (float) str_replace( ',', '.', (string) $own );
			$tbl  = $wpdb->get_var( $wpdb->prepare( "SELECT cost FROM $pt WHERE item_id=%d", $item ) );
			if ( null !== $tbl ) { $eff = (float) $tbl; } else { list( $c ) = thhe_cost_for( $p ); $eff = ( null === $c ) ? null : (float) $c; }
			// Ongewijzigd: gelijk aan het eigen inkoopveld, of (zonder eigen veld) gelijk aan de inkoop die nu gebruikt
			// wordt. Zo schrijft een terug-geïmporteerde export geen geschatte inkoop weg als echte inkoop.
			if ( ( null !== $ownf && abs( $ownf - $cost ) < 0.005 ) || ( null === $ownf && null !== $eff && abs( $eff - $cost ) < 0.005 ) ) { $st['ongewijzigd']++; continue; }
			$in = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $pt WHERE product_id=%d", $pid ) );
			$st['gewijzigd']++;
			if ( ! $in ) { $st['buiten_prijstabel']++; }
			$res[] = array( 'ref' => $ref, 'item_id' => (int) $item, 'name' => html_entity_decode( $p->get_name(), ENT_QUOTES, 'UTF-8' ), 'sku' => (string) $p->get_sku(), 'oud' => $eff, 'nieuw' => thhe_r2( $cost ), 'status' => $in ? 'gewijzigd' : 'gewijzigd, niet in prijstabel' );
			if ( ! $dry ) {
				update_post_meta( $item, '_thh_inkoopprijs_excl_btw', number_format( thhe_r2( $cost ), 2, '.', '' ) );
				if ( $in ) { $rebuild[ $pid ] = 1; }
			}
		}
	}
	$stats = null;
	if ( ! $dry && $rebuild ) { $stats = thhe_prices_build( array_keys( $rebuild ), false, thhp_actor( $req ) ); }
	if ( ! $dry && $st['gewijzigd'] ) { thhp_flush(); if ( function_exists( 'thhe_log' ) ) { thhe_log( 'inkoop_bijgewerkt', '', $st, 0, thhp_actor( $req ) ); } }
	return thhe_ok( array( 'dry' => $dry ? 1 : 0, 'stats' => $st, 'rebuild' => $stats, 'rows' => array_slice( $res, 0, 1000 ) ) );
}

/* ------------------------------------------------------------------ */
/* Routes                                                              */
/* ------------------------------------------------------------------ */

add_action( 'rest_api_init', function () {
	if ( ! thhp_ready() ) { return; }
	$ns = 'thhe/v1';
	$r  = function ( $path, $cb, $perm ) use ( $ns ) {
		register_rest_route( $ns, $path, array( 'methods' => 'POST', 'callback' => $cb, 'permission_callback' => $perm ) );
	};
	$r( '/catalog/products', 'thhp_r_catalog', 'thhp_perm_public' );
	$r( '/admin/products/list', 'thhp_r_list', 'thhp_perm_admin' );
	$r( '/admin/products/get', 'thhp_r_get', 'thhp_perm_admin' );
	$r( '/admin/products/save', 'thhp_r_save', 'thhp_perm_admin' );
	$r( '/admin/products/bulk', 'thhp_r_bulk', 'thhp_perm_admin' );
	$r( '/admin/products/delete', 'thhp_r_delete', 'thhp_perm_admin' );
	$r( '/admin/products/wc-search', 'thhp_r_wc_search', 'thhp_perm_admin' );
	$r( '/admin/prices/add', 'thhp_r_prices_add', 'thhp_perm_admin' );
	$r( '/admin/prices/cost', 'thhp_r_cost', 'thhp_perm_admin' );
}, 20 );
