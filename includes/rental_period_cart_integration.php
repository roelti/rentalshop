<?php
/** 
 * @package: Rentman
 * 
 * Class for integration of rental periods within the cart
 * One global rental period is set, which accounts for all rentable products in the cart
 * This class controls the session logic
 * Source: http://wisdmlabs.com/blog/add-custom-data-woocommerce-order/
 */

class Rentman_Rental_Period {
	public function __construct()
    {
		add_action('wp_ajax_wdm_add_user_custom_data_options', array($this, 'wdm_add_user_custom_data_options_callback'));
		add_action('wp_ajax_nopriv_wdm_add_user_custom_data_options', array($this, 'wdm_add_user_custom_data_options_callback'));
		add_action('wp_ajax_rentman_get_availability', array($this, 'get_availability'), 10, 4);
		add_action('wp_ajax_nopriv_rentman_get_availability', array($this, 'get_availability'), 10, 4);

		add_filter('woocommerce_get_cart_item_from_session', array($this, 'wdm_get_cart_items_from_session'), 1, 3 );
		add_filter('woocommerce_cart_item_name', array($this, 'cart_item_availability'), 10, 3);

		add_filter('woocommerce_update_cart_action_cart_updated', array($this, 'cart_validator'), 10, 1);
        add_filter('woocommerce_update_cart_validation', array($this, 'cart_validator'), 1, 4 );
	}


	function cart_validator($updated)
    {
        global $rentman;
		$dates = $rentman->get_dates();

        //check dates
        if(!$dates)
            wc_add_notice(__("De ingevoerde huurperiode is niet correct","rentman"),"error");

        if($dates["to_date"] < $dates["from_date"])
            wc_add_notice(__("De ingevoerde huurperiode is niet correct","rentman"),"error");

        //check availability

		return $updated;
	}

    //add date after "update shoppingcart"
	function wdm_add_user_custom_data_options_callback()
    {
		if (isset($_POST) && isset($_POST['user_data']) && is_numeric($_POST['user_data']["from_date"]) && is_numeric($_POST['user_data']["to_date"]))
        {
            session_start();
            $user_custom_data_values =  $_POST['user_data'];
            $_SESSION['rentman_rental_session'] = $user_custom_data_values;
            die();
		}
	}

	function wdm_get_cart_items_from_session($item,$values,$key)
    {
		if (array_key_exists( 'rentman_rental_session_value', $values ) ) {
			$item['rentman_rental_session_value'] = $values['rentman_rental_session_value'];
		}       
		return $item;
	}

	function get_availability()
    {
		global $rentman;
		if (isset($_POST)) {
			$from_date = $_POST["user_data"]["from_date"];
			$to_date = $_POST["user_data"]["to_date"];
			$product_id = $_POST["user_data"]["product_id"];
			$cart_ids = (isset($_POST["user_data"]["cart_ids"]) ? $_POST["user_data"]["cart_ids"] : false);
		}
		if (!is_numeric($from_date) || !is_numeric($to_date) || !(is_numeric($product_id) || is_array($cart_ids)) ) {
			echo "error: invalid input";
			die();
		}
		$result = $rentman->api_get_availability($from_date, $to_date, $product_id, $cart_ids);

		if (! json_last_error() === JSON_ERROR_NONE) {
			echo "JSON decoding error";
		} else {
			header('Content-Type: application/json');
			echo $result;
		}
		die();
	}

    function cart_item_availability($value, $cart_item, $cart_item_key)
    {
        echo $value;

        $options = get_option( 'rentman_settings' );
        if(!$options['rentman_availabilityCheck'])
            return;

        global $rentman;
        $dates = $rentman->get_dates();
        if(!is_array($dates))
            return;

        $product_sku = get_post_meta($cart_item["product_id"], "_sku");
        $product_sku = $product_sku[0];

        $availability = $rentman->api_get_availability($dates["from_date"], $dates["to_date"], $product_sku, false);
        $availability = json_decode($availability);

        $quantity = $cart_item["quantity"];

        if (!isset($availability) || $availability === null) {
            echo '</br><span id='. $product_sku .' quantity="'. $quantity .'" class="error"></span>';
            return;
        }

        echo "<br>";
        echo '<span id="' . $product_sku . '">';
        if ($quantity <= $availability->maxconfirmed) {
            echo '<span class="icon-green">&#9679;</span>'.__('Beschikbaar',"rentman");
        } else if ($quantity <= $availability->maxoption) {
            echo '<span class="icon-orange">&#9679;</span>'.__('Mogelijk beschikbaar',"rentman");
        } else {
            echo '<span class="icon-red">&#9679;</span>'.__('Niet beschikbaar',"rentman");
        }
        echo "</span>";

    }
}
$rentman_rental_period = new Rentman_Rental_Period();