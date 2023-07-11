<?php
/**
 * Plugin Name: Online Users
 * Plugin Title: Online Users Plugin
 * Description: WordPress Online Online Users plugin enables you to display how many users are currently online active and display user last seen on your Users page in the WordPress admin.
 * Tags: wp-online-users, users, active-users, online-user, available-users, user-last-seen, currently-active-user, user-online, online-user-status, online users, active users, wordpress online user, wp online users, wordPress users
 * Version: 1.5
 * Author: Suparjo Zheng
 * Author URI: http://rajait.com/
 * Contributors: SZheng
 * License:  GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-online-users
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'rajait_active_user' ) ) {
    class rajait_active_user {
        public function __construct(){
           
            register_activation_hook( __FILE__, array($this, 'rajait_users_status_init' ));
            add_action('init', array($this, 'rajait_users_status_init'));
            add_action('wp_loaded', array($this,'rajait_enqueue_script'));
            add_action('admin_enqueue_scripts', array($this,'raja_enqueue_custom_scripts'));
            add_action('admin_init', array($this, 'rajait_users_status_init'));
            add_action('wp_dashboard_setup', array($this, 'rajait_active_users_metabox'));
            add_filter('manage_users_columns', array($this, 'rajait_user_columns_head'));
            add_action('manage_users_custom_column', array($this, 'rajait_user_columns_content'), 10, 10);
            add_filter('views_users', array($this, 'rajait_modify_user_view' ));
            add_action('admin_bar_menu',  array($this, 'rajait_admin_bar_link'),999);
            add_filter('plugin_row_meta', array($this, 'rajait_support_and_faq_links'), 10, 4 );
            add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'rajait_plugin_by_link'), 10, 2 );
            add_action('admin_notices', array($this,'rajait_display_notice'));
            register_deactivation_hook( __FILE__, array($this,'rajait_display_notice' ));
            register_deactivation_hook( __FILE__, array($this,'rajait_delete_transient' ));
            
            $this->rajait_active_user_shortcode();
        }    

        public function rajait_enqueue_script() {
           wp_enqueue_style( 'style-css', plugin_dir_url( __FILE__ ) . 'assets/css/style.css' );
        }

        public function raja_enqueue_custom_scripts() {
            wp_enqueue_script('raja-plugin-script', plugin_dir_url(__FILE__) . 'assets/js/custom.js', array('jquery'), rand(1,9999), true);
        }


        //Update user online status
        public function rajait_users_status_init(){
            $logged_in_users = get_transient('users_status');
            $user = wp_get_current_user();
            
            if ( !isset($logged_in_users[$user->ID]['last']) || $logged_in_users[$user->ID]['last'] <= time()-50 ){
                $logged_in_users[$user->ID] = array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'last' => time(),
                );
                set_transient('users_status', $logged_in_users, 50);
            }
        }

        //Check if a user has been online in the last 5 minutes
        public function rajait_is_user_online($id){  
            $logged_in_users = get_transient('users_status');
            return isset($logged_in_users[$id]['last']) && $logged_in_users[$id]['last'] > time()-50;
        }

        //Check when a user was last online.
        public function rajait_user_last_online($id){
            $logged_in_users = get_transient('users_status');
            if ( isset($logged_in_users[$id]['last']) ){
                return $logged_in_users[$id]['last'];
            } else {
                return false;
            }
        }

        //Add columns to user listings
        public function rajait_user_columns_head($defaults){
            $defaults['status'] = 'User Online Status';
            return $defaults;
        }

        //Display Status in Users Page 
        public function rajait_user_columns_content($value='', $column_name, $id){
            if ( $column_name == 'status' ){
                if ( $this->rajait_is_user_online($id) ){
                    return '<span class="online-logged-in">●</span>';
                } else if($this->rajait_user_last_online($id)){
                    return ( $this->rajait_user_last_online($id) ) ? ' <span class="offline-dot">●</span><br /><small>Last Seen: <br /><em>' . date('M j, Y @ g:ia', $this->rajait_user_last_online($id)) . '</em></small>' : '';
                }else{
                    return '<span class="offline-dot">●</span>';
                }
            }
        }


        //Online Users Metabox
        public function rajait_active_users_metabox(){
            global $wp_meta_boxes;
            wp_add_dashboard_widget('rajait_active_users', 'Online Users', array($this, 'rajait_active_user_dashboard'));
        }

        public function rajait_active_user_dashboard( $post, $callback_args ){
            $user_count = count_users();
            $users_plural = ( $user_count['total_users'] == 1 )? 'User' : 'Users';
            echo '<div><a href="users.php">' . $user_count['total_users'] . ' ' . $users_plural . '</a> <small>(' . $this->rajait_online_users('count') . ' currently online)</small>
                        <br />
                        </div>';
        }

        //Online User Shortcode
        public function rajait_active_user_shortcode(){
            add_shortcode('rajait_active_user', array($this, 'rajait_active_user'));
        }  

        public function rajait_active_user(){
            ob_start();
            if(is_user_logged_in()){
                $user_count = count_users();
                $users_plural = ( $user_count['total_users'] == 1 ) ? 'User' : 'Users';
                echo '<div class="raja-active-users"> Currently Online Users: <small>(' . $this->rajait_online_users('count') . ')</small></div>';
            }  
            return ob_get_clean();
        }    

        //Display Online User in Admin Bar 
        public function rajait_admin_bar_link() {
            global $wp_admin_bar;
            if ( !is_super_admin() || !is_admin_bar_showing() )
                return;
            $wp_admin_bar->add_menu( array(
                'id' => 'raja_user_link', 
                'title' => '<span class="ab-icon online-logged-in">●</span><span class="ab-label">' . __( 'Online Users (' . $this->rajait_online_users('count') . ')') .'</span>',
                'href' => esc_url( admin_url( 'users.php' ) )
            ) );
        }

        //Get a count of online users, or an array of online user IDs
        public function rajait_online_users($return='count'){
            $logged_in_users = get_transient('users_status');
            
            //If no users are online
            if ( empty($logged_in_users) ){
                return ( $return == 'count' )? 0 : false;
            }
            
            $user_online_count = 0;
            $online_users = array();
            foreach ( $logged_in_users as $user ){
                if ( !empty($user['username']) && isset($user['last']) && $user['last'] > time()-50 ){ 
                    $online_users[] = $user;
                    $user_online_count++;
                }
            }

            return ( $return == 'count' )? $user_online_count : $online_users; 

        }

        public function rajait_modify_user_view( $views ) {

            $logged_in_users = get_transient('users_status');
            $user = wp_get_current_user();

            $logged_in_users[$user->ID] = array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'last' => time(),
                );

            $view = '<a href=' . admin_url('users.php') . '>User Online <span class="count">('.$this->rajait_online_users('count').')</span></a>';

            $views['status'] = $view;
            return $views;
        }

        public function rajait_support_and_faq_links( $links_array, $plugin_file_name, $plugin_data, $status ) {
            if ( strpos( $plugin_file_name, basename(__FILE__) ) ) {

                // You can still use `array_unshift()` to add links at the beginning.
                $links_array[] = '<a href="https://rajait.com" target="_blank">RajaIT.com</a>';
                $links_array[] = '<strong><a href="https://rajait.com/donation" target="_blank">Donate »</a></strong>';
            }
            return $links_array;
        }

        public function rajait_plugin_by_link( $links ){
            $url = 'https://rajait.com/';
            $_link = '<a href="'.$url.'" target="_blank">' . __( 'By <span style="font-weight: bold;">Raja IT Indonesia</span>', 'wp-online-users' ) . '</a>';
            $links[] = $_link;
            return $links;
        }

        public function rajait_display_notice() {
            echo '<div class="notice notice-success is-dismissible wp-online-users-notice" id="wp-online-users-notice">';
            echo '<p>Enjoying our Wp online users plugin? Please support with a small donation <a href="https://rajait.com/donation" target="_blank">here</a>. We would greatly appreciate it!</p>';
            echo '</div>';
        }

        public function rajait_delete_transient() {
            delete_transient( 'users_status' );
        }

    }
}
$myPlugin = new rajait_active_user();