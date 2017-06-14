<?php
    // ------------- Rentman Request Functions ------------- \\

    // --------------------------\\
    // --------| LOGIN |-------- \\
    // --------------------------\\

    # Returns API request ready to be encoded in Json
    # Login Request
    function setup_login_request()
    {
        $login_data = array(
            "requestType" => "login",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.5.1"
            ),
            "account" => get_option('plugin-account'),
            "user" => get_option('plugin-username'),
            "password" => get_option('plugin-password')
        );
        return $login_data;
    }

    // ----------------------------------------------------------------- \\
    // --------| CREATION AND RETRIEVAL OF USERS AND LOCATIONS |-------- \\
    // ----------------------------------------------------------------- \\

    # Returns API request ready to be encoded in Json
    # Checks if a user already exists by their email
    function setup_check_request($token, $mail){
        # Check if contact already exists (by email)
        $object_data = array(
            "requestType" => "query",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.5.1"
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
    function setup_location_request($token, $address, $email){
        # Check if contact already exists (by address)
        $object_data = array(
            "requestType" => "query",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.5.1"
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
            "query" => array(
                "conditions" => array(
                    array(
                        "key" => "bezoekstraat",
                        "value" => $address
                    ),
                    array(
                        "key" => "email",
                        "value" => $email
                    )
                ),
                "operator" => "AND"
            )
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
                "version" => "4.5.1"
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
                            "contactpersoon" => "",
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
            $object_data['items']['Contact'][-1]["values"]["contactpersoon"] = -2;
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
                "version" => "4.5.1"
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
                            "contactpersoon" => "",
                            "bedrijf" => $order->shipping_company,
                            "email" => $order->shipping_email,
                            "bezoekstraat" => $order->shipping_address_1,
                            "bezoekstad" => $order->shipping_city,
                            "bezoekpostcode" => $order->shipping_postcode,
                            "factuurstraat" => $order->shipping_address_1,
                            "factuurstad" => $order->shipping_city,
                            "factuurpostcode" => $order->shipping_postcode,
                            "poststraat" => $order->shipping_address_1,
                            "poststad" => $order->shipping_city,
                            "postpostcode" => $order->shipping_postcode,
                            "telefoon" => $order->shipping_phone,
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
            $object_data['items']['Contact'][-1]["values"]["contactpersoon"] = -2;
        } else{ # Remove the Person data from the request
            unset($object_data['items']['Person']);
        }
        return $object_data;
    }

    // ------------------------------------------ \\
    // --------| EXPORT OF NEW PROJECTS |-------- \\
    // ------------------------------------------ \\

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

        $dates = get_dates();
        $startdate = $dates['from_date'];
        $enddate = $dates['to_date'];

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
            $order_data = array(
                "token" => $token,
                "proj" => $proj,
                "contact_id" => $contact_id,
                "transport_id" => $transport_id,
                "startdate" => $startdate,
                "enddate" => $enddate,
                "planarray" => $planarray,
                "notitie" => $notitie,
                "shippingBTW" => $shippingbtw,
                "materials" => $materials,
                "order_id" => $order_id,
                "ext_ref" => $ext_ref,
                "contact_person" => $contact_person,
                "location_contact" => $location_contact
            );
            $object_data = rentRequest($order_data, $fees);
        }
        else{
            $count = -6;
            $planarray = array('Planningmateriaal' => array());
            for ($x = 0; $x < $materialsize; $x++){
                array_push($planarray['Planningmateriaal'], $count);
                $count--;
            }
            $order_data = array(
                "token" => $token,
                "proj" => $proj,
                "contact_id" => $contact_id,
                "transport_id" => $transport_id,
                "planarray" => $planarray,
                "notitie" => $notitie,
                "shippingBTW" => $shippingbtw,
                "materials" => $materials,
                "order_id" => $order_id,
                "ext_ref" => $ext_ref,
                "contact_person" => $contact_person,
                "location_contact" => $location_contact
            );
            $object_data = saleRequest($order_data, $fees);
        }

        return $object_data;
    }

    # Function that generates a new project request for rentable products
    function rentRequest($order_data, $fees){
        # Represents request object
        $object_data = array(
            "requestType" => "create",
            "apiVersion" => 1,
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.5.1"
            ),
            "account" => get_option('plugin-account'),
            "token" => $order_data['token'],
            "itemType" => "Project",
            "columns" => array(
                "Project" => array()
            ),
            "items" => array(
                "Project" => array(
                    "-1" => array(
                        "values" => array(
                            "id" => "-1",
                            "naam" => $order_data['proj'],
                            "opdrachtgever" => $order_data['contact_id'],
                            "opdrachtgever_persoon" => $order_data['contact_person'],
                            "locatie" => $order_data['transport_id'],
                            "locatie_persoon" => $order_data['location_contact'],
                            "referentie" => $order_data['ext_ref'],
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
                            "naam" => $order_data['proj'],
                            "korting_personeel" => $fees[0],
                            "korting_totaal" => $fees[1],
                            "korting_transport" => $fees[2],
                            "locatie" => $order_data['transport_id'],
                            "locatie_persoon" => $order_data['location_contact']
                        )
                    )
                ),
                "Tijd" => array(
                    "-2" => array(
                        "values" => array(
                            "van" => $order_data['startdate'],
                            "tot" => $order_data['enddate'],
                            "naam" => __("Huurperiode",'rentalshop'),
                            "subproject" => -4
                        )
                    )
                ),
                "MateriaalCat" => array(
                    "-3" => array(
                        "values" => array(
                            "subproject" => -4,
                            "van" => $order_data['startdate'],
                            "tot" => $order_data['enddate'],
                            "gebruikvan" => $order_data['startdate'],
                            "gebruiktot" => $order_data['enddate'],
                            "planvan" => $order_data['startdate'],
                            "plantot" => $order_data['enddate']
                        ),
                        "links" => $order_data['planarray']
                    )
                ),
                "Projectnotitie" => array(
                    "-5" => array(
                        "values" => array(
                            "subproject" => -4,
                            "naam" => 'WebShop',
                            "omschrijving" => $order_data['notitie']
                        )
                    )
                ),
                "Functie" => array(
                    "-6" => array(
                        "values" => array(
                            "subproject" => -4,
                            "naamintern" => 'Shipping',
                            "prijs_vast" => $order_data['shippingBTW'],
                            "type" => "T"
                        )
                    )
                ),
                "Planningmateriaal" => planmaterial_array($order_data['materials'], $order_data['planarray'], $order_data['order_id'], $order_data['contact_id'], -7)
            )
        );
        return $object_data;
    }

    # Function that generates a new project request for rentable products
    function saleRequest($order_data, $fees){
        # Represents request object
        $object_data = array(
            "requestType" => "create",
            "apiVersion" => 1,
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.5.1"
            ),
            "account" => get_option('plugin-account'),
            "token" => $order_data['token'],
            "itemType" => "Project",
            "columns" => array(
                "Project" => array()
            ),
            "items" => array(
                "Project" => array(
                    "-1" => array(
                        "values" => array(
                            "id" => "-1",
                            "naam" => $order_data['proj'],
                            "opdrachtgever" => $order_data['contact_id'],
                            "opdrachtgever_persoon" => $order_data['contact_person'],
                            "locatie" => $order_data['transport_id'],
                            "locatie_persoon" => $order_data['location_contact'],
                            "referentie" => $order_data['ext_ref'],
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
                            "naam" => $order_data['proj'],
                            "korting_personeel" => $fees[0],
                            "korting_totaal" => $fees[1],
                            "korting_transport" => $fees[2],
                            "locatie" => $order_data['transport_id'],
                            "locatie_persoon" => $order_data['location_contact']
                        )
                    )
                ),
                "MateriaalCat" => array(
                    "-3" => array(
                        "values" => array(
                            "subproject" => -2
                        ),
                        "links" => $order_data['planarray']
                    )
                ),
                "Projectnotitie" => array(
                    "-4" => array(
                        "values" => array(
                            "subproject" => -2,
                            "naam" => 'WebShop',
                            "omschrijving" => $order_data['notitie']
                        )
                    )
                ),
                "Functie" => array(
                    "-5" => array(
                        "values" => array(
                            "subproject" => -2,
                            "naamintern" => 'Shipping',
                            "prijs_vast" => $order_data['shippingBTW'],
                            "type" => "T"
                        )
                    )
                ),
                "Planningmateriaal" => planmaterial_array($order_data['materials'], $order_data['planarray'], $order_data['order_id'], $order_data['contact_id'], -6)
            )
        );
        return $object_data;
    }

    // ------------------------------------------------ \\
    // --------| RETRIEVAL OF PRODUCT FOLDERS |-------- \\
    // ------------------------------------------------ \\

    # Returns API request ready to be encoded in Json
    # For importing folders
    function setup_folder_request($token){
        $object_data = array(
            "requestType" => "query",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.5.1"
            ),
            "account" => get_option('plugin-account'),
            "token" => $token,
            "itemType" => "Folder",
            "columns" => array(
                "Folder" => array(
                    "naam",
                    "id",
                    array(
                        "parent" => array(
                            "naam",
                            "id"
                        )
                    ),
                    "numberofitems"
                )
            ),
            "query" => array("operator" => "AND", "conditions" => [])
        );
        return $object_data;
    }

    // ------------------------------------------------------------- \\
    // --------| RETRIEVAL OF MATERIALS AND ATTACHED FILES |-------- \\
    // ------------------------------------------------------------- \\

    # Returns API request ready to be encoded in Json
    # For importing products
    function setup_import_request($token){
        $object_data = array(
            "requestType" => "query",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.5.1"
            ),
            "account" => get_option('plugin-account'),
            "token" => $token,
            "itemType" => "Materiaal",
            "columns" => array(
                "Materiaal" => array(
                    "naam",
                    "verhuurprijs",
                    "verhuur",
                    "shop_description_long",
                    "shop_description_short",
                    "omschrijving",
                    "modified",
                    array(
                        "folder" => array(
                            "naam",
                            "parent"
                        )
                    ),
                    "gewicht",
                    "height",
                    "length",
                    "width",
                    "img",
                    array(
                        "btwcode" => array(
                            "tarief"
                        )
                    )
                )
            ),
            "query" => array(
                "conditions" => array(
                    array(
                        "key" => "tijdelijk",
                        "value" => false
                    ),
                    array(
                        "key" => "in_shop",
                        "value" => true
                    )
                ),
                "operator" => "AND"
            )
        );
        return $object_data;
    }

    # Returns API request ready to be encoded in Json
    # For getting image files for every product
    function setup_file_request($token, $prodList, $globalimages = false){
        if ($globalimages)
            $idList = rentman_ids();
        else
            $idList = list_of_ids($prodList);
        $file_data = array(
            "requestType" => "query",
            "apiVersion" => 1,
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.5.1"
            ),
            "account" => get_option('plugin-account'),
            "token" => $token,
            "itemType" => "Files",
            "columns" => array(
                "Files" => array(
                    "url",
                    "item"
                )
            ),
            "query" => array(
                "conditions" => array(
                    array(
                        "linkedTo" => "Materiaal",
                        "reverse" => false,
                        "query" => array(
                            "id" => $idList
                        )
                    ),
                    array(
                        "key" => "image",
                        "value" => true
                    )
                ),
                "operator" => "AND"
            )
        );
        return $file_data;
    }

    // --------------------------------------------- \\
    // --------| AVAILABILITY OF MATERIALS |-------- \\
    // --------------------------------------------- \\

    # Setup API request that checks the availability of the product
    function available_request($token, $identifier, $quantity){
        $dates = get_dates();
        $enddate = $dates['to_date'];
        $enddate = date("Y-m-j", strtotime("+1 day", strtotime($enddate)));
        $object_data = array(
            "requestType" => "modulefunction",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.5.1"
            ),
            "account" => get_option('plugin-account'),
            "token" => $token,
            "module" => "Availability",
            "parameters" => array(
                "van" => $dates['from_date'],
                "tot" => $enddate,
                "materiaal" => $identifier,
                "aantal" => $quantity
            ),
            "method" => "is_available"
        );
        return $object_data;
    }

    // ------------------------------------------------------- \\
    // --------| RETRIEVAL OF STAFFELS AND DISCOUNTS |-------- \\
    // ------------------------------------------------------- \\

    # Returns API request ready to be encoded in Json
    # For getting staffel by staffelgroup
    function setup_staffel_request($token, $totaldays, $staffelgroup){
        $object_data = array(
            "requestType" => "query",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.5.1"
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
                "version" => "4.5.1"
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
                "version" => "4.5.1"
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