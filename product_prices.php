<?php
    // ------------- Main Product Price Functions ------------- \\

    # Show the customer discount and tax on the product page
    function show_discount(){
        global $post;
        $pf = new WC_Product_Factory();
        $product = $pf->get_product($post->ID);
        $tax = 1 + get_post_meta($post->ID, '_rentman_tax', true);

        # Receive endpoint and token
        $url = receive_endpoint();
        $token = get_option('plugin-token');

        if ($product->product_type == 'rentable'){
            if (get_option('plugin-checkdisc') == 1){
                global $user_email;
                get_currentuserinfo();

                # Setup request to send JSON
                $message = json_encode(setup_check_request($token, $user_email), JSON_PRETTY_PRINT);

                # Send request and receive response
                $received = do_request($url, $message);
                $parsed = json_decode($received, true);
                $parsed = parseResponse($parsed);
                $contactarr = $parsed['response']['items']['Contact'];

                if (empty($contactarr)){ # User not found, so don't add the discount
                    $taxnotice = __('Prijs inclusief BTW: ','rentalshop');
                    # Display price including tax
                    $taxprice = $product->get_price() * $tax;
                    echo '<h4>' . $taxnotice . '€' . number_format(round($taxprice, 2), 2) . '</h4>';
                    return;
                } else{ # Calculate the total customer discount
                    # Get contact and relevant materials
                    $contact = current($contactarr);
                    $contact_id = $contact['data']['id'];
                    $materials = array($product->get_sku());

                    # Setup request, send request and receive response
                    $message = json_encode(setup_discount_request($token, $contact_id, $materials), JSON_PRETTY_PRINT);
                    $received = do_request($url, $message);
                    $parsed = json_decode($received, true);
                    $parsed = parseResponse($parsed);

                    # Receive array of discounts
                    $discounts = $parsed['response']['value'];
                    $notice = __('Kortingsprijs: ','rentalshop');
                    $taxnotice = __('Prijs inclusief BTW: ','rentalshop');

                    # Display discount if there is one
                    $taxprice = $product->get_price() * $tax;
                    $discountprice = (1 - current($discounts)) * $taxprice;
                    echo '<h4>' . $taxnotice . '€' . number_format(round($taxprice, 2), 2) . '</h4>';
                    if (1 - current($discounts) == 1)
                        return;
                    echo '<h4 style="color:#8b0000">' . $notice . '€' . number_format(round($discountprice, 2), 2) . '</h4>';
                }
            } else {
                $taxnotice = __('Prijs inclusief BTW: ','rentalshop');
                # Display price including tax
                $taxprice = $product->get_price() * $tax;
                echo '<h4>' . $taxnotice . '€' . number_format(round($taxprice, 2), 2) . '</h4>';
                return;
            }
        }
    }

    # Apply staffel on the prices of the products
    function apply_staffel(){
        $staffels = array();
        $pf = new WC_Product_Factory();

        # Get token and dates
        $fromDate = strtotime(get_option('plugin-startdate'));
        $endDate = strtotime(get_option('plugin-enddate'));
        $totaldays = abs($endDate - $fromDate);
        $totaldays = ceil($totaldays / (3600*24)) + 1;
        $token = get_option('plugin-token');

        # Fill staffel array with data from the cart
        $items = WC()->cart->get_cart();
        foreach ($items as $item => $values){
            $_product = $values['data']->post;
            $product = $pf->get_product($_product->ID);
            $staffelgroup = get_staffelgroup($token, $product->get_sku());
            if ($staffelgroup == null)
                $staffels[$product->get_sku()] = '1.0';
            else
                $staffels[$product->get_sku()] = get_staffel($token, $totaldays, $staffelgroup);
        }

        # Calculate the additional fee and add it to the cart
        $fee = calculate_fee($staffels);
        apply_customer_discount($staffels);
        WC()->cart->add_fee('Staffel', $fee, true, 'standard');
    }

    # Apply tax from Rentman to product
    function apply_rentman_tax(){
        $items = WC()->cart->get_cart();
        $pf = new WC_Product_Factory();
        $taxtotal = 0;
        foreach ($items as $item => $values){
            $amount = $items[$item]["quantity"];
            $_product = $values['data']->post;
            $product = $pf->get_product($_product->ID);
            $tax = get_post_meta($_product->ID, '_rentman_tax', true);
            $price = $tax * $product->get_price() * $amount;
            $taxtotal += $price;
        }
        WC()->cart->add_fee(__('BTW','rentalshop'), $taxtotal, true, 'standard');
    }

    # Apply customer discount on the prices of the products
    function apply_customer_discount($staffels){
        # Check if discount check is enabled
        if (get_option('plugin-checkdisc') == 1){
            global $user_email;
            get_currentuserinfo();

            # Receive endpoint and token
            $url = receive_endpoint();
            $token = get_option('plugin-token');

            # Setup request to send JSON
            $message = json_encode(setup_check_request($token, $user_email), JSON_PRETTY_PRINT);

            # Send request and receive response
            $received = do_request($url, $message);
            $parsed = json_decode($received, true);
            $parsed = parseResponse($parsed);
            $contactarr = $parsed['response']['items']['Contact'];

            if (empty($contactarr)){ # User not found, so don't add the discount
                return;
            } else{ # Calculate the total customer discount
                # Get contact and relevant materials
                $contact = current($contactarr);
                $contact_id = $contact['data']['id'];
                $materials = array();
                $items = WC()->cart->get_cart();
                $pf = new WC_Product_Factory();
                foreach ($items as $item => $values){
                    $_product = $values['data']->post;
                    $product = $pf->get_product($_product->ID);
                    array_push($materials, $product->get_sku());
                }

                # Setup request, send request and receive response
                $message = json_encode(setup_discount_request($token, $contact_id, $materials), JSON_PRETTY_PRINT);
                $received = do_request($url, $message);
                $parsed = json_decode($received, true);
                $parsed = parseResponse($parsed);

                # Receive array of discounts
                $discounts = $parsed['response']['value'];
                $totaldiscount = $contact['data']['totaalkorting'];
                $discount = calculate_discount($discounts, $staffels, $totaldiscount);
                WC()->cart->add_fee(__('Klantkorting','rentalshop'), $discount, true, 'standard');
            }
        }
    }

    # Get staffel from Rentman
    function get_staffel($token, $totaldays, $staffelgroup){
        $url = receive_endpoint();

        # Setup request to send JSON
        $message = json_encode(setup_staffel_request($token, $totaldays, $staffelgroup), JSON_PRETTY_PRINT);

        # Send request and receive response
        $received = do_request($url, $message);

        $parsed = json_decode($received, true);
        $parsed = parseResponse($parsed);
        $stafObject = current($parsed['response']['items']['Staffel']);

        return $stafObject['data']['staffel'];
    }

    # Get staffelgroup of products in cart
    function get_staffelgroup($token, $product_id){
        $url = receive_endpoint();

        # Setup request to send JSON
        $message = json_encode(setup_staffelgroup_request($token, $product_id), JSON_PRETTY_PRINT);

        # Send request and receive response
        $received = do_request($url, $message);

        $parsed = json_decode($received, true);
        $parsed = parseResponse($parsed);
        $stafObject = current($parsed['response']['items']['Materiaal']);

        # If product is only for sale
        if ($stafObject['data']['verhuur'] == false)
            return null;

        return $stafObject['data']['staffelgroep'];
    }

    # Get staffel array for project export
    function get_staffels($order_id){
        global $wpdb;
        $order = new WC_Order($order_id);
        $staffels = array();

        # Get token and dates
        $fromDate = strtotime(get_option('plugin-startdate'));
        $endDate = strtotime(get_option('plugin-enddate'));
        $totaldays = abs($endDate - $fromDate);
        $totaldays = ceil($totaldays / (3600*24)) + 1;
        $token = get_option('plugin-token');

        # Get staffel data for all items
        foreach($order->get_items() as $key => $lineItem){
            $name = $lineItem['name'];
            $product_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_title = '" . $name . "'");
            $product = wc_get_product($product_id);
            # Receive the right staffel depending on the staffelgroup
            $staffelgroup = get_staffelgroup($token, $product->get_sku());
            if ($staffelgroup == null)
                $staffels[$product->get_sku()] = '1,0';
            else
                $staffels[$product->get_sku()] = get_staffel($token, $totaldays, $staffelgroup);
        }
        return $staffels;
    }

    # Get discount array for project export
    function get_all_discounts($order_id, $contact_id){
        global $wpdb;
        $order = new WC_Order($order_id);
        $materials = array();
        $pf = new WC_Product_Factory();

        # Receive endpoint and token
        $url = receive_endpoint();
        $token = get_option('plugin-token');

        # Create array with all materials from the order
        foreach($order->get_items() as $key => $lineItem){
            $name = $lineItem['name'];
            $product_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_title = '" . $name . "'");
            $product = $pf->get_product($product_id);
            array_push($materials, $product->get_sku());
        }

        # Setup request, send request and receive response
        $message = json_encode(setup_discount_request($token, $contact_id, $materials), JSON_PRETTY_PRINT);
        $received = do_request($url, $message);
        $parsed = json_decode($received, true);
        $parsed = parseResponse($parsed);

        # Receive array of discounts
        $discounts = $parsed['response']['value'];
        return $discounts;
    }

    # Calculate the total staffel fee of the shopping cart
    function calculate_fee($staffels){
        $pf = new WC_Product_Factory();
        $totalprice = 0;
        $items = WC()->cart->get_cart();
        foreach ($items as $item => $values){
            $_product = $values['data']->post;
            $amount = $items[$item]["quantity"];
            $product = $pf->get_product($_product->ID);
            $staffel = $staffels[$product->get_sku()];
            $tax = 1 + get_post_meta($_product->ID, '_rentman_tax', true);
            $price = $tax * $product->get_price();
            $carttotals = $price * $amount;
            $staffelprice = $carttotals * $staffel;
            $totalprice += $staffelprice - $carttotals;
        }
        return $totalprice;
    }

    # Calculate the total customer discount of the shopping cart
    function calculate_discount($discounts, $staffels, $totaldisc){
        $pf = new WC_Product_Factory();
        $totalprice = 0;
        $items = WC()->cart->get_cart();
        foreach ($items as $item => $values){
            $_product = $values['data']->post;
            $amount = $items[$item]["quantity"];
            $product = $pf->get_product($_product->ID);
            $discount = $discounts[$product->get_sku()];
            $tax = 1 + get_post_meta($_product->ID, '_rentman_tax', true);
            $price = $tax * $product->get_price();
            $carttotals = $price * $staffels[$product->get_sku()] * $amount;
            $percentage = 1 - $discount;
            $discountprice = $carttotals * $percentage;
            $extradiscount = $discountprice * $totaldisc;
            $totalprice += $carttotals - $discountprice + $extradiscount;
        }
        return $totalprice*(-1);
    }

    // ------------- API Request ------------- \\

    # Returns API request ready to be encoded in Json
    # For getting staffel by staffelgroup
    function setup_staffel_request($token, $totaldays, $staffelgroup){
        $object_data = array(
            "requestType" => "query",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.3.3"
            ),
            "account" => get_option('plugin-account'),
            "token" => $token,
            "itemType" => "Staffel",
            "columns" => array(
                "Staffel" => array(
                    "id",
                    "displayname",
                    "staffel",
                    "staffelgroep",
                    "van",
                    "tot"
                )
            ),
            "query" => array(
                "conditions" => array(
                    array(
                        "key" => "staffelgroep",
                        "value" => $staffelgroup
                    ),
                    array(
                        "key" => "van",
                        "value" => $totaldays,
                        "comparator" => "<="
                    ),
                    array(
                        "key" => "tot",
                        "value" => $totaldays,
                        "comparator" => ">="
                    )
                ))
        );
        return $object_data;
    }

    # Returns API request ready to be encoded in Json
    # For getting staffelgroups
    function setup_staffelgroup_request($token, $product_id){
        $object_data = array(
            "requestType" => "query",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.3.3"
            ),
            "account" => get_option('plugin-account'),
            "token" => $token,
            "itemType" => "Materiaal",
            "columns" => array(
                "Materiaal" => array(
                    "naam",
                    "verhuurprijs",
                    "staffelgroep",
                    "verhuur"
                )
            ),
            "query" => array("id" => $product_id)
        );
        return $object_data;
    }

    # Setup API request that returns the fees of products
    function setup_discount_request($token, $contact_id, $materials){
        $object_data = array(
            "requestType" => "modulefunction",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.3.3"
            ),
            "account" => get_option('plugin-account'),
            "token" => $token,
            "module" => "Webshop",
            "parameters" => array(
                "contact" => $contact_id,
                "materialen" => $materials
            ),
            "method" => "calculateDiscount"
        );
        return $object_data;
    }
?>