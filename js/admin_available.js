// ----- JavaScript functions for availability ----- \\

// Initiate the availability functions
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
    attachFunction();
    quickCheck();
});

// Adds availability function to the 'amount' field
function attachFunction() {
    var input = document.getElementsByClassName("input-text qty text")[0];
    if (typeof input != 'undefined')
    	input.addEventListener ("change", quickCheck, false);
}

// Function that applies the availability check when changes are made on the page
function quickCheck() {
    jQuery(".availLog").html("...");
    jQuery(".availLog").css("color", "#4C4C4C");
    if (document.contains(document.getElementsByName("start_date")[0])) {
        var fromDate = document.getElementsByName("start_date")[0].value;
        var toDate = document.getElementsByName("end_date")[0].value;
        if (fromDate != null && fromDate != "" && toDate != null && toDate != null){
        	var incDate = new Date(toDate);
        	incDate.setDate(incDate.getDate() + 1);
        	toDate = incDate.toISOString().substring(0,19);
        }
    } else {
        var fromDate = startDate;
        var toDate = endDate;
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
