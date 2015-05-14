<?php
/**
 * Plugin Name: Basic User Avatars
 * Plugin URI:  http://wordpress.org/extend/basic-user-avatars
 * Description: Adds an avatar upload field to user profiles. Also provides front-end avatar management via a shortcode and bbPress support. No frills. Fork of Simple Local Avatars 1.3.
 * Version:     1.0.3
 * Author:      Jared Atchison
 * Author URI:  http://jaredatchison.com
 *
 * ---------------------------------------------------------------------------//
 * This plugin is a fork of Simple Local Avatars v1.3.1 by Jake Goldman (10up).
 *
 * Orignal author url:  http://get10up.com
 * Original plugin url: http://wordpress.org/plugins/simple-local-avatars
 *
 * If you want some snazzy ajax and some other nifty features, check out Simple
 * Local Avatars 2.0+
 * ---------------------------------------------------------------------------//
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WP Forms. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author     Jared Atchison
 * @version    1.0.3
 * @package    JA_BasicLocalAvatars
 * @copyright  Copyright (c) 2015, Jared Atchison
 * @link       http://jaredatchison.com
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

class basic_user_avatars {

	/**
	 * User ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $user_id_being_edited;
	private static $options = NULL;

	/**
	 * Initialize all the things
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		//Get default options

		$db_options=get_option('basic_user_avatars_caps');
		self::$options = array('upload_path' => wp_upload_dir(),
								'deny_upload'=>$db_options['basic_user_avatars_caps']
								);
		// Text domain
		$this->load_textdomain();

		// Actions
		add_action( 'admin_init',                array( $this, 'admin_init'               )        );
		add_action( 'show_user_profile',         array( $this, 'edit_user_profile'        ),20     );//own profile
		add_action( 'edit_user_profile',         array( $this, 'edit_user_profile'        ),20     );//others profile
		add_action( 'personal_options_update',   array( $this, 'edit_user_profile_update' )        );//own profile
		add_action( 'edit_user_profile_update',  array( $this, 'edit_user_profile_update' )        );//others profile

		// Filters
		add_filter( 'get_avatar',                array( $this, 'get_avatar'               ), 20, 5 );
		add_filter( 'avatar_defaults',           array( $this, 'avatar_defaults'          )        );
	}

	/**
	 * Loads the plugin language files.
	 *
	 * @since 1.0.1
	 */
	public function load_textdomain() {
		$domain = 'basic-user-avatars';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Start the admin engine.
	 *
	 * @since 1.0.0
	 */
	public function admin_init() {
		// Register/add the Discussion setting to restrict avatar upload capabilites
		register_setting( 'discussion', 'basic_user_avatars_caps', array( __CLASS__, 'sanitize_options' ) );
		add_settings_field( 'basic-user-avatars-caps', __( 'Local Avatar Permissions', 'basic-user-avatars' ), array( __CLASS__, 'avatar_settings_field' ), 'discussion', 'avatars' );
	}


	/**
	 * Discussion settings option
	 *
	 * @since 1.0.0
	 * @param array $args [description]
	 */
	public static function avatar_settings_field( $args ) {
		$options = get_option( 'basic_user_avatars_caps');
		?>
		<label for="basic_user_avatars_caps">
			<input type="checkbox" name="basic_user_avatars_caps" id="basic_user_avatars_caps" value="1"
					<?php checked(self::$options['deny_upload'], 1 ); ?>/>
			<?php _e( 'Only allow users with file upload capabilities to upload local avatars (Authors and above)', 'basic-user-avatars' ); ?>
		</label>
		<?php
	}

	/**
	 * Sanitize the Discussion settings option
	 *
	 * @since 1.0.0
	 * @param array $input
	 * @return array
	 */
	public function sanitize_options( $input ) {
		$new_input['basic_user_avatars_caps'] =  $input['basic_user_avatars_caps'] ? 1 : 0;
		return $new_input;
	}

	/**
	 * Filter the avatar WordPress returns
	 *
	 * @since 1.0.0
	 * @param string $avatar 
	 * @param int/string/object $id_or_email
	 * @param int $size 
	 * @param string $default
	 * @param boolean $alt 
	 * @return string
	 */
	public function get_avatar( $avatar = '', $id_or_email, $size = 96, $default = '', $alt = false ) {
add_filter( 'upload_dir',  array( $this, 'set_avatar_directory') );
		// Determine if we recive an ID or string
		if ( is_numeric( $id_or_email ) )
			$user_id = (int) $id_or_email;
		elseif ( is_string( $id_or_email ) && ( $user = get_user_by( 'email', $id_or_email ) ) )
			$user_id = $user->ID;
		elseif ( is_object( $id_or_email ) && ! empty( $id_or_email->user_id ) )
			$user_id = (int) $id_or_email->user_id;

		if ( empty( $user_id ) )
			return $avatar;

		$local_avatars = get_user_meta( $user_id, 'basic_user_avatar', true );
		/*print("<pre>");
		print_r($local_avatars);
		print("</pre>");*/
		if ( empty( $local_avatars ) || empty( $local_avatars['full'] ) )
			return $avatar;

		$size = (int) $size;

		if ( empty( $alt ) )
			$alt = get_the_author_meta( 'display_name', $user_id );

		// Generate a new size
		if ( empty( $local_avatars[$size] ) ) {

			$upload_path =self::$options['upload_path'];
			$avatar_full_path = str_replace( $upload_path['baseurl'], $upload_path['basedir'], $local_avatars['full'] );
			$image            = wp_get_image_editor( $avatar_full_path );

			if ( ! is_wp_error( $image ) ) {
				$image->resize( $size, $size, true );
				$image_sized = $image->save();
			}

			// Deal with original being >= to original image (or lack of sizing ability)
			$local_avatars[$size] = is_wp_error( $image_sized ) ? $local_avatars[$size] = $local_avatars['full'] : str_replace( $upload_path['basedir'], 					$upload_path['baseurl'], $image_sized['path'] );

			// Save updated avatar sizes
			update_user_meta( $user_id, 'basic_user_avatar', $local_avatars );

		} elseif ( substr( $local_avatars[$size], 0, 4 ) != 'http' ) {
			$local_avatars[$size] = home_url( $local_avatars[$size] );
		}

		$author_class = is_author( $user_id ) ? ' current-author' : '' ;
		$avatar       = "<img alt='" . esc_attr( $alt ) . "' src='" . $local_avatars[$size] . "' class='avatar avatar-{$size}{$author_class} photo' height='{$size}' width='{$size}' />";
		return apply_filters( 'basic_user_avatar', $avatar );
remove_filter( 'upload_dir',  array( $this, 'set_avatar_directory') );
}
	

	/**
	 * Form to display on the user profile edit screen
	 *
	 * @since 1.0.0
	 * @param object $profileuser
	 * @return
	 */
	public function edit_user_profile( $profileuser ) {
		?>

		<h3><?php _e( 'Avatar', 'basic-user-avatars' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="basic-user-avatar"><?php _e( 'Upload Avatar', 'basic-user-avatars' ); ?></label></th>
				<td style="width: 50px;" valign="top">
					<?php echo get_avatar( $profileuser->ID ); ?>
				</td>
				<td>
				<?php

				if ( empty( self::$options['deny_upload'] ) || current_user_can( 'upload_files' ) ) {
					// Nonce security ftw
					wp_nonce_field( 'basic_user_avatar_nonce', '_basic_user_avatar_nonce', false );
					
					// File upload input
					echo '<input type="file" name="basic-user-avatar" id="basic-local-avatar" /><br />';

					if ( empty( $profileuser->basic_user_avatar ) ) {
						echo '<span class="description">' . __( 'No local avatar is set. Use the upload field to add a local avatar.', 'basic-user-avatars' ) . '</span>';
					} else {
						echo '<input type="checkbox" name="basic-user-avatar-erase" value="1" /> ' . __( 'Delete local avatar', 'basic-user-avatars' ) . '<br />';
						echo '<span class="description">' . __( 'Replace the local avatar by uploading a new avatar, or erase the local avatar (falling back to a gravatar) by checking the delete option.', 'basic-user-avatars' ) . '</span>';
					}

				} else {
					if ( empty( $profileuser->basic_user_avatar ) ) {
						echo '<span class="description">' . __( 'No local avatar is set. Set up your avatar at Gravatar.com.', 'basic-user-avatars' ) . '</span>';
					} else {
						echo '<span class="description">' . __( 'You do not have media management permissions. To change your local avatar, contact the site administrator.', 'basic-user-avatars' ) . '</span>';
					}	
				}
				?>
				</td>
			</tr>
		</table>
		<script type="text/javascript">var form = document.getElementById('your-profile');form.encoding = 'multipart/form-data';form.setAttribute('enctype', 'multipart/form-data');</script>
		<?php
	}

	/**
	 * Update the user's avatar setting
	 *
	 * @since 1.0.0
	 * @param int $user_id
	 */
	public function edit_user_profile_update( $user_id ) {
add_filter( 'upload_dir',  array( $this, 'set_avatar_directory') );
		// Check for nonce otherwise bail
		if ( ! isset( $_POST['_basic_user_avatar_nonce'] ) || ! wp_verify_nonce( $_POST['_basic_user_avatar_nonce'], 'basic_user_avatar_nonce' ) )
			return;

		if ( ! empty( $_FILES['basic-user-avatar']['name'] ) ) {

			// Allowed file extensions/types
			$mimes = array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'gif'          => 'image/gif',
				'png'          => 'image/png',
			);

			// Front end support - shortcode, bbPress, etc
			if ( ! function_exists( 'wp_handle_upload' ) )
				require_once ABSPATH . 'wp-admin/includes/file.php';

			// Delete old images if successful
			$this->avatar_delete( $user_id );

			// Need to be more secure since low privelege users can upload
			if ( strstr( $_FILES['basic-user-avatar']['name'], '.php' ) )
				wp_die( 'For security reasons, the extension ".php" cannot be in your file name.' );

			// Make user_id known to unique_filename_callback function
			$this->user_id_being_edited = $user_id; 
			$avatar = wp_handle_upload( $_FILES['basic-user-avatar'], array( 'mimes' => $mimes, 'test_form' => false, 'unique_filename_callback' => array( $this, 'unique_filename_callback' ) ) );

			// Handle failures
			if ( empty( $avatar['file'] ) ) {  
				switch ( $avatar['error'] ) {
				case 'File type does not meet security guidelines. Try another.' :
					add_action( 'user_profile_update_errors', create_function( '$a', '$a->add("avatar_error",__("Please upload a valid image file for the avatar.","basic-user-avatars"));' ) );
					break;
				default :
					add_action( 'user_profile_update_errors', create_function( '$a', '$a->add("avatar_error","<strong>".__("There was an error uploading the avatar:","basic-user-avatars")."</strong> ' . esc_attr( $avatar['error'] ) . '");' ) );
				}
				return;
			}

			// Save user information (overwriting previous)
			update_user_meta( $user_id, 'basic_user_avatar', array( 'full' => $avatar['url'] ) );

		} elseif ( ! empty( $_POST['basic-user-avatar-erase'] ) ) {
			// Nuke the current avatar
			$this->avatar_delete( $user_id );
		}
remove_filter( 'upload_dir',  array( $this, 'set_avatar_directory') );
}

	/**
	 * Remove the custom get_avatar hook for the default avatar list output on 
	 * the Discussion Settings page.
	 *
	 * @since 1.0.0
	 * @param array $avatar_defaults
	 * @return array
	 */
	public function avatar_defaults( $avatar_defaults ) {
		remove_action( 'get_avatar', array( $this, 'get_avatar' ) );
		return $avatar_defaults;
	}

	/**
	 * Delete avatars based on user_id
	 *
	 * @since 1.0.0
	 * @param int $user_id
	 */
	public function avatar_delete( $user_id ) {
		$old_avatars = get_user_meta( $user_id, 'basic_user_avatar', true );
		$upload_path = self::$options['upload_path'];

		if ( is_array( $old_avatars ) ) {
			foreach ( $old_avatars as $old_avatar ) {
				$old_avatar_path = str_replace( $upload_path['baseurl'], $upload_path['basedir'], $old_avatar );
				@unlink( $old_avatar_path );
			}
		}

		delete_user_meta( $user_id, 'basic_user_avatar' );
	}
	
	/**
	 * Set upload dir
	 *
	 * @since 1.0.0
	 * @param int $user_id
	 */
	public function set_avatar_directory( $param ){
    $mydir = '/profilephoto';

    $param['path'] = $param['basedir'] . $mydir;
    $param['url'] = $param['baseurl'] . $mydir;
	/*
    error_log("path={$param['path']}");  
    error_log("url={$param['url']}");
    error_log("subdir={$param['subdir']}");
    error_log("basedir={$param['basedir']}");
    error_log("baseurl={$param['baseurl']}");
    error_log("error={$param['error']}"); 
	*/
	return $param;
	}

	/**
	 * File names are magic
	 *
	 * @since 1.0.0
	 * @param string $dir
	 * @param string $name
	 * @param string $ext
	 * @return string
	 */
	public function unique_filename_callback( $dir, $name, $ext ) {
		$user = get_user_by( 'id', (int) $this->user_id_being_edited );
		$name = $base_name = sanitize_file_name( $user->display_name . '_avatar' );
		$number = 1;

		while ( file_exists( $dir . "/$name$ext" ) ) {
			$name = $base_name . '_' . $number;
			$number++;
		}

		return $name . $ext;
	}
}
$basic_user_avatars = new basic_user_avatars;

/**
 * During uninstallation, remove the custom field from the users and delete the local avatars
 *
 * @since 1.0.0
 */
function basic_user_avatars_uninstall() {
	$basic_user_avatars = new basic_user_avatars;
	$users = get_users_of_blog();

	foreach ( $users as $user )
		$basic_user_avatars->avatar_delete( $user->user_id );

	delete_option( 'basic_user_avatars_caps' );
}
register_uninstall_hook( __FILE__, 'basic_user_avatars_uninstall' );
