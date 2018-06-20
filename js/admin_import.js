// ------------- V4.20.2 ------------- \\
// ----- JavaScript functions for product import ----- \\

// Show import message in the menu
jQuery().ready(function(){
    jQuery("#importMelding").show();
    jQuery("#importStatus").html(string1 + "0 / " + products.length);
    applyAjax();
});

// Recursive function that sends product indices to PHP until the
// whole array has been covered
function applyAjax(){
    jQuery(".lasttime").html(pluginlasttime);
    jQuery.ajax({
        type: "POST",
        url: 'admin.php?page=rentman-shop&import_products',
        datatype: "json",
        data: JSON.stringify({ file_array : folders, array_index : arrayindex, prod_array : products, pdf_array : pdfs, basic_to_advanced: basictoadvanced}),
    	contentType: 'application/json; charset=utf-8',
        success: function(){
        	  console.log('Current Array Index:');
    		    console.log(arrayindex);
            var endindex = parseInt(arrayindex) + 5;
            if (endindex > products.length)
                endindex = products.length;
            jQuery("#importStatus").html(string1 + endindex + " / " + products.length);
            arrayindex = parseInt(arrayindex) + 5;
            if (arrayindex < products.length){
                applyAjax();
            } else {
                if(basictoadvanced == 2) {
                  basictoAdvancedFirstImport();
                }
                removeFolders();
            }
        }
    });
}

function basictoAdvancedFirstImport(){
    jQuery.ajax({
        type: "GET",
        url: 'admin.php?page=rentman-shop&basic_to_advanced',
        data: '',
        success: function(){
            console.log("First import from basic to advanced done");
        }
    });
}

// Calls PHP function that removes all empty product categories from WooCommerce
function removeFolders(){
    jQuery("#importStatus").append(string2);
    jQuery("#taxWarning").html('<br>' + taxWarning);
    jQuery.ajax({
        type: "GET",
        url: 'admin.php?page=rentman-shop&remove_folders',
        data: '',
        success: function(){
            jQuery("#importStatus").append(string3);
            jQuery("#importMelding").html(string4);
        }
    });
}
