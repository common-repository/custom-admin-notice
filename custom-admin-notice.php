<?php
/**
 * Plugin Name: Custom Admin Notice
 * Plugin URI: https://freelancedeveloperkent.co.uk/
 * Description: Display custom admin notices on your WordPress dashboard.
 * Version: 1.1.0
 * Author: Steve North
 * Author URI: https://freelancedeveloperkent.co.uk/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Tested up to: 6.2
 * Donate link: https://buymeacoffee.com/tex0gen
 *
 * @package Custom_Admin_Notice
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Custom_Admin_Notice {

	public $options = '';

	function __construct() {
		$this->options = get_option('custom_admin_notice', array());

		if ( empty($this->options) ) {
			add_option( 'custom_admin_notice', array() );
		}

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_dismiss_custom_admin_notice', array( $this, 'dismiss_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 99 );

		if ( isset( $this->options['enabled'] ) && "1" === $this->options['enabled'] ) {
			add_action('admin_notices', array( $this, 'general_admin_notice' ) );
		}
	}

	function enqueue_admin_scripts() {
		// enqueue jquery
		wp_enqueue_script( 'custom-admin-notice', plugin_dir_url( __FILE__ ) . 'js/custom-admin-notice.js', array( 'jquery' ), '1.0.1', true );
		wp_localize_script( 'custom-admin-notice', 'customAdminNotice', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'dismiss_custom_admin_notice' ),
		) );
	}

	function dismiss_notice() {
		check_ajax_referer( 'dismiss_custom_admin_notice', 'nonce' );

		$user_id = get_current_user_id();
		update_user_meta( $user_id, 'custom_admin_notice_dismissed', $this->options['hash'] );

		wp_send_json_success();
	}

	function general_admin_notice() {
		$user_id = get_current_user_id();
		// Check if the user dismissed the current notice hash
		$dismissed_hash = get_user_meta( $user_id, 'custom_admin_notice_dismissed', true );
		if ( $dismissed_hash === $this->options['hash'] ) {
				return;
		}
		?>
		<div class="notice notice-<?php echo esc_attr($this->options['type']); ?><?php echo ($this->options['dismissable'] === "1") ? ' is-dismissible':''; ?>" data-notice="custom_admin_notice">
				<p><?php echo wp_kses_post($this->options['message']); ?></p>
		</div>
		<?php
	}

	function admin_menu() {
		add_submenu_page( 'options-general.php', 'Admin Notice', 'Admin Notice', 'manage_options', 'custom-admin-notice', array( $this, 'admin_page' ) );
	}

	function admin_page() {
		?>
		<div class="wrap">
			<form method="post" action="options.php">
				<h1>Custom Admin Notice</h1>
				<?php settings_fields( 'custom_admin_notice' ); ?>
				<?php do_settings_sections( 'custom_admin_notice' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	function elements_checkbox_enabled( $args ) {
		$checked = ( "1" === $args['options']['enabled'] ) ? 'checked' : '';
		echo '<input type="checkbox" name="custom_admin_notice[enabled]" value="1" ' . $checked . ' />';
	}
	
	function elements_checkbox_dismissable( $args ) {
		$checked = ( "1" === $args['options']['dismissable'] ) ? 'checked' : '';
		echo '<input type="checkbox" name="custom_admin_notice[dismissable]" value="1" ' . $checked . ' />';
	}
	
	function elements_textarea_message( $args ) {
		echo '<textarea name="custom_admin_notice[message]">' . esc_textarea($args['options']['message']) . '</textarea>';
	}

	function elements_select_type( $args ) {
		$selected_type = isset($args['options']['type']) ? $args['options']['type'] : '';
		$types = array(
			'' => 'None (Grey)',
			'success' => 'Success (Green)',
			'warning' => 'Warning (Orange)',
			'error' => 'Error (Red)'
		);
		echo '<select name="custom_admin_notice[type]">';
		foreach ($types as $value => $label) {
			echo '<option value="' . esc_attr($value) . '"' . selected($selected_type, $value, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
	}

	function sanitize( $input ) {
		$new_input = array();

		$new_input['enabled'] = isset( $input['enabled'] ) ? "1" : "0";
		$new_input['dismissable'] = isset( $input['dismissable'] ) ? "1" : "0";
		$new_input['message'] = isset( $input['message'] ) ? wp_kses_post( $input['message'] ) : '';
		$new_input['type']    = isset( $input['type'] ) ? sanitize_text_field( $input['type'] ) : '';

		// Create a hash of the message
		$new_input['hash'] = md5( $new_input['message'] );

		// If the hash is different from the previous hash, then delete the user meta for all users
		if ( isset( $this->options['hash'] ) && $new_input['hash'] !== $this->options['hash'] ) {
				$users = get_users();
				foreach ( $users as $user ) {
						delete_user_meta( $user->ID, 'custom_admin_notice_dismissed' );
				}
		}

		return $new_input;
	}

	function elements_wysiwyg_message( $args ) {
		wp_editor( $args['options']['message'], 'custom_admin_notice_message', array(
			'textarea_name' => 'custom_admin_notice[message]',
			'media_buttons' => false,  // Set to true if you want to add media buttons
			'drag_drop_upload' => false,
			'teeny' => true,  // Set to true for a minimal editor configuration
			'textarea_rows' => 10,
			'quicktags' => true
		) );
	}

	function register_settings() {
		$options = $this->options;

		add_settings_section(
			'custom_admin_notice_settings_section',
			'Settings',
			null,
			'custom_admin_notice'
		);

		add_settings_field( 
			'enabled',
			'Enabled',
			array( $this, 'elements_checkbox_enabled' ),
			'custom_admin_notice', 
			'custom_admin_notice_settings_section',
			array( 'options' => $options )
		);
		
		add_settings_field( 
			'dismissable',
			'Dismissable',
			array( $this, 'elements_checkbox_dismissable' ),
			'custom_admin_notice', 
			'custom_admin_notice_settings_section',
			array( 'options' => $options )
		);

		add_settings_field( 
			'type',
			'Message Type',
			array( $this, 'elements_select_type' ),
			'custom_admin_notice', 
			'custom_admin_notice_settings_section',
			array( 'options' => $options )
		);

		add_settings_field( 
			'message',
			'Message',
			array( $this, 'elements_wysiwyg_message' ),
			'custom_admin_notice', 
			'custom_admin_notice_settings_section',
			array( 'options' => $options )
		);

		register_setting( 'custom_admin_notice', 'custom_admin_notice', array( $this, 'sanitize' ) );
	}
}

if ( is_admin() ) {
	new Custom_Admin_Notice;
}
