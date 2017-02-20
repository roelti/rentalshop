// ----- JavaScript functions for product import ----- \\

// Show import message in the menu
jQuery().ready(function()
{
    jQuery("#importMelding").show();
    jQuery("#importStatus").html(string1 + "0 / " + products.length);
    console.log('jQuery works, now loading applyAjax function');
    applyAjax();
});

// Recursive function that sends product indices to PHP until the
// whole array has been covered
function applyAjax(){
    console.log('Arrived at applyAjax');
    jQuery.ajax({
        type: "POST",
        url: 'admin.php?page=rentman-shop&import_products',
        data: { prod_array : products, file_array : folders, array_index : arrayindex },
        success: function(){
            console.log('Ajax call success!');
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