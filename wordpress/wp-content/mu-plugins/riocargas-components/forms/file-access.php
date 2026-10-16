<?php
/**
 * Acesso protegido aos uploads do WPForms (LGPD).
 *
 * Os arquivos em uploads/wpforms/ (currículos, apresentações) ficam bloqueados
 * para acesso web direto via .htaccess. O e-mail de notificação passa a conter
 * links assinados (HMAC) para este endpoint, que serve o arquivo.
 *
 * Registrado fora do gate do plugin original: links emitidos continuam válidos
 * mesmo se o WPForms for reativado (rollback).
 *
 * @package RioCargas\Components\Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gera URL assinada para servir um arquivo dentro de uploads/wpforms/.
 *
 * @param string $relpath Caminho relativo dentro de uploads/wpforms/ (ex.: "3534-abc/arquivo.pdf").
 * @return string URL do endpoint com assinatura HMAC.
 */
function rc_wpforms_file_url( $relpath ) {
	$relpath = ltrim( str_replace( '\\', '/', $relpath ), '/' );
	$sig     = hash_hmac( 'sha256', $relpath, wp_salt( 'auth' ) );
	return add_query_arg(
		[
			'action' => 'rc_wpforms_file',
			'p'      => $relpath,
			's'      => $sig,
		],
		admin_url( 'admin-ajax.php' )
	);
}

/**
 * Reescreve URLs diretas de uploads/wpforms/ no HTML (corpo de e-mail) para o
 * endpoint assinado.
 *
 * @param string $html HTML do e-mail.
 * @return string HTML com links substituídos.
 */
function rc_wpforms_protect_links( $html ) {
	$uploads = wp_upload_dir();
	$direct  = trailingslashit( $uploads['baseurl'] ) . 'wpforms/';
	if ( false === strpos( $html, $direct ) ) {
		return $html;
	}
	return preg_replace_callback(
		'#' . preg_quote( $direct, '#' ) . '([^\s"\'<>]+)#',
		function ( $m ) {
			return rc_wpforms_file_url( $m[1] );
		},
		$html
	);
}

/**
 * Handler AJAX: valida a assinatura e serve o arquivo.
 */
function rc_wpforms_file_serve() {
	$rel = isset( $_GET['p'] ) ? sanitize_text_field( wp_unslash( $_GET['p'] ) ) : '';
	$sig = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

	// Sanitização: sem traversal, sem caminho absoluto, sem query.
	if ( '' === $rel || false !== strpos( $rel, '..' ) || 0 === strpos( $rel, '/' ) || preg_match( '#^[a-z]+://#i', $rel ) ) {
		status_header( 404 );
		exit;
	}

	$expect = hash_hmac( 'sha256', $rel, wp_salt( 'auth' ) );
	if ( ! hash_equals( $expect, $sig ) ) {
		status_header( 403 );
		exit;
	}

	$uploads = wp_upload_dir();
	$base    = realpath( trailingslashit( $uploads['basedir'] ) . 'wpforms' );
	$abs     = $base ? realpath( $base . '/' . $rel ) : false;

	// Garante que o arquivo resolvido está dentro de uploads/wpforms/.
	if ( ! $abs || ! $base || 0 !== strpos( $abs, $base ) || ! is_file( $abs ) ) {
		status_header( 404 );
		exit;
	}

	$mime = wp_check_filetype( basename( $abs ) );
	nocache_headers();
	header( 'Content-Type: ' . ( $mime['type'] ?: 'application/octet-stream' ) );
	header( 'Content-Length: ' . filesize( $abs ) );
	header( 'X-Robots-Tag: noindex, nofollow', true );
	header( 'Content-Disposition: inline; filename="' . basename( $abs ) . '"' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	readfile( $abs );
	exit;
}

add_action( 'wp_ajax_rc_wpforms_file', 'rc_wpforms_file_serve' );
add_action( 'wp_ajax_nopriv_rc_wpforms_file', 'rc_wpforms_file_serve' );

/**
 * Garante o .htaccess de bloqueio em uploads/wpforms/ (fora dos marcadores
 * gerenciados "BEGIN/END WPForms" para não ser sobrescrito pelo plugin).
 * Idempotente: sai cedo se o marcador próprio já existe.
 */
function rc_wpforms_ensure_uploads_htaccess() {
	$uploads = wp_upload_dir();
	$dir     = trailingslashit( $uploads['basedir'] ) . 'wpforms';
	$file    = $dir . '/.htaccess';
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$current = is_file( $file ) ? file_get_contents( $file ) : ''; // phpcs:ignore
	if ( false !== strpos( (string) $current, 'BEGIN RioCargas LGPD' ) ) {
		return;
	}
	$block = "\n# BEGIN RioCargas LGPD\n"
		. "# Acesso direto negado — arquivos servidos so pelo endpoint assinado\n"
		. "# (admin-ajax.php?action=rc_wpforms_file). Gerenciado pelo mu-plugin.\n"
		. "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
		. "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n"
		. "# END RioCargas LGPD\n";
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	file_put_contents( $file, $current . $block, LOCK_EX );
}
add_action( 'init', 'rc_wpforms_ensure_uploads_htaccess' );
