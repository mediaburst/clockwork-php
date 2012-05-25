<?php
/**
 * Clockwork PHP API
 *
 * @package     Clockwork
 * @copyright   Mediaburst Ltd 2012
 * @license     ISC
 * @link        http://www.clockworksms.com
 * @version     1.0
 */


if ( !class_exists('ClockworkHTTP')) {
    require_once('class-ClockworkHTTP.php');
}
if ( !class_exists('ClockworkException')) {
    require_once('class-ClockworkException.php');
}

/**
 * Main Clockwork API Class
 * 
 * @package     Clockwork
 * @since       1.0
 */
class Clockwork {

    /**
     * All Clockwork API calls start with BASE_URL
     */
    const API_BASE_URL      = 'api.clockworksms.com/xml/';

    /**
     * string to append to API_BASE_URL for sending SMS
     */
    const API_SMS_METHOD    = 'sms';

    /**
     * string to append to API_BASE_URL for checking message credit
     */
    const API_CREDIT_METHOD = 'credit';

    /** 
     * Clockwork API Key
     * 
     * @var string
     */
    public $key;

    /**
     * Use SSL when making HTTP requests
     *
     * If this is not set, SSL will be used where PHP supports it
     *
     * @var bool
     */
    public $ssl;

    /**
     * Proxy server hostname (Optional)
     *
     * @var string
     */
    public $proxy_host;

    /**
     * Proxy server port (Optional)
     *
     * @var integer
     */
    public $proxy_port;

    /**
     * From address used on text messages
     *
     * @var string (11 characters or 12 numbers)
     */
    public $from;

    /**
     * Allow long SMS messages (Cost up to 3 credits)
     *
     * @var bool
     */
    public $long;

    /**
     * Truncate message text if it is too long
     *
     * @var bool
     */
    public $truncate;

    /**
     * Enables various logging of messages when true.
     *
     * @var bool
     */
    public $log;

    /**
     * What Clockwork should do if you send an invalid character
     *
     * Possible values:
     *      'error'     - Return an error (Messasge is not sent)
     *      'remove'    - Remove the invalid character(s)
     *      'replace'   - Replace invalid characters where possible, remove others 
     */
    public $invalid_char_action;

    /**
     * Class to use when making HTTP requests
     * 
     * By default this will use ClockworkHTTP which wraps cURL and PHP Streams
     * to find a working implementation. 
     * 
     * If you're using a framework CMS that already contains HTTP functionality 
     * extend or replace the ClockworkHTTP class and pass your class name 
     * as the http_class (string) option in the options array.
     * 
     * @var string
     */
    private $http_class;

    /**
     * Create a new instance of the Clockwork wrapper
     *
     * @param   string  key         Your Clockwork API Key
     * @param   array   options     Optional parameters for sending SMS
     */
    public function __construct($key, array $options = array()) {
        if (empty($key)) {
            throw new ClockworkException("Key can't be blank");
        } else {
            $this->key = $key;
        }
        
        $this->ssl                  = (array_key_exists('ssl', $options)) ? $options['ssl'] : null;
        $this->proxy_host           = (array_key_exists('proxy_host', $options)) ? $options['proxy_host'] : null;
        $this->proxy_port           = (array_key_exists('proxy_port', $options)) ? $options['proxy_port'] : null;
        $this->from                 = (array_key_exists('from', $options)) ? $options['from'] : null;
        $this->long                 = (array_key_exists('long', $options)) ? $options['long'] : null;
        $this->truncate             = (array_key_exists('truncate', $options)) ? $options['truncate'] : null;
        $this->invalid_char_action  = (array_key_exists('invalid_char_action', $options)) ? $options['invalid_char_action'] : null;
        $this->log                  = (array_key_exists('log', $options)) ? $options['log'] : false;
        $this->http_class           = (array_key_exists('http_class', $options)) ? $options['http_class'] : 'ClockworkHTTP';
    }

