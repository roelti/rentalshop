<?php
/**
 * Class for integration of the Rentman Rentable Product type into the admin menu.
 *
 *
 */
class Rentman_Product_Rentable_Admin {
	public function __construct() {
            add_filter( 'product_type_selector' , array( $this, 'rentman_product_type'));
        }

    public function rentman_product_type( $types ) {
        wp_enqueue_script(
			'admin_add_product',
			plugins_url('js/admin_add_product.js', dirname(dirname(__FILE__)) . ""),
			array( 'jquery' )
		);
        $types[ 'rentable' ] = __( 'Rentable Product', 'rentman');
        return $types;
   }
}

new Rentman_Product_Rentable_Admin;