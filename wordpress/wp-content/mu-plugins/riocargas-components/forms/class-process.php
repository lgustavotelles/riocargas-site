<?php
/**
 * Process — submit de formulários (AJAX + POST clássico), validação,
 * entries, e-mails de notificação, confirmações e hooks do WPForms 1.7.6.
 *
 * @package RioCargas\Components\Forms
 */

namespace RioCargas\Components\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Engine de processamento.
 */
class Process {

	/** @var array Campos formatados do submit corrente. */
	public static $fields = [];

	/** @var array Erros por form_id. */
	public static $errors = [];

	/** @var array Form data do submit corrente. */
	public static $form_data;

	/** @var int Entry ID salvo. */
	public static $entry_id = 0;

	/** @var array Confirmação corrente. */
	protected static $confirmation = [];

	/**
	 * Mensagem padrão de campo obrigatório (mesma do wpforms_get_required_label).
	 */
	protected static function required_label() {
		return esc_html__( 'This field is required.', 'wpforms-lite' );
	}

	/**
	 * `wp` — escuta POST clássico (sem ajax) e ?wpforms_return.
	 */
	/**
	 * Confirmação corrente (para render não-AJAX e hooks).
	 */
	public static function get_current_confirmation() {
		return ! empty( self::$confirmation ) ? self::$confirmation : [];
	}

	/**
	 * Mensagem de confirmação pronta para um form_id (submit não-AJAX).
	 */
	public static function confirmation_message_for( $form_id ) {
		if ( empty( self::$confirmation ) || empty( self::$form_data ) || absint( self::$form_data["id"] ) !== absint( $form_id ) ) {
			return "";
		}
		return self::get_confirmation_message( self::$form_data );
	}

