<?php
/**
 * Uploads — endpoints AJAX do Dropzone (upload moderno) e rotinas de
 * movimentação tmp → diretório definitivo, reproduzindo o WPForms 1.7.6.
 *
 * @package RioCargas\Components\Forms
 */

namespace RioCargas\Components\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Uploads clássicos e modernos.
 */
class Uploads {

	const STYLE_MODERN = 'modern';

	/**
	 * Extensões default: get_allowed_mime_types() menos a denylist do plugin.
	 */
	public static function default_extensions() {
		$denylist = [ 'ade', 'adp', 'app', 'asp', 'bas', 'bat', 'cer', 'cgi', 'chm', 'cmd', 'com', 'cpl', 'crt', 'csh', 'csr', 'dll', 'drv', 'exe', 'fxp', 'flv', 'hlp', 'hta', 'htaccess', 'htm', 'html', 'htpasswd', 'inf', 'ins', 'isp', 'jar', 'js', 'jse', 'jsp', 'ksh', 'lnk', 'mdb', 'mde', 'mdt', 'mdw', 'msc', 'msi', 'msp', 'mst', 'ops', 'pcd', 'php', 'pif', 'pl', 'prg', 'ps1', 'ps2', 'py', 'rb', 'reg', 'scr', 'sct', 'sh', 'shb', 'shs', 'sys', 'swf', 'tmp', 'torrent', 'url', 'vb', 'vbe', 'vbs', 'vbscript', 'wsc', 'wsf', 'wsf', 'wsh', 'dfxp', 'onetmp' ];
		$exts     = [];
		foreach ( array_keys( get_allowed_mime_types() ) as $key ) {
			foreach ( explode( '|', $key ) as $e ) {
				if ( ! in_array( $e, $denylist, true ) ) {
					$exts[] = $e;
				}
			}
		}
		return implode( ',', $exts );
	}

	/**
	 * Diretório base de uploads: uploads/wpforms.
	 */
	public static function upload_dir() {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return [ 'error' => $upload_dir['error'] ];
		}
		$basedir = wp_is_stream( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : realpath( $upload_dir['basedir'] );
		$root    = trailingslashit( $basedir ) . 'wpforms';
		return [
			'path'  => $root,
			'url'   => trailingslashit( $upload_dir['baseurl'] ) . 'wpforms',
			'error' => false,
		];
	}

	/**
	 * Diretório temporário uploads/wpforms/tmp.
	 */
	public static function tmp_dir() {
		$upload_dir = self::upload_dir();
		$tmp        = $upload_dir['path'] . '/tmp';
		if ( ! file_exists( $tmp ) || ! wp_is_writable( $tmp ) ) {
			wp_mkdir_p( $tmp );
		}
		self::create_index_html( $tmp );
		return $tmp;
	}

	/**
	 * Diretório do formulário: {form_id}-{md5(form_id . post_date)}.
	 */
	public static function form_directory( $form_id, $date_created ) {
		return absint( $form_id ) . '-' . md5( $form_id . $date_created );
	}

	/**
	 * index.html vazio (proteção de listing).
	 */
	public static function create_index_html( $path ) {
		if ( ! is_dir( $path ) || is_link( $path ) ) {
			return false;
		}
		$index = wp_normalize_path( trailingslashit( $path ) . 'index.html' );
		if ( file_exists( $index ) ) {
			return false;
		}
		return file_put_contents( $index, '' );
	}

