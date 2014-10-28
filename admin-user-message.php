<?php
/**
 * Plugin Name: Admin User Message
 * Description: Add message to users of wp-admin. Choose wheter they can dismiss it or not.
 * Version: 0.0.1
 * Author: Jonathan Bardo
 * License: GPLv2+
 * Text Domain: admin-user-message
 * Domain Path: /languages
 * Author URI: http://jonathanbardo.com
 */

class Admin_User_Message {
	/**
	 * Contain the called class name
	 *
	 * @var string
	 */
	protected static $class;

	/**
	 * Contains settings page name
	 */
	const PAGE_NAME = 'admin-user-message';

	/**
	 * Contain all the settings prefix
	 */
	const SETTINGS_PREFIX = 'admin_user_message_';

	/**
	 * Plugin constructor. Add actions and filters
	 */
	public static function setup() {
		// I heard you like to extend plugins?
		static::$class = get_called_class();

		add_action( 'admin_init', array( static::$class, 'register_settings_fields' ) );
		add_action( 'admin_menu', array( static::$class, 'register_settings_page' ) );

		if ( ! empty( get_option( self::SETTINGS_PREFIX . 'active' ) ) ) {
			add_action( 'admin_notices',                      array( static::$class, 'add_admin_notices' ) );
			add_action( 'wp_ajax_admin_user_message_dismiss', array( static::$class, 'dismiss_message' ) );
			add_action( 'wp_logout',                          array( static::$class, 'remove_dismissal' ) );
		}
	}

	/**
	 * Register plugin settings fields
	 */
	public static function register_settings_fields() {
		add_settings_section( 'default', '', '', self::PAGE_NAME );

		$id = self::SETTINGS_PREFIX . 'active';
		add_settings_field(
			$id,
			__( 'Activate message', 'admin-user-message' ),
			array( static::$class, 'checkbox_render' ),
			self::PAGE_NAME,
			'default',
			array(
				'content' => get_option( $id, '' ),
				'id' => $id,
			)
		);
		register_setting( self::PAGE_NAME, $id, 'sanitize_text_field' );

		$id = self::SETTINGS_PREFIX . 'type';
		add_settings_field(
			$id,
			__( 'Message type', 'admin-user-message' ),
			array( static::$class, 'select_render' ),
			self::PAGE_NAME,
			'default',
			array(
				'content' => get_option( $id, '' ),
				'id' => $id,
			)
		);

		register_setting( self::PAGE_NAME, $id, 'sanitize_text_field' );

		$id = self::SETTINGS_PREFIX . 'content';
		add_settings_field(
			$id,
			__( 'Message content', 'admin-user-message' ),
			array( static::$class, 'wysiwyg_render' ),
			self::PAGE_NAME,
			'default',
			array(
				'content' => get_option( $id, '' ),
				'id' => $id,
			)
		);

		register_setting( self::PAGE_NAME, $id, 'wp_kses_post' );

		$id = self::SETTINGS_PREFIX . 'exclude';
		add_settings_field(
			$id,
			__( 'Exclude message for roles', 'admin-user-message' ),
			array( static::$class, 'multi_select_render' ),
			self::PAGE_NAME,
			'default',
			array(
				'content' => get_option( $id, '' ),
				'id' => $id,
			)
		);

		register_setting( self::PAGE_NAME, $id, array( self::$class, 'sanitize_multi_select' ) );

		$id = self::SETTINGS_PREFIX . 'dismiss';
		add_settings_field(
			$id,
			__( 'Allow message to be dismiss', 'admin-user-message' ),
			array( static::$class, 'checkbox_render' ),
			self::PAGE_NAME,
			'default',
			array(
				'content' => get_option( $id, '' ),
				'id' => $id,
			)
		);

		register_setting( self::PAGE_NAME, $id, 'sanitize_text_field' );

		$id = self::SETTINGS_PREFIX . 'reset';
		add_settings_field(
			$id,
			__( 'Force message display on page reload', 'admin-user-message' ),
			array( static::$class, 'checkbox_render' ),
			self::PAGE_NAME,
			'default',
			array(
				'content' => get_option( $id, '' ),
				'id' => $id,
			)
		);

		register_setting( self::PAGE_NAME, $id, array( self::$class, 'increment_msg_id' ) );
	}

	/**
	 * Render text area settings field
	 *
	 * @param $args
	 */
	public static function wysiwyg_render( $args ) {
		printf(
			wp_editor(
				$args['content'],
				$args['id'],
				array(
					'media_buttons' => false,
					'textarea_rows' => 4,
					'teeny' => true,
				)
			)
		);
	}

	/**
	 * Render a checkbox settings
	 *
	 * @param $args
	 */
	public static function checkbox_render( $args ) {
		printf( '<input name="%s" type="checkbox" %s>', $args['id'], checked( 'on', $args['content'], false ) );
	}

	/**
	 * Render a select
	 *
	 * @param $args
	 */
	public static function select_render( $args ) {
		$types = array(
			'updated'     => esc_html__( 'Informative', 'admin-user-message' ),
			'error'       => esc_html__( 'Important', 'admin-user-message' ),
			'update-nag'  => esc_html__( 'Update', 'admin-user-message' ),
		);

		printf( '<select name="%s">', $args['id'] );

		foreach ( $types as $key => $choice ) {
			printf( '<option value="%s" %s>%s</option>', $key, selected( $key, $args['content'], false ), $choice );
		}

		echo '<select>';
	}

	/**
	 * Render a multiselect
	 *
	 * @param $args
	 */
	public static function multi_select_render( $args ) {
		global $wp_roles;

		printf( '<select name="%s[]" multiple>', $args['id'] );

		foreach ( $wp_roles->roles as $key => $role ) {
			printf( '<option value="%s" %s>%s</option>', $key, selected( true, in_array( $key, (array) $args['content'] ), false ), $role['name'] );
		}

		echo '<select>';
	}

