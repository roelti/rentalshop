<?php
    // ------------- V4.20.2 ------------- \\
    // ------------- Rentman Request Functions ------------- \\

    // ---------------------------------\\
    // --------| BASE REQUEST |-------- \\
    // ---------------------------------\\

    # Returns base request used in all API requests
    function getBaseRequest($sRequestType){
        if(!function_exists('get_plugin_data')){
          require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        return array(
            "requestType" => $sRequestType,
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => get_plugin_data(realpath(dirname(__FILE__)) . '/rentman.php')['Version']
            ),
            "account" => get_option('plugin-rentman-account')
        );
    }

    // --------------------------\\
    // --------| LOGIN |-------- \\
    // --------------------------\\

    # Returns API request ready to be encoded in Json
    # Login Request
    function setup_login_request()
    {
        $login_data = getBaseRequest('login');
        $login_data["user"] = get_option('plugin-rentman-username');
        $login_data["password"] = dec_enc("decrypt", get_option('plugin-rentman-password'));
        return $login_data;
    }

    // ----------------------------------------------------------------- \\
    // --------| CREATION AND RETRIEVAL OF USERS AND LOCATIONS |-------- \\
    // ----------------------------------------------------------------- \\

    # Returns API request ready to be encoded in Json
    # Checks if a user already exists by their email
    function setup_check_request($token, $mail){
        # Check if contact already exists (by email)
        $object_data = getBaseRequest('query');
        $object_data["token"] = $token;
        $object_data["itemType"] = "Contact";
        $object_data["columns"] = array(
            "Contact" => array(
                "naam",
                "id",
                "personeelkorting",
                "totaalkorting",
                "transportkorting"
            )
        );
        $object_data["query"] = array("email" => $mail);
        return $object_data;
    }

    # Returns API request ready to be encoded in Json
    # Checks if a location already exists by their address
    function setup_location_request($token, $address, $email){
        # Check if contact already exists (by address)
        $object_data = getBaseRequest('query');
        $object_data["token"] = $token;
        $object_data["itemType"] = "Contact";
        $object_data["columns"] = array(
            "Contact" => array(
                "naam",
                "id"
            )
        );
        $object_data["query"] = array(
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
        );
        return $object_data;
    }

    # Returns API request ready to be encoded in Json
    # For sending new user data to Rentman
    function setup_newuser_request($token, $order_id){
        $order = new WC_Order($order_id);
        $company = $order->get_billing_company();
        $attachperson = array();
        $attached = false;

        # Check if the new contact is a company or not
        if ($company == ''){
            $type = "particulier";
            $firstname = $order->get_billing_first_name();
            $lastname = $order->get_billing_last_name();
        } else{
            $type = "bedrijf";
            $firstname = $order->get_billing_first_name();
            $lastname = $order->get_billing_last_name();
            if ($firstname != '' && $lastname != ''){
                $attachperson = array("Person" => array(-2));
                $attached = true;
                $firstname = '';
                $lastname = '';
            }
        }

        # Setup of the request
        $object_data = getBaseRequest('create');
        $object_data["token"] = $token;
        $object_data["itemType"] = "Contact";
        $object_data["columns"] = array(
            "Contact" => array()
        );
        $object_data["items"] = array(
            "Contact" => array(
                "-1" => array(
                    "values" => array(
                        "id" => "-1",
                        "voornaam" => $firstname,
                        "naam" => $lastname,
                        "contactpersoon" => "",
                        "bedrijf" => $order->get_billing_company(),
                        "email" => $order->get_billing_email(),
                        "bezoekstraat" => $order->get_billing_address_1(),
                        "bezoekstad" => $order->get_billing_city(),
                        "bezoekpostcode" => $order->get_billing_postcode(),
                        "factuurstraat" => $order->get_billing_address_1(),
                        "factuurstad" => $order->get_billing_city(),
                        "factuurpostcode" => $order->get_billing_postcode(),
                        "poststraat" => $order->get_billing_address_1(),
                        "poststad" => $order->get_billing_city(),
                        "postpostcode" => $order->get_billing_postcode(),
                        "telefoon" => $order->get_billing_phone(),
                        "type" => $type
                    ),
                    "links" => $attachperson
                )
            ),
            "Person" => array()
        );
        $object_data["parentId"] = 900;
        $object_data['parenType'] = "Contact";

        # Attach the Person data for companies where the names have also
        # been filled in
        if ($attached){
            $person = array(
                "-2" => array(
                    "values" => array(
                        "id" => -2,
                        "voornaam" => $order->get_billing_first_name(),
                        "achternaam" => $order->get_billing_last_name(),
                        "postcode" => $order->get_billing_postcode(),
                        "stad" => $order->get_billing_city(),
                        "straat" => $order->get_billing_address_1(),
                        "telefoon" => $order->get_billing_phone(),
                        "email" => $order->get_billing_email()
                    )
                )
            );
            $object_data["items"]["Person"] = $person;
            $object_data["items"]["Contact"][-1]["values"]["contactpersoon"] = -2;
        } else { # Remove the Person data from the request if not
            unset($object_data["items"]["Contact"][-1]["values"]["contactpersoon"]);
            unset($object_data["items"]["Person"]);
        }
        return $object_data;
    }

    # Returns API request ready to be encoded in Json
    # For sending new location data as a user to Rentman
    function setup_newlocation_request($token, $order_id){
        $order = new WC_Order($order_id);
        $company = $order->get_shipping_company();
        $attachperson = array();
        $attached = false;

        # Check if the new contact is a company or not
        if ($company == ''){
            $type = "particulier";
            $firstname = $order->get_shipping_first_name();
            $lastname = $order->get_shipping_last_name();
        } else{
            $type = "bedrijf";
            $firstname = $order->get_shipping_first_name();
            $lastname = $order->get_shipping_last_name();
            if ($firstname != '' && $lastname != ''){
                $attachperson = array("Person" => array(-2));
                $attached = true;
                $firstname = '';
                $lastname = '';
            }
        }

        $object_data = getBaseRequest('create');
        $object_data["token"] = $token;
        $object_data["itemType"] = "Contact";
        $object_data["columns"] = array(
            "Contact" => array()
        );
        $object_data["items"] = array(
            "Contact" => array(
                "-1" => array(
                    "values" => array(
                        "id" => "-1",
                        "voornaam" => $firstname,
                        "naam" => $lastname,
                        "contactpersoon" => "",
                        "bedrijf" => $order->get_shipping_company(),
                        "email" => get_post_meta($order_id, '_shipping_email', true),
                        "bezoekstraat" => $order->get_shipping_address_1(),
                        "bezoekstad" => $order->get_shipping_city(),
                        "bezoekpostcode" => $order->get_shipping_postcode(),
                        "factuurstraat" => $order->get_shipping_address_1(),
                        "factuurstad" => $order->get_shipping_city(),
                        "factuurpostcode" => $order->get_shipping_postcode(),
                        "poststraat" => $order->get_shipping_address_1(),
                        "poststad" => $order->get_shipping_city(),
                        "postpostcode" => $order->get_shipping_postcode(),
                        "telefoon" => get_post_meta($order_id, '_shipping_phone', true),
                        "type" => $type
                    ),
                    "links" => $attachperson
                )
            ),
            "Person" => array()
        );
        $object_data['parentId'] = 900;
        $object_data['parenType'] = "Contact";

        # Attach the Person data for companies where the names have also been filled in
        if ($attached){
            $person = array(
                "-2" => array(
                    "values" => array(
                        "id" => -2,
                        "voornaam" => $order->get_shipping_first_name(),
                        "achternaam" => $order->get_shipping_last_name(),
                        "postcode" => $order->get_shipping_postcode(),
                        "stad" => $order->get_shipping_city(),
                        "straat" => $order->get_shipping_address_1(),
                        "telefoon" => get_post_meta($order_id, '_shipping_phone', true),
                        "email" => get_post_meta($order_id, '_shipping_email', true)
                    )
                )
            );
            $object_data["items"]["Person"] = $person;
            $object_data["items"]["Contact"][-1]["values"]["contactpersoon"] = -2;
        } else{ # Remove the Person data from the request
            unset($object_data["items"]["Person"]);
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
        $comp = $order->get_billing_company();
        $proj = $comp . " Webshop Project";
        $notitie = "Bestelnummer in WooCommerce: " . $order_id . ".\n" . $order->get_customer_note();
        $ext_ref = get_post_meta($order_id, '_billing_reference', true);

        $dates = get_dates();
        $startdate = $dates['from_date'];
        $enddate = $dates['to_date'];

        $enddate = date("Y-m-j", strtotime("+1 day", strtotime($enddate)));
        $startdate = $startdate . 'T00:00:00';
        $enddate = $enddate . 'T00:00:00';

        # Get List of Materials and create arrays by using
        # that list for the json request
        $shippingbtw = $order->get_total_shipping() / 1.21;
        $materials = get_material_array($order_id);
        $materialsize = sizeof($materials);

        # Do not add any rental dates to the request when only non-rentable products
        # have been purchased in the order
        $rentableProduct = false;
        $_tax = new WC_Tax();
        $tax = 0;
        foreach($order->get_items() as $key => $lineItem){
            $product_id = $lineItem['product_id'];
            $product = wc_get_product($product_id);
            if ($tax == 0){
                $product_tax_class = $product->get_tax_class();
                $rates = $_tax->get_rates($product_tax_class);
                $rate = current($rates);
                $tax = (floatval($rate['rate']) / 100);
            }
            if ($product->get_type() == 'rentable'){
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
        $object_data = getBaseRequest('create');
        $object_data["token"] = $order_data['token'];
        $object_data["itemType"] = "Project";
        $object_data["columns"] = array(
            "Project" => array()
        );
        $object_data["items"] = array(
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
                        "naam" => __("Rental period",'rentalshop'),
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
        );
        return $object_data;
    }

    # Function that generates a new project request for rentable products
    function saleRequest($order_data, $fees){
        # Represents request object
        $object_data = getBaseRequest('create');
        $object_data["token"] = $order_data['token'];
        $object_data["itemType"] = "Project";
        $object_data["columns"] = array(
            "Project" => array()
        );
        $object_data["items"] = array(
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
        );
        return $object_data;
    }

    // ------------------------------------------------ \\
    // --------| RETRIEVAL OF PRODUCT FOLDERS |-------- \\
    // ------------------------------------------------ \\

    # Returns API request ready to be encoded in Json
    # For importing folders
    function setup_folder_request($token){
        $object_data = getBaseRequest('query');
        $object_data["token"] = $token;
        $object_data["itemType"] = "Folder";
        $object_data["columns"] = array(
            "Folder" => array(
                "naam",
                "id",
                "volgorde",
                array(
                    "parent" => array(
                        "naam",
                        "id"
                    )
                ),
                "numberofitems"
            )
        );
        $object_data["query"] = array(
          "operator" => "AND",
          "conditions" => array(
              array(
                "key" => "itemtype",
                "value" => "Materiaal",
                "comparator" => "="
              )
          )
        );
        //$object_data["query"] = array("operator" => "AND", "conditions" => []);
        return $object_data;
    }

    // ------------------------------------------------------------- \\
    // --------| RETRIEVAL OF MATERIALS AND ATTACHED FILES |-------- \\
    // ------------------------------------------------------------- \\

    # Returns API request ready to be encoded in Json
    # For importing products
    function setup_import_request($token){
        global $customFields;
        $object_data = getBaseRequest('query');
        $object_data["token"] = $token;
        $object_data["itemType"] = "Materiaal";
        $object_data["query"] = array(
            "operator" => "AND",
            "conditions" => array(
                array(
                    "key" => "tijdelijk",
                    "value" => false
                ),
                array(
                    "key" => "in_shop",
                    "value" => true
                ),
                array(
                    "operator" => "OR",
                    "conditions" => array(
                        array(
                            "key" => "modified",
                            "value" => "2001-01-01T01:30:00+02:00",
                            "comparator" => ">"
                        ),
                        array(
                            "linkedTo" => "Files",
                            "query" => array(
                                "operator" => "AND",
                                "conditions" => array(
                                    array(
                                        "key" => "image",
                                        "value" => true
                                    ),
                                    array(
                                      "key" => "in_shop",
                                      "value" => true
                                    )
                                )
                            )
                        ),
                        array(
                            "linkedTo" => "Taglink",
                            "query" => array(
                                "conditions" => array(
                                    array(
                                        "key" => "modified",
                                        "value" => "2001-01-01T01:30:00+02:00",
                                        "comparator" => ">"
                                    ),
                                )
                            )
                        )
                    )
                )
            )
        );
        $object_data["columns"] = array(
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
                "images",
                "standaardtarief",
                "aantal",
                "taglist",
                "shop_seo_title",
                "shop_seo_keyword",
                "shop_seo_description",
                "shop_featured"
            ),
            "Files" => array(
                "image",
                "id",
                "name",
                "modified",
                "in_shop",
                "description"
            ),
            "Taglink" => array(
                "name"
            )
        );
        # Add custom fields if wanted (=defined in product_customfields.php)
        foreach ($customFields as $customField) {
          array_push($object_data["columns"]["Materiaal"], $customField[0]);
        }
        return $object_data;
    }

    # Returns API request ready to be encoded in Json
    # For getting the url for a single image file based on its id for every product
    function get_file_url($token, $id){
        $object_data = getBaseRequest('query');
        $object_data["token"] = $token;
        $object_data["itemType"] = "Files";
        $object_data["columns"] = array(
            "Files" => array(
                "id",
                "url",
                "name",
                "description",
                "type"
            )
        );
        $object_data["query"] = array(
            "conditions" => array(
                array(
                    "key" => "id",
                    "value" => $id,
                    "comparator" => "="
                )
            )
        );
        return $object_data;
    }

    # Returns API request ready to be encoded in Json
    # Get all the custom fields created in Rentman
    function get_custom_fields($token){
        $object_data = getBaseRequest('query');
        $object_data["token"] = $token;
        $object_data["itemType"] = "Customfield";
        $object_data["columns"] = array(
            "Customfield" => array(
                "id",
                "naam",
                "type"
            )
        );
        $object_data["query"] = array(
          "operator" => "AND",
          "conditions" => array(
              array(
                "key" => "itemtype",
                "value" => "Materiaal",
                "comparator" => "="
              )
          )
        );
        return $object_data;
    }


    // --------------------------------------------- \\
    // --------| AVAILABILITY OF MATERIALS |-------- \\
    // --------------------------------------------- \\

    # Setup API request that checks the availability of the product
    function available_request($token, $identifier, $quantity, $updating = false, $sdate, $edate){
        if ($updating){
            $startDate = $sdate;
            $endDate = $edate;
        } else {
            $dates = get_dates();
            $startDate = $dates['from_date'];
            $endDate = $dates['to_date'];
        }
        $endDate = date("Y-m-j", strtotime("+1 day", strtotime($endDate)));
        $object_data = getBaseRequest('modulefunction');
        $object_data["token"] = $token;
        $object_data["module"] = "Availability";
        $object_data["parameters"] = array(
            "van" => $startDate,
            "tot" => $endDate,
            "materiaal" => $identifier,
            "aantal" => $quantity
        );
        $object_data["method"] = "is_available";
        return $object_data;
    }

    // ------------------------------------------------------- \\
    // --------| RETRIEVAL OF STAFFELS, DISCOUNTS AND TAXES|-------- \\
    // ------------------------------------------------------- \\

    # Returns API request ready to be encoded in Json
    # For getting staffel by staffelgroup
    function setup_staffel_request($token, $totaldays, $staffelgroup){
        $object_data = getBaseRequest('query');
        $object_data["token"] = $token;
        $object_data["itemType"] = "Staffel";
        $object_data["columns"] = array(
            "Staffel" => array(
                "id",
                "displayname",
                "staffel",
                "staffelgroep",
                "van",
                "tot"
            )
        );
        $object_data["query"] = array(
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
            )
        );
        return $object_data;
    }

    # Returns API request ready to be encoded in Json
    # For getting staffelgroups
    function setup_staffelgroup_request($token, $product_id){
        $object_data = getBaseRequest('query');
        $object_data["token"] = $token;
        $object_data["itemType"] = "Materiaal";
        $object_data["columns"] = array(
            "Materiaal" => array(
                "naam",
                "verhuurprijs",
                "staffelgroep",
                "verhuur"
            )
        );
        $object_data["query"] = array("id" => $product_id);
        return $object_data;
    }

    # Setup API request that returns the fees of products
    function setup_discount_request($token, $contact_id, $materials){
        //$object_data = getBaseRequest('query');
        $object_data = getBaseRequest('modulefunction');
        $object_data["token"] = $token;
        $object_data["module"] = "Webshop";
        $object_data["parameters"] = array(
            "contact" => $contact_id,
            "materialen" => $materials
        );
        $object_data["method"] = "calculateDiscount";
        return $object_data;
    }

    # Setup API request that returns the ID of the Btwcode that
    # is linked to the tax value in Rentman
    function receive_btwcode_request($token, $tax){
        $object_data = getBaseRequest('query');
        $object_data["token"] = $token;
        $object_data["itemType"] = "Btwcode";
        $object_data["columns"] = array(
            "Btwcode" => array(
                "naam",
                "id",
                "tarief"
            )
        );
        $object_data["query"] = array("tarief" => $tax);
        return $object_data;
    }
?>
