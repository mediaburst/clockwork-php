<?php
/*  Copyright 2012, Mediaburst Limited.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Require the Clockwork API
if( !class_exists('Clockwork') ) {
  require_once( 'clockwork/class-Clockwork.php' );
}
if( !class_exists('WordPressClockwork') ) {
  require_once( 'clockwork/class-WordPressClockwork.php' );
}

/**
 * Base class for Clockwork plugins
 *
 * @package Clockwork
 * @author James Inman
 */
abstract class Clockwork_Plugin {
  
  /**
   * Version of the Clockwork Wordpress wrapper
   */
  const VERSION = '1.2.1';
	/**
	 * URL to signup for a new Clockwork account
	 */
	const SIGNUP_URL = 'http://www.clockworksms.com/platforms/wordpress/?utm_source=wpadmin&utm_medium=plugin&utm_campaign=wp-clockwork';
	/**
	 * URL to top up message credit
	*/
	const BUY_URL = 'https://app.clockworksms.com/purchase/?utm_source=wpadmin&utm_medium=plugin&utm_campaign=wp-clockwork';
	/**
	 * URL for support
	 */
	const SUPPORT_URL = 'http://www.clockworksms.com/support/?utm_source=wpadmin&utm_medium=plugin&utm_campaign=wp-clockwork';
  
  /**
   * @param $callback Callback function for the plugin's menu item
   *
   * @author James Inman
   */
  public $plugin_callback = null;
  
  /**
   * @param $plugin_dir Plugin directory name 
   *
   * @author James Inman
   */
  public $plugin_dir = null;
  
  /**
	 * Instance of WordPressClockwork
	 *
	 * @var WordPressClockwork
	 * @author James Inman
	 */
  protected $clockwork = null;

  /**
   * Setup admin panel menu, notices and settings
   *
   * @author James Inman
   */
  public function __construct() {
    // If Clockwork API key isn't set, convert existing username and password into API key
    $this->convert_existing_username_and_password();
    
    // Setup clockwork
    try {
      $options = get_option( 'clockwork_options' );
      if( is_array( $options ) && isset( $options['api_key'] ) ) {
        $this->clockwork = new WordPressClockwork( $options['api_key'] );
      }
    } catch( Exception $e ) {
    }
  
    // Register the activation hook to install
    register_activation_hook( __FILE__, array( $this, 'install' ) );
    
    add_action( 'admin_head', array( $this, 'setup_admin_head' ) );  
    add_action( 'admin_menu', array( $this, 'setup_admin_navigation' ) );
    add_action( 'admin_notices', array( $this, 'setup_admin_message' ) ); 
    add_action( 'admin_bar_menu', array( $this, 'setup_admin_bar' ), 999 );
    add_action( 'admin_init', array( $this, 'setup_admin_init' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'setup_clockwork_js' ) );
    
    $this->plugin_callback = array( $this, 'main' );
  }
    
  /**
   * Return the username and password from the plugin's existing options
   *
   * @return array Array of 'username' and 'password'
   * @author James Inman
   */
  abstract public function get_existing_username_and_password();
  
  /**
   * Setup HTML for the admin <head>
   *
   * @return void
   * @author James Inman
   */
  abstract public function setup_admin_head();
    
  /**
   * Convert existing username and password to a new API key
   *
   * @return void
   * @author James Inman
   */
  public function convert_existing_username_and_password() {
    $options = get_option( 'clockwork_options' );
    if( !is_array( $options ) || !isset( $options['api_key'] ) ) {
      $existing_details = $this->get_existing_username_and_password();
      
      if( is_array( $existing_details ) && isset( $existing_details['username'] ) && isset( $existing_details['password'] ) ) {
        try {
          // We have a username and password, now go and convert them
          $this->clockwork = new WordPressClockwork( $existing_details['username'], $existing_details['password'] );
          $key = $this->clockwork->createAPIKey( 'WordPress - ' . home_url() );
          // Set the Clockwork API key to be the newly created key
          update_option( 'clockwork_options', array( 'api_key' => $key ) );          
        } catch( ClockworkException $e ) {
          return;
        }
      }        
    }
  }
  
  /**
   * Called on plugin activation
   *
   * @return void
   * @author James Inman
   */
  public function install() {
  }
  
  /**
   * Tell the user to update their Clockwork options on every admin panel page if they haven't already
   *
   * @return void
   * @author James Inman
   */
  public function setup_admin_message() {
    // Don't bother showing the "You need to set your Clockwork options" message if it's that form we're viewing
    if( !isset( $this->clockwork ) && ( get_current_screen()->base != 'toplevel_page_clockwork_options' ) ) {
      $this->show_admin_message('You need to set your <a href="' . site_url() . '/wp-admin/admin.php?page=clockwork_options">Clockwork options</a> before you can use ' . $this->plugin_name . '.');
    }
  }
  
