<?php // FUNCTIONS REGARDING EXPORT OF CONTACTS

    // ------------- Main User Export Function ------------- \\

    # Checks if customer from new order already exists
    # in Rentman and adds them to Rentman if not
    function export_users($order_id)
    {
        # Check for rentable products in the order
        global $wpdb;
        $order = new WC_Order($order_id);
        $rentableProduct = false;
        foreach($order->get_items() as $key => $lineItem){
            $name = $lineItem['name'];
            $product_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $name . "'" );
            $product = wc_get_product($product_id);
            if ($product->product_type == 'rentable'){
                $rentableProduct = true;
                break;
            }
        }

        # If it contains rentable products, add customer as contact to Rentman and create a new project
        if ($rentableProduct) {
            $url = receive_endpoint();
            $token = get_option('plugin-token');

            # Setup Request to send JSON
            $message = json_encode(setup_check_request($token, $order->billing_email), JSON_PRETTY_PRINT);

            # Send Request & Receive Response
            $received = do_request($url, $message);

            $parsed = json_decode($received, true);

            $contactarr = $parsed['response']['items']['Contact'];
            if (empty($contactarr)) {
                # Contact doesn't exist yet, so do a create request
                # Setup Request to send JSON
                $message = json_encode(setup_newuser_request($token, $order_id), JSON_PRETTY_PRINT);
                # Send Request & Receive Response
                $received = do_request($url, $message);
                $parsed = json_decode($received, true);
                $contact_id = current($parsed['response']['items']['Contact']);
                $fees = array();
                for ($x = 0; $x <= 2; $x++){
                    array_push($fees, 0);
                }
            } else { # Get discounts from Rentman 4G account
                $contact_id = current($parsed['response']['items']['Contact']);
                $fees = array();
                for ($x = 0; $x <= 2; $x++){
                    array_push($fees, $contact_id['data'][$x+2]);
                }
            }
            add_project($order_id, $contact_id['data'][1], $fees);
        }
    }

    // ------------- API Request Functions ------------- \\

    # Returns API request ready to be encoded in Json
    # Checks if a user already exists by their email
    function setup_check_request($token, $mail){
        # Check if contact already exists (by email)
        $object_data = array(
            "requestType" => "query",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.0.0"
            ),
            "account" => get_option('plugin-account'),
            "token" => $token,
            "itemType" => "Contact",
            "columns" => array(
                "Contact" => array(
                    "naam",
                    "id",
                    "personeelkorting",
                    "totaalkorting",
                    "transportkorting"
                )
            ),
            "query" => array("email" => $mail)
        );
        return $object_data;
    }

    # Returns API request ready to be encoded in Json
    # For sending new user data to Rentman
    function setup_newuser_request($token, $order_id){
        $order = new WC_Order($order_id);
        $object_data = array(
            "requestType" => "create",
            "apiVersion" => 1,
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.0.0"
            ),
            "account" => get_option('plugin-account'),
            "token" => $token,
            "itemType" => "Contact",
            "columns" => array(
                "Contact" => array()
            ),
            "items" => array(
                "Contact" => array(
                    "-1" => array(
                        "values" => array(
                            "id" => "-1",
                            "naam" => $order->billing_last_name,
                            "bedrijf" => $order->billing_company,
                            "email" => $order->billing_email,
                            "bezoekstraat" => $order->billing_address_1,
                            "bezoekstad" => $order->billing_city,
                            "bezoekpostcode" => $order->billing_postcode,
                            "telefoon" => $order->billing_phone
                        ),
                        "links" => array()
                    )
                )
            ),
            "parentId" => 900,
            "parenType" => "Contact"
        );
        return $object_data;
    }
?>