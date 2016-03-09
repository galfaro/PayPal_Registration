<?php
/*
  Plugin Name: Custom Registration
  Description: PayPal WooCommerce Registration Form With PayPal Validation.
  Version: 1.1
  Author: Gabriel Alfaro
  Version: 1.0
  Author URI:
  License: GPLv2
*/

// Block direct requests
if ( !defined('ABSPATH') )
	die('-1');
	
function paypal_url_redirect() {
	
	// Get Registration Page Slug
	if (get_option('paypal_pageslug') != ""){
		$registration_page = get_option('paypal_pageslug');
	}

	// Check pageslug and logged status. If logged in, redirect to prefered page.
	if ( is_page( $registration_page ) && is_user_logged_in() ) {
		
		// Get redirect slug 
		if (get_option('paypal_redirect') != ""){
			$paypal_redirect_slug = get_option('paypal_redirect');
		}
		
		// Check Registration field and redirected
		if ( $registration_page != "" ){
			wp_redirect ( home_url( $paypal_redirect_slug ) );
			exit;
		}
	}
  
	// Buffer output - New User Creation Use
	ob_start();
	
	// Check for form submit
	if (isset($_POST['submit'])) {
	  
		  // Check for nonce
		  if (! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'register' ) ){
			die('-1');
		  }
		  
		  /******** Get fields from submitted form ********/
		  $user_login   =  isset($_POST['user_login'])   ?  sanitize_user($_POST['user_login'])   :  '';
		  $user_email   =  isset($_POST['user_email'])   ?  sanitize_email($_POST['user_email'])   :  '';
		  $user_pass    =  isset($_POST['user_pass'])    ?  esc_attr($_POST['user_pass'])    :  '';
		  $confirm_pass =  isset($_POST['confirm_pass']) ?  esc_attr($_POST['confirm_pass'])    :  '';
		  $first_name   =  isset($_POST['first_name'])   ?  sanitize_text_field($_POST['first_name'])   :  '';
		  $last_name    =  isset($_POST['last_name'])    ?  sanitize_text_field($_POST['last_name'])    :  '';
		  
		  /********* Check Fields for errors *********/    
		  if (strlen($user_login) < 4) {
			$user_login_error = "Username too short. At least 4 characters is required";
		  }
		  if (username_exists($user_login)) {
			$user_login_error = "Sorry, that username already exists!";
		  }
		  if (!validate_username($user_login)) {
			$user_login_error = "Sorry, the username you entered is not valid";
		  }
		  if (empty($user_login)) {
			$user_login_error = "Required form field is missing";
		  }
		  
	  
		  if (email_exists($user_email)) {
			$user_email_error = "Email Already in use";
		  }
		  if (empty($user_email)) {
			$user_email_error = "Required form field is missing";
		  }
		  if (!is_email($user_email)) {
			$user_email_error = "Email is not valid";
		  }
		  
		  if (strlen($user_pass) < 5) {
			$user_pass_error = "Password length must be greater than 5";
		  }
		  if (empty($user_pass)) {
			$user_pass_error = "Required form field is missing";
		  }
		  
		  If ($user_pass != $confirm_pass){
			$confirm_paypal_error = "Passwords don't match";
		  }
		  
		  if (empty($first_name)) {
			$first_name_error = "Required form field is missing";
		  }
		  if (empty($last_name)) {
			$last_name_error = "Required form field is missing";
		  }
		  
		  
		  /*---- PayPal check for errors ----*/
		  $ch = curl_init();
		  // Get submited PayPal Details
		  $ppUserID = get_option('paypal_SBppUserID');			
		  $ppPass   = get_option('paypal_SBppPass');			
		  $ppSign   = get_option('paypal_SBppSign');			
		  $ppAppID  = get_option('paypal_SBppAppID');
		  
		  // Check if sandbox is enabled and if so enable sandbox fields
		  $sandbox_on = get_option('activate_paypal_sandbox');
		  if ($sandbox_on == true){
			// Sandbox Account Details
			$sandboxEmail = "pp.devtools@gmail.com"; // comment this line if you want to use it in production mode.It is just for sandbox mode
			$ppAppID = "APP-80W284485P519543T"; // if it is sandbox then app id is always: APP-80W284485P519543T
			$emailSBheader = "X-PAYPAL-SANDBOX-EMAIL-ADDRESS:$sandboxEmail";
		  }else{
			$ppAppID = $ppAppID;			
			$emailSBheader = "";
		  }
		  
		  // Fields to be verified
		  $emailAddress = $fields['user_email']; // The email address you wana verify
		  $firstName = $fields['first_name']; // first name of the account holder you want to verify, sandbox personal account default first name is: test
		  $lastName = $fields['last_name']; // last name of the account holder you want to verify, sandbox personal account default last name is: buyer
	  
		  //parameters of requests
		  $nvpStr = 'emailAddress='.$emailAddress.'&firstName='.$firstName.'&lastName='.$lastName.'&matchCriteria=NAME';
	  
		  // RequestEnvelope fields
		  $detailLevel    = urlencode("ReturnAll");   // See DetailLevelCode in the WSDL for valid enumerations
		  $errorLanguage  = urlencode("en_US");       // This should be the standard RFC 3066 language identification tag, e.g., en_US
		  $nvpreq = "requestEnvelope.errorLanguage=$errorLanguage&requestEnvelope.detailLevel=$detailLevel";
		  $nvpreq .= "&$nvpStr";
		  curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
		  
		  $headerArray = array(
			"X-PAYPAL-SECURITY-USERID:$ppUserID",
			"X-PAYPAL-SECURITY-PASSWORD:$ppPass",
			"X-PAYPAL-SECURITY-SIGNATURE:$ppSign",
			"X-PAYPAL-REQUEST-DATA-FORMAT:NV",
			"X-PAYPAL-RESPONSE-DATA-FORMAT:JSON",
			"X-PAYPAL-APPLICATION-ID:$ppAppID",
			$emailSBheader
		  );
		  
		  if ($sandbox_on == true){
			$url="https://svcs.sandbox.paypal.com/AdaptiveAccounts/GetVerifiedStatus";
		  }else{
			$url="https://svcs.paypal.com/AdaptiveAccounts/GetVerifiedStatus";
		  }
		  
		  curl_setopt($ch, CURLOPT_URL,$url);
		  curl_setopt($ch, CURLOPT_POST, 1);
		  curl_setopt($ch, CURLOPT_VERBOSE, 1);
		  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		  curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
		  $paypalResponse = curl_exec($ch);
		  $errmsg = curl_error($ch);
		  
		  //echo $paypalResponse;   //if you want to see whole PayPal response then uncomment it.
		  curl_close($ch);
	  
		  $data = json_decode($paypalResponse);
		  $verified = $data->accountStatus;
		  if($data->responseEnvelope->ack == "Success"){
			  $output = array('status' => true); //means user is verified successfully
		  } else {
			  $output = array('status' => false); //means verification was unsuccessful
			  $user_paypal_error = "This is not a Valid PayPal Aaccount";	
		  }
		  
		  /***** Create array or errors ******/
		  $errors = array(
			  $emtpy_fields          => $emtpy_fields,
			  $user_login_error      => $user_login_error,
			  $user_pass_error       => $user_pass_error,
			  $confirm_paypal_error  => $confirm_paypal_error,
			  $user_email_error      => $user_email_error,
			  $user_paypal_error     => $user_paypal_error
		  );
	  
		  $errors = array_filter($errors);

		  // If errors is empty pass fields and create user account
		  if (empty($errors)){
				//  Set fields
				$fields = array(
					"user_login"    =>    $user_login,
					"user_pass"     =>    $user_pass,
					"user_email"    =>    $user_email,
					"first_name"    =>    $first_name,
					"last_name"     =>    $last_name
				);
	  
				// If successful, register user
				$user_id = wp_insert_user($fields);
				
				// Auto login user
				wp_set_current_user($user_id);
				wp_set_auth_cookie($user_id);
				
				// Redirect them to preferred page
				wp_redirect( home_url() );
				exit;
		  
				// Clear field data
				$fields = array();
		  }
	}  

	// Return buffer
	return ob_get_clean();
}
add_action( 'template_redirect', 'paypal_url_redirect' );



