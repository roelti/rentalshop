// ------------- V4.20.2 ------------- \\
// ----- JavaScript functions for updating the datepickers ----- \\
// Attach function to #changePeriod button
jQuery().ready(function(){
    var minDate = jQuery("#start-date").attr("min");
    jQuery('#start-date, #end-date').on('input',function(e){
      if(jQuery("#start-date").val().length > 10){
          jQuery("#start-date").val(minDate);
      }
      if(jQuery("#end-date").val().length > 10){
          jQuery("#end-date").val(minDate);
      }
      if(jQuery("#start-date").val() < minDate){
          jQuery("#start-date").val(minDate);
      }
      if(jQuery("#end-date").val() < minDate){
          jQuery("#end-date").val(minDate);
      }
      if(jQuery("#start-date").val() > jQuery("#end-date").val()){
          jQuery("#end-date").val(jQuery("#start-date").val());
      }
    });
    jQuery("#changePeriod").click(function(){
        ajax_post_date();
    });
});

// Update the dates in the session
function ajax_post_date() {
	var data = {
		'action' : 'wdm_add_user_custom_data_options',
		'start_date' : document.getElementsByName("start_date")[0].value,
		'end_date' : document.getElementsByName("end_date")[0].value
	};
	jQuery.post(ajax_file_path, data, function(response) {
		//console.log('Server Responded!');
		location.reload();
	})
}
