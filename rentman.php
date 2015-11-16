<?php
/**
 * Plugin Name: Rentman
 * Plugin URI: http://www.rentman.nl
 * Description: Integrates Rentman rental software into WooCommerce
 * Version: 0.0.1
 * Author: 
 * Author URI: 
 * Text Domain: rentman
*/
class Rentman {
    function __construct() {
        if(session_id() == '') {
            session_start();
        }

        $this->dbname = 'rentman_customers';

        $this->api_username = "";;
        $this->api_sslkey = "";;
        $this->base_url = "";

        $options = get_option( 'rentman_settings' );
        if($options)
        {
            $this->api_username = $options['rentman_account_name'];
            $this->api_sslkey = $options['rentman_password'];
            $this->base_url = 'https://'. $options['rentman_account_name'] . '.rentman.nl';
        }

        register_activation_hook(__FILE__, array($this, 'add_defaults'));
        register_deactivation_hook(__FILE__, array($this, 'remove_defaults'));

        // Add product type files
        include_once('includes/admin/rentman_product_rentable_admin.php');
        include_once('includes/rentman_product_rentable.php');
        // Add cart session integration
        include_once('includes/rental_period_cart_integration.php');
        // Add RESTclient
        include_once('includes/restclient.php');
        // Add JSON import
        include_once('includes/json_product_import.php');
        // Add Option field object
        include_once('includes/options.php');
        // Add date fields for Checkout
        include_once('includes/checkout_fields.php');

        add_action('plugins_loaded', array($this,'translatePlugin'));

        add_action('wp_enqueue_scripts', array($this, 'script_init'));
        // Register admin menu
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'init' ));

        add_filter( 'woocommerce_locate_template', array($this, 'rentman_woocommerce_locate_template'), 10, 3 );
        add_filter( 'comments_open', array( $this, 'disable_comments') );
        add_action( 'woocommerce_single_product_summary', array($this, 'add_to_cart_template'), 30 );
        add_action( 'woocommerce_single_product_summary', array($this, 'show_attachments'), 10, 2);

        add_action( 'woocommerce_cart_collaterals', array($this, 'cart_date_picker') );

        add_action( 'woocommerce_checkout_update_order_meta', array($this, 'send_user_details') );

        add_action( 'woocommerce_cart_calculate_fees', array($this, 'multiplyDailyFee') );
        add_filter( 'gettext', array($this,'my_text_strings'), 20, 3 );
    }

    function script_init() {
        wp_enqueue_style(
            'rentman',
            plugins_url('css/rentman.css', __FILE__)
        );
    }

    function translatePlugin()
    {
        load_plugin_textdomain( 'rentman', false, dirname( plugin_basename(__FILE__) ) . '/lang/' );
    }

	function init() {
		global $option_object;

		if( false == get_option( 'rentman_settings' ) ) { 
			add_option( 'rentman_settings' );
		}

		register_setting( 'rentman_login_options', 'rentman_settings', array( $option_object, 'validate' ) );

		add_settings_section(
			'rentman_login_section', 
			__('Log hier in met uw Rentman API gegevens',"rentman"),
			array( $option_object, 'render_login' ), 
			'rentman'
		);

		add_settings_field( 
			'rentman_account_name', 
			__('Accountnaam',"rentman"),
			array( $option_object, 'render_account_name' ), 
			'rentman', 
			'rentman_login_section' 
		);

		add_settings_field( 
			'rentman_password', 
			__('Wachtwoord',"rentman"),
			array( $option_object, 'render_password' ), 
			'rentman', 
			'rentman_login_section' 
		);

        add_settings_field(
            'rentman_availabilityCheck',
            __('Check beschikbaarheid voor versturen',"rentman"),
            array( $option_object, 'render_availabilityCheck' ),
            'rentman',
            'rentman_login_section'
        );

        add_settings_field(
            'rentman_addDiscount',
            __('Korting contact uit Rentman overnemen',"rentman"),
            array( $option_object, 'render_addDiscount' ),
            'rentman',
            'rentman_login_section'
        );

		if ( isset ( $_POST['import-rentman']))
        {
            wp_enqueue_script(
                'admin_add_product',
                plugins_url('js/admin_import.js', __FILE__ ),
                array( 'jquery' )
            );
		}

        if(isset($_GET["import"]) && in_array($_GET["import"],array("import_categories","import_products","import_delete_products","import_cross_sells")))
        {
            set_time_limit(600);
            if ( ! $this->login_credentials_correct() )
            {
                echo '<div id="message" class="error"><p>'.__('Inloggegevens niet correct',"rentman").'</p></div>';
                return false;
            }

            $van = isset($_GET["van"]) ? $_GET["van"] : null;
            $tot = isset($_GET["tot"]) ? $_GET["tot"] : null;

            $products = $this->api_get_products();
            if (is_null($products))
            {
                $error_string = "FATAL: Error parsing Rentman API JSON. Please check login settings and API availability";
                error_log($error_string);
                echo '<div id="message" class="error"><p>' . $error_string . '</p></div>';
                return false;
            }

            $result = $this->$_GET["import"]($products, $van,$tot);

            if ( is_wp_error( $result ) )
                $status = array("status" => "error", "error" => $result->get_error_message());
            else
                $status = array("status" => "ok", "products" => count($products));

            die(json_encode($status));
        }
	}

	function import_categories($products,$van,$tot)
    {
        $categories = $this->api_get_categories();
        if (empty($categories)) {
            return new WP_Error('no categories', __('De categorieën konden niet worden geladen. Controleer in Rentman of er producten zijn waarbij u heeft aangegeven dat ze worden getoond in de webshop.',"rentman"));
        }

        $json_product_import = new JSON_Product_Import();
        $json_product_import->import_categories_safe($categories);
	}

    function import_products($products,$van,$tot)
    {
        $products = array_slice($products,$van,$tot-$van);

        $json_product_import = new JSON_Product_Import();
        $json_product_import->import_products($products);
    }

    function import_delete_products($products,$van,$tot)
    {
        $json_product_import = new JSON_Product_Import();
        $json_product_import->delete_all_products_safe($products);
    }

    function import_cross_sells($products,$van,$tot)
    {
        $products = array_slice($products,$van,$tot-$van);

        $json_product_import = new JSON_Product_Import();
        $cross_sells = $this->api_get_cross_sells($products);
        $json_product_import->add_cross_sells($cross_sells);
    }

	function send_user_details( $order_id )
    {
		$order = new WC_Order($order_id);
		$user = $order->get_user();
		$wp_id = $user->ID;

		$visiting_street = $order->billing_address_1;
		$city = $order->billing_city;
		$postcode = $order->billing_postcode;
		$company = $order->billing_company;
		$first_name = $order->billing_first_name;
		$last_name = $order->billing_last_name;
		$phone = $order->billing_phone;
		$email = $order->billing_email;

		$contact_data = Array(
			'email' => $email,
			'bedrijf' => $company,
			'bezoekstraat' => $visiting_street,
			'bezoekpostcode' => $postcode,
			'bezoekstad' => $city,
            'factuurstraat' => $visiting_street,
            'factuurpostcode' => $postcode,
            'factuurstad' => $city,
            'poststraat' => $visiting_street,
            'postpostcode' => $postcode,
            'poststad' => $city,
			'voornaam' => $first_name,
			'naam' => $last_name,
			'telefoon' => $phone
			);

        if($company == "")
            $contact_data["type"] == "particulier";

		$contact_data_serialized = serialize($contact_data);

		// Get user Rentman id from table
		$db_data = $this->get_rentman_id_from_db( $wp_id );
		$rentman_user_id = -1;

		// User doesn't exist
		if ( ! $db_data->rentman_id || $db_data->rentman_id === NULL ) {

			$user_id = $this->api_post_contact($contact_data);
			$user_id = $user_id[0]->id;

			if ( is_numeric( $user_id ) ) {
				$this->add_rentman_id_to_db( $wp_id, $user_id, $contact_data_serialized );
				$rentman_user_id = $user_id;
			} else {
				logit('userid problem');
				return false;
			}
		// Information has changed
		} else if ( strcmp( $db_data->data, $contact_data_serialized ) !== 0 ) {
			$rentman_user_id = $db_data->rentman_id;
			$contact_data['id'] = $rentman_user_id;
			$user_id = $this->api_post_contact($contact_data);

			$this->update_rentman_customer_data( $rentman_user_id, $contact_data_serialized );
		} else {
			$rentman_user_id = $db_data->rentman_id;
		}

		$this->send_products( $rentman_user_id, $order );
		unset( $_SESSION['rentman_rental_session'] );
	}

	function send_products( $rentman_user_id, $order) {
		$products = $order->get_items();

		$cart = array();

		foreach ( $products as $product ) {
			$product_id = $product['item_meta']['_product_id'][0];
			$sku = get_post_meta($product_id, "_sku");
			$sku = $sku[0];
			$quantity = $product['item_meta']['_qty'];		
			$cart["items"][$sku] = array(
				"id" => $sku,
				"aantal" => $quantity[0]
				);
		}

		$cart["in"] = get_post_meta( $order->id, 'from_date', true );
		$cart["out"] = get_post_meta( $order->id, 'to_date', true );

		$notes = $order->customer_note;

		$output = array(
			"client" => $rentman_user_id,
			"location" => $rentman_user_id,
			"cart" => json_encode($cart),
			"notes" => $notes
			);

		$this->api_post_order($output);
	}

	function get_rentman_id_from_db( $user_id )
    {
		global $wpdb;
		$wpdb->show_errors();
		
		$table_name = $wpdb->prefix . $this->dbname;
		
		$result = $wpdb->get_row( "SELECT rentman_id, data FROM $table_name WHERE wp_id=$user_id" );

		return $result;
	}

	function add_rentman_id_to_db( $wp_id, $rentman_id, $data )
    {
		global $wpdb;
		$wpdb->show_errors();
		
		$table_name = $wpdb->prefix . $this->dbname;
		
		$wpdb->insert(
			$table_name, 
			array( 
				'wp_id' => $wp_id,
				'rentman_id' => $rentman_id,
				'data' => $data
			) 
		);

		return $wpdb->insert_id;
	}

	function update_rentman_customer_data( $rentman_id, $data )
    {
		global $wpdb;
		$wpdb->show_errors();
		
		$table_name = $wpdb->prefix . $this->dbname;
		
		$result = $wpdb->update( 
			$table_name, 
			array( 
				'data' => $data
			),
			array( 'rentman_id' => $rentman_id )
		);

		if ($result == false) {
			//logit('DB ERROR');
		}

		return $wpdb->insert_id;	
	}

	function login_credentials_correct()
    {
		$request = $this->api_get('api/v1/webshop/staffel/1' , false, true);
		if ( $request->info->http_code === 200 ) {
			$options = get_option( 'rentman_settings' );
			$this->api_username = $options['rentman_account_name'];
			$this->api_sslkey = $options['rentman_password'];
			$this->base_url = 'https://'. $options['rentman_account_name'] . '.rentman.nl';
			return true;
		}
		//logit($request);
		return false;
	}

	function api_get_products()
    {
		return $this->api_get('api/v1/Materiaal/isFolder/0/tijdelijk/0/inShop/1');
	}

	function api_get_categories()
    {
		return $this->api_get('api/v1/webshop/menu/inShop');
	}

	/** 
	 * Returns the availability of a product on a period
	 * $from_date: the from date in Unix timestamp
	 * $to_date: the to date in Unix timestamp
	 * $product_id: the rentman id of the product to check (only set if single product)
	 * $cart_ids: (only set if the user is viewing cart) list of Rentman id's of the cart products
	 */
	function api_get_availability($from_date, $to_date, $product_id, $cart_ids, $cache = true)
    {
        $key = "rm_availability_" . $from_date ."_". $to_date . "_". $product_id."_";
        if(is_array($cart_ids))
            $key .= implode("-",$cart_ids);

        if ($cache && wp_cache_get($key)) {
            return wp_cache_get($key);
        }

        if ( $cart_ids )
        {
			$result = array();
			foreach ( $cart_ids as $product )
            {
				 // Get the result from the api and add the current product ID
				$api = $this->api_get('api/v1/available/' . $from_date . '/'. $to_date . '/'. $product, false);
				// Use string replacement 
				$api = substr_replace($api, '"id":' . $product . ',' , 1, 0);
				$result[] = $api;
			}
			$result = implode(",", $result);
			$result = substr_replace($result, '[', 0, 0);
			$result .= "]";

            wp_cache_set($key,$result,300);
			return $result;
		} else
        {
            $result = $this->api_get('api/v1/available/' . $from_date . '/'. $to_date . '/'. $product_id, false);
            wp_cache_set($key,$result,300);
            return $result;
		}
	}

	function api_get_staffel($days)
    {
		$key = "rm_staffel_" . $days;

		if (wp_cache_get($key)) {
			return wp_cache_get($key);
		}

		$staffel = $this->api_get('api/v1/webshop/staffel/' . $days , false);
        wp_cache_set($key,$staffel);
		return $staffel;	
	}

    function api_get_rental_discount_user($userId)
    {
        $key = "discount_rental_" . $userId;

        if (wp_cache_get($key)) {
            return wp_cache_get($key);
        }

        $users = $this->api_get('api/v1/contact/ids/' . $userId);
        $discount = $users[0]["materiaalkorting"];
        wp_cache_set($key,$discount);

        return $discount;
    }

    function api_get_sale_discount_user($userId)
    {
        $key = "discount_sale_" . $userId;

        if (wp_cache_get($key)) {
            return wp_cache_get($key);
        }

        $users = $this->api_get('api/v1/contact/ids/' . $userId);
        $discount = $users[0]["verkoopkorting"];
        wp_cache_set($key,$discount);

        return $discount;
    }

	function api_get_cross_sells($products)
    {
		$cross_sells = array();
		if ($products)
        {
			foreach($products as $product)
            {
				$id = $product["id"];
				$server_response = $this->api_get('api/v1/Materiaal/'. $id . '/link/accessoire');
				if (!empty($server_response))
                {
					$server_response["parent"] = $id;
					$cross_sells[] = $server_response;
				}
			}
		}
		return $cross_sells;
	}

	// Gets a single product's cross sells
	function api_get_cross_sell($product_id)
    {
		$server_response = $this->api_get('api/v1/Materiaal/'. $product_id . '/link/accessoire');
		if (!empty($server_response))
        {
			return $server_response;
		 } else {
		 	return false;
		 }
	}

	function api_get($url, $decoded = TRUE, $return_object = false)
    {
		$api = new RestClient(array(
		    'base_url' => $this->base_url, 
		    'format' => "json", 
		));
		$result = $api->get($url);
		if ( $return_object ) {
			return $result;
		}
		return ($decoded ? json_decode($result->response, TRUE) : $result->response);
	}

	function api_post_contact( $contact )
    {
		$result = $this->api_post('api/v1/Contact', $contact);
		return json_decode($result->response);
	}

	function api_post_order( $order )
    {
		$result = $this->api_post('api/v1/webshop/submitorder', $order);
		//logit($result);
		return json_decode($result->response);
	}

	function api_post( $url, $data )
    {
		$api = new RestClient(array(
		    'base_url' => $this->base_url, 
		    'format' => "json", 
		));
		$result = $api->post($url, $data);
		return $result;
	}

	function add_defaults()
    {
		global $wpdb;

		$table_name = $wpdb->prefix . $this->dbname;
		
		/*
		 * We'll set the default character set and collation for this table.
		 * If we don't do this, some characters could end up being converted 
		 * to just ?'s when saved in our table.
		 */
		$charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
		  $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		}

		if ( ! empty( $wpdb->collate ) ) {
		  $charset_collate .= " COLLATE {$wpdb->collate}";
		}

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			wp_id bigint(20) NOT NULL,
			rentman_id bigint(20) NOT NULL,
			data text(10000) NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

	}

	// Staffel = volume discount factor
	function apply_staffel($price)
    {
		// Get the current dates from session
		$dates = $this->get_dates();
		if (is_numeric($dates['from_date']) && is_numeric($dates['to_date'])) {
			$from_date = new DateTime();
			$to_date = new DateTime();
			$from_date->setTimestamp($dates['from_date']);
			$to_date->setTimestamp($dates['to_date']);
			$days = $to_date->diff($from_date)->format("%a");
			$staffel = $this->api_get_staffel($days);
			$staffel = str_replace('"', '', $staffel);
			if (is_numeric($staffel)) {	
				$price = $price * $staffel;
				return $price;
			} else {
				return new WP_Error('Invalid staffel data');
			}
		} else {
			return $price;
		}
	}

    function get_staffel()
    {
        // Get the current dates from session
        $days = $this->get_days();
        $staffel = $this->api_get_staffel($days);
        $staffel = str_replace('"', '', $staffel);
        if (is_numeric($staffel))
            return $staffel;

        return 1;
    }

    function get_days()
    {
        // Get the current dates from session
        $dates = $this->get_dates();
        if (is_numeric($dates['from_date']) && is_numeric($dates['to_date'])) {
            $from_date = new DateTime();
            $to_date = new DateTime();
            $from_date->setTimestamp($dates['from_date']);
            $to_date->setTimestamp($dates['to_date']);
            return $to_date->diff($from_date)->format("%a");
        }

        return 1;
    }

	function get_dates()
    {
        if (!isset ($_SESSION['rentman_rental_session']))
        {
            $_SESSION['rentman_rental_session'] = array();
        }

        if (isset($_SESSION['rentman_rental_session']['from_date']) && isset($_SESSION['rentman_rental_session']['to_date']))
        {
			$from_date = sanitize_text_field($_SESSION['rentman_rental_session']['from_date']);
			$to_date = sanitize_text_field($_SESSION['rentman_rental_session']['to_date']);
			return array("from_date" => $from_date, "to_date" => $to_date);
		}
        else
        {
			return false;
		}
	}

	function add_menu()
    {
		add_submenu_page('woocommerce', 
			'Rentman settings',
			'Rentman', 
			'manage_woocommerce', 
			'rentman', 
			array($this, 'build_menu')
			);
	}

	function build_menu()
    {
		?>
		<h1>Rentman import</h1>
		<p>
			<form method="post">
			<input type="hidden" name="import-rentman">
			<input type="submit" class="button button-primary" value="<?php echo __('Producten importeren',"rentman"); ?>">
			</form>
		</p>
		<p id="importMelding" style="display: none;"><?php echo __('Bezig met importeren. Het importeren van producten kan enkele minuten duren, sluit gedurende deze periode dit scherm niet af',"rentman"); ?></p>
        <ul id="importstatus" style="color: orange;">

        </ul>
		<p>
			<form action="options.php" method="post">
			<?php
				settings_fields( 'rentman_login_options' );
				do_settings_sections( 'rentman' );
				submit_button();
			?>
			</form>
		</p>
		<?php
	}

	/**
	 * $cart: whether the current page is the cart page
	 */
	function init_datepickers($cart = false)
    {
		global $post;
		// Register the required JS
		wp_enqueue_script(
			'jquery-ui',
			"https://code.jquery.com/ui/1.11.1/jquery-ui.min.js",
			array( 'jquery' )
		);
        wp_enqueue_script(
            'jquery-ui-i18n',
            "http://ajax.googleapis.com/ajax/libs/jqueryui/1.11.1/i18n/jquery-ui-i18n.min.js",
            array( 'jquery' )
        );
		wp_enqueue_script(
			'date_picker_base',
			plugins_url('js/date_picker_base.js', __FILE__),
			array( 'jquery' )
		);

		$session_dates = $this->get_dates();
		if ($session_dates !== false)
        {
			$from_date = $session_dates["from_date"];
			$to_date = $session_dates["to_date"];
		} else {
			$from_date = date(DATE_ISO8601);
			$to_date = date(DATE_ISO8601, time() + 60 * 60 * 24);
		}

		$cart_ids = array();
		$product_id = -1;

		if ($cart) {
			$cart_ids = $this->get_cart_ids();
		} else {
			$product_id = get_post_meta($post->ID, "_sku");
			$product_id = $product_id[0];
		}
        $options = get_option( 'rentman_settings' );
	    $js_variables = array( 
	    	'ajax_file_path' => admin_url('admin-ajax.php'),
	    	'from_date' => $from_date,
	    	'to_date' => $to_date,
	    	'cart_ids' => $cart_ids,
	    	'product_id' => $product_id,
	    	'server_utc_offset' => timezone_offset_get(timezone_open(date_default_timezone_get()), new DateTime()),
            'rm_checkAvailabilty' => $options['rentman_availabilityCheck']
	    	); 
	    wp_localize_script( 'date_picker_base', 'date_picker_localized', $js_variables);

        wp_localize_script( 'date_picker_base', 'rm_translate', array(
            'is_available' => __( 'Product is beschikbaar', 'rentman' ),
            'not_available' => __( 'Het product is mogelijk beschikbaar, maar niet definitief', 'rentman' ),
            'maybe_available' => __( 'Het product is niet beschikbaar in deze hoeveelheid voor de opgegeven periode', 'rentman' )
            ));
		// CSS for jQuery UI
		wp_enqueue_style(
			'jquery-ui',
			plugins_url('js/jquery-ui.min.css', __FILE__)
		);
	}

	function cart_datepicker_template()
    {
		global $woocommerce; global $rentman;

        if(!$this->rentalInCart())
            return;

		?>
        <div class="cart_totals">
		<h2><?php echo __('Huurperiode',"rentman"); ?></h2>
		<label for="datepicker-from-date" style="display: inline-block; width: 60px;"><?php echo __('Van:',"rentman"); ?> </label>
		<input type="text" id="datepicker-from-date" class="datepicker"><br>
		<label for="datepicker-from-date" style="display: inline-block; width: 60px;"><?php echo __('Tot:',"rentman"); ?> </label>
		<input type="text" id="datepicker-to-date" class="datepicker"><br>
        <label for="datepicker-from-date" style="display: inline-block; width: 60px;"><?php echo __('Staffel:',"rentman"); ?> </label>
        <span id="staffelweergave"><?php echo($rentman->get_staffel()); ?></span><br>
        <input type="button" class="button" value="Periode bijwerken" id="changePeriod" style="margin-top: 20px;">
        </div>
		<?php
	}

	// Add the required HTML for datepickers and availability to product pages
	function product_datepicker_template()
    {
		global $woocommerce;

        $dates = $this->get_dates();
		if ( count( $woocommerce->cart->cart_contents ) !== 0 && is_numeric($dates['from_date']) && is_numeric($dates['to_date']))
        {
			$format = "%d %B %Y";
			$from_date_formatted = strftime($format, $dates['from_date']);
			$to_date_formatted = strftime($format, $dates['to_date']);

			echo '<h4>'. __('Geselecteerde data',"rentman") .'</h4><p>';
			echo $from_date_formatted;
			echo " - ";
			echo $to_date_formatted;
			echo '</p><p>'. __('Ga naar de winkelwagen om de huurperiode te wijzigen',"rentman").'</p>';
			?>
			<div class="" id="rentman-availability-status"></div>
			<input type="hidden" id="datepicker-from-date" class="datepicker" /><br>
			<input type="hidden" id="datepicker-to-date" class="datepicker" /><br>
			<?php
		} else { 
			?>
			<input type="text" id="datepicker-from-date" class="datepicker"><br>
			<input type="text" id="datepicker-to-date" class="datepicker"><br>
			<div class="" id="rentman-availability-status"></div></br>
			<?php
		}
	}

	// Gets all the rentman ids of the products in cart
	function get_cart_ids()
    {
		global $woocommerce;
		$ids = array();
		foreach($woocommerce->cart->cart_contents as $cart_item)
        {
			$product_id = $cart_item["product_id"];
			$sku = get_post_meta($product_id, "_sku");
			$ids[] = $sku[0];
		}
		return $ids;
	}

	function show_attachments()
    {
		global $post;

		$args = array( 
			'post_type' => 'attachment',
			'post_status' => null,
			'post_parent' => $post->ID,
			'posts_per_page' => -1,
			'post_mime_type' => array( 'application/pdf','application/vnd.ms-excel','application/msword' )
			); 

		$attachments = get_posts( $args );

		if ( $attachments )
        {
			echo "<h4>". __('Bijgevoegde bestanden:',"rentman") ."</h4></br>";
			foreach ( $attachments as $attachment )
            {
				echo '<div class="rentman-attachment">';
				echo wp_get_attachment_link( $attachment->ID, 'thumbnail', false, false);
				echo '</div>';
			}
			echo "<br><br>";
		}
	}

	function add_to_cart_template()
    {
		global $post;
		$product = get_product($post->ID);

		if ($product->product_type == 'rentable')
        {
			wc_get_template( 'single-product/add-to-cart/rentable.php');
			$this->init_datepickers(false);
			$this->product_datepicker_template();
		}
	}

	function cart_date_picker()
    {
        if(!$this->rentalInCart())
            return;

        $this->init_datepickers(TRUE);
		$this->cart_datepicker_template();
	}

    function rentalInCart()
    {
        global $woocommerce;

        foreach ($woocommerce->cart->cart_contents as $cart_key => $cart_item)
        {
            if($cart_item['data']->product_type == "rentable")
                return true;
        }

        return false;
    }

	function disable_comments()
    {
		return false;
	}

	function plugin_path()
    {
	  // gets the absolute path to this plugin directory
	  return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	function rentman_woocommerce_locate_template( $template, $template_name, $template_path )
    {
		global $woocommerce, $product;

		$_template = $template;
		if ( ! $template_path ) $template_path = $woocommerce->template_url;
		$plugin_path  = $this->plugin_path() . '/woocommerce/';


		// Look within passed path within the theme - this will be overridden by the plugin
		$theme_template	= locate_template(
			array(
			  $template_path . $template_name,
			  $template_name
			)
		);
		if ($theme_template) {
			$template = $theme_template;
		}

		// Modification: Get the template from this plugin, if it exists
		if ( ( $theme_template || ! $template ) && file_exists( $plugin_path . $template_name ) )
        {
			$template = $plugin_path . $template_name;
		}

		// Hacky way to make the template override work in certain themes
		if (strpos('_' . $_template, 'rentable.php'))
        {
			// Get the name of the plugin folder (with intermediate variables so PHP isn't confused by pointers)
			$plugin_folder_name = explode( '/', $plugin_path );
			$plugin_folder_name = array_reverse( $plugin_folder_name );
			return str_replace('woocommerce/templates', $plugin_folder_name[2] . '/woocommerce', $_template);
		}

		// Use default template
		if ( ! $template )
		    $template = $_template;

		// Return what we found
		return $template;
	}

    function multiplyDailyFee()
    {
        global $woocommerce,$rentman;

        $totalSale = 0;
        $totalRental = 0;
        foreach ($woocommerce->cart->cart_contents as $cart_key => $cart_item)
        {
            if($cart_item['data']->product_type == "rentable")
                $totalRental += $cart_item["line_total"];
            else
                $totalSale += $cart_item["line_total"];
            //var_dump($cart_item_array);
        }


        $days = $this->get_days();

        $incl_staffel = $rentman->apply_staffel($totalRental);
        if($incl_staffel - $totalRental > 0)
            $woocommerce->cart->add_fee( __('Extra', 'woocommerce'). " " .($days-1) .__(' dag(en)', 'woocommerce'), floatval ($incl_staffel - $totalRental),true);

        //Add discount:
        $options = get_option( 'rentman_settings' );
        if($options['rentman_addDiscount'] && get_current_user_id()> 0)
        {
            $db_data = $this->get_rentman_id_from_db( get_current_user_id() );

            if ( is_numeric($db_data->rentman_id) )
            {
                $discount = $this->api_get_rental_discount_user($db_data->rentman_id);
                if($discount > 0)
                    $woocommerce->cart->add_fee($discount.__("% Korting verhuur","rentman"),($incl_staffel * ($discount * 0.01) * -1),true);

                $discount = $this->api_get_rental_discount_user($db_data->rentman_id);
                if($discount > 0)
                    $woocommerce->cart->add_fee($discount.__("% Korting verkoop","rentman"),($totalSale * ($discount * 0.01) * -1),true);
            }
        }
    }

    function my_text_strings( $translated_text, $text, $domain )
    {
        switch ( $translated_text ) {
            case 'Subtotaal' :
                $translated_text = __("Dagprijs","rentman");
                break;
            case 'Winkelmand Subtotaal' :
                $translated_text = __("Dagprijs","rentman");
                break;
        }
        return $translated_text;
    }
}



if (!defined('ABSPATH')) exit;

global $rentman_db_version;
$rentman_db_version = '0.1';

setlocale(LC_ALL, 'nl_NL');
$rentman = new Rentman();
