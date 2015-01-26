<?php 
/**
 * Extends the default WC_Cart implementation
 *
 *
 */

class Rentman_Class_WC_Cart extends WC_Cart {
	/** @var array Contains the rental from date and to date */
	public $rentman_rental_dates = array();

	/**
	 * @param array $dates
	 */
	public function set_date($dates) {
		if ( isset( $dates['from_date'] ) ) {
			$this->rentman_rental_dates['from_date'] = $dates['from_date'];
		}
		if ( isset( $dates['to_date'] ) ) {
			$this->rentman_rental_dates['to_date'] = $dates['to_date'];
		}
		var_dump($this->rentman_rental_dates);
	}

	public function get_dates() {
		return $this->rentman_rental_dates;
	}

}