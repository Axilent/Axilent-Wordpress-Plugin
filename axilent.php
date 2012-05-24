<?php
/*
Plugin Name: Axilent
Plugin URI: https://github.com/Axilent/
Description: Hook your Wordpress installation up with your Axilent account
Version: 1.0.6
Author: Kenny Katzgrau
Author URI: http://www.katzgrau.com
*/

require_once dirname(__FILE__) . '/lib/Utility.php';
require_once dirname(__FILE__) . '/lib/View.php';
require_once dirname(__FILE__) . '/lib/Model.php';
require_once dirname(__FILE__) . '/vendor/Axilent.php';

add_action('admin_menu',   array('Axilent_Core', 'registerAdmin'));
add_action('widgets_init', array('Axilent_Core', 'registerWidget'));
add_action('add_meta_boxes', array('Axilent_Core', 'addMetaBoxes'));
add_action('save_post', 	array('Axilent_Core', 'saveCallback'));

/**
 * This class is the core of the github/bitbucket project lister.
 */
class Axilent_Core
{
    /**
     * The amount of time to cache the projects for
     * @var int
     */
    public static $_cacheExpiration = 3600;
    
    /**
     * An active axlient client, if one has been created
     * @var Axilent
     */
    public static $_axilent = null;

    /**
     * Add the Axilent meta box below the post content 
     */
    static function addMetaBoxes()
    {
        add_meta_box( 
            'axilent_sectionid',
            __( 'Axilent Content Portlet', 'axilent_textdomain' ),
            array(__CLASS__, 'axilentAPIBox'),
            'post' 
        );
        add_meta_box(
            'axilent_sectionid',
            __( 'Axilent Content Portlet', 'axilent_textdomain'), 
            array(__CLASS__, 'axilentAPIBox'),
            'page'
        );
    }

    /* Prints the box content */
    function axilentAPIBox($post) {
        // Use nonce for verification
        wp_nonce_field(plugin_basename(__FILE__), 'axilent_noncename');

        $axilent = self::getAxilentClient();
        $content_key = get_post_meta($post->ID, 'axilent_content_key', true);
        
        if(!$axilent->hasPortletKey())
        {
            $markup = "You do not have a portlet key defined for your user account. Have an adminitrator set it on the Axilent plugin settings page.";
        }
        else
        {
            if($content_key)
            {
                try 
                {
                    $markup = '<iframe style="width:100%; height: 500px;" src="'.$axilent->getPortletURL($content_key).'"></iframe>';
                } 
                catch(Exception $ex) 
                {
                    $markup = 'There was an error loading this section.';
                    error_log($ex->__toString());
                }
            }
            else
            {
                $markup = '<p>When this post is saved, an Axilent portlet will appear here on subsequent views.</p>';
            }
        }
        
        echo $markup;
    }
    
    /**
     * @return Axilent  
     */
    static function getAxilentClient()
    {
        if(self::$_axilent) return self::$_axilent;
        
        $axilent_project_name   = Axilent_Utility::getOption('axilent_project_name');
        $axilent_api_key        = Axilent_Utility::getOption('axilent_api_key');
        $portlet_key            = get_user_meta(get_current_user_id(), 'axilent_portlet_key', true);

        self::$_axilent = new Axilent($axilent_project_name, $axilent_api_key, $portlet_key);
        
        return self::$_axilent;
    }
    
    /**
     * Register the admin settings page
     */
    static function registerAdmin()
    {
        add_options_page('Axilent', 'Axilent', 'edit_pages', 'axilent.php', array(__CLASS__, 'adminMenuCallback'));
    }

