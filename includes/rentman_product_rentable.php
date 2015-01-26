<?php
if (!defined('ABSPATH')) exit;

if (! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    exit;
}

include_once(plugin_dir_path(realpath(__DIR__ . '/../../woocommerce/woocommerce.php')) . 'woocommerce.php');

/**
 * Rentable product
 * 
 * For use in the Rentman WooCommerce extension
 *
 * All products that are imported from Rentman are of this type.
 **/

class WC_Product_Rentable extends WC_Product {

	public function __construct( $product ) {
		$this->product_type = 'rentable';
		parent::__construct($product);
		//$this->add_templates();
	}

	public function add_to_cart_text() {
		return apply_filters('woocommerce_product_add_to_cart_text', __('Choose rental period', 'woocommerce'), $this);
	}

	public function add_to_cart_url() {
		$url = true ? remove_query_arg( 'added-to-cart', add_query_arg( 'add-to-cart', $this->id ) ) : get_permalink( $this->id );

		return apply_filters( 'woocommerce_product_add_to_cart_url', $url, $this );
	}

	public function is_purchasable() {
		return true;
	}

	/**
	 * Returns the product's active price. Staffel is applied here
	 *
	 * @return string price
	 */
	public function get_price() {
		global $rentman;
		// Product list taking forever fix
		// Also, checkout page somehow being admin fix
		// This makes the admin panel more intuitive (no staffel applied) and improves performance
		if ( !is_ajax() && is_admin()) {
			return apply_filters( 'woocommerce_get_price', parent::get_price(), $this );
		} else {
			$price = $rentman->apply_staffel($this->price);
			return apply_filters( 'woocommerce_get_price', $price, $this );
		}
	}

	public function get_total_stock() {
		return apply_filters('woocommerce_stock_amount', $this->total_stock);
	}


}