  /**
	 * Add the Clockwork balance to the admin bar
	 *
	 * @return void
	 * @author James Inman
	 */
  public function setup_admin_bar() {
		global $wp_admin_bar;
		if ( !is_super_admin() || !is_admin_bar_showing() ) {
			return;
		}

    $options = get_option( 'clockwork_options' );
    if( isset( $options['api_key'] ) ) {
  		// Display a low credit notification if there's no credit
      try {
        if( !isset( $this->clockwork ) ) {
          $clockwork = new WordPressClockwork( $options['api_key'] );
        }
    		$balance = $this->clockwork->checkBalance();
    		if( $balance['balance'] <= 0 && $balance['account_type'] == 'PAYG' ) {
    			$balance_string = '£0. Top up now!'; 
    		} else {
    			$balance_string = $balance['symbol'] . $balance['balance'];
    		}
    		// Add a node to the Admin bar
    	  $wp_admin_bar->add_node( array(
    	  	'id' => 'clockwork_balance',
    			'title' => 'Clockwork: ' . $balance_string,
    			'href' => self::BUY_URL ) 
    		);
      } catch( Exception $e ) {
        // Don't kill the entire admin panel because we can't get the balance
      }
    }
  }
  
  /**
   * Setup admin navigation: callback for 'admin_menu'
   *
   * @return void
   * @author James Inman
   */
  public function setup_admin_navigation() {
    global $menu;
    
    $menu_exists = false;
    foreach( $menu as $k => $item ) {
      if( $item[0] == "Clockwork SMS" ) {
        $menu_exists = true;
        break;
      }
    }

    // Setup global Clockwork options
    if( !$menu_exists ) {    
      add_menu_page( __( 'Clockwork SMS', $this->language_string ), __( 'Clockwork SMS', $this->language_string ), 'manage_options', 'clockwork_options', array( $this, 'clockwork_options' ), plugins_url( 'images/logo_16px_16px.png', dirname( __FILE__ ) ) );
      add_submenu_page( 'clockwork_options', __( 'Clockwork Options', $this->language_string ), __( 'Clockwork Options', $this->language_string ), 'manage_options', 'clockwork_options', array( $this, 'clockwork_options' ) );
      add_submenu_page( NULL, 'Test', 'Test', 'manage_options', 'clockwork_test_message', array( $this, 'clockwork_test_message' ) );
    }
    
    // Setup options for this plugin
    add_submenu_page( 'clockwork_options', __( $this->plugin_name, $this->language_string ), __( $this->plugin_name, $this->language_string ), 'manage_options', $this->plugin_callback[1], $this->plugin_callback );
  }
  
  /**
   * Set up javascript for the Clockwork admin functions
   *
   * @return void
   * @author James Inman
   */
  public function setup_clockwork_js() {
		wp_enqueue_script( 'clockwork_options', plugins_url( 'js/clockwork_options.js', dirname( __FILE__ ) ), array( 'jquery' ) );
  }
  
  /**
   * Register global Clockwork settings for API keys 
   *
   * @return void
   * @author James Inman
   */
  public function setup_admin_init() {
    register_setting( 'clockwork_options', 'clockwork_options', array( $this, 'clockwork_options_validate' ) );
    add_settings_section( 'clockwork_api_keys', 'API Key', array( $this, 'settings_api_key_text' ), 'clockwork' );
    add_settings_field( 'clockwork_api_key', 'Your API Key', array( $this, 'settings_api_key_input' ), 'clockwork', 'clockwork_api_keys' );   
    
    add_settings_section( 'clockwork_defaults', 'Default Settings', array( $this, 'settings_default_text' ), 'clockwork' );
    add_settings_field( 'clockwork_from', "'From' Number ", array( $this, 'settings_from_input' ), 'clockwork', 'clockwork_defaults' );    
  }
  
  /**
   * Introductory text for the API keys part of the form
   *
   * @return void
   * @author James Inman
   */
  public function settings_api_key_text() {
		echo '<p>You need an API key to use the Clockwork plugins.</p>';
	}
  
  /**
   * Introductory text for the default part of the form
   *
   * @return void
   * @author James Inman
   */
  public function settings_default_text() {
		echo '<p>Default settings apply to every Clockwork plugin you have installed.</p>';
	}
  
