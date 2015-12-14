<?php
class Rentman_Options {
	public function render_login() {
		global $rentman;
		if ( ! $rentman->login_credentials_correct() ) {
			?><p><strong>Couldn't connect to the Rentman API. Did you provide the correct credentials?</strong></p><?php
		} else {
			?><p>Succesfull login</p><?php
		}
	}

	public function render_account_name() {
	 	$options = get_option( 'rentman_settings' );
		?>
		<input type='text' name='rentman_settings[rentman_account_name]' value='<?php if($options){echo $options['rentman_account_name'];} ?>'>
		<?php
	 }

	 public function render_password() {
	 	$options = get_option( 'rentman_settings' );
		?>
		<input type='password' name='rentman_settings[rentman_password]' value='<?php if($options){echo $options['rentman_password'];} ?>'>
		<?php
	 }

	 public function validate( $input ) {
		return($input);
	 }

    public function render_availabilityCheck()
    {
        $options = get_option( 'rentman_settings' );
        ?>
        <select name='rentman_settings[rentman_availabilityCheck]'>
            <option value="1" <?php if($options && $options['rentman_availabilityCheck'] == 1){echo"selected";} ?>>Yes</option>
            <option value="0" <?php if($options && $options['rentman_availabilityCheck'] == 0){echo"selected";} ?>>No</option>
        </select>
        <?php
    }

    public function render_addDiscount()
    {
        $options = get_option( 'rentman_settings' );
        ?>
        <select name='rentman_settings[rentman_addDiscount]'>
            <option value="1" <?php if($options && isset($options['rentman_addDiscount']) && $options['rentman_addDiscount'] == 1){echo"selected";} ?>>Yes</option>
            <option value="0" <?php if($options && isset($options['rentman_addDiscount']) && $options['rentman_addDiscount'] == 0){echo"selected";} ?>>No</option>
        </select>
    <?php
    }
}
global $option_object;
$option_object = new Rentman_Options();