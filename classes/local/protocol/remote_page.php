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
    // An array of \DOMNode instances, script elements in the document with data-astra-jquery attribute.
    protected $astrajqueryscriptelements;
    protected $responseheaders = array();

    protected $learningobjects; // The learning object in Ad Astra that corresponds to the URL.
    // Used for fixing relative URLs in some cases.

    /**
     * Create a remote page: a HTML page whose content and metadata are downloaded from a server.
     *
     * @param string $url URL of the remote page.
     * @param boolean $post True to set request method to HTTP POST, otherwise GET is used.
     * @param array $data POST payload key-value pairs.
     * @param array $files An array of files to upload. Keys are used as POST data keys and values are
     * objects with fields filename (original base name), filepath (full path) and mimetype.
     * @param string $apikey API key for authorization, null if not used.
     * @param null|string $stamp Timestamp string for If-Modified-Since request header. Only usable in HTTP GET requests.
     * @throws \mod_adastra\local\protocol\remote_page_exception If there are errors in connecting to the server.
     * @throws \mod_adastra\local\protocol\remote_page_not_modified If the $stamp argument is used and the remote page
     * has not been modified since that time.
     */
    public function __construct($url, $post = false, $data = null, $files = null, $apikey = null, $stamp = null) {
        $this->url = $url;
        list($this->response, $this->responseheaders) = self::request($url, $post, $data, $files, $apikey, $stamp);
        libxml_use_internal_errors(true); // Disable libxml errors.
        // Libxml (DOMDocument) prints a lot of warnings when it does not recognize (valid) HTML5 elements or
        // sees unexpected <p> end tags... Disable error reporting to avoid useless spam.
        $this->domdoc = new \DOMDocument();
        if ($this->domdoc->loadHTML($this->response) === false) {
            $message = '';
            foreach (libxml_get_errors() as $error) {
                switch ($error->level) {
                    case LIBXML_ERR_WARNING:
                        $message .= "Warning {$error->code}: ";
                        break;
                    case LIBXML_ERR_ERROR:
                        $message .= "Error {$error->code}: ";
                        break;
                    case LIBXML_ERR_FATAL:
                        $message .= "Fatal Error {$error->code}: ";
                        break;
                }
                $message .= trim($error->message);
                $message .= "\n";
            }
            libxml_clear_errors();
            throw new \mod_adastra\local\protocol\remote_page_exception(
                    "DOMDocument::loadHTML could not loag the response\n" . $message
            );
        }
        libxml_clear_errors();
    }

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
     * Set the learning object that corresponds to the remote page (URL).
     * Set it before calling any load method so that the information of
     * the learning object may be used in fixing relative URLs in the remote page.
     *
     * @param \mod_adastra\local\data\learning_object $lobject
     * @return void
     */
    public function set_learning_object(\mod_adastra\local\data\learning_object $lobject) {
        $this->learningobject = $lobject;
    }

    /**
     * Load the exercise page (usually containing instructions and submission form,
     * or chapter content) from the exercise service.
     *
     * @param \mod_adastra\local\data\learning_object $learningobject
     * @return \mod_adastra\local\protocol\exercise_page The exercise page.
     */
    public function load_exercise_page(\mod_adastra\local\data\learning_object $learningobject) {
        $page = new \mod_adastra\local\protocol\exercise_page($learningobject);
        $this->parse_page_content($learningobject, $page);
        return $page;
    }

    /**
     * Load the feedback page for a new submission and store the grading results
     * if the submission was graded synchronously.
     *
     * @param \mod_adastra\local\data\exercise $exercise
     * @param \mod_adastra\local\data\submission $submission
     * @param boolean $nopenalties
     * @return \mod_adastra\local\protocol\exercise_page The feedback page.
     */
    public function load_feedback_page(
            \mod_adastra\local\data\exercise $exercise,
            \mod_adastra\local\data\submission $submission,
            $nopenalties = false
    ) {
        $page = new \mod_adastra\local\protocol\exercise_page($exercise);
        $this->parse_page_content($exercise, $page);
        if ($page->isloaded) {
            $feedback = $page->content;
            if ($page->isaccepted) {
                if ($page->isgraded) {
                    $servicepoints = $page->points;
                    if (isset($page->meta['max_points'])) {
                        $servicemaxpoints = $page->meta['max_points'];
                    } else {
                        $servicemaxpoints = $exercise->get_max_points();
                    }

                    $submission->grade($servicepoints, $servicemaxpoints, $feedback, null, $nopenalties);
                } else {
                    $submission->set_waiting();
                    $submission->set_feedback($feedback);
                    $submission->save();
                }
            } else if ($page->isrejected) {
                $submission->set_rejected();
                $submission->set_feedback($feedback);
                $submission->save();
            } else {
                $submission->set_error();
                $submission->set_feedback($feedback);
                $submission->save();
            }
        }
        return $page;
    }

    /**
     * Parse the page content and insert the data to the $page.
     *
     * @param \mod_adastra\local\data\learning_object $lobj
     * @param \mod_adastra\local\protocol\exercise_page $page
     * @return void
     */
    protected function parse_page_content(
            \mod_adastra\local\data\learning_object $lobj,
            \mod_adastra\local\protocol\exercise_page $page
    ) {
        if ($lobj->is_submittable()) {
            $this->fix_form_action($lobj);
            $this->fix_form_multiple_checkboxes();
        } else {
            // Chapter: find embedded exercise elements and add exercise URL to data attributes,
            // AJAX Javascript will load the exercise to the DOM.
            $replacevalues = array();
            foreach ($lobj->get_children() as $childex) {
                $replacevalues[] = array(
                        'id' => 'chapter-exercise-' . $childex->get_order(),
                        'data-aplus-exercise' => \mod_adastra\local\urls\urls::exercise($childex),
                );
            }
            $this->find_and_replace_element_attributes('div', 'data-aplus-exercise', $replacevalues);
        }
        // Fix relative URLs (make them absolute with the address of the origin server).
        $this->fix_relative_urls();

        // Find tags in <head> that have attribute data-aplus.
        $this->aplusheadelements = $this->find_head_elements_with_attribute('data-aplus');

        // Find script tags in the document with attribute data-astra-jquery="$" (attribute value is optional).
        $this->astrajqueryscriptelements = $this->find_script_elements_with_attribute('data-astra-jquery');
        // Remove the script tags from the document, their contents will be inserted again later as
        // Moodle page requirements (not done in this class).
        foreach ($this->astrajqueryscriptelements as $scriptelem) {
            $scriptelem->parentNode->removeChild($scriptelem);
        }

        $page->isloaded = true;

        // Save CSS and JS code or elements that should be injected to the final page.
        // These values are strings.
        $page->injectedcssurls = $this->get_injected_css_urls();
        $page->injectedjsurlsandinline = $this->get_injected_js_urls_and_inline();
        $page->inlinejqueryscripts = $this->get_inline_jquery_scripts();

        // Find learning object content.
        $page->content = $this->get_element_or_body(
                array('exercise', 'aplus', 'chapter'),
                array('class' => 'entry-content')
        );

        // Parse metadata.
        $maxpoints = $this->get_meta('max-points');
        if ($maxpoints !== null) {
            $page->meta['max_points'] = $maxpoints;
        }
        $maxpoints = $this->get_meta('max_points'); // Underscore preferred.
        if ($maxpoints !== null) {
            $page->meta['max_points'] = $maxpoints;
        }
        $page->meta['status'] = $this->get_meta('status');
        if ($page->meta['status'] === 'accepted') {
            $page->isaccepted = true; // Accepted for async grading.
            $metawait = $this->get_meta('wait');
            if (!empty($metawait) || $metawait === '0') {
                // If the remote page has non-empty attribute value for wait, we should wait.
                // PHP thinks empty('0') === true.
                $page->iswait = true;
            }
        } else if ($page->meta['status'] === 'rejected') {
            $page->isrejected = true;
        }

        $page->meta['points'] = $this->get_meta('points');
        if ($page->meta['points'] !== null) {
            $page->points = (int) $page->meta['points'];
            $page->isgraded = true;
            $page->isaccepted = true;
            $page->iswait = false;
        }

        $metatitle = $this->get_meta('DC.Title');
        if ($metatitle) {
            $page->meta['title'] = $metatitle;
        } else {
            $page->meta['title'] = $this->get_title();
        }

        $page->meta['description'] = $this->get_meta('DC.Description');

        $page->lastmodified = $this->get_header('last-modified');
        $page->expires = $this->get_expires();
    }

    /**
     * Return the value of the given HTTP response header, i.e.,
     * header returned by the server when this page was retrieved.
     *
     * @param string $name Name of the HTTP header, e.g., 'Last-Modified'.
     * @return mixed|boolean The value of the header, or false if not found.
     */
    public function get_header($name) {
        $name = strtolower($name);
        if (isset($this->responseheaders[$name])) {
            return $this->responseheaders[$name];
        } else {
            return false;
        }
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

    /**
     * Return the Unix timestamp corresponding to the Expires HTTP response header.
     *
     * @return int A Unix timestamp.
     */
    public function get_expires() {
        return self::parse_expires($this->get_header('Expires'));
    }

    /**
     * Return href values of link elements in the document head that have the attribute data-aplus.
     * The page must be loaded before calling this.
     *
     * @return array An array of strings.
     */
    public function get_injected_css_urls() {
        $cssurls = array();
        foreach ($this->aplusheadelements as $element) {
            if ($element->nodeName == 'link' && $element->getAttribute('rel') == 'stylesheet') {
                $href = $element->getAttribute('href');
                if ($href != '') {
                    $cssurls[] = $href;
                }
            }
        }
        return $cssurls;
    }

    /**
     * Return src values and inline Javascript code strings of script elements in the document
     * head that have the attribute data-aplus. The page must be loaded before calling this.
     *
     * @return array An array of two arrays: the first array is a list of URLs (strings) to JS
     * code files; the second array is a list of Javascript code strings (inline code).
     */
    public function get_injected_js_urls_and_inline() {
        $jsurls = array();
        $jsinlineelements = array();
        foreach ($this->aplusheadelements as $element) {
            if ($element->nodeName == 'script') {
                $src = $element->getAttribute('src');
                if ($src != '') {
                    // Link to JS code file.
                    $jsurls[] = $src;
                } else {
                    $jsinlineelements[] = $element->textContent; // Code inside <script> tags.
                }
            }
        }
        return array($jsurls, $jsinlineelements);
    }

    /**
     * Return inline Javascript code strings of script elements in the document
     * that have the attribute data-astra-jquery. The JS code is expected to use
     * the jQuery JS library with the name given as the value of the data attribute,
     * by default "$" is assumed. The page must be loaded before calling this method.
     *
     * @return array An array of arrays: one array for each found script element. The
     * nested arrays consist of two elements: inline JS code and the name for jQuery.
     */
    public function get_inline_jquery_scripts() {
        $jscodes = array();
        foreach ($this->astrajqueryscriptelements as $elem) {
            $attrval = $elem->getAttribute('data-astra-jquery');
            if (!$attrval) {
                $attrval = '$'; // Default.
            }
            $jscodes[] = array($elem->textContent, $attrval); // Inline code and the name for jQuery.
        }
        return $jscodes;
    }

    /**
     * Return HTML string of the contents of the element with the given id or attribute value,
     * or body if no element is found with the given id or attribute in the HTML document.
     * The contents of an element refer to its inner HTML, excluding the outer element itself.
     *
     * @param array $ids An array of ID values to search for: the first hit is returned. Use
     * empty array to avoid searching for IDs.
     * @param array $searchattrs An array of (div) element attributes to search for: the first hit
     * is returned but only if no ID was found. Array keys are attribute names and array values are
     * attribute values.
     * @return null|string HTML string, null if the document has no body.
     */
    protected function get_element_or_body(array $ids, array $searchattrs) {
        $element = null;
        // Search for id.
        foreach ($ids as $id) {
            $element = $this->domdoc->getElementById($id);
            if (!is_null($element)) {
                return self::dom_inner_html($element);
            }
        }

        // Search for attributes.
        foreach ($this->domdoc->getElementsByTagName('div') as $node) {
            if ($node->nodeType == \XML_ELEMENT_NODE) {
                foreach ($searchattrs as $attrname => $attrvalue) {
                    if ($node->getAttribute($attrname) == $attrvalue) {
                        return self::dom_inner_html($node);
                    }
                }
            }
        }

        // Resort to body since no id/attr was found.
        // Note: using id is more reliable than parsing content from body.
        $nodeslist = $this->domdoc->getElementsByTagName('body');
        if ($nodeslist->length == 0) {
            return null;
        }
        $element = $nodeslist->item(0); // There should always be exactly one body.
        return self::dom_inner_html($element);
    }

    /**
     * Return HTML of the inner contents of a DOMNode (or DOMElement).
     * The element itself is not included, only its children (and their children etc.).
     * DOMDocument->saveHTML($element) gives the HTML including the element itself.
     * Source: http://stackoverflow.com/a/2087136
     *
     * @param \DOMNode $element
     * @return string
     */
    public static function dom_inner_html(\DOMNode $element) {
        $innerhtml = '';
        $children = $element->childNodes;

        foreach ($children as $child) {
            $innerhtml .= $element->ownerDocument->saveHTML($child);
        }

        return $innerhtml;
    }

    /**
     * Sets the action attribute of the form elements in the page to the submission handler in Moodle.
     *
     * @param \mod_adastra\local\data\exercise $ex
     * @return void
     */
    protected function fix_form_action(\mod_adastra\local\data\exercise $ex) {
        $nodeslist = $this->domdoc->getElementsByTagName('form');
        // Set action to the new submission handler in Moodle.
        $formaction = \mod_adastra\local\urls\urls::new_submission_handler($ex);
        foreach ($nodeslist as $formnode) {
            if ($formnode->nodeType == \XML_ELEMENT_NODE) {
                $formnode->setAttribute('action', $formaction);
            }
        }
    }

    /**
     * Add array notation to form checkbox groups: if there are checkbox inputs
     * that use the same name but the name does not end with brackets []. PHP
     * cannot parse multi-checkbox form input without the array notation.
     *
     * @return void
     */
    protected function fix_form_multiple_checkboxes() {
        $nodeslist = $this->domdoc->getElementsByTagName('input');
        $checkboxesbyname = array();
        // Find checkbox groups (multiple checkboxes with the same name) such that
        // their names do not use the array notation [] yet.
        foreach ($nodeslist as $inputnode) {
            if ($inputnode->nodeType == \XML_ELEMENT_NODE && $inputnode->getAttribute('type') == 'checkbox') {
                $name = $inputnode->getAttribute('name');
                if ($name != '' && \strpos(\strrev($name), '][') !== 0) {
                    // Name attr not empty and does not already end with [].
                    if (!isset($checkboxesbyname[$name])) {
                        $checkboxesbyname[$name] = array();
                    }
                    $checkboxesbyname[$name][] = $inputnode;
                }
            }
        }

        // Add [] to the checkbox names, if they form a group.
        foreach ($checkboxesbyname as $name => $inputnodes) {
            if (\count($inputnodes) > 1) {
                foreach ($inputnodes as $inputnode) {
                    $inputnode->setAttribute('name', $name . '[]');
                }
            }
        }
    }

    /**
     * Return the value of a meta element.
     *
     * @param string $name Name attribute value of the meta element.
     * @return string|null The value, or null if it is not set.
     */
    protected function get_meta($name) {
        if (!isset($this->metanodes)) {
            $this->metanodes = $this->domdoc->getElementsByTagName('meta');
        }
        foreach ($this->metanodes as $node) {
            if ($node->nodeType == \XML_ELEMENT_NODE && $node->getAttribute('name') == $name) {
                if ($node->hasAttribute('value')) {
                    return $node->getAttribute('value');
                } else if ($node->hasAttribute('content')) {
                    return $node->getAttribute('content');
                } else {
                    return null;
                }
            }
        }
        return null;
    }

    /**
     * Return the contents of the title element in the DOMDocument.
     *
     * @return string|null
     */
    protected function get_title() {
        $titlenodes = $this->domdoc->getElementsByTagName('title');
        foreach ($titlenodes as $node) { // There is usually exactly one title element.
            return $node->textContent;
        }
        return null;
    }

    /**
     * Find elements of type $tagname that have attribute $attrname. Then, replace the attributes of the element
     * with the attribute values in $replacevalues. Only the attributes given in $replacevalues are affected.
     *
     * @param string $tagname Name of the elements/tags that are searched.
     * @param string $attrname Attribute name that is used in the search of elements.
     * @param array $replacevalues An array of new attribute values, separately for each element. The outer
     * array is traversed in the same order as $tagname elements with attribute $attrname are found in the
     * document, while the corresponding inner array is used to replace attribute values. The inner array
     * has attribute names as keys and attribute values as array values.
     * @return void
     */
    protected function find_and_replace_element_attributes($tagname, $attrname, array $replacevalues) {
        $length = count($replacevalues);
        if ($length == 0) {
            return;
        }
        $i = 0;
        foreach ($this->domdoc->getElementsByTagName($tagname) as $node) {
            if ($node->nodeType == \XML_ELEMENT_NODE && $node->hasAttribute($attrname)) {
                $attrstoreplace = $replacevalues[$i];
                foreach ($attrstoreplace as $replaceattrname => $replaceattrvalue) {
                    if (substr($replaceattrname, 0, strlen('?')) === '?') {
                        // If the attribute name for replacing has been prefixed with a question mark,
                        // only replace the attribute if the element had the attribute previously.
                        $replaceattrname = substr($replaceattrname, 1); // Drop the first ?.
                        if ($node->hasAttribute($replaceattrname)) {
                            $node->setAttribute($replaceattrname, $replaceattrvalue);
                        }
                    } else {
                        $node->setAttribute($replaceattrname, $replaceattrvalue);
                    }
                }

                $i += 1;
                if ($i >= $length) {
                    return;
                }
            }
        }
    }

    /**
     * Return an array of DOMNodes that are located inside the document head element and
     * have attribute $attrname.
     *
     * @param string $attrname The attribute name to search.
     * @return \DOMNode[]
     */
    protected function find_head_elements_with_attribute($attrname) {
        $elements = array();
        // There should be one head element.
        foreach ($this->domdoc->getElementsByTagName('head') as $head) {
            foreach ($head->childNodes as $node) {
                if ($node->nodeType == \XML_ELEMENT_NODE && $node->hasAttribute($attrname)) {
                    $elements[] = $node;
                }
            }
        }
        return $elements;
    }

    /**
     * Return an array of DOMNodes that are script elements with the given attribute.
     *
     * @param string $attrname The attribute name to search for.
     * @return \DOMNode[]
     */
    protected function find_script_elements_with_attribute($attrname) {
        $elements = array();
        foreach ($this->domdoc->getElementsByTagName('script') as $scriptelem) {
            if ($scriptelem->hasAttribute($attrname)) {
                $elements[] = $scriptelem;
            }
        }
        return $elements;
    }

    /**
     * Return the base address/URL of this remote page.
     *
     * @return array An array containing the domain and path.
     */
    public function base_address() {
        $remoteurlcomponents = \parse_url($this->url);
        $domain = '';
        $path = '';
        $hostport = '';
        if (isset($remoteurlcomponents['scheme'])) {
            $domain .= $remoteurlcomponents['scheme'] . '://';
        }
        if (isset($remoteurlcomponents['user'])) {
            $domain .= $remoteurlcomponents['user'];
        }
        if (isset($remoteurlcomponents['pass'])) {
            $domain .= ':' - $remoteurlcomponents['pass'] . '@';
        }

        if (isset($remoteurlcomponents['host'])) {
            $hostport .= $remoteurlcomponents['host'];
        }
        if (isset($remoteurlcomponents['port'])) {
            $hostport .= ':' . $remoteurlcomponents['port'];
        }
        if (defined('ADASTRA_REMOTE_PAGE_HOSTS_MAP') && isset(ADASTRA_REMOTE_PAGE_HOSTS_MAP[$hostport])) {
            // Transform the host into another if it is set in the configuration. This is particularly
            // used in testing to deal with fake/internal domains and the localhost domain.
            $domain .= ADASTRA_REMOTE_PAGE_HOSTS_MAP[$hostport];
        } else {
            $domain .= $hostport;
        }

        if (isset($remoteurlcomponents['path'])) {
            $path = $remoteurlcomponents['path'];
        }
        if (empty($path)) {
            $path = '/';
        } else if (mb_substr($path, -1) !== '/') {
            // Remove the last part in path, e.g. "chapter.html" in "/course/module/chapter.html".
            $path = dirname($path) . '/';
        }
        return array($domain, $path);
    }

    /**
     * Fix relative URLs so that the address points to the origin server. Otherwise, the relative URL
     * would be interpreted as relative inside the Moodle server.
     *
     * @return void
     */
    protected function fix_relative_urls() {
        // Parse remote server domain and base path.
        list($domain, $path) = $this->base_address();

        $tagsattrs = array(
            'img' => 'src',
            'script' => 'src',
            'iframe' => 'src',
            'link' => 'href',
            'a' => 'href',
            'video' => 'poster',
            'source' => 'src',
        );
        foreach ($tagsattrs as $tag => $attr) {
            $this->_fix_relative_urls($domain, $path, $tag, $attr);
        }
    }

    /**
     * Find relative URLs in the document and make them point to the origin server.
     *
     * @param string $domain The domain of the origin server, e.g. 'https://example.com'
     * @param string $path The base path to use if the original URL paths are relative.
     * @param string $tagname Search the document for these elements, e.g. 'img'.
     * @param string $attrname Fix URLs in this attribute of the element, e.g. 'src'.
     * @return void
     */
    protected function _fix_relative_urls($domain, $path, $tagname, $attrname) {
        global $DB;

        // Regular expressions for detecting certain kinds of URLs.
        // Recognize absolute URLs (https: or // or data: etc.) or anchor URLs (#someid).
        // URL scheme starts with an alphabetic character and may contain alphabets,
        // digits, dots as well as plus and minus characters.
        $pattern = '%^(#|//|[[:alpha:]][[:alnum:].+-]*:)%';
        // Link between chapters when the chapters are in different rounds.
        $chapterpattern = '%(\.\./)?(?P<roundkey>[\w-]+)/(?P<chapterkey>[\w-]+)(\.html)(?P<anchor>#.+)?$%';
        // Link between chapters in the same round.
        $chaptersameround = '%^(?P<chapterkey>[\w-]+)(\.html)(?P<anchor>#.+)?$%';

        foreach ($this->domdoc->getElementsByTagName($tagname) as $elem) {
            if ($elem->nodeType == \XML_ELEMENT_NODE && $elem->hasAttribute($attrname)) {
                $value = $elem->getAttribute($attrname);
                if (empty($value)) {
                    continue;
                }

                if (self::is_internal_link($elem, $value)) {
                    // Custom transform for RST chapter to chapter links.
                    // The link must refer to Moodle, not the exercise service.
                    $chapterrecord = null;
                    $matches = array();
                    if (preg_match($chapterpattern, $value, $matches)) {
                        // Find the chapter with the remote key and the exercise round key.
                        $chapterrecord = $DB->get_record_sql(
                                \mod_adastra\local\data\learning_object::get_subtype_join_sql(
                                        \mod_adastra\local\data\chapter::TABLE) .
                                ' JOIN {' . \mod_adastra\local\data\exercise_round::TABLE . '} round ON round.id = lob.roundid ' .
                                ' WHERE lob.remotekey = ? AND round.remotekey = ? AND round.course = ?',
                                array(
                                        $matches['chapterkey'],
                                        $matches['roundkey'],
                                        $this->learningobject->get_exercise_round()->get_course()->courseid,
                                ));
                    } else if ($this->learningobject !== null && preg_match($chaptersameround, $value, $matches)) {
                        // Find the chapter with the remote key in the same round as the current exercise.
                        $chapterrecord = $DB->get_record_sql(
                                \mod_adastra\local\data\learning_object::get_subtype_join_sql(
                                        \mod_adastra\local\data\chapter::TABLE) .
                                ' JOIN {' . \mod_adastra\local\data\exercise_round::TABLE . '} round ON round.id = lob.roundid ' .
                                ' WHERE lob.remotekey = ? AND round.id = ?',
                                array($matches['chapterkey'], $this->learningobject->get_exercise_round()->get_id()));
                    }

                    if ($chapterrecord) {
                        $chapter = new \mod_adastra\local\data\chapter($chapterrecord);
                        $url = \mod_adastra\local\urls\urls::exercise($chapter);
                        // Keep the original URL anchor if it exists (#someid at the end).
                        if (isset($matches['anchor'])) {
                            $url .= $matches['anchor'];
                        }
                        // Replace the URL with the Moodle URL of the chapter.
                        $elem->setAttribute($attrname, $url);
                    }
                } else if (preg_match($pattern, $value) === 0) {
                    // Not absolute URL.

                    if ($elem->hasAttribute('data-aplus-path')) {
                        // Custom transform for RST generated exercises.
                        // Add the mooc-grader course key to the URL template.
                        $fixpath = str_replace('{course}', explode('/', $path)[1], $elem->getAttribute('data-aplus-path'));
                        $fixvalue = $value;
                        if (mb_substr($value, 0, strlen('../')) === '../') { // Variable $value starts with "../".
                            $fixvalue = mb_substr($value, 2); // Remove ".." from the start.
                        }

                        $newval = $domain . $fixpath . $fixvalue;
                    } else if ($value[0] == '/') { // Absolute path.
                        $newval = $domain . $value;
                    } else {
                        $newval = $domain . $path . $value;
                    }
                    $elem->setAttribute($attrname, $newval);
                }
            }
        }
    }

    /**
     * Return true if the element is an internal chapter link.
     *
     * @param DOMElement $elem
     * @param string $value The target URL of the element.
     * @return boolean
     */
    protected static function is_internal_link($elem, $value) {
        if ($elem->hasAttribute('data-aplus-chapter')) {
            return true;
        }
        $ishtml = '%\.html(#.+)?$%';
        if (preg_match($ishtml, $value) === 0) {
            // Not HTML, which implies not a chapter.
            return false;
        }
        // While the exercise service is not always including the data-aplus-chapter attribute
        // correctly, we need to check other attributes to find internal chapter links.
        $classattr = $elem->getAttribute('class');
        $classes = explode(' ', $classattr);

        $internalseen = false;
        $referenceseen = false;
        foreach ($classes as $cl) {
            if ($cl === 'internal') {
                $internalseen = true;
            } else if ($cl === 'reference') {
                $referenceseen = true;
            }
        }
        return ($internalseen && $referenceseen);
    }
}