<?php
require_once(dirname(__FILE__)."/../../resources/common_functions.php");
require_once(dirname(__FILE__)."/../../resources/globals.php");

// checks the session for a logged in user
// @retval the user object or null
function get_logged_in() {
	global $global_user;
	$time_before_timeout = 10; // minutes

	if (!isset($_SESSION['loggedin']) || !isset($_SESSION['username']) || !isset($_SESSION['last_activity']) || !isset($_SESSION['crypt_password']))
			return NULL;
	if ((time()-$_SESSION['last_activity'])/60 > $time_before_timeout)
			return NULL;
	$_SESSION['last_activity'] = time();
	$o_user = new user($_SESSION['username'], NULL, urldecode($_SESSION['crypt_password']));
	if ($o_user->exists_in_db()) {
			$global_user = $o_user;
			return $o_user;
	}
}

// returns a string for the login page
function draw_login_page() {
	$a_page = array();
	$a_page[] = draw_page_head();
	$a_page[] = "<form id='login_form'>";
	$a_page[] = "<label class='errors'></label><br />";
	$a_page[] = "<label name='username'>Username</label>";
	$a_page[] = "<input type='textbox' size='20' name='username'><br />";
	$a_page[] = "<label name='password'>Password</label>";
	$a_page[] = "<input type='password' size='20' name='password'><br />";
	$a_page[] = "<dev style='float:right;'><input type='button' value='Submit' onclick='send_ajax_call_from_form(\"/pages/login/login_ajax.php\",$(this).parent().parent().prop(\"id\"));' /></dev><br />";
	$a_page[] = "</form>";
	$a_page[] = "<form id='login_form_guest'><input type='hidden' name='username' value='guest' /><input type='hidden' name='password' value='password' />or <font style='font-style:italic;font-weight:bold;font-decoration:underline;cursor:pointer;' onclick='send_ajax_call_from_form(\"/pages/login/login_ajax.php\",\"login_form_guest\");'>Login As Guest</font></form>";
	$a_page[] = draw_page_foot();
	return implode("\n", $a_page);
}

function check_logged_in() {
	my_session_start();
	
	$o_user = get_logged_in();
	if ($o_user == NULL)
			return FALSE;
	return TRUE;
}

?>