<?php
    // ------------- Image File Attachment Functions ------------- \\

    # Attach image file from Rentman to product in Woocommerce
    function attach_media($fileUrl, $post_id, $sku, $count = 0){
        global $wpdb;
        $artDir = '/uploads/rentman/';
        $fileUrl = str_replace(' ', '%20', $fileUrl);

        # Create Rentman image directory if it somehow still
        # doesn't exist yet
        if(!file_exists(WP_CONTENT_DIR . $artDir)){
            mkdir(WP_CONTENT_DIR . $artDir);
        }

        # Get the extension and return when the image url is incorrect
        $ext = array_pop(explode(".", $fileUrl));
        if ($ext == '')
            return;
        $new_file_name = 'media-' . $sku . '-' . $count . '.' . $ext;
        $post_file_name = 'media-' . $sku . '-' . $count;
        $targetUrl = WP_CONTENT_DIR . $artDir . $new_file_name;

        # Delete old attachments with the same name
        $attachment = get_page_by_title($post_file_name, OBJECT, 'attachment');
        wp_delete_attachment($attachment->ID);

        if(!file_exists($targetUrl)){
            copy($fileUrl, $targetUrl);
        }
		$siteurl = get_option('siteurl');
		$file_info = getimagesize($targetUrl);

		# Create an array of attachment data to insert into wp_posts table
		$artdata = array(
			'post_author' => 1,
			'post_date' => current_time('mysql'),
			'post_date_gmt' => current_time('mysql'),
			'post_title' => $post_file_name,
			'post_status' => 'inherit',
			'comment_status' => 'closed',
			'ping_status' => 'closed',
			'post_name' => sanitize_title_with_dashes(str_replace("_", "-", $post_file_name)),
			'post_modified_gmt' => current_time('mysql'),
			'post_parent' => $post_id,
			'post_type' => 'attachment',
			'guid' => $siteurl.'/'.$artDir.$new_file_name,
			'post_mime_type' => $file_info['mime'],
			'post_excerpt' => '',
			'post_content' => ''
		);

		$uploads = wp_upload_dir();
		$save_path = $uploads['basedir'] . '/rentman/' . $new_file_name;

		# Insert database record
		$attach_id = wp_insert_attachment($artdata, $save_path, $post_id);
		if ($attach_data = wp_generate_attachment_metadata($attach_id, $save_path)){
			wp_update_attachment_metadata($attach_id, $attach_data);
		}

        # Check the amount of images
        # If there are more than one, add them to the image galery
        if ($count > 0){
            $gallery = get_post_meta($post_id,'_product_image_gallery');
            $newgallery = $attach_id;
            for ($y = 0; $y < sizeof($gallery); $y++){
                $newgallery = $newgallery . ',' . $gallery[$y];
            }
            update_post_meta($post_id,'_product_image_gallery',$newgallery);
        }
        else{
            # Make it the featured image of the post it is attached to
            $wpdb->insert($wpdb->prefix.'postmeta', array(
                'post_id' => $post_id,
                'meta_key' => '_thumbnail_id',
                'meta_value' => $attach_id));
        }
    }

    # Function that updates the images of products
    function update_images($token){
        # Get the endpoint url
        $url = receive_endpoint();
        # Update the images of all imported products
        $current_files = get_files(Array(), $token, $url, true);
        if (sizeof($current_files) == 0){
            _e('<b>Er zijn geen producten of afbeeldingen gevonden..</b>', 'rentalshop');
            return;
        }
        # Register and localize the admin_images.js file, which handles the image update
        wp_register_script('admin_add_images', plugins_url('js/admin_images.js', __FILE__ ));
        wp_localize_script('admin_add_images', 'images', $current_files);
        wp_localize_script('admin_add_images', 'arrayindex', (string)key($current_files));
        wp_localize_script('admin_add_images', 'totalsize', (string)sizeof($current_files));
        wp_localize_script('admin_add_images', 'string1', __('<b>Afbeeldingsgroepen klaar:</b> ', 'rentalshop'));
        wp_localize_script('admin_add_images', 'string2', __('<b>Afbeeldingen updaten geslaagd!</b> ', 'rentalshop'));
        wp_enqueue_script('admin_add_images');
    }

    # Returns list of image file url's for every product
    function get_files($prodList, $token, $url, $globalimages = false){
        $fileList = array();
        $message = json_encode(setup_file_request($token, $prodList, $globalimages), JSON_PRETTY_PRINT);
        $received = do_request($url, $message);

        # Get the list of files and return
        $parsed = json_decode($received, true);
        $parsed = parseResponse($parsed);

        foreach ($parsed['response']['items']['Files'] as $imgFile){
            if ($fileList[$imgFile['data']['item']] == null){
                $fileList[$imgFile['data']['item']] = array($imgFile['data']['url']);
            } else {
                array_push($fileList[$imgFile['data']['item']], $imgFile['data']['url']);
            }
        }
        return $fileList;
    }
?>