<?php
/*
Addon Name: Import enclosure images
Description: Experimental rss enclosure importer - attaches images to posts and adds a title, caption and description, and sets it as the related post's featured image.
Author: David Smith (updated by Daniel J. Schneider)
Author URI: http://www.ballsmania.com
*/

/* -- after the example of a function from wp-admin/import/wordpress.php:~666 -- */

function fetch_remote_file($post, $url) {
		$url2= str_replace('&amp;', '&', str_replace('https://', 'http://', $url));
 
		preg_match('/[a-z0-9;=_%\Q?&.-+[]\E]+\.(jpg|jpeg|gif|png)/i', $url2, $pu);
		$file_name= str_replace('%25', '-', $pu[0]);
		$file_name= preg_replace('/[;=%\Q?&-+\E]+/i', '-', $file_name);
		$file_name= (strlen($file_name)>255)? substr($file_name, 180): $file_name;

		$upload = wp_upload_bits( $file_name, 0, '', $post['post_date']);

		if ( $upload['error'] ) {
			echo $upload['error'];
			return new WP_Error( 'upload_dir_error', $upload['error'] );
		}

		$headers = wp_get_http($url2, $upload['file']);

		if ( !$headers ) {
			@unlink($upload['file']);
			return new WP_Error( 'import_file_error', __('Remote server did not respond', 'rigr') );
		}

		if ( $headers['response'] != '200' ) {
			@unlink($upload['file']);
			return new WP_Error( 'import_file_error', sprintf(__('Remote server says: %1$d %2$s', 'rigr'), $headers['response'], get_status_header_desc($headers['response']) ) );
		}
		elseif ( isset($headers['content-length']) && filesize($upload['file']) != $headers['content-length'] ) {
			@unlink($upload['file']);
			return new WP_Error( 'import_file_error', __('Remote file is incorrect size', 'rigr') );
		}

		$upload['filesize'] = $file_size;
		$upload['content-type'] = $headers['content-type'];
		return $upload;
}

class A_EnclosureCacheAddon {

	function __construct() {

		add_action( 'autoblog_post_post_insert', array(&$this, 'process_enclosure'), 10, 3 );
	}

	function A_EnclosureCacheAddon() {
		$this->__construct();
	}

	function process_enclosure( $post_ID, $ablog, $item ) {

		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		// get first enclosure item
     	if ($enclosure = $item->get_enclosure(0,1)) {
     		//var_dump($enclosure);
	     	$post_title = html_entity_decode( $item->get_title(), ENT_QUOTES, 'UTF-8' );
			$image = $enclosure->get_link();
			$fetched_file = fetch_remote_file($post_ID, $image);
			if (!is_object($fetched_file)) {
				$file_array = array(
					'name'		=> basename($fetched_file['file']),
					'type' 		=> $fetched_file['content-type'],
					'tmp_name'	=> $fetched_file['file'],
					'error'		=> 0,
					'size'		=> intval($fetched_file['filesize'])
					);
				//var_dump($enclosure->get_description());
				$post_data['post_excerpt'] = html_entity_decode( $enclosure->get_description() );
				$post_data['post_content'] = $post_data['post_excerpt'];
			        update_post_meta( $post_ID , 'enclosure-2', $enclosure->get_link() );
				$img = media_handle_sideload($file_array, $post_ID, $post_title, $post_data);

		        // set last image added as featured image
				set_post_thumbnail($post_ID, $img);

				// Returning the $post_ID even though it's an action and we really don't need to
				return $post_ID;
			}
		}
	}
}

$aenclosurecacheaddon = new A_EnclosureCacheAddon();

?>
