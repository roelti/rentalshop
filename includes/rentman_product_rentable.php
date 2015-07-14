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

    /*
	public function add_to_cart_text() {
		return apply_filters('woocommerce_product_add_to_cart_text', __('Choose rental period', 'woocommerce'), $this);
	}
    */

	public function add_to_cart_url() {
		$url = true ? remove_query_arg( 'added-to-cart', add_query_arg( 'add-to-cart', $this->id ) ) : get_permalink( $this->id );

		return apply_filters( 'woocommerce_product_add_to_cart_url', $url, $this );
	}

    public function needs_shipping()
    {
        return false;
    }
}