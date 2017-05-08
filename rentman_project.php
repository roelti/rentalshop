<?php
    // ------------- API Request Functions ------------- \\

    # Handles API Request for project creation
    function add_project($order_id, $contact_id, $transport_id, $fees, $contact_person, $location_contact){
        $url = receive_endpoint();
        $token = get_option('plugin-token');

        # Setup New Project Request to send JSON
        $message = json_encode(setup_newproject_request($token, $order_id, $contact_id, $transport_id, $fees, $contact_person, $location_contact), JSON_PRETTY_PRINT);
        # Send Request & Receive Response
        do_request($url, $message);
    }

    # Returns API request ready to be encoded in Json
    # Used for sending new project data to Rentman
    # Includes contact, relevant materials & rent dates
    function setup_newproject_request($token, $order_id, $contact_id, $transport_id, $fees, $contact_person, $location_contact){
        # Get Order data and rent dates
        $order = new WC_Order($order_id);
        $comp = $order->billing_company;
        $proj = $comp . " Project";
        $notitie = $order->customer_note;
        $ext_ref = $order->billing_reference;

        $startdate = get_option('plugin-startdate');
        $enddate = get_option('plugin-enddate');

        $enddate = date("Y-m-j", strtotime("+1 day", strtotime($enddate)));
        $startdate = $startdate . 'T00:00:00';
        $enddate = $enddate . 'T00:00:00';

        # Get List of Materials and create arrays by using
        # that list for the json request
        $shippingbtw = ($order->order_shipping) / 1.21;
        $materials = get_material_array($order_id);
        $materialsize = sizeof($materials);

        # Do not add any rental dates to the request when only non-rentable products
        # have been purchased in the order
        $rentableProduct = false;
        foreach($order->get_items() as $key => $lineItem){
            $product_id = $lineItem['product_id'];
            $product = wc_get_product($product_id);
            if ($product->product_type == 'rentable'){
                $rentableProduct = true;
                break;
            }
        }

        # Call the right function for the request generation
        if ($rentableProduct){
            $count = -7;
            $planarray = array('Planningmateriaal' => array());
            for ($x = 0; $x < $materialsize; $x++){
                array_push($planarray['Planningmateriaal'], $count);
                $count--;
            }
            $object_data = rentRequest($token, $proj, $contact_id, $transport_id,
                $fees, $startdate, $enddate, $planarray, $notitie, $shippingbtw,
                $materials, $order_id, $ext_ref, $contact_person, $location_contact);
        }
        else{
            $count = -6;
            $planarray = array('Planningmateriaal' => array());
            for ($x = 0; $x < $materialsize; $x++){
                array_push($planarray['Planningmateriaal'], $count);
                $count--;
            }
            $object_data = saleRequest($token, $proj, $contact_id, $transport_id,
                $fees, $planarray, $notitie, $shippingbtw, $materials, $order_id,
                $ext_ref, $contact_person, $location_contact);
        }

        return $object_data;
    }

    # Function that generates a new project request for rentable products
    function rentRequest($token, $proj, $contact_id, $transport_id,
                         $fees, $startdate, $enddate, $planarray, $notitie,
                         $shippingbtw, $materials, $order_id, $ext_ref,
                         $contact_person, $location_contact){
        # Represents request object
        $object_data = array(
            "requestType" => "create",
            "apiVersion" => 1,
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.4.2"
            ),
            "account" => get_option('plugin-account'),
            "token" => $token,
            "itemType" => "Project",
            "columns" => array(
                "Project" => array()
            ),
            "items" => array(
                "Project" => array(
                    "-1" => array(
                        "values" => array(
                            "id" => "-1",
                            "naam" => $proj,
                            "opdrachtgever" => $contact_id,
                            "opdrachtgever_persoon" => $contact_person,
                            "locatie" => $transport_id,
                            "locatie_persoon" => $location_contact,
                            "referentie" => $ext_ref,
                            "gebruiksperiode" => -2,
                            "planperiode" => -2
                        ),
                        "links" => array(
                            "Tijd" => array(
                                -2
                            ),
                            "MateriaalCat" => array(
                                -3
                            ),
                            "Subproject" => array(
                                -4
                            ),
                            "Projectnotitie" => array(
                                -5
                            ),
                            "Functie" => array(
                                -6
                            )
                        )
                    )
                ),
                "Subproject" => array(
                    "-4" => array(
                        "values" => array(
                            "id" => -4,
                            "naam" => $proj,
                            "korting_personeel" => $fees[0],
                            "korting_totaal" => $fees[1],
                            "korting_transport" => $fees[2],
                            "locatie" => $transport_id,
                            "locatie_persoon" => $location_contact
                        )
                    )
                ),
                "Tijd" => array(
                    "-2" => array(
                        "values" => array(
                            "van" => $startdate,
                            "tot" => $enddate,
                            "naam" => __("Huurperiode",'rentalshop'),
                            "subproject" => -4
                        )
                    )
                ),
                "MateriaalCat" => array(
                    "-3" => array(
                        "values" => array(
                            "subproject" => -4,
                            "van" => $startdate,
                            "tot" => $enddate,
                            "gebruikvan" => $startdate,
                            "gebruiktot" => $enddate,
                            "planvan" => $startdate,
                            "plantot" => $enddate
                        ),
                        "links" => $planarray
                    )
                ),
                "Projectnotitie" => array(
                    "-5" => array(
                        "values" => array(
                            "subproject" => -4,
                            "naam" => 'WebShop',
                            "omschrijving" => $notitie
                        )
                    )
                ),
                "Functie" => array(
                    "-6" => array(
                        "values" => array(
                            "subproject" => -4,
                            "naamintern" => 'Shipping',
                            "prijs_vast" => $shippingbtw,
                            "type" => "T"
                        )
                    )
                ),
                "Planningmateriaal" => planmaterial_array($materials, $planarray, $order_id, $contact_id, -7)
            )
        );
        return $object_data;
    }

    # Function that generates a new project request for rentable products
    function saleRequest($token, $proj, $contact_id, $transport_id, $fees, $planarray,
                         $notitie, $shippingbtw, $materials, $order_id, $ext_ref,
                         $contact_person, $location_contact){
        # Represents request object
        $object_data = array(
            "requestType" => "create",
            "apiVersion" => 1,
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.4.2"
            ),
            "account" => get_option('plugin-account'),
            "token" => $token,
            "itemType" => "Project",
            "columns" => array(
                "Project" => array()
            ),
            "items" => array(
                "Project" => array(
                    "-1" => array(
                        "values" => array(
                            "id" => "-1",
                            "naam" => $proj,
                            "opdrachtgever" => $contact_id,
                            "opdrachtgever_persoon" => $contact_person,
                            "locatie" => $transport_id,
                            "locatie_persoon" => $location_contact,
                            "referentie" => $ext_ref,
                        ),
                        "links" => array(
                            "Subproject" => array(
                                -2
                            ),
                            "MateriaalCat" => array(
                                -3
                            ),
                            "Projectnotitie" => array(
                                -4
                            ),
                            "Functie" => array(
                                -5
                            )
                        )
                    )
                ),
                "Subproject" => array(
                    "-2" => array(
                        "values" => array(
                            "id" => -2,
                            "naam" => $proj,
                            "korting_personeel" => $fees[0],
                            "korting_totaal" => $fees[1],
                            "korting_transport" => $fees[2],
                            "locatie" => $transport_id,
                            "locatie_persoon" => $location_contact
                        )
                    )
                ),
                "MateriaalCat" => array(
                    "-3" => array(
                        "values" => array(
                            "subproject" => -2
                        ),
                        "links" => $planarray
                    )
                ),
                "Projectnotitie" => array(
                    "-4" => array(
                        "values" => array(
                            "subproject" => -2,
                            "naam" => 'WebShop',
                            "omschrijving" => $notitie
                        )
                    )
                ),
                "Functie" => array(
                    "-5" => array(
                        "values" => array(
                            "subproject" => -2,
                            "naamintern" => 'Shipping',
                            "prijs_vast" => $shippingbtw,
                            "type" => "T"
                        )
                    )
                ),
                "Planningmateriaal" => planmaterial_array($materials, $planarray, $order_id, $contact_id, -6)
            )
        );
        return $object_data;
    }

    // ------------- Array Creation Functions ------------- \\

    # Create array containing all products
    function get_material_array($order_id){
        $order = new WC_Order($order_id);
        $matarr = array();
        foreach($order->get_items() as $key => $lineItem){
            $name = $lineItem['name'];
            $product_id = $lineItem['product_id'];
            $product = wc_get_product($product_id);
            if (get_post_meta($product_id, 'rentman_imported', true) == true){ # Only Rentman products must be added to the request
                array_push($matarr, array(
                    $name,
                    $lineItem['qty'],
                    ($lineItem['line_total'] / $lineItem['qty']),
                    $product->get_sku()));
            }
        }
        return $matarr;
    }

    # Combine the two arrays into an array with the right format
    function planmaterial_array($materials, $planarray, $order_id, $contact_id, $counter){
        $staffels = get_staffels($order_id);
        $discounts = get_all_discounts($order_id, $contact_id);
        $planmatarr = array_fill_keys($planarray['Planmateriaal'], 'Test');
        foreach ($materials as $item){
            $planmatarr[$counter] = array(
                'values' => array(
                    'naam' => $item[0],
                    'aantal' => $item[1],
                    'aantaltotaal' => $item[1],
                    'prijs' => $item[2],
                    'materiaal' => $item[3],
                    'staffel' => $staffels[$item[3]],
                    'korting' => $discounts[$item[3]]),
                'parameters' => array(
                    'extend_sets' => true));
            $counter--;
        }
        return $planmatarr;
    }

    // ------------- Customizing Checkout Fields ------------- \\

    # Adds checkout fields for the external reference, shipping phone number and shipping email
    function adjust_checkout($fields){
        $fields['billing']['billing_reference'] = array(
            'label'     => __('Externe referentie', 'rentalshop'),
            'placeholder'   => __('Externe referentie (optioneel)', 'rentalshop'),
            'required'  => false,
            'class'     => array('form-row-wide'),
            'clear'     => true
        );
        $fields['shipping']['shipping_email'] = array(
            'label'     => __('E-mailadres', 'rentalshop'),
            'placeholder'   => __('E-mail', 'rentalshop'),
            'required'  => true,
            'class'     => array('form-row-wide'),
            'clear'     => true
        );
        $fields['shipping']['shipping_phone'] = array(
            'label'     => __('Telefoon', 'rentalshop'),
            'placeholder'   => __('Telefoonnummer', 'rentalshop'),
            'required'  => true,
            'class'     => array('form-row-wide'),
            'clear'     => true
        );
        return $fields;
    }
?>