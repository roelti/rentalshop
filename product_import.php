<?php
    // ------------- Main Product Import Functions ------------- \\

    # Handles import of products from Rentman to your WooCommerce Shop
    function import_products($token){
        if ($token == "fail"){
            _e('<h4>Importeren mislukt! Kloppen uw inloggegevens wel?</h4>', 'rentalshop');
        } else{
            if (apply_filters('rentman/import_products', true)) {
                $date = date('l jS \of F Y h:i:s A');
                update_option('plugin-lasttime', $date);

                # Get the url of the most latest endpoint
                $url = receive_endpoint();

                # First, obtain all the products from Rentman
                $message = json_encode(setup_import_request($token), JSON_PRETTY_PRINT);

                # Send Request & Receive Response
                $received = do_request($url, $message);
                $parsed = json_decode($received, true);
                $parsed = parseResponse($parsed);

                # Receive ID's of first and last product in response
                $listLength = sizeof($parsed['response']['links']['Materiaal']);

                if ($listLength > 0) { # At least one product has been found
                    $firstItem = $parsed['response']['links']['Materiaal'][0];
                    $lastItem = $parsed['response']['links']['Materiaal'][$listLength - 1];

                    # Build arrays containing all imported products and image files
                    $prod_array = convert_items($parsed, $firstItem, $lastItem);
                    $file_array = get_files($prod_array, $token, $url);

                    # If one or more products have been found, create the product categories
                    # and register and localize the admin_import.js file
                    if (sizeof($prod_array) > 0) {
                        # Import the product categories
                        $message = json_encode(setup_folder_request($token), JSON_PRETTY_PRINT);
                        $received = do_request($url, $message);
                        $parsed = json_decode($received, true);
                        $parsed = parseResponse($parsed);

                        # Arrange the response data in a new clear array
                        $folder_arr = arrange_folders($parsed);

                        # Create new Categories by using the imported folder data
                        foreach ($folder_arr as $folder) {
                            add_category($folder);
                        }

                        # Prepare Script
                        wp_register_script('admin_add_product', plugins_url('js/admin_import.js', __FILE__));
                        wp_localize_script('admin_add_product', 'products', $prod_array);
                        wp_localize_script('admin_add_product', 'folders', $file_array);
                        wp_localize_script('admin_add_product', 'arrayindex', '0');
                        wp_localize_script('admin_add_product', 'string1', __('<b>Producten klaar:</b> ', 'rentalshop'));
                        wp_localize_script('admin_add_product', 'string2', __('<br>Verwerken..', 'rentalshop'));
                        wp_localize_script('admin_add_product', 'string3', __('<br>Irrelevante producten en mappen zijn verwijderd', 'rentalshop'));
                        wp_localize_script('admin_add_product', 'string4', __('<h3>Importeren geslaagd!</h3>', 'rentalshop'));
                        wp_enqueue_script('admin_add_product');
                    } else { # No new products have been added and the existing ones haven't been updated
                        _e('<br><b>Er zijn geen nieuwe Rentman producten gevonden!</b> ', 'rentalshop');
                        remove_empty_categories();
                    }
                } else { # No rentable products have been found
                    _e('<br>Er zijn geen producten gevonden op uw Rentman account..<br>', 'rentalshop');
                    remove_empty_categories();
                }
            }
        }
    }

    # Imports five products from the product array, starting from the received index
    function array_to_product($prod_array, $file_array, $startIndex){
        if (sizeof($prod_array) > 0){
            _e('<br><br><b>Geimporteerde Producten:</b><br>', 'rentalshop');

            # Import the products in the WooCommerce webshop
            $endIndex = $startIndex + 4;
            for ($index = $startIndex; $index <= $endIndex; $index++){
                if ($index >= sizeof($prod_array))
                    break;
                import_product($prod_array[$index], $file_array);
                echo 'Index of Product:' . $prod_array[$index]['id'];
            }
        }
    }

    # Parses the products in the API response, then checks whether they have been
    # updated and adds them to a list
    function convert_items($parsed, $lower, $higher){
        global $wpdb;
        $prodList = array(); # array that stores new/updates products
        $checkList = array(); # array containing id's of all checked products

        for ($x = $lower; $x <= $higher; $x++) {
            # Check if the key exists in the material array
            if (!array_key_exists($x, $parsed['response']['items']['Materiaal']))
                continue;

            # Get description, last modified date and btwcode from current product
            $longdesc = $parsed['response']['items']['Materiaal'][$x]['data']['shop_description_long'];
            $fulldesc = $parsed['response']['items']['Materiaal'][$x]['data']['omschrijving'];
            $modDate = $parsed['response']['items']['Materiaal'][$x]['data']['modified'];
            $btwcode = $parsed['response']['items']['Materiaal'][$x]['data']['btwcode'];

            # Check if product already exists in database
            $noDiff = false;
            $product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $x));
            if ($product_id){
                # Post Exists, now check update time
                $updated = check_updated($x, $modDate);
                if ($updated == false){
                    # Post has not been updated
                    $noDiff = true;
                }
            }

            # Products has been checked
            array_push($checkList, $x);

            if($noDiff){ # Product already exists and has not been updated
                continue;
            } else{ # Product does not exist yet or has been updated, so add it to the array
                if ($longdesc == '')
                    $longdesc = $fulldesc;
                # Retrieve the rest of the product information from the response and add to array
                $product_data = array(
                    "id" => $x,
                    "name" => trim($parsed['response']['items']['Materiaal'][$x]['data']['naam']),
                    "cost" => $parsed['response']['items']['Materiaal'][$x]['data']['verhuurprijs'],
                    "long_desc" => $longdesc,
                    "short_desc" => $parsed['response']['items']['Materiaal'][$x]['data']['shop_description_short'],
                    "folder_id" => $parsed['response']['items']['Materiaal'][$x]['data']['folder'],
                    "mod_date" => $modDate,
                    "weight" => $parsed['response']['items']['Materiaal'][$x]['data']['gewicht'],
                    "btw" => $parsed['response']['items']['Btwcode'][$btwcode]['data']['tarief'],
                    "verhuur" => $parsed['response']['items']['Materiaal'][$x]['data']['verhuur'],
                    "length" => $parsed['response']['items']['Materiaal'][$x]['data']['length'],
                    "width" => $parsed['response']['items']['Materiaal'][$x]['data']['width'],
                    "height" => $parsed['response']['items']['Materiaal'][$x]['data']['height'],
                    "amount" => $parsed['response']['items']['Materiaal'][$x]['data']['aantal']
                );
                array_push($prodList, $product_data);
            }
        }
        # Delete products in WooCommerce shop that are not in the product list
        compare_and_delete($checkList);

        return $prodList; # Return array of objects for later use
    }

    # Compare the list of updated products with the current product list
    # and delete all products that aren't available in Rentman anymore
    function compare_and_delete($checkList){
        global $wpdb;
        $all_products = get_product_list();
        $remainder = array_diff($all_products, $checkList);
        if (sizeof($remainder) > 0){
            # Now delete the posts in the remainder array
            foreach ($remainder as $item){
                $postID = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $item));
                # Delete attached images
                $media = get_children(array('post_parent' => $postID, 'post_type' => 'attachment'));
                foreach ($media as $file){
                    wp_delete_post($file->ID);
                }
                wp_delete_post($postID);
            }
        }
    }

    # Get all 'Rentable' products stored in WooCommerce
    function get_product_list(){
        $full_product_list = array();
        $args = array('post_type' => 'product', 'posts_per_page' => -1, 'rentman_imported' => true);
        $posts = get_posts($args);
        for ($x = 0; $x < sizeof($posts); $x++){
            $product = $posts[$x];
            $material = wc_get_product($product->ID);
            array_push($full_product_list, $material->get_sku());
        }
        return $full_product_list;
    }

    # Use the array of imported projects from Rentman to create
    # new products in WooCommerce
    function import_product($product, $file_list){
        if (empty($product['long_desc'])){
            $content = __('Geen informatie beschikbaar', 'rentalshop');
        } else{
            $content = $product['long_desc'];
        }

        # Create new product
        $new_product = array(
            "post_name" => $product['name'],
            "post_title" => $product['name'],
            "post_content" => $content,
            "post_excerpt" => $product['short_desc'],
            "post_date" => $product['mod_date'],
            "post_status" => "publish",
            "post_type" => "product"
        );

        # Check Category
        $categoryIDs = get_option('plugin-rentmanIDs', []);
        $checkterm = get_term($categoryIDs[$product['folder_id']], 'product_cat');

        # Insert post
        $post_id = wp_insert_post($new_product, TRUE);

        # Other method for setting category
        wp_set_object_terms($post_id, $checkterm->term_id, 'product_cat');

        # If it is a 'verhuur' product, the type is set to 'rentable'
        if ($product['verhuur']) {
            wp_set_object_terms($post_id, 'rentable', 'product_type');
        }
        else { # Otherwise it is a 'simple product'
            wp_set_object_terms($post_id, 'simple_product', 'product_type');
        }

        # Add/update the post meta of the product
        add_post_meta($post_id, 'rentman_imported', true);
        add_post_meta($post_id, '_rentman_tax', $product['btw']);
        update_post_meta($post_id, '_visibility', 'visible');
        update_post_meta($post_id, '_stock_status', 'instock');
        update_post_meta($post_id, 'total_sales', '0');
        update_post_meta($post_id, '_downloadable', 'no');
        update_post_meta($post_id, '_virtual', 'no');
        update_post_meta($post_id, '_regular_price', $product['cost']);
        update_post_meta($post_id, '_purchase_note', "");
        update_post_meta($post_id, '_featured', "no");
        update_post_meta($post_id, '_weight', $product['weight']);
        update_post_meta($post_id, '_length', $product['length']);
        update_post_meta($post_id, '_width', $product['width']);
        update_post_meta($post_id, '_height', $product['height']);
        update_post_meta($post_id, '_sku', $product['id']);
        update_post_meta($post_id, '_product_attributes', array());
        update_post_meta($post_id, '_sale_price_dates_from', "");
        update_post_meta($post_id, '_sale_price_dates_to', "");
        update_post_meta($post_id, '_price', $product['cost']);
        update_post_meta($post_id, '_sold_individually', "");
        update_post_meta($post_id, '_manage_stock', "no");
        update_post_meta($post_id, '_backorders', "no");
        update_post_meta($post_id, '_stock', $product['amount']);

        # Attach the media files to the post
        for ($x = 0; $x < sizeof($file_list[$product['id']]); $x++){
            attach_media($file_list[$product['id']][$x], $post_id, $product['id'], $x);
        }
    }

    // ------------- Various Functions ------------- \\

    # Delete products with a javascript function
    function reset_rentman(){
        $posts = get_product_list();
        $categoryIDs = get_option('plugin-rentmanIDs', []); // get global category array
        if (!is_array($categoryIDs)){
            $categoryIDs = [];
            update_option('plugin-rentmanIDs', $categoryIDs); // update if not an array
        }
        # Register and localize the admin_delete.js file, which handles the reset
        wp_register_script('admin_del_product', plugins_url('js/admin_delete.js', __FILE__ ));
        wp_localize_script('admin_del_product', 'products', $posts);
        wp_localize_script('admin_del_product', 'arrayindex', '0');
        wp_localize_script('admin_del_product', 'string1', __('<b>Producten verwijderd:</b> ', 'rentalshop'));
        wp_localize_script('admin_del_product', 'string2', __('<br>Resetten was succesvol!', 'rentalshop'));
        wp_enqueue_script('admin_del_product');
    }

    # Delete up to 15 products from the array starting with a certain index
    function delete_by_index($posts, $startIndex){
        global $wpdb;
        $endIndex = $startIndex + 9;
        for ($index = $startIndex; $index <= $endIndex; $index++){
            if ($index >= sizeof($posts))
                break;
            $object = $posts[$index];
            $product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $object));
            if (get_post_meta($product_id, 'rentman_imported', true) == true){
                # Delete product and the attached images
                $media = get_children(array('post_parent' => $product_id, 'post_type' => 'attachment'));
                foreach ($media as $file){
                    wp_delete_post($file->ID);
                }
                wp_delete_post($product_id);
            }
        }
    }

    # Checks if last modified date is different
    function check_updated($sku, $lastmodified){
        global $wpdb;
        $newestdate = substr($lastmodified, 0, 10) . ' ' . substr($lastmodified, 11, 8);
        $postID = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku));
        $getDate = get_the_date('c', $postID);
        $postdate = substr($getDate, 0, 10) . ' ' . substr($getDate, 11, 8);
        $rentDate = strtotime($newestdate);
        $wooDate = strtotime($postdate);
        if ($wooDate >= $rentDate)
            return false; # Has not been updated

        # Product has been updated, delete old post
        wp_delete_post($postID);
        return true;
    }

    # Returns list of identifiers when given a list of products
    function list_of_ids($prodList){
        $id_list = array();
        foreach ($prodList as $item){
            array_push($id_list, $item['id']);
        }
        return $id_list;
    }

    # Returns list of identifiers of all imported Rentman products
    function rentman_ids(){
        $full_product_list = array();
        $args = array('post_type' => 'product', 'posts_per_page' => -1, 'rentman_imported' => true);
        $pf = new WC_Product_Factory();
        $posts = get_posts($args);
        for ($x = 0; $x < sizeof($posts); $x++){
            $post = $posts[$x];
            $product = $pf->get_product($post->ID);
            array_push($full_product_list, $product->get_sku());
        }
        return $full_product_list;
    }

    # Register 'Rentable' Product Type for imported products
    function register_rental_product_type(){
        class WC_Product_Rentable extends WC_Product{ # Extending Product Class

            public function __construct($product){
                $this->product_type = 'rentable';
                parent::__construct($product);
            }

            public function add_to_cart_text(){
                return apply_filters('woocommerce_product_add_to_cart_text', __('Verder lezen','rentalshop'), $this);
            }

            public function add_to_cart_url(){
                $url = get_permalink($this->id);
                return apply_filters('woocommerce_product_add_to_cart_url', $url, $this);
            }

            public function needs_shipping()
            {
                return false;
            }
        }
    }

    # Make new product type selectable in Wordpress admin menu
    function add_rentable_product($types){
        $types['rentable'] = __('Rentable');
        return $types;
    }

    # Use database query to check if the product that
    # is going to be imported already exists
    function wp_exist_post_by_title($title){
        global $wpdb;
        $query = $wpdb->get_row("SELECT ID FROM wp_posts WHERE post_title = '" . $title . "' && post_status = 'publish'
            && post_type = 'product' ", 'ARRAY_N');
        if (empty($query)){
            return false; # Product does not exist
        } else{
            return true; # Product already exists
        }
    }
?>