// ----- JavaScript functions for tabs in wp-admin ----- \\
// Make the tabs accessible without reloading the page
jQuery().ready(function(){
    jQuery("a.nav-tab").click(function() {
        jQuery("a.nav-tab").removeClass('nav-tab-active');
        jQuery(this).addClass('nav-tab-active');
        divid = "#rentman-" + jQuery(this).attr('href').replace("#", "");
        jQuery('#rentman-login,#rentman-settings,#rentman-import').hide();
        jQuery(divid).show();
    });
});
