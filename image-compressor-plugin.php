<?php
/*
Plugin Name: Image Compressor
Description: A plugin to compress JPEG images on upload and retrospectively based on specified settings.
Version: 1.1
Author: Your Name
*/

// Hook for adding admin menus
add_action('admin_menu', 'image_compressor_menu');

// Register settings
add_action('admin_init', 'image_compressor_register_settings');

// Add compression hook on upload
add_filter('wp_handle_upload', 'image_compressor_compress_image', 10, 2);

// Override WordPress's JPEG quality for thumbnails and resized images
add_filter('jpeg_quality', 'image_compressor_jpeg_quality');

// Create the admin menu page
function image_compressor_menu()
{
	add_options_page(
		'Image Compressor Settings',
		'Image Compressor',
		'manage_options',
		'image-compressor',
		'image_compressor_settings_page'
	);
}

// Register settings
function image_compressor_register_settings()
{
	register_setting('image_compressor_settings', 'jpeg_quality');
	register_setting('image_compressor_settings', 'keep_originals');
}

// The settings page
function image_compressor_settings_page()
{
?>
	<div class="wrap">
		<h1>Image Compressor Settings</h1>
		<form method="post" action="options.php">
			<?php settings_fields('image_compressor_settings'); ?>
			<?php do_settings_sections('image_compressor_settings'); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">JPEG Quality</th>
					<td>
						<input type="number" name="jpeg_quality" value="<?php echo esc_attr(get_option('jpeg_quality', 80)); ?>" min="1" max="100" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Keep Originals</th>
					<td>
						<input type="checkbox" name="keep_originals" value="1" <?php checked(get_option('keep_originals'), 1); ?> />
						<label for="keep_originals">Keep the original images</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2>Retrospective Compression</h2>
		<form method="post" action="">
			<input type="submit" name="image_compressor_compress_all" value="Compress All Images" class="button button-primary" />
		</form>

		<p><em>Note: If "Keep Originals" is enabled, the original images are saved with a "-original" suffix in the uploads directory and can be accessed via FTP.</em></p>
	</div>
<?php

	// Handle retrospective compression
	if (isset($_POST['image_compressor_compress_all'])) {
		image_compressor_compress_all_images();
	}
}

// Compress images upon upload
function image_compressor_compress_image($file)
{
	$quality = get_option('jpeg_quality', 80);
	$keep_original = get_option('keep_originals', 0);

	// Check if the uploaded file is a JPEG
	if ($file['type'] === 'image/jpeg') {
		$image_path = $file['file'];
		$image = imagecreatefromjpeg($image_path);

		if ($keep_original) {
			// Save original image with a new name
			$original_path = pathinfo($image_path, PATHINFO_DIRNAME) . '/' . pathinfo($image_path, PATHINFO_FILENAME) . '-original.' . pathinfo($image_path, PATHINFO_EXTENSION);
			copy($image_path, $original_path);
		}

		// Compress the JPEG
		imagejpeg($image, $image_path, $quality);
		imagedestroy($image);
	}

	return $file;
}

// Override WordPress default JPEG quality for resized images
function image_compressor_jpeg_quality($quality)
{
	return get_option('jpeg_quality', 80);  // Use the JPEG quality set in plugin options
}

// Compress all images including thumbnails
function image_compressor_compress_all_images()
{
	global $wpdb;

	$quality = get_option('jpeg_quality', 80);
	$keep_original = get_option('keep_originals', 0);

	// Get all JPEG images from the media library
	$attachments = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/jpeg'");

	foreach ($attachments as $attachment) {
		// Get the paths of the original image and all its resized versions
		$image_path = get_attached_file($attachment->ID);
		image_compressor_compress_single_image($image_path, $quality, $keep_original);

		// Compress all image sizes (thumbnails, medium, etc.)
		$metadata = wp_get_attachment_metadata($attachment->ID);
		if (isset($metadata['sizes'])) {
			foreach ($metadata['sizes'] as $size) {
				$resized_image_path = pathinfo($image_path, PATHINFO_DIRNAME) . '/' . $size['file'];
				image_compressor_compress_single_image($resized_image_path, $quality, false);
			}
		}
	}

	echo '<div class="notice notice-success"><p>All images and their thumbnails have been compressed.</p></div>';
}

// Function to compress a single image file
function image_compressor_compress_single_image($image_path, $quality, $keep_original)
{
	if (file_exists($image_path)) {
		$image = imagecreatefromjpeg($image_path);

		if ($keep_original) {
			// Save original image with a new name
			$original_path = pathinfo($image_path, PATHINFO_DIRNAME) . '/' . pathinfo($image_path, PATHINFO_FILENAME) . '-original.' . pathinfo($image_path, PATHINFO_EXTENSION);
			if (!file_exists($original_path)) {
				copy($image_path, $original_path);
			}
		}

		// Compress the JPEG
		imagejpeg($image, $image_path, $quality);
		imagedestroy($image);
	}
}
