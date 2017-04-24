<?php
    // ------------- Main User Export Function ------------- \\

    # Checks if customer from new order already exists
    # in Rentman and adds them to Rentman if not
    function export_users($order_id){
        # Check for rentable products in the order
        global $wpdb;
        $order = new WC_Order($order_id);
        $rentableProduct = false;
        foreach($order->get_items() as $key => $lineItem){
            $name = $lineItem['name'];
            $product_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_title = '" . $name . "'");
            if (get_post_meta($product_id, 'rentman_imported', true) == true){
                $rentableProduct = true;
                break;
            }
        }

        # If it contains Rentman products, add customer as contact to Rentman and create a new project
        # Check if alternative shipping address has been filled in and create a separate contact
        if ($rentableProduct){
            $url = receive_endpoint();
            $token = get_option('plugin-token');
            $billing = $order->billing_address_1;
            $shipping = $order->shipping_address_1;

            # Setup Request to send JSON
            $message = json_encode(setup_check_request($token, $order->billing_email), JSON_PRETTY_PRINT);

            # Send Request & Receive Response
            $received = do_request($url, $message);

            $parsed = json_decode($received, true);
            $parsed = parseResponse($parsed);

            $contactarr = $parsed['response']['items']['Contact'];
            if (empty($contactarr)){
                # Contact doesn't exist yet, so do a create request
                # Setup Request to send JSON
                $message = json_encode(setup_newuser_request($token, $order_id), JSON_PRETTY_PRINT);

                # Send Request & Receive Response
                $received = do_request($url, $message);
                $parsed = json_decode($received, true);
                $parsed = parseResponse($parsed);
                $contact_id = current($parsed['response']['items']['Contact']);
                $fees = array();
                for ($x = 0; $x <= 2; $x++){
                    array_push($fees, 0);
                }
            } else{ # Get discounts from Rentman 4G account
                $contact_id = current($parsed['response']['items']['Contact']);
                $fees = array();
                array_push($fees, $contact_id['data']['personeelkorting']);
                array_push($fees, $contact_id['data']['totaalkorting']);
                array_push($fees, $contact_id['data']['transportkorting']);
            }

            if ($billing != $shipping){ # Get Rentman Contact for location
                # Setup Request to send JSON
                $message = json_encode(setup_location_request($token, $order->shipping_address_1), JSON_PRETTY_PRINT);

                # Send Request & Receive Response
                $received = do_request($url, $message);
                $parsed = json_decode($received, true);
                $parsed = parseResponse($parsed);

                $contactarr = $parsed['response']['items']['Contact'];
                if (empty($contactarr)){
                    # Contact doesn't exist yet, so do a create request
                    # Setup Request to send JSON
                    $message = json_encode(setup_newlocation_request($token, $order_id), JSON_PRETTY_PRINT);
                    # Send Request & Receive Response
                    $received = do_request($url, $message);
                    $parsed = json_decode($received, true);
                    $parsed = parseResponse($parsed);
                    $transport_id = current($parsed['response']['items']['Contact']);
                } else{
                    $transport_id = current($parsed['response']['items']['Contact']);
                }
            }
            else # Billing and shipping addresses are exactly the same
                $transport_id = $contact_id;

            add_project($order_id, $contact_id['data']['id'], $transport_id['data']['id'], $fees);
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
                "version" => "4.4.1"
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
    # Checks if a location already exists by their address
    function setup_location_request($token, $address){
        # Check if contact already exists (by address)
        $object_data = array(
            "requestType" => "query",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.4.1"
            ),
            "account" => get_option('plugin-account'),
            "token" => $token,
            "itemType" => "Contact",
            "columns" => array(
                "Contact" => array(
                    "naam",
                    "id"
                )
            ),
            "query" => array("bezoekstraat" => $address)
        );
        return $object_data;
    }

    # Returns API request ready to be encoded in Json
    # For sending new user data to Rentman
    function setup_newuser_request($token, $order_id){
        $order = new WC_Order($order_id);
        $company = $order->billing_company;
        $attachperson = array();
        $attached = false;

        # Check if the new contact is a company or not
        if ($company == ''){
            $type = "particulier";
            $firstname = $order->billing_first_name;
            $lastname = $order->billing_last_name;
        } else{
            $type = "bedrijf";
            $firstname = $order->billing_first_name;
            $lastname = $order->billing_last_name;
            if ($firstname != '' && $lastname != ''){
                $attachperson = array("Person" => array(-2));
                $attached = true;
                $firstname = '';
                $lastname = '';
            }
        }

        # Setup of the request
        $object_data = array(
            "requestType" => "create",
            "apiVersion" => 1,
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.4.1"
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
                            "voornaam" => $firstname,
                            "naam" => $lastname,
                            "bedrijf" => $order->billing_company,
                            "email" => $order->billing_email,
                            "bezoekstraat" => $order->billing_address_1,
                            "bezoekstad" => $order->billing_city,
                            "bezoekpostcode" => $order->billing_postcode,
                            "factuurstraat" => $order->billing_address_1,
                            "factuurstad" => $order->billing_city,
                            "factuurpostcode" => $order->billing_postcode,
                            "poststraat" => $order->billing_address_1,
                            "poststad" => $order->billing_city,
                            "postpostcode" => $order->billing_postcode,
                            "telefoon" => $order->billing_phone,
                            "type" => $type
                        ),
                        "links" => $attachperson
                    )
                ),
                "Person" => array()
            ),
            "parentId" => 900,
            "parenType" => "Contact"
        );

        # Attach the Person data for companies where the names have also
        # been filled in
        if ($attached){
            $person = array(
                "-2" => array(
                    "values" => array(
                        "id" => -2,
                        "voornaam" => $order->billing_first_name,
                        "achternaam" => $order->billing_last_name,
                        "postcode" => $order->billing_postcode,
                        "stad" => $order->billing_city,
                        "straat" => $order->billing_address_1,
                        "telefoon" => $order->billing_phone,
                        "email" => $order->billing_email
                    )
                )
            );
            $object_data['items']['Person'] = $person;
        } else { # Remove the Person data from the request if not
            unset($object_data['items']['Person']);
        }
        return $object_data;
    }

    # Returns API request ready to be encoded in Json
    # For sending new location data as a user to Rentman
    function setup_newlocation_request($token, $order_id){
        $order = new WC_Order($order_id);
        $company = $order->shipping_company;
        $attachperson = array();
        $attached = false;

        # Check if the new contact is a company or not
        if ($company == ''){
            $type = "particulier";
            $firstname = $order->shipping_first_name;
            $lastname = $order->shipping_last_name;
        } else{
            $type = "bedrijf";
            $firstname = $order->shipping_first_name;
            $lastname = $order->shipping_last_name;
            if ($firstname != '' && $lastname != ''){
                $attachperson = array("Person" => array(-2));
                $attached = true;
                $firstname = '';
                $lastname = '';
            }
        }

        # Setup of the request
        $object_data = array(
            "requestType" => "create",
            "apiVersion" => 1,
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.4.1"
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
                            "voornaam" => $firstname,
                            "naam" => $lastname,
                            "bedrijf" => $order->shipping_company,
                            "email" => $order->billing_email,
                            "bezoekstraat" => $order->shipping_address_1,
                            "bezoekstad" => $order->shipping_city,
                            "bezoekpostcode" => $order->shipping_postcode,
                            "factuurstraat" => $order->shipping_address_1,
                            "factuurstad" => $order->shipping_city,
                            "factuurpostcode" => $order->shipping_postcode,
                            "poststraat" => $order->shipping_address_1,
                            "poststad" => $order->shipping_city,
                            "postpostcode" => $order->shipping_postcode,
                            "telefoon" => $order->billing_phone,
                            "type" => $type
                        ),
                        "links" => $attachperson
                    )
                ),
                "Person" => array()
            ),
            "parentId" => 900,
            "parenType" => "Contact"
        );

        # Attach the Person data for companies where the names have also been filled in
        if ($attached){
            $person = array(
                "-2" => array(
                    "values" => array(
                        "id" => -2,
                        "voornaam" => $order->shipping_first_name,
                        "achternaam" => $order->shipping_last_name,
                        "postcode" => $order->shipping_postcode,
                        "stad" => $order->shipping_city,
                        "straat" => $order->shipping_address_1,
                        "telefoon" => $order->billing_phone,
                        "email" => $order->billing_email
                    )
                )
            );
            $object_data['items']['Person'] = $person;
        } else{ # Remove the Person data from the request
            unset($object_data['items']['Person']);
        }

        return $object_data;
    }
?>