	/**
	 * Sanitize multi select options
	 *
	 * @param $data
	 *
	 * @return array|string
	 */
	public static function sanitize_multi_select( $data ) {
		if ( is_array( $data ) ) {
			$data = array_map( 'sanitize_text_field', $data );
		} else {
			$data = sanitize_text_field( $data );
		}

		return $data;
	}

	/**
	 * Increment the message id so everyone will see it next time they reload
	 *
	 * @param $data
	 */
	public static function increment_msg_id( $data ) {
		if ( ! is_null( $data ) ) {
			$id = get_option( self::SETTINGS_PREFIX . 'id', 1 );
			$id++;
			update_option( self::SETTINGS_PREFIX . 'id', $id );
		}
	}

	/**
	 * Add new admin menu to settings page
	 */
	public static function register_settings_page() {
		add_options_page(
			__( 'Admin User Message', 'admin-user-message' ),
			__( 'Admin User Message', 'admin-user-message' ),
			apply_filters( 'admin_user_message_cap', 'manage_options' ),
			self::PAGE_NAME,
			array( self::$class, 'page_callback' )
		);
	}
	
	/**
	 * Page function
	 */
	public static function page_callback() {
		?>
			<div class="wrap">
				<h2><?php _e( 'Admin User Message Settings', 'admin-user-message' ); ?></h2>
				<form action="options.php" method="post">
					<?php settings_fields( self::PAGE_NAME ); ?>
					<?php do_settings_sections( self::PAGE_NAME ); ?>

					<?php submit_button(); ?>
				</form>
			</div>
		<?php
	}

	/**
	 * Add admin notices for user
	 */
	public static function add_admin_notices() {
		$dismiss_enabled = get_option( self::SETTINGS_PREFIX . 'dismiss' );
		$content         = get_option( self::SETTINGS_PREFIX . 'content' );

		if ( ! self::can_show_message_for_role() || self::user_has_dismissed() && $dismiss_enabled || empty( $content ) ) {
			return false;
		}
		?>
			<style>
				.admin-user-message p {
					display: inline-block;
				}

				.admin-user-message p.content {
					max-width: 80%;
				}

				.admin-user-message p.dismiss {
					float: right;
				}

				.admin-user-message p.dismiss a {
					text-decoration: none;
				}

				.admin-user-message p.dismiss a:before {
					font-family: 'dashicons';
					content: "\f158";
					vertical-align: middle;
				}

				.admin-user-message.update-nag p.content {
					margin-right: 10px;
				}
			</style>
			<div class="<?php echo esc_attr( get_option( self::SETTINGS_PREFIX . 'type', 'updated' ) ); ?> admin-user-message">
				<p class="content"><?php echo $content; //xss ok ?></p>
				<?php if ( ! empty( get_option( self::SETTINGS_PREFIX . 'dismiss' ) ) ):  ?>
				<p class="dismiss">
					<a href="<?php echo admin_url( 'admin-ajax.php?action=admin_user_message_dismiss&admin_user_message_nonce=' . wp_create_nonce( 'admin_user_message_nonce' ) ); //xss ok ?>"
					   title="<?php esc_html_e( 'Dismiss this message', 'admin-user-message' ); ?>">
						<?php esc_html_e( 'Dismiss', 'admin-user-message' ); ?>
					</a>
				</p>
				<?php endif ?>
			</div>
			<script>
				(function($) {
					$('.admin-user-message p.dismiss a').on('click', function(event) {
						event.preventDefault();
						var $link = $(this);
						$.get(
							$link.attr('href'),
							function(data) {
								if (data.success === true) {
									$link.parents('.admin-user-message').slideToggle();
								}
							}
						)
					});
				})(jQuery);
			</script>
		<?php
	}

	/**
	 * Don't show message if user has dismissed it
	 */
	private static function user_has_dismissed() {
		$token = wp_get_session_token();
		if ( $token ) {
			$manager = WP_Session_Tokens::get_instance( get_current_user_id() );
			$session = $manager->get( $token );

			$msg_id = get_option( self::SETTINGS_PREFIX . 'id', 1 );
			if ( isset( $session[ 'admin-user-message-dismiss-' . $msg_id ] ) ) {
				return true;
			}
		}
	}

	/**
	 * Validate roles
	 *
	 * @return bool
	 */
	private static function can_show_message_for_role() {
		$user = wp_get_current_user();

		if ( empty( $user ) ) {
			return false;
		}

		$exclude_for_roles = get_option( self::SETTINGS_PREFIX . 'exclude', array() );

		// No role was selected so we can show the message
		if ( empty( $exclude_for_roles ) ) {
			return true;
		}

		foreach ( (array) $user->roles as $role ) {
			if ( ! in_array( $role, $exclude_for_roles ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Will dismiss message for current session only
	 */
	public static function dismiss_message() {
		check_ajax_referer( 'admin_user_message_nonce', 'admin_user_message_nonce' );

		$token = wp_get_session_token();
		if ( $token ) {
			$manager = WP_Session_Tokens::get_instance( get_current_user_id() );
			$session = $manager->get( $token );
			add_filter( 'attach_session_information', '__return_empty_array' );
			$manager->update( $token, array_merge( $session, array( 'admin-user-message-dismiss-' . get_option( self::SETTINGS_PREFIX . 'id', 1 ) => true ) ) );
		}

		wp_send_json_success();
	}
}

add_action( 'plugins_loaded', array( 'Admin_User_Message', 'setup' ) );
