jQuery().ready(function()
{
    jQuery("importMelding").show();
    //import categories
    jQuery("ul#importstatus").append("<li>Importing categories</li>");

    var numProducts = 0;
    jQuery.get( "admin.php?page=rentman&import=import_categories", function( data )
    {
        data = JSON.parse(data);

        if(data.status == "error")
        {
            alert("Er ging iets mis: "+data.error);
            return;
        }

        importProducts(1,50);
    })
    .fail(function()
    {
        data = JSON.parse(data);
        alert(data.error);
        return;
    })
});

function importProducts(van,tot)
{
    jQuery("ul#importstatus").append("<li>Importing products "+van+" - "+tot+"</li>");

    jQuery.get( "admin.php?page=rentman&import=import_products&van="+van+"&tot="+tot, function( data )
    {
        data = JSON.parse(data);

        if(data.status == "error")
        {
            alert("Er ging iets mis: "+data.error);
            return;
        }

        numProducts = data.products;

        if(numProducts > tot)
            importProducts(tot+1,tot+50);
        else
            deleteProducts();
    })
    .fail(function()
    {
        data = JSON.parse(data);
        alert(data.error);
    })
}

function deleteProducts()
{
    jQuery("ul#importstatus").append("<li>Deleting unused products</li>");

    jQuery.get( "admin.php?page=rentman&import=import_delete_products", function( data )
    {
        data = JSON.parse(data);

        if(data.status == "error")
        {
            alert("Er ging iets mis: "+data.error);
            return;
        }

        importCrossSells(1,50);
    })
        .fail(function()
        {
            data = JSON.parse(data);
            alert(data.error);
        })
}

function importCrossSells(van,tot)
{
    jQuery("ul#importstatus").append("<li>Importing linked products "+van+" - "+tot+"</li>");

    jQuery.get( "admin.php?page=rentman&import=import_cross_sells&van="+van+"&tot="+tot, function( data )
    {
        data = JSON.parse(data);

        if(data.status == "error")
        {
            alert("Er ging iets mis: "+data.error);
            return;
        }

        numProducts = data.products;

        if(numProducts > tot)
            importCrossSells(tot+1,tot+50);
        else
            jQuery("ul#importstatus").append("<li style='color: green;'>Import complete!</li>");
    })
    .fail(function()
    {
        data = JSON.parse(data);
        alert(data.error);
    })
}