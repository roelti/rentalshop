<?php // FUNCTIONS REGARDING IMPORT OF PRODUCTS

    // ------------- Main Product Import Functions ------------- \\

    # Handles import of products from Rentman to your WooCommerce Shop
    function import_products($token){
        if ($token == "fail"){
            _e('<h4>Importeren mislukt! Kloppen uw inloggegevens wel?</h4>','rentalshop');
        } else{
            $date = date('l jS \of F Y h:i:s A');
            update_option('plugin-lasttime', $date);

            # Get the endpoint url
            $url = receive_endpoint();
            # First import Folders
            $message = json_encode(setup_folder_request($token), JSON_PRETTY_PRINT);
            $received = do_request($url, $message);
            $parsed = json_decode($received, true);
            $parsed = parseResponse($parsed);
			
			$folder_arr = arrange_folders($parsed);
			
            # Create new Categories by using the imported folder data
            foreach ($folder_arr as $folder) {
                add_category($folder);
            }
            # Now import Products
            $message = json_encode(setup_import_request($token), JSON_PRETTY_PRINT);
            # Send Request & Receive Response
            $received = do_request($url, $message);
            $parsed = json_decode($received, true);
            $parsed = parseResponse($parsed);
            # Receive id's of first and last ID in response
            $listLength = sizeof($parsed['response']['links']['Materiaal']);
            if ($listLength > 0){ # At least one product has been found
                $firstItem = $parsed['response']['links']['Materiaal'][0];
                $lastItem = $parsed['response']['links']['Materiaal'][$listLength-1];
                # Array containing all imported products & array containing image files
                $prod_array = convert_items($parsed, $firstItem, $lastItem);
                $file_array = get_files($prod_array, $token, $url);
                # If one or more products have been found, call javascript function
                if (sizeof($prod_array) > 0){ # Prepare Script
                    wp_register_script('admin_add_product', plugins_url('js/admin_import.js', __FILE__ ));
                    wp_localize_script('admin_add_product', 'products', $prod_array);
                    wp_localize_script('admin_add_product', 'folders', $file_array);
                    wp_localize_script('admin_add_product', 'arrayindex', 0);
                    wp_localize_script('admin_add_product', 'string1', __('<b>Producten klaar:</b> ','rentalshop'));
                    wp_localize_script('admin_add_product', 'string2', __('<br>Verwerken..','rentalshop'));
                    wp_localize_script('admin_add_product', 'string3', __('<br>Irrelevante producten en mappen zijn verwijderd','rentalshop'));
                    wp_localize_script('admin_add_product', 'string4', __('<h3>Importeren geslaagd!</h3>','rentalshop'));
                    wp_enqueue_script('admin_add_product');
                } else{
                    _e('<br><b>Er zijn geen nieuwe Rentman producten gevonden!</b> ','rentalshop');
                    remove_empty_categories();
                }
            } else { # No rentable products have been found
                _e('<br>Er zijn geen producten gevonden op uw Rentman account..<br>','rentalshop');
            }
        }
    }

    # Function that updates the images of products
    function update_images($token){
        # Get the endpoint url
        $url = receive_endpoint();
        # Update the images of all imported products
        $current_files = get_files(Array(), $token, $url, true);
        if (sizeof($current_files) == 0){
            _e('<b>Er zijn geen afbeeldingen gevonden..</b>','rentalshop');
            return;
        }
        # Call javascript function
        wp_register_script('admin_add_images', plugins_url('js/admin_images.js', __FILE__ ));
        wp_localize_script('admin_add_images', 'images', $current_files);
        wp_localize_script('admin_add_images', 'arrayindex', key($current_files));
        wp_localize_script('admin_add_images', 'totalsize', sizeof($current_files));
        wp_localize_script('admin_add_images', 'string1', __('<b>Afbeeldingen klaar:</b> ','rentalshop'));
        wp_localize_script('admin_add_images', 'string2', __('<b>Afbeeldingen updaten geslaagd!</b> ','rentalshop'));
        wp_enqueue_script('admin_add_images');
    }

    # Imports five products from the product array
    function array_to_product($prod_array, $file_array, $startIndex){
        if (sizeof($prod_array) > 0){
            _e('<br><br><b>Geimporteerde Producten:</b><br>','rentalshop');
            # Import the products in the WooCommerce webshop
            $endIndex = $startIndex + 4;
            for ($index = $startIndex; $index <= $endIndex; $index++){
                if ($index >= sizeof($prod_array))
                    break;
                import_product($prod_array[$index], $file_array);
            }
        }
    }

    # Converts products in API response to arrays and
    # adds them to one long list
    function convert_items($parsed, $lower, $higher)
    {
        $prodList = array(); # array that stores new/updates products
        $checkList = array(); # array containing id's of all checked products

        for ($x = $lower; $x <= $higher; $x++) {
            # Get name, price, description, category and last modified date from current product
            $name = trim($parsed['response']['items']['Materiaal'][$x]['data']['naam']);
            $cost = $parsed['response']['items']['Materiaal'][$x]['data']['verhuurprijs'];
            $longdesc = $parsed['response']['items']['Materiaal'][$x]['data']['shop_description_long'];
            $shortdesc = $parsed['response']['items']['Materiaal'][$x]['data']['shop_description_short'];
            $fulldesc = $parsed['response']['items']['Materiaal'][$x]['data']['omschrijving'];
            $modDate = $parsed['response']['items']['Materiaal'][$x]['data']['modified'];
            # Set correct folder data
            $folderID = $parsed['response']['items']['Materiaal'][$x]['data']['folder'];
            $weight = $parsed['response']['items']['Materiaal'][$x]['data']['gewicht'];
            $btwcode = $parsed['response']['items']['Materiaal'][$x]['data']['btwcode'];
            $btw = $parsed['response']['items']['Btwcode'][$btwcode]['data']['tarief'];
            $parent_term = get_term_by('slug', $folderID, 'product_cat'); // array is returned if taxonomy is given
            $kate = $parent_term->name;

            # If current item doesn't have a name (probably junk object)
            if ($name == "")
                continue;

            # Check if product already exists in database
            $noDiff = false;
            if (wp_exist_post_by_title($name)){
                # Post Exists, now check update time
                $updated = check_updated($name, $modDate);
                if ($updated == false){
                    # Post has not been updated
                    $noDiff = true;
                }
            }

            # Products has been checked
            array_push($checkList, $name);

            if($noDiff){ # Product already exists and has not been updated
                continue;
            } else { # Product does not exist yet or has been updated, so add it to the array
                if ($longdesc == '')
                    $longdesc = $fulldesc;
                array_push($prodList, array($x, $name, $cost, $longdesc, $shortdesc, $kate, $modDate, $weight, $btw));
            }
        }
        # Delete products in woocommerce shop that are not in the product list
        compare_and_delete($checkList);

        return $prodList; # Return array of objects for later use
    }

    # Compare the list of updated products with the current product list
    # and delete all products that aren't available in Rentman anymore
    function compare_and_delete($checkList){
        global $wpdb;
        $all_products = get_product_list();
        $remainder = array_diff($all_products, $checkList);
        if (sizeof($remainder) > 0) {
            # Now delete the posts in the remainder array
            foreach ($remainder as $item) {
                $postID = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_title = '" . $item . "'");
                wp_delete_post($postID);
            }
        }
    }

    # Get all rentable products from Woocommerce shop
    function get_product_list() {
        $full_product_list = array();
        $args = array('post_type' => 'product', 'posts_per_page' => -1, 'product_type' => 'rentable');
        $posts = get_posts($args);
        for ($x = 0; $x < sizeof($posts); $x++) {
            $product = $posts[$x];
            array_push($full_product_list, $product->post_title);
        }
        return $full_product_list;
    }

    # Use the array of imported projects from Rentman to create
    # new products in WooCommerce
    function import_product($product, $file_list)
    {
        if ( empty( $product[3] ) ) {
            $content = __('Geen informatie beschikbaar', 'rentalshop');
        } else {
            $content = $product[3];
        }

        # Create new product
        $new_product = array(
            "post_name" => $product[1],
            "post_title" => $product[1],
            "post_content" => $content,
            "post_excerpt" => $product[4],
            "post_date" => $product[6],
            "post_status" => "publish",
            "post_type" => "product"
        );

        # Check Category
        $checkterm = get_term_by('name', $product[5], 'product_cat');

        # Insert post
        $post_id = wp_insert_post($new_product, TRUE);

        # Other method for setting category
        wp_set_object_terms($post_id, $checkterm->term_id, 'product_cat');
        wp_set_object_terms($post_id, 'rentable', 'product_type');

        # Add/update the post meta
        add_post_meta($post_id, 'rentman_imported', true);
        add_post_meta($post_id, '_rentman_tax', $product[8]);
        update_post_meta($post_id, '_visibility', 'visible');
        update_post_meta($post_id, '_stock_status', 'instock');
        update_post_meta($post_id, 'total_sales', '0');
        update_post_meta($post_id, '_downloadable', 'no');
        update_post_meta($post_id, '_virtual', 'no');
        update_post_meta($post_id, '_regular_price', $product[2]);
        update_post_meta($post_id, '_purchase_note', "");
        update_post_meta($post_id, '_featured', "no");
        update_post_meta($post_id, '_weight', $product[7]);
        update_post_meta($post_id, '_length', "");
        update_post_meta($post_id, '_width', "");
        update_post_meta($post_id, '_height', "");
        update_post_meta($post_id, '_sku', $product[0]);
        update_post_meta($post_id, '_product_attributes', array());
        update_post_meta($post_id, '_sale_price_dates_from', "");
        update_post_meta($post_id, '_sale_price_dates_to', "");
        update_post_meta($post_id, '_price', $product[2]);
        update_post_meta($post_id, '_sold_individually', "");
        update_post_meta($post_id, '_manage_stock', "no");
        update_post_meta($post_id, '_backorders', "no");
        update_post_meta($post_id, '_stock', "aantal");

        # Attach Media File
        for ($x = 0; $x < sizeof($file_list[$product[0]]); $x++){
            attach_media($file_list[$product[0]][$x], $post_id, $product[0], $x);
        }
    }

    // ------------- API Requests ------------- \\

    # Returns API request ready to be encoded in Json
    # For importing products
    function setup_import_request($token){
        $object_data = array(
            "requestType" => "query",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "4.2.0"
            ),
            "account" => get_option('plugin-account'),
            "token" => $token,
            "itemType" => "Materiaal",
            "columns" => array(
                "Materiaal" => array(
                    "naam",
                    "verhuurprijs",
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
                    "img",
                    array(
                        "btwcode" => array(
                            "tarief"
                        )
                    )
                )
            ),
            "query" => array("in_shop" => true)
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
                "version" => "4.2.0"
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

    // ------------- Various Functions ------------- \\

    # Delete products with a javascript function
    function reset_rentman(){
        $posts = get_product_list();
        wp_register_script('admin_del_product', plugins_url('js/admin_delete.js', __FILE__ ));
        wp_localize_script('admin_del_product', 'products', $posts);
        wp_localize_script('admin_del_product', 'arrayindex', 0);
        wp_localize_script('admin_del_product', 'string1', __('<b>Producten verwijderd:</b> ','rentalshop'));
        wp_localize_script('admin_del_product', 'string2', __('<br>Resetten was succesvol!','rentalshop'));
        wp_enqueue_script('admin_del_product');
    }

    # Delete up to 15 products from the array starting with a certain index
    function delete_by_index($posts, $startIndex){
        global $wpdb;
        $endIndex = $startIndex + 9;
        for ($index = $startIndex; $index <= $endIndex; $index++) {
            if ($index >= sizeof($posts))
                break;
            $object = $posts[$index];
            $product_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $object . "'" );
            $material = wc_get_product($product_id);
            if ($material->product_type == 'rentable'){
                # Delete product and attached image
                $sku = $material->sku;
                $image_name = 'media-'.$sku.'-0';
                $image_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $image_name . "'" );
                wp_delete_post($image_id);
                wp_delete_post($product_id);
            }
        }
    }

    # Checks if last modified date is different
    function check_updated($name, $lastmodified){
        global $wpdb;
        $newestdate = substr($lastmodified, 0, 10) . ' ' . substr($lastmodified, 11, 8);
        $postdate = $wpdb->get_var( "SELECT post_date FROM $wpdb->posts WHERE post_title = '" . $name . "'" );
        $postID = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $name . "'" );

        if ($postdate == $newestdate)
            return false; # Has not been updated

        # Product has been updated, delete old post
        wp_delete_post($postID);
        return true;
    }

    # Returns list of identifiers when given a list of products
    function list_of_ids($prodList){
        $id_list = array();
        foreach ($prodList as $item){
            array_push($id_list, $item[0]);
        }
        return $id_list;
    }

    # Returns list of identifiers of all imported Rentman products
    function rentman_ids(){
        $full_product_list = array();
        $args = array('post_type' => 'product', 'posts_per_page' => -1, 'product_type' => 'rentable');
        $pf = new WC_Product_Factory();
        $posts = get_posts($args);
        for ($x = 0; $x < sizeof($posts); $x++) {
            $post = $posts[$x];
            $product = $pf->get_product($post->ID);
            array_push($full_product_list, $product->get_sku());
        }
        return $full_product_list;
    }

    # Register 'Rentable' Product Type for imported products
    function register_rental_product_type() {
        class WC_Product_Rentable extends WC_Product { # Extending Product Class

            public function __construct( $product ) {
                $this->product_type = 'rentable';
                parent::__construct($product);
            }

            public function add_to_cart_text() {
                return apply_filters('woocommerce_product_add_to_cart_text', __('Verder lezen','rentalshop'), $this);
            }

            public function add_to_cart_url() {
                $url = get_permalink($this->id);
                return apply_filters( 'woocommerce_product_add_to_cart_url', $url, $this );
            }

            public function needs_shipping()
            {
                return false;
            }
        }
    }

    # Make new product type selectable
    function add_rentable_product( $types ){
        $types[ 'rentable' ] = __('Rentable');
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
        } else {
            return true; # Product already exists
        }
    }
?>