  /**
   * Input box for the API key
   *
   * @return void
   * @author James Inman
   */
  public function settings_api_key_input() {
    $options = get_option( 'clockwork_options' );
    
    if( isset( $options['api_key'] ) ) {      
      try {
        if( !isset( $this->clockwork ) ) {
          $this->clockwork = new WordPressClockwork( $options['api_key'] );
        }
      
        echo "<input id='clockwork_api_key' name='clockwork_options[api_key]' size='40' type='text' value='{$this->clockwork->key}' />";
      
        // Show balance
        $balance = $this->clockwork->checkBalance();
        if( $balance ) {
  	      echo '<p><strong>Balance:</strong> ' . $balance['symbol'] . $balance['balance'] . '&nbsp;&nbsp;&nbsp;<a href="' . self::BUY_URL . '" class="button">Buy More</a></p>';
  	    } else { // We can't get the credits for some reason
  		    echo '<p><a href="' . self::BUY_URL . '" class="button">Buy More Credit</a></p>';
  	    } 
      
      } catch( ClockworkException $e ) {
        echo "<input id='clockwork_api_key' name='clockwork_options[api_key]' size='40' type='text' value='' />";
        echo '<p><a href="' . self::SIGNUP_URL . '" class="button">Get An API Key</a></p>';        
      }
    
      return;
    } else {
      echo "<input id='clockwork_api_key' name='clockwork_options[api_key]' size='40' type='text' value='' />";
      echo '<p><a href="' . self::SIGNUP_URL . '" class="button">Get An API Key</a></p>';            
    }
  }
  
  /**
   * Input box for the from name
   *
   * @return void
   * @author James Inman
   */
  public function settings_from_input() {
    $options = get_option( 'clockwork_options' );
    if( isset( $options['from'] ) ) {
      echo "<input id='clockwork_from' name='clockwork_options[from]' size='40' maxlength='14' type='text' value='{$options['from']}' />";
    } else {
      echo "<input id='clockwork_from' name='clockwork_options[from]' size='40' maxlength='14' type='text' value='' />";
    }
    
    echo "<p>Enter the number your messages will be sent from. We recommend your mobile phone number.<br />UK customers can use alphanumeric strings up to 11 characters.</p>";
  }
  
  /**
   * Validation for the API key
   *
   * @return void
   * @author James Inman
   */
  public function clockwork_options_validate( $val ) {
    // From santization
    $val['from'] = preg_replace( '/[^A-Za-z0-9]/', '', $val['from'] );
    if( preg_match( '/[0-9]/', $val['from'] ) ) {
      $val['from'] = substr( $val['from'], 0, 14 );
    } else {
      $val['from'] = substr( $val['from'], 0, 11 );
    }
    $val['api_key']= trim($val['api_key']);
    // API key checking
    try {      
      $key = $val['api_key'];
      if( $key ) {
        
        $clockwork = new WordPressClockwork( $key );
        if( $clockwork->checkKey() ) {
          
          $this->clockwork = $clockwork;      
          add_settings_error( 'clockwork_options', 'clockwork_options', 'Your settings were saved! You can now start using Clockwork SMS.', 'updated' );    
          return $val;
          
        } else {
          add_settings_error( 'clockwork_options', 'clockwork_options', 'Your API key was incorrect. Please enter it again.', 'error' );  
          return false;        
        }
        
      } else {
        // Key is blank, but a blank update (they might have added 'from') is okay
        $key = '';
        add_settings_error( 'clockwork_options', 'clockwork_options', 'Your settings were saved! You can now start using Clockwork SMS.', 'updated' );
        return $val;
      }
      
    } catch( ClockworkException $ex ) {
      add_settings_error( 'clockwork_options', 'clockwork_options', 'Your API key was incorrect. Please enter it again.', 'error' );
      return false;
    }
    
    return $val;
  }
  
  /**
   * Render the main Clockwork options page
   *
   * @return void
   * @author James Inman
   */
  public function clockwork_options() {
    $this->render_template( 'clockwork-options' );
  }
  