    /**
     * The function used by WP to print the admin settings page
     */
    static function adminMenuCallback()
    {
        $users   = Axilent_Utility::arrayGet($_POST, 'users');
        $submit  = (bool)($users);
        $updated = FALSE;

        if($submit)
        {
            foreach($users as $id => $value) 
            {
                $value = trim($value);
                update_user_meta($id, 'axilent_portlet_key', $value);
            }
            
            Axilent_Utility::setOption('axilent_project_name',      $_POST['axilent_project_name']);
            Axilent_Utility::setOption('axilent_api_key',           $_POST['axilent_api_key']);
            Axilent_Utility::setOption('axilent_title_field',       $_POST['axilent_title_field']);
            Axilent_Utility::setOption('axilent_link_field',        $_POST['axilent_link_field']);
            Axilent_Utility::setOption('axilent_description_field', $_POST['axilent_description_field']);
            Axilent_Utility::setOption('axilent_content_field',     $_POST['axilent_content_field']);
            
            if($_POST['axilent_sync'])
                Axilent_Utility::setOption('axilent_sync', 'yes');
            else
                Axilent_Utility::setOption('axilent_sync', 'no');
        }
        
        # Attach the API key to each user object
        $users = get_users(array('who' => 'author'));
        for($i = 0; $i < count($users); $i++) {
            $users[$i]->portlet_key = get_user_meta($users[$i]->ID, 'axilent_portlet_key', true);
        }
        
        $axilent_project_name       = Axilent_Utility::getOption('axilent_project_name');
        $axilent_api_key            = Axilent_Utility::getOption('axilent_api_key');
        $axilent_title_field        = Axilent_Utility::getOption('axilent_title_field');
        $axilent_description_field  = Axilent_Utility::getOption('axilent_description_field');
        $axilent_link_field         = Axilent_Utility::getOption('axilent_link_field');
        $axilent_content_field      = Axilent_Utility::getOption('axilent_content_field');
        $axilent_sync               = Axilent_Utility::getOption('axilent_sync');
        
        # Test the widgets if we need to
        if(isset($_POST['axilent_widget_test']))
        {
            $widget_errors = array();
            $widgets = get_option('widget_axilent_content');
            foreach($widgets as $order => $widget)
            {
                if(!is_numeric($order)) continue;
                
                $policy_content = $widget['w_policy_content'];
                
                try
                {
                    self::getAxilentClient()->getRelevantContent($policy_content);
                }
                catch(Axilent_MisconfigurationException $ex)
                {
                    $widget_errors[] = "Widget: The widget with policy content setting '$policy_content' is misconfigured: ".$ex->getMessage();
                }
                catch(Axilent_UnauthorizedException $ex)
                {
                    $widget_errors[] = "Unauthorized: Check your API key. An 'Unauthorized' error occurs when making calls: ".$ex->getMessage();
                }
                catch(Axilent_Exception $ex)
                {
                    $widget_errors[] = "Widget: There was a general error for the widget with policy content setting '$policy_content': ".$ex->getMessage();
                }
            }
            
            try
            {
                if(!self::getAxilentClient()->ping()) {
                    $widget_errors[] = "Axilent system is reporting that this instance is configured incorrectly";
                }
            }
            catch(Axilent_MisconfigurationException $ex)
            {
                $widget_errors[] = "Content Posting: Posting content to Axilent via the general configuration on the admin page is broken: ".$ex->getMessage();
            }
            catch(Axilent_UnauthorizedException $ex)
            {
                $widget_errors[] = "Unauthorized: Check your API key. An 'Unauthorized' error occurs when making calls: ".$ex->getMessage();
            }
            catch(Axilent_Exception $ex)
            {
                $widget_errors[] = "Ping: Axilent API returned an error in response to a 'ping'. Settings above: ".$ex->getMessage();
            }
        }

        $data = array (
            'axilent_project_name'      => $axilent_project_name,
            'axilent_api_key'           => $axilent_api_key,
            'axilent_title_field'       => $axilent_title_field,
            'axilent_link_field'        => $axilent_link_field,
            'axilent_content_field'     => $axilent_content_field,
            'axilent_description_field' => $axilent_description_field,
            'axilent_users'             => $users,
            'axilent_sync'              => $axilent_sync,
            'widget_errors'             => $widget_errors
        );

        Axilent_View::load('admin', $data);
    }
    
    /**
     * A callback executed wheeever a post is posted
     * @param int $postId
     */
    public function saveCallback($postId)
    {
        $do_sync = Axilent_Utility::getOption('axilent_sync');
        
        if(get_post_status($postId) != 'publish' || $do_sync == 'no') 
        {
            # No interest
            return;
        }
        
        # Get the options
        $axilent_title_field        = Axilent_Utility::getOption('axilent_title_field');
        $axilent_description_field  = Axilent_Utility::getOption('axilent_description_field');
        $axilent_link_field         = Axilent_Utility::getOption('axilent_link_field');
        $axilent_content_field      = Axilent_Utility::getOption('axilent_content_field');
        
        # Set some defaults for non-existant options
        $axilent_title_field        = $axilent_title_field ? $axilent_title_field : 'title';
        $axilent_description_field  = $axilent_description_field ? $axilent_description_field : 'description';
        $axilent_link_field         = $axilent_link_field ? $axilent_link_field : 'link';
        
        $parents = get_post_ancestors($postId);
        if(count($parents)) $postId = $parents[0];
        
        $post         = get_post($postId);
        $content_key  = get_post_meta($postId, 'axilent_content_key', true);

        if(!$content_key) $content_key = false;
        
        $content = array (
            $axilent_content_field      => $post->post_content,
            $axilent_title_field        => $post->post_title,
            $axilent_link_field         => get_permalink($postId),
            # And snag the first 3 sentences
            $axilent_description_field  => implode('. ', array_slice(explode('. ', $post->post_content),0, 3))
        );

        $content_key = self::getAxilentClient()->postContent($content, $content_key);
        update_post_meta($postId, 'axilent_content_key', $content_key);
    }

    /**
     * The callback used to register the widget
     */
    static function registerWidget()
    {
        register_widget('Axilent_Widget');
    }
}