/*--------------------- Registratino Form & Shortcode Setup -----------------------*/
/******* Registration Setup/Components **********/
function woo_paypal_registration() {

	/******** Get fields from submitted form ********/
	$user_login   =  isset($_POST['user_login'])   ?  sanitize_user($_POST['user_login'])   :  '';
	$user_email   =  isset($_POST['user_email'])   ?  sanitize_email($_POST['user_email'])   :  '';
	$user_pass    =  isset($_POST['user_pass'])    ?  esc_attr($_POST['user_pass'])    :  '';
	$confirm_pass =  isset($_POST['confirm_pass']) ?  esc_attr($_POST['confirm_pass'])    :  '';
	$first_name   =  isset($_POST['first_name'])   ?  sanitize_text_field($_POST['first_name'])   :  '';
	$last_name    =  isset($_POST['last_name'])    ?  sanitize_text_field($_POST['last_name'])    :  '';
	
	/********* Check Fields for errors *********/    
	if (strlen($user_login) < 4) {
		$user_login_error = "Username too short. At least 4 characters is required";
	}
	if (username_exists($user_login)) {
		$user_login_error = "Sorry, that username already exists!";
	}
	if (!validate_username($user_login)) {
		$user_login_error = "Sorry, the username you entered is not valid";
	}
	if (empty($user_login)) {
		$user_login_error = "Required form field is missing";
	}
	
	
	if (email_exists($user_email)) {
		$user_email_error = "Email Already in use";
	}
	if (empty($user_email)) {
		$user_email_error = "Required form field is missing";
	}
	if (!is_email($user_email)) {
		$user_email_error = "Email is not valid";
	}
	
	if (strlen($user_pass) < 5) {
		$user_pass_error = "Password length must be greater than 5";
	}
	if (empty($user_pass)) {
		$user_pass_error = "Required form field is missing";
	}
	
	If ($user_pass != $confirm_pass){
		$confirm_paypal_error = "Passwords don't match";
	}
	
	if (empty($first_name)) {
		$first_name_error = "Required form field is missing";
	}
	if (empty($last_name)) {
		$last_name_error = "Required form field is missing";
	}
	
	/*---- PayPal check for errors ----*/
	$ch = curl_init();
	// Get submited PayPal Details
	$ppUserID = get_option('paypal_SBppUserID');			
	$ppPass   = get_option('paypal_SBppPass');			
	$ppSign   = get_option('paypal_SBppSign');			
	$ppAppID  = get_option('paypal_SBppAppID');
	
	// Check if sandbox is enabled and if so enable sandbox fields
	$sandbox_on = get_option('activate_paypal_sandbox');
	if ($sandbox_on == true){
		// Sandbox Account Details
		$sandboxEmail = "pp.devtools@gmail.com"; // comment this line if you want to use it in production mode.It is just for sandbox mode
		$ppAppID = "APP-80W284485P519543T"; // if it is sandbox then app id is always: APP-80W284485P519543T
		$emailSBheader = "X-PAYPAL-SANDBOX-EMAIL-ADDRESS:$sandboxEmail";
		$url="https://svcs.sandbox.paypal.com/AdaptiveAccounts/GetVerifiedStatus";
	}else{
		$ppAppID = $ppAppID;			
		$emailSBheader = "";
		$url="https://svcs.paypal.com/AdaptiveAccounts/GetVerifiedStatus";
	}
	
	// Fields to be verified
	$emailAddress = $fields['user_email']; // The email address you wana verify
	$firstName = $fields['first_name']; // first name of the account holder you want to verify, sandbox personal account default first name is: test
	$lastName = $fields['last_name']; // last name of the account holder you want to verify, sandbox personal account default last name is: buyer
	
	//parameters of requests
	$nvpStr = 'emailAddress='.$emailAddress.'&firstName='.$firstName.'&lastName='.$lastName.'&matchCriteria=NAME';
	
	// RequestEnvelope fields
	$detailLevel    = urlencode("ReturnAll");   // See DetailLevelCode in the WSDL for valid enumerations
	$errorLanguage  = urlencode("en_US");       // This should be the standard RFC 3066 language identification tag, e.g., en_US
	$nvpreq = "requestEnvelope.errorLanguage=$errorLanguage&requestEnvelope.detailLevel=$detailLevel";
	$nvpreq .= "&$nvpStr";
	curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
	
	$headerArray = array(
		"X-PAYPAL-SECURITY-USERID:$ppUserID",
		"X-PAYPAL-SECURITY-PASSWORD:$ppPass",
		"X-PAYPAL-SECURITY-SIGNATURE:$ppSign",
		"X-PAYPAL-REQUEST-DATA-FORMAT:NV",
		"X-PAYPAL-RESPONSE-DATA-FORMAT:JSON",
		"X-PAYPAL-APPLICATION-ID:$ppAppID",
		$emailSBheader
	);

	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
	$paypalResponse = curl_exec($ch);
	$errmsg = curl_error($ch);
	
	//echo $paypalResponse;   //if you want to see whole PayPal response then uncomment it.
	curl_close($ch);
	
	$data = json_decode($paypalResponse);
	$verified = $data->accountStatus;
	if($data->responseEnvelope->ack == "Success"){
	  $output = array('status' => true); //means user is verified successfully
	} else {
	  $output = array('status' => false); //means verification was unsuccessful
	  $user_paypal_error = "This is not a Valid PayPal Aaccount";	
	}
		  
  // Generate form and paypal registration link
  if (!is_user_logged_in()){ ?>
		<div class="paypal_registration_form_header">
		  Click <a href="https://www.paypal.com" target="_blank">here</a> to register with paypal
		  <br /><span class="paypal_message_error"><?php echo $user_paypal_error; ?></span>
		</div>
		
		<div id="paypal_registration_form">
		  <form action="<?php $_SERVER['REQUEST_URI'] ?>" method="post" class="paypal_registration_form"> 
			  <label for="firstname">First Name <span class="required-woo-paypal">*</span></label><br />
			  <input type="text" name="first_name" value="<?php echo $first_name; ?>">
			  <span class="paypal_form_error"><?php echo $first_name_error; ?></span><br />
	  
			  <label for="lastname">Last Name <span class="required-woo-paypal">*</span></label><br />
			  <input type="text" name="last_name" value="<?php echo $last_name; ?>">
			  <span class="paypal_form_error"><?php echo $last_name_error; ?></span><br />
	  
			  <label for="email">Email <span class="required-woo-paypal">*</span></label><br />
			  <input type="text" name="user_email" value="<?php echo $user_email; ?>">
			  <span class="paypal_form_error"><?php echo $user_email_error; ?></span><br />
		
			  <label for="user_login">Username <span class="required-woo-paypal">*</span></label><br />
			  <input type="text" name="user_login" value="<?php echo $user_login; ?>">
			  <span class="paypal_form_error"><?php echo $user_login_error; ?></span><br />
			
			  <label for="user_pass">Password <span class="required-woo-paypal">*</span></label>
			  <input type="password" name="user_pass">
			  <span class="paypal_form_error"><?php echo $user_pass_error; ?></span><br />
	  
			  <label for="user_pass">Confirm Password <span class="required-woo-paypal">*</span></label>
			  <input type="password" name="confirm_pass">
			  <span class="paypal_form_error"><?php echo $confirm_paypal_error; ?></span><br /><br />
			  
			<input type="submit" name="submit" value="Register" class="submit">
			<?php $login_page = "my-account" ?>
			<a href="<?php echo home_url( '/' . $login_page ); ?>" class="re_login">Login</a>
			<?php wp_nonce_field( 'register', 'nonce' ); ?>	  
		  </form>
		</div>
  <?php }else{
	echo "<h2>You're logged in.</h2>";
  }
}
add_shortcode('paypal_registration', 'woo_paypal_registration');
/*--------------------- End of Registratino Form & Shortcode Setup -----------------------*/