    /**
     * Send some text messages
     * 
     *
     */
    public function send(array $sms) {
        if (!is_array($sms)) {
            throw new ClockworkException("sms parameter must be an array");
        }
        $single_message = $this->is_assoc($sms);

        if ($single_message) {
            $sms = array($sms);
        }

        $req_doc = new DOMDocument('1.0', 'UTF-8');
        $root = $req_doc->createElement('Message');
        $req_doc->appendChild($root);

        $user_node = $req_doc->createElement('Key');
        $user_node->appendChild($req_doc->createTextNode($this->key));
        $root->appendChild($user_node);

        for ($i = 0; $i < count($sms); $i++) {
            $single = $sms[$i];

            $sms_node = $req_doc->createElement('SMS');
           
            // Phone number
            $sms_node->appendChild($req_doc->createElement('To', $single['to'])); 
            
            // Message text
            $content_node = $req_doc->createElement('Content');
            $content_node->appendChild($req_doc->createTextNode($single['message']));
            $sms_node->appendChild($content_node);

            // From
            if (array_key_exists('from', $single) || isset($this->from)) {
                $from_node = $req_doc->createElement('From');
                $from_node->appendChild($req_doc->createTextNode(array_key_exists('from', $single) ? $single['from'] : $this->from));
                $sms_node->appendChild($from_node);
            }

            // Client ID
            if (array_key_exists('client_id', $single)) {
                $client_id_node = $req_doc->createElement('ClientID');
                $client_id_node->appendChild($req_doc->createTextNode($single['client_id']));
                $sms_node->appendChild($client_id_node);
            }

            // Long
            if (array_key_exists('long', $single) || isset($this->long)) {
                $long = array_key_exists('long', $single) ? $single['long'] : $this->long;
                $long_node = $req_doc->createElement('Long');
                $long_node->appendChild($req_doc->createTextNode($long ? 1 : 0));
                $sms_node->appendChild($long_node);
            }

            // Truncate
            if (array_key_exists('truncate', $single) || isset($this->truncate)) {
                $truncate = array_key_exists('truncate', $single) ? $single['truncate'] : $this->truncate;
                $trunc_node = $req_doc->createElement('Truncate');
                $trunc_node->appendChild($req_doc->createTextNode($truncate ? 1 : 0));
                $sms_node->appendChild($trunc_node);
            }

            // Invalid Char Action
            if (array_key_exists('invalid_char_action', $single) || isset($this->invalid_char_action)) {
                $action = array_key_exists('invalid_char_action', $single) ? $single['invalid_char_action'] : $this->invalid_char_action;
                switch (strtolower($action)) {
                    case 'error':
                        $sms_node->appendChild($req_doc->createElement('InvalidCharAction', 1));
                        break;
                    case 'remove':
                        $sms_node->appendChild($req_doc->createElement('InvalidCharAction', 2));
                        break;
                    case 'replace':
                        $sms_node->appendChild($req_doc->createElement('InvalidCharAction', 3));
                        break;
                    default:
                        break;
                }
            }

            // Wrapper ID
            $sms_node->appendChild($req_doc->createElement('WrapperID', $i));

            $root->appendChild($sms_node);
        }

        $req_xml = $req_doc->saveXML();
     
        $resp_xml = $this->postToClockwork(self::API_SMS_METHOD, $req_xml);
        $resp_doc = new DOMDocument();
        $resp_doc->loadXML($resp_xml);   

        $response = array();
        $err_no = null;
        $err_desc = null;

        foreach($resp_doc->documentElement->childNodes AS $doc_child) {
            switch(strtolower($doc_child->nodeName)) {
                case 'sms_resp':
                    $resp = array();
                    $wrapper_id = null;
                    foreach($doc_child->childNodes AS $resp_node) {
                        switch(strtolower($resp_node->nodeName)) {
                            case 'messageid':
                                $resp['id'] = $resp_node->nodeValue;
                                break;
                            case 'errno':
                                $resp['error_code'] = $resp_node->nodeValue;
                                break;
                            case 'errdesc':
                                $resp['error_message'] = $resp_node->nodeValue;
                                break;
                            case 'wrapperid':
                                $wrapper_id = $resp_node->nodeValue;
                                break;
                        }
                    }
                    $resp['success'] = !array_key_exists('error_code', $sms);
                    $resp['sms'] = $sms[$wrapper_id];
                    array_push($response, $resp);
                    break;
                case 'errno':
                    $err_no = $doc_child->nodeValue;
                    break;
                case 'errdesc':
                    $err_desc = $doc_child->nodeValue;
                    break;
            }
        }

        if (isset($err_no)) {
            throw new ClockworkException($err_desc, $err_no);
        }
        
        if ($single_message) {
            return $response[0];
        } else {
            return $response;
        }
    }

