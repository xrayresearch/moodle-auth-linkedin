<?php

/**
 * @author Martin Dougiamas
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package moodle multiauth
 *
 * Authentication Plugin: No Authentication
 *
 * No authentication at all. This method approves everything!
 *
 * 2006-08-31  File created.
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/authlib.php');
require_once($CFG->dirroot.'/auth/linkedin/linkedinoauth.php');
/**
 * Plugin for no authentication.
 */
class auth_plugin_linkedin extends auth_plugin_base {

	/**
	 *
	 * @var linkedinOAuth
	 */
	public $lioauth;
	/**
     * Constructor.
     */
    function auth_plugin_linkedin() {
		global $CFG;
        $this->authtype = 'linkedin';
        $this->config = get_config('auth/linkedin');
		if(!isset($this->config->appid)){
			$plugin = 'auth/linkedin';
			set_config('appid', "", $plugin);
			set_config('appsecret', "", $plugin);
			set_config('createuser', 0, $plugin);
			set_config('syncuserinfo', 1, $plugin);
			set_config('callback', $CFG->wwwroot."/login/index.php", $plugin);
			$this->config = get_config("auth/linkedin");
		}
		$this->lioauth = new linkedinOAuth($this->config->appid, $this->config->appsecret);
    }
	
	function getlinkedinLoginUrl(){
		global $CFG;
		if(session_id()=='') {
			session_start();
		}
		$loginurl = $CFG->wwwroot."/login/index.php";
		if(!isset($_SESSION['LI_access_token'])){
			if(empty($_SESSION['LI_state']))
				$_SESSION['LI_state'] = md5(time());
			$url = "https://www.linkedin.com/uas/oauth2/authorization?response_type=code
							&client_id={$this->config->appid}
							&scope=r_basicprofile%20r_emailaddress
							&state={$_SESSION['LI_state']}
							&redirect_uri=$loginurl";
			return $url;
		}
		return $loginurl; 
	}

    /**
     * Returns true if the username and password work or don't exist and false
     * if the user exists and the password is wrong.
     *
     * @param string $username The username
     * @param string $password The password
     * @return bool Authentication success or failure.
     */
    function user_login ($username, $password) {
		return !empty($_SESSION['LI_access_token']);
    }
	
	function get_userinfo($username) {
		$connectionNew = new linkedinOAuth($this->config->appid,$this->config->appsecret,$_SESSION['LI_access_token']);
		$account = $connectionNew->getProfile();
		return $account;
	}

	
	/**
     * Updates the user's password.
     *
     * called when the user password is updated.
     *
     * @param  object  $user        User table object
     * @param  string  $newpassword Plaintext password
     * @return boolean result
     *
     */
    function user_update_password($user, $newpassword) {
        $user = get_complete_user_data('id', $user->id);
        return update_internal_user_password($user, $newpassword);
    }

    function prevent_local_passwords() {
        return false;
    }

    /**
     * Returns true if this authentication plugin is 'internal'.
     *
     * @return bool
     */
    function is_internal() {
        return true;
    }

    /**
     * Returns true if this authentication plugin can change the user's
     * password.
     *
     * @return bool
     */
    function can_change_password() {
        return true;
    }

    /**
     * Returns the URL for changing the user's pw, or empty if the default can
     * be used.
     *
     * @return moodle_url
     */
    function change_password_url() {
        return null;
    }

    /**
     * Returns true if plugin allows resetting of internal password.
     *
     * @return bool
     */
    function can_reset_password() {
        return true;
    }

    /**
     * Prints a form for configuring this authentication plugin.
     *
     * This function is called from admin/auth.php, and outputs a full page with
     * a form for configuring this plugin.
     *
     * @param array $page An object containing all the data for this page.
     */
    function config_form($config, $err, $user_fields) {
        include "config.php";
		
    }

