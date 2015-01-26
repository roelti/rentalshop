jQuery(document).ready(function() {
	hide_wc_elements();
	create_datepicker();
});

function hide_wc_elements() {
	jQuery('.single_add_to_cart_button').hide();
};

// Return the offset in seconds
// For example, if the server is UTC and the client +1, this returns 3600
function get_server_timezone_offset() {
	// Server offset is in seconds
	var server_offset = date_picker_localized.server_utc_offset;
	var now = new Date();
	var client_offset = now.getTimezoneOffset() * 60; // Convert to seconds
	return server_offset - client_offset;
};

function send_server_timezone_offset() {
	var offset = get_server_timezone_offset();
	jQuery(function() {
		jQuery.ajax({
			url: date_picker_localized.ajax_file_path,
			type: 'post',
			data: { 
				action: 'set_timezone_offset', 
				user_data: {
					"offset": offset
				}
			},
			success: function() {
				//console.log("Good!");
			},
			error: function(arg1, desc, error) {
				//console.log(arg1);
				//console.log("AJAX error: " + desc + " " + error);
			}
		});
	});
}

// Add the datepickers to the page
function create_datepicker(cart) {
	// Convert Unix epoch to JS time
	var from_date_object = new Date(date_picker_localized.from_date * 1000 - get_server_timezone_offset());
	var to_date_object = new Date(date_picker_localized.to_date * 1000 - get_server_timezone_offset());

	jQuery(function() {
		jQuery( "#datepicker-from-date" ).datepicker({
			// Minimum date is today
			minDate: new Date(Date.now()),
			onSelect: function(date) {
				date_picked(date, this);
			}
		});
		jQuery( "#datepicker-to-date" ).datepicker({
			// Minimum date is tomorrow
			minDate: new Date(Date.now() + (24 * 60 * 60 * 1000)),
			onSelect: function(date) {
				date_picked(date, this);
			}
		});

		// Get the date from object
		jQuery("#datepicker-from-date").datepicker("setDate", from_date_object);
		jQuery("#datepicker-to-date").datepicker("setDate", to_date_object);
		jQuery("#datepicker-from-date").datepicker( "option", "dateFormat", "dd-mm-yy");
		jQuery("#datepicker-to-date").datepicker( "option", "dateFormat", "dd-mm-yy");

		jQuery('input.minus').click(function() {
			date_picked();
		})
		jQuery('input.plus').click(function() {
			date_picked();
		})

		jQuery( ".single_add_to_cart_button").click(function() {
			ajax_post_date();
		});
		jQuery( 'input[name="update_cart"]').click(function() {
			ajax_post_date();
		});

		send_server_timezone_offset();

		if (cart) date_picked_cart(); else date_picked();
	});
}


function date_picked() {
	var from_date = jQuery("#datepicker-from-date").datepicker("getDate");
	var to_date = jQuery("#datepicker-to-date").datepicker("getDate");
	var result = {};

	if (from_date === null) from_date = new Date(date_picker_localized.from_date);
	if (to_date === null) to_date = new Date(date_picker_localized.to_date);

	// Convert to Unix timestamp and compensate server timezone differences
	from_date = from_date / 1000 + get_server_timezone_offset();
	to_date = to_date / 1000 + get_server_timezone_offset();

	// Check if the entered dates are even possible without breaking the space-time continuum
	if ((from_date !== "") && (to_date !== "") && (from_date <= to_date)) {
		jQuery(function() {
			jQuery.ajax({
				"url": date_picker_localized.ajax_file_path,
				"type": 'post',
				"data": { 
					"action": 'rentman_get_availability', 
					"user_data": {
						"from_date": from_date,
						"to_date": to_date,
						"product_id": date_picker_localized.product_id,
						"cart_ids": date_picker_localized.cart_ids
					}
				},
				success: function(data) {
					try {
						//data = jQuery.parseJSON(data);
						// Check if you're on a cart page and have to handle multiple products
						if (typeof date_picker_localized.cart_ids === 'undefined' || date_picker_localized.cart_ids.length === 0) {
							handle_availability(data);
						} else {
							handle_availability_cart(data);
						}
					}
					catch(e) {
						console.log(e);
					}
				},
				error: function(arg1, desc, error) {
					console.log(arg1);
					console.log("AJAX error: " + desc + " " + error);
				}
			});
		});
	} else {
		console.log('hide button');
		jQuery('.single_add_to_cart_button').hide();
	}
}