/**
 * This is an optional widget to display GitHub projects
 */
class Axilent_Widget extends WP_Widget
{
    /**
     * Set the widget options
     */
     function Axilent_Widget()
     {
        $widget_ops = array('classname' => 'axilent_content', 'description' => 'A list of relvant Axilent content');
        $this->WP_Widget('axilent_content', 'Axilent Content', $widget_ops);
     }

     /**
      * Display the widget on the sidebar
      * @param array $args
      * @param array $instance
      */
     function widget($args, $instance)
     {
         global $wp_query;
         extract($args);
         
         $title       = apply_filters('widget_title', $instance['w_title']);
         $num_items   = $instance['w_num_items'];         
         $link_field  = $instance['w_link_field'];         
         $title_field = $instance['w_title_field'];         
         $description_field = $instance['description_field'];         
         $content_key = get_post_meta($wp_query->post->ID, 'axilent_content_key', true);
         $content     = Axilent_Core::getAxilentClient()->getRelevantContent($instance['w_policy_content'], $content_key, $num_items);
         $keys        = array();
         
         echo $before_widget;

         if($title);
            echo $before_title . $title. $after_title;

         echo $w_opener;
         
         if(!isset($content->default) || !$content->default)
         {
             echo "No items to show";
         }
         else
         {
            foreach($content->default as $item) {
                $keys[] = $item->content->key;
            }

            $meta = Axilent_Model::getPostsByMetaValues($keys);

            if(count($content->default) > 0)
            {
                echo "<ul>";
                foreach($content->default as $item)
                {
                    $i_link = isset($item->content->data->{$link_field}) ? $item->content->data->{$link_field} : '#';
                    $i_title = isset($item->content->data->{$title_field}) ? $item->content->data->{$title_field} : 'Set Widget Settings';

                    echo "<li>";
                        echo "<a href=\"". $i_link ."\" \">";
                            echo htmlentities($i_title);
                        echo "</a>";
                    echo "</li>";
                }
                echo "</ul>";
            } else {
                echo "<p><em>No related items to list</em></p>";
            }
         }
         echo $w_closer;

         echo $after_widget;
     }

     /**
      * Update the widget info from the admin panel
      * @param array $new_instance
      * @param array $old_instance
      * @return array
      */
     function update($new_instance, $old_instance)
     {
        $instance = $old_instance;
        
        $instance['w_title']            = $new_instance['w_title'];
        $instance['w_policy_content']   = $new_instance['w_policy_content'];
        $instance['w_num_items']        = $new_instance['w_num_items'];
        $instance['w_link_field']       = $new_instance['w_link_field'];
        $instance['w_title_field']      = $new_instance['w_title_field'];
        $instance['w_description_field']= $new_instance['w_description_field'];

        return $instance;
     }

     /**
      * Display the widget update form
      * @param array $instance
      */
     function form($instance)
     {

        $defaults = array('w_title'             => 'Related Items', 
                          'w_policy_content'    => 'posts',
                          'w_num_items'         => 10,
                          'w_link_field'        => 'link',
                          'w_title_field'       => 'title',
                          'w_description_field' => 'description');
        
		$instance = wp_parse_args((array) $instance, $defaults);
       ?>
        <div class="widget-content">
       <p>
            <label for="<?php echo $this->get_field_id('w_title'); ?>">Title:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('w_title'); ?>" name="<?php echo $this->get_field_name('w_title'); ?>" value="<?php  echo $instance['w_title']; ?>" />
       </p>
       <p>
            <label for="<?php echo $this->get_field_id('w_policy_content'); ?>">Content Channel:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('w_policy_content'); ?>" name="<?php echo $this->get_field_name('w_policy_content'); ?>" value="<?php  echo $instance['w_policy_content']; ?>" />
       </p>
       <p>
            <label for="<?php echo $this->get_field_id('w_num_items'); ?>">Number of items:</label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'w_num_items' ); ?>" name="<?php echo $this->get_field_name('w_num_items'); ?>" value="<?php echo $instance['w_num_items']; ?>" />
       </p>
       <p>
            <label for="<?php echo $this->get_field_id('w_link_field'); ?>">Link field name:</label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'w_link_field' ); ?>" name="<?php echo $this->get_field_name('w_link_field'); ?>" value="<?php echo $instance['w_link_field']; ?>" />
       </p>
       <p>
            <label for="<?php echo $this->get_field_id('w_title_field'); ?>">Title field name:</label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'w_title_field' ); ?>" name="<?php echo $this->get_field_name('w_title_field'); ?>" value="<?php echo $instance['w_title_field']; ?>" />
       </p>
       <p>
            <label for="<?php echo $this->get_field_id('w_description_field'); ?>">Description field name:</label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'w_description_field' ); ?>" name="<?php echo $this->get_field_name('w_description_field'); ?>" value="<?php echo $instance['w_description_field']; ?>" />
       </p>
        </div>
       <?php
     }
}




