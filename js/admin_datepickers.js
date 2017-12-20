// ----- JavaScript functions for updating the datepickers ----- \\

// Attach function to #changePeriod button
jQuery().ready(function()
{
    jQuery("#changePeriod").click(function(){
        ajax_post_date();
    });
});

// Update the dates in the session
function ajax_post_date() {
	var fromDate = document.getElementsByName("start-date")[0].value;
    var toDate = document.getElementsByName("end-date")[0].value;

	jQuery(function() {
		jQuery.ajax({
			url: ajax_file_path,
			type: 'post',
			data: { 
				action: 'wdm_add_user_custom_data_options', 
				'start-date': fromDate,
				'end-date': toDate
			},
			success: function() {
				location.reload();
			},
			error: function(arg1, desc, error) {
				console.log("AJAX error: " + desc + " " + error);
			}
		});
	});
}