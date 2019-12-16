<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * SST Product.
 *
 * Contains methods for working with products.
 *
 * @author  Simple Sales Tax
 * @package SST
 * @since   5.0
 */
class SST_Product {

	/**
	 * Register action hooks/
	 *
	 * @since 5.0
	 */
	public static function init() {
		add_action( 'woocommerce_product_options_shipping', [ __CLASS__, 'output_origin_select_box' ] );
		add_action( 'woocommerce_product_bulk_edit_start', [ __CLASS__, 'output_bulk_edit_fields' ] );
		add_action( 'woocommerce_product_bulk_edit_save', [ __CLASS__, 'save_bulk_edit_fields' ] );
		add_action( 'woocommerce_product_options_tax', [ __CLASS__, 'display_tic_field' ] );
		add_action( 'woocommerce_product_after_variable_attributes', [ __CLASS__, 'display_tic_field' ], 10, 3 );
		add_action( 'woocommerce_ajax_save_product_variations', [ __CLASS__, 'ajax_save_variation_tics' ] );
		add_action( 'save_post_product', [ __CLASS__, 'save_product_meta' ] );
	}

	/**
	 * Returns an array of origin addresses for a given product. If no origin
	 * addresses have been configured, returns set of default origin addresses.
	 *
	 * @param int $product_id
	 *
	 * @return SST_Origin_Address[]
	 * @since 5.0
	 */
	public static function get_origin_addresses( $product_id ) {
		$raw_addresses = get_post_meta( $product_id, '_wootax_origin_addresses', true );

		if ( ! is_array( $raw_addresses ) || empty( $raw_addresses ) ) {
			return SST_Addresses::get_default_addresses();
		}

		$addresses = SST_Addresses::get_origin_addresses();
		$return    = [];

		foreach ( $raw_addresses as $address_id ) {
			if ( isset( $addresses[ $address_id ] ) ) {
				$return[ $address_id ] = $addresses[ $address_id ];
			}
		}

		return $return;
	}

	/**
	 * Get the TIC for a product.
	 *
	 * @param int $product_id
	 * @param int $variation_id (default: 0)
	 *
	 * @return mixed TIC for product, or null if none set.
	 * @since 5.0
	 */
	public static function get_tic( $product_id, $variation_id = 0 ) {
		$product_tic   = get_post_meta( $product_id, 'wootax_tic', true );
		$variation_tic = ! $variation_id ? false : get_post_meta( $variation_id, 'wootax_tic', true );
		$tic           = $variation_tic ? $variation_tic : $product_tic;

		/* Fall back to default TIC for product category */
		if ( empty( $tic ) ) {
			$categories = get_the_terms( $product_id, 'product_cat' );

			if ( ! is_wp_error( $categories ) && $categories ) {
				foreach ( $categories as $category ) {
					$cat_tic = get_term_meta( $category->term_id, 'tic', true );
					if ( ! empty( $cat_tic ) ) {
						$tic = $cat_tic;
						break;
					}
				}
			}
		}

		/* Let devs adjust TIC */
		$final_tic = apply_filters( 'wootax_product_tic', $tic, $product_id, $variation_id );

		return empty( $final_tic ) ? null : $final_tic;
	}

	/**
	 * Output "Shipping Origin Addresses" select box.
	 *
	 * @since 4.6
	 */
	public static function output_origin_select_box() {
		global $post;

		$addresses = SST_Addresses::get_origin_addresses();

		// Do not display if there is less than 2 origin addresses to select from
		if ( ! is_array( $addresses ) || count( $addresses ) < 2 ) {
			return;
		}

		include __DIR__ . '/admin/views/html-origin-select.php';
	}

	/**
	 * Output bulk edit fields.
	 *
	 * @since 5.0
	 */
	public static function output_bulk_edit_fields() {
		wp_localize_script(
			'simplesalestax.tic-select',
			'ticSelectLocalizeScript',
			[
				'tic_list' => sst_get_tics(),
				'strings'  => [
					'default' => __( 'No Change', 'simplesalestax' ),
				],
			]
		);

		SST()->assets->enqueue( 'script', 'simplesalestax.tic-select' );

		require_once __DIR__ . '/admin/views/html-select-tic-bulk.php';
	}

	/**
	 * Handle bulk TIC updates.
	 *
	 * @param WC_Product $product The product being saved.
	 *
	 * @since 4.2
	 */
	public static function save_bulk_edit_fields( $product ) {
		$tic = sanitize_text_field( $_REQUEST['wootax_tic'] );

		if ( '' !== $tic ) {
			update_post_meta( $product->get_id(), 'wootax_tic', $tic );
		}
	}

	/**
	 * Output TIC select box on "Edit Product" screen.
	 *
	 * @param int     $loop           Loop counter used by WooCommerce when displaying variation attributes (default:
	 *                                null).
	 * @param array   $variation_data Unused parameter (default: null).
	 * @param WP_Post $variation      WP_Post for variation if variation is being displayed (default: null).
	 *
	 * @since 5.0
	 */
	public static function display_tic_field( $loop = null, $variation_data = null, $variation = null ) {
		global $post;

		$is_variation = ! empty( $variation );

		if ( $is_variation ) {
			$product_id = $variation->ID;
		} else {
			$product_id = $post->ID;
		}

		$current_tic = get_post_meta( $product_id, 'wootax_tic', true );

		wp_localize_script(
			'simplesalestax.tic-select',
			'ticSelectLocalizeScript',
			[
				'tic_list' => sst_get_tics(),
				'strings'  => [
					'default' => $is_variation ? __( 'Same as parent', 'simplesalestax' ) : __(
						'Using site default',
						'simplesalestax'
					),
				],
			]
		);

		SST()->assets->enqueue( 'script', 'simplesalestax.tic-select' );

		require __DIR__ . '/admin/views/html-select-tic.php';
	}

	/**
	 * Update variation TICs.
	 *
	 * @since 4.6
	 */
	public static function ajax_save_variation_tics() {
		$variable_post_id = $_POST['variable_post_id'];
		$tic_selections   = $_POST['wootax_tic'];
		$max_loop         = max( array_keys( $_POST['variable_post_id'] ) );

		for ( $i = 0; $i <= $max_loop; $i++ ) {
			if ( ! isset( $variable_post_id[ $i ] ) ) {
				continue;
			}

			$variation_id = $variable_post_id[ $i ];

			if ( isset( $tic_selections[ $variation_id ] ) ) {
				update_post_meta( $variation_id, 'wootax_tic', $tic_selections[ $variation_id ] );
			}
		}
	}

	/**
	 * Save the TIC and origin addresses for a product.
	 *
	 * @param int $product_id The ID of the product being saved.
	 *
	 * @since 4.2
	 */
	public static function save_product_meta( $product_id ) {
		if ( isset( $_REQUEST['_inline_edit'] ) || isset( $_REQUEST['bulk_edit'] ) ) {
			return;
		}

		// Save product origin addresses
		$origins = isset( $_REQUEST['_wootax_origin_addresses'] ) ? $_REQUEST['_wootax_origin_addresses'] : [];
		update_post_meta( $product_id, '_wootax_origin_addresses', $origins );

		// Save product and variation TICs
		$selected_tics = isset( $_REQUEST['wootax_tic'] ) ? $_REQUEST['wootax_tic'] : [];

		foreach ( $selected_tics as $product_id => $tic ) {
			update_post_meta( $product_id, 'wootax_tic', $tic );
		}
	}

}

SST_Product::init();
