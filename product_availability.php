<?php
    // ------------- Adding the date fields for the rental period ------------- \\

    # Adds date fields to 'Rentable' products in the store
    function add_custom_field(){
        global $post;
        $pf = new WC_Product_Factory();
        $product = $pf->get_product($post->ID);
        if ($product->product_type == 'rentable'){
            $rentableProduct = false;
            # Checks if there already is a 'Rentable' product in the shopping cart
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item){
                $product = $cart_item['data'];
                if ($product->product_type == 'rentable'){
                    $rentableProduct = true;
                    break;
                }
            }

            echo '<div class="rentman-fields">';
            # If there isn't, display the date input fields
            if ($rentableProduct == false){
                $dates = get_dates();
                $fromDate = $dates['from_date'];
                $toDate = $dates['to_date'];
                $today = date("Y-m-d");
                # Check if the 'from' date is earlier than the 'to' date
                if (strtotime($fromDate) < strtotime($today))
                    $fromDate = $today;
                if (strtotime($toDate) < strtotime($today))
                    $toDate = $today;
                ?>
                <!-- actual HTML code for the date input fields -->
                <?php _e('Van:', 'rentalshop');?>
                <input type="date" name="start-date" onchange="quickCheck()" value="<?php echo $fromDate;?>" min="<?php echo $today;?>">
                <?php _e('Tot:', 'rentalshop');?>
                <input type="date" name="end-date" onchange="quickCheck()" value="<?php echo $toDate;?>" min="<?php echo $today;?>">
                <p><?php _e('Let op: je kan de datums voor ander materiaal pas in de winkelwagen weer wijzigen!', 'rentalshop');?></p>
                <?php
            }
            else{ # Else, display the dates from the products in your shopping cart
                $dates = get_dates();
                ?>
                <?php _e('<h3>Geselecteerde datums: </h3> <p><b>Van </b>', 'rentalshop'); echo $dates['from_date']; _e('<b> tot </b>', 'rentalshop'); echo $dates['to_date'];?></p>
                <?php
            }
            # Only show the availability messages when 'check availability for sending' is allowed
            if (get_option('plugin-checkavail') == 1){
                echo '<p class="availLog"></p>';
            } else{
                echo '<p class="availLog" hidden></p>';
            }
            echo '</div>';
        }
    }

    # Also adds date fields to the checkout screen
    function add_date_checkout(){
        $rentableProduct = false;
        $today = date("Y-m-d");
        # Again check if the shopping cart contains any 'Rentable' products
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item){
            $product = $cart_item['data'];
            if ($product->product_type == 'rentable'){
                $rentableProduct = true;
                break;
            }
        }
        # If it does, add the date fields
        if ($rentableProduct){
            if (apply_filters('rentman/show_cart_dates', true)) {
                ?><p>
                <?php _e('<h2>VERHUURPERIODE</h2>','rentalshop');
                $dates = get_dates();
                $startdate = $dates['from_date'];
                $enddate = $dates['to_date'];
                $sdate =& $startdate;
                $edate =& $enddate;
                ?>
                <form method="post">
                <?php _e('Van:', 'rentalshop');?>
                <input type="date" name="start-date" value="<?php echo $startdate;?>" min="<?php echo $today;?>">
                <?php _e('Tot:', 'rentalshop');?>
                <input type="date" name="end-date" value="<?php echo $enddate;?>" min="<?php echo $today;?>"><br>

                <!-- Update Button --></p>
                <input type="hidden" name="rm-update-dates">
                <input type="submit" class="button button-primary" value="<?php _e('Update Huurperiode', 'rentalshop');?>">
                <input type="hidden" name="backup-start" value="<?php echo $sdate;?>">
                <input type="hidden" name="backup-end" value="<?php echo $edate;?>">
                </form>
                <?php
            }

            # If 'Update Dates' button has been pressed, call update_dates function
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

        if ($product->product_type == 'rentable'){
            return __('Reserveer', 'rentalshop');
        }

        return __('Aan winkelmandje toevoegen', 'rentalshop');
    }

    # Adds a template for 'Rentable' products
    function add_to_cart_template(){
        global $post;
        $pf = new WC_Product_Factory();

        $product = $pf->get_product($post->ID);

        if ($product->product_type == 'rentable')
        {
            do_action('woocommerce_before_add_to_cart_form');?>

            <form class="cart rentman-extra-margin" method="post" enctype='multipart/form-data'>
                <?php do_action('woocommerce_before_add_to_cart_button');?>

                <?php
                if (!$product->is_sold_individually())
                    woocommerce_quantity_input(array(
                        'min_value' => apply_filters('woocommerce_quantity_input_min', 1, $product),
                        'max_value' => apply_filters('woocommerce_quantity_input_max', $product->backorders_allowed() ? '' : $product->get_stock_quantity(), $product)
                    ));
                ?>

                <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product->id);?>" />

                <button type="submit" class="single_add_to_cart_button button alt"><?php echo $product->single_add_to_cart_text();?></button>

                <?php do_action('woocommerce_after_add_to_cart_button');?>
            </form>

            <?php do_action('woocommerce_after_add_to_cart_form');
        }
    }

    // ------------- API Request Functions ------------- \\

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
        } # Only update the dates when all materials are available in the new time period
        if ($checkergroup == false){
            $_SESSION['rentman_rental_session']['from_date'] = $_POST['backup-start'];
            $_SESSION['rentman_rental_session']['to_date'] = $_POST['backup-end'];
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

        # Adjust the ending date to 00:00 on the following day
        $dates = get_dates();
        $enddate = $dates['to_date'];
        $enddate = date("Y-m-j", strtotime("+1 day", strtotime($enddate)));

        # Register and localize the availability script
        wp_register_script('admin_availability', plugins_url('js/admin_available.js', __FILE__ ));
        wp_localize_script('admin_availability', 'startDate', $dates['from_date']);
        wp_localize_script('admin_availability', 'endDate', $enddate);
        wp_localize_script('admin_availability', 'endPoint', receive_endpoint());
        wp_localize_script('admin_availability', 'rm_account', get_option('plugin-account'));
        wp_localize_script('admin_availability', 'rm_token', get_option('plugin-token'));
        wp_localize_script('admin_availability', 'cart_amount', (string)$quantity);
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
            $dates = get_dates();
            $startDate = $dates['from_date'];
            $endDate = $dates['to_date'];
        }

        # Only apply availability check on products that were
        # imported from Rentman
        if ($product->product_type == 'rentable'){
            $_SESSION['rentman_rental_session']['from_date'] = $startDate;
            $_SESSION['rentman_rental_session']['to_date'] = $endDate;
            $dates = get_dates();
            $sdate = $dates['from_date'];
            $edate = $dates['to_date'];
            # Check if any of the input dates are wrong
            if ($sdate == '' or $edate == '' or (strtotime($edate) < strtotime($sdate))){
                $passed = false;
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
                    $parsed = parseResponse($parsed);

                    # Get values from parsed response
                    $maxconfirmed = $parsed['response']['value']['maxconfirmed'];
                    $maxoption = $parsed['response']['value']['maxoption'];

                    $residual = $quantity + $maxconfirmed; # Total amount of available items
                    $possible = $maxoption*(-1); # Amount of items that are definitely available

                    # ~~ The actual Availability Check
                    # Comparing values of 'maxconfirmed' and 'maxoption'
                    if ($maxconfirmed < 0){ # Products are definitely not available
                        $passed = false;
                        $notice = __('Er zijn slechts ','rentalshop') . $residual . ' ' . $product->get_title() . __(' beschikbaar in die tijdsperiode.','rentalshop');
                        wc_add_notice($notice, 'error');
                    } else if ($maxconfirmed >= 0 and $maxoption < 0){ # Products might be available, depending on confirmation of other orders
                        $notice = __('Let op: ','rentalshop') . $possible . __(' van de ','rentalshop') . $quantity . ' ' . $product->get_title() . __(' zijn misschien niet beschikbaar in die tijdsperiode..','rentalshop');
                        wc_add_notice($notice, 'error');
                    } else{ # Products are available and are added to the cart
                        $notice = __('Uw geselecteerde aantal ','rentalshop') . $product->get_title() . __(' is beschikbaar in die tijdsperiode!','rentalshop');
                        wc_add_notice($notice, 'success');
                    }
                }
            }
        }
        return $passed;
    }
?>