// ----- JavaScript functions for product deletion ----- \\

// Show import message in the menu
jQuery().ready(function()
{
    jQuery("#deleteStatus").html(string1 + "0%");
    applyAjax();
});

// Recursive function that sends product indices to PHP until the
// whole array has been covered
function applyAjax(){
    console.log('Delete List:');
    console.log(products);
    jQuery.ajax({
        type: "POST",
        url: 'admin.php?page=rentman-shop&delete_products',
        data: { prod_array : products, array_index : arrayindex },
        success: function(){
            var endindex = arrayindex + 10;
            if (endindex > products.length)
                endindex = products.length;
            if (products.length == 0)
                var percentage = 100;
            else
                var percentage = Math.round((endindex / products.length) * 100);
            jQuery("#deleteStatus").html(string1 + percentage + '%');
            arrayindex += 10;
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
    jQuery.ajax({
        type: "GET",
        url: 'admin.php?page=rentman-shop&remove_folders',
        data: '',
        success: function(){
            jQuery("#deleteStatus").append(string2);
        }
    });
}