  /**
   * Send a test SMS message
   *
   * @param string $to Mobile number to send to
   * @return void
   * @author James Inman
   */
  public function clockwork_test_message( $to ) {
    $log = array();
    
    global $wp_version;
    $log[] = "Using Wordpress " . $wp_version;
    $log[] = "Clockwork PHP wrapper initalised: using " . Clockwork::VERSION;
    $log[] = "Plugin wrapper initialised: using " . get_class($this) . ' ' . self::VERSION;
    $log[] = '';
    
    $options = get_option( 'clockwork_options' );
    
    // Check API key for sanity
    if( isset( $options['api_key'] ) && strlen( $options['api_key'] ) == 40 ) {
      $log[] = "API key is set and appears valid – " . $options['api_key'];
    } else {
      $log[] = "API key is not set, or is the incorrect length.";
      $log[] = "No credit has been used for this test";
      $this->output_test_message_log( $log );
      return;
    }
    
    // Check originator for sanity
    if( isset( $options['from'] ) && strlen( $options['from'] ) <= 14 ) {
      $log[] = "Originator is set to " . $options['from'] . " and is below 14 characters";
      
      // Then remove special characters
      $from = $options['from'];
      $replaced_from = preg_replace( '/[^a-zA-Z0-9]/', '', $from );
      
      if( $from == $replaced_from ) {
        $log[] = 'Replaced special characters in originator, no changes';
      } else {
        $log[] = 'Removed special characters from originator: ' . $replaced_from;
      }
      
      // Is it alphanumeric?
      if( preg_match( '/[a-zA-Z]/', $replaced_from ) == 1 ) {
        // Is it under 11 characters?
        if( strlen( $replaced_from ) <= 11 ) {
          $log[] = 'Alphanumeric originator is less than 11 characters – note that some countries reject alpha originators';
        } else {
          $log[] = 'You are trying to send with an alphanumeric originator over 11 characters in length';
          $log[] = "No credit has been used for this test";
          $this->output_test_message_log( $log );
          return;
        }
      } else {
        $log[] = 'Originator is set as numeric';
      }
      
    } else {
      $log[] = "Originator is not set, using your Clockwork account default (probably 84433)";
    }
        
    // Check if API key is valid
    $log[] = '';
    
    $clockwork = new WordPressClockwork( $options['api_key'] );
    if( $clockwork->checkKey() ) {
      $log[] = 'API key exists according to clockworksms.com';
    }
    
    // Check what the balance is
    $balance_resp = $clockwork->checkBalance();
    
    if( $balance_resp['balance'] > 0 ) {
      $log[] = 'Balance is ' . $balance_resp['symbol'] . $balance_resp['balance'];
    } 
	elseif($balance_resp['account_type']== 'Invoice'){
	$log[] = 'You have a credit account.  No need to check the balance.';
}
else {
      $log[] = 'Balance is 0. You need to add more credit to your Clockwork account';      
      $log[] = "No credit has been used for this test";
      $this->output_test_message_log( $log );
      return;
    }
        
    // Can we authenticate?
    $log[] = '';
    
    $message = 'This is a test message from Clockwork';
    
    if( !$clockwork->is_valid_msisdn( $_GET['to'] ) ) {
      $log[] = $_GET['to'] . ' appears an invalid number to send to, this message may not send';
    }
    
    $log[] = 'Attempting test send with API key ' . $options['api_key'] . ' to ' . $_GET['to'];

    try {
      $message_data = array( array( 'from' => $options['from'], 'to' => $_GET['to'], 'message' => $message ) );
      $result = $clockwork->send( $message_data );
      
      $log[] = '';
      
      if( isset( $result[0]['id'] ) && isset( $result[0]['success'] ) && ( $result[0]['success'] == '1' ) ) {
        $log[] = 'Message successfully sent with ID ' . $result[0]['id'];
        
        $log[] = '';
        $log[] = 'Used 5p of your Clockwork credit for this test';
        
        $balance_resp = $clockwork->checkBalance();
        $log[] = 'Your new balance is ' . $balance_resp['symbol'] . $balance_resp['balance'];
      } else {
        $log[] = 'There was an error sending the message: error code ' . $result[0]['error_code'] . ' – ' . $result[0]['error_message'];
        $log[] = "No credit has been used for this test";
      }
    } catch( ClockworkException $e ) {
      $log[] = "Error: " . $e->getMessage();
    } catch( Exception $e ) { 
      $log[] = "Error: " . $e->getMessage();
    }
    
    $this->output_test_message_log( $log );
  }
  
  protected function output_test_message_log( $log ) {
    $this->render_template( 'clockwork-test-message', array( 'log' => implode( "\r\n", $log ) ) );
  }
  
  /**
   * Show a message at the top of the administration panel
   *
   * @param string $message Error message to show (can include HTML) 
   * @param bool $errormsg True to display as a red 'error message'
   * @return void
   * @author James Inman
   */
  protected function show_admin_message( $message, $errormsg = false ) {
    if( $errormsg ) {
      echo '<div id="message" class="error">';
    } else {
      echo '<div id="message" class="updated fade">';
    }
  
    echo "<p><strong>$message</strong></p></div>";
  }
  
  /**
   * Render a template file from the templates directory
   *
   * @param string $name Path to template file, excluding .php extension
   * @param array $data Array of data to include in template
   * @return void
   * @author James Inman
   */
  protected function render_template( $name, $data = array() ) {
    include( WP_PLUGIN_DIR . '/' . $this->plugin_dir . '/templates/' . $name . '.php');
  }

}
