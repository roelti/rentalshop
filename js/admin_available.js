// ----- JavaScript functions for availability ----- \\

// Initiate the availability functions
jQuery().ready(function()
{
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
        document.getElementsByClassName("availLog")[0].innerHTML = "";
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
                console.log(json);
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
            "version":"4.11.0"},"account":account,"token":token,"module":"Availability","parameters":{
            "van":fromDate,"tot":toDate,"materiaal":productID,"aantal":totalamount},"method":"is_available"});
        xhr.send(data);
        console.log(data);
        console.log(typeof(token));
    }
}