    /**
     * Processes and stores configuration data for this authentication plugin.
     */
    function process_config($config) {
        global $CFG;
		if (!isset ($config->appid)) {
			$config->appid = '';
		}
		if (!isset ($config->appsecret)) {
			$config->appsecret = '';
		}
		if (!isset ($config->createuser)) {
			$config->createuser = 0;
		}else{
			$config->createuser = 1;
		}
		if (!isset ($config->syncuserinfo)) {
			$config->syncuserinfo = 0;
		}else{
			$config->syncuserinfo = 1;
		}
		$plugin = 'auth/linkedin';
        set_config('appid', trim($config->appid), $plugin);
        set_config('appsecret', trim($config->appsecret), $plugin);
        set_config('createuser', $config->createuser, $plugin);
        set_config('syncuserinfo', trim($config->syncuserinfo), $plugin);
		set_config('callback', $CFG->wwwroot."/login/index.php", $plugin);
		return true;
    }
	
	function getAccessToken(){
		
	}
	
	function loginpage_hook() {
		global $CFG, $frm,$user;
		$frm = data_submitted();
		if(!$frm) $frm = new stdClass ();
		if(!isset($_REQUEST['code'])&&!isset($frm->username))
			include($CFG->dirroot."/auth/linkedin/linkedinlogin.php");
		if(!empty($_SESSION['LI_access_token'])||(isset($_REQUEST['code'])&&isset($_SESSION['LI_state']) && $_SESSION['LI_state'] == $_REQUEST['state'])){
			if(empty($_SESSION['LI_access_token'])){
				$token = $this->lioauth->getAccessToken($_REQUEST['code'], $this->config->callback);
				$_SESSION['LI_access_token'] = $token;
			}
			$this->lioauth->setAccessToken($_SESSION['LI_access_token']);
			$profile = $this->lioauth->getProfile();
			$u = $this->getMoodleUser($profile->emailAddress);
			if($u){
				if($u->auth=='linkedin'){
					$frm->username = $u->username;
					$frm->password = "Rewq!234";
				}else{
					$user = $u;
				}
			}
			else{
				if($this->config->createuser){
					$usernew = new stdClass();
					$usernew->username = $profile->emailAddress;
					$usernew->email = $profile->emailAddress;
					$usernew->password = "Rewq!234";
					$usernew->auth = "linkedin";
					$usernew->firstname = $profile->firstName;
					$usernew->lastname  = $profile->lastName;
					$usernew->confirmed = 1;
					$usernew->city = preg_replace("#,.*#","",$profile->location->name);
					$usernew->country = strtoupper(substr($profile->location->country->code,0,2));
					$usernew->mnethostid = $CFG->mnet_localhost_id;
					if($this->user_signup($usernew)){
						$frm->username = $profile->emailAddress;
						$frm->password = "Rewq!234";
					}
				}
			}
		}
	}
	
	function user_exists($username){
		global $DB;
		$user = $DB->get_record("user",array("username"=>$username));
		return is_object($user)&&property_exists($user, "id")&&  is_numeric($user->id);
	}
	
	
	function user_signup($user, $notify = false) {
		global $CFG, $DB, $PAGE, $OUTPUT;

        require_once($CFG->dirroot.'/user/profile/lib.php');

        if ($this->user_exists($user->username)) {
            print_error('auth_linkedin_user_exists', 'auth_linkedin');
        }

        $plainslashedpassword = $user->password;

        $user->id = $DB->insert_record('user', $user);
		profile_save_data($user);
        // This will also update the stored hash to the latest algorithm
        // if the existing hash is using an out-of-date algorithm (or the
        // legacy md5 algorithm).
        update_internal_user_password($user, $plainslashedpassword);

        $user = $DB->get_record('user', array('id'=>$user->id));
        events_trigger('user_created', $user);

        
        if ($notify) {
            $emailconfirm = get_string('emailconfirm');
            $PAGE->set_url('/auth/ldap/auth.php');
            $PAGE->navbar->add($emailconfirm);
            $PAGE->set_title($emailconfirm);
            $PAGE->set_heading($emailconfirm);
            echo $OUTPUT->header();
            notice(get_string('emailconfirmsent', '', $user->email), "{$CFG->wwwroot}/index.php");
        } else {
			return true;
		}
	}



	/**
	 * Retrieve the Moodle user given username of linkedin user
	 * 
	 * @param int $fb_id linkedin User ID
	 * @return string Moodle User ID
	 */
	function getMoodleUser($email) {
		global $DB;
		return $DB->get_record('user', array('email' => $email), '*');
	}
	
	function prelogout_hook(){
		unset($_SESSION['LI_access_token']);
		unset($_SESSION['LI_state']);
	}
	
}


