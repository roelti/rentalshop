<?php
class Rentman_Options {
	public function render_login() {
		global $rentman;
		if ( ! $rentman->login_credentials_correct() ) {
			?><p><strong>Kon geen verbinding maken met de Rentman API. Zijn de inloggevens correct?</strong></p><?php
		} else {
			?><p>Logingegevens correct</p><?php
		}
	}

	public function render_account_name() {
	 	$options = get_option( 'rentman_settings' );
		?>
		<input type='text' name='rentman_settings[rentman_account_name]' value='<?php echo $options['rentman_account_name']; ?>'>
		<?php
	 }

	 public function render_password() {
	 	$options = get_option( 'rentman_settings' );
		?>
		<input type='password' name='rentman_settings[rentman_password]' value='<?php echo $options['rentman_password']; ?>'>
		<?php
	 }

	 public function validate( $input ) {
	 	//logit($input);
	 	echo 'dinges';
	 	return($input);
	 }
}
global $option_object;
$option_object = new Rentman_Options();