/*------------------------------------------  Backend Options Panel -------------------------------------------*/
add_action( 'admin_menu', 'paypal_registration_admin_menu' );

/*------ Create Admin Page & Register its Options ---------*/
function paypal_registration_admin_menu() {
	// Setup the args.
	$page_title = "PayPal Registration";
	$menu_title = "PayPal Registration";
	$capability = "manage_options";
	$menu_slug  = "paypal-registration";
	$function   = "paypal_registration_options_page";
	$image 		= plugins_url( 'images/paypal_icon.png', __FILE__ ) ;
	
	// pass the args to the menu page
	add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $image);
}

/*------ Create configurations form/function ---------*/
function paypal_registration_options_page() {
    ?>
		<style>
			.form-table td{padding: 10px; background-color: royalblue; color: white;}
			.woocommerce table.form-table th{padding: 10px; background-color: orange; color: black;}
			.form-table{border: 2px solid;}
		</style>
		<div class="wrap">
			<h1>PayPal Pre Verified Users Settings</h1>
			<form method="post" action="options.php">
				<?php
					settings_fields("section"); // Register a section with and ID called section
					do_settings_sections("paypal-registration-options"); // Create a Groupd ID that all the fields belongin to a section
					submit_button(); // echo Submit button
				?>          
			</form>
		</div>
    <?php
}

