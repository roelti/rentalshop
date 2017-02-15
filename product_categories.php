<?php // FUNCTIONS REGARDING THE IMPORTED PRODUCT CATEGORIES HERE

    // ------------- Rentman Product Category Functions ------------- \\

    # Names of (sub)folders have been imported
    # Create the categories in Wordpress
    function add_category($folder){
        $parent_term = get_term_by('slug', $folder[2], 'product_cat'); // array is returned if taxonomy is given
        $parent_term_id = $parent_term->term_id;

        # Insert WooCommerce Term
        wp_insert_term(
            $folder[1], # Name of term
            'product_cat', # Taxonomy
            array(
                'slug' => $folder[0],
                'parent' => $parent_term_id,
                'description' => 'Rentman'
            )
        );
    }

    # Arranges folder data from API response
    function arrange_folders($parsed){
        $folderList = array();
        $switch = true;
        $counter = 1;
        while ($switch){
            $name = $parsed['response']['items']['Folder'][$counter]['data'][0];
            $id = $parsed['response']['items']['Folder'][$counter]['data'][1];
            $parent = $parsed['response']['items']['Folder'][$counter]['data'][2];

            if ($name == 'Materialen')
                $switch = false;
            else{
                array_push($folderList, array($id, $name, $parent));
                $counter++;
            }
        }
        return $folderList;
    }

    # Hide all irrelevant product subcategories in the webshop (so the empty ones)
    function remove_empty_categories(){
        $args = array(
            'taxonomy'     => 'product_cat',
            'orderby'      => 'name',
            'show_count'   => 0,
            'pad_counts'   => 0,
            'hierarchical' => 1,
            'title_li'     => '',
            'hide_empty'   => 0
        );
        $all_categories = get_categories($args);
        foreach($all_categories as $cat){
            if($cat->category_parent == 0){
                $children = get_categories(array('taxonomy' => 'product_cat', 'parent' => $cat->term_id, 'hide_empty' => 0));
                $itemCount = max($cat->count, displayChildren($children));
                if ($itemCount == 0){
                    if ($cat->description == 'Rentman'){
                        wp_delete_term($cat->term_id, 'product_cat');
                    }
                }
            }
        }
    }

    # Check item count for children
    function displayChildren($children){
        $itemCount = 0;
        foreach ($children as $child){
            $itemCount = max($itemCount, $child->count);
            $grandchildren = get_categories( array ('taxonomy' => 'product_cat', 'parent' => $child->term_id, 'hide_empty' => 0));
            $thiscount = displayChildren($grandchildren);
            $itemCount = max($itemCount, $thiscount);
            if (max($thiscount, $child->count) == 0){
                if ($child->description == 'Rentman'){
                    wp_delete_term($child->term_id, 'product_cat');
                }
            }

        }
        return $itemCount;
    }

    # Returns API request ready to be encoded in Json
    # For importing folders
    function setup_folder_request($token){
        $object_data = array(
            "requestType" => "query",
            "client" => array(
                "language" => "1",
                "type" => "webshopplugin",
                "version" => "2"
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
?>