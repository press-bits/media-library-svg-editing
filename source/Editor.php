<?php
/**
 * Scalable Vector Graphics Editor.
 *
 * @package MediaLibrary\ScalableVectorGraphics
 */

namespace PressBits\MediaLibrary\ScalableVectorGraphics;

use JangoBrick\SVG\SVGImage;

use WP_Image_Editor;
use WP_Error;

/**
 * Scalable Vector Graphics Editor class.
 *
 * @since 0.1.0
 */
class Editor extends WP_Image_Editor {

	/**
	 * The loaded SVG image.
	 *
	 * @since 0.1.0
	 * @var SVGImage
	 */
	protected $svg_image;

	/**
	 * The path of the SVG file.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $file;


	/**
	 * Whether the editor can be loaded.
	 *
	 * @since 0.1.0
	 * @param array $args Argument array is ignored.
	 * @return bool
	 */
	public static function test( $args = array() ) {
		return true;
	}

	/**
	 * Checks to see if editor supports the mime-type specified.
	 *
	 * @since 0.1.0
	 * @param string $mime_type The mime type to check.
	 * @return bool
	 */
	public static function supports_mime_type( $mime_type ) {
		return MIMEType::SVG_IMAGE === $mime_type;
	}

	/**
	 * Instantiate an SVG image editor;
	 *
	 * @since 0.1.0
	 * @param string $file The path of the SVG file to edit.
	 */
	public function __construct( $file ) {
		$this->file = $file;
	}

	/**
	 * Load an SVG image.
	 *
	 * @since 0.1.0
	 * @return bool|WP_Error
	 */
	public function load() {
		if ( $this->svg_image ) {
			return true;
		}

		try {
			$this->svg_image = SVGImage::fromFile( $this->file );
		} catch ( Exception $e ) {
			return new WP_Error( 'svg_editor_load_error', 'Failed to load SVG file', compact( 'path', 'e' ) );
		}

		$this->update_size();

		return $this->svg_image instanceof SVGImage;
	}

	/**
	 * Resize the SVG image.
	 *
	 * @since 0.1.0
	 * @param int|null $max_w New width in pixels.
	 * @param int|null $max_h New height in pixels.
	 * @param bool     $crop Whether to crop.
	 * @return bool
	 */
	public function resize( $max_w, $max_h, $crop = false ) {

		if ( $crop ) {
			return $this->resize_crop( $max_w, $max_h );
		}

		$width = $max_w ?: $this->size['width'] * ( $max_h / $this->size['height'] );
		$height = $max_h ?: $this->size['height'] * ( $max_w / $this->size['width'] );

		$this->svg_image->getDocument()->setWidth( $width );
		$this->svg_image->getDocument()->setHeight( $height );
		if ( ! $this->svg_image->getDocument()->getAttribute( 'viewBox' ) ) {
			$this->set_viewbox( [ 0, 0, $this->size['width'], $this->size['height'] ] );
		}

		return $this->update_size( $width, $height );
	}

	/**
	 * Crop SVG image.
	 *
	 * @since 0.1.0
	 *
	 * @param int  $src_x The start x position to crop from.
	 * @param int  $src_y The start y position to crop from.
	 * @param int  $src_w The width to crop.
	 * @param int  $src_h The height to crop.
	 * @param int  $dst_w Optional. The destination width.
	 * @param int  $dst_h Optional. The destination height.
	 * @param bool $src_abs Optional. If the source crop points are absolute.
	 * @return bool
	 */
	public function crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) {

		if ( $src_abs ) {
			$src_w -= $src_x;
			$src_h -= $src_y;
		}

		$dst_w = $dst_w ?: $src_w;
		$dst_h = $dst_h ?: $src_h;

		$viewbox = $this->get_viewbox();

		$width_ratio = (float) $viewbox['width'] / $this->size['width'];
		$height_ratio = (float) $viewbox['height'] / $this->size['height'];

		// Map coordinates to viewbox.
		$src_x *= $width_ratio;
		$src_w *= $width_ratio;
		$src_y *= $height_ratio;
		$src_h *= $height_ratio;

		// Calculate cropped viewbox.
		$viewbox['x'] += $src_x;
		$viewbox['y'] += $src_y;
		$viewbox['width'] = $src_w;
		$viewbox['height'] = $src_h;

		$this->set_viewbox( $viewbox );

		$this->resize( $dst_w, $dst_h, $crop = false );

		return true;
	}

	/**
	 * Rotate SVG image.
	 *
	 * Not yet implemented.
	 *
	 * @since 0.1.0
	 * @param float $angle The angle to rotate in degrees.
	 * @return bool|WP_Error
	 */
	public function rotate( $angle ) {
		return new WP_Error( 'image_rotate_error', 'Image rotation not yet supported for SVG.', $this->file );
	}

	/**
	 * Rotate SVG image.
	 *
	 * Not yet implemented.
	 *
	 * @since 0.1.0
	 * @param bool $horz Flip along horizontal axis.
	 * @param bool $vert Flip along verticle axis.
	 * @return bool|WP_Error
	 */
	public function flip( $horz, $vert ) {
		return new WP_Error( 'image_flip_error', 'Image flipping not yet supported for SVG.', $this->file );
	}

	/**
	 * Saves current in-memory image to file.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param string $filename The destination file path.
	 * @param string $mime_type 'image/svg+xml'.
	 * @return array|WP_Error {'path'=>string, 'file'=>string, 'width'=>int, 'height'=>int, 'mime-type'=>string}
	 */
	public function save( $filename = null, $mime_type = null ) {

		if ( ! $filename ) {
			$filename = $this->generate_filename( null, null, 'svg' );
		}

		$mime_type = $mime_type ?: MIMEType::SVG_IMAGE;

		if ( MIMEType::SVG_IMAGE !== $mime_type ) {
			return new WP_Error( 'image_save_error', 'Unable to save unsupported MIME type.', compact( 'filename', 'mime_type' ) );
		}

		if ( ! isset( $GLOBALS['wp_filesystem'] ) ) {
			WP_Filesystem( false, dirname( $filename ), true );
		}

		$fs = $GLOBALS['wp_filesystem'];

		if ( ! $fs ) {
			return new WP_Error( 'image_save_error', 'Unable to access the filesystem.', $filename );
		}

		if ( ! $fs->is_dir( dirname( $filename ) ) and ! $fs->mkdir( dirname( $filename ) ) ) {
			return new WP_Error( 'image_save_error', 'Unable to access the filesystem.', $filename );
		}

		if ( false === $fs->put_contents( $filename, $this->svg_image->toXMLString(), 0000666 ) ) {
			return new WP_Error( 'image_save_error', 'Unable to access the filesystem.', $filename );
		}

		$this->file = $filename;

		/**
		 * Filter the name of the saved image file.
		 *
		 * @since 2.6.0
		 *
		 * @param string $filename Name of the file.
		 */
		return array(
			'path' => $filename,
			'file' => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
			'width' => $this->size['width'],
			'height' => $this->size['height'],
			'mime-type' => $mime_type,
		);
	}

	/**
	 * Resize multiple images from a single source.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param array $sizes {
	 *     An array of image size arrays. Default sizes are 'small', 'medium', 'large'.
	 *
	 *     Either a height or width must be provided.
	 *     If one of the two is set to null, the resize will
	 *     maintain aspect ratio according to the provided dimension.
	 *
	 * @type array $size {
	 * @type int  ['width']  Optional. Image width.
	 * @type int  ['height'] Optional. Image height.
	 * @type bool ['crop']   Optional. Whether to crop the image. Default false.
	 *     }
	 * }
	 * @return array An array of resized images' metadata by size.
	 */
	public function multi_resize( $sizes ) {
		$metadata = [];
		foreach ( $sizes as $size => $size_data ) {
			$metadata[ $size ] = $this->resize_and_save( $size_data );
		}
		return array_filter( $metadata );
	}

	/**
	 * Return a stream of current image.
	 *
	 * @since 0.1.0
	 *
	 * @param string $mime_type Optional MIME type.
	 */
	public function stream( $mime_type = null ) {
		$mime_type = $mime_type ?: MIMEType::SVG_IMAGE;
		header( "Content-Type: $mime_type" );
		echo $this->svg_image->toXMLString();
	}

	/**
	 * Resize and crop the SVG image.
	 *
	 * @since 0.1.0
	 * @param int|null $max_w New width in pixels.
	 * @param int|null $max_h New height in pixels.
	 * @return bool
	 */
	protected function resize_crop( $max_w, $max_h ) {
		$dimensions = image_resize_dimensions( $this->size['width'], $this->size['height'], $max_w, $max_h, true );

		if ( ! $dimensions ) {
			// No change.
			return true;
		}

		list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dimensions;

		return $this->crop( $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h, $src_abs = false );
	}

	/**
	 * Resize and save a single image.
	 *
	 * @since 0.1.0
	 * @param array $size_data Array with keys 'width', height', 'crop'.
	 * @return array|null
	 */
	protected function resize_and_save( $size_data ) {
		if ( ! isset( $size_data['width'] ) && ! isset( $size_data['height'] ) ) {
			return null;
		}
		if ( ! isset( $size_data['width'] ) ) {
			$size_data['width'] = null;
		}
		if ( ! isset( $size_data['height'] ) ) {
			$size_data['height'] = null;
		}
		if ( ! isset( $size_data['crop'] ) ) {
			$size_data['crop'] = false;
		}

		$restore_size = $this->size;
		if ( ! $this->resize( $size_data['width'], $size_data['height'], $size_data['crop'] ) ) {
			return null;
		}

		$restore_file = $this->file;

		$file_data = $this->save();

		$this->file = $restore_file;
		$this->size = $restore_size;

		if ( ! $file_data or is_wp_error( $file_data ) ) {
			return null;
		}

		unset( $file_data['path'] );
		return $file_data;
	}

	/**
	 * Parse the current viewBox attribute.
	 *
	 * @since 0.1.0
	 * @return array Keys 'x', 'y', 'width', 'height'.
	 */
	protected function get_viewbox() {
		$dimensions = explode( ' ', $this->svg_image->getDocument()->getAttribute( 'viewBox' ), 4 );
		list( $x, $y, $width, $height ) = array_map( 'intval', array_pad( $dimensions, 4, null ) );
		$width = $width ?: $this->size['width'];
		$height = $height ?: $this->size['height'];
		return compact( 'x', 'y', 'width', 'height' );
	}

	/**
	 * Set the current viewBox attribute.
	 *
	 * @since 0.1.0
	 * @param array $viewbox Keys 'x', 'y', 'width', 'height' in that order.
	 */
	protected function set_viewbox( $viewbox ) {
		$this->svg_image->getDocument()->setAttribute( 'viewBox', implode( ' ', $viewbox ) );
	}

	/**
	 * Set current image size.
	 *
	 * @since 0.1.0
	 * @param bool|int $width Optional width in pixels.
	 * @param bool|int $height Optional height in pixels.
	 * @return bool
	 */
	protected function update_size( $width = false, $height = false ) {

		if ( ! $width ) {
			$width = intval( $this->svg_image->getDocument()->getWidth() );
		}

		if ( ! $height ) {
			$height = intval( $this->svg_image->getDocument()->getHeight() );
		}

		return parent::update_size( $width, $height );
	}
}