/*------ Create form fields ---------*/
function display_pageslug_element(){
	if (get_option('paypal_pageslug') != ""){
		$paypal_pageslug = get_option('paypal_pageslug');
	}
	?>
	<select name="paypal_pageslug" id="paypal_pageslug"> 
		<option value=""><?php echo attribute_escape(__('Select page')); ?></option> 
		<?php 
			$pages = get_pages();
			
			foreach ($pages as $pagg) {
				// Check for selected value	
				$selected = $paypal_pageslug == str_replace(' ', '-',strtolower($pagg->post_title)) ? 'selected' : '';
	
				// Display all pages.
				$option = '<option ' . $selected . ' value="'.str_replace(' ', '-',strtolower($pagg->post_title)).'">';
				$option .= $pagg->post_title;
				$option .= '</option>';
				
				echo $option;
			}
		?>
	</select>	
	<?php
}
function display_redirect_element(){
	if (get_option('paypal_redirect') != ""){
		$paypal_redirect = get_option('paypal_redirect');
	}
	?>
	<select name="paypal_redirect" id="paypal_redirect"> 
		<option value=""><?php echo attribute_escape(__('Select page')); ?></option> 
		<?php 
			$pages = get_pages();
			
			foreach ($pages as $pagg) {
				// Check for selected value	
				$selected = $paypal_redirect == str_replace(' ', '-',strtolower($pagg->post_title)) ? 'selected' : '';
	
				// Display all pages.
				$option = '<option ' . $selected . ' value="'.str_replace(' ', '-',strtolower($pagg->post_title)).'">';
				$option .= $pagg->post_title;
				$option .= '</option>';
				
				echo $option;
			}
		?>
	</select>	
	<?php
}

