<?php
class Rentman_Checkout_Fields {
	public function __construct() {
		add_filter( 'woocommerce_after_order_notes', array( $this, 'add_section' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_order_meta' ), 10, 1 );
	}

	public function add_section( $checkout )
    {
		global $rentman;

        $dates = $rentman->get_dates();

        $van = new DateTime();
        $van->setTimestamp($dates['from_date']);
        $tot = new DateTime();
        $tot->setTimestamp($dates['to_date']);

        echo '<div id="rentman_checkout_fields"><h3>Huurperiode</h3>';
        echo '<span style="font-weight: bold;">Van: '.$van->format("d-m-Y").'</span><br>';
        echo '<span style="font-weight: bold;">Tot: '.$tot->format("d-m-Y").'</span><br>';

		echo '</div>';

		$rentman->init_datepickers(true);
	}

	// Converts date string (format DD/MM/YYYY) to Unix timestamp
	private function convert_date_string($date) {
		// Convert Dutch date format to ISO
		$date = explode('-', $date);
		$date = implode( '-', array_reverse( $date ) );

		// Convert to Unix timestamp
		$date = strtotime($date);

		return $date;
	}

	public function update_order_meta( $order_id )
    {
        global $rentman;
        $dates = $rentman->get_dates();
        update_post_meta( $order_id, 'from_date', sanitize_text_field($dates['from_date'] ) );
	    update_post_meta( $order_id, 'to_date', sanitize_text_field( $dates['to_date']) );
	}

	function display_admin_order_meta( $order ){
	    echo '<p><strong>Begindatum verhuur:</strong> ' . date( "l j-n-Y", get_post_meta( $order->id, 'from_date', true ) ) . '</p>';
	    echo '<p><strong>Einddatum verhuur:</strong> ' . date( "l j-n-Y", get_post_meta( $order->id, 'to_date', true ) ) . '</p>';
	}

}

$rentman_checkout_fields = new Rentman_Checkout_Fields();