<?php
/**
 * Plugin Name: Minimal User Avatars
 * Plugin URI:  https://github.com/sistlind/wp-min-user-avatars
 * Description: Adds an avatar upload field to user profiles. Fork of Basic User Avatars 1.0.3.
 * Version:     git
 * Author:      Stefan Lindner
 * Author URI:  http://jaredatchison.com
 *
 * ---------------------------------------------------------------------------//
 * This plugin is a fork of Basic User Avatars 1.0.3 by Jared Atchison.
 *
 * Orignal author url:  http://jaredatchison.com
 * Original plugin url: http://wordpress.org/extend/basic-user-avatars
 *
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
 * @author     Stefan Lindner
 * @version    git
 * @package    wp_min_user_avatars
 * @copyright  Copyright (c) 2015, Stefan Lindner
 * @link       
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

class min_user_avatars {

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

		$db_options=get_option('min_user_avatars_caps');
		self::$options = array('upload_dir' => 'photos',// ->uploads/photos
								'deny_upload'=>$db_options['min_user_avatars_caps']
								);
		//save upload_path
		add_filter( 'upload_dir',  array( $this, 'set_avatar_directory') );
		self::$options['upload_path']=wp_upload_dir();
    remove_filter( 'upload_dir',  array( $this, 'set_avatar_directory') );
		// Text domain
		$this->load_textdomain();

		// Actions
		add_action( 'admin_init',                array( $this, 'admin_init'               )        );
		add_action( 'show_user_profile',         array( $this, 'edit_user_profile'        ),20     );//own profile
		add_action( 'edit_user_profile',         array( $this, 'edit_user_profile'        ),20     );//others profile
		add_action( 'personal_options_update',   array( $this, 'edit_user_profile_update' )        );//own profile
		add_action( 'edit_user_profile_update',  array( $this, 'edit_user_profile_update' )        );//others profile

 		// Filters
		add_filter( 'pre_get_avatar',            array($this, 'pre_get_avatar_filter' ),10,3);//adjust also priority of remove_filter!
		add_filter( 'avatar_defaults',           array($this, 'avatar_defaults_filter'),10  );
		//Shortcode
		add_shortcode('author',                  array($this, 'print_author'));
	}

	/**
	 * Loads the plugin language files.
	 *
	 * @since 1.0.1
	 */
	public function load_textdomain() {
		$domain = 'min-user-avatars';
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
		register_setting( 'discussion', 'min_user_avatars_caps', array($this, 'sanitize_options' ) );
		register_setting( 'discussion', 'min_user_delete_non_scaled', array( $this, 'sanitize_options' ) );
		add_settings_field( 'min-user-avatars-caps', __( 'Local Avatar Permissions', 'min-user-avatars' ), array( $this, 'avatar_settings_field' ), 'discussion', 'avatars' );

	}


	/**
	 * Discussion settings option
	 *
	 * @since 1.0.0
	 * @param array $args [description]
	 */
	public static function avatar_settings_field( $args ) {
		$options = get_option( 'min_user_avatars_caps');
		?>
		<label for="min_user_avatars_caps">
			<input type="checkbox" name="min_user_avatars_caps" id="min_user_avatars_caps" value="1"
					<?php checked(self::$options['deny_upload'], 1 ); ?>/>
			<?php _e( 'Only allow users with file upload capabilities to upload local avatars (Authors and above)', 'min-user-avatars' ); ?>
		</label>
		<br>
		<label for="min_user_delete_non_scaled">
			<input type="checkbox" name="min_user_delete_non_scaled" id="min_user_delete_non_scaled" value="1"/>
			<?php _e( 'Check and save to delete all scaled picture(will be auto-regenerated at request).', 'min-user-avatars' ); ?>
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
		$new_input['min_user_avatars_caps'] =  $input['min_user_avatars_caps'] ? 1 : 0;
		print_r($input);
		
		if($input['min_user_delete_non_scaled']){
		echo "delete requested";
		self::min_user_delete_scaled();
		echo "All deleted";
		
		break;
		}
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
	public function pre_get_avatar_filter( $avatar = '', $id_or_email, $args=null ) {
    $avatar="";
			// Determine if we recive an ID or string
		if ( is_numeric( $id_or_email ) )
			$user_id = (int) $id_or_email;
		elseif ( is_string( $id_or_email ) && ( $user = get_user_by( 'email', $id_or_email ) ) )
			$user_id = $user->ID;
		elseif ( is_object( $id_or_email ) && ! empty( $id_or_email->user_id ) )
			$user_id = (int) $id_or_email->user_id;

		if ( empty( $user_id ) )
			return $avatar;

		$local_avatars = get_user_meta( $user_id, 'min_user_avatar', true );
	/*	print("<pre>");
		print_r($local_avatars);
		print("</pre>");*/
		if ( empty( $local_avatars ) || empty( $local_avatars['full'] ) )
    {
    return $avatar;
    }

      $defaults = array(
            // get_avatar_data() args.
            'size'          => 96,
            'height'        => null,
            'width'         => null,
            'default'       => get_option( 'avatar_default', 'mystery' ),
            'force_default' => false,
            'rating'        => get_option( 'avatar_rating' ),
            'scheme'        => null,
            'alt'           => '',
            'class'         => null,
            'force_display' => false,
            'extra_attr'    => '',
	       );

      if(empty($args)){$args = array();}
         // $args['size']    = (int) $size;
	       // $args['default'] = $default;
	       // $args['alt']     = $alt;
     $args = wp_parse_args( $args, $defaults );
	
	        if (empty( $args['height'])) {
	                $args['height'] = $args['size'];
	        }
	        if ( empty( $args['width'] ) ) {
	                $args['width'] = $args['size'];
	        }


		$sizewidth = (int) $args['width'];
		$sizeheight = (int) $args['height'];
		$sizename=$sizewidth.'x'.$sizeheight;
		if ( empty( $alt ) )
			{$alt = get_the_author_meta( 'display_name', $user_id );}

		// Generate a new size
		if (empty( $local_avatars[$sizename] ) ) {

			$avatar_full_path = str_replace( self::$options['upload_path']['baseurl'], self::$options['upload_path']['basedir'], $local_avatars['full'] );

			$image= wp_get_image_editor( $avatar_full_path );

			if ( ! is_wp_error( $image ) ) {
				$image->resize( $sizewidth, $sizeheight, true );
				$image_sized = $image->save();
			}else
			{echo "error at resize";}

			// Deal with original being >= to original image (or lack of sizing ability)
			$local_avatars[$sizename] = is_wp_error( $image_sized ) ? $local_avatars[$size] = $local_avatars['full'] : str_replace( self::$options['upload_path']['basedir'], self::$options['upload_path']['baseurl'], $image_sized['path'] );

			// Save updated avatar sizes
			update_user_meta( $user_id, 'min_user_avatar', $local_avatars );

		} elseif ( substr( $local_avatars[$sizename], 0, 4 ) != 'http' ) {
			$local_avatars[$sizename] = home_url( $local_avatars[$sizename] );
		}
		

		$author_class = is_author( $user_id ) ? ' current-author' : '' ;
		$avatar       = "<img alt='" . esc_attr( $alt ) . "' src='" . $local_avatars[$sizename] . "' class='avatar avatar-{$sizename}{$author_class} photo' height='{$sizeheight}' width='{$sizewidth}' />";
		return $avatar;
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

		<h3><?php _e( 'Photo', 'min-user-avatars' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="min-user-avatar"><?php _e( 'Upload Photo', 'min-user-avatars' ); ?></label></th>
				<td style="width: 50px;" valign="top">
					<?php echo get_avatar($profileuser->ID,'','NOLINK','',array('width'=>150,'height'=>200));?>
				</td>
				<td>
				<?php

				if ( empty( self::$options['deny_upload'] ) || current_user_can( 'upload_files' ) ) {
					// Nonce security ftw
					wp_nonce_field( 'min_user_avatar_nonce', '_min_user_avatar_nonce', false );
					
					// File upload input
					echo '<input type="file" name="min-user-avatar" id="min-local-avatar" /><br />';

					if ( empty( $profileuser->min_user_avatar ) ) {
						echo '<span class="description">' . __( 'No local photo is set. Use the upload field to add a local photo.', 'min-user-avatars' ) . '</span>';
					} else {
						echo '<input type="checkbox" name="min-user-avatar-erase" value="1" /> ' . __( 'Delete local photo', 'min-user-avatars' ) . '<br />';
						echo '<span class="description">' . __( 'Replace the local photo by uploading a new photo, or erase the local photo by checking the delete option.', 'min-user-avatars' ) . '</span>';
					}

				} else {
					if ( empty( $profileuser->min_user_avatar ) ) {
						echo '<span class="description">' . __( 'No local photo is set, you don´t have permissions to set one, ask your administrator.', 'min-user-avatars' ) . '</span>';
					} else {
						echo '<span class="description">' . __( 'You do not have media management permissions. To change your local photo, contact the site administrator.', 'min-user-avatars' ) . '</span>';
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
		// Check for nonce otherwise bail
		if ( ! isset( $_POST['_min_user_avatar_nonce'] ) || ! wp_verify_nonce( $_POST['_min_user_avatar_nonce'], 'min_user_avatar_nonce' ) )
			return;

		if ( ! empty( $_FILES['min-user-avatar']['name'] ) ) {

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
			if ( strstr( $_FILES['min-user-avatar']['name'], '.php' ) )
				wp_die( 'For security reasons, the extension ".php" cannot be in your file name.' );

			// Make user_id known to unique_filename_callback function
			$this->user_id_being_edited = $user_id; 
			add_filter( 'upload_dir',  array( $this, 'set_avatar_directory'));
			$avatar = wp_handle_upload( $_FILES['min-user-avatar'], array( 'mimes' => $mimes, 'test_form' => false, 'unique_filename_callback' => array( $this, 'unique_filename_callback' ) ) );
      remove_filter( 'upload_dir',  array( $this, 'set_avatar_directory'));
			// Handle failures
			if ( empty( $avatar['file'] ) ) {  
				switch ( $avatar['error'] ) {
				case 'File type does not meet security guidelines. Try another.' :
					add_action( 'user_profile_update_errors', create_function( '$a', '$a->add("avatar_error",__("Please upload a valid image file for the avatar.","min-user-avatars"));' ) );
					break;
				default :
					add_action( 'user_profile_update_errors', create_function( '$a', '$a->add("avatar_error","<strong>".__("There was an error uploading the avatar:","min-user-avatars")."</strong> ' . esc_attr( $avatar['error'] ) . '");' ) );
				}
				return;
			}

			// Save user information (overwriting previous)
			update_user_meta( $user_id, 'min_user_avatar', array( 'full' => $avatar['url'] ) );

		} elseif ( ! empty( $_POST['min-user-avatar-erase'] ) ) {
			// Nuke the current avatar
			$this->avatar_delete( $user_id );
		}
}

	/**
	 * Remove the custom get_avatar hook for the default avatar list output on 
	 * the Discussion Settings page.
	 *
	 * @since 1.0.0
	 * @param array $avatar_defaults
	 * @return array
	 */
	public function avatar_defaults_filter( $avatar_defaults ) {
		remove_action( 'pre_get_avatar', array($this, 'pre_get_avatar_filter' ),10);
	 return $avatar_defaults;
	}

	/**
	 * Delete avatars based on user_id
	 *
	 * @since 1.0.0
	 * @param int $user_id
	 */
	public function avatar_delete( $user_id ) {
		$old_avatars = get_user_meta( $user_id, 'min_user_avatar', true );

		if ( is_array( $old_avatars ) ) {
			foreach ( $old_avatars as $old_avatar ) {
				$old_avatar_path = str_replace( self::$options['upload_path']['baseurl'], self::$options['upload_path']['basedir'], $old_avatar );
				@unlink( $old_avatar_path );
			}
		}

		delete_user_meta( $user_id, 'min_user_avatar' );
	}
	
	/**
	 * Override upload dir
	 *
	 * @since 1.0.0
	 * @param int $user_id
	 */
	public function set_avatar_directory( $param ){

    $param['path'] = $param['basedir'] . "/". self::$options['upload_dir'];
    $param['url'] = $param['baseurl'] . "/". self::$options['upload_dir'];

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
    $ext=strtolower($ext);//only lowercase
		if(($ext===".jpeg")||($ext===".jpe")){$ext=".jpg";}//only use .jpg
		$user = get_user_by( 'id', (int) $this->user_id_being_edited );
		$name = $basename=sanitize_file_name( $user->user_nicename . '' );
		$number = 1;

		while ( file_exists( $dir . "/" . $name . $ext) ) {
			$name = $basename . '_' . $number;
			$number++;
		}
		if (empty($name))
		  {
		    $name=$this->user_id_being_edited;
		  }
		return $name . $ext;
	}
	/**
	 * Delete only scaled versions
	 *
	 */
	public function min_user_delete_scaled() {
	$users = get_users_of_blog();
	echo "delete all scaled pictures";

	foreach ( $users as $user ){
	echo "<br>#########NEW User: ".$user->display_name." ###########<br>";
    $avatars=get_user_meta( $user->ID, 'min_user_avatar', true );
    if(!is_array($avatars))
      {
      echo "No Avatar found!";
      continue;
      }
   foreach ($avatars as $size=>$url)
    {//echo $size;
      if (strtolower($size)==="full")//dont delete unscaled picture
        {
         echo "Skipping unscaled version<br>";
        continue;
        }
    	$old_avatar_path = str_replace( self::$options['upload_path']['baseurl'], self::$options['upload_path']['basedir'], $url );
      echo "Deleting size ".$size." and file: ".$old_avatar_path."<br>";
			unset($avatars[$size]);
			@unlink( $old_avatar_path );
    }
    update_user_meta( $user->ID, 'min_user_avatar', $avatars);
  }
}

/* Shortcode display author */
function print_author($atts){
$output_string="";
        /* If the user passed an integer then good to go */
        if (is_numeric($atts[0])) {
                $authorid = $atts[0];
           }
        if (strpos($atts[0],'@') !==false) {
                $email = $atts[0];
                $user = get_user_by('email',$email);
                if($user)$authorid=$user->ID;
       }

        if (!empty($authorid)){
              ob_start();
              echo "<table style=\"border:0;width:200px\"><tr><td style=border:0>";
              echo get_avatar($authorid,64);
              echo "</td>";
              echo '<td style=border:0><a href="'.get_author_posts_url($authorid).'/">';
              echo the_author_meta('display_name', $authorid );

        echo '</a></td></tr></table>';
        $output_string = ob_get_contents();
        ob_end_clean();
        }
return $output_string;
}

//add_filter('my_authors_meta', 'do_shortcode');
/* Start Get Author Link call [author id] */




	
	
}
$min_user_avatars = new min_user_avatars;

/**
 * During uninstallation, remove the custom field from the users and delete the local avatars
 *
 * @since 1.0.0
 */
function min_user_avatars_uninstall() {
	$min_user_avatars = new min_user_avatars;
	$users = get_users_of_blog();

	foreach ( $users as $user )
		$min_user_avatars->avatar_delete( $user->user_id );

	delete_option( 'min_user_avatars_caps');
}

register_uninstall_hook( __FILE__, 'min_user_avatars_uninstall' );
