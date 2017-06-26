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
    input.addEventListener ("change", quickCheck, false);
}

// Function that applies the availability check when changes are made on the page
function quickCheck() {
    if (document.contains(document.getElementsByName("start-date")[0])) {
        var fromDate = document.getElementsByName("start-date")[0].value;
        var toDate = document.getElementsByName("end-date")[0].value;
        var incDate = new Date(toDate);
        incDate.setDate(incDate.getDate() + 1);
        toDate = incDate.toISOString().substring(0,19);
    } else {
        var fromDate = startDate;
        var toDate = endDate;
    }
    if (fromDate > toDate){
        document.getElementsByClassName("availLog")[0].innerHTML = unavailable;
        document.getElementsByClassName("availLog")[0].style = "color:red";
    }
    else {
        var productID = document.getElementsByClassName("sku")[0].innerText;
        var amount = document.getElementsByClassName("input-text qty text")[0].value;
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
            "version":"4.6.0"},"account":account,"token":token,"module":"Availability","parameters":{
            "van":fromDate,"tot":toDate,"materiaal":productID,"aantal":totalamount},"method":"is_available"});
        xhr.send(data);
        console.log(data);
    }
}