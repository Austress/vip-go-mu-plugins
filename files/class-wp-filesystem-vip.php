<?php

namespace Automattic\VIP\Files;

require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );

require_once( __DIR__ . '/class-wp-filesystem-uploads.php' );
require_once( __DIR__ . '/class-api-client.php' );

use WP_Error;
use WP_Filesystem_Direct;

class WP_Filesystem_VIP extends \WP_Filesystem_Base {

	private $api;
	private $direct;

	public function __construct( Api_Client $api_client ) {
		$this->method = 'vip';
		$this->errors = new WP_Error();

		$this->api = new WP_Filesystem_Uploads( $api_client );
		$this->direct = new WP_Filesystem_Direct( null );
	}

	private function get_transport_for_path( $filename ) {
		if ( $this->is_uploads_path( $filename ) ) {
			return $this->api;
		} elseif ( $this->is_tmp_path( $filename ) ) {
			return $this->direct;
		}

		// This is the usual way to do errors, we'll use it but also trigger a PHP E_USER_ERROR to ensure users see this.
		$this->errors->add( 'filepath_not_supported', 'No appropriate transport found for filename: ' . $filename );

		// TODO: Do we want to just trigger_error in some circumstances? maybe only when environement != production?
		trigger_error( 'Files can only be modified either in the temporary folder or in the uploads folder. Please see our documentation here:', E_USER_ERROR );
	}

	private function is_tmp_path( $file_path ) {
		$tmp_dir = get_temp_dir();
		return 0 === strpos( $file_path, $tmp_dir );
	}

	private function is_uploads_path( $file_path ) {
		$upload_dir = wp_get_upload_dir();
		$upload_base = $upload_dir['basedir'];

		return 0 === strpos( $file_path, $upload_base );
	}

	/**
	 * Reads entire file into a string
	 *
	 * @param string $file Name of the file to read.
	 * @return string|bool The function returns the read data or false on failure.
	 */
	public function get_contents( $file ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->get_contents( $file );
	}

	/**
	 * Reads entire file into an array
	 *
	 * @param string $file Path to the file.
	 * @return array|bool the file contents in an array or false on failure.
	 */
	public function get_contents_array( $file ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->get_contents_array( $file );
	}

	/**
	 * Write a string to a file
	 *
	 * @param string $file     Remote path to the file where to write the data.
	 * @param string $contents The data to write.
	 * @param int    $mode     Optional. The file permissions as octal number, usually 0644.
	 *                         Default false. - Unimplemented
	 * @return bool False upon failure, true otherwise.
	 */
	public function put_contents( $file, $contents, $mode = false ) {
		$transport = $this->get_transport_for_path( $file );
		$transport->put_contents( $file, $contents, $mode );
		return true;
	}

	/**
	 * @param string $source
	 * @param string $destination
	 * @param bool   $overwrite
	 * @param int    $mode - Unimplemented
	 * @return bool
	 */
	public function copy( $source, $destination, $overwrite = false, $mode = false ) {
		$source_transport = $this->get_transport_for_path( $source );
		$destination_transport = $this->get_transport_for_path( $destination );

		if ( ! $overwrite && $destination_transport->exists( $destination ) ) {
			return false;
		}

		$file_content = $source_transport->get_contents( $source );
		$destination_transport->put_contents( $destination, $file_content );
	}

	/**
	 * @param string $source
	 * @param string $destination
	 * @param bool $overwrite
	 * @return bool
	 */
	public function move( $source, $destination, $overwrite = false ) {
		$copy_results = $this->copy( $source, $destination, $overwrite );
		if ( false === $copy_results ) {
			return false;
		}

		$this->delete( $source );

		return true; // TODO: What if delete fails?
	}

	/**
	 * @param string $file
	 * @param bool $recursive - Unimplemented
	 * @param string $type - Unimplemented
	 * @return bool
	 */
	public function delete( $file, $recursive = false, $type = false ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->delete( $file );
	}

	/**
	 * @param string $file
	 * @return int
	 */
	public function size( $file ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->size( $file );
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function exists( $file ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->exists( $file );
	}
	/**
	 * @param string $file
	 * @return bool
	 */
	public function is_file( $file ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->is_file( $file );
	}
	/**
	 * @param string $path
	 * @return bool
	 */
	public function is_dir( $path ) {
		$transport = $this->get_transport_for_path( $path );
		return $transport->is_dir( $path );
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function is_readable( $file ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->is_readable( $file );
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function is_writable( $file ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->is_writable( $file );
	}

	/**
	 * Unimplemented - Check if resource is a directory.
	 *
	 * @param string $path Directory path.
	 * @return bool Whether $path is a directory.
	 */
	public function is_dir( $path ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}


	/**
	 * Unimplemented - Gets the file's last access time.
	 *
	 * @param string $file Path to file.
	 * @return int|bool Unix timestamp representing last access time.
	 */
	public function atime( $file ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Gets the file modification time.
	 *
	 * @param string $file Path to file.
	 * @return int|bool Unix timestamp representing modification time.
	 */
	public function mtime( $file ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Set the access and modification times of a file.
	 *
	 * Note: If $file doesn't exist, it will be created.
	 *
	 * @param string $file  Path to file.
	 * @param int    $time  Optional. Modified time to set for file.
	 *                      Default 0.
	 * @param int    $atime Optional. Access time to set for file.
	 *                      Default 0.
	 * @return bool Whether operation was successful or not.
	 */
	public function touch( $file, $time = 0, $atime = 0 ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Create a directory.
	 *
	 * @param string $path  Path for new directory.
	 * @param mixed  $chmod Optional. The permissions as octal number, (or False to skip chmod)
	 *                      Default false.
	 * @param mixed  $chown Optional. A user name or number (or False to skip chown)
	 *                      Default false.
	 * @param mixed  $chgrp Optional. A group name or number (or False to skip chgrp).
	 *                      Default false.
	 * @return bool False if directory cannot be created, true otherwise.
	 */
	public function mkdir( $path, $chmod = false, $chown = false, $chgrp = false ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Delete a directory.
	 *
	 * @param string $path      Path to directory.
	 * @param bool   $recursive Optional. Whether to recursively remove files/directories.
	 *                          Default false.
	 * @return bool Whether directory is deleted successfully or not.
	 */
	public function rmdir( $path, $recursive = false ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Get details for files in a directory or a specific file.
	 *
	 * @param string $path           Path to directory or file.
	 * @param bool   $include_hidden Optional. Whether to include details of hidden ("." prefixed) files.
	 *                               Default true.
	 * @param bool   $recursive      Optional. Whether to recursively include file details in nested directories.
	 *                               Default false.
	 * @return array|bool {
	 *     Array of files. False if unable to list directory contents.
	 *
	 *     @type string $name        Name of the file/directory.
	 *     @type string $perms       *nix representation of permissions.
	 *     @type int    $permsn      Octal representation of permissions.
	 *     @type string $owner       Owner name or ID.
	 *     @type int    $size        Size of file in bytes.
	 *     @type int    $lastmodunix Last modified unix timestamp.
	 *     @type mixed  $lastmod     Last modified month (3 letter) and day (without leading 0).
	 *     @type int    $time        Last modified time.
	 *     @type string $type        Type of resource. 'f' for file, 'd' for directory.
	 *     @type mixed  $files       If a directory and $recursive is true, contains another array of files.
	 * }
	 */
	public function dirlist( $path, $include_hidden = true, $recursive = false ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}


	/**
	 * Unimplemented - Gets the current working directory
	 *
	 * @return string|bool the current working directory on success, or false on failure.
	 */
	public function cwd() {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Change directory
	 *
	 * @param string $dir The new current directory.
	 * @return bool Returns true on success or false on failure.
	 */
	public function chdir( $dir ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Changes file group
	 *
	 * @param string $file      Path to the file.
	 * @param mixed  $group     A group name or number.
	 * @param bool   $recursive Optional. If set True changes file group recursively. Default false.
	 * @return bool Returns true on success or false on failure.
	 */
	public function chgrp( $file, $group, $recursive = false ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Changes filesystem permissions
	 *
	 * @param string $file      Path to the file.
	 * @param int    $mode      Optional. The permissions as octal number, usually 0644 for files,
	 *                          0755 for dirs. Default false.
	 * @param bool   $recursive Optional. If set True changes file group recursively. Default false.
	 * @return bool Returns true on success or false on failure.
	 */
	public function chmod( $file, $mode = false, $recursive = false ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Changes file owner
	 *
	 * @param string $file      Path to the file.
	 * @param mixed  $owner     A user name or number.
	 * @param bool   $recursive Optional. If set True changes file owner recursively.
	 *                          Default false.
	 * @return bool Returns true on success or false on failure.
	 */
	public function chown( $file, $owner, $recursive = false ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Gets file owner
	 *
	 * @param string $file Path to the file.
	 * @return string|bool Username of the user or false on error.
	 */
	public function owner( $file ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Gets file permissions
	 *
	 * @param string $file Path to the file.
	 * @return string Mode of the file (last 3 digits).
	 */
	public function getchmod( $file ) {
		return $this->handle_unimplemented_method( __METHOD__, '' );
	}

	/**
	 * Unimplemented - Get the file's group.
	 *
	 * @param string $file Path to the file.
	 * @return string|bool The group or false on error.
	 */
	public function group( $file ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	protected function handle_unimplemented_method( $method, $return_value = false ) {
		/* Translators: unsupported method name */
		$error_msg = sprintf( __( 'The `%s` method is not implemented and/or not supported.' ), $method );

		$this->errors->add( 'unimplemented-method', $error_msg );

		trigger_error( $error_msg, E_USER_ERROR );

		return $return_value;
	}
}
