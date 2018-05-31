<?php
    // ------------- Image File Attachment Functions ------------- \\
    # Attach image file from Rentman to product in Woocommerce
    function attach_pdf($files, $post_id, $sku, $product_name, $token){
      if (apply_filters('rentman/attaching_media', true)) {
          global $wpdb;
          $artDir = '/uploads/rentman/';

          # Create Rentman image directory if it somehow still
          # doesn't exist yet
          if (!file_exists(WP_CONTENT_DIR . $artDir)) {
              mkdir(WP_CONTENT_DIR . $artDir);
          }

          #SEARCH FOR ALL THE PDFS RELATED TO $post_id
          $pdfs = get_children( array (
            'post_parent' => $post_id,
            'post_type' => 'attachment',
            'post_mime_type' => 'application/pdf',
            'orderby' => 'ID',
            'order' => 'ASC'
          ));

          # Force update for all the products, necessary on first import when the plugin is upgraded from Basic to Advanced.
          # After the import is completely done the option will be set to '1' again. (see also rentman.php)
          if(BASICTOADVANCED == 2){
              foreach ( $pdfs as $attachment_id => $attachment ) {
                  wp_delete_attachment($attachment->ID, true);
              }
          }

          # If product already has pdfs in WordPress and not first import from basic to advanced
          if (!empty($pdfs) && BASICTOADVANCED != 2) {
            #Create an array containing all the existing wordpress pdfs: [][1] = rentmanID, [][2] = WordPressID
            foreach ( $pdfs as $attachment_id => $attachment ) {
              $pdf_id_rentman = explode("-", $attachment->post_title);
              $filescheck[] =  array($attachment->post_title, $pdf_id_rentman[count($pdf_id_rentman) - 1], $attachment->ID, $attachment->post_modified);
            }

            #CHECK WHAT IMAGES NEED TO BE DELETED IN WooCommerce
            for ($y = 0; $y < sizeof($filescheck); $y++) {
              $found = false;
              for ($z = 0; $z < sizeof($files); $z++) {
                if($filescheck[$y][1] == $files[$z][1]){
                  $found = true;

                  #If Rentman modified date is greater then WordPress modified date update modified dates in wordpress and update alt text of image
                  if($files[$z][3] > convertdate($filescheck[$y][3])){
                    $pdfdata = array(
                        'ID' => $filescheck[$y][2],
                        'post_date' => $files[$z][3],
                        'post_date_gmt' => $files[$z][3]
                    );
                    wp_update_post($pdfdata);
                    //update_post_meta($filescheck[$y][2], '_wp_attachment_image_alt', str_replace('"', '', preg_replace('~[\r\n]+~', ' ', $files[$z][2])));
                  }
                  break;
                }
              }
              if(!$found) {
                wp_delete_attachment($filescheck[$y][2], true);
              }
            }

            #CHECK WHAT WE NEED TO DO WITH THE RENTMAN PDFS (nothing changed or new insert)
            $pdfs = "";

            for ($y = 0; $y < sizeof($files); $y++) {
              $found = false;
              for ($z = 0; $z < sizeof($filescheck); $z++) {
                if($files[$y][1] == $filescheck[$z][1]){
                  $found = true;
                  $files[$y][2] = $filescheck[$z][0];
                  $files[$y][3] = $filescheck[$z][2];
                  break;
                }
              }

              #Pdf not found, insert new pdf
              $fileInfo = "";
              if(!$found) {
                $fileInfo = add_new_attachment($files[$y], $product_name, $artDir, $post_id, $token);
                if($fileInfo !== null) {
                    $files[$y][3] =  $fileInfo[0];
                    if ($attach_data = wp_generate_attachment_metadata($fileInfo[0], $fileInfo[1])) {
                        wp_update_attachment_metadata($fileInfo[0], $attach_data);
                    }
                }
              }

              if($fileInfo !== null) {
                  if ($y == 0) {
                    $pdfs = $files[$y][3];
                  } else {
                    $pdfs.=  ',' . $files[$y][3];
                  }
              }
            }
            update_post_meta($post_id,'_pdf_id', $pdfs);
          }else{
            $newpdfs = "";
            for ($x = 0; $x < sizeof($files); $x++){
                $fileInfo = add_new_attachment($files[$x], $product_name, $artDir, $post_id, $token);

                if ($attach_data = wp_generate_attachment_metadata($fileInfo[0], $fileInfo[1])) {
                    wp_update_attachment_metadata($fileInfo[0], $attach_data);
                }

                # Check the amount of pdfs
                # If there are more than one, add them to the image galery
                if ($x == 0) {
                    $newpdfs = $fileInfo[0];
                }else{
                    $newpdfs.=  ',' . $fileInfo[0];
                }
            }
            if ($newpdfs != "") {
                update_post_meta($post_id,'_pdf_id', $newpdfs);
            }
          }
      }
    }

    function attach_media($files, $post_id, $sku, $product_name, $token){
        if (apply_filters('rentman/attaching_media', true)) {
            global $wpdb;
            $artDir = '/uploads/rentman/';

            # Create Rentman image directory if it somehow still
            # doesn't exist yet
            if (!file_exists(WP_CONTENT_DIR . $artDir)) {
                mkdir(WP_CONTENT_DIR . $artDir);
            }

            #SEARCH FOR ALL THE IMAGES RELATED TO $post_id
            $images = get_children( array (
              'post_parent' => $post_id,
              'post_type' => 'attachment',
              'post_mime_type' => 'image',
              'orderby' => 'ID',
              'order' => 'ASC'
            ));

            # Force update for all the products, necessary on first import when the plugin is upgraded from Basic to Advanced.
            # After the import is completely done the option will be set to '1' again. (see also rentman.php)
            if(BASICTOADVANCED == 2){
                foreach ( $images as $attachment_id => $attachment ) {
                    wp_delete_attachment($attachment->ID, true);
                }
            }

            # If product already has images in WordPress and not first import from basic to advanced
            if (!empty($images) && BASICTOADVANCED != 2) {
              #Create an array containing all the existing wordpress images: [][1] = rentmanID, [][2] = WordPressID
              foreach ( $images as $attachment_id => $attachment ) {
                $image_id_rentman = explode("-", $attachment->post_title);
                $filescheck[] =  array($attachment->post_title, $image_id_rentman[count($image_id_rentman) - 1], $attachment->ID, $attachment->post_modified);
              }

              #CHECK WHAT IMAGES NEED TO BE DELETED IN WooCommerce
              for ($y = 0; $y < sizeof($filescheck); $y++) {
                $found = false;
                for ($z = 0; $z < sizeof($files); $z++) {
                  if($filescheck[$y][1] == $files[$z][1]){
                    $found = true;

                    #If Rentman modified date is greater then WordPress modified date update modified dates in wordpress and update alt text of image
                    if($files[$z][3] > convertdate($filescheck[$y][3])){
                      $imgdata = array(
                          'ID' => $filescheck[$y][2],
                          'post_date' => $files[$z][3],
                          'post_date_gmt' => $files[$z][3]
                      );
                      wp_update_post($imgdata);
                      update_post_meta($filescheck[$y][2], '_wp_attachment_image_alt', str_replace('"', '', preg_replace('~[\r\n]+~', ' ', $files[$z][2])));
                    }
                    break;
                  }
                }
                if(!$found) {
                  wp_delete_attachment($filescheck[$y][2], true);
                }
              }

              #CHECK WHAT WE NEED TO DO WITH THE RENTMAN IMAGES (nothing changed or new insert)
              $updategallery = "";
              $updatethumbnail = "";

              for ($y = 0; $y < sizeof($files); $y++) {
                $found = false;
                for ($z = 0; $z < sizeof($filescheck); $z++) {
                  if($files[$y][1] == $filescheck[$z][1]){
                    $found = true;
                    $files[$y][2] = $filescheck[$z][0];
                    $files[$y][3] = $filescheck[$z][2];
                    break;
                  }
                }

                #Image not found, insert new image
                $fileInfo = "";
                if(!$found) {
                  $fileInfo = add_new_attachment($files[$y], $product_name, $artDir, $post_id, $token);
                  if($fileInfo !== null) {
                      $files[$y][3] =  $fileInfo[0];
                      if ($attach_data = wp_generate_attachment_metadata($fileInfo[0], $fileInfo[1])) {
                          wp_update_attachment_metadata($fileInfo[0], $attach_data);
                      }
                  }
                }

                if($fileInfo !== null) {
                    if ($y > 0) {
                      if($y == 1) {
                        $updategallery = $files[$y][3];
                      }else{
                        $updategallery.=  ',' . $files[$y][3];
                      }
                    } else {
                      $updatethumbnail = $files[$y][3];
                    }
                }
              }
              update_post_meta($post_id, '_thumbnail_id', $updatethumbnail);
              update_post_meta($post_id, '_product_image_gallery', $updategallery);
            }else{
              $newgallery = "";

              for ($x = 0; $x < sizeof($files); $x++){
                $fileInfo = add_new_attachment($files[$x], $product_name, $artDir, $post_id, $token);
                if($fileInfo !== null) {
                    if ($attach_data = wp_generate_attachment_metadata($fileInfo[0], $fileInfo[1])) {
                        wp_update_attachment_metadata($fileInfo[0], $attach_data);
                    }

                    # Check the amount of images
                    # If there are more than one, add them to the image galery
                    if ($x > 0) {
                        if($x == 1) {
                          $newgallery = $fileInfo[0];
                        }else{
                          $newgallery.=  ',' . $fileInfo[0];
                        }
                    } else {
                        update_post_meta($post_id, '_thumbnail_id', $fileInfo[0]);
                    }
                }
              }
              if ($newgallery != "") {
                update_post_meta($post_id, '_product_image_gallery', $newgallery);
              }
            }
        }
    }

    # Function that inserts a new image for a product
    function add_new_attachment($file, $productName, $artDir, $postId, $token){
      # Get url of file based on the id of the file
      $url = receive_endpoint();
      $message = json_encode(get_file_url($token, $file[1]), JSON_PRETTY_PRINT);
      $received = do_request($url, $message);
      $parsed = json_decode($received, true);
      $parsed = parseResponse($parsed);
      $file[0] = $parsed['response']['items']['Files'][$file[1]]['data']['url'];
      $mimetype = $parsed['response']['items']['Files'][$file[1]]['data']['type'];
      if($mimetype=="application/pdf"){
        $productName=substr($parsed['response']['items']['Files'][$file[1]]['data']['name'],0,-4);
      }
      $fileUrl = str_replace(' ', '%20', $file[0]);
      $rentman_id = $file[1];     

      # Get the extension and return when the file is incorrect
      $ext = pathinfo(parse_url($fileUrl)['path'], PATHINFO_EXTENSION);
      if ($ext == '')
          return;

      # Create a seo friendly filename for the image based on the product name
      $new_file_name = sanitize_title_with_dashes(str_replace("_", "-", $productName)) . '-' . $rentman_id . '.' . $ext;
      $post_file_name = sanitize_title_with_dashes(str_replace("_", "-", $productName)) . '-' . $rentman_id;
      $productName = iconv('UTF-8', 'US-ASCII//TRANSLIT', $productName);
      $guid = strtolower(sanitize_file_name(str_replace("_", "-", $productName)) . '-' . $rentman_id . '.' . $ext);
      $targetUrl = WP_CONTENT_DIR . $artDir . $guid;

      if($mimetype=="application/pdf"){
        //$productName=substr($parsed['response']['items']['Files'][$file[1]]['data']['name'],0,-4);
        $post_file_name = $parsed['response']['items']['Files'][$file[1]]['data']['description'];
        $post_file_name = str_replace('"', '', preg_replace('~[\r\n]+~', ' ', $post_file_name));
      }

      $targetUrl = WP_CONTENT_DIR . $artDir . $guid;

      if (!file_exists($targetUrl)) {
          copy($fileUrl, $targetUrl);
      }

      $file_info = mime_content_type($targetUrl);

      if($file_info != "application/pdf" && $file_info != "image/jpg" && $file_info != "image/jpeg" && $file_info != "image/png" && $file_info != "image/gif") {
          unlink($targetUrl);
          return;
      }

      $siteurl = get_option('siteurl');

      # Create an array of attachment data to insert into wp_posts table
      $artdata = array(
          'post_author' => 1,
          'post_date' => $file[3],
          'post_date_gmt' => $file[3],
          'post_title' => $post_file_name,
          'post_status' => 'inherit',
          'comment_status' => 'closed',
          'ping_status' => 'closed',
          'post_name' => $post_file_name,
          'post_modified' => current_time('mysql'),
          'post_modified_gmt' => current_time('mysql'),
          'post_parent' => $postId,
          'post_type' => 'attachment',
          'guid' => $siteurl . '/wp-content' . $artDir . $guid,
          'post_mime_type' => $file_info,
          'post_excerpt' => '',
          'post_content' => ''
      );


      $uploads = wp_upload_dir();
      $save_path = $uploads['basedir'] . '/rentman/' . $guid;

      # Insert database record
      $returnId = wp_insert_attachment($artdata, $save_path, $postId);

      # Add alt text to image
      if($file_info != "application/pdf"){
        update_post_meta($returnId, '_wp_attachment_image_alt', str_replace('"', '', preg_replace('~[\r\n]+~', ' ', $file[2])));
      }

      $fileInformation = array($returnId, $save_path);
      return $fileInformation;
    }
?>