function display_SBppUserID_element(){
	if (get_option('paypal_SBppUserID') != ""){
		$paypal_SBppUserID = get_option('paypal_SBppUserID');
	}
	?><hr /><input type="text" name="paypal_SBppUserID" id="paypal_SBppUserID" value="<?php echo sanitize_text_field($paypal_SBppUserID); ?>" /><?php
}
function display_SBppPass_element(){
	if (get_option('paypal_SBppPass') != ""){
		$paypal_SBppPass = get_option('paypal_SBppPass');
	}
	?><input type="text" name="paypal_SBppPass" id="paypal_SBppPass" value="<?php echo sanitize_text_field($paypal_SBppPass); ?>" /><?php
}
function display_SBppSign_element(){
	if (get_option('paypal_SBppSign') != ""){
		$paypal_SBppSign = get_option('paypal_SBppSign');
	}
	?><input type="text" name="paypal_SBppSign" id="paypal_SBppSign" value="<?php echo sanitize_text_field($paypal_SBppSign); ?>" /><?php
}
function display_SBppAppID_element(){
	if (get_option('paypal_SBppAppID') != ""){
		$paypal_SBppAppID = get_option('paypal_SBppAppID');
	}
	?><input type="text" name="paypal_SBppAppID" id="paypal_SBppAppID" value="<?php echo sanitize_text_field($paypal_SBppAppID); ?>" /><?php
}