// Updates the DOM to match product availability for the selected date
function handle_availability(result) {
	var quantity = jQuery('.input-text.qty.text').attr("value");
	if (quantity <= result.maxconfirmed) {
		jQuery('#rentman-availability-status').html('<span class="icon-green">&#9679;</span>Product is beschikbaar');
		jQuery('.single_add_to_cart_button').show();
	} else if (quantity <= result.maxoption) {
		jQuery('#rentman-availability-status').html('<span class="icon-orange">&#9679;</span>Het product is mogelijk beschikbaar, maar niet definitief');
		jQuery('.single_add_to_cart_button').show();
	} else {
		jQuery('#rentman-availability-status').html('<span class="icon-red">&#9679;</span>Het product is niet beschikbaar in deze hoeveelheid voor de opgegeven periode');
		jQuery('.single_add_to_cart_button').hide();
	}
}

function handle_availability_cart(result) {
	result.forEach(function(element) {
		var quantity = jQuery("#" + element.id).parent().parent().find("input.input-text.qty.text").attr("value");
		
		if (quantity <= element.maxconfirmed) {
			jQuery("#" + element.id).html('<span class="icon-green">&#9679;</span>Product is beschikbaar');
		} else if (quantity <= element.maxoption) {
			jQuery("#" + element.id).html('<span class="icon-orange">&#9679;</span>Mogelijk beschikbaar, maar niet definitief');
		} else {
			jQuery("#" + element.id).html('<span class="icon-red">&#9679;</span>Niet beschikbaar in deze hoeveelheid voor de opgegeven periode');
		}
	});
}

function ajax_post_date() {
	var from_date = jQuery("#datepicker-from-date").datepicker("getDate");
	var to_date = jQuery("#datepicker-to-date").datepicker("getDate");

	if (from_date === null) from_date = new Date(date_picker_localized.from_date);
	if (to_date === null) to_date = new Date(date_picker_localized.to_date);

	// Convert to Unix timestamp and compensate timezone differences with the server
	from_date = from_date / 1000 + get_server_timezone_offset();
	to_date = to_date / 1000 + get_server_timezone_offset();

	jQuery(function() {
		jQuery.ajax({
			url: date_picker_localized.ajax_file_path,
			type: 'post',
			data: { 
				action: 'wdm_add_user_custom_data_options', 
				user_data: {
					from_date: from_date,
					to_date: to_date
				}
			},
			success: function() {
				//console.log("Good!");
			},
			error: function(arg1, desc, error) {
				//console.log(arg1);
				//console.log("AJAX error: " + desc + " " + error);
			}
		});
	});
}

function realtime_price() {
	// Get price from metadata
	var price_string = jQuery('meta[itemprop="price"]')[0].content;
	var baseprice = parseFloat(price_string);
	var total_days = difference_in_days(from_date, to_date);
	var total_price = baseprice * total_days + baseprice;
	if ( isNaN ( total_price )) { total_price = price_string; }
	
	jQuery('.price').children('.amount').html('€' + total_price);
}

function difference_in_days(date1, date2) {
	// Convert dates to UTC to prevent issues with Daylight Saving Time
	var utc1 = Date.UTC(date1.getFullYear(), date1.getMonth(), date1.getDate());
	var utc2 = Date.UTC(date2.getFullYear(), date2.getMonth(), date2.getDate());

	return Math.abs(Math.floor((utc1 - utc2) / (1000 * 60 * 60  * 24)));
}