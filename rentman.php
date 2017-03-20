<?php // MAIN PLUGIN FILE

    // ------------- Plugin Setup Functions ------------- \\

    /**
     * Plugin Name: Rentman
     * Plugin URI: http://www.rentman.nl
     * Description: Integrates Rentman rental software into WooCommerce
     * Version: 4.1.3
     * Author: Rentman
     * Text Domain: rentalshop
     */

    # Include other PHP files
    include_once('product_availability.php');
    include_once('product_categories.php');
    include_once('product_import.php');
    include_once('product_media.php');
    include_once('product_prices.php');
    include_once('rentman_project.php');
    include_once('rentman_user.php');

    # Create plugin-settings option
    if( false == get_option('plugin-settings')){
        add_option('plugin-settings');
    }

    # Add actions for Admin Initialization, Admin Menu and
    # Woocommerce Checkout to the right hooks
    add_action('wp_print_scripts', 'add_header_script');
    add_action('admin_init', 'register_settings');
    add_action('admin_menu', 'register_submenu');
    add_action('init', 'register_rental_product_type');
    add_action('init', 'translatePlugin');
    add_action('woocommerce_after_single_product', 'set_functions');
    add_action('woocommerce_before_add_to_cart_form', 'show_discount', 9, 1);
    add_action('woocommerce_before_add_to_cart_button', 'add_custom_field', 10, 1);
    add_action('woocommerce_before_cart_totals', 'add_date_checkout');
    add_action('woocommerce_cart_calculate_fees','apply_staffel');
    add_action('woocommerce_cart_calculate_fees','apply_rentman_tax');
    add_action('woocommerce_single_product_summary', 'add_to_cart_template', 30 );
    add_action('woocommerce_thankyou', 'export_users', 10, 1);

    # Add filters for buttons
    add_filter('woocommerce_add_to_cart_validation', 'check_available', 10, 5);
    add_filter('woocommerce_product_single_add_to_cart_text', 'woo_custom_cart_button_text');
    add_filter('woocommerce_update_cart_validation', 'update_amount', 10, 5);
    add_filter('woocommerce_cart_needs_shipping', '__return_true');
    add_filter('product_type_selector', 'add_rentable_product');

    # Register the plugin settings
    function register_settings(){
        register_setting('plugin-settings', 'plugin-account');
        register_setting('plugin-settings', 'plugin-checkavail');
        register_setting('plugin-settings', 'plugin-checkdisc');
        register_setting('plugin-settings', 'plugin-enddate');
        register_setting('plugin-settings', 'plugin-lasttime');
        register_setting('plugin-settings', 'plugin-password');
        register_setting('plugin-settings', 'plugin-startdate');
        register_setting('plugin-settings', 'plugin-token');
        register_setting('plugin-settings', 'plugin-username');
    }

    # Register the WooCommerce submenu
    function register_submenu(){
        add_submenu_page('woocommerce', 'Rentman', 'Rentman', 'manage_options', 'rentman-shop', 'menu_display');
    }

    # Load text domain used for translation of the plugin
    function translatePlugin(){
        load_plugin_textdomain('rentalshop', false, dirname(plugin_basename(__FILE__)) . '/lang' );
    }

    # Display Rentman Plugin Menu in Wordpress Admin Panel
    function menu_display(){
        ?>
        <?php _e('<h1>Rentman Product Import - v4.1.3</h1><hr><br>','rentalshop')?>
        <img src="http://rentman.nl/wp-content/uploads/2013/09/header.jpg" alt="Rentman" height="42" width="42">
        <?php _e('<h3>Log hier in met uw Rentman 4G gegevens</h3>','rentalshop')?>
        <form method="post", action ="options.php">
            <?php settings_fields('plugin-settings'); ?>
            <?php do_settings_sections('plugin-settings'); ?>
            <strong>Rentman Account</strong>
            <input type="text" name="plugin-account" value="<?php echo get_option('plugin-account'); ?>"/><br>
            <?php _e('<strong>Gebruikersnaam</strong>','rentalshop')?>
            <input type="text" name="plugin-username" value="<?php echo get_option('plugin-username'); ?>"/><br>
            <?php _e('<strong>Wachtwoord</strong>','rentalshop')?>
            <input type="password" name="plugin-password" value="<?php echo get_option('plugin-password'); ?>"/><br>
            <?php submit_button(__('Gegevens controleren','rentalshop')); ?>
        </form>

        <?php
            if (get_option('plugin-startdate') == '') {
                $today = date("Y-m-j");
                update_option('plugin-startdate', $today);
                update_option('plugin-enddate', $today);
            }
            $token = login_user(); # Receive token when signing in
            update_option('plugin-token', $token); # Save new token in database
            if (false == get_option('plugin-lasttime'))
                $lastTime = 'Never'; # Product Import hasn't been done before
            else
                $lastTime = get_option('plugin-lasttime');
        ?>
        <!-- Buttons for availability check and discount -->
        <?php
        $availCheck = get_option('plugin-checkavail');
        $discountCheck = get_option('plugin-checkdisc');
        if ($availCheck == '' or $discountCheck == ''){
            update_option('plugin-checkdisc', 0);
            update_option('plugin-checkavail', 0);
        }
        ?>
        <?php _e('<hr><h3>Instellingen</h3>', 'rentalshop')?>
        <form method="post"><!-- If checked, applies availability check in the shop -->
        <?php _e('<strong>Check beschikbaarheid voor sturen  </strong>','rentalshop')?>
        <select name='plugin-checkavail'>
            <option value="1" <?php if(get_option('plugin-checkavail') == 1){echo"selected";} ?>>Yes</option>
            <option value="0" <?php if(get_option('plugin-checkavail') == 0){echo"selected";} ?>>No</option>
        </select>
        <!-- This doesn't do anything yet because of the complexity of all discount calculations -->
        <?php _e('<br><br><strong>Korting contact uit Rentman overnemen  </strong>','rentalshop')?>
        <select name='plugin-checkdisc'>
            <option value="1" <?php if(get_option('plugin-checkdisc') == 1){echo"selected";} ?>>Yes</option>
            <option value="0" <?php if(get_option('plugin-checkdisc') == 0){echo"selected";} ?>>No</option>
        </select>
        <!-- Button that handles product import -->
        <p><input type="hidden" name="change-settings">
        <input type="submit" class="button button-primary" value="<?php _e('Wijzigingen Opslaan','rentalshop')?>">
        </form>
        <br>
        <hr><h3><?php _e('Update afbeeldingen van producten', 'rentalshop')?></h3>
        <ul>
            <li><?php _e('Druk op de onderstaande knop wanneer je afbeeldingen in Rentman hebt gewijzigd<br>
                om de wijzigingen toe te passen in WooCommerce.','rentalshop')?></li>
        </ul>
        <p> <!-- Button that handles image import and update -->
        <form method="post">
            <input type="hidden" name="image-rentman">
            <input type="submit" class="button button-primary" value="<?php _e('Afbeeldingen Updaten','rentalshop')?>">
        </form><br><p id="imageStatus"></p>
        <hr><h3><?php _e('Importeer materiaal uit Rentman', 'rentalshop')?></h3>
        <ul>
            <li><?php _e('Druk op de onderstaande knop om te zoeken naar nieuwe of gewijzigde producten en deze<br>
                van je Rentman account naar je WooCommerce shop over te zetten.','rentalshop')?></li>
            <li><?php _e('-- Meest recente check voor updates: ','rentalshop'); echo $lastTime;?></li>
        </ul>

        <p> <!-- Button that handles product import -->
        <form method="post">
            <input type="hidden" name="import-rentman">
            <input type="submit" class="button button-primary" value="<?php _e('Producten Importeren','rentalshop')?>">
        </form>
        <br> <!-- Button for total reset -->
        <form method="post">
            <input type="hidden" name="reset-rentman">
            <input type="submit" class="button button-primary" value="<?php _e('Reset','rentalshop')?>">
        </form>
        <br> <!-- Message that appears when you start the product import -->
        <div id="importMelding" style="display: none;"><?php _e('<h3>Bezig met importeren.. Dit kan enkele minuten duren, dus verlaat deze pagina niet!</h3>','rentalshop'); ?></div>
        <p id="deleteStatus"></p>
        <p id="importStatus"></p>
        <?php

        # If 'Save Changes' button has been pressed, update options
        if ( isset ( $_POST['change-settings'])){
            update_option('plugin-checkdisc', $_POST['plugin-checkdisc']);
            update_option('plugin-checkavail', $_POST['plugin-checkavail']);
            echo "<meta http-equiv='refresh' content='0'>";
        }

        # If 'Import Products' button has been pressed, call function from product_import.php
        if ( isset ( $_POST['import-rentman'])){
            import_products($token);
        }

        # If 'Update Images' button has been pressed, call function from product_import.php
        if ( isset ( $_POST['image-rentman'])){
            update_images($token);
        }

        # Import Products with certain index in array
        if(isset($_GET['import_products'])){
            $prod_array = $_REQUEST['prod_array'];
            $file_array = $_REQUEST['file_array'];
            $array_index = $_REQUEST['array_index'];
            array_to_product($prod_array, $file_array, $array_index);
        }

        # Delete certain amount of posts
        if(isset($_GET['delete_products'])){
            $posts = $_REQUEST['prod_array'];
            $index = $_REQUEST['array_index'];
            delete_by_index($posts, $index);
        }

        # Remove Empty Categories
        if(isset($_GET['remove_folders'])){
            remove_empty_categories();
        }

        # If 'Reset' button has been pressed, delete Rentman products and their categories
        if ( isset ( $_POST['reset-rentman']))
            reset_rentman();
    }

    // ------------- User Login Functions ------------- \\

    # Receive the endpoint url for use in all API requests
    function receive_endpoint(){
        $account = get_option('plugin-account');
        $url = "https://intern.rentman.nl/rmversion.php?account=" . $account;
        $received = do_request($url, '');
        $parsed = json_decode($received, true);
        if ($parsed['version'] != 4)
            _e('Dit is geen Rentman 4 account! &#10005;<br>','rentalshop');
        return $parsed['endpoint'] . '/api.php';
    }

    # Main function for user login
    function login_user(){
        if (false == completedata()){
            _e('<strong>Niet alle verplichte velden zijn ingevuld</strong>', 'rentalshop');
            $token = "fail"; # User has not filled in all required fields yet
        } else {
            $url = receive_endpoint();
            $message = json_encode(setup_login_request(), JSON_PRETTY_PRINT);

            # Do API request
            $received = do_request($url, $message);

            # Set Token (is used in other API requests)
            $parsed = json_decode($received, true);
            $token = $parsed['response']['token'];

            # Functionality check
            check_compatibility();

            if ($parsed['response']['login'] == false){
				_e('<h4>De verbinding met de Rentman API is mislukt! Kloppen uw gegevens wel?</h4>','rentalshop');
			}	
			else {
				_e('<h4>De verbinding met de Rentman API was succesvol!</h4>','rentalshop');
			}
        }

        return $token;
    }

    # Check multiple things that could go wrong in the plugin
    function check_compatibility(){
        _e('<b>Compatibiliteitscontrole..</b><br>','rentalshop');
        echo 'Current PHP version: ' . phpversion() . '<br>';
        $artDir = 'wp-content/uploads/rentman/';
        $fileUrl = 'https://raw.githubusercontent.com/rentmanpublic/rentalshop/plugin4g_beta/img/test.png';

        # Time Limit Check
        $timelimit = ini_get('max_execution_time');
        if ($timelimit < 30)
            _e('Let op, de PHP tijdslimiet is lager dan 30 seconden! Mogelijk werkt de plugin hierdoor niet goed.. &#10005;<br>','rentalshop');
        else {
            _e('PHP tijdslimiet is in orde &#10003;<br>','rentalshop');
        }

        # Does Rentman Folder exist?
        if(!file_exists(ABSPATH.$artDir)){
            _e('Map aangemaakt op <i>wp-content/uploads/rentman/</i> &#10003;<br>','rentalshop');
            mkdir(ABSPATH.$artDir);
        } else {
            _e('De Rentman map voor afbeeldingen is aanwezig &#10003;<br>','rentalshop');
        }

        # Does the copy function work?
        $file_name = 'test.png';
        $targetUrl = ABSPATH.$artDir.$file_name;
        copy($fileUrl, $targetUrl);
        $errors= error_get_last();
        if (file_exists($targetUrl)) {
            _e('Toevoegen van afbeeldingen is gelukt &#10003;<br>','rentalshop');
        } else {
            _e('Toevoegen van afbeeldingen is mislukt.. &#10005;<br>','rentalshop');
            echo "&bull; Copy Error: ".$errors['type'];
            echo "<br />\n&bull; ".$errors['message'].'<br>';
            if(!ini_get('allow_url_fopen')) {
                _e('&bull; <i>url_fopen()</i> is disabled in het <i>php.ini</i> bestand. Probeer dit te wijzigen en kijk of het probleem daarmee is opgelost.<br>','rentalshop');
            }
        }
        $artDir = 'wp-content/uploads/rentman/';
        $new_file_name = '.htaccess';
        $targetUrl = ABSPATH.$artDir.$new_file_name;
        if(!file_exists($targetUrl)) {
            _e('Let op: er ontbreekt een .htaccess bestand in de \'wp-content/uploads/rentman/\' map. Mogelijk worden de afbeeldingen niet correct weergegeven..<br>','rentalshop');
        } else {
            _e('Afbeeldingen kunnen weergegeven worden &#10003;<br>','rentalshop');
        }
    }

    # Check if given login data is complete
    function completedata(){
        if (false == get_option('plugin-account') or false == get_option('plugin-username')
            or false == get_option('plugin-password'))
            return false;
        return true;
    }

    # Returns API request ready to be encoded in Json
    # Login Request
    function setup_login_request(){
        $login_data = array(
            "requestType" => "login",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.1.3"
            ),
            "account" => get_option('plugin-account'),
            "user" => get_option('plugin-username'),
            "password" => get_option('plugin-password')
        );
        return $login_data;
    }

    // ------------- API Request Functions ------------- \\

    # Does a JSON request with a given message
    function do_request($url, $message){
        # Setup
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        # Send Request & Receive Response
        $response = curl_exec($ch);

        # Uncomment this function if you want to display additional error data
        // error_info($ch);

        curl_close($ch);
        return $response;
    }

    # Displays error info and HTTP code
    function error_info($ch){
        # Error Info
        echo "<b>Error?</b><br>";

        if (curl_error($ch) == "")
            echo 'None';
        echo curl_error($ch);

        echo '<br><br>';

        # Other Info
        echo "<b>HTTP Code</b><br>";

        $info = curl_getinfo($ch);
        echo($info['http_code']);

        echo '<br><br>';
    }

?>