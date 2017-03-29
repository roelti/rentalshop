// ----- JavaScript functions for product import ----- \\

// Show import message in the menu
jQuery().ready(function()
{
    jQuery("#importMelding").show();
    jQuery("#importStatus").html(string1 + "0 / " + products.length);
    console.log('Products Imported:');
    console.log(products);
    applyAjax();
});

// Recursive function that sends product indices to PHP until the
// whole array has been covered
function applyAjax(){
    jQuery.ajax({
        type: "POST",
        url: 'admin.php?page=rentman-shop&import_products',
        datatype: "json",
        data: JSON.stringify({ file_array : folders, array_index : arrayindex, prod_array : products}),
    	contentType: 'application/json; charset=utf-8',
        success: function(){
        	console.log('Current Array Index:');
    		console.log(arrayindex);
            var endindex = arrayindex + 5;
            if (endindex > products.length)
                endindex = products.length;
            jQuery("#importStatus").html(string1 + endindex + " / " + products.length);
            arrayindex += 5;
            if (arrayindex < products.length){
                applyAjax();
            } else {
                removeFolders();
            }
        }
    });
}

// Calls PHP function that removes all empty product categories from WooCommerce
function removeFolders(){
    jQuery("#importStatus").append(string2);
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