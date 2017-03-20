<?php // FUNCTIONS REGARDING THE AVAILABILITY CHECK OF PRODUCTS

    // ------------- Adding the date fields ------------- \\

    # Adds date fields to 'Rentable' products in the store
    function add_custom_field(){
        global $post;
        $pf = new WC_Product_Factory();
        $product = $pf->get_product($post->ID);
        if ($product->product_type == 'rentable'){
            $rentableProduct = false;
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item){
                $product = $cart_item['data'];
                if ($product->product_type == 'rentable'){
                    $rentableProduct = true;
                    break;
                }
            }
            if ($rentableProduct == false){
                # If shopping cart is empty, display the date input fields
                $fromDate = get_option('plugin-startdate');
                $toDate = get_option('plugin-enddate');
                $today = date("Y-m-d");
                if (strtotime($fromDate) < strtotime($today))
                    $fromDate = $today;
                if (strtotime($toDate) < strtotime($today))
                    $toDate = $today;
                ?>
                <?php _e('Van:','rentalshop')?>
                <input type="date" name="start-date" onchange="quickCheck()" value="<?php echo $fromDate;?>" min="<?php echo $today;?>">
                <?php _e('Tot:','rentalshop')?>
                <input type="date" name="end-date" onchange="quickCheck()" value="<?php echo $toDate;?>" min="<?php echo $today;?>">
                <p><?php _e('Let op: je kan de datums voor ander materiaal pas in de winkelwagen weer wijzigen!', 'rentalshop')?></p>
                <?php
            }
            else{ # Else, display the dates from the products in your shopping cart
                ?>
                <?php _e('<h3>Geselecteerde datums: </h3> <p><b>Van </b>','rentalshop'); echo get_option('plugin-startdate'); _e('<b> tot </b>','rentalshop'); echo get_option('plugin-enddate'); ?></p>
                <?php
            }
            if (get_option('plugin-checkavail') == 1){
                echo '<p class="availLog"></p>';
            } else {
                echo '<p class="availLog" hidden></p>';
            }
        }
    }

    # Adds date fields to the checkout screen
    function add_date_checkout(){
        $rentableProduct = false;
        $today = date("Y-m-d");
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item){
            $product = $cart_item['data'];
            if ($product->product_type == 'rentable'){
                $rentableProduct = true;
                break;
            }
        }
        if ($rentableProduct){
            ?><p>
            <?php _e('<h2>VERHUURPERIODE</h2>','rentalshop');
            $startdate = get_option('plugin-startdate');
            $enddate = get_option('plugin-enddate');
            $sdate =& $startdate;
            $edate =& $enddate;
            ?>
            <form method="post">
            <?php _e('Van:','rentalshop')?>
            <input type="date" name="start-date" value="<?php echo $startdate?>" min="<?php echo $today;?>">
            <?php _e('Tot:','rentalshop')?>
            <input type="date" name="end-date" value="<?php echo $enddate;?>" min="<?php echo $today;?>"><br>

            <!-- Update Button --></p>
            <input type="hidden" name="rm-update-dates">
            <input type="submit" class="button button-primary" value="<?php _e('Update Huurperiode','rentalshop')?>">
            <input type="hidden" name="backup-start" value="<?php echo $sdate?>">
            <input type="hidden" name="backup-end" value="<?php echo $edate?>">
            </form>
            <?php

            # If 'Import Products' button has been pressed, call import_products function
            if (isset($_POST['rm-update-dates'])){
                update_dates();
            }
        }
    }

    // ------------- Template Changes ------------- \\

    # Changes text of the 'add to cart' button
    function woo_custom_cart_button_text(){
        global $post;
        $pf = new WC_Product_Factory();

        $product = $pf->get_product($post->ID);

        if ($product->product_type == 'rentable') {
            return __('Reserveer', 'rentalshop');
        }

        return __('Aan winkelmandje toevoegen', 'rentalshop');
    }

    # Adds a template for rentable products
    function add_to_cart_template(){
        global $post;
        $pf = new WC_Product_Factory();

        $product = $pf->get_product($post->ID);

        if ($product->product_type == 'rentable')
        {
            do_action( 'woocommerce_before_add_to_cart_form' ); ?>

            <form class="cart rentman-extra-margin" method="post" enctype='multipart/form-data'>
                <?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

                <?php
                if ( ! $product->is_sold_individually() )
                    woocommerce_quantity_input( array(
                        'min_value' => apply_filters( 'woocommerce_quantity_input_min', 1, $product ),
                        'max_value' => apply_filters( 'woocommerce_quantity_input_max', $product->backorders_allowed() ? '' : $product->get_stock_quantity(), $product )
                    ) );
                ?>

                <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->id ); ?>" />

                <button type="submit" class="single_add_to_cart_button button alt"><?php echo $product->single_add_to_cart_text(); ?></button>

                <?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
            </form>

            <?php do_action( 'woocommerce_after_add_to_cart_form' );
        }
    }

    // ------------- API Request Functions ------------- \\

    # Setup API request that checks the availability of the product
    function available_request($token, $identifier, $quantity){
        $enddate = get_option('plugin-enddate');
        $enddate = date("Y-m-j", strtotime("+1 day", strtotime($enddate)));
        $object_data = array(
            "requestType" => "modulefunction",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.1.3"
            ),
            "account" => get_option('plugin-account'),
            "token" => $token,
            "module" => "Availability",
            "parameters" => array(
                "van" => get_option('plugin-startdate'),
                "tot" => $enddate,
                "materiaal" => $identifier,
                "aantal" => $quantity
            ),
            "method" => "is_available"
        );
        return $object_data;
    }

    # Apply the check_available function on updated products in the cart
    function update_amount($passed, $cart_item_key, $values, $quantity){
        $product = $values['data'];
        return check_available($passed, $product->id, $quantity, 'from_CART');
    }

    # Apply availability check for each item in the cart for the new dates
    function update_dates(){
        $checkergroup = true;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item){
            $product = $cart_item['data'];
            if ($product->product_type == 'rentable'){
                $checkergroup = check_available($checkergroup, $product->id, $cart_item['']);
                if ($checkergroup == false)
                    break;
            }
        } # Updates the dates when all materials are available in the new time period
        if ($checkergroup == false){
            update_option('plugin-startdate', $_POST['backup-start']);
            update_option('plugin-enddate', $_POST['backup-end']);
        }

        echo "<meta http-equiv='refresh' content='0'>";
    }

    # Set the availability functions
    function set_functions(){
        # Check if product is already in the cart
        global $product;
        $quantity = 0;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item){
            $cartproduct = $cart_item['data'];
            if ($cartproduct->get_title() == $product->get_title()){
                $quantity += $cart_item['quantity'];
                break;
            }
        }
        # Adjust the ending date
        $enddate = get_option('plugin-enddate');
        $enddate = date("Y-m-j", strtotime("+1 day", strtotime($enddate)));

        # Add the file containing the availability script
        wp_register_script('admin_availability', plugins_url('js/admin_available.js', __FILE__ ));
        wp_localize_script('admin_availability', 'startDate', get_option('plugin-startdate'));
        wp_localize_script('admin_availability', 'endDate', $enddate);
        wp_localize_script('admin_availability', 'endPoint', receive_endpoint());
        wp_localize_script('admin_availability', 'rm_account', get_option('plugin-account'));
        wp_localize_script('admin_availability', 'rm_token', get_option('plugin-token'));
        wp_localize_script('admin_availability', 'cart_amount', $quantity);
        wp_localize_script('admin_availability', 'unavailable', __("Product is niet beschikbaar!", "rentalshop"));
        wp_localize_script('admin_availability', 'maybe', __("Product is misschien niet beschikbaar!", "rentalshop"));
        wp_localize_script('admin_availability', 'available', __("Product is beschikbaar!", "rentalshop"));
        wp_enqueue_script('admin_availability');
    }

    # Main function for the availability check and relevant API requests
    function check_available($passed, $product_id, $quantity, $variation_id = '', $variations= '')
    {
        # Get the product and chosen dates
        $pf = new WC_Product_Factory();
        $product = $pf->get_product($product_id);
        $startDate = $_POST['start-date'];
        $endDate = $_POST['end-date'];
        if ($startDate == '' or $endDate == ''){
            $startDate = get_option('plugin-startdate');
            $endDate = get_option('plugin-enddate');
        }

        # Only apply availability check on products that were
        # imported from Rentman
        if ($product->product_type == 'rentable') {
            update_option('plugin-startdate', $startDate);
            update_option('plugin-enddate', $endDate);
            $sdate = get_option('plugin-startdate');
            $edate = get_option('plugin-enddate');
            if ($sdate == '' or $edate == '' or (strtotime($edate) < strtotime($sdate))){
                $passed = false; # Dates from input are wrong
                wc_add_notice(__('Er ging iets mis.. Kloppen de datums wel?', 'rentalshop'), 'error');
            } else{
                # Continue with the check if 'Check availability for sending' is set to yes
                if (get_option('plugin-checkavail') == 1){
                    $url = receive_endpoint();
                    $token = get_option('plugin-token');

                    # Check if the item is already in the cart and adjust
                    # the input quantity accordingly
                    if($variation_id != 'from_CART'){
                        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item){
                            $cartproduct = $cart_item['data'];
                            if ($cartproduct->get_title() == $product->get_title()){
                                $quantity += $cart_item['quantity'];
                                break;
                            }
                        }
                    }

                    # Setup Request to send JSON
                    $message = json_encode(available_request($token, $product->get_sku(), $quantity), JSON_PRETTY_PRINT);

                    # Send Request & Receive Response
                    $received = do_request($url, $message);
                    $parsed = json_decode($received, true);

                    # Get values from parsed response
                    $maxconfirmed = $parsed['response']['value']['maxconfirmed'];
                    $maxoption = $parsed['response']['value']['maxoption'];

                    $residual = $quantity + $maxconfirmed; # Total amount of available items
                    $possible = $maxoption*(-1); # Amount of items that are definitely available

                    # ~~ Availability Check
                    # Comparing values of 'maxconfirmed' and 'maxoption'
                    if ($maxconfirmed < 0) { # Products are definitely not available
                        $passed = false;
                        $notice = __('Er zijn slechts ','rentalshop') . $residual . ' ' . $product->get_title() . __(' beschikbaar in die tijdsperiode.','rentalshop');
                        wc_add_notice($notice, 'error');
                    } else if ($maxconfirmed >= 0 and $maxoption < 0) { # Products might be available
                        $notice = __('Let op: ','rentalshop') . $possible . __(' van de ','rentalshop') . $quantity . ' ' . $product->get_title() . __(' zijn misschien niet beschikbaar in die tijdsperiode..','rentalshop');
                        wc_add_notice($notice, 'error');
                    } else { # Products are available and are added to the cart
                        $notice = __('Uw geselecteerde aantal ','rentalshop') . $product->get_title() . __(' is beschikbaar in die tijdsperiode!','rentalshop');
                        wc_add_notice($notice, 'success');
                    }
                }
            }
        }
        return $passed;
    }
?>