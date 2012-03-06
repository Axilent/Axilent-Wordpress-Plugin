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
    protected $_apiBase = '';
    
    /**
     *
     * @var type 
     */
    protected $_username = '';
    
    /**
     *
     * @var type 
     */
    protected $_password = '';
    
    /**
     * 
     * @param type $api_base
     * @param type $username
     * @param type $password 
     */
    public function __construct($api_base, $username, $password) 
    {
        $this->_apiBase     = $api_base;
        $this->_username    = $username;
        $this->_password    = $password;
    }
    
    protected function _makeRequest($path, $arguments)
    {
        
    }
    
    public function getPortletURL()
    {
        
    }
    
    public function getRelevantContent($content_key)
    {
        
    }
    
    public function postContent($content, $content_key = false)
    {
        
    }
}