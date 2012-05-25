<?php
/**
 * Clockwork PHP API
 *
 * @package     Clockwork
 * @copyright   Mediaburst Ltd 2012
 * @license     ISC
 * @link        http://www.clockworksms.com
 */


/*
 * ClockworkHTTP
 *
 * //TODO: write a description
 *
 * @package     Clockwork
 * @subpackage  HTTP
 * @since       1.0
 */
class ClockworkHTTP {

    const VERSION = 1.0;

    /**
     * Proxy server hostname (Optional)
     */
    public $proxy_host;

    /**
     * Proxy server port (Optional)
     */
    public $proxy_port;

    /**
     * Does the server/HTTP wrapper support SSL
     *
     * This is a best guess effor, some servers have weird setups where even 
     * though cURL is compiled with SSL support is still fails to make
     * any requests.
     *
     * @return bool     True if SSL is supported
     */
    public function sslSupport() {
        $ssl = false;
        // See if PHP is compiled with cURL
        if (extension_loaded('curl')) {
            $version = curl_version();
            $ssl = ($version['features'] & CURL_VERSION_SSL) ? true : false;
        } elseif (extension_loaded('openssl')) {
            $ssl = true;
        }
        return $ssl;
    }

    /**
     * Make an HTTP POST
     *
     * cURL will be used if available, otherwise tries the PHP stream functions
     * The PHP stream functions require at least PHP 5.0, cURL should work with PHP 4
     *
     * @param string url URL to send to
     * @param string type MIME Type of data
     * @param string data Data to POST
     * @return string Response returned by server
     */
    public function post($url, $type, $data) {
        if(extension_loaded('curl')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: $type"));
            curl_setopt($ch, CURLOPT_USERAGENT, 'Clockwork PHP Wrapper/1.0' . self::VERSION);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            if (isset($this->proxy_host) && isset($this->proxy_port)) {
                curl_setopt($ch, CURLOPT_PROXY, $this->proxy_host);
                curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxy_port);
            }

            $response = curl_exec($ch);
            $info = curl_getinfo($ch);

            if ($response === false || $info['http_code'] != 200) {
                throw new Exception('HTTP Error calling Clockwork API - HTTP Status: ' . $info['http_code'] . ' - cURL Erorr: ' . curl_error($ch));
            } elseif (curl_errno($ch) > 0) {
                throw new Exception('HTTP Error calling Clockwork API - cURL Error: ' . curl_error($ch));
            }

            curl_close($ch);

            return $response;
        } elseif (function_exists('stream_get_contents')) {

            // Enable error Track Errors
            $track = ini_get('track_errors');
            ini_set('track_errors',true);

            $params = array('http' => array(
                'method'  => 'POST',
                'header'  => "Content-Type: $type\r\nUser-Agent: mediaburst PHP Wrapper/" . self::VERSION . "\r\n",
                'content' => $data
            ));

            if (isset($this->proxy_host) && isset($this->proxy_port)) {
                $params['http']['proxy'] = 'tcp://'.$this->proxy_host . ':' . $this->proxy_port;
                $params['http']['request_fulluri'] = True;
            }

            $ctx = stream_context_create($params);
            $fp = @fopen($url, 'rb', false, $ctx);
            if (!$fp) {
                ini_set('track_errors',$track);
                throw new Exception("HTTP Error calling Clockwork API - fopen Error: $php_errormsg");
            }
            $response = @stream_get_contents($fp);
            if ($response === false) {
                ini_set('track_errors',$track);
                throw new Exception("HTTP Error calling Clockwork API - stream Error: $php_errormsg");
            }
            ini_set('track_errors',$track);
            return $response;
        } else {
            throw new Exception("Clockwork requires PHP5 with cURL or HTTP stream support");
        }
    }
}
