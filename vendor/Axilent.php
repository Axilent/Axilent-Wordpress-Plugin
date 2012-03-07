<?php
/**
 * This file contains a PHP class for facilitating Axilent API calls. 
 */

/**
 * This is the core Axilent PHP library. It is used to authenticate and make
 *  API calls to Axilent.
 * @author Kenny Katzgrau, Katzgrau LLC <kenny@katgrau.com> www.katzgau.com
 */
class Axilent 
{
    /**
     *
     * @var type 
     */
    protected $_apiBase = null;
    
    /**
     *
     * @var type 
     */
    protected $_apiKey = null;
    
    /**
     * 
     * @var Axilent 
     */
    protected static $_instances = array();
    
    /**
     * A portlet key
     * @var type 
     */
    protected $_portletKey = null;
    
    /**
     *
     * @var type 
     */
    protected $_username = null;
    
    /**
     * Create a new Axilent API client
     * @param type $api_base The base path of the API
     * @param type $username
     * @param type $apiKey 
     */
    public function __construct($api_base, $username, $apiKey, $portlet_key = null) 
    {
        $this->_apiBase     = rtrim($api_base, '/') . '/';
        $this->_username    = $username;
        $this->_apiKey      = $apiKey;
        $this->_portletKey  = $portlet_key;
    }
    
    /**
     * Make a request to the Axilent API
     * @param type $path The URI to send data to
     * @param type $arguments An associative array that will be encoded as JSON 
     *  and posted
     * @return object An object with 'url', 'body' (response body), and 'status' (http status) 
     *  properties
     * @throws Exception If curl is not found
     */
    protected function _makeRequest($path, $arguments)
    {
        if(!function_exists('curl_exec'))
        {
            throw new Exception("The cURL module must be installed to use this class");
        }

        $curl_handle = curl_init($url);
        $options     = array (
                            CURLOPT_USERPWD        => "{$this->_username}:{$this->_apiKey}",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_POST           => true,
                            CURLOPT_POSTFIELDS     => json_encode($arguments)
                       );

        curl_setopt_array($curl_handle, $options);

        $body   = curl_exec($curl_handle);
        $status = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);

        return (object)(array('url' => $url, 'body' => $body, 'status' => $status));
    }
    
    /**
     * Generate a portlet URL based on the portlet key that was provided at
     *  object instantiation
     * @throws Exception If the portlet key was not provided
     */
    public function getPortletURL()
    {
        if($this->_portletKey === null)
            throw new Exception("Portlet key was not provided at initialization of " . __CLASS__);
    }
    
    
    public function getRelevantContent($content_key)
    {
        
    }
    
    public function postContent($content, $content_key = false)
    {
        
    }
}