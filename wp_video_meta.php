<?php

/**
 * WPVideoMeta
 * Base for using video meta in WP
 *
 * @package default
 * @author Dan deHavilland
 */
if (!class_exists('WPVideoMeta')):

require(dirname(__FILE__).'/getid3/getid3.php');

class WPVideoMeta {
	
	function __construct() {
		add_filter('wp_update_attachment_metadata', array(&$this, 'update_video_meta'), 10, 2);
	}
	
	function update_video_meta($data, $attachment_id) {
		
		$attachment = get_post($attachment_id);
		$upload_dir_info = wp_upload_dir();
		$filename = $upload_dir_info['basedir']."/".get_post_meta($attachment_id, '_wp_attached_file', true);
		$getid3 = new getID3;
		
		if (strpos($attachment->post_mime_type, "video") !== false) {
			
			// Try to determine video sizes etc.
			try {
				$info = $getid3->analyze($filename);
				if (isset($info['video'])) {
					// create video meta
					$data = array(
						'width' => $info['video']['resolution_x'],
						'height' => $info['video']['resolution_y']
					);
				}
				
				
			} catch (Exception $exception) {
				error_log("Error determining video resolution.");
			}
			
			try {
				if (!empty($info['quicktime'])) {
					$extension = substr($info['filenamepath'], -3, 3);
					
					// ffmpeg for mp4/m4v
					if (in_array($extension, array("mp4","m4v"))) {
						$original_file = str_replace(".$extension", ".original.$extension", $info['filenamepath']);
						$command = "cp {$info['filenamepath']} {$original_file} && ffmpeg -y -i {$original_file} -acodec copy -vcodec copy {$info['filenamepath']}";
						$output = exec($command . ' 2>&1', $output=array(), $return);
						error_log($output);
					}
					
					// qt faststart for mov/mp4/m4v
					if (in_array($extension, array("mov","mp4","m4v"))) {
						$tmp_file = str_replace(".$extension", ".tmp.$extension", $info['filenamepath']);
						$command = "cp {$info['filenamepath']} {$tmp_file} && qt-faststart {$tmp_file} {$info['filenamepath']} && rm {$tmp_file}";
						$output = exec($command . ' 2>&1', $output=array(), $return);
						error_log($output);
					}
				}
			} catch (Exception $exception) {
				error_log("Could not run qt-faststart/ffmpeg. Are they installed?");
			}
		}
		
		return $data;
	}
}
new WPVideoMeta;

endif;

?>