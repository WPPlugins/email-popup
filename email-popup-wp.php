<?php
/*
    Plugin Name: Email Popup by Optin Cat
    Plugin URI: https://fatcatapps.com/optincat
    Description: Email Popup By Optin Cat WordPress Lets You Convert More Blog Viisots Into Email Subscribers. Set It Up In 2 Minutes.
    Author: Fatcat Apps
    Version: 1.5.4
    Author URI: https://fatcatapps.com/
*/

// define( 'FCA_EOI_DEBUG', true );

if ( ! function_exists( 'is_admin' ) ) {
    exit();
}

require 'includes/skelet/skelet.php';

define( 'FCA_EOI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FCA_EOI_PLUGIN_FILE', __FILE__ );
define( 'FCA_EOI_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'FCA_EOI_EDITION', 'email_popup' );


if( ! defined ( 'FCA_EOI_DEBUG' ) ) {
    define( 'FCA_EOI_DEBUG', false );
}
if ( ! class_exists ( 'Mustache_Engine' ) ) {
    require FCA_EOI_PLUGIN_DIR . 'includes/classes/Mustache/Autoloader.php';
    Mustache_Autoloader::register();
}

/**
 * Include scssphp
 * 
 * Latest version requires PHP 5.3
 * Version 0.0.15 works with PHP 5.2
 * We detect PHP > 5.3 by checking the constant __DIR__
 */
if ( ! class_exists ( 'scssc' ) ) {
    if( defined( '__DIR__' ) ) {
        require FCA_EOI_PLUGIN_DIR . 'includes/classes/scssphp/scss.inc.php';
    } else {
        require FCA_EOI_PLUGIN_DIR . 'includes/classes/scssphp-0.0.15/scss.inc.php';
    }
}

/**
 * Include and instantiate Mobile Detect
 */
if ( ! class_exists ( 'Mobile_Detect' ) ) {
    require FCA_EOI_PLUGIN_DIR . 'includes/classes/Mobile-Detect/Mobile_Detect.php';
}

if( ! class_exists( 'DhEasyOptIns' ) ) {
class DhEasyOptIns {

    var $ver = '1.5.4';
    var $distro = '';
    var $shortcode = 'optin-cat';
    var $shortcode_aliases = array(
        'easy-opt-in',
        'optincat',
        'opt-in-cat',
    );
    var $settings;
    var $provider = '';
    var $providers = array();

    function __construct() {

        require FCA_EOI_PLUGIN_DIR . 'includes/eoi-post-types.php';
        require FCA_EOI_PLUGIN_DIR . 'includes/eoi-layout.php';
        require FCA_EOI_PLUGIN_DIR . 'includes/eoi-shortcode.php';
        require FCA_EOI_PLUGIN_DIR . 'includes/eoi-widget.php';
        require FCA_EOI_PLUGIN_DIR . 'includes/eoi-pointer.php';
        require FCA_EOI_PLUGIN_DIR . 'includes/eoi-tour-pointer.php';
        require FCA_EOI_PLUGIN_DIR . 'includes/eoi-activity.php';
		require FCA_EOI_PLUGIN_DIR . 'includes/eoi-functions.php';

        global $fca_eoi_shortcodes;
	

        // Settings
        $this->settings['shortcode']  = $this->shortcode;
        $this->settings['shortcode_aliases']  = $this->shortcode_aliases;
        $this->settings['version']    = $this->ver;
        $this->settings['provider']   = $this->provider;

        // Load all providers
        foreach ( glob( FCA_EOI_PLUGIN_DIR . 'providers/*', GLOB_ONLYDIR ) as $provider_path ) {  
            $provider_id = basename(  $provider_path );
            require_once "$provider_path/provider.php";
            $this->settings[ 'providers' ][ $provider_id ] = call_user_func( "provider_$provider_id" );
        }

        if ( $_SERVER['REQUEST_METHOD'] == 'POST' && ! empty( $_POST['paf_submit'] ) ) {
            $options_before = get_option('paf');
            $options_after = empty( $_POST['paf'] ) ? array() : $_POST['paf'];
            $options_changed = true;
        } else {
            $options_changed = false;
        }

        // Load all powerups
        foreach ( glob( FCA_EOI_PLUGIN_DIR . 'powerups/*', GLOB_ONLYDIR ) as $powerup_path ) {  
            $powerup_id = basename( $powerup_path );            
            $powerup_id = preg_replace('/(\d+)_/', '', $powerup_id);

            require_once "$powerup_path/powerup.php";
            $function_name = "powerup_$powerup_id";
            $this->settings[ 'powerups' ][ $powerup_id ] = call_user_func( $function_name, $this->settings );

            $option_name = 'eoi_' . $function_name;
            if ( $options_changed ) {
                $hook_function_name = null;

                if ( empty( $options_before[ $option_name ] ) && ! empty( $options_after[ $option_name ] ) ) {
                    $hook_function_name = $function_name . '_on_activate';
                } else if ( ! empty( $options_before[ $option_name ] ) && empty( $options_after[ $option_name ] ) ) {
                    $hook_function_name = $function_name . '_on_deactivate';
                }

                if ( ! empty( $hook_function_name ) && function_exists( $hook_function_name ) ) {
                    call_user_func( $hook_function_name, $this->settings );
                }
            }
        }

        paf_pages( array( 'eoi_powerups' => array(
            'title' => __( 'Power Ups Settings' ),
            'menu_title' => __( 'Power Ups' ),
            'parent' => 'edit.php?post_type=easy-opt-ins',
        ) ) );

        // Add provider to settings
        $providers_available = array_keys( $this->settings[ 'providers' ] );
        
        //set current post type to setting array
        $this->settings[ 'post_type' ] = $post_type;

        // If there is only one provider, use it
        if( 1 == count( $providers_available ) ) {
            $this->provider = $this->settings[ 'provider' ] = $providers_available[ 0 ];
        }

        // Distributions
        if( 1 == count( $providers_available ) ) {
            $this->distro = 'free';
            $this->settings['distribution'] = 'free';
        } else {
            $this->distro = 'premium';
            $this->settings['distribution'] = 'premium';
        }

        // Add options that are stored in DB if any
        $this->settings[ 'eoi_settings' ] = $eoi_settings;

        // Include provider helper class(es)
        foreach ( $providers_available as $provider ) {
            require_once FCA_EOI_PLUGIN_DIR . "providers/$provider/functions.php";
        }

        // Load extensions
        $post_types = new EasyOptInsPostTypes($this->settings);
        $fca_eoi_shortcodes = new EasyOptInsShortcodes($this->settings);
        $widget = new EasyOptInsWidgetHelper($this->settings);
        EasyOptInsActivity::get_instance()->settings = $this->settings;

        // Load subscribing banner for free users
        if( 1 == count( $providers_available ) ) {
            $pointer = new EasyOptInsPointer( $this->settings );
        }
        
        
        //load EasyOptIns Upgrade notifications
        if( count( $providers_available ) === 1 ) {
            require plugin_dir_path( __FILE__ ) . 'includes/eoi-upgrade.php';
            new EasyOptInsUpgrade( $this->settings );
        }
    }

    function get_current_post_type() {

        global $post, $typenow, $current_screen;

        if ( $post && $post->post_type ) {
            return $post->post_type;
        } elseif( $typenow ) {
            return $typenow;
        } elseif( $current_screen && $current_screen->post_type ) {
            return $current_screen->post_type;
        } elseif( isset( $_REQUEST['post_type'] ) ) {
            return sanitize_key( $_REQUEST['post_type'] );
        } elseif ( isset( $_REQUEST['post'] ) && $_REQUEST['post'] ) {
            $id =  $_REQUEST['post'];
            $post_obj = get_post( $id );
            if( $post_obj ) {
                return $post_obj->post_type;
            }
        }
        return null;
    }


}
}

require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

if ( ! is_plugin_active( plugin_basename( __FILE__ ) ) ) {
    function fca_eoi_fail_activation( $message ) {
        wp_die( sprintf(
            '<h2>%s</h2><p>%s</p><p><a class="button button-large" href="%s">%s</a></p>'
            , __( 'Ooops!' )
            , __( $message )
            , admin_url( 'plugins.php' )
            , __( 'Go to plugins page' )
        ) );
    }

    function fca_eoi_activation() {
        $plugins = get_plugins();
		
        // Fail to activate the plugin if other Optin Cat plugins are already active
        foreach ( $plugins as $file => $plugin ) {
            if ( stripos( $plugin['PluginURI'], 'fatcatapps.com/optincat' ) !== false && is_plugin_active( $file ) ) {
                $current_plugin = $plugins[ plugin_basename( __FILE__ ) ];
                fca_eoi_fail_activation(
                    'Only one Optin Cat plugin can be active at a time, but you already have ' .
                    htmlspecialchars( $plugin['Name'] ) . ' active. ' .
                    'Please deactivate it before activating ' .
                    htmlspecialchars( $current_plugin['Name'] ) . '.' );
            }
        }

        // Fail to activate the plugin if the providers or layouts directories are empty
        $providers  = glob( FCA_EOI_PLUGIN_DIR . 'providers/*', GLOB_ONLYDIR );
        $layouts    = glob( FCA_EOI_PLUGIN_DIR . 'layouts/*', GLOB_ONLYDIR );

        if ( empty( $providers ) || empty( $layouts ) ) {
            fca_eoi_fail_activation( 'Something went wrong. Please delete the plugin and install it again.' );
        }
		
		require FCA_EOI_PLUGIN_DIR . 'includes/eoi-functions.php';
				
		//convert everyone to new CSS if they are on OLD
		fca_eoi_migrate_css();
				
		//pre-compile SCSS
		fca_eoi_compile_scss();
			
        // If everything went well, continue with the activation setup
        require FCA_EOI_PLUGIN_DIR . 'includes/eoi-activity.php';
        EasyOptInsActivity::get_instance()->setup();

		
    }

    // If the plugin is not yet active, check for any obstacles in activation
    register_activation_hook( __FILE__, 'fca_eoi_activation' );
    return;
}

$dh_easy_opt_ins_plugin = new DhEasyOptIns();
