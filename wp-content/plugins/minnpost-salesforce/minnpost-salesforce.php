<?php
/*
Plugin Name: MinnPost Salesforce
Plugin URI: 
Description: 
Version: 0.0.1
Author: Jonathan Stegall
Author URI: http://code.minnpost.com
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: minnpost-salesforce
*/

// Start up the plugin
class Minnpost_Salesforce {

	/**
	* @var string
	*/
	private $version;

	/**
	* @var object
	*/
	public $salesforce;

	/**
	 * This is our constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->version = '0.0.1';
		$this->admin_init();
		$this->init();
	}

	/**
	* admin start
	*
	* @throws \Exception
	*/
	private function admin_init() {
		add_action( 'admin_init', array( &$this, 'salesforce' ) );
		add_action( 'admin_init', array( &$this, 'minnpost_salesforce_settings_forms' ) );
	}

	/**
	* start
	*
	* @throws \Exception
	*/
    private function init() {
    	add_filter( 'salesforce_rest_api_find_object_match', array( &$this, 'find_object_match' ), 10, 3 );
    	add_filter( 'salesforce_rest_api_push_object_allowed', array( &$this, 'push_not_allowed' ), 10, 5 );
    	add_filter( 'salesforce_rest_api_settings_tabs', array( &$this, 'minnpost_tabs'), 10, 1 );
    }

    /**
	* Load the Salesforce object
	* Also make it available to this whole class
	*
	* @return $this->salesforce
	*
	*/
    public function salesforce() {
		// get the base class
		if ( ! function_exists( 'is_plugin_active' ) ) {
     		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
     	}
		if ( is_plugin_active('salesforce-rest-api/salesforce-rest-api.php') ) {
			require_once plugin_dir_path( __FILE__ ) . '../salesforce-rest-api/salesforce-rest-api.php';
			$salesforce = Salesforce_Rest_API::get_instance();
			$this->salesforce = $salesforce;
			return $this->salesforce;
		}
	}

	/**
    * Create default WordPress admin settings form for MinnPost-specific salesforce things
    * This is for the Settings page/tab
    *
    */
    public function minnpost_salesforce_settings_forms() {
        $page = isset( $_GET['tab'] ) ? $_GET['tab'] : 'settings';
        $section = isset( $_GET['tab'] ) ? $_GET['tab'] : 'settings';
        $input_callback_default = array( $this, 'display_input_field' );
        $input_checkboxes_default = array( &$this, 'display_checkboxes' );
        $this->fields_minnpost_settings( 'minnpost', 'minnpost', array( 'text' => $input_callback_default, 'checkboxes' => $input_checkboxes_default ) );
    }

    /**
    * Fields for the Log Settings tab
    * This runs add_settings_section once, as well as add_settings_field and register_setting methods for each option
    *
    * @param string $page
    * @param string $section
    * @param array $callbacks
    */
    private function fields_minnpost_settings( $page, $section, $callbacks ) {
        add_settings_section( $page, ucwords( str_replace('_', ' ', $page) ), null, $page );
        $minnpost_salesforce_settings = array(
        );
        foreach ( $minnpost_salesforce_settings as $key => $attributes ) {
            $id = 'salesforce_api_' . $key;
            $name = 'salesforce_api_' . $key;
            $title = $attributes['title'];
            $callback = $attributes['callback'];
            $page = $attributes['page'];
            $section = $attributes['section'];
            $args = array_merge(
                $attributes['args'],
                array(
                    'title' => $title,
                    'id' => $id,
                    'label_for' => $id,
                    'name' => $name
                )
            );
            add_settings_field( $id, $title, $callback, $page, $section, $args );
            register_setting( $section, $id );
        }
    }

   function minnpost_tabs( $tabs ) {
		$tabs['minnpost'] = 'MinnPost';
		return $tabs;
	}

    public function push_not_allowed( $push_allowed, $object_type, $object, $sf_sync_trigger, $mapping ) {
    	if ( $object_type === 'user' && $object['ID'] === 1 ) { // do not add user 1 to salesforce
			return FALSE;
		}
    }

