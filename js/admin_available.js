// ----- JavaScript functions for availability ----- \\
// Initiate the availability functions
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
      quickCheck();
    }
  }).attr('readonly','readonly');
  attachFunction();
  quickCheck();
});

function changeDateformat(dates) {
  return parseInt(dates.substring(6,10) + dates.substring(3,5) + dates.substring(0,2));
}

// Adds availability function to the 'amount' field
function attachFunction() {
    var input = document.getElementsByClassName("input-text qty text")[0];
    if (typeof input != 'undefined')
    	input.addEventListener ("change", quickCheck, false);
}

// Function that applies the availability check when changes are made on the page
function quickCheck() {
    re = /^\d{1,2}\-\d{1,2}\-\d{4}$/;
    if(!jQuery("#start-date").val().match(re)) {
      jQuery("#start-date").val(jQuery("#start-date").attr("min"));
    }
    if(!jQuery("#end-date").val().match(re)) {
      jQuery("#end-date").val(jQuery("#start-date").attr("min"));
    }
    startdate = changeDateformat(jQuery('#start-date').val());
    enddate = changeDateformat(jQuery('#end-date').val());
    if(startdate > enddate){
      jQuery("#end-date").val(jQuery("#start-date").val());
      enddate = startdate;
    }

    jQuery(".availLog").html("...");
    jQuery(".availLog").css("color", "#4C4C4C");
    if (document.contains(document.getElementsByName("start_date")[0])) {
        var fromDate = startdate;
        var toDate = enddate;
        if (fromDate != null && fromDate != "" && toDate != null && toDate != null){
        	var incDate = new Date(toDate);
        	incDate.setDate(incDate.getDate() + 1);
        	toDate = incDate.toISOString().substring(0,19);
        }
    } else {
        var fromDate = startdate;
        var toDate = enddate;
    }
    if (fromDate == null || toDate == null || fromDate == ""|| toDate == "" || fromDate > toDate){
        document.getElementsByClassName("availLog")[0].innerHTML = unavailable;
        document.getElementsByClassName("availLog")[0].style = "color:red";
    }
    else {
        var productID = document.getElementsByClassName("sku")[0].innerText;
        // Check if the quantity field exists
        var input = document.getElementsByClassName("input-text qty text")[0];
        if (typeof input != 'undefined')
        	var amount = document.getElementsByClassName("input-text qty text")[0].value;
        else
        	var amount = 1;
        // Do the actual request
        xhr = new XMLHttpRequest();
        var url = endPoint;
        var account = rm_account;
        var token = rm_token;
        var totalamount = parseInt(amount) + parseInt(cart_amount);
        xhr.open("POST", url, true);
        xhr.setRequestHeader("Content-type", "application/json");
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4 && xhr.status == 200) {
                var json = JSON.parse(xhr.responseText);
                var maxcon = json.response.value.maxconfirmed;
                var maxopt = json.response.value.maxoption;
                // Show correct message depending on the values of maxconfirmed and maxoption
                if (maxcon < 0){
                    document.getElementsByClassName("availLog")[0].innerHTML = unavailable;
                    document.getElementsByClassName("availLog")[0].style = "color:red";
                }
                else if (maxcon >= 0 & maxopt < 0){
                    document.getElementsByClassName("availLog")[0].innerHTML = maybe;
                    document.getElementsByClassName("availLog")[0].style = "color:orange";
                }
                else{
                    document.getElementsByClassName("availLog")[0].innerHTML = available;
                    document.getElementsByClassName("availLog")[0].style = "color:green";
                }
            }
        }
        var data = JSON.stringify({"requestType":"modulefunction","client":{"language":1,"type":"webshopplugin",
            "version":"4.10.4"},"account":account,"token":token,"module":"Availability","parameters":{
            "van":fromDate,"tot":toDate,"materiaal":productID,"aantal":totalamount},"method":"is_available"});
        xhr.send(data);
    }
}
