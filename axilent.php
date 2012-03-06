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

add_action('admin_menu',   array('Axilent_Core', 'registerAdmin'));
add_action('widgets_init', array('Axilent_Core', 'registerWidget'));
add_action('add_meta_boxes', array('Axilent_Core', 'addMetaBoxes'));

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
    function axilentAPIBox( $post ) {
        // Use nonce for verification
        wp_nonce_field(plugin_basename(__FILE__), 'axilent_noncename');

        // The actual fields for data entry
        echo '<iframe style="width:100%; height: 300px;" src="http://wpdev.axilent.net/airtower/portlets/content/?key=5ac5c760d99449c5852e66ad8b221119&content_type=Whiskey"></frame>';
    }
    
    function getPortletURL() {
        
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
        $submit  = (bool)$users;
        $updated = FALSE;

        if($submit)
        {
            foreach($users as $id => $value) 
            {
                $value = trim($value);
                if($value) {
                    update_user_meta($id, 'axilent_user_key', $value);
                }
            }
        }
        
        # Attach the API key to each user object
        $users = get_users(array('who' => 'author'));
        for($i = 0; $i < count($users); $i++) {
            $users[$i]->axilent_key = get_user_meta($users[$i]->ID, 'axilent_user_key', true);
        }

        $data = array (
           # 'axilent_opener'   => self::getOpeningListTemplate(),
           # 'axilent_closer'   => self::getClosingListTemplate(),
           # 'axilent_template' => self::getProjectTemplate(),
           # 'axilent_updated'  => $updated
            'axilent_users' => $users
        );

        Axilent_View::load('admin', $data);
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
         extract($args);
         $title       = apply_filters('widget_title', $instance['w_title']);
         $info_string = $instance['w_num_items'];

         echo $before_widget;

         if($title);
            echo $before_title . $title. $after_title;

         echo $w_opener;
         
         #$projects = WPGH_Project::fetch($info_string);

         if(count($projects) > 0)
         {
             echo "<ul>";
             foreach($projects as $project)
             {
                $noun = $project->watchers == 1 ? 'watcher' : 'watchers';
                echo "<li>";
                    echo "<a target=\"_blank\" href=\"{$project->url}\" title=\"{$project->description} &mdash; {$project->watchers} $noun \">";
                            echo $project->name;
                    echo "</a>";
                echo "</li>";
             }
             echo "</ul>";
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
        $instance['w_num_items'] = $new_instance['w_num_items'];

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
            <label for="<?php echo $this->get_field_id('w_title'); ?>">Box title:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('w_title'); ?>" name="<?php echo $this->get_field_name('w_title'); ?>" value="<?php  echo $instance['w_title']; ?>" />
       </p>
       <p>
            <label for="<?php echo $this->get_field_id('w_num_items'); ?>">Number of items:</label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'w_num_items' ); ?>" name="<?php echo $this->get_field_name('w_num_items'); ?>" value="<?php echo $instance['w_num_items']; ?>" />
       </p>
        </div>
       <?php
     }
}