    /**
	* Find an object match between a WordPress object and a Salesforce object
	* This is designed to find out if there is already a map based on the available WordPress data
	*
	* @param string $salesforce_id
	*	Unique identifier for the Salesforce object
	* @param array $wordpress_object
	*	Array of the wordpress object's data
    * @param array $mapping
    *   Array of the fieldmap between the WordPress and Salesforce object types
	*
	* @return array $salesforce_id
	*	Unique identifier for the Salesforce object
	*
	* todo: may need a way for this to prevent a deletion in Salesforce if multiple contacts match the email address, for example. the plugin itself will block it if there are existing map rows. we might need to expand it for this, or maybe it is sufficient as it is. mp would probably turn off the delete hooks anyway.
	*
	*/
	public function find_object_match( $salesforce_id, $wordpress_object, $mapping = array() ) {

        if ( $mapping['wordpress_object'] === 'user' ) {
    		if ( is_object( $this->salesforce ) ) {
    			$salesforce_api = $this->salesforce->salesforce['sfapi'];
    		} else {
    			$salesforce = $this->salesforce();
    			$salesforce_api = $salesforce->salesforce['sfapi'];
    		}
    		
    		if ( is_object( $salesforce_api ) ) {

    			// we want to see if the user's email address exists as a primary on any contact and use that contact if so
    			$mail = $wordpress_object['user_email'];
    			$query = "SELECT Primary_Contact__c FROM Email__c WHERE Email_Address__c = '$mail'";
    			$result = $salesforce_api->query( $query );

    			if ( $result['data']['totalSize'] === 1 ) {
    				$salesforce_id = $result['data']['records'][0]['Primary_Contact__c'];
    			} elseif ( $result['data']['totalSize'] > 1 ) {
    				error_log('Salesforce has ' . $result['data']['totalSize'] . ' matches for this email. Try to log all of them: ' . print_r($result['data']['records'], true));
    			}
    		} else {
    			error_log('object for sf api does not exist');
    		}
        }

		return $salesforce_id;
	}

	/**
    * Default display for <input> fields
    *
    * @param array $args
    */
    public function display_input_field( $args ) {
        $type   = $args['type'];
        $id     = $args['label_for'];
        $name   = $args['name'];
        $desc   = $args['desc'];
        $checked = '';

        $class = 'regular-text';

        if ( $type === 'checkbox' ) {
            $class = 'checkbox';
        }

        if ( !defined( $args['constant'] ) ) {
            $value  = esc_attr( get_option( $id, '' ) );
            if ( $type === 'checkbox' ) {
                if ( $value === '1' ) {
                    $checked = 'checked ';
                }
                $value = 1;
            }
            if ( $value === '' && isset( $args['default'] ) && $args['default'] !== '' ) {
                $value = $args['default'];
            }
            echo '<input type="' . $type. '" value="' . $value . '" name="' . $name . '" id="' . $id . '"
            class="' . $class . ' code" ' . $checked . ' />';
            if ( $desc != '' ) {
                echo '<p class="description">' . $desc . '</p>';
            }
        } else {
            echo '<p><code>Defined in wp-config.php</code></p>';
        }
    }

    /**
    * Display for multiple checkboxes
    * Above method can handle a single checkbox as it is
    *
    * @param array $args
    */
    public function display_checkboxes( $args ) {
        $type = 'checkbox';
        $name = $args['name'];
        $options = get_option( $name );
        foreach ( $args['items'] as $key => $value ) {
            $text = $value['text'];
            $id = $value['id'];
            $desc = $value['desc'];
            $checked = '';
            if (is_array( $options ) && in_array( $key, $options ) ) {
                $checked = 'checked';
            }
            echo '<div><label><input type="' . $type. '" value="' . $key . '" name="' . $name . '[]" id="' . $id . '" ' . $checked . ' />' . $text . '</label></div>';
            if ( $desc != '' ) {
                echo '<p class="description">' . $desc . '</p>';
            }
        }
    }


/// end class
}
// Instantiate our class
$Minnpost_Salesforce = new Minnpost_Salesforce();