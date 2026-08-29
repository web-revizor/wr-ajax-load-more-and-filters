<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WRALM_Woo {

	public static function active() {
		return class_exists( 'WooCommerce' );
	}

	public static function is_product_query( $post_type ) {
		return self::active() && 'product' === $post_type;
	}

	public static function visibility_tax_query() {
		if ( ! function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			return array();
		}
		$ids  = wc_get_product_visibility_term_ids();
		$excl = array();
		if ( ! empty( $ids['exclude-from-catalog'] ) ) {
			$excl[] = (int) $ids['exclude-from-catalog'];
		}
		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) && ! empty( $ids['outofstock'] ) ) {
			$excl[] = (int) $ids['outofstock'];
		}
		if ( ! $excl ) {
			return array();
		}
		return array(
			'taxonomy' => 'product_visibility',
			'field'    => 'term_taxonomy_id',
			'terms'    => $excl,
			'operator' => 'NOT IN',
		);
	}

	/**
	 * Products-per-page from the WooCommerce catalog settings, ignoring any
	 * shortcode / request value. Mirrors what a normal shop loop uses:
	 * the `loop_shop_per_page` filter over columns * rows.
	 */
	public static function per_page() {
		// Prefer WC's own helpers (they honour theme support args); fall back to
		// the raw catalog options, which are available even on admin-ajax where
		// wc-template-functions.php may not be loaded.
		if ( function_exists( 'wc_get_default_products_per_row' )
			&& function_exists( 'wc_get_default_product_rows_per_page' ) ) {
			$default = (int) wc_get_default_products_per_row() * (int) wc_get_default_product_rows_per_page();
		} else {
			$cols    = (int) get_option( 'woocommerce_catalog_columns', 4 );
			$rows    = (int) get_option( 'woocommerce_catalog_rows', 4 );
			$default = $cols * $rows;
		}
		if ( $default < 1 ) {
			$default = 16;
		}
		$per_page = (int) apply_filters( 'loop_shop_per_page', $default );
		return $per_page > 0 ? $per_page : $default;
	}

	public static function orderby_args( $orderby, $order ) {
		switch ( $orderby ) {
			case 'price':
				return array( 'orderby' => 'meta_value_num', 'meta_key' => '_price', 'order' => $order );
			case 'popularity':
				return array( 'orderby' => 'meta_value_num', 'meta_key' => 'total_sales', 'order' => $order );
			case 'rating':
				return array( 'orderby' => 'meta_value_num', 'meta_key' => '_wc_average_rating', 'order' => $order );
			case 'menu_order':
				return array( 'orderby' => 'menu_order title', 'order' => $order );
			case 'title':
			case 'date':
			case 'rand':
			case 'modified':
			case 'comment_count':
				return array( 'orderby' => $orderby, 'order' => $order );
			default:
				return array( 'orderby' => 'date', 'order' => $order );
		}
	}

	public static function setup_loop( WP_Query $q ) {
		if ( function_exists( 'wc_setup_loop' ) ) {
			wc_setup_loop( array(
				'is_shortcode' => true,
				'is_paginated' => true,
				'total'        => (int) $q->found_posts,
				'total_pages'  => (int) $q->max_num_pages,
				'per_page'     => (int) $q->get( 'posts_per_page' ),
				'current_page' => max( 1, (int) $q->get( 'paged' ) ),
			) );
		}
	}

	public static function reset_loop() {
		if ( function_exists( 'wc_reset_loop' ) ) {
			wc_reset_loop();
		}
	}
}
