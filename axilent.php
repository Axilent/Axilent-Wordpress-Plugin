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
                    $markup = '<iframe style="width:100%; height: 600px;" src="'.$axilent->getPortletURL($content_key).'"></iframe>';
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
        $axilent_subdomain      = Axilent_Utility::getOption('axilent_subdomain');
        $axilent_api_key        = Axilent_Utility::getOption('axilent_api_key');
        $portlet_key            = get_user_meta(get_current_user_id(), 'axilent_portlet_key', true);

        self::$_axilent = new Axilent($axilent_subdomain, $axilent_project_name, $axilent_api_key, $portlet_key);
        
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
            
            Axilent_Utility::setOption('axilent_subdomain', $_POST['axilent_subdomain']);
            Axilent_Utility::setOption('axilent_project_name', $_POST['axilent_project_name']);
            Axilent_Utility::setOption('axilent_api_key', $_POST['axilent_api_key']);
        }
        
        # Attach the API key to each user object
        $users = get_users(array('who' => 'author'));
        for($i = 0; $i < count($users); $i++) {
            $users[$i]->portlet_key = get_user_meta($users[$i]->ID, 'axilent_portlet_key', true);
        }
        
        $axilent_project_name   = Axilent_Utility::getOption('axilent_project_name');
        $axilent_subdomain      = Axilent_Utility::getOption('axilent_subdomain');
        $axilent_api_key        = Axilent_Utility::getOption('axilent_api_key');

        $data = array (
            'axilent_project_name'  => $axilent_project_name,
            'axilent_subdomain'     => $axilent_subdomain,
            'axilent_api_key'       => $axilent_api_key,
            'axilent_users'         => $users
        );

        Axilent_View::load('admin', $data);
    }
    
    /**
     * A callback executed wheeever a post is posted
     * @param int $postId
     */
    public function saveCallback($postId)
    {
        if(get_post_status($postId) != 'publish') 
        {
            # No interest
            return;
        }
        
        $parents = get_post_ancestors($postId);
        if(count($parents)) $postId = $parents[0];
        
        $post         = get_post($postId);
        $content_key  = get_post_meta($postId, 'axilent_content_key', true);

        if(!$content_key) $content_key = false;
        
        $content = array (
            'Content' => $post->post_content,
            'Title'   => $post->post_title
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
         $num_items = $instance['w_num_items'];         
         $content_key = get_post_meta($wp_query->post->ID, 'axilent_content_key', true);
         $content     = Axilent_Core::getAxilentClient()->getRelevantContent($instance['w_policy_content'], $content_key, $num_items);
         $keys        = array();
         
         echo $before_widget;

         if($title);
            echo $before_title . $title. $after_title;

         echo $w_opener;
         
         foreach($content->default as $item) {
             $keys[] = $item->content->key;
         }
         
         $meta = Axilent_Model::getPostsByMetaValues($keys);

         if(count($content->default) > 0)
         {
             echo "<ul>";
             foreach($content->default as $item)
             {
                echo "<li>";
                    echo "<a href=\"". get_permalink($meta[$item->content->key]) ."\" \">";
                           echo htmlentities($item->content->data->Title);
                    echo "</a>";
                echo "</li>";
             }
             echo "</ul>";
         } else {
             echo "<p><em>No related items to list</em></p>";
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
        
        $instance['w_title']       = $new_instance['w_title'];
        $instance['w_policy_content']= $new_instance['w_policy_content'];
        $instance['w_num_items']   = $new_instance['w_num_items'];

        return $instance;
     }

     /**
      * Display the widget update form
      * @param array $instance
      */
     function form($instance)
     {

        $defaults = array('w_title' => 'Related Items', 'w_num_items' => 10);
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
        </div>
       <?php
     }
}