	/**
	 * .htaccess de proteção do diretório wpforms (mesmo conteúdo do plugin).
	 */
	public static function create_htaccess() {
		$upload_dir = self::upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return false;
		}
		$file = wp_normalize_path( trailingslashit( $upload_dir['path'] ) . '.htaccess' );
		if ( is_file( $file ) ) {
			return true;
		}
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		$contents = '# Disable PHP and Python scripts parsing.
<IfModule mod_php.c>
	php_flag engine off
</IfModule>
<IfModule mod_php7.c>
	php_flag engine off
</IfModule>
<IfModule mod_php8.c>
	php_flag engine off
</IfModule>
<IfModule mod_mime.c>
	AddType text/plain .php .phtml .py .cgi .pl
</IfModule>';
		return insert_with_markers( $file, 'WPForms', $contents );
	}

	/**
	 * Lê form_data e valida (form_id, field_id, style modern).
	 *
	 * @return array|false
	 */
	protected static function validate_form_field_modern() {
		if ( empty( $_POST['form_id'] ) || empty( $_POST['field_id'] ) ) {
			return false;
		}
		$form_data = Renderer::get_form_data( absint( $_POST['form_id'] ) );
		if ( empty( $form_data ) ) {
			return false;
		}
		$field_id = absint( $_POST['field_id'] );
		if (
			empty( $form_data['fields'][ $field_id ] ) ||
			( $form_data['fields'][ $field_id ]['style'] ?? '' ) !== self::STYLE_MODERN
		) {
			return false;
		}
		return [
			'form_data'  => $form_data,
			'field_id'   => $field_id,
			'field_data' => $form_data['fields'][ $field_id ],
		];
	}

	/**
	 * Nome temporário: md5(uniqid) + ext.
	 */
	protected static function tmp_name( $ext ) {
		return md5( uniqid( wp_rand(), true ) ) . '.' . strtolower( $ext );
	}

	/**
	 * Extensões permitidas do campo (lista ou default).
	 */
	protected static function field_extensions( $field_data ) {
		$exts = ! empty( $field_data['extensions'] ) ? $field_data['extensions'] : self::default_extensions();
		return array_map( 'trim', explode( ',', strtolower( $exts ) ) );
	}

	/**
	 * Tamanho máximo do campo em bytes.
	 */
	protected static function field_max_size( $field_data ) {
		if ( ! empty( $field_data['max_size'] ) ) {
			$mb = preg_replace( '/[^0-9.]/', '', $field_data['max_size'] );
			return (int) ( $mb * 1024 * 1024 );
		}
		return wp_max_upload_size();
	}

	/**
	 * Valida extensão/tamanho/tipo real de um arquivo. Retorna msg de erro ou ''.
	 */
	public static function validate_file( $path, $name, $size, $field_data ) {
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::field_extensions( $field_data ), true ) ) {
			return __( 'File type is not allowed.', 'wpforms-lite' );
		}
		if ( $size > self::field_max_size( $field_data ) ) {
			return __( 'File exceeds max size allowed. File was not uploaded.', 'wpforms-lite' );
		}
		$check = wp_check_filetype_and_ext( $path, $name );
		if ( empty( $check['ext'] ) && empty( $check['type'] ) ) {
			return __( 'File type is not allowed.', 'wpforms-lite' );
		}
		return '';
	}

	/**
	 * Remove arquivos tmp com mais de 1 dia.
	 */
	protected static function clean_tmp() {
		$files = glob( trailingslashit( self::tmp_dir() ) . '*' );
		if ( ! is_array( $files ) ) {
			return;
		}
		foreach ( $files as $f ) {
			if ( 'index.html' === basename( $f ) || ! is_file( $f ) ) {
				continue;
			}
			$mtime = (int) @filemtime( $f );
			if ( $mtime && ( time() - $mtime ) >= DAY_IN_SECONDS ) {
				@unlink( $f );
			}
		}
	}

	/**
	 * wp_ajax_wpforms_upload_file — upload único (sem chunks).
	 */
	public static function ajax_modern_upload() {
		$default_error = esc_html__( 'Something went wrong, please try again.', 'wpforms-lite' );
		$validated     = self::validate_form_field_modern();
		if ( ! $validated ) {
			wp_send_json_error( $default_error, 400 );
		}
		if ( empty( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( $default_error, 400 );
		}
		$file           = $_FILES['file'];
		$file_user_name = sanitize_text_field( $file['name'] );
		$name           = sanitize_file_name( $file['name'] );
		$ext            = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

		$err = self::validate_file( $file['tmp_name'], $name, $file['size'], $validated['field_data'] );
		if ( $err ) {
			wp_send_json_error( $err, 400 );
		}

		$tmp_name = self::tmp_name( $ext );
		$tmp_path = wp_normalize_path( self::tmp_dir() . '/' . $tmp_name );
		if ( ! @move_uploaded_file( $file['tmp_name'], $tmp_path ) && ! @rename( $file['tmp_name'], $tmp_path ) ) {
			wp_send_json_error( $default_error, 400 );
		}
		self::clean_tmp();

		wp_send_json_success(
			[
				'name'           => $name,
				'file'           => $tmp_name,
				'url'            => self::upload_dir()['url'] . '/tmp/' . $tmp_name,
				'size'           => filesize( $tmp_path ),
				'type'           => wp_check_filetype( $tmp_path )['type'],
				'file_user_name' => $file_user_name,
			]
		);
	}

	/**
	 * Metadados do chunk: tmp/sha1(uuid) JSON.
	 */
	protected static function chunk_meta_path( $uuid ) {
		return trailingslashit( self::tmp_dir() ) . sha1( $uuid ) . '-';
	}

	/**
	 * wp_ajax_wpforms_upload_chunk_init — autoriza e devolve a config de chunks.
	 */
	public static function ajax_chunk_init() {
		$default_error = esc_html__( 'Something went wrong, please try again.', 'wpforms-lite' );
		$validated     = self::validate_form_field_modern();
		if ( ! $validated ) {
			wp_send_json_error( $default_error );
		}
		$uuid     = isset( $_POST['dzuuid'] ) ? sanitize_text_field( wp_unslash( $_POST['dzuuid'] ) ) : '';
		$name     = isset( $_POST['name'] ) ? sanitize_file_name( wp_unslash( $_POST['name'] ) ) : '';
		$size     = isset( $_POST['dztotalfilesize'] ) ? absint( $_POST['dztotalfilesize'] ) : 0;
		$chunks   = isset( $_POST['dztotalchunkcount'] ) ? absint( $_POST['dztotalchunkcount'] ) : 0;
		$username = isset( $_POST['file_user_name'] ) ? sanitize_text_field( wp_unslash( $_POST['file_user_name'] ) ) : $name;

		if ( ! $uuid || ! $name || ! $chunks ) {
			wp_send_json_error( $default_error, 403 );
		}
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$err = self::validate_file( __FILE__, $name, $size, $validated['field_data'] );
		// Validação de tipo real é adiada ao finalize (arquivo ainda não existe).
		if ( ! in_array( $ext, self::field_extensions( $validated['field_data'] ), true ) ) {
			wp_send_json_error( __( 'File type is not allowed.', 'wpforms-lite' ) );
		}
		if ( $size > self::field_max_size( $validated['field_data'] ) ) {
			wp_send_json_error( __( 'File exceeds max size allowed. File was not uploaded.', 'wpforms-lite' ) );
		}
		file_put_contents(
			self::chunk_meta_path( $uuid ),
			wp_json_encode(
				[
					'name'           => $name,
					'size'           => $size,
					'chunks'         => $chunks,
					'file_user_name' => $username,
				]
			)
		);
		wp_send_json(
			[
				'success' => true,
				'data'    => [
					'dzchunksize'          => min( 2097152, wp_max_upload_size() ),
					'parallelChunkUploads' => true,
				],
			]
		);
	}

	/**
	 * wp_ajax_wpforms_upload_chunk — recebe um chunk e concatena no tmp.
	 */
	public static function ajax_chunk() {
		$default_error = esc_html__( 'Something went wrong, please try again.', 'wpforms-lite' );
		$validated     = self::validate_form_field_modern();
		if ( ! $validated ) {
			wp_send_json_error( $default_error );
		}
		$uuid = isset( $_POST['dzuuid'] ) ? sanitize_text_field( wp_unslash( $_POST['dzuuid'] ) ) : '';
		if ( ! $uuid || ! is_file( self::chunk_meta_path( $uuid ) ) ) {
			wp_send_json_error( $default_error, 403 );
		}
		if ( empty( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( $default_error, 403 );
		}
		$data_path = self::chunk_meta_path( $uuid ) . 'data';
		$in        = fopen( $_FILES['file']['tmp_name'], 'rb' );
		$out       = fopen( $data_path, 'ab' );
		if ( ! $in || ! $out ) {
			wp_send_json_error( $default_error, 403 );
		}
		stream_copy_to_stream( $in, $out );
		fclose( $in );
		fclose( $out );
		wp_send_json( [ 'success' => true ] );
	}

	/**
	 * wp_ajax_wpforms_file_chunks_uploaded — finaliza: move para tmp definitivo.
	 */
	public static function ajax_chunks_uploaded() {
		$default_error = esc_html__( 'Something went wrong, please try again.', 'wpforms-lite' );
		$uuid          = isset( $_POST['dzuuid'] ) ? sanitize_text_field( wp_unslash( $_POST['dzuuid'] ) ) : '';
		if ( ! $uuid || ! is_file( self::chunk_meta_path( $uuid ) ) ) {
			wp_send_json_error( $default_error, 403 );
		}
		$meta      = json_decode( file_get_contents( self::chunk_meta_path( $uuid ) ), true );
		$data_path = self::chunk_meta_path( $uuid ) . 'data';
		if ( ! is_array( $meta ) || ! is_file( $data_path ) ) {
			wp_send_json_error( $default_error, 403 );
		}
		$name        = $meta['name'];
		$ext         = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$tmp_name    = self::tmp_name( $ext );
		$tmp_path    = wp_normalize_path( self::tmp_dir() . '/' . $tmp_name );
		if ( ! @rename( $data_path, $tmp_path ) ) {
			wp_send_json_error( $default_error, 403 );
		}
		@unlink( self::chunk_meta_path( $uuid ) );

		$check = wp_check_filetype_and_ext( $tmp_path, $name );
		if ( empty( $check['ext'] ) && empty( $check['type'] ) ) {
			@unlink( $tmp_path );
			wp_send_json_error( __( 'File type is not allowed.', 'wpforms-lite' ), 403 );
		}
		self::clean_tmp();

		wp_send_json_success(
			[
				'name'           => $name,
				'file'           => $tmp_name,
				'url'            => self::upload_dir()['url'] . '/tmp/' . $tmp_name,
				'size'           => filesize( $tmp_path ),
				'type'           => wp_check_filetype( $tmp_path )['type'],
				'file_user_name' => $meta['file_user_name'],
			]
		);
	}

	/**
	 * wp_ajax_wpforms_remove_file — remove arquivo do tmp.
	 */
	public static function ajax_remove() {
		$default_error = esc_html__( 'Something went wrong while removing the file.', 'wpforms-lite' );
		if ( ! self::validate_form_field_modern() ) {
			wp_send_json_error( $default_error, 400 );
		}
		if ( empty( $_POST['file'] ) ) {
			wp_send_json_error( $default_error, 403 );
		}
		$file     = sanitize_file_name( wp_unslash( $_POST['file'] ) );
		$tmp_path = wp_normalize_path( self::tmp_dir() . '/' . $file );
		if ( ! is_file( $tmp_path ) ) {
			wp_send_json_success( $file );
		}
		if ( ! @unlink( $tmp_path ) ) {
			wp_send_json_error( $default_error, 403 );
		}
		wp_send_json_success( $file );
	}

	/**
	 * Move arquivo do tmp para o diretório definitivo do form (modern).
	 * Ou move $_FILES (classic). Devolve dados no formato do plugin.
	 *
	 * @param array $file      Dados do arquivo (name/file_user_name/file ou tmp_name).
	 * @param int   $field_id  Field ID.
	 * @param array $form_data Form data (com 'created').
	 * @return array
	 */
	public static function process_file( $file, $field_id, $form_data ) {
		$file_name     = sanitize_file_name( $file['name'] );
		$file_ext      = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		$file_base     = substr( $file_name, 0, -strlen( '.' . $file_ext ) );
		$file_name_new = sprintf(
			'%s-%s.%s',
			$file_base,
			wp_hash( wp_rand() . microtime() . $form_data['id'] . $field_id ),
			$file_ext
		);

		$upload_dir     = self::upload_dir();
		$form_directory = self::form_directory( $form_data['id'], $form_data['created'] );
		$form_path      = trailingslashit( $upload_dir['path'] ) . $form_directory;
		$form_url       = trailingslashit( $upload_dir['url'] ) . $form_directory;

		self::create_htaccess();
		if ( ! is_dir( $form_path ) ) {
			wp_mkdir_p( $form_path );
		}
		self::create_index_html( $upload_dir['path'] );
		self::create_index_html( $form_path );

		$dest     = trailingslashit( $form_path ) . $file_name_new;
		$file_url = trailingslashit( $form_url ) . $file_name_new;

		if ( ! empty( $file['tmp_name'] ) && is_uploaded_file( $file['tmp_name'] ) ) {
			$moved = @move_uploaded_file( $file['tmp_name'], $dest ) || @rename( $file['tmp_name'], $dest );
		} else {
			$moved = @rename( $file['tmp_name'], $dest );
		}
		if ( ! $moved ) {
			return [];
		}
		@chmod( $dest, 0644 );

		return [
			'file_name'      => $file_name,
			'file_name_new'  => $file_name_new,
			'file_ext'       => $file_ext,
			'file_url'       => $file_url,
			'file_user_name' => isset( $file['file_user_name'] ) ? sanitize_text_field( $file['file_user_name'] ) : $file_name,
			'attachment_id'  => '0',
		];
	}
}
