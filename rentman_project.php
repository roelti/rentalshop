<?php // FUNCTIONS REGARDING ADDITION OF NEW PROJECTS TO RENTMAN

    // ------------- API Request Functions ------------- \\

    # Handles API Request
    function add_project($order_id, $contact_id, $fees){
        $url = receive_endpoint();
        $token = get_option('plugin-token');

        # Setup New Project Request to send JSON
        $message = json_encode(setup_newproject_request($token, $order_id, $contact_id, $fees), JSON_PRETTY_PRINT);

        # Send Request & Receive Response
        do_request($url, $message);
    }

    # Returns API request ready to be encoded in Json
    # Used for sending new project data to Rentman
    # Includes contact, relevant materials & rent dates
    function setup_newproject_request($token, $order_id, $contact_id, $fees){
        # Get Order data and rent dates
        $order = new WC_Order($order_id);
        $comp = $order->billing_company;
        $proj = $comp . " Project";
        $notitie = $order->customer_note;
        $startdate = get_option('plugin-startdate');
        $enddate = get_option('plugin-enddate');
        $enddate = date("Y-m-j", strtotime("+1 day", strtotime($enddate)));
        $startdate = $startdate . 'T00:00:00';
        $enddate = $enddate . 'T00:00:00';

        # Get List of Materials and create arrays by using
        # that list for the json request
        $materials = get_material_array($order_id);
        $materialsize = sizeof($materials);
        $count = -6;
        $planarray = array('Planningmateriaal' => array());
        for ($x = 0; $x < $materialsize; $x++){
            array_push($planarray['Planningmateriaal'], $count);
            $count--;
        }

        # Represents request object
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
                            "locatie" => $contact_id,
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
                            "korting_transport" => $fees[2]
                        )
                    )
                ),
                "Tijd" => array(
                    "-2" => array(
                        "values" => array(
                            "van" => $startdate,
                            "tot" => $enddate,
                            "naam" => "Bouwdag",
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
                "Planningmateriaal" => planmaterial_array($materials, $planarray, $order_id, $contact_id)
            )
        );

        return $object_data;
    }

    // ------------- Array Creation Functions ------------- \\

    # Create array containing all products
    function get_material_array($order_id){
        global $wpdb;
        $order = new WC_Order($order_id);
        $matarr = array();
        foreach($order->get_items() as $key => $lineItem){
            $name = $lineItem['name'];
            $product_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $name . "'" );
            $product = wc_get_product($product_id);
            if ($product->product_type == 'rentable'){
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
    function planmaterial_array($materials, $planarray, $order_id, $contact_id){
        $staffels = get_staffels($order_id);
        $discounts = get_all_discounts($order_id, $contact_id);
        $planmatarr = array_fill_keys($planarray['Planmateriaal'], 'Test');
        $counter = -6;
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

?>