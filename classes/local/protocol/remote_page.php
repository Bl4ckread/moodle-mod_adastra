<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_adastra\local\protocol;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../local_settings.php');

/**
 * Class remote_page represents an HTML document that is downloaded from
 * a server. Either HTTP GET or POST is supported for requesting the page.
 *
 * Derived from A+ (a-plus/lib/remote_page.py and a-plus/exercise/protocol/aplus.py).
 */
class remote_page {

    protected $url;
    protected $response; // String, the whole reponse from the server.
    protected $domdoc; // Instance of class \DOMDocument.

    protected $metanodes = null; // Cache \DOMNodeList.
    protected $aplusheadelements; // An array of \DOMNode instances, nodes in document head with aplus attribute.
    // An array of \DOMNode instances, script elements in the document with data-adastra-jquery attribute.
    protected $adastrajqueryscriptelements;
    protected $responseheaders = array();

    protected $learningobjects; // The learning object in Ad Astra that corresponds to the URL.
    // Used for fixing relative URLs in some cases.

    /**
     * Send an HTTP request.
     *
     * @param string $url URL target of the HTTP request.
     * @param boolean $post If true set request method to HTTP POST, otherwise GET is used.
     * @param array $data POST payload as key-value pairs.
     * @param array $files An array of files to upload. Keys are used as POST data keys and
     * values are objects with fields filename, filepath and mimetype.
     * @param string $apikey API key for authorization, null if not used.
     * @param null|string $stamp Timestamp string for If-Modified-Since request header. Only usable in HTTP GET requests.
     * @throws \mod_adastra\local\protocol\service_connection_exception If there are errors in connecting to the server.
     * @throws \mod_adastra\local\protocol\exercise_service_exception If there is an error in the exercise service.
     * @throws \mod_adastra\local\protocol\remote_page_not_modified If the $stamp argument is used and the remote page has
     * not been modified since that time.
     * @return array An array of two elements: the response (string) and an array of response headers (header names as keys).
     */
    public static function request($url, $post = false, $data = null, $files = null, $apikey = null, $stamp = null) {
        $responseheaders = array();
        // Callback for storing HTTP response headers.
        $headerfunction = static function($curlhandle, $header) use(&$responseheaders) {
            // Array $parts[HEADER] = VALUE.
            $parts = explode(':', $header, 2);
            if (count($parts) == 2) {
                $responseheaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            // Else no colon (:) in the header, possibly the status line (HTTP 200).

            return strlen($header);
        };

        $ch = curl_init();
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true, // Response as string.
                CURLOPT_HEADER => false, // No header in output.
                CURLOPT_FOLLOWLOCATION => true, // Follow redirects (Location header).
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_HEADERFUNCTION => $headerfunction, // Save the HTTP response headers.
                CURLOPT_SSL_VERIFYPEER => true, // HTTPS certificate and security.
                CURLOPT_SSL_VERIFYHOST => 2,
            )
        );
        // CA certificates for HTTPS.
        curl_setopt_array($ch, self::server_ca_certificate_curl_options());

        $requestheaders = array();

        if (!is_null($apikey)) {
            $requestheaders[] = 'Authorization: key=' . $apikey; // HTTP request header for API key.
        }
        if (!empty($stamp) && !$post) {
            $requestheaders[] = 'If-Modified-Since: ' . $stamp;
        }
        if (!empty($requestheaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $requestheaders);
        }

        if ($post) {
            // Make the request HTTP Post instead of GET and add post data key-value pairs.
            curl_setopt($ch, CURLOPT_POST, true);
            if (empty($files)) {
                // Avoid array syntax in the POST data since Django does not parse it the way we want.
                curl_setopt($ch, CURLOPT_POSTFIELDS, self::build_query($data));
            } else {
                $postdata = $data;
                if (empty($data)) {
                    $postdata = array();
                }
                foreach ($files as $name => $fileobj) {
                    if (function_exists('curl_file_create')) {
                        $postdata[$name] = curl_file_create(
                            $fileobj->filepath,
                            $fileobj->mimetype,
                            $fileobj->filename
                        );
                    } else {
                        // Older PHP than 5.5.0.
                        // If any POST data value starts with @-sign, it is assumed to be a filepath.
                        $postdata[$name] = "@{$fileobj->filepath};type={$fileobj->mimetype}";
                    }
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
            }
        }

        $response = curl_exec($ch);
        if ($response === false) {
            // Curl failed.
            $error = curl_error($ch);
            curl_close($ch);
            throw new \mod_adastra\local\protocol\service_connection_exception($error);
        } else {
            // Check HTTP status code.
            $resstatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($resstatus == 304) {
                // Not modified ($stamp argument was used).
                $expires = self::parse_expires(isset($responseheaders['expires']) ?
                        $responseheaders['expires'] : null);
                throw new \mod_adastra\local\protocol\remote_page_not_modified($expires);
            } else if ($resstatus != 200) {
                // Server returned some error message.
                $error = "curl HTTP response status: {$resstatus}";
                throw new \mod_adastra\local\protocol\exercise_service_exception($error);
            }
        }
        return array($response, $responseheaders);
    }

    /**
     * Return a cURL options array for setting the CA certificate location(s).
     * The CA certificates are used to verify the peer certificatge in HTTPS connections.
     * The locations used may be configured in the admin settings of the Ad Astra plugin.
     *
     * @return array
     */
    protected static function server_ca_certificate_curl_options() {
        global $CFG;
        /*
         * Typical defaults for Ubuntu:
         * array(CURLOPT_CAINFO => '/etc/ssl/certs/ca_certificates.crt')
         * array(CURLOPT_CAPATH => '/etc/ssl/certs')
         */
        $cainfo = get_config(\mod_adastra\local\data\exercise_round::MODNAME, 'curl_cainfo');
        // Use CAINFO if it is set, otherwise CAPATH.
        if (empty($cainfo)) {
            $capath = get_config(\mod_adastra\local\data\exercise_round::MODNAME, 'curl_capath');
            if (empty($capath)) {
                require_once($CFG->libdir . '/filelib.php');
                $cainfo = \curl::get_cacert();
                if (empty($cainfo)) {
                    return array();
                } else {
                    return array(CURLOPT_CAINFO => $cainfo);
                }
            }
            return array(CURLOPT_CAPATH => $capath);
        } else {
            return array(CURLOPT_CAINFO => $cainfo);
        }
    }

    /**
     * Build URL-encoded query string from $data. This method does not use
     * array syntax in the result (when there are multiple values for the same name,
     * for example because an HTML form has multiple checkboxes that can be all selected).
     *
     * @param array|null $data Associative array, null allowed too.
     * @return string
     */
    public static function build_query($data) {
        if (empty($data)) {
            return '';
        }
        $q = self::build_query_helper($data);
        if (!empty($q)) { // Drop the trailing &.
            $q = \substr($q, 0, -1);
        }
        return $q;
    }

    /**
     * Helper function for build_query. Recursively goes throug the $data
     * tree and builds the query string.
     *
     * @param array $data
     * @param string|null $outerkey
     * @return string
     */
    private static function build_query_helper($data, $outerkey = null) {
        $q = '';
        foreach ($data as $key => $val) {
            if (\is_array($val)) {
                $q .= self::build_query_helper($val, $key);
            } else {
                if (\is_numeric($key) && $outerkey !== null) {
                    $key = $outerkey;
                }
                $q .= \urlencode($key) . '=' . \urlencode($val) . '&';
            }
        }
        return $q;
    }

    /**
     * Parse the value of the expires HTTP header.
     *
     * @param string $expiresheader A date string.
     * @return int A Unix timestamp.
     */
    public static function parse_expires($expiresheader) {
        if ($expiresheader && ($val = strtotime($expiresheader))) {
            // The expires header exists and can be parsed.
            return $val;
        }
        return 0;
    }
}