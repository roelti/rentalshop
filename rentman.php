<?php
    // ------------- Plugin Setup Functions ------------- \\

    /**
     * Plugin Name: Rentman Advanced
     * GitHub Plugin URI: https://github.com/rentmanpublic/rentalshop/tree/appsys
     * Description: Integrates Rentman rental software into WooCommerce
     * Version: 4.19.92
     * Author: AppSys
     * Text Domain: rentalshop
     * WC requires at least: 3.0.0
     * WC tested up to: 3.3.3
     */
    //error_reporting(E_ALL | E_STRICT);

    # Start session
    if (session_id() == ''){
        session_start();
    }

    # Check if yoast seo is installed and if so what version of yoast
    # Check if WooCommerce is installed and if so what version of WooCommerce
    # Check if Github updater is installed
    $yoastversion = "";
    $yoastversioncheck = "";
    $woocommerceversion = "";
    $woocommerceversioncheck = "";

    # Do only for wp-admin
    function check_needed_plugins(){
        global $yoastversion;
        global $yoastversioncheck;
        global $woocommerceversion;
        global $woocommerceversioncheck;

        if(is_admin()){
            $rootpath = $_SERVER['DOCUMENT_ROOT'];
            if(substr($rootpath, -1) != "/"){
              $rootpath.= "/";
            }
            $active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
            foreach($active_plugins as $plugin){
              if($plugin == 'wordpress-seo/wp-seo.php'){
                $plugin_data = get_plugin_data($rootpath . 'wp-content/plugins/' . $plugin);
                $yoastversion = $plugin_data['Version'];
                $yoastversioncheck = explode(".", $yoastversion);
                $yoastversioncheck = intval($yoastversion[0]);
              }
              if($plugin == 'woocommerce/woocommerce.php'){
                $plugin_data = get_plugin_data($rootpath . 'wp-content/plugins/' . $plugin);
                $woocommerceversion = $plugin_data['Version'];
                $woocommerceversioncheck = explode('.', $woocommerceversion);
                $woocommerceversioncheck = intval($woocommerceversioncheck[0] . substr($woocommerceversioncheck[1],0,1));
              }
            }
            require 'plugin-update-checker/plugin-update-checker.php';
            $myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	             'https://github.com/rentmanpublic/rentalshop/tree/appsys',
	              __FILE__,
	               'unique-plugin-or-theme-slug'
            );
        }

        # product_import.php will give errors if Woocommerce is not installed
        # register_rental_product_type will give errors if Woocommerce is not installed
        if($woocommerceversion != ""){
          include_once('product_import.php');
          add_action('init', 'register_rental_product_type');
        }else{
          if(!is_admin()){
            include_once('product_import.php');
            add_action('init', 'register_rental_product_type');
          }
        }
    }

    if (isset($_POST['set-authentication'])){
        update_option('plugin-rentman-account', $_POST['plugin-rentman-account']);
        update_option('plugin-rentman-username', $_POST['plugin-rentman-username']);
        update_option('plugin-rentman-password', dec_enc("encrypt", $_POST['plugin-rentman-password']));
    }


    # Include other PHP files
    include_once('product_availability.php');
    include_once('product_categories.php');
    include_once('product_customfields.php');
    include_once('product_media.php');
    include_once('product_pdf.php');
    include_once('product_prices.php');
    include_once('rentman_requests.php');
    include_once('rentman_project.php');
    include_once('rentman_user.php');

    # Create plugin-rentman-settings option
    if (false == get_option('')){
        add_option('plugin-rentman-settings');
    }

    # Add actions for Admin Initialization, Admin Menu, Fee Calculation,
    # Woocommerce Checkout and more to the right hooks
    add_action('init','check_needed_plugins');
    add_action('admin_init', 'register_settings');
    add_action('admin_menu', 'register_submenu');
    add_action('admin_enqueue_scripts', 'pw_load_scripts');
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
    add_filter('the_content', 'show_product_pdf', 10, 5);

    //add_filter( 'single_template', 'get_custom_post_type_template' );
    add_filter('plugin_action_links', 'add_action_links', 10, 5 );
    add_filter('woocommerce_add_to_cart_validation', 'check_available', 10, 5);
    add_filter('woocommerce_checkout_fields', 'adjust_checkout');
    add_filter('woocommerce_product_single_add_to_cart_text', 'woo_custom_cart_button_text');
    add_filter('woocommerce_update_cart_validation', 'update_amount', 10, 5);
    add_filter('woocommerce_cart_needs_shipping', '__return_true');
    add_filter('woocommerce_email_order_meta_fields', 'add_dates_to_email', 10, 3);
    add_filter('product_type_selector', 'add_rentable_product');
    add_filter('gettext', 'my_text_strings', 20, 3);

    # Register the plugin settings for a specific user
    function register_settings(){
        register_setting('plugin-rentman-settings', 'plugin-rentman-account');
        register_setting('plugin-rentman-settings', 'plugin-rentman-basictoadvanced');
        register_setting('plugin-rentman-settings', 'plugin-rentman-checkavail');
        register_setting('plugin-rentman-settings', 'plugin-rentman-checkdisc');
        register_setting('plugin-rentman-settings', 'plugin-rentman-checkstock');
        register_setting('plugin-rentman-settings', 'plugin-rentman-customfields');
        register_setting('plugin-rentman-settings', 'plugin-rentman-enddate');
        register_setting('plugin-rentman-settings', 'plugin-rentman-lasttime');
        register_setting('plugin-rentman-settings', 'plugin-rentman-password');
        register_setting('plugin-rentman-settings', 'plugin-rentman-startdate');
        register_setting('plugin-rentman-settings', 'plugin-rentmanIDs');
        register_setting('plugin-rentman-settings', 'plugin-rentman-timezone');
        register_setting('plugin-rentman-settings', 'plugin-rentman-token');
        register_setting('plugin-rentman-settings', 'plugin-rentman-username');


        # Check if the option 'plugin-rentman-basictoadvanced' is not empty
        # If empty change all the names in the db from 'plugin-###' to 'plugin-rentman-###' if they exists
        # If they exist it means that before the advanced the basic plugin was installed
        if(get_option('plugin-rentman-basictoadvanced') == ""){
            if (get_option('plugin-account', "false") != "false"){
              update_option('plugin-rentman-account', get_option('plugin-account'));
              delete_option('plugin-account');
            }

            if (get_option('plugin-checkavail', "false") != "false"){
              update_option('plugin-rentman-checkavail', get_option('plugin-checkavail'));
              delete_option('plugin-checkavail');
            }

            if (get_option('plugin-checkdisc', "false") != "false"){
              update_option('plugin-rentman-checkdisc', get_option('plugin-checkdisc'));
              delete_option('plugin-checkdisc');
            }

            if (get_option('plugin-checkstock', "false") != "false"){
              update_option('plugin-rentman-checkstock', get_option('plugin-checkstock'));
              delete_option('plugin-checkstock');
            }

            if (get_option('plugin-enddate', "false") != "false"){
              update_option('plugin-rentman-enddate', get_option('plugin-enddate'));
              delete_option('plugin-enddate');
            }

            if (get_option('plugin-lasttime', "false") != "false"){
              update_option('plugin-rentman-lasttime', get_option('plugin-lasttime'));
              delete_option('plugin-lasttime');
            }

            if (get_option('plugin-password', "false") != "false"){
              update_option('plugin-rentman-password', dec_enc("encrypt", get_option('plugin-password')));
              delete_option('plugin-password');
            }

            if (get_option('plugin-startdate', "false") != "false"){
              update_option('plugin-rentman-startdate', get_option('plugin-startdate'));
              delete_option('plugin-startdate');
            }

            if (get_option('plugin-timezone', "false") != "false"){
              update_option('plugin-rentman-timezone', get_option('plugin-timezone'));
              delete_option('plugin-timezone');
            }

            if (get_option('plugin-token', "false") != "false"){
              update_option('plugin-rentman-token', get_option('plugin-token'));
              delete_option('plugin-token');
            }

            if (get_option('plugin-username', "false") != "false"){
              update_option('plugin-rentman-username', get_option('plugin-username'));
              delete_option('plugin-username');
            }
            delete_option( 'plugin-settings' );
        }
    }

    # Register the WooCommerce submenu
    function register_submenu()
    {
        global $woocommerceversion;
        # If Woocommerce is not installed only a single Rentman-menu will show up
        # If installed the Rentman-menu-button will be a child menu item of the main WooCommerce button
        if($woocommerceversion != ""){
            add_submenu_page('woocommerce', 'Rentman', 'Rentman', 'manage_options', 'rentman-shop', 'menu_display');
          }else{
            add_menu_page('Rentman', 'Rentman', 'manage_options', 'rentman-shop', 'menu_display');
          }
    }

    # Load text domain used for translation of the plugin
    function translatePlugin()
    {
        load_plugin_textdomain('rentalshop', false, dirname(plugin_basename(__FILE__)) . '/lang');
    }

    # If current tab is empty, show the login tab
    $currenttab = "";
    # If 'Save Changes' button has been pressed for settings, show the settings tab
    if (isset($_POST['change-settings']) || isset($_POST['update-custom-fields'])){
        $currenttab = "settings";
    }

    # If 'Import Products' button has been pressed, show the import tab
    if (isset($_POST['import-rentman']) || isset($_POST['reset-rentman'])){
        $currenttab = "import";
    }

    # Display and initialize Rentman Plugin Menu in Wordpress Admin Panel
    function menu_display(){
        global $currenttab;
        global $woocommerceversion;
        global $woocommerceversioncheck;
        printf( __("<h1>Rentman Product Import - Advanced v%s</h1><hr><br>", "rentalshop"), get_plugin_data(realpath(dirname(__FILE__)) . '/rentman.php')['Version']);

        # Check for plugin updates
        $url = 'https://www.appsysit.be/rentman/checkforupdate.php';
        $fields = array(
            'language'               => get_locale(),
            'currentapiversion'      => get_plugin_data(realpath(dirname(__FILE__)) . '/rentman.php')['Version'],
            'adminurl'               => admin_url() . "update-core.php"
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        $result = curl_exec($ch);
        curl_close($ch);

        $current = "login";
        if(!empty($currenttab)) {
          $current = $currenttab;
        }
        $tabs = array(
            'login'   => __( 'Login', 'rentalshop' )
        );
        if(login_user(0) != "fail" && $woocommerceversion!="" && $woocommerceversioncheck >= 30) {
            $tabs = array(
                'login'   => __( 'Login', 'rentalshop' ),
                'settings'  => __( 'Settings', 'rentalshop' ),
                'import'  => __( 'Import', 'rentalshop' )
            );
        }

        $html = '<h2 class="nav-tab-wrapper">';
        foreach( $tabs as $tab => $name ){
            $class = ( $tab == $current ) ? 'nav-tab-active' : '';
            $html .= '<a class="nav-tab ' . $class . '" href="#' . $tab . '">' . $name . '</a>';
        }
        $html .= '</h2>';
        echo $html;
        if($current == "login"){
            echo('<div id="rentman-login">');
        }else{
            echo('<div id="rentman-login" style="display:none;">');
        } ?>

        <br><img src="https://rentman.io/img/rentman-logo.svg" alt="Rentman" height="42" width="42">
        <?php _e('<h3>Provide your Rentman 4G login credentials</h3>', 'rentalshop') ?>
        <form method="post">
            <table width="100%">
              <tr>
                <td width="1%"><strong>Rentman&nbsp;Account</strong></td>
                <td><input type="text" name="plugin-rentman-account" value="<?php echo get_option('plugin-rentman-account'); ?>"/></td>
              </tr>
              <tr>
                <td><?php _e('<strong>Username</strong>', 'rentalshop'); ?></td>
                <td><input type="text" name="plugin-rentman-username" value="<?php echo get_option('plugin-rentman-username'); ?>"/></td>
              </tr>
              <tr>
                <td><?php _e('<strong>Password</strong>', 'rentalshop'); ?></td>
                <td><input type="password" name="plugin-rentman-password" value="<?php echo dec_enc("decrypt", get_option('plugin-rentman-password')); ?>"/></td>
              </tr>
            </table>
            <?php
              $token = login_user(1); # Receive token when signing in
            ?>
            <br><br>
            <input type="hidden" name="set-authentication">
            <input type="submit" class="button button-primary" value="<?php _e('Save Changes', 'rentalshop') ?>">
            <br><br>
        </form>
        <?php
        check_compatibility();
        echo("</div>");

        # If no dates are set, they are set to the current date
        if (!isset ($_SESSION['rentman_rental_session'])){
            $today = date("Y-m-j");
            $_SESSION['rentman_rental_session'] = array(
                'from_date' => $today,
                'to_date' => $today
            );
        }

        update_option('plugin-rentman-token', $token); # Save new token in database
        if (false == get_option('plugin-rentman-lasttime')){
            $lastTime = ''; # Product Import hasn't been done before
        }else{
            $lastTime = get_option('plugin-rentman-lasttime');
        }

        # Buttons for availability check and discount
        # If 'Save Changes' button has been pressed for settings, update options
        if (isset($_POST['change-settings'])){
            update_option('plugin-rentman-checkdisc', $_POST['plugin-checkdisc']);
            update_option('plugin-rentman-checkavail', $_POST['plugin-checkavail']);
            update_option('plugin-rentman-checkstock', $_POST['plugin-checkstock']);
            update_option('plugin-rentman-timezone', $_POST['plugin-timezone']);
            //echo "<meta http-equiv='refresh' content='0'>";
        }

        $availCheck = get_option('plugin-rentman-checkavail');
        $discountCheck = get_option('plugin-rentman-checkdisc');
        $stockCheck = get_option('plugin-rentman-checkstock');
        $timezoneCheck = get_option('plugin-rentman-timezone');


        # Check if the option 'plugin-basictoadvanced' is set in db, if not then there recently was an update of the plugin and the first import with the new plugin hasn't run yet.
        # 'plugin-basictoadvanced' set to value 2 will force an update for all the products on first imported
        # When the import is completely done this value will be set to 1
        if(get_option('plugin-rentman-basictoadvanced') == "") {
            update_option('plugin-rentman-basictoadvanced', 2);
        }
        $basictoAdvanced = get_option('plugin-rentman-basictoadvanced');
        define("BASICTOADVANCED", get_option('plugin-rentman-basictoadvanced'));

        if ($availCheck == '' or $discountCheck == '' or $stockCheck == '' or $timezoneCheck == ''){
            update_option('plugin-rentman-checkdisc', 0);
            update_option('plugin-rentman-checkavail', 0);
            update_option('plugin-rentman-checkstock', 0);
            update_option('plugin-rentman-timezone', 'Europe/Berlin');
            $availCheck = 0;
            $discountCheck = 0;
            $stockCheck = 0;
            $timezoneCheck = 'Europe/Berlin';
        }
        define("TIMEZONE", $timezoneCheck);

        if($token != "fail"){
            if($current == "settings"){
                echo('<div id="rentman-settings">');
            }else{
                echo('<div id="rentman-settings" style="display:none;">');
            }
            echo("<br>"); ?>
            <form method="post"><!-- If checked, applies availability check in the shop -->
                <table width="100%">
                  <tr>
                    <td width="1%">
                      <?php _e('<strong>Check&nbsp;availability&nbsp;for&nbsp;sending</strong>', 'rentalshop'); ?>
                    </td>
                    <td>
                      <select name='plugin-checkavail'>
                          <option value="0" <?php if (get_option('plugin-rentman-checkavail') == 0){echo "selected";} _e('>No', 'rentalshop'); ?></option>
                          <option value="1" <?php if (get_option('plugin-rentman-checkavail') == 1){echo "selected";} _e('>Yes', 'rentalshop'); ?></option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <!-- If checked, specific customer discounts in Rentman are loaded and applied -->
                    <td width="1%">
                      <?php _e('<strong>Use&nbsp;discount&nbsp;from&nbsp;contact&nbsp;in&nbsp;Rentman</strong>', 'rentalshop'); ?>
                    </td>
                    <td>
                      <select name='plugin-checkdisc'>
                          <option value="0" <?php if (get_option('plugin-rentman-checkdisc') == 0){echo "selected";} _e('>No', 'rentalshop'); ?></option>
                          <option value="1" <?php if (get_option('plugin-rentman-checkdisc') == 1){echo "selected";} _e('>Yes', 'rentalshop'); ?></option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <!-- If checked, displays the stock of the materials on the product pages -->
                    <td width="1%">
                      <?php _e('<strong>Display&nbsp;stock&nbsp;on&nbsp;product&nbsp;pages</strong>', 'rentalshop'); ?>
                    </td>
                    <td>
                      <select name='plugin-checkstock'>
                          <option value="0" <?php if (get_option('plugin-rentman-checkstock') == 0){echo "selected";} _e('>No', 'rentalshop'); ?></option>
                          <option value="1" <?php if (get_option('plugin-rentman-checkstock') == 1){echo "selected";} _e('>Yes', 'rentalshop'); ?></option>
                      </select>
                    </td>
                  </tr>
                  <?php
                  $regions = array(
                      'Africa' => DateTimeZone::AFRICA,
                      'America' => DateTimeZone::AMERICA,
                      'Antarctica' => DateTimeZone::ANTARCTICA,
                      'Aisa' => DateTimeZone::ASIA,
                      'Atlantic' => DateTimeZone::ATLANTIC,
                      'Europe' => DateTimeZone::EUROPE,
                      'Indian' => DateTimeZone::INDIAN,
                      'Pacific' => DateTimeZone::PACIFIC
                  );
                  $timezones = array();
                  foreach ($regions as $name => $mask){
                      $zones = DateTimeZone::listIdentifiers($mask);
                      foreach($zones as $timezone){
                          // Lets sample the time there right now
                          $time = new DateTime(NULL, new DateTimeZone($timezone));
                          // Us dumb Americans can't handle millitary time
                          $ampm = $time->format('H') > 12 ? ' ('. $time->format('g:i a'). ')' : '';
                          // Remove region name and add a sample time
                          $timezones[$name][$timezone] = substr($timezone, strlen($name) + 1) . ' - ' . $time->format('H:i') . $ampm;
                        }
                  }
                  ?>
                  <tr>
                    <!-- If checked, displays the stock of the materials on the product pages -->
                    <td width="1%">
                      <?php _e('<strong>Select&nbsp;your&nbsp;time&nbsp;zone</strong>', 'rentalshop'); ?>
                    </td>
                    <td>
                      <select name='plugin-timezone'>
                          <?php
                          foreach($timezones as $region => $list){
                          	echo '<optgroup label="' . $region . '">';
                          	foreach($list as $timezone => $name){
                              if($timezoneCheck == $timezone){
                          		    echo '<option value="' . $timezone . '" selected>' . $name . '</option>';
                              }else{
                                  echo '<option value="' . $timezone . '">' . $name . '</option>';
                              }
                          	}
                          	echo '<optgroup>';
                          }
                          ?>
                      </select>
                    </td>
                  </tr>
                </table>

                <!-- Button that saves the changes to the settings -->
                <p>
                <?php
                if (isset($_POST['change-settings'])){
                    _e('&#x2705; Settings saved successfully!', 'rentalshop');
                    echo("<br><br>");
                }
                ?>
                <input type="hidden" name="change-settings">
                <input type="submit" class="button button-primary" value="<?php _e('Save Changes', 'rentalshop') ?>">
                </p>
            </form>
        <?php
        # If 'Save Changes' button has been pressed for custom fields, update options
        if (isset($_POST['update-custom-fields'])){
          $customFields = [];
          foreach ($_POST as $key => $value){
            if($key != "update-custom-fields" && $value != "") {
                $firstchar = "";
                if(substr($value, 0, 1) == "_"){
                  $firstchar = "_";
                }
                $value = str_replace(array('[\', \']'), '', $value);
                $value = str_replace('"', '', $value);
                $value = str_replace("'", '', $value);
                $value = str_replace("&", '', $value);
                $value = str_replace("§", '', $value);
                $value = preg_replace('/\[.*\]/U', '', $value);
                $value = preg_replace('/&(amp;)?#?[a-z0-9]+;/i', '', $value);
                $value = htmlentities($value, ENT_COMPAT, 'utf-8');
                $value = preg_replace('/&([a-z])(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig|quot|rsquo);/i', '\\1', $value );
                $value = preg_replace(array('/[^a-z0-9]/i', '/[-]+/') , '_', $value);
                $value = strtolower(trim($value, '_'));
                $value = str_replace("__", '_', $value);
                $customFields[] = [$key, $firstchar . $value];
            }
          }
          if(sizeof($customFields) > 0) {
            update_option('plugin-rentman-customfields', $customFields);
          }else{
            update_option('plugin-rentman-customfields', "");
          }
        }

        if($token != "fail"){
          $url = receive_endpoint();
          # Get all the custom fields defined in Rentman
          $message = json_encode(get_custom_fields($token), JSON_PRETTY_PRINT);
          # Send Request & Receive Response
          $received = do_request($url, $message);
          $parsed = json_decode($received, true);
          $parsed = parseResponse($parsed);

          if(sizeof($parsed['response']['items']['Customfield'])>0) {
              _e('<hr><h3>Extra input fields</h3>', 'rentalshop');
              _e("<p>Additional input fields have been found for materials. If you want to import these,<br />
              you can link them here to the WordPress database. The linked field names will be<br />
              saved in the 'wp_postmeta' table of the WordPress database in the 'meta_key' field.</p>
              <p><strong>Caution!</strong><br />
              -The field names must be unique within the table 'wp_post_meta'.<br />
              -Spaces will be replaced by a underscore.<br />
              -Special characters will be replaced.<br />
              -Additional programming will be required to make these extra input fields visible on the website.<br />
              -After first import it is best not to change the existing field names to avoid duplicate entries in the database.
              </p>", "rentalshop");
              echo('<br />');
              echo('<form method="post">');
              echo('<table width="100%">');
              echo('<tr><th align="left">Rentman</th><th>&nbsp;</th><th align="left">WordPress (wp_postmeta)</th></tr>');
              $customFieldsCompare = get_option('plugin-rentman-customfields');

              foreach ($parsed['response']['items']['Customfield'] as $customfield) {
                  if(is_array($customFieldsCompare)) {
                    foreach ($customFieldsCompare as $customfieldcompare){
                      $value="";
                      if('custom_' . $customfield['data']['id'] == $customfieldcompare[0]) {
                        $value = $customfieldcompare[1];
                        break;
                      }
                    }
                  }else{
                    $value="";
                  }
                  echo('<tr><td width="1%"><strong>' . str_replace(' ', '&nbsp;', $customfield['data']['naam']) . '</strong></td>');
                  echo('<td width="5%" align="center"><strong>-</strong></td>');
                  echo('<td><input type="text" name="custom_' . $customfield['data']['id'] . '" value="'.$value.'" /></td></tr>');
              } ?>
              </table>
              <p>
              <?php
              if(isset($_POST['update-custom-fields'])){
                  _e('&#x2705; The additional input fields have been saved successfully!', 'rentalshop');
                  echo("<br><br>");
              }
              ?>
              <input type="submit" name="update-custom-fields" class="button button-primary" value="<?php _e('Save changes', 'rentalshop') ?>">
              </p>
              </form>
              <?php
          }else{
            update_option('plugin-rentman-customfields', '');
          }
          echo("</div>");
        }

          if($current == "import"){
              echo('<div id="rentman-import">');
          }else{
              echo('<div id="rentman-import" style="display:none;">');
          }
          _e('<h3>Synchronize material from Rentman</h3>', 'rentalshop'); ?>
          <ul>
              <li><?php _e('Press the button below to check for new or updates products and transfer all<br>webshop products from your Rentman account to your WooCommerce shop.', 'rentalshop'); ?></li>
              <li><?php _e('-- Most recent check for updates: ', 'rentalshop');
                  echo '<span class="lasttime">' . $lastTime . '</span>'; ?></li>
          </ul>

          <p> <!-- Button that handles product import -->
          <form method="post">
              <input type="hidden" name="import-rentman">
              <input type="submit" class="button button-primary"
                     value="<?php _e('Update Products', 'rentalshop'); ?>">
          </form>
          <br> <!-- Button for total reset -->
          <form method="post">
              <input type="hidden" name="reset-rentman">
              <input type="submit" class="button button-primary" value="<?php _e('Reset', 'rentalshop'); ?>">
          </form>
          <br> <!-- Message that appears when you start the product import -->
          <div id="importMelding" style="display: none;"><?php _e("<h3>Starting import.. This might take a few minutes, so don't leave this page!</h3>", 'rentalshop'); ?></div>
          <p id="deleteStatus"></p>
          <p id="importStatus"></p>
          <p id="taxWarning"></p>
          </div>
        <?php
        }

        # If 'Import Products' button has been pressed, call function from product_import.php
        if (isset($_POST['import-rentman'])){
            import_products($token);
        }

        # Import Products with certain index in array (called by admin_import.js)
        if (isset($_GET['import_products'])){
            $_REQUEST = array_merge($_GET, json_decode(file_get_contents('php://input'), true));
            $prod_array = $_REQUEST['prod_array'];
            $file_array = $_REQUEST['file_array'];
            $pdf_array = $_REQUEST['pdf_array'];
            $array_index = $_REQUEST['array_index'];
            array_to_product($prod_array, $file_array, $pdf_array, (int)$array_index,$token);
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

        # Change value of basic_to_advanced from 2 to 1 when first import with plugin is done
        if (isset($_GET['basic_to_advanced'])){
            if(BASICTOADVANCED == 2){
                update_option('plugin-rentman-basictoadvanced', 1);
            }
        }

        # If 'Reset' button has been pressed, delete Rentman products and their categories
        if (isset($_POST['reset-rentman'])){
            reset_rentman();
        }
    }

    # Return the dates for the rental period from the current session
    function get_dates(){
        $today = date("Y-m-j");
        if (!isset ($_SESSION['rentman_rental_session'])){
            $_SESSION['rentman_rental_session'] = array(
                'from_date' => $today,
                'to_date' => $today
            );
        }

        if (isset($_SESSION['rentman_rental_session']['from_date']) && isset($_SESSION['rentman_rental_session']['to_date'])){
            $from_date = date("Y-m-d", strtotime(sanitize_text_field($_SESSION['rentman_rental_session']['from_date'])));
            $to_date = date("Y-m-d", strtotime(sanitize_text_field($_SESSION['rentman_rental_session']['to_date'])));
            return array("from_date" => $from_date, "to_date" => $to_date);
        }
        else{
            return false;
        }
    }

    // ------------- User Login Functions ------------- \\

    # Receive the endpoint url for use in all API requests
    function receive_endpoint(){
        $account = get_option('plugin-rentman-account');
        $url = "http://api.rentman.eu/version/index.php?account=" . $account;
        $received = do_request($url, '');
        $parsed = json_decode($received, true);
        if ($parsed['exists'] == false) {
          if($account != ""){
            return "account does not exist";
          }
        }elseif ($parsed['hasrm4database'] == false) {
          return "no rentman 4 database";
        }elseif ($parsed['version'] != 4) {
          return "rentman 4 test database";
        }
        return $parsed['endpoint'] . '/api.php';
    }

    # Main function for user login
    function login_user($showerrormsgs){
        if (completedata() == "empty"){
            $token = "fail"; # User has not filled in all required fields yet
        }
        if (completedata() == "notcomplete"){
            if($showerrormsgs) {
                echo('<br>');
                _e('&#x274c; Please fill in all fields', 'rentalshop');
            }
            return "fail";
        }

        if (completedata() == "complete"){
            $url = receive_endpoint();
            if($url == "account does not exist"){
                if($showerrormsgs) {
                    echo("<br>");
                    _e("&#x274c; This Rentman account doesn't exist!", 'rentalshop');
                }
                return "fail";
            }
            if($url == "no rentman 4 database"){
                if($showerrormsgs) {
                    echo("<br>");
                    _e("&#x274c; This account does not have a Rentman 4 database!",  'rentalshop');
                }
                return "fail";
            }
            if($url == "rentman 4 test database"){
                if($showerrormsgs) {
                    echo("<br>");
                    _e('&#x274c; This is a rentman 4 test environment!',  'rentalshop');
                }
                return "fail";
            }

            $message = json_encode(setup_login_request(), JSON_PRETTY_PRINT);

            # Do API request
            $received = do_request($url, $message);

            # Set Token (is used in other API requests)
            $parsed = json_decode($received, true);
            $token = $parsed['response']['token'];

            if ($parsed['response']['login'] == false){
                if($showerrormsgs) {
                    echo("<br>");
                    _e('&#x274c; Connection with the Rentman API failed! Did you provide the correct credentials?', 'rentalshop');
                }
                return "fail";
            } else{
                if($showerrormsgs) {
                    echo("<br>");
                    _e('&#x2705; Connection with the Rentman API was successful!', 'rentalshop');
                }
            }
        }
        return $token;
    }

    function add_action_links( $actions, $plugin_file ) {
        static $plugin;
        if (!isset($plugin)){
        	$plugin = plugin_basename(__FILE__);
        }
        if ($plugin == $plugin_file) {
            $settings = array('settings' => '<a href="admin.php?page=rentman-shop">' . __('Settings', 'General') . '</a>');
            $actions = array_merge($settings, $actions);
            return $actions;
        }
        return $actions;

    }

    # Check the compatibility with the plugin
    function check_compatibility()
    {
        global $yoastversion;
        global $yoastversioncheck;
        global $woocommerceversion;
        global $woocommerceversioncheck;
        _e('<h3>Checking for compatibility errors</h3>', 'rentalshop');

        # Check current php version
        $phpversion = explode(".", phpversion());
        $phpversion = intval($phpversion[0] . substr($phpversion[1],0,1));
        if($phpversion < 56){
          printf( __("&#x26a0; PHP version: %s. We recommend at least PHP 5.6 or higher.<br>", "rentalshop"), phpversion());
        }else{
          printf( __("&#x2705; Current version of PHP: %s<br>", "rentalshop"), phpversion());
        }

        $artDir = '/uploads/rentman/';
        $fileUrl = 'https://raw.githubusercontent.com/rentmanpublic/rentalshop/appsys/img/test.png';
        $fileUrlHtaccess = 'https://raw.githubusercontent.com/rentmanpublic/rentalshop/appsys/img/.htaccess';

        # Check the PHP time limit
        $timelimit = ini_get('max_execution_time');
        if ($timelimit < 30){
            _e('&#x26a0; Important: the PHP time limit is lower than 30 seconds! The plugin might not work correctly.<br>', 'rentalshop');
        }
        else{
            _e('&#x2705; PHP time limit is okay.<br>', 'rentalshop');
        }

        # Does Rentman image Folder exist?
        if (!file_exists(WP_CONTENT_DIR . $artDir)){
            _e('&#x2705; Folder created at <i>wp-content/uploads/rentman/</i>.<br>', 'rentalshop');
            mkdir(WP_CONTENT_DIR . $artDir); # Create one if it doesn't
        } else{
            _e('&#x2705; The Rentman folder for images exists.<br>', 'rentalshop');
        }

        # Does the copy function for images work?
        $file_name = 'test.png';
        $targetUrl = WP_CONTENT_DIR . $artDir . $file_name;
        copy($fileUrl, $targetUrl);
        $errors = error_get_last();
        if (file_exists($targetUrl)){
            _e('&#x2705; Addition of images was successful.<br>', 'rentalshop');
        } else{
            echo '<div style="color:red;">';
            _e('&#x274c; Addition of images failed.', 'rentalshop');
            echo '</div>';
            echo "&bull; Copy Error: " . $errors['type'];
            echo "<br />\n&bull; " . $errors['message'] . '<br>';
            if (!ini_get('allow_url_fopen')){ # Show possible solution if the copy function fails
                _e('&bull; <i>url_fopen()</i> is disabled in the <i>php.ini</i> file. Try to change this and check if the error has been resolved.<br>', 'rentalshop');
            }
        }
        $artDir = '/uploads/rentman/';
        $new_file_name = '.htaccess';

        # Check if images can be displayed
        $targetUrl = WP_CONTENT_DIR . $artDir . $new_file_name;
        copy($fileUrlHtaccess,$targetUrl);
        $errors = error_get_last();
        if (!file_exists($targetUrl)){
            _e('&#x26a0; Important: a .htaccess file is missing in the \'uploads/rentman/\' folder! The imported images might not be displayed correctly.<br>', 'rentalshop');
        } else{
            //_e('&#x2705; Images can be displayed.<br>', 'rentalshop');
        }

        if($woocommerceversion == "") {
          printf( __("&#x274c; The plugin 'WooCommerce' for WordPress is missing. Please <a href='%splugin-install.php?s=woocommerce&tab=search&type=term' target='_blank'>install</a> this plugin.<br>", "rentalshop"), admin_url());
        }else{
          if($woocommerceversioncheck < 30) {
            printf( __("&#x274c; The current plugin 'WooCommerce (%s)' does not meet the requirements, please <a href='%splugin-install.php?s=woocommerce&tab=search&type=term' target='_blank'>update</a>.<br>", "rentalshop"), $woocommerceversion, admin_url());
          }else{
            printf( __("&#x2705; The current plugin 'WooCommerce (%s)' meets the requirements.<br>", "rentalshop"), $woocommerceversion);
          }
        }

        if($yoastversion == "") {
          printf( __("&#x26a0; The plugin 'Yoast seo' for WordPress is missing. Please <a href='%splugin-install.php?s=yoast&tab=search&type=term' target='_blank'>install</a> this plugin.<br>", "rentalshop"), admin_url());
        }else{
          $yoastv = $yoastversion;
          if($yoastversioncheck < 6) {
            printf( __("&#x26a0; Important: Current version of 'Yoast seo' is %s. We recommended to <a href='%splugin-install.php?s=yoast&tab=search&type=term' target='_blank'>update</a>.<br>", "rentalshop"), $yoastversion, admin_url());
          }else{
            printf( __("&#x2705; The current plugin 'Yoast seo (%s)' meets the requirements.<br>", "rentalshop"), $yoastversion);
          }
        }

    }

    # Check if given login data is complete
    function completedata()
    {
        if (false == get_option('plugin-rentman-account') && false == get_option('plugin-rentman-username') && false == get_option('plugin-rentman-password')) {
            return "empty";
        }
        if (false == get_option('plugin-rentman-account') || false == get_option('plugin-rentman-username') || false == get_option('plugin-rentman-password')) {
            return "notcomplete";
        }
        return "complete";
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

    # Add admin_tabs.js for jQuery tabs
    function pw_load_scripts($hook) {
      	if( $hook != 'woocommerce_page_rentman-shop' ){
      		  return;
        }
      	wp_enqueue_script( 'custom-js', plugins_url('js/admin_tabs.js' , __FILE__));
    }

    function dec_enc($action, $string) {
        $output = false;
        $encrypt_method = "AES-256-CBC";
        $secret_key = DB_PASSWORD;
        $secret_iv = DB_USER;
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16);

        if( $action == 'encrypt' ) {
            $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
            $output = base64_encode($output);
        }
        if( $action == 'decrypt' ){
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }

        return $output;
    }
?>
