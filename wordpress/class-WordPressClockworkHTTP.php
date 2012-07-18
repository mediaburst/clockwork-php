<?php
/**
 * WordPress Clockwork class
 *
 * Extends the Clockwor wrapper class to use the
 * WordPress HTTP API for HTTP calls, attempts to work
 * round the differences in PHP versions, such as SSL
 * & curl support
 *
 * @package     Clockwork
 * @subpackage  WordPressClockwork       
 * @since       1.0
 */


class WordPressClockwork extends Clockwork {

    const OPTIONS_KEY = 'clockwork';

    /**
     * Check if the WordPress HTTP API can support SSL
     *
     * @returns bool True if SSL is supported
     */
    public function sslSupport() {
        return wp_http_supports(array('ssl'));
    }

    /**
     * Make an HTTP POST using the WordPress HTTP API.
     *
     * @param string url URL to send to
     * @param string data Data to POST
     * @return string Response returned by server
     */
    protected function xmlPost($url, $data) {
        $args = array(
            'body'    => $data,
            'headers' => array('Content-Type' => 'text/xml'),
            'timeout' => 10, // Seconds
        );

        // Check whether WordPress should veryify the SSL certificate
        if( stristr( $url, 'https://' ) ) {
            $args['sslverify'] = $this->sslVerify();
        }

        $result = wp_remote_post($url, $args);
        if (is_wp_error($result)) {
            error_log( "POST failed: " . $result->get_error_message() );
            throw new ClockworkException("HTTP Call failed - Error: ".$result->get_error_message());
        }
        return $result[ 'body' ];
    }

    /**
     * Verify SSL conectivity to the remote host
     *
     * If the request fails store a flag so that we 
     * don't need to do the check again
     */
    private function sslVerify() {

        $opt = get_option(self::OPTION_KEY);
        if(!$opt) {
            $opt = array();
        }
        if  ( !array_key_exists('sslverify', $opt)) {
            $args = array(
                'timeout' => 10, // Seconds
            );
            $result = wp_remote_post($url, $args);
            if(is_wp_error($result)) {
                $opt['sslverify'] = false;
            } else {
                $opt['sslverify'] = true;
            }
            update_option(self::OPTION_KEY, $opt);
        }
        return $opt['sslverify'];
    }

}