	public static function listen() {
		$form_id = ! empty( $_POST['wpforms']['id'] ) ? absint( $_POST['wpforms']['id'] ) : 0;
		if ( ! $form_id ) {
			return;
		}
		self::process( wp_unslash( $_POST['wpforms'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * `wp_ajax_wpforms_submit` — submit AJAX (todos os forms do site são ajax).
	 */
	public static function ajax_submit() {
		$form_id = isset( $_POST['wpforms']['id'] ) ? absint( $_POST['wpforms']['id'] ) : 0;
		if ( empty( $form_id ) ) {
			wp_send_json_error();
		}
		if ( isset( $_POST['wpforms']['post_id'] ) ) {
			global $post;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$post = \WP_Post::get_instance( absint( $_POST['wpforms']['post_id'] ) );
		}

		add_filter( 'wp_redirect', [ __CLASS__, 'ajax_process_redirect' ], 999 );

		do_action( 'wpforms_ajax_submit_before_processing', $form_id );

		self::process( wp_unslash( $_POST['wpforms'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		$form_data = self::$form_data;
		if ( empty( $form_data ) ) {
			$form_data = Renderer::get_form_data( $form_id );
		}

		if ( ! empty( self::$errors[ $form_id ] ) ) {
			self::ajax_errors( $form_id, $form_data );
			wp_send_json_error();
		}

		// Confirmação (mesmo wrapper do plugin).
		$confirmation_message = self::get_confirmation_message( $form_data );
		if ( empty( $confirmation_message ) ) {
			wp_send_json_success( [ 'confirmation' => '' ] );
		}
		$class = 'wpforms-confirmation-container';
		if ( ! empty( self::$confirmation['message_scroll'] ) ) {
			$class .= ' wpforms-confirmation-scroll';
		}
		$response = [
			'confirmation' => sprintf(
				'<div class="%s" id="wpforms-confirmation-%d">%s</div>',
				esc_attr( $class ),
				absint( $form_data['id'] ),
				$confirmation_message
			),
		];
		if ( ! empty( self::$confirmation['type'] ) && 'redirect' === self::$confirmation['type'] && ! empty( self::$confirmation['redirect'] ) ) {
			$response = apply_filters(
				'wpforms_ajax_submit_redirect',
				[
					'form_id'      => $form_id,
					'redirect_url' => self::$confirmation['redirect'],
				],
				$form_id,
				self::$confirmation['redirect']
			);
			wp_send_json_success( $response );
		}

		$response = apply_filters( 'wpforms_ajax_submit_success_response', $response, $form_id, $form_data );
		do_action( 'wpforms_ajax_submit_completed', $form_id, $response );
		wp_send_json_success( $response );
	}

	/**
	 * `wp_redirect` interceptado durante submit AJAX (hook disparado por addons
	 * que chamam wp_redirect dentro do process) — devolve JSON como o plugin.
	 */
	public static function ajax_process_redirect( $url ) {
		$form_id = isset( $_POST['wpforms']['id'] ) ? absint( $_POST['wpforms']['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $form_id ) ) {
			wp_send_json_error();
		}
		$response = [
			'form_id'      => $form_id,
			'redirect_url' => $url,
		];
		$response = apply_filters( 'wpforms_ajax_submit_redirect', $response, $form_id, $url );
		do_action( 'wpforms_ajax_submit_completed', $form_id, $response );
		wp_send_json_success( $response );
		return $url;
	}

	/**
	 * Erros no formato do plugin: errors.general (html) + errors.field (name→msg).
	 */
	protected static function ajax_errors( $form_id, $form_data ) {
		$errors         = isset( self::$errors[ $form_id ] ) ? self::$errors[ $form_id ] : [];
		$errors         = apply_filters( 'wpforms_ajax_submit_errors', $errors, $form_id, $form_data );
		$general_errors = array_intersect_key( $errors, array_flip( [ 'header', 'footer', 'recaptcha' ] ) );
		foreach ( $general_errors as $key => $error ) {
			$general_errors[ $key ] = '<div class="wpforms-error-container">' . wpautop( wp_kses_post( $error ) ) . '</div>';
		}
		$fields       = isset( $form_data['fields'] ) ? $form_data['fields'] : [];
		$field_errors = array_intersect_key( $errors, $fields );
		foreach ( $field_errors as $key => $error ) {
			$name = "wpforms[fields][{$key}]";
			unset( $field_errors[ $key ] );
			$field_errors[ $name ] = $error;
		}
		$response = [];
		if ( $general_errors ) {
			$response['errors']['general'] = $general_errors;
		}
		if ( $field_errors ) {
			$response['errors']['field'] = $field_errors;
		}
		$response = apply_filters( 'wpforms_ajax_submit_errors_response', $response, $form_id, $form_data );
		do_action( 'wpforms_ajax_submit_completed', $form_id, $response );
		wp_send_json_error( $response );
	}

	/**
	 * Processa um submit (shared entre ajax e POST clássico).
	 */
	public static function process( $entry ) {
		self::$errors   = [];
		self::$fields   = [];
		self::$entry_id = 0;
		$form_id = ! empty( $entry['id'] ) ? absint( $entry['id'] ) : 0;
		if ( ! $form_id ) {
			return;
		}
		$form      = get_post( $form_id );
		$form_data = $form ? Renderer::get_form_data( $form_id ) : [];
		// Form inexistente, não-publicado ou de outro post_type — mesmo header do plugin.
		if ( ! $form || 'publish' !== $form->post_status || 'wpforms' !== $form->post_type || empty( $form_data ) ) {
			self::$errors[ $form_id ]['header'] = esc_html__( 'Invalid form.', 'wpforms-lite' );
			return;
		}
		$form_data       = apply_filters( 'wpforms_process_before_form_data', $form_data, $entry );
		self::$form_data = $form_data;
		$entry           = apply_filters( 'wpforms_process_before_filter', $entry, $form_data );

		do_action( 'wpforms_process_before', $entry, $form_data );
		do_action( "wpforms_process_before_{$form_id}", $entry, $form_data );

		// Validação por tipo — no plugin, aqui só se coletam erros; os fields
		// são "formatados" depois das checagens de segurança (nonce/hp/token).
		$built_fields = [];
		foreach ( (array) $form_data['fields'] as $field_props ) {
			$fid   = $field_props['id'];
			$type  = $field_props['type'];
			$input = isset( $entry['fields'][ $fid ] ) ? $entry['fields'][ $fid ] : '';
			do_action( "wpforms_process_validate_{$type}", $fid, $input, $form_data );
			$built = self::validate_field( $fid, $type, $input, $field_props, $form_data, $entry );
			if ( null !== $built ) {
				$built_fields[ $fid ] = $built;
			}
		}

		// Nonce: para usuários logados, ausente/inválido falha silencioso (igual ao plugin).
		if ( is_user_logged_in() && ( empty( $entry['nonce'] ) || ! wp_verify_nonce( $entry['nonce'], "wpforms::form_{$form_id}" ) ) ) {
			return;
		}

		// Honeypot — fail silencioso (o bot recebe "sucesso" sem gravar entry).
		$honeypot = false;
		if (
			! empty( $form_data['settings']['honeypot'] ) &&
			'1' === $form_data['settings']['honeypot'] &&
			! empty( $entry['hp'] )
		) {
			$honeypot = esc_html__( 'WPForms honeypot field triggered.', 'wpforms-lite' );
		}
		$honeypot = apply_filters( 'wpforms_process_honeypot', $honeypot, self::$fields, $entry, $form_data );
		if ( $honeypot ) {
			return;
		}

		// Token antispam (data-token do form → vem via JS em wpforms[token]).
		$token_error = self::validate_token( $form_data, $entry );
		if ( $token_error ) {
			self::$errors[ $form_id ]['header'] = $token_error;
			return;
		}

		// Akismet — não há integração configurada no site; mantém o ponto de extensão.
		$akismet = apply_filters( 'wpforms_process_akismet', false, $form_data, $entry );
		if ( $akismet ) {
			self::$errors[ $form_id ]['header'] = $akismet;
			return;
		}

		$form_data['created'] = get_post_field( 'post_date', $form_id );
		self::$form_data      = $form_data;

		// Format — hooks por tipo; os fields vêm da validação interna.
		foreach ( (array) $form_data['fields'] as $field_props ) {
			$fid   = $field_props['id'];
			$type  = $field_props['type'];
			$input = isset( $entry['fields'][ $fid ] ) ? $entry['fields'][ $fid ] : '';
			do_action( "wpforms_process_format_{$type}", $fid, $input, $form_data );
		}
		do_action( 'wpforms_process_format_after', $form_data );

		self::$fields = $built_fields;

		// Conditional logic: marca visibilidade.
		foreach ( (array) $form_data['fields'] as $field_props ) {
			$fid = $field_props['id'];
			if ( ! isset( self::$fields[ $fid ] ) ) {
				continue;
			}
			self::$fields[ $fid ]['visible'] = self::field_is_visible( $form_data, $fid );
		}

		self::$fields = apply_filters( 'wpforms_process_filter', self::$fields, $entry, $form_data );
		do_action( 'wpforms_process', self::$fields, $entry, $form_data );
		do_action( "wpforms_process_{$form_id}", self::$fields, $entry, $form_data );

		self::$fields = apply_filters( 'wpforms_process_after_filter', self::$fields, $entry, $form_data );

		if ( ! empty( self::$errors[ $form_id ] ) ) {
			if ( empty( self::$errors[ $form_id ]['header'] ) ) {
				self::$errors[ $form_id ]['header'] = esc_html__( 'Form has not been submitted, please see the errors below.', 'wpforms-lite' );
			}
			return;
		}

		// Uploads (só depois de validado — igual ao plugin).
		self::complete_uploads( $form_data );

		// Entry no banco.
		self::$entry_id = self::entry_save( $entry, $form_id, $form_data );

		// E-mails.
		self::entry_email( $entry, $form_data );

		$_POST['wpforms']['complete'] = self::$fields;
		$_POST['wpforms']['entry_id'] = self::$entry_id;

		do_action( 'wpforms_process_complete', self::$fields, $entry, $form_data, self::$entry_id );
		do_action( "wpforms_process_complete_{$form_id}", self::$fields, $entry, $form_data, self::$entry_id );

		self::resolve_confirmation( $form_data, self::$entry_id );
	}

	/**
	 * Token antispam v2 — habilitado quando settings['antispam'] === '1'.
	 * Token ausente OU inválido gera erro de header (não é bypassável).
	 */
	protected static function validate_token( $form_data, $entry ) {
		if ( empty( $form_data['settings']['antispam'] ) || '1' !== $form_data['settings']['antispam'] ) {
			return '';
		}
		$valid = isset( $entry['token'] ) && self::token_verify( $entry['token'], $form_data );
		$valid = apply_filters( 'wpforms_process_antispam', $valid, self::$fields, $entry, $form_data );
		if ( $valid ) {
			return '';
		}
		if ( wpforms_current_user_can() ) {
			$msg = esc_html__( 'This page isn\'t loading JavaScript properly, and the form will not be able to submit.', 'wpforms-lite' );
			if ( is_super_admin() ) {
				$msg .= ' ' . sprintf(
					esc_html__( 'Please check out our %1$stroubleshooting guide%2$s for details on resolving this issue.', 'wpforms-lite' ),
					'<a href="https://wpforms.com/docs/getting-support-wpforms/">',
					'</a>'
				);
			}
			return $msg;
		}
		return esc_html__( 'The form was unable to submit. Please contact the site administrator.', 'wpforms-lite' );
	}

	/**
	 * Gera um token para $time — md5( "dmYzW" . "::{$form_id}" . secret ).
	 */
	protected static function token_get( $time, $form_id ) {
		$token_data = gmdate( 'dmYzW', $time );
		if ( $form_id ) {
			$token_data .= "::{$form_id}";
		}
		return md5( $token_data . get_option( 'wpforms_crypto_secret_key', '' ) );
	}

	/**
	 * Verifica token contra a janela do plugin: -2d, -1d, hoje e +45min.
	 */
	protected static function token_verify( $token, $form_data ) {
		$form_id  = absint( $form_data['id'] );
		$current  = time();
		$before   = apply_filters(
			'wpforms_form_token_check_before_today',
			[ 2 * DAY_IN_SECONDS, DAY_IN_SECONDS ]
		);
		$after    = apply_filters(
			'wpforms_form_token_check_after_today',
			[ 45 * MINUTE_IN_SECONDS ]
		);
		$tokens   = [ self::token_get( $current, $form_id ) ];
		foreach ( (array) $before as $off ) {
			$tokens[] = self::token_get( $current - $off, $form_id );
		}
		foreach ( (array) $after as $off ) {
			$tokens[] = self::token_get( $current + $off, $form_id );
		}
		return in_array( $token, $tokens, true );
	}

	/**
	 * Valida e formata um campo submetido.
	 */
	protected static function validate_field( $fid, $type, $input, $props, $form_data, $entry ) {
		$form_id = absint( $form_data['id'] );
		$label   = isset( $props['label'] ) ? sanitize_text_field( $props['label'] ) : '';
		$req     = ! empty( $props['required'] );
		$value   = is_scalar( $input ) ? trim( (string) $input ) : $input;

		$field = [
			'name'  => $label,
			'value' => '',
			'id'    => (int) $fid,
			'type'  => $type,
		];

		switch ( $type ) {
			case 'name':
				$field['first']  = '';
				$field['middle'] = '';
				$field['last']   = '';
				$field['value']  = sanitize_text_field( $value );
				if ( $req && '' === $field['value'] ) {
					self::$errors[ $form_id ][ $fid ] = self::required_label();
				}
				break;

			case 'text':
				$field['value'] = sanitize_text_field( $value );
				if ( $req && '' === $field['value'] ) {
					self::$errors[ $form_id ][ $fid ] = self::required_label();
				}
				break;

			case 'textarea':
				$field['value'] = sanitize_textarea_field( $value );
				if ( $req && '' === $field['value'] ) {
					self::$errors[ $form_id ][ $fid ] = self::required_label();
				}
				break;

			case 'email':
				$field['value'] = sanitize_text_field( $value );
				if ( $req && '' === $field['value'] ) {
					self::$errors[ $form_id ][ $fid ] = self::required_label();
				} elseif ( '' !== $field['value'] && ! wpforms_is_email( $field['value'] ) ) {
					self::$errors[ $form_id ][ $fid ] = esc_html__( 'The provided email is not valid.', 'wpforms-lite' );
				}
				break;

			case 'url':
				$field['value'] = esc_url_raw( $value );
				if ( $req && '' === $field['value'] ) {
					self::$errors[ $form_id ][ $fid ] = self::required_label();
				}
				break;

			case 'number':
				$field['value'] = sanitize_text_field( $value );
				if ( $req && '' === $field['value'] ) {
					self::$errors[ $form_id ][ $fid ] = self::required_label();
				} elseif ( '' !== $field['value'] && ! is_numeric( $field['value'] ) ) {
					self::$errors[ $form_id ][ $fid ] = esc_html__( 'Please enter a valid number.', 'wpforms-lite' );
				}
				break;

			case 'phone':
				$v              = sanitize_text_field( $value );
				$field['value'] = preg_match( '/^\+/', $v ) ? '+' . preg_replace( '/[^0-9]/', '', $v ) : $v;
				if ( $req && '' === $field['value'] ) {
					self::$errors[ $form_id ][ $fid ] = self::required_label();
				}
				break;

			case 'select':
				$choices = isset( $props['choices'] ) ? $props['choices'] : [];
				$allowed = [];
				foreach ( $choices as $c ) {
					$allowed[] = isset( $c['value'] ) && '' !== $c['value'] ? $c['value'] : ( isset( $c['label'] ) ? $c['label'] : '' );
				}
				$field['value'] = sanitize_text_field( $value );
				if ( $req && '' === $field['value'] ) {
					self::$errors[ $form_id ][ $fid ] = self::required_label();
				} elseif ( '' !== $field['value'] && $allowed && ! in_array( $field['value'], $allowed, true ) ) {
					self::$errors[ $form_id ][ $fid ] = esc_html__( 'Please select a valid choice.', 'wpforms-lite' );
				}
				break;

			case 'payment-single':
				$v = preg_replace( '/[^0-9.,\-]/', '', (string) $value );
				$v = str_replace( ',', '', $v );
				$field['amount']     = $v;
				$field['amount_raw'] = $value;
				$field['currency']   = 'USD';
				$field['value']      = sanitize_text_field( $value );
				if ( $req && '' === $field['value'] ) {
					self::$errors[ $form_id ][ $fid ] = self::required_label();
				} elseif ( '' !== $v && (float) $v <= 0 ) {
					self::$errors[ $form_id ][ $fid ] = esc_html__( 'Please enter a valid positive number.', 'wpforms-lite' );
				}
				break;

			case 'divider':
				// Sem input — não gera erro nem campo na entry.
				return null;

			case 'file-upload':
				return self::validate_file_upload( $fid, $props, $form_data );

			default:
				$field['value'] = is_scalar( $value ) ? sanitize_text_field( $value ) : '';
				if ( $req && '' === $field['value'] ) {
					self::$errors[ $form_id ][ $fid ] = self::required_label();
				}
				break;
		}

		return $field;
	}

	/**
	 * Campo file-upload (classic = $_FILES; modern = JSON tmp).
	 */
	protected static function validate_file_upload( $fid, $props, $form_data ) {
		$form_id = absint( $form_data['id'] );
		$label   = isset( $props['label'] ) ? sanitize_text_field( $props['label'] ) : '';
		$style   = ! empty( $props['style'] ) ? $props['style'] : 'classic';
		$req     = ! empty( $props['required'] );
		$input   = 'wpforms_' . $form_id . '_' . $fid;

		if ( 'modern' !== $style ) {
			$field  = [
				'name'          => $label,
				'value'         => '',
				'file'          => '',
				'file_original' => '',
				'ext'           => '',
				'id'            => $fid,
				'type'          => 'file-upload',
			];
			$file   = isset( $_FILES[ $input ] ) ? $_FILES[ $input ] : null;
			$has    = $file && isset( $file['error'] ) && UPLOAD_ERR_OK === (int) $file['error'] && ! empty( $file['name'] );
			$nofile = ! $file || ( isset( $file['error'] ) && UPLOAD_ERR_NO_FILE === (int) $file['error'] );
			if ( $req && $nofile ) {
				self::$errors[ $form_id ][ $fid ] = self::required_label();
				return $field;
			}
			if ( $file && ! $has && ! $nofile ) {
				self::$errors[ $form_id ][ $fid ] = esc_html__( 'File uploads failed.', 'wpforms-lite' );
				return $field;
			}
			if ( $has ) {
				$err = Uploads::validate_file( $file['tmp_name'], $file['name'], $file['size'], $props );
				if ( $err ) {
					self::$errors[ $form_id ][ $fid ] = $err;
				}
			}
			return $field;
		}

		// Modern: input text com JSON dos arquivos pré-upados no tmp.
		$field = [
			'name'      => $label,
			'value'     => '',
			'value_raw' => '',
			'id'        => $fid,
			'type'      => 'file-upload',
			'style'     => 'modern',
		];
		$json  = isset( $_POST[ $input ] ) ? sanitize_text_field( wp_unslash( $_POST[ $input ] ) ) : ''; // phpcs:ignore
		$files = json_decode( $json, true );
		$files = is_array( $files ) ? array_values( array_filter( $files ) ) : [];
		if ( $req && empty( $files ) ) {
			self::$errors[ $form_id ][ $fid ] = self::required_label();
			return $field;
		}
		$maxn = ! empty( $props['max_file_number'] ) ? absint( $props['max_file_number'] ) : 0;
		if ( $maxn && count( $files ) > $maxn ) {
			self::$errors[ $form_id ][ $fid ] = sprintf(
				esc_html__( 'File uploads exceed the maximum number allowed (%s).', 'wpforms-lite' ),
				$maxn
			);
			return $field;
		}
		foreach ( $files as $f ) {
			if ( empty( $f['file'] ) || ! is_file( trailingslashit( Uploads::tmp_dir() ) . sanitize_file_name( $f['file'] ) ) ) {
				self::$errors[ $form_id ][ $fid ] = esc_html__( 'File uploads failed.', 'wpforms-lite' );
				return $field;
			}
		}
		return $field;
	}

	/**
	 * Move uploads validados para o diretório definitivo e preenche `value`.
	 */
	protected static function complete_uploads( $form_data ) {
		$form_id = absint( $form_data['id'] );
		foreach ( self::$fields as $fid => $field ) {
			if ( 'file-upload' !== $field['type'] ) {
				continue;
			}
			$props = isset( $form_data['fields'][ $fid ] ) ? $form_data['fields'][ $fid ] : [];
			$style = ! empty( $props['style'] ) ? $props['style'] : 'classic';

			if ( 'modern' !== $style ) {
				$input = 'wpforms_' . $form_id . '_' . $fid;
				$file  = isset( $_FILES[ $input ] ) ? $_FILES[ $input ] : null;
				if ( $file && isset( $file['error'] ) && UPLOAD_ERR_OK === (int) $file['error'] ) {
					$processed = Uploads::process_file(
						[
							'name'           => $file['name'],
							'tmp_name'       => $file['tmp_name'],
							'file_user_name' => $file['name'],
						],
						$fid,
						$form_data
					);
					if ( ! empty( $processed ) ) {
						self::$fields[ $fid ] = array_merge(
							self::$fields[ $fid ],
							[
								'value'          => esc_url_raw( $processed['file_url'] ),
								'file'           => $processed['file_name_new'],
								'file_original'  => $processed['file_name'],
								'file_user_name' => sanitize_text_field( $file['name'] ),
								'ext'            => $processed['file_ext'],
								'attachment_id'  => 0,
							]
						);
					}
				}
				continue;
			}

			// Modern: JSON em $_POST[wpforms_{form}_{field}].
			$input = 'wpforms_' . $form_id . '_' . $fid;
			$json  = isset( $_POST[ $input ] ) ? sanitize_text_field( wp_unslash( $_POST[ $input ] ) ) : ''; // phpcs:ignore
			$files = json_decode( $json, true );
			if ( empty( $files ) || ! is_array( $files ) ) {
				continue;
			}
			$tmp   = Uploads::tmp_dir();
			$data  = [];
			foreach ( $files as $f ) {
				$tmp_file = trailingslashit( $tmp ) . sanitize_file_name( $f['file'] );
				if ( ! is_file( $tmp_file ) ) {
					continue;
				}
				$processed = Uploads::process_file(
					[
						'name'           => isset( $f['name'] ) ? $f['name'] : $f['file_user_name'],
						'tmp_name'       => $tmp_file,
						'file_user_name' => isset( $f['file_user_name'] ) ? $f['file_user_name'] : ( isset( $f['name'] ) ? $f['name'] : '' ),
					],
					$fid,
					$form_data
				);
				if ( ! empty( $processed ) ) {
					$data[] = [
						'name'           => sanitize_text_field( $processed['file_name'] ),
						'value'          => esc_url_raw( $processed['file_url'] ),
						'file'           => $processed['file_name_new'],
						'file_original'  => $processed['file_name'],
						'file_user_name' => $processed['file_user_name'],
						'ext'            => $processed['file_ext'],
						'attachment_id'  => 0,
						'id'             => $fid,
						'type'           => 'file-upload',
					];
				}
			}
			self::$fields[ $fid ]['value_raw'] = $data;
			self::$fields[ $fid ]['value']     = implode( "\n", wp_list_pluck( $data, 'value' ) );
		}
	}

	/**
	 * Conditional logic — grupos OR, regras AND (port do ConditionalLogicCore::process).
	 */
	protected static function field_is_visible( $form_data, $field_id ) {
		if ( ! isset( $form_data['fields'][ $field_id ] ) ) {
			return false;
		}
		$props = $form_data['fields'][ $field_id ];
		if ( empty( $props['conditional_logic'] ) || '1' !== (string) $props['conditional_logic'] || empty( $props['conditionals'] ) ) {
			return true;
		}
		// Limpa regras vazias (sem field/operator, ou sem value salvo op e/!e).
		$conditionals = [];
		foreach ( $props['conditionals'] as $gid => $group ) {
			foreach ( (array) $group as $rid => $rule ) {
				if ( ! isset( $rule['field'] ) || '' === trim( (string) $rule['field'] ) || empty( $rule['operator'] ) ) {
					continue;
				}
				if ( ! in_array( $rule['operator'], [ 'e', '!e' ], true ) && ( ! isset( $rule['value'] ) || '' === trim( (string) $rule['value'] ) ) ) {
					continue;
				}
				$conditionals[ $gid ][ $rid ] = $rule;
			}
			if ( empty( $conditionals[ $gid ] ) ) {
				unset( $conditionals[ $gid ] );
			}
		}
		if ( empty( $conditionals ) ) {
			return true;
		}
		$pass = false;
		foreach ( $conditionals as $group ) {
			$pass_group = true;
			foreach ( $group as $rule ) {
				$rf = $rule['field'];
				$ro = $rule['operator'];
				$rv = isset( $rule['value'] ) ? $rule['value'] : '';
				if ( ! isset( self::$fields[ $rf ] ) ) {
					continue;
				}
				$left  = strtolower( trim( html_entity_decode( (string) self::$fields[ $rf ]['value'] ) ) );
				$right = strtolower( trim( (string) $rv ) );
				switch ( $ro ) {
					case '==':
						$pr = $left === $right;
						break;
					case '!=':
						$pr = $left !== $right;
						break;
					case 'c':
						$pr = false !== strpos( $left, $right );
						break;
					case '!c':
						$pr = false === strpos( $left, $right );
						break;
					case '^':
						$pr = 0 === strpos( $left, $right );
						break;
					case '~':
						$pr = $right === '' || substr( $left, -strlen( $right ) ) === $right;
						break;
					case 'e':
						$pr = '' === $left;
						break;
					case '!e':
						$pr = '' !== $left;
						break;
					case '>':
						$l  = preg_replace( '/[^-0-9.]/', '', $left );
						$pr = '' !== $l && (float) $l > (float) $right;
						break;
					case '<':
						$l  = preg_replace( '/[^-0-9.]/', '', $left );
						$pr = '' !== $l && (float) $l < (float) $right;
						break;
					default:
						$pr = apply_filters( 'wpforms_process_conditional_logic', false, $ro, $left, $right );
						break;
				}
				if ( ! $pr ) {
					$pass_group = false;
					break;
				}
			}
			if ( $pass_group ) {
				$pass = true;
				break;
			}
		}
		if ( isset( $props['conditional_type'] ) && 'hide' === $props['conditional_type'] ) {
			$pass = ! $pass;
		}
		return $pass;
	}

	/**
	 * Salva a entry nas tabelas do WPForms.
	 */
	protected static function entry_save( $entry, $form_id, $form_data ) {
		global $wpdb;

		do_action( 'wpforms_process_entry_save', self::$fields, $entry, $form_id, $form_data );

		$fields_save = [];
		foreach ( self::$fields as $fid => $field ) {
			$field['id'] = (int) $fid;
			unset( $field['visible'] );
			$fields_save[ $fid ] = $field;
		}
		$fields_json = wp_json_encode( $fields_save );
		$now         = current_time( 'mysql' );
		$ua          = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 256 ) : '';
		$ip          = '';
		foreach ( [ 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $h ) {
			if ( ! empty( $_SERVER[ $h ] ) ) {
				$ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) ) )[0] );
				break;
			}
		}
		$user_uuid = '';
		if ( wpforms_is_collecting_cookies_allowed() && ! empty( $_COOKIE['_wpfuuid'] ) ) {
			$user_uuid = sanitize_key( $_COOKIE['_wpfuuid'] );
		}
		$wpdb->insert(
			$wpdb->prefix . 'wpforms_entries',
			[
				'form_id'       => $form_id,
				'post_id'       => 0,
				'user_id'       => get_current_user_id(),
				'status'        => '',
				'type'          => '',
				'viewed'        => 0,
				'starred'       => 0,
				'fields'        => $fields_json,
				'meta'          => '',
				'date'          => $now,
				'date_modified' => $now,
				'ip_address'    => $ip,
				'user_agent'    => $ua,
				'user_uuid'     => $user_uuid,
			]
		);
		$entry_id = (int) $wpdb->insert_id;

		foreach ( self::$fields as $fid => $field ) {
			$wpdb->insert(
				$wpdb->prefix . 'wpforms_entry_fields',
				[
					'entry_id' => $entry_id,
					'form_id'  => $form_id,
					'field_id' => $fid,
					'value'    => isset( $field['value'] ) ? $field['value'] : '',
					'date'     => $now,
				]
			);
		}
		return $entry_id;
	}

	/**
	 * Envia notificações (smart tags + template HTML).
	 */
	protected static function entry_email( $entry, $form_data ) {
		if ( empty( $form_data['settings']['notification_enable'] ) ) {
			return;
		}
		if ( ! apply_filters( 'wpforms_entry_email', true, self::$fields, $entry, $form_data ) ) {
			return;
		}
		$settings = $form_data['settings'];
		if ( empty( $settings['notifications'] ) && ! empty( $settings['notification_email'] ) ) {
			$notifications = [
				1 => [
					'email'          => $settings['notification_email'],
					'subject'        => $settings['notification_subject'],
					'sender_name'    => $settings['notification_fromname'],
					'sender_address' => $settings['notification_fromaddress'],
					'replyto'        => $settings['notification_replyto'],
					'message'        => '{all_fields}',
				],
			];
		} else {
			$notifications = isset( $settings['notifications'] ) ? $settings['notifications'] : [];
		}

		foreach ( (array) $notifications as $notification_id => $notification ) {
			if ( empty( $notification['email'] ) ) {
				continue;
			}
			if ( ! apply_filters( 'wpforms_entry_email_process', true, self::$fields, $form_data, $notification_id, 'entry' ) ) {
				continue;
			}
			$subject  = ! empty( $notification['subject'] )
				? $notification['subject']
				: sprintf( /* translators: %s - form name. */ esc_html__( 'New %s Entry', 'wpforms-lite' ), $settings['form_title'] ?? $form_data['title'] );
			$subject  = self::smart_tags( $subject, $form_data );
			$address  = explode( ',', self::smart_tags( $notification['email'], $form_data ) );
			$address  = array_map( 'sanitize_email', array_map( 'trim', $address ) );
			$from_ad  = ! empty( $notification['sender_address'] ) ? $notification['sender_address'] : get_option( 'admin_email' );
			$from_nm  = ! empty( $notification['sender_name'] ) ? self::smart_tags( $notification['sender_name'], $form_data ) : get_bloginfo( 'name' );
			$replyto  = ! empty( $notification['replyto'] ) ? self::smart_tags( $notification['replyto'], $form_data ) : false;
			$message  = ! empty( $notification['message'] ) ? $notification['message'] : '{all_fields}';
			$message  = str_replace( '{all_fields}', self::all_fields_html( $form_data ), $message );
			$message  = self::smart_tags( $message, $form_data );

			$headers = [
				'Content-Type: text/html; charset=UTF-8',
				sprintf( 'From: %s <%s>', $from_nm, $from_ad ),
			];
			if ( $replyto && is_email( $replyto ) ) {
				$headers[] = sprintf( 'Reply-To: %s', $replyto );
			}
			$body = self::email_template( $message, $form_data );
			foreach ( $address as $to ) {
				if ( $to ) {
					wp_mail( $to, $subject, $body, $headers );
				}
			}
		}
	}

	/**
	 * `{all_fields}` — tabela por campo (mesmo template do plugin).
	 */
	protected static function all_fields_html( $form_data ) {
		if ( empty( self::$fields ) || empty( $form_data['fields'] ) ) {
			return '';
		}
		$tpl     = '<table align="left" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-top:1px solid #dddddd; display:block;min-width: 100%;border-collapse: collapse;width:100%;"><tbody>
	<tr><td style="color:#333333;padding-top: 20px;padding-bottom: 3px;"><strong>{field_name}</strong></td></tr>
	<tr><td style="color:#555555;padding-top: 3px;padding-bottom: 20px;">{field_value}</td></tr>
</tbody></table>';
		$message = '';
		$x       = 1;
		foreach ( $form_data['fields'] as $field_id => $field ) {
			if ( empty( self::$fields[ $field_id ] ) ) {
				if ( 'divider' === $field['type'] && ! empty( $field['label'] ) ) {
					$fname = str_repeat( '&mdash;', 3 ) . ' ' . $field['label'] . ' ' . str_repeat( '&mdash;', 3 );
					$fval  = ! empty( $field['description'] ) ? $field['description'] : '';
				} else {
					continue;
				}
			} else {
				if ( isset( self::$fields[ $field_id ]['visible'] ) && ! self::$fields[ $field_id ]['visible'] ) {
					continue;
				}
				if ( ! isset( self::$fields[ $field_id ]['value'] ) || '' === (string) self::$fields[ $field_id ]['value'] ) {
					continue;
				}
				$fname = isset( self::$fields[ $field_id ]['name'] ) ? self::$fields[ $field_id ]['name'] : '';
				$fval  = self::$fields[ $field_id ]['value'];
				if ( '' === (string) $fval && ! is_numeric( $fval ) ) {
					$fval = '<em>' . esc_html__( '(empty)', 'wpforms-lite' ) . '</em>';
				}
			}
			$item = $tpl;
			if ( 1 === $x ) {
				$item = str_replace( 'border-top:1px solid #dddddd;', '', $item );
			}
			$item     = str_replace( '{field_name}', $fname, $item );
			$item     = str_replace( '{field_value}', nl2br( (string) $fval ), $item );
			$message .= wpautop( $item );
			$x++;
		}
		return $message;
	}

	/**
	 * Envelope do e-mail HTML (header/body/footer do plugin, simplificado mas
	 * preservando a estrutura de tabelas e o título do site).
	 */
	protected static function email_template( $message, $form_data ) {
		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$body     = '<div id="wpforms-template-body" style="background-color:#f5f5f5;padding:30px 0;">'
			. '<table border="0" cellpadding="0" cellspacing="0" width="600" align="center" style="border-collapse:collapse;">'
			. '<tr><td style="padding:20px;background:#ffffff;border:1px solid #dddddd;">'
			. $message
			. '</td></tr>'
			. '<tr><td style="padding:15px;color:#999999;font-size:12px;" align="center">'
			. sprintf( '%s', esc_html( $blogname ) )
			. '</td></tr></table></div>';
		return '<!DOCTYPE html><html><body>' . $body . '</body></html>';
	}

	/**
	 * Smart tags: {field_id="N"}, {entry_id}, {form_id}, {form_name},
	 * {page_id}, {page_title}, {page_url}, {date}, {admin_email}, {site_name},
	 * {url_referer}, {user_ip}, {user_id}, {query_var key=""}.
	 */
	public static function smart_tags( $content, $form_data ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}
		return preg_replace_callback(
			'/\{(\w+)([^{}]*)\}/',
			function ( $m ) use ( $form_data ) {
				$tag   = $m[1];
				$attrs = isset( $m[2] ) ? $m[2] : '';
				switch ( $tag ) {
					case 'field_id':
						if ( preg_match( '/"(\d+)"/', $attrs, $mm ) ) {
							$fid = absint( $mm[1] );
							return isset( self::$fields[ $fid ]['value'] ) ? (string) self::$fields[ $fid ]['value'] : '';
						}
						return '';
					case 'field_value_id':
						if ( preg_match( '/"(\d+)"/', $attrs, $mm ) ) {
							$fid = absint( $mm[1] );
							return isset( self::$fields[ $fid ]['value_raw'] ) ? (string) self::$fields[ $fid ]['value_raw'] : '';
						}
						return '';
					case 'entry_id':
						return (string) self::$entry_id;
					case 'form_id':
						return isset( $form_data['id'] ) ? (string) absint( $form_data['id'] ) : '';
					case 'form_name':
						return isset( $form_data['title'] ) ? $form_data['title'] : '';
					case 'page_id':
						$pid = isset( $_POST['wpforms']['post_id'] ) ? absint( $_POST['wpforms']['post_id'] ) : get_the_ID();
						return (string) $pid;
					case 'page_title':
						$pid = isset( $_POST['wpforms']['post_id'] ) ? absint( $_POST['wpforms']['post_id'] ) : get_the_ID();
						return $pid ? get_the_title( $pid ) : '';
					case 'page_url':
						$pid = isset( $_POST['wpforms']['post_id'] ) ? absint( $_POST['wpforms']['post_id'] ) : get_the_ID();
						return $pid ? get_permalink( $pid ) : '';
					case 'date':
						return date_i18n( get_option( 'date_format' ) );
					case 'admin_email':
						return get_option( 'admin_email' );
					case 'site_name':
						return get_bloginfo( 'name' );
					case 'user_ip':
						return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
					case 'user_id':
						return (string) get_current_user_id();
					case 'user_email':
						$u = wp_get_current_user();
						return $u ? $u->user_email : '';
					case 'url_referer':
						return isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
					case 'query_var':
						if ( preg_match( '/key="([^"]+)"/', $attrs, $mm ) && isset( $_GET[ $mm[1] ] ) ) {
							return sanitize_text_field( wp_unslash( $_GET[ $mm[1] ] ) );
						}
						return '';
					default:
						return apply_filters( "wpforms_smarttags_process_{$tag}_value", $m[0], $form_data, self::$fields, self::$entry_id );
				}
			},
			$content
		);
	}

	/**
	 * Resolve a confirmação vigente (última configurada com regras satisfeitas;
	 * default = menor key) e gera a mensagem com smart tags.
	 */
	protected static function resolve_confirmation( $form_data, $entry_id ) {
		$settings = $form_data['settings'];
		if ( empty( $settings['confirmations'] ) ) {
			$settings['confirmations'] = [
				1 => [
					'type'           => ! empty( $settings['confirmation_type'] ) ? $settings['confirmation_type'] : 'message',
					'message'        => ! empty( $settings['confirmation_message'] ) ? $settings['confirmation_message'] : esc_html__( 'Thanks for contacting us! We will be in touch with you shortly.', 'wpforms-lite' ),
					'message_scroll' => ! empty( $settings['confirmation_message_scroll'] ) ? $settings['confirmation_message_scroll'] : 1,
					'page'           => ! empty( $settings['confirmation_page'] ) ? $settings['confirmation_page'] : '',
					'redirect'       => ! empty( $settings['confirmation_redirect'] ) ? $settings['confirmation_redirect'] : '',
				],
			];
		}
		$confirmations = $settings['confirmations'];
		krsort( $confirmations );
		$default_key = min( array_keys( $confirmations ) );
		$current     = $confirmations[ $default_key ];
		foreach ( $confirmations as $cid => $confirmation ) {
			if ( $cid === $default_key ) {
				continue;
			}
			// Confirmações condicionais: usa a primeira satisfeita.
			if ( empty( $confirmation['conditional_logic'] ) || empty( $confirmation['conditionals'] ) ) {
				$current = $confirmation;
				break;
			}
			$tmp            = [ 'fields' => [ 999999 => $confirmation ] ];
			$field_check    = [ 'id' => $form_data['id'], 'fields' => $form_data['fields'] ];
			$field_check['fields'][ 'confirmation_' . $cid ] = [
				'conditional_logic' => $confirmation['conditional_logic'],
				'conditional_type'  => isset( $confirmation['conditional_type'] ) ? $confirmation['conditional_type'] : 'show',
				'conditionals'      => $confirmation['conditionals'],
			];
			if ( self::field_is_visible( $field_check, 'confirmation_' . $cid ) ) {
				$current = $confirmation;
				break;
			}
		}
		self::$confirmation = $current;
	}

	/**
	 * Mensagem de confirmação com smart tags + wpautop.
	 */
	protected static function get_confirmation_message( $form_data ) {
		if ( empty( self::$confirmation ) ) {
			self::resolve_confirmation( $form_data, self::$entry_id );
		}
		$conf = self::$confirmation;
		if ( empty( $conf ) || empty( $conf['type'] ) || 'message' !== $conf['type'] ) {
			return '';
		}
		$message = ! empty( $conf['message'] ) ? $conf['message'] : '';
		$message = self::smart_tags( $message, $form_data );
		$message = do_shortcode( $message );
		return wpautop( $message );
	}
}
