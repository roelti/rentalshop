<?php
    // ------------- Plugin Setup Functions ------------- \\

    /**
     * Plugin Name: Rentman
     * Plugin URI: https://rentman.io
     * GitHub Plugin URI: https://github.com/rentmanpublic/rentalshop
     * Description: Integrates Rentman rental software into WooCommerce
     * Version: 4.10.4
     * Author: Rentman
     * Text Domain: rentalshop
     * WC requires at least: 3.0.0
     * WC tested up to: 3.3.3
     */

    # Start session
    if (session_id() == ''){
        session_start();
    }

    # Include other PHP files
    include_once('product_availability.php');
    include_once('product_categories.php');
    include_once('product_import.php');
    include_once('product_media.php');
    include_once('product_prices.php');
    include_once('rentman_requests.php');
    include_once('rentman_project.php');
    include_once('rentman_user.php');

    # Create plugin-settings option
    if (false == get_option('plugin-settings')){
        add_option('plugin-settings');
    }

    # Add actions for Admin Initialization, Admin Menu, Fee Calculation,
    # Woocommerce Checkout and more to the right hooks

    add_action('admin_init', 'register_settings');
    add_action('admin_menu', 'register_submenu');
    add_action('admin_notices', 'check_github_updater');
    add_action('init', 'register_rental_product_type');
    add_action('init', 'translatePlugin');
    add_action('woocommerce_admin_order_data_after_billing_address', 'display_dates_in_order', 10, 1);
    add_action('woocommerce_after_single_product', 'set_functions');
    add_action('woocommerce_before_add_to_cart_form', 'show_discount', 9, 1);
    add_action('woocommerce_before_add_to_cart_button', 'add_custom_field', 10, 1);
    add_action('woocommerce_before_cart_totals', 'add_date_checkout');
    add_action('woocommerce_cart_calculate_fees', 'apply_staffel');
    add_action('woocommerce_checkout_update_order_meta', 'add_rental_data');
    add_action('woocommerce_review_order_before_submit', 'show_selected_dates');
    add_action('woocommerce_single_product_summary', 'add_to_cart_template', 30);
    add_action('woocommerce_thankyou', 'export_users', 10, 1);
    add_action('wp_ajax_wdm_add_user_custom_data_options', 'update_dates');
    add_action('wp_ajax_nopriv_wdm_add_user_custom_data_options', 'update_dates');

    # Add filters for certain buttons and texts
    add_filter('woocommerce_add_to_cart_validation', 'check_available', 10, 5);
    add_filter('woocommerce_checkout_fields', 'adjust_checkout');
    add_filter('woocommerce_product_single_add_to_cart_text', 'woo_custom_cart_button_text');
    add_filter('woocommerce_update_cart_validation', 'update_amount', 10, 5);
    add_filter('woocommerce_cart_needs_shipping', '__return_true');
    add_filter('woocommerce_email_order_meta_fields', 'add_dates_to_email', 10, 3);
    add_filter('product_type_selector', 'add_rentable_product');
    add_filter('gettext', 'my_text_strings', 20, 3);

    # Register the plugin settings for a specific user
    function register_settings()
    {
        register_setting('plugin-settings', 'plugin-account');
        register_setting('plugin-settings', 'plugin-checkavail');
        register_setting('plugin-settings', 'plugin-checkdisc');
        register_setting('plugin-settings', 'plugin-checkoverwrite');
        register_setting('plugin-settings', 'plugin-checkstock');
        register_setting('plugin-settings', 'plugin-enddate');
        register_setting('plugin-settings', 'plugin-lasttime');
        register_setting('plugin-settings', 'plugin-password');
        register_setting('plugin-settings', 'plugin-rentmanIDs');
        register_setting('plugin-settings', 'plugin-startdate');
        register_setting('plugin-settings', 'plugin-token');
        register_setting('plugin-settings', 'plugin-username');
    }

    # Register the WooCommerce submenu
    function register_submenu()
    {
        add_submenu_page('woocommerce', 'Rentman', 'Rentman', 'manage_options', 'rentman-shop', 'menu_display');
    }

    # Load text domain used for translation of the plugin
    function translatePlugin()
    {
        load_plugin_textdomain('rentalshop', false, dirname(plugin_basename(__FILE__)) . '/lang');
    }

    # Check if 'GitHub Updater' plugin is active
    function check_github_updater() {
        # Show message if 'GitHub Updater' plugin is inactive
        if (!is_plugin_active('github-updater-develop/github-updater.php') && !is_plugin_active('github-updater/github-updater.php')){
            _e("<div class='notice notice-warning is-dismissible'><p>Let op: Installeer en activeer de GitHub Updater plugin (https://github.com/afragen/github-updater) om automatisch naar updates te zoeken voor de Rentman 4G plugin!</p></div>", 'rentalshop');
        }
    }

    # Display and initialize Rentman Plugin Menu in Wordpress Admin Panel
    function menu_display()
    {
        ?>
        <?php _e('<h1>Rentman Product Import - v4.10.4</h1><hr><br>', 'rentalshop') ?>
        <img src="https://rentman.io/img/rentman-logo.svg" alt="Rentman" height="42" width="42">
        <?php _e('<h3>Log hier in met uw Rentman 4G gegevens</h3>', 'rentalshop') ?>
        <form method="post" , action="options.php">
            <?php settings_fields('plugin-settings'); ?>
            <?php do_settings_sections('plugin-settings'); ?>
            <strong>Rentman Account</strong>
            <input type="text" name="plugin-account" value="<?php echo get_option('plugin-account'); ?>"/><br>
            <?php _e('<strong>Gebruikersnaam</strong>', 'rentalshop'); ?>
            <input type="text" name="plugin-username" value="<?php echo get_option('plugin-username'); ?>"/><br>
            <?php _e('<strong>Wachtwoord</strong>', 'rentalshop'); ?>
            <input type="password" name="plugin-password" value="<?php echo get_option('plugin-password'); ?>"/><br>
            <?php submit_button(__('Gegevens controleren', 'rentalshop')); ?>
        </form>

        <?php # If no dates are set, they are set to the current date
        if (!isset ($_SESSION['rentman_rental_session'])){
            $today = date("Y-m-j");
            $_SESSION['rentman_rental_session'] = array(
                'from_date' => $today,
                'to_date' => $today
            );
        }

        $token = login_user(); # Receive token when signing in
        update_option('plugin-token', $token); # Save new token in database
        if (false == get_option('plugin-lasttime'))
            $lastTime = 'Never'; # Product Import hasn't been done before
        else
            $lastTime = get_option('plugin-lasttime');
        ?>

        <?php # Buttons for availability check and discount
        $availCheck = get_option('plugin-checkavail');
        $discountCheck = get_option('plugin-checkdisc');
        $overwriteCheck = get_option('plugin-checkoverwrite');
        $stockCheck = get_option('plugin-checkstock');
        if ($availCheck == '' or $discountCheck == '' or $overwriteCheck == '' or $stockCheck == ''){
            update_option('plugin-checkdisc', 0);
            update_option('plugin-checkavail', 0);
            update_option('plugin-checkoverwrite', 0);
            update_option('plugin-checkstock', 0);
        } ?>

        <?php _e('<hr><h3>Instellingen</h3>', 'rentalshop'); ?>
        <form method="post"><!-- If checked, applies availability check in the shop -->
            <?php _e('<strong>Check beschikbaarheid voor sturen  </strong>', 'rentalshop'); ?>
            <select name='plugin-checkavail'>
                <option value="1" <?php if (get_option('plugin-checkavail') == 1){
                    echo "selected";
                } ?>>Yes
                </option>
                <option value="0" <?php if (get_option('plugin-checkavail') == 0){
                    echo "selected";
                } ?>>No
                </option>
            </select>
            <!-- If checked, specific customer discounts in Rentman are loaded and applied -->
            <?php _e('<br><br><strong>Korting contact uit Rentman overnemen  </strong>', 'rentalshop'); ?>
            <select name='plugin-checkdisc'>
                <option value="1" <?php if (get_option('plugin-checkdisc') == 1){
                    echo "selected";
                } ?>>Yes
                </option>
                <option value="0" <?php if (get_option('plugin-checkdisc') == 0){
                    echo "selected";
                } ?>>No
                </option>
            </select>
            <!-- If checked, existing Rentman products will not be overwritten during the import -->
            <?php _e('<br><br><strong>Schakel het overschrijven van bestaande Rentman-producten uit  </strong>', 'rentalshop'); ?>
            <select name='plugin-checkoverwrite'>
                <option value="1" <?php if (get_option('plugin-checkoverwrite') == 1){
                    echo "selected";
                } ?>>Yes
                </option>
                <option value="0" <?php if (get_option('plugin-checkoverwrite') == 0){
                    echo "selected";
                } ?>>No
                </option>
            </select>
            <!-- If checked, displays the stock of the materials on the product pages -->
            <?php _e('<br><br><strong>Voorraad weergeven op productpagina  </strong>', 'rentalshop'); ?>
            <select name='plugin-checkstock'>
                <option value="1" <?php if (get_option('plugin-checkstock') == 1){
                    echo "selected";
                } ?>>Yes
                </option>
                <option value="0" <?php if (get_option('plugin-checkstock') == 0){
                    echo "selected";
                } ?>>No
                </option>
            </select>
            <!-- Button that saves the changes to the settings -->
            <p><input type="hidden" name="change-settings">
                <input type="submit" class="button button-primary"
                       value="<?php _e('Wijzigingen Opslaan', 'rentalshop') ?>">
        </form>
        <br>
        <hr><h3><?php _e('Update afbeeldingen van producten', 'rentalshop'); ?></h3>
        <ul>
            <li><?php _e('Druk op de onderstaande knop wanneer je afbeeldingen in Rentman hebt gewijzigd<br>
            om de wijzigingen toe te passen in WooCommerce.', 'rentalshop'); ?></li>
        </ul>
        <p> <!-- Button that handles image import and update -->
        <form method="post">
            <input type="hidden" name="image-rentman">
            <input type="submit" class="button button-primary"
                   value="<?php _e('Afbeeldingen Updaten', 'rentalshop') ?>">
        </form><br>
        <div id="imageMelding"
             style="display: none;"><?php _e('<h3>De afbeeldingen worden opgehaald..</h3>', 'rentalshop'); ?></div>
        <p id="imageStatus"></p>
        <hr><h3><?php _e('Importeer materiaal uit Rentman', 'rentalshop'); ?></h3>
        <ul>
            <li><?php _e('Druk op de onderstaande knop om te zoeken naar nieuwe of gewijzigde producten en deze<br>
            van je Rentman account naar je WooCommerce shop over te zetten.', 'rentalshop'); ?></li>
            <li><?php _e('-- Meest recente check voor updates: ', 'rentalshop');
                echo $lastTime; ?></li>
        </ul>

        <p> <!-- Button that handles product import -->
        <form method="post">
            <input type="hidden" name="import-rentman">
            <input type="submit" class="button button-primary"
                   value="<?php _e('Producten Importeren', 'rentalshop'); ?>">
        </form>
        <br> <!-- Button for total reset -->
        <form method="post">
            <input type="hidden" name="reset-rentman">
            <input type="submit" class="button button-primary" value="<?php _e('Reset', 'rentalshop'); ?>">
        </form>
        <br> <!-- Message that appears when you start the product import -->
        <div id="importMelding"
             style="display: none;"><?php _e('<h3>Bezig met importeren.. Dit kan enkele minuten duren, dus verlaat deze pagina niet!</h3>', 'rentalshop'); ?></div>
        <p id="deleteStatus"></p>
        <p id="importStatus"></p>
        <p id="taxWarning"></p>
        <?php

        # If 'Save Changes' button has been pressed, update options
        if (isset($_POST['change-settings'])){
            update_option('plugin-checkdisc', $_POST['plugin-checkdisc']);
            update_option('plugin-checkavail', $_POST['plugin-checkavail']);
            update_option('plugin-checkoverwrite', $_POST['plugin-checkoverwrite']);
            update_option('plugin-checkstock', $_POST['plugin-checkstock']);
            echo "<meta http-equiv='refresh' content='0'>";
        }

        # If 'Import Products' button has been pressed, call function from product_import.php
        if (isset($_POST['import-rentman'])){
            import_products($token);
        }

        # If 'Update Images' button has been pressed, call function from product_import.php
        if (isset($_POST['image-rentman'])){
            update_images($token);
        }

        # Import Products with certain index in array (called by admin_import.js)
        if (isset($_GET['import_products'])){
            $_REQUEST = array_merge($_GET, json_decode(file_get_contents('php://input'), true));
            $prod_array = $_REQUEST['prod_array'];
            $file_array = $_REQUEST['file_array'];
            $array_index = $_REQUEST['array_index'];
            array_to_product($prod_array, $file_array, (int)$array_index);
        }

        # Update images with certain index in array (called by admin_images.js)
        if (isset($_GET['update_images'])){
            $_REQUEST = array_merge($_GET, json_decode(file_get_contents('php://input'), true));
            $image_array = $_REQUEST['image_array'];
            $array_index = $_REQUEST['array_index'];
            $current_image = $image_array[(int)$array_index];
            $post_id = wc_get_product_id_by_sku((int)$array_index);
            # Delete the old images attached to the product
            $media = get_children(array('post_parent' => $post_id, 'post_type' => 'attachment'));
            foreach ($media as $file){
                wp_delete_post($file->ID);
            }
            # Create and attach the new images
            for ($x = 0; $x < sizeof($current_image); $x++){
                attach_media($current_image[$x], $post_id, (int)$array_index, $x);
            }
        }

        # Delete certain amount of posts (called by admin_delete.js)
        if (isset($_GET['delete_products'])){
            $_REQUEST = array_merge($_GET, json_decode(file_get_contents('php://input'), true));
            $posts = $_REQUEST['prod_array'];
            $index = $_REQUEST['array_index'];
            delete_by_index($posts, (int)$index);
        }

        # Remove Empty Categories
        if (isset($_GET['remove_folders'])){
            remove_empty_categories();
        }

        # If 'Reset' button has been pressed, delete Rentman products and their categories
        if (isset($_POST['reset-rentman'])){
            reset_rentman();
        }
    }

    # Return the dates for the rental period from the current session
    function get_dates()
    {
        $today = date("Y-m-j");
        if (!isset ($_SESSION['rentman_rental_session'])){
            $_SESSION['rentman_rental_session'] = array(
                'from_date' => $today,
                'to_date' => $today
            );
        }

        if (isset($_SESSION['rentman_rental_session']['from_date']) && isset($_SESSION['rentman_rental_session']['to_date'])){
            $from_date = sanitize_text_field($_SESSION['rentman_rental_session']['from_date']);
            $to_date = sanitize_text_field($_SESSION['rentman_rental_session']['to_date']);
            return array("from_date" => $from_date, "to_date" => $to_date);
        }
        else{
            return false;
        }
    }

    // ------------- User Login Functions ------------- \\

    # Receive the endpoint url for use in all API requests
    function receive_endpoint()
    {
        $account = get_option('plugin-account');
        $url = "http://api.rentman.eu/version/index.php?account=" . $account;
        $received = do_request($url, '');
        $parsed = json_decode($received, true);
        if ($parsed['version'] != 4) # Check whether it is a Rentman 4 account
            _e('Dit is geen Rentman 4 account! &#10005;<br>', 'rentalshop');
        return $parsed['endpoint'] . '/api.php';
    }

    # Main function for user login
    function login_user()
    {
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
                _e('<h4>De verbinding met de Rentman API is mislukt! Kloppen uw gegevens wel?</h4>', 'rentalshop');
            } else{
                _e('<h4>De verbinding met de Rentman API was succesvol!</h4>', 'rentalshop');
            }
        }
        return $token;
    }

    # Check the compatibility with the plugin
    function check_compatibility()
    {
        _e('<b>Compatibiliteitscontrole..</b><br>', 'rentalshop');
        echo 'Current PHP version: ' . phpversion() . '<br>'; # Get current PHP version
        $artDir = '/uploads/rentman/';
        $fileUrl = 'https://raw.githubusercontent.com/rentmanpublic/rentalshop/plugin4g_beta/img/test.png';

        # Check the PHP time limit
        $timelimit = ini_get('max_execution_time');
        if ($timelimit < 30){
            echo '<p style="color:red;">';
            _e('Let op, de PHP tijdslimiet is lager dan 30 seconden! Mogelijk werkt de plugin hierdoor niet goed..', 'rentalshop');
            echo '</p>';
        }
        else{
            _e('PHP tijdslimiet is in orde &#10003;<br>', 'rentalshop');
        }

        # Does Rentman image Folder exist?
        if (!file_exists(WP_CONTENT_DIR . $artDir)){
            _e('Map aangemaakt op <i>wp-content/uploads/rentman/</i> &#10003;<br>', 'rentalshop');
            mkdir(WP_CONTENT_DIR . $artDir); # Create one if it doesn't
        } else{
            _e('De Rentman map voor afbeeldingen is aanwezig &#10003;<br>', 'rentalshop');
        }

        # Does the copy function for images work?
        $file_name = 'test.png';
        $targetUrl = WP_CONTENT_DIR . $artDir . $file_name;
        copy($fileUrl, $targetUrl);
        $errors = error_get_last();
        if (file_exists($targetUrl)){
            _e('Toevoegen van afbeeldingen is gelukt &#10003;<br>', 'rentalshop');
        } else{
            echo '<p style="color:red;">';
            _e('Toevoegen van afbeeldingen is mislukt..', 'rentalshop');
            echo '</p>';
            echo "&bull; Copy Error: " . $errors['type'];
            echo "<br />\n&bull; " . $errors['message'] . '<br>';
            if (!ini_get('allow_url_fopen')){ # Show possible solution if the copy function fails
                _e('&bull; <i>url_fopen()</i> is disabled in het <i>php.ini</i> bestand. Probeer dit te wijzigen en kijk of het probleem daarmee is opgelost.<br>', 'rentalshop');
            }
        }
        $artDir = '/uploads/rentman/';
        $new_file_name = '.htaccess';

        # Check if images can be displayed
        $targetUrl = WP_CONTENT_DIR . $artDir . $new_file_name;
        if (!file_exists($targetUrl)){
            _e('Let op: er ontbreekt een .htaccess bestand in de \'uploads/rentman/\' map. Mogelijk worden de afbeeldingen niet correct weergegeven..<br>', 'rentalshop');
        } else{
            _e('Afbeeldingen kunnen weergegeven worden &#10003;<br>', 'rentalshop');
        }
    }

    # Check if given login data is complete
    function completedata()
    {
        if (false == get_option('plugin-account') or false == get_option('plugin-username') or false == get_option('plugin-password'))
            return false;
        return true;
    }

    // ------------- API Request Functions ------------- \\

    # Function that parses the response to a clear format
    function parseResponse($response)
    {
        # Check whether the response contains any data
        if (!empty($response['response']['columns'])){
            $columnNames = array();
            # Get column names
            foreach ($response['response']['columns'] as $key => $file) {
                array_push($columnNames, $key);
            }
            # Parse key names of each column
            foreach ($columnNames as $column) {
                $currentCol = $response['response']['items'][$column];
                # For every item in the column, change the keys
                foreach ($currentCol as $identifier => $item) {
                    for ($x = 0; $x < sizeof($item['data']); $x++) {
                        $keyname = $response['response']['columns'][$column][$x]['id'];
                        $item['data'][$keyname] = $item['data'][$x];
                        unset($item['data'][$x]);
                    }
                    # Adjust the response accordingly
                    $response['response']['items'][$column][$identifier]['data'] = $item['data'];
                }
            }
        }
        return $response;
    }

    # Does a JSON request with a given message
    function do_request($url, $message)
    {
        # Setup a cURL session
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
    function error_info($ch)
    {
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