<?php
class Rentman_Checkout_Fields {
	public function __construct() {
		add_filter( 'woocommerce_after_order_notes', array( $this, 'add_section' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_order_meta' ), 10, 1 );
	}

	public function add_section( $checkout ) {
		global $rentman;

		echo '<div id="rentman_checkout_fields"><h3>Huurperiode</h3>';

		$dates = $rentman->get_dates();

		woocommerce_form_field( 'datepicker-from-date', array(
			'type' => 'text',
			'label' => 'Begindatum',
			'placeholder' => 'Begindatum',
			'class' => array('checkout-from-date'),
			'required' => true,
			'clear' => true,
			), $dates['from_date']
		);

		woocommerce_form_field( 'datepicker-to-date', array(
			'type' => 'text',
			'label' => 'Einddatum',
			'placeholder' => 'Einddatum',
			'class' => array('checkout-to-date'),
			'required' => true,
			'clear' => true,
			), $dates['to_date']
		);

		echo '</div>';

		$rentman->init_datepickers(true);
	}

	public function validate() {
		if ( ! $_POST['datepicker-from-date'] || ! $_POST['datepicker-to-date'] ) {
			wc_add_notice( 'Het invullen van een huurperiode is verplicht' );
		} else {
			$from_date = $_POST['datepicker-from-date'];
			$to_date = $_POST['datepicker-to-date'];

			if ( $from_date >= $to_date) {
				wc_add_notice( 'Begin huurperiode is na of op dezelfde dag als het eind van de huurperiode' );
			}
			if ( $from_date < ( time() - 60 * 60 * 24 ) || $to_date < ( time() - 60 * 60 * 24 ) ) {
				wc_add_notice( 'Ongeldige huurperiode' );
			}
		}
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

	public function update_order_meta( $order_id ) {
		if ( ! empty( $_POST['datepicker-from-date'] ) && ! empty( $_POST['datepicker-to-date'] ) ) {
			$from_date = sanitize_text_field( $_POST['datepicker-from-date'] );
			$to_date = sanitize_text_field( $_POST['datepicker-to-date'] );

			$from_date = $this->convert_date_string($from_date);
			$to_date = $this->convert_date_string($to_date);

	        update_post_meta( $order_id, 'from_date', sanitize_text_field( $from_date ) );
	        update_post_meta( $order_id, 'to_date', sanitize_text_field( $to_date ) );
	    }
	}

	function display_admin_order_meta( $order ){
	    echo '<p><strong>Begindatum verhuur:</strong> ' . date( "l j-n-Y", get_post_meta( $order->id, 'from_date', true ) ) . '</p>';
	    echo '<p><strong>Einddatum verhuur:</strong> ' . date( "l j-n-Y", get_post_meta( $order->id, 'to_date', true ) ) . '</p>';
	}

}

$rentman_checkout_fields = new Rentman_Checkout_Fields();