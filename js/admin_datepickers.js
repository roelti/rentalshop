// ----- JavaScript functions for updating the datepickers ----- \\
// Attach function to #changePeriod button
jQuery().ready(function() {
  jQuery('#start-date, #end-date').datepicker({
    minDate: new Date(),
    language: jQuery(this).attr("data-language"),
    autoClose: true,
    onSelect: function (fd, d, picker) {
      startdate = changeDateformat(jQuery('#start-date').val());
      enddate = changeDateformat(jQuery('#end-date').val());
      if(startdate > enddate){
        jQuery("#end-date").val(jQuery("#start-date").val());
      }
    }
  }).attr('readonly','readonly');

  jQuery("#changePeriod").click(function(){
    ajax_post_date();
  });
});

function changeDateformat(dates) {
  return parseInt(dates.substring(6,10) + dates.substring(3,5) + dates.substring(0,2));
}

// Update the dates in the session
function ajax_post_date() {
	var data = {
		'action' : 'wdm_add_user_custom_data_options',
		'start_date' : changeDateformat(jQuery('#start-date').val()),
		'end_date' : changeDateformat(jQuery('#end-date').val())
	};
	jQuery.post(ajax_file_path, data, function(response) {		
		location.reload();
	})
}
