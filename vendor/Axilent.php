<?php
/**
 * This file contains classes for facilitating Axilent API calls. The only one
 *  relevant to the average develper is class 'Axilent'. Axilent_Net is a
 *  utility class containing static methods for making various HTTP requests.
 * 
 * Usage:
 *  $client = new Axilent('fooproj, 'Foo Proj', 'somemumbojumbo');
 *  $client->postContent(array('title' => 'Title Text', 'content' => 'Body Text'))
 */

/**
 * This is the core Axilent PHP library. It is used to authenticate and make
 *  API calls to Axilent.
 * @author Kenny Katzgrau, Katzgrau LLC <kenny@katgrau.com> www.katzgau.com
 * @version Works with Axilent API beta3
 */
class Axilent 
{        
    /**
     * The base URL for the Axilent API. Will be set int he constructor
     * @var type 
     */
    protected $_apiBase = null;
    
    /**
     * The API base template, less the domain
     * @var type 
     */
    protected $_apiBaseTemplate = "https://%s/";
    
    /**
     * The domain for the API endpoints
     * @var type 
     */
    protected $_apiDomain = "www.axilent.net";
    
    /**
     * The API Key
     * @var string
     */
    protected $_deploymentKey = null;
    
    /**
     * A template for API Functions
     * @var string 
     */
    protected $_functionPrototype = "api/%s/%s/";
    
    /**
     * A template for API Resources
     * @var string 
     */
    protected $_resourcePrototype = "api/resource/%s/%s/";
    
    /**
     * The version of the API we're using
     * @var string
     */
    protected $_apiVersion = "beta3";
    
    /**
     * A hash of active API instances
     * @var Axilent 
     */
    protected static $_instances = array();
    
    /**
     * The API Key
     * @var string
     */
    protected $_libraryKey = null;
    
    /**
     * A portlet key
     * @var type 
     */
    protected $_portletKey = null;
    
    /**
     * The project name
     * @var type 
     */
    protected $_project = null;
    
    /**
     * Create a new Axilent API client
     * @param string $project The project name
     * @param string $libraryKey 
     * @param string $deploymentKey
     */
    public function __construct($project, $libraryKey = false, $deploymentKey = false, $portlet_key = null)
    {
        $this->_libraryKey    = $libraryKey;
        $this->_deploymentKey = $deploymentKey;
        $this->_portletKey    = $portlet_key;
        $this->_project       = $project;
        
        # Build the API
        $this->_apiBase     = sprintf($this->_apiBaseTemplate, $this->_apiDomain);
        $this->_functionPrototype = $this->_apiBase . $this->_functionPrototype;
        $this->_resourcePrototype = $this->_apiBase . $this->_resourcePrototype;
    }
    
    /**
     * Make a request to the Axilent API
     * @param type $method The request type, like "get", "post", etc.
     * @param type $type The type of the object we're hitting, like 'function'
     *  or 'resource'
     * @param type $path The URI to send data to, like "axilent.library"
     * @param type $arguments An associative array that will be encoded as JSON 
     *  and posted
     * @return object An object with 'url', 'body' (response body), and 'status' (http status) 
     *  properties
     * @throws Exception If one of several errors is thrown
     */
    protected function _makeRequest($method, $type, $target, $path = false, $arguments = array())
    {
        if(!function_exists('curl_exec'))
        {
            throw new Exception("The cURL module must be installed to use this class");
        }

        $url = $this->_getRequestURL($type, $target, $path);
        
        /* Figure out which sort of auth key we should use */
        $key = false;
        if($target == 'axilent.library')
        {
            if($this->_libraryKey === false)
                throw new Axilent_MisconfigurationException('You must initialize the Axilent client with a library key to push data to Axilent');

            $key = $this->_libraryKey;
        }
        else
        {
            if($this->_deploymentKey === false)
                throw new Axilent_MisconfigurationException('You must initialize the Axilent client with a deployment key to retrieve data from Axilent');
             
            $key = $this->_deploymentKey;
        }
        
        /* JSON-encode the arguments if it's a POST or PUT */
        if($method == 'get')
            $result = Axilent_Net::call('get', $url, $arguments, array(CURLOPT_USERPWD => $key));
        else
            $result = Axilent_Net::call($method, $url, json_encode($arguments), array(CURLOPT_USERPWD => $key));

        /* Check for anything gone wrong */
        if(Axilent_Net::$lastStatus == '409') {
            throw new Axilent_MisconfigurationException("Recieved misconfiguration status from endpoint at $url with HTTP $method and args" . print_r($arguments, true));
        }
        
        if(Axilent_Net::$lastStatus == '401') {
            throw new Axilent_UnauthorizedException("Recieved an HTTP 401 (Unauthorized) exception from $url on HTTP $method and args " . print_r($arguments, true));
        }
        
        if(substr(Axilent_Net::$lastStatus, 0, 1) == '4') {
            throw new Axilent_HTTPException("General HTTP Error (".Axilent_Net::$lastStatus.") found at $url with HTTP $method and args" . print_r($arguments, true));
        }
        
        return json_decode($result);
    }
    
    /**
     * Get the request url for a call
     * @param type $target The resourceor function, such as "axilent.library"
     * @param type $path The URI for the request and the given resource
     */
    protected function _getRequestURL($type, $target, $path = false)
    {
        return sprintf($type == 'resource' ? $this->_resourcePrototype : $this->_functionPrototype,
                $target,
                $this->_apiVersion) . ($path ? rtrim($path, '/').'/' : '');
    }
    
    /**
     * Generate a portlet URL based on the portlet key that was provided at
     *  object instantiation
     * @throws Exception If the portlet key was not provided
     */
    public function getPortletURL($content_key = '')
    {
        if($this->_portletKey === null)
            throw new Exception("Portlet key was not provided at initialization of " . __CLASS__);
        
        $domain_with_auth = $this->_portletKey . '@' . $this->_apiDomain;
        $temp_base = sprintf($this->_apiBaseTemplate, $domain_with_auth);
        
        return "{$temp_base}airtower/portlets/content/?key={$content_key}&content_type=Post";
    }
    
    /**
     * Get and array of relevant content
     * @param type $content_key 
     */
    public function getRelevantContent($policy_slug, $content_key = false, $limit = 10)
    {
        $args = array('channel' => $policy_slug, 'limit' => $limit);
        
        if($content_key) $args['basekey'] = $content_key;
        
        $result = $this->_makeRequest('get', 'function', 'axilent.content', 'contentchannel', $args);
        
        return $result;
    }
    
    
    /**
     * Does this class have a portlet key?
     * @return bool
     */
    public function hasPortletKey()
    {
        return (bool)$this->_portletKey;
    }
    
    /**
     * Ping the axilent API to check for configuration status. 
     * @param type $content_type The content type to check. 'post' by default
     * @throws Various Axilent HTTP Exceptions if something goes wrong
     * @return bool False for anything other than an HTTP 200
     */
    public function ping($content_type = 'Post') 
    {
        $response = $this->_makeRequest('get', 'function', 'axilent.library', 'ping', array (
            'project' => $this->_project,
            'content_type' => $content_type
        ));
        
        return (Axilent_Net::$lastStatus == '200');
    }
    
    /**
     * Import content (create or update)
     * @param array $content An associative array of content fields to values
     * @param type $content_key  The content key. If this isn't provided, this
     *  will be treated as an update
     * @return The content key of the post just sent
     */
    public function postContent($content, $content_key = false, $content_type = 'Post')
    {
        $args = array (
            'project'      => $this->_project, 
            'content'      => $content, 
            'content_type' => $content_type
        );
        
        $method = 'post';

        if($content_key)
        {
            $method = 'put';
            $args['key'] = $content_key;
        }
        
        $response = $this->_makeRequest($method, 'resource', 'axilent.library', 'content', $args);
        
        # If a PUT fails, come back with a post
        if($method == 'put' && Axilent_Net::$lastStatus != '200') {
            $response = $this->_makeRequest('post', 'resource', 'axilent.library', 'content', $args);
        }
        
        if(!$content_key) 
        {
            list($type, $key) = explode(':', $response->created_content);
            return $key;
        }
        else
        {
            list($type, $key) = explode(':', $response->updated_content);
            return $key;
        }
    }
}

/**
 * Facilitates HTTP GET, POST, PUT, and DELETE calls using cURL as a backend. For
 *  GET, will fallback to file_get_contents
 */
class Axilent_Net
{
    /**
     * Will do a bunch of things for debugging purposes.
     *  - Write the request headers for any calls to request.txt
     * @var bool
     */
    protected static $_debug = true;
    
    /**
     * The HTTP status of the last request
     * @var type 
     */
    public static $lastStatus = 0;
    
    /**
     * Fetch a web resource by URL
     * @param string $url The HTTP URL that the request is being made to
     * @param array  $options Any PHP cURL options that are needed
     * @return object An object with properties of 'url', 'body', and 'status'
     */
    public static function fetch($url, $options = array())
    {
        if(!function_exists('curl_exec'))
        {
            if(!$options) return file_get_contents ($url);
            else return '';
        }

        $curl_handle = curl_init($url);
        $options     = array(CURLOPT_RETURNTRANSFER => true, 
                             CURLOPT_FOLLOWLOCATION => true) 
                       + $options;

        if(self::$_debug)
        {
            $f = tmpfile();
            $options[CURLOPT_VERBOSE] = 1;
            $options[CURLOPT_STDERR] = $f;
            self::_log("Call to $url ...");
        }
        
        curl_setopt_array($curl_handle, $options);

        $body   = curl_exec($curl_handle);
        $status = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
        
        if(self::$_debug) {
            fseek($f, 0);
            self::_log("Request headers (".date('Y-m-d H:i:s')."): " . stream_get_contents($f));
            fclose($f);
            self::_log("Recieved HTTP $status and \n $body in respose..");
        }
    
        #exit("$url / $status / $body / " . print_r($options, true));
        
        self::$lastStatus = $status;
        
        if(!$status) throw new Axilent_HTTPException("Error making request to $url with ".print_r($options, true).". \nStatus: $status");

        return $body;
    }
    
    /**
     * Make an HTTP call
     * @param type $method The HTP method to use
     * @param type $url The URL to call
     * @param type $data The data to pass (if applicable)
     * @param type $options The options to pass
     * @throws Axilent_ArgumentException 
     */
    public static function call($method, $url, $data, $options)
    {
        if(method_exists(__CLASS__, $method)) {
            return call_user_func_array(array(__CLASS__, $method), array($url, $data, $options));
        } else {
            throw new Axilent_ArgumentException("Method '$method' not allowed on " . __CLASS__);
        }
    }

    /**
     * Issues an HTTP GET request to the specified URL
     * @param string $url
     * @return object An object with properties of 'url', 'body', and 'status'
     */
    public static function get($url, $data = false, $options = array())
    {
        if($data) $url .= '?'.http_build_query($data);
        return self::fetch($url, $options);
    }

    
    /**
     * Log a message to a log file
     * @param type $message 
     */
    protected static function _log($message)
    {
        if(!self::$_debug) return;
        
        error_log("Axilent DEBUG: $message");
    }
    
    /**
     * Issues an HTTP POST request to the specified URL with the supplied POST
     *  body
     * @param string $url
     * @param string $data The raw POST body
     * @return object An object with properties of 'url', 'body', and 'status'
     */
    public static function post($url, $data = false, $options = array())
    {
        $options = array (
                    CURLOPT_POST       => true,
                    CURLOPT_POSTFIELDS => $data
                    ) + $options;

        return self::fetch($url, $options);
    }

    /**
     * Issues an HTTP DELETE to the specified URL
     * @param string $url
     * @return object An object with properties of 'url', 'body', and 'status'
     */
    public static function delete($url, $data = false, $options = array())
    {
        $options = array (CURLOPT_CUSTOMREQUEST => 'DELETE') + $options;
        return self::fetch($url, $options);
    }

    /**
     * Issues an HTTP PUT to the specified URL
     * @param string $url
     * @param string $data Raw PUT data
     * @return object An object with properties of 'url', 'body', and 'status'
     */
    public static function put($url, $data = false, $options = array())
    {
        $putData = tmpfile();

        fwrite($putData, $data);
        fseek($putData, 0);

        $options = array (
                        CURLOPT_PUT        => true,
                        CURLOPT_INFILE     => $putData,
                        CURLOPT_INFILESIZE => strlen($data)
                        ) + $options;
        
        $result = self::fetch($url, $options);
        fclose($putData);

        return $result;
    }
}

class Axilent_Exception extends Exception {}
class Axilent_ArgumentException extends Axilent_Exception {}
class Axilent_HTTPException extends Axilent_Exception {}
class Axilent_UnauthorizedException extends Axilent_HTTPException {}
class Axilent_MisconfigurationException extends Axilent_HTTPException {}
