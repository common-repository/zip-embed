<?php
/*
Plugin Name: Zip Embed
Plugin URI: http://trepmal.com/plugins/zip-embed
Description: Upload a zip file and embed its contents
Author: Kailey Lampert
Version: 0.4
Author URI: http://kaileylampert.com/

Copyright (C) 2011  Kailey Lampert

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


/**
 * Zip_Embed class
 *
 * class used as a namespace
 *
 * @package Zip Embed
 */
class Zip_Embed {

	var $tmp_dir;
	var $default_format = '<h2>[zip_title]</h2><div class="zip_file">[zip_files]<h3>[zip_file_name]</h3><textarea class="widefat" readonly>[zip_file_contents]</textarea>[/zip_files]</div>[zip_gallery]';

	/**
	 * Get hooked into init
	 *
	 * @return void
	 */
	function __construct( ) {
		load_plugin_textdomain( 'zip-embed', false, dirname( plugin_basename( __FILE__ ) ) .  '/lang' );
		add_action( 'init', array( &$this, 'general' ) );
		add_action( 'init', array( &$this, 'post_type' ) );
		add_filter( 'manage_edit-zip_columns', array( &$this, 'add_column' ) );
		add_action( 'manage_posts_custom_column', array( &$this, 'fill_column' ), 10, 2 );
		add_action( 'add_meta_boxes', array( &$this, 'setup_meta_boxes' ) );
		add_shortcode( 'zip', array( &$this, 'sc_zip' ) );
		add_action( 'load-post-new.php', array( &$this, 'redirect' ) );
		add_action( 'admin_menu', array( &$this, 'menu' ) );
		add_filter( 'contextual_help', array( &$this, 'help' ), 10, 3 );
		add_filter( 'media_upload_tabs', array( &$this, 'create_new_tab') );
		add_action( 'media_buttons', array( &$this, 'context'), 11 );
		add_filter( 'media_upload_uploadzip', array( &$this, 'media_upload_uploadzip') );

		add_filter( 'admin_init', array( &$this, 'register_fields' ) );
		add_filter( 'admin_init', array( &$this, 'allowed_tags' ) );

		add_shortcode( 'zip_title', array( &$this, 'sc_zip_title' ) );
		add_shortcode( 'zip_files', array( &$this, 'sc_zip_files' ) );
		//add_shortcode( 'zip_file_name', array( &$this, 'sc_zip_file_name' ) );
		//add_shortcode( 'zip_file_contents', array( &$this, 'sc_zip_file_contents' ) );
		add_shortcode( 'zip_gallery', array( &$this, 'sc_zip_gallery' ) );
	}
	/**
	 * General setup
	 *
	 * @return void
	 */
	function general() {
		/* create a 'temp' directory 
		 * inside the uploads folder
		 */
		$upl = wp_upload_dir();
		$temp = $upl['basedir'] . '/temp/';
		if ( ! is_dir( $temp ) ) {
			if ( ! mkdir( $temp ) ) {
				$this->tmp_dir = false;
			}
		}
		$this->tmp_dir = $temp;
	}
	/**
	 * Create custom post type for storing plugins
	 *
	 * @return void
	 */
	function post_type() {
		$labels = array(
			'name' => _x('Zips', 'post type general name', 'zip-embed' ),
			'singular_name' => _x('Zip', 'post type singular name', 'zip-embed' ),
			'add_new' => __('Upload Zip', 'zip-embed' ),
			'add_new_item' => __('Upload Zip', 'zip-embed' ),
			'edit_item' => __('View Zip', 'zip-embed' ),
			'new_item' => __('Upload Zip', 'zip-embed' ),
			'all_items' => __('All Zips', 'zip-embed' ),
			//'view_item' => __('View Book', 'zip-embed' ),
			'search_items' => __('Search Zips', 'zip-embed' ),
			'not_found' =>  __('No zips found', 'zip-embed' ),
			'not_found_in_trash' => __('No zips found in Trash', 'zip-embed' ),
			//'parent_item_colon' => '',
			'menu_name' => 'Zips'
		);
		$args = array(
			'labels' => $labels,
			'public' => false,
			'show_ui' => true,
			'capability_type' => 'post',
			'hierarchical' => false,
			'supports' => array('title')
		);
		register_post_type( 'zip', $args );
	}
	/**
	 * Add column for shortcode
	 *
	 * @return array Modified columns
	 */
	function add_column( $columns ) {
		$columns['shortcode'] = 'Shortcode';
		return $columns;
	}
	/**
	 * Populate new column
	 *
	 * @return void
	 */
	function fill_column( $column_name, $id ) {
		switch ( $column_name ) {
			case 'shortcode' :
				echo "<input type='text' readonly value='[zip id=$id]' />";
			break;
		}
	}
	/**
	 * Create meta boxes
	 *
	 * @return void
	 */
	function setup_meta_boxes() {
		add_meta_box( 'zip_files', __( 'Zip Files', 'zip-embed' ), array( &$this, 'zip_files_box' ), 'zip', 'normal' );
		add_meta_box( 'zip_original', __( 'Original Zip', 'zip-embed' ), array( &$this, 'zip_original_box' ), 'zip', 'side' );
		add_meta_box( 'zip_attachments', __( 'Zip Attachments', 'zip-embed' ), array( &$this, 'zip_attachments_box' ), 'zip', 'side' );
	}
	/**
	 * Meta box for original zip file
	 *
	 * @return void
	 */
	function zip_original_box( $post ) {
		$original = get_post_meta( get_the_ID(), 'original', true );

		$or = get_post($original);
		echo "<h4><a href='". get_permalink( $or->ID ) ."'>". __( 'Original Zip', 'zip-embed' ) .": $or->post_title</a></h4>";
	}
	/**
	 * Meta box for non-text files from zip
	 *
	 * @return void
	 */
	function zip_attachments_box( $post ) {
		$original = get_post_meta( get_the_ID(), 'original', true );

		$posts = get_posts( 'post_type=attachment&exclude='.$original.'&post_parent='.get_the_ID() );
		foreach( $posts as $p ) {
			echo "<h4><a href='".get_permalink($p->ID)."'>$p->post_title</a></h4>";
			if ( strpos($p->post_mime_type,'image') !== false ) {
				echo wp_get_attachment_image( $p->ID );
			} else {
				echo '<em>'. __( 'no thumbnail available', 'zip-embed' ) .'</em>';
			}
			echo '<hr />';
		}
	}
	/**
	 * Meta box for text files from zip
	 *
	 * @return void
	 */
	function zip_files_box( $post ) {
		$html = '';
		$id = get_the_ID();
		$files = get_post_meta( $id, 'file' );
		foreach ( $files as $f) {
			$html .= '<div class="zip_file">';
			$html .= '<h4>'. $f['name'] .'</h4>';
			$rows = count( explode( "\n", $f['contents'] ) );
			$rows = $rows > 100 ? 100 : $rows;
			$rows = $rows*5;
			$contents = htmlspecialchars( $f['contents'] );
			$html .= "<textarea class='widefat' style='height:{$rows}px;' readonly>{$contents}</textarea>";
			$html .= '</div>';
		}
		echo $html;
	}
	/**
	 * Shortcode handler
	 *
	 * @return string HTML for zip-post contents
	 */
	function sc_zip( $atts ) {
		extract( shortcode_atts( array(
			'id' => 0,
		), $atts ) );
		$id = intval( $id );
		if (!get_post( $id )) return;
		$r = '';
		//using WP_Query makes it easy to pass ID to shortcodes
		$zip = new WP_Query('post_type=zip&p='.$id);
		while( $zip->have_posts() ) : $zip->the_post();
			$r .= do_shortcode( get_option('zip_options', $this->default_format) );
		endwhile;
		wp_reset_query();
		//return it all nicely wrapped
		return '<div id="zip_files_group">'.$r.'</div>';
	}
	/**
	 * Redirect to our custom "new" page for Zip post type
	 *
	 * @return void
	 */
	function redirect() {
		global $typenow;
		if (isset($typenow) && $typenow == 'zip')
		wp_redirect( admin_url( 'edit.php?post_type=zip&page=zip_embed' ) );
	}
	/**
	 * Create admin pages in menu
	 *
	 * @return void
	 */
	function menu() {
		remove_submenu_page( 'edit.php?post_type=zip', 'post-new.php?post_type=zip' );
		add_submenu_page( 'edit.php?post_type=zip', __( 'Upload Zip', 'zip-embed' ), __( 'Upload Zip', 'zip-embed' ), 'upload_files', 'zip_embed', array( &$this, 'page' ) );
		add_submenu_page( 'edit.php?post_type=zip', __( 'Zip Embed Options', 'zip-embed' ), __( 'Zip Embed Options', 'zip-embed' ), 'upload_files', 'zip_embed_options', array( &$this, 'page2' ) );
	}
	/**
	 * The Admin Page - Upload
	 *
	 */
	function page() {
		echo '<div class="wrap">';
		echo '<h2>' . __( 'Upload Zip', 'zip-embed' ) . '</h2>';
		echo self::handler();
		self::form();
		echo '</div>';
	}
	/**
	 * The Admin Page - Configure
	 *
	 */
	function page2() {
		echo '<div class="wrap">';
		echo '<h2>' . __( 'Zip Embed Options', 'zip-embed' ) . '</h2>';
		echo '<form method="post" action="options.php">';
		settings_fields('zip_options_set');
		do_settings_sections( 'zip_embed' );
		echo '<input class="button-primary" type="submit" value="Save" />';
		echo '</form>';		
		echo '</div>';
	}
	/**
	 * Contextual Help
	 *
	 * Show only on Zip Config page
	 *
	 */
	function help( $contextual_help, $screen_id, $screen ) {
		if ( $screen_id == 'zip_page_zip_embed_options' ) {
			$contextual_help = '<p>'. __( 'To disable the included javascript, add the following to your theme\'s functions.php file', 'zip-embed' ) .'</p>'.
				"<pre>add_action( 'init', function() { \n" .
				"	remove_action('wp_head', 'zip_embed_scripts'); \n".
				"});</pre>";
		}
		//$contextual_help .= "<hr />$screen_id";
		return $contextual_help;
	}
	/**
	 * Add the new tab to the media pop-ip
	 *
	 * @param array $tabs Existing media tabs
	 * @return array $tabs Modified media tabs
	 */
	function create_new_tab( $tabs ) {
		$tabs['uploadzip'] = __( 'Upload/Insert Zip', 'zip-embed' );
	    return $tabs;
	}
	/**
	 * Prepare the tab in the media pop-up
	 *
	 * @return string iframe
	 */
	function media_upload_uploadzip() {
	    $errors = false;
		if ( isset( $_POST['send'] ) )
			return media_send_to_editor( $_POST['shortcode'] );

		return wp_iframe( array( &$this, 'media_uploadzip_tab_content' ), 'media', $errors );
	}
	/**
	 * Media tab content
	 *
	 */
	function media_uploadzip_tab_content( $errors ) {
		global $type;
		$message = self::handler( true );

		media_upload_header();
		$post_id = isset( $_REQUEST['post_id'] ) ? intval( $_REQUEST['post_id'] ) : 0;

		$form_action_url = admin_url("media-upload.php?type=$type&tab=uploadzip&post_id=$post_id");
		$form_action_url = apply_filters('media_upload_form_url', $form_action_url, $type );

		if ( ! empty( $message) )
			echo "<form method='post'>$message</form>";

		self::form( array('action' => $form_action_url, 'post_id' => $post_id ) );

		//list existings zips for easy embedding. Sorry, no paging (yet)
		$zips = get_posts('post_type=zip');
		if (count($zips) > 0) {
			//inline styles a-plenty. shame on me
			echo '<div style="margin: 0 10px;border-bottom: 1px solid #ccc; "><h3>'. __( 'Zips Library', 'zips-embed' ) .'</h3>';
			foreach( $zips as $z ) {
				echo "<div style='height:40px;overflow:hidden;border: 1px solid #ccc; border-bottom:0;padding: 0 10px;'>
					<p style='height:40px;float:left;'>$z->post_title</p>
					<form method='post' style='height:40px;float:right;margin-top:8px;'><input type='hidden' value='[zip id=$z->ID]' name='shortcode' /><input type='submit' name='send' value='". __( 'Embed', 'zip-embed' ) ."' /></form>
					</div>";
			}
			echo '</div>';
		}
	}
	/**
	 * Add new button to Upload/Insert icons
	 *
	 */
	function context() {
		global $post_ID;
		$button  = '<a class="thickbox" href="'. admin_url( "media-upload.php?post_id={$post_ID}&tab=uploadzip&TB_iframe=1" ).'" title="'. __( 'Upload/Insert Zip', 'zip-embed' ) .'">';
		$button .= '<img src="'. plugins_url( 'media-upload-zip.gif', __FILE__ ) .'" alt="'. __( 'Upload/Insert Zip', 'zip-embed' ) .'" />';
		$button .= '</a>';
		echo $button;
	}
	/**
	 * Move unzipped content from temp folder to media library
	 *
	 * $return being left in for debugging purposes
	 *
	 * @param string $dir Directory to loop through
	 * @param integer $parent Page ID to be used as attachment parent
	 * @param string $return String to append results to
	 * @return string Results as <li> items
	 */
	function move_from_dir( $dir, $pid, $return = '' ) {

		$dir = trailingslashit( $dir );

		$here = glob("$dir*.*" ); //get files
		$dirs = glob("$dir*", GLOB_ONLYDIR|GLOB_MARK ); //get subdirectories
		$dirs_ = glob("$dir*" ); //get all
		
		/* Here, we're figuring out if the uploaded zip
		 * is a folder. If it is, use that as the basis
		 * for naming the uploaded files
		 */
		if ( count( $dirs_ ) == 1 && count( $dirs ) == 1 ) {
			$current_dir = str_replace( $this->tmp_dir, '', untrailingslashit( $dirs[0] ) );
			if ( strpos( $current_dir, '/' ) === false )
				$this->root_dir = $dirs[0];
		}

		//start with subs, less confusing
		foreach ($dirs as $k => $sdir) {
			$return .= self::move_from_dir( $sdir, $pid, $return );
		}
		
		if ( ! isset( $this->root_dir ) ) $this->root_dir = $this->tmp_dir;

		//loop through files and add them to the media library
		foreach ( $here as $img ) {
			$img_name = str_replace( $this->root_dir, '', $img );
			$title = explode( '.', $img_name );
			array_pop( $title );
			$title = implode( '.', $title );

			//ignore certain filetypes
			$skip = array( '.mo', '.po', '.pot' );
			foreach ( $skip as $ext ) {
				if ( is_file( $img ) && strpos( $img, $ext ) !== false ) {
					if (unlink($img))
						$return .= "<li>skipped and deleted: $img_name</li>";
					else
						$return .= "<li>skipped: $img_name</li>";
				}
			}

			//get text files, attach as post-meta
			$allow = array( '.php', '.js', '.css', '.txt', '.html', '.htm' );
			foreach ( $allow as $ext ) {
				if ( strpos( $img, $ext ) !== false ) {
					$data = get_plugin_data( $img );
					if (isset($data['Name'])) wp_update_post( array( 'ID' => $pid, 'post_title' => $data['Name'], 'post_name' => sanitize_title( $data['Name'] ) ) );
					$contents = array( 'name' => $img_name, 'contents' => file_get_contents( $img ) );
					add_post_meta( $pid, 'file', $contents );
					unlink( $img );
				}
			}

			//make sure file exists, since it may have been removed above
			if ( is_file( $img ) ) {
				$upl = wp_upload_dir();

				$img_url = str_replace( $upl['basedir'], $upl['baseurl'], $img );
				$file = array( 'file' => $img, 'tmp_name' => $img, 'name' => $img_name );
				$img_id = media_handle_sideload( $file, $pid, $title );
				if (!is_wp_error( $img_id ) ) {
					$return .= "<li>($img_id) ". sprintf( __( '%s uploaded', 'zip-embed' ), $img_name ) ."</li>";
				} else {
					$return .= "<li style='color:#a00;'>". sprintf( __( '%s could not be uploaded.', 'zip-embed' ), "$img_name ($dir)" );
					if ( is_file( $img ) && unlink( $img ) )
						$return .= __( ' It has been deleted.', 'zip-embed' );
					$return .= "</li>";
				}
			}
			$return .= "<input type='hidden' name='shortcode' value='[zip id=$pid]' />";

		}

		//We need check for hidden files and remove them so that the directory can be deleted
		foreach( glob("$dir.*") as $k => $hidden ) {
 			if ( is_file( $hidden ) )
 				unlink( $hidden );
		}

		//delete any folders that were unzipped
		//make sure we don't delete the plugin's temp folder
		if ( $dir != $this->tmp_dir )
			rmdir( $dir );

		return $return;
	}
	/**
	 * Handle the initial zip upload
	 *
	 * @param bool $media_tab Whether or not this function is being run in the media tab
	 * @return string HTML Results or Error message
	 */
	function handler( $media_tab = false ) {

		if ( isset( $_FILES[ 'upload-zip-archive' ][ 'name' ] ) && ! empty( $_FILES[ 'upload-zip-archive' ][ 'name' ] ) ) {

			$upl_id = media_handle_upload( 'upload-zip-archive', 0, array(), array('mimes' => array('zip' => 'application/zip'), 'ext' => array('zip'), 'type' => true, 'action' => 'wp_handle_upload') );
			if ( is_wp_error( $upl_id ) ) {
				return '<div class="error"><p>'. $upl_id->errors['upload_error']['0'] .'</p></div>';
			}

			$upl = wp_upload_dir();
			$file = str_replace( $upl['baseurl'], $upl['basedir'], wp_get_attachment_url( $upl_id ) );

			/*
				If the zipped file cannot be unzipped
				try again after uncommenting the lines
				below marked 1, 2, and 3
			*/
			/*1*/	function __return_direct() { return 'direct'; }
			/*2*/	add_filter( 'filesystem_method', '__return_direct' );
			if ( ! WP_Filesystem() ) {
				return '<div class="error"><p>No can do. Sorry.</p></div>';
			}
			/*3*/	remove_filter( 'filesystem_method', '__return_direct' );

			$return = '<div class="updated">';
			$upl_name = get_the_title( $upl_id );
			$unzip = unzip_file( $file, $this->tmp_dir );
			if ( ! is_wp_error( $unzip ) ) {
				$pid = wp_insert_post( array( 'post_title'=> $upl_name, 'post_type'=> 'zip', 'post_status' => 'publish' ) );
				self::move_from_dir( $this->tmp_dir, $pid );
				wp_update_post( array( 'ID' => $upl_id, 'post_parent' => $pid ) );
				add_post_meta( $pid, 'original', $upl_id );
			} else {
				//lazy errors. writable check happens earlier, shouldn't get this far.
				wp_delete_attachment( $upl_id );
				$return .= '<p>'. sprintf( __( '%s could not be extracted and has been deleted', 'zip-embed' ), $upl_name ) .'</p>';
				$return .= '<pre>'. print_r( $unzip, true) .'</pre>';
			}

			if ( isset( $pid ) && ! is_wp_error( $pid ) ) {
				$button = $media_tab ? '<input type="submit" class="alignright button-primary" style="margin-top: -3px;" value="'. __( 'Embed', 'zip-embed' ) .'" name="send" />' : '';
				$return .= '<p>'. __( 'All done!', 'zip-embed' ) .' <a href="'. get_edit_post_link( $pid ) .'">' . __( 'View Zip post &rarr;', 'zip-embed' ) . '</a>'. $button .'</p>';
				$return .= "<input type='hidden' name='shortcode' value='[zip id=$pid]' />";
			}

			$return .= '</div>';

			return $return;
		}

	}
	/**
	 * The upload form
	 *
	 * @param array $args 'action' URL for form action, 'post_id' ID for preset parent ID
	 */
	function form( $args = array() ) {
		$action = '';
		$tab = false;
		if ( count( $args ) > 0) {
			$tab = true;
			extract( $args );
		}

		//This code taken from: wp-admin/includes/media.php
		$upload_size_unit = $max_upload_size =  wp_max_upload_size();
		$sizes = array( 'KB', 'MB', 'GB' );
		for ( $u = -1; $upload_size_unit > 1024 && $u < count( $sizes ) - 1; $u++ )
			$upload_size_unit /= 1024;
		if ( $u < 0 ) {
			$upload_size_unit = 0;
			$u = 0;
		} else {
			$upload_size_unit = (int) $upload_size_unit;
		}

		//warn if the temp directory isn't writable
		if ( ! $this->tmp_dir || ! is_writable( $this->tmp_dir ) ) {

			echo '<div class="error"><p>';
			echo sprintf( __( 'Please make %s writable.', 'zip-embed' ), "<code>$this->tmp_dir</code>" );
			echo '</p></div>';

		} else {

			echo '<form action="'. $action .'" method="post" enctype="multipart/form-data" class="media-upload-form type-form validate html-uploader" id="file-form">';
			if ( $tab ) echo '<h3 class="media-title">'. __( 'Upload Zip', 'zip-embed' ) .'</h3>';
			echo '<p><input type="file" name="upload-zip-archive" id="upload-zip-archive" size="50" />
			<input type="submit" class="button" value="' . __( 'Upload Zip', 'zip-embed' ) . '"/>
			<input type="hidden" name="submitted-upload-media" />
			<input type="hidden" name="action" value="wp_handle_upload" /></p>';

			echo '<p>' . sprintf( __( 'Maximum upload file size: %d%s' ), $upload_size_unit, $sizes[ $u ] ) .'</p>';

			echo '</form>';

		}
	}
	/**
	 * Register our options
	 *
	 * A little overkill since this plugin has only one option, but in case more are added...
	 *
	 * @return void
	 */
	function register_fields( ) {
		register_setting( 'zip_options_set', 'zip_options', array( &$this, 'sanitize' ) );
		add_settings_section( 'zip_options_main', __( 'Zip Embed', 'zip-embed' ), array( &$this, 'zip_options_' ), 'zip_embed' );
		add_settings_field( 'zip_options_id', __( 'Display Format', 'zip-embed' ), array( &$this, 'fields' ), 'zip_embed', 'zip_options_main' ); 
	}
	/**
	 * Heading for our options section
	 *
	 * @return void
	 */
	function zip_options_() {
		//echo '';
	}
	/**
	 * Field HTML
	 *
	 * @return void
	 */
	function fields( ) {
		$value = get_option( 'zip_options', $this->default_format );
		echo '<textarea class="large-text code" rows="5" cols="50" name="zip_options">' . htmlspecialchars( $value ) . '</textarea>';
		echo __( 'Allowed shortcodes and default parameters', 'zip-embed' ) .'<ul>
			<li>[zip_title link=1 pre="Original Zip: " post=""]</li>
			<li>[zip_files]
				<ul style="margin-left:15px;">
				<li>[zip_file_name] <em>'. __( 'Must be used inside [zip_files]', 'zip-embed' ) .'</em></li>
				<li>[zip_file_contents] <em>'. __( 'Must be used inside [zip_files]', 'zip-embed' ) .'</em></li>
				</ul>
				[/zip_files]
			</li>
			<li>[zip_gallery heading="Other Files"]</li>
			</ul>';
	}
	/**
	 * Validate saved option
	 *
	 * @return sanitized optino
	 */
	function sanitize( $input ) {
		$input = wp_filter_post_kses( $input );
		$input = stripslashes( $input );
		return $input;
	}
	/**
	 * Allow 'class' attribute in textarea
	 *
	 * @return array Modified list of allowed tags
	 */
	function allowed_tags() {
		global $allowedposttags;
		$allowedposttags['textarea']['class'] = array();
	}
	/**
	 * Shortcode handler. Get zip post-title
	 *
	 * @return string HTML link to zip post
	 */
	function sc_zip_title( $atts ) {
		extract( shortcode_atts( array(
			'id' => get_the_ID(),
			'link' => 1,
			'pre' => 'Original Zip: ',
			'post' => '',
		), $atts ) );
		$original = get_post_meta( $id, 'original', true );
		$url = wp_get_attachment_url( $original );

		$title = get_the_title( $id );
		
		$r = "{$pre}{$title}{$post}";
		if ($link)
			$r = "<a href='$url'>$r</a>";
		return $r;
	}
	/**
	 * Shortcode handler. Get text files from zip-post
	 *
	 * @return string HTML of zip's text files
	 */
	function sc_zip_files( $atts, $content ) {
		extract( shortcode_atts( array(
			'id' => get_the_ID()
		), $atts ) );

		$r = '';
		$files = get_post_meta( $id, 'file' );
		foreach ( $files as $f) {
 			$contents = htmlspecialchars( $f['contents'] );
 			$x = $content;
			$x = str_replace('[zip_file_name]', $f['name'], $x );
			$x = str_replace('[zip_file_contents]', $contents, $x );
			$r .= $x;
		}
		return $r;
	}
/*
	function sc_zip_file_name( $atts ) {
		extract( shortcode_atts( array(
			'id' => get_the_ID()
		), $atts ) );

		$r = '';
		return $r;
	}
	function sc_zip_file_contents( $atts ) {
		extract( shortcode_atts( array(
			'id' => get_the_ID()
		), $atts ) );

		$r = '';
		return $r;
	}
*/
	/**
	 * Shortcode handler. Get zip post-title
	 *
	 * @return string HTML gallery for zip-post attachments
	 */
	function sc_zip_gallery( $atts ) {
		extract( shortcode_atts( array(
			'id' => get_the_ID(),
			'heading' => 'Other Files'
		), $atts ) );
		$r = '';

		$original = get_post_meta( $id, 'original', true );
		$gallery = do_shortcode( '[gallery id="'. $id .'" exlude="'. $original .'"]' );
		if ( ! empty ( $gallery ) )
			$r = "<h3>$heading</h3>$gallery";

		return $r;
	}

}//end class
new Zip_Embed( );


add_action('wp_head', 'zip_embed_scripts');
function zip_embed_scripts() {
	wp_enqueue_script('jquery');
	add_action('wp_footer', 'zip_embed_foot');
}
function zip_embed_foot() {
	?>
	<script type="text/javascript">
	jQuery(document).ready( function($) {
		$('.zip_file textarea').height( 50 );
		$('.zip_file h3').append( '<span style="float:right;">&darr;</span>');
		$('.zip_file h3').bind( 'click', function () {
			ta = $(this).siblings( 'textarea');
			if (ta.height() == 50 ) {
				$(this).children('span').css('-webkit-transform', 'rotate(180deg)');
				ta.animate( {
					'height': 500
				});
			} else {
				$(this).children('span').css('-webkit-transform', 'rotate(0deg)');
				ta.animate( {
					'height': 50
				});
			}
		})
	});
	</script>
	<?php
}
