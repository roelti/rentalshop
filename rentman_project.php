<?php
    // ------------- V4.20.2 ------------- \\
    // ------------- API Request Functions ------------- \\

    # Handles API Request for project creation
    function add_project($order_id, $contact_id, $transport_id, $fees, $contact_person, $location_contact){
        if (apply_filters('rentman/creating_project', true)) {
            $url = receive_endpoint();
            $token = get_option('plugin-rentman-token');

            # Setup New Project Request to send JSON
            $message = json_encode(setup_newproject_request($token, $order_id, $contact_id, $transport_id, $fees, $contact_person, $location_contact), JSON_PRETTY_PRINT);

            # Send Request & Receive Response
            do_request($url, $message);
            //echo json_encode(json_decode($received), JSON_PRETTY_PRINT);
        }
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
        $planmatarr = array_fill_keys($planarray['Planningmateriaal'], 'Test');
        foreach ($materials as $item){
            $planmatarr[$counter] = array(
                'values' => array(
                    'naam' => $item[0],
                    'aantal' => $item[1],
                    'aantaltotaal' => $item[1],
                    'prijs' => $item[2],
                    'materiaal' => $item[3],
                    'staffel' => $staffels[$item[3]],
                    'korting' => isset($discounts[$item[3]]) ? $discounts[$item[3]] : 0),
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
            'label'     => __('External reference', 'rentalshop'),
            'placeholder'   => __('External reference (optional)', 'rentalshop'),
            'required'  => false,
            'class'     => array('form-row-wide'),
            'clear'     => true
        );
        $fields['shipping']['shipping_email'] = array(
            'label'     => __('Email address', 'rentalshop'),
            'placeholder'   => __('Email address', 'rentalshop'),
            'required'  => true,
            'class'     => array('form-row-wide'),
            'clear'     => true
        );
        $fields['shipping']['shipping_phone'] = array(
            'label'     => __('Phone', 'rentalshop'),
            'placeholder'   => __('Phone number', 'rentalshop'),
            'required'  => true,
            'class'     => array('form-row-wide'),
            'clear'     => true
        );
        return $fields;
    }

    # Adds the rental period data to the order data in the confirmation email
    function add_dates_to_email($fields, $sent_to_admin, $order){
        $fields['rental_period'] = array(
            'label' => __('Rental period', 'rentalshop'),
            'value' => get_post_meta($order->id, 'rental_period', true),
        );
        return $fields;
    }

    # Adds the rental period data to the order meta
    function add_rental_data($order_id){
        $dates = get_dates();
        $rental = $dates['from_date'] . ' ~ ' . $dates['to_date'];
        update_post_meta($order_id, 'rental_period', $rental);
    }

    # Displays the rental period data on the order details page
    function display_dates_in_order($order){
        echo '<p><strong>' . __('Rental period', 'rentalshop') . ':</strong> ' . get_post_meta($order->get_id(), 'rental_period', true) . '</p>';
    }
?>
