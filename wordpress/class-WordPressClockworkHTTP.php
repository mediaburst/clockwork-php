<?php
/**
* WordPress Clockwork class
*
* Extends the Clockwork wrapper class to use the
* WordPress HTTP API for HTTP calls, attempts to work
* round the differences in PHP versions, such as SSL
* and curl support
*
* @package     Clockwork
* @subpackage  WordPressClockwork       
* @since       1.0
*/

class WordPressClockwork extends Clockwork {

  /**
   * Options key for Clockwork plugins
   */
  const OPTIONS_KEY = 'clockwork_options';

  /**
   * String to append to API_BASE_URL for getting a new key
   */
  const API_GET_KEY_METHOD = 'get_key';

  /** 
   * Legacy username
   * 
   * @var string
   */
  private $username;

  /** 
   * Legacy password
   * 
   * @var string
   */
  public $password;

  /**
   * Create a new instance of the Clockwork wrapper
   *
   * Also supports passing a username and a password as first and second parameter, but ONLY when 
   * used in WordPressClockwork for the purposes of converting to an API key.
   *
   * @param   string  key         Your Clockwork API Key
   * @param   array   options     Optional parameters for sending SMS
   * @author James Inman
   */
  public function __construct( $arg1, $arg2 = array() ) {
    if( !isset( $arg2 ) || is_array( $arg2 ) ) {
      parent::__construct( $arg1, $arg2 );
    } else {
      $this->username = $arg1;
      $this->password = $arg2;
    }
  }

  public function createAPIKey( $name = 'WordPress API Key' ) {
    // Create XML doc for request
    $req_doc = new DOMDocument( '1.0', 'UTF-8' );
    $root = $req_doc->createElement( 'GetKey' );
    $req_doc->appendChild( $root );
    $root->appendChild( $req_doc->createElement( 'Username', $this->username ) );
    $root->appendChild( $req_doc->createElement( 'Password', $this->password ) );
    $root->appendChild( $req_doc->createElement( 'Name', $name ) );
    $req_xml = $req_doc->saveXML();

    // POST XML to Clockwork
    $resp_xml = $this->postToClockwork( self::API_GET_KEY_METHOD, $req_xml );

    // Create XML doc for response
    $resp_doc = new DOMDocument();
    $resp_doc->loadXML( $resp_xml );

    // Parse the response to find credit value
    $key = null;
    $err_no = null;
    $err_desc = null;

    foreach( $resp_doc->documentElement->childNodes as $doc_child ) {
      switch( $doc_child->nodeName ) {
        case "Key":
          $key = $doc_child->nodeValue;
          break;
        case "ErrNo":
          $err_no = $doc_child->nodeValue;
          break;
        case "ErrDesc":
          $err_desc = $doc_child->nodeValue;
          break;
        default:
          break;
      }
    }

    if( isset( $err_no ) ) {
      throw new ClockworkException( $err_desc, $err_no );
    }

    $this->key = $key;
    return $key;
  }

  /**
  * Check if the WordPress HTTP API can support SSL
  *
  * @returns bool True if SSL is supported
  */
  public function sslSupport() {
    return wp_http_supports( array( 'ssl' ) );
  }

  /**
  * Make an HTTP POST using the WordPress HTTP API.
  *
  * @param string url URL to send to
  * @param string data Data to POST
  * @return string Response returned by server
  */
  protected function xmlPost( $url, $data ) {
    $args = array(
    'body'    => $data,
    'headers' => array( 'Content-Type' => 'text/xml' ),
    'timeout' => 10, // Seconds
    );

    // Check whether WordPress should veryify the SSL certificate
    if( stristr( $url, 'https://' ) ) {
      $args['sslverify'] = $this->sslVerify( $url );
    }

    $result = wp_remote_post( $url, $args );
    if( is_wp_error( $result ) ) {
      error_log( "POST failed: " . $result->get_error_message() );
      throw new ClockworkException( "HTTP Call failed - Error: " . $result->get_error_message() );
    }
    return $result[ 'body' ];
  }

  /**
  * Verify SSL conectivity to the remote host
  *
  * If the request fails store a flag so that we 
  * don't need to do the check again
  */
  private function sslVerify($url) {
    $opt = get_option( self::OPTIONS_KEY );
    if( !$opt ) {
      $opt = array();
    }
    if( !array_key_exists( 'sslverify', $opt ) ) {
        $args = array(
        'timeout' => 10, // Seconds
      );
      $result = wp_remote_post( $url, $args );
      if( is_wp_error( $result ) ) {
        $opt['sslverify'] = false;
      } else {
        $opt['sslverify'] = true;
      }
      update_option( self::OPTIONS_KEY, $opt );
    }
    return $opt['sslverify'];
  }

}