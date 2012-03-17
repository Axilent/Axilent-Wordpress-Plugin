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
 * @version Works with Axilent API beta1
 */
class Axilent 
{   
    /**
     * The subdmain used for the API
     * @var string
     */
    protected $_apiDomain = null;
    
    /**
     * The API Key
     * @var string
     */
    protected $_apiKey = null;
    
    /**
     * A template for API Functions
     * @var string 
     */
    protected $_functionPrototype = "http://%s.axilent.net/api/%s/%s/";
    
    /**
     * A template for API Resources
     * @var string 
     */
    protected $_resourcePrototype = "http://%s.axilent.net/api/resource/%s/%s/";
    
    /**
     * The version of the API we're using
     * @var string
     */
    protected $_apiVersion = "beta1";
    
    /**
     * A hash of active API instances
     * @var Axilent 
     */
    protected static $_instances = array();
    
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
     * @param type $project The project name
     * @param type $apiKey 
     */
    public function __construct($subdomain, $project, $apiKey, $portlet_key = null)
    {
        $this->_apiDomain   = $subdomain;
        $this->_apiKey      = $apiKey;
        $this->_portletKey  = $portlet_key;
        $this->_project     = $project;
    }
    
    /**
     * Make a request to the Axilent API
     * @param type $method The request type, like "get", "post", etc.
     * @param type $type The name fo the resource we're sending data to, like
     *  "axilent.airtower"
     * @param type $path The URI to send data to
     * @param type $arguments An associative array that will be encoded as JSON 
     *  and posted
     * @return object An object with 'url', 'body' (response body), and 'status' (http status) 
     *  properties
     * @throws Exception If curl is not found
     */
    protected function _makeRequest($method, $type, $target, $path = false, $arguments = array())
    {
        if(!function_exists('curl_exec'))
        {
            throw new Exception("The cURL module must be installed to use this class");
        }

        $url = $this->_getRequestURL($type, $target, $path);
        
        if($method == 'get')
            $result = Axilent_Net::call('get', $url, $arguments, array(CURLOPT_USERPWD => $this->_apiKey));
        else
            $result = Axilent_Net::call($method, $url, json_encode($arguments), array(CURLOPT_USERPWD => $this->_apiKey));

        return json_decode($result);
    }
    
    /**
     * Get the request url for a call
     * @param type $target The resourceor function, such as "axilent.airtower"
     * @param type $path The URI for the request and the given resource
     */
    protected function _getRequestURL($type, $target, $path = false)
    {
        return sprintf($type == 'resource' ? $this->_resourcePrototype : $this->_functionPrototype, 
                $this->_apiDomain,
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
        
        return "http://{$this->_portletKey}@wpdev.axilent.net/airtower/portlets/content/?key={$content_key}&content_type=post";
    }
    
    /**
     * Get and array of relevant content
     * @param type $content_key 
     */
    public function getRelevantContent($policy_slug, $content_key = false)
    {
        $args = array('content_policy_slug' => $policy_slug);
        
        if($content_key) $args['basekey'] = $content_key;
        
        $result = $this->_makeRequest('get', 'function', 'axilent.content', 'policycontent', $args);
        
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
     * Import content (create or update)
     * @param array $content An associative array of content fields to values
     * @param type $content_key  The content key. If this isn't provided, this
     *  will be treated as an update
     * @return The content key of the post just sent
     */
    public function postContent($content, $content_key = false, $content_type = 'post')
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
        
        $response = $this->_makeRequest($method, 'resource', 'axilent.airtower', 'content', $args);
        
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
        $options     = array(CURLOPT_RETURNTRANSFER => true) + $options;

        curl_setopt_array($curl_handle, $options);

        $timer = "Call to $url via HTTP";

        $body   = curl_exec($curl_handle);
        $status = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
    
        #exit("$url / $status / $body / " . print_r($options, true));
        
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