    /**
     * Check how many SMS credits you have available
     *
     * @return  integer|float   SMS credits remaining
     */
    public function checkCredit() {
        // Create XML doc for request
        $req_doc = new DOMDocument('1.0', 'UTF-8');
        $root = $req_doc->createElement('Credit');
        $req_doc->appendChild($root);
        $root->appendChild($req_doc->createElement('Key', $this->key));
        $req_xml = $req_doc->saveXML();

        // POST XML to Clockwork
        $resp_xml = $this->postToClockwork(self::API_CREDIT_METHOD, $req_xml);

        // Create XML doc for response
        $resp_doc = new DOMDocument();
        $resp_doc->loadXML($resp_xml);

        // Parse the response to find credit value
        $credit;
        $err_no = null;
        $err_desc = null;
        
        foreach ($resp_doc->documentElement->childNodes AS $doc_child) {
            switch ($doc_child->nodeName) {
                case "Credit":
                    $credit = $doc_child->nodeValue;
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

        if (isset($err_no)) {
            throw new ClockworkException($err_desc, $err_no);
        }
        return $credit;
    }

    /**
     * Make an HTTP POST to Clockwork
     *
     * @param   string   method Clockwork method to call (sms/credit)
     * @param   string   data   Content of HTTP POST
     *
     * @return  string          Response from Clockwork
     */
    private function postToClockwork($method, $data) {

        if ($this->log) {
            $this->logXML("API $method Request XML", $data);
        }

        $http_class = $this->http_class;
        $http = new $http_class();
        
        $ssl = isset($this->ssl) ? $this->ssl : $http->sslSupport();

        $url = $ssl ? 'https://' : 'http://';
        $url.= self::API_BASE_URL . $method;

        print "URL: $url\n";

        $http->proxy_host = isset($this->proxy_host) ? $this->proxy_host : null;
        $http->proxy_port = isset($this->proxy_port) ? $this->proxy_port : null;

        $response = $http->Post($url, 'text/xml', $data);

        if ($this->log) {
            $this->logXML("API $method Response XML", $response);
        }

        return $response;
    }

    /**
     * Log some XML, tidily if possible, in the PHP error log
     *
     * @param   string  log_msg The log message to prepend to the XML
     * @param   string  xml     An XML formatted string
     *
     * @return  void
     */
    protected function logXML($log_msg, $xml) {
        // Tidy if possible
        if (class_exists('tidy')) {
            $tidy = new tidy;
            $config = array(
                'indent'     => true,
                'input-xml'  => true,
                'output-xml' => true,
                'wrap'       => 200
            );
            $tidy->parseString($xml, $config, 'utf8');
            $tidy->cleanRepair();
            $xml = $tidy;
        }
        // Output
        error_log("Clockwork $log_msg: $xml");
    }

    /*
     * Check if an array is associative
     *
     * @param   array   array   Array to check
     *
     * @return  bool    
     */
    function is_assoc($array) {
        return (bool)count(array_filter(array_keys($array), 'is_string'));
    }

}