// Activate Sandbox Mode & Create Sandbox fields
function display_sandbox_element(){
	?><hr /><input type="checkbox" name="activate_paypal_sandbox" value="1" <?php checked(1, get_option('activate_paypal_sandbox'), true); ?> /> <?php
}


/*------ Register form fields ---------*/
function display_woo_paypal_fields(){
	add_settings_section("section", "All Settings", null, "paypal-registration-options"); 
	
	// Set Field Titles, Set Field Value, Create Value Group Name, Register the Section
	add_settings_field("paypal_pageslug", "Registration Page <br /><span style='font-size: 10px; color:red'>* Required</span>", "display_pageslug_element", "paypal-registration-options", "section");
	add_settings_field("paypal_redirect", "Redirect Page", "display_redirect_element", "paypal-registration-options", "section");
	add_settings_field("paypal_SBppUserID", "<hr />User ID", "display_SBppUserID_element", "paypal-registration-options", "section");
	add_settings_field("paypal_SBppPass", "Pass", "display_SBppPass_element", "paypal-registration-options", "section");
	add_settings_field("paypal_SBppSign", "Signature", "display_SBppSign_element", "paypal-registration-options", "section");	
	add_settings_field("paypal_SBppAppID", "App ID", "display_SBppAppID_element", "paypal-registration-options", "section");
    add_settings_field("activate_paypal_sandbox", "<hr/>Enable Sandbox", "display_sandbox_element", "paypal-registration-options", "section");

	// Register the field settings
    register_setting("section", "paypal_pageslug");
    register_setting("section", "paypal_redirect");
    register_setting("section", "paypal_SBppUserID");
    register_setting("section", "paypal_SBppPass");
    register_setting("section", "paypal_SBppSign");	
    register_setting("section", "paypal_SBppAppID");
    register_setting("section", "activate_paypal_sandbox");	
}
add_action("admin_init", "display_woo_paypal_fields");
/*------------------------------------------  End of Backend Options Panel -------------------------------------------*/	


/*------------------------------------------  Register Stylesheet -------------------------------------------*/
/**
 * Register style sheet.
 */
function register_woo_paypal_styles() {
	wp_register_style( 'woo-paypal-registration', plugins_url( 'woo-paypal-registration/css/styles.css' ) );
	wp_enqueue_style( 'woo-paypal-registration' );
}
add_action( 'wp_enqueue_scripts', 'register_woo_paypal_styles' );
/*------------------------------------------  Register Stylesheet -------------------------------------------*/
?>