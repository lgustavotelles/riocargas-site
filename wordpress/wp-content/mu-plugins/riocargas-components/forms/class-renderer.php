<?php
/**
 * Renderer dos formulários — reproduz o markup do WPForms 1.7.6.
 *
 * @package RioCargas\Components\Forms
 */

namespace RioCargas\Components\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Gera HTML idêntico ao do WPForms a partir do post_content JSON de cada form.
 */
class Renderer {

	/**
	 * Forms renderizados na pagina.
	 *
	 * @var array
	 */
	protected static $forms = [];

	/**
	 * Caminho/URL base dos assets espelhados do plugin.
	 */
	public static function asset_url( $rel = '' ) {
		return content_url( 'mu-plugins/riocargas-components/assets/wpforms-plugin/' . $rel );
	}

	/**
	 * Lê e decodifica os dados do form (wpforms post type, post_content JSON).
	 *
	 * @param int $form_id Form ID.
	 * @return array|null
	 */
	public static function get_form_data( $form_id ) {
		$post = get_post( $form_id );
		if ( ! $post || 'wpforms' !== $post->post_type ) {
			return null;
		}
		$data = json_decode( $post->post_content, true );
		if ( ! is_array( $data ) || empty( $data['fields'] ) ) {
			return null;
		}
		$data['id']    = $form_id;
		$data['title'] = $post->post_title;
		return $data;
	}

	/**
	 * Token antispam — mesmo algoritmo do WPForms\Pro\AntiSpam\Token.
	 *
	 * @param int $form_id Form ID.
	 */
	public static function token( $form_id ) {
		$secret = get_option( 'wpforms_crypto_secret_key', '' );
		return md5( gmdate( 'dmYzW' ) . '::' . absint( $form_id ) . $secret );
	}

	/**
	 * Output público — equivale a wpforms_display().
	 */
	public static function output( $form_id, $title = false, $description = false ) {
		$form_data = self::get_form_data( $form_id );
		if ( ! $form_data ) {
			return;
		}

		// Submit não-AJAX concluído neste request: confirmação no lugar do form.
		$conf_msg = Process::confirmation_message_for( $form_id );
		if ( "" !== $conf_msg ) {
			$scroll = ! empty( Process::get_current_confirmation()["message_scroll"] ) ? " wpforms-confirmation-scroll" : "";
			printf(
				"<div class=\"wpforms-container\" id=\"wpforms-%d\"><div class=\"wpforms-confirmation-container%s\" id=\"wpforms-confirmation-%d\">%s</div></div>",
				absint( $form_id ),
				$scroll,
				absint( $form_id ),
				$conf_msg
			);
			return;
		}

		self::render_form( $form_data, (bool) $title, (bool) $description );
	}

	/**
	 * Renderiza <form> completo.
	 */
	public static function render_form( $form_data, $title, $description ) {
		$form_id  = absint( $form_data['id'] );
		$settings = isset( $form_data['settings'] ) ? $form_data['settings'] : [];
		$fields   = $form_data['fields'];

		self::$forms[ $form_id ] = $form_data;

		// Container.
		$classes = [ 'wpforms-container' ];
		if ( ! empty( $settings['form_class'] ) ) {
			$classes = array_merge( $classes, array_map( 'trim', explode( ' ', $settings['form_class'] ) ) );
		}
		$classes = array_map( 'esc_attr', apply_filters( 'wpforms_frontend_container_class', $classes, $form_data ) );

		// Action: a URL atual; ?wpforms_form_id= quando ha file-upload (rastreio de post_max_size).
		$action = remove_query_arg( 'wpforms_return' );
		if ( in_array( 'file-upload', wp_list_pluck( $fields, 'type' ), true ) ) {
			$action = add_query_arg( 'wpforms_form_id', $form_id, $action );
		}
		$action = apply_filters( 'wpforms_frontend_form_action', $action, $form_data, null );

		// Atributos do form (mesma ordem do plugin: id, class, data, atts;
		// o token entra em atts como 'data-token' — sai depois do action).
		$form_atts = [
			'id'    => sprintf( 'wpforms-form-%d', $form_id ),
			'class' => [ 'wpforms-validate', 'wpforms-form' ],
			'data'  => [ 'formid' => $form_id ],
			'atts'  => [
				'method'  => 'post',
				'enctype' => 'multipart/form-data',
				'action'  => esc_url( $action ),
			],
		];
		if ( ! empty( $settings['ajax_submit'] ) ) {
			$form_atts['class'][] = 'wpforms-ajax-form';
		}
		if ( empty( $settings['antispam_v3'] ) ) {
			$form_atts['atts']['data-token'] = self::token( $form_id );
		}
		$form_atts = apply_filters( 'wpforms_frontend_form_atts', $form_atts, $form_data );

		// Confirmação pós-submit no próprio POST (redirect da resposta ajax é tratado em JS).
		printf( '<div class="%s " id="wpforms-%d">', implode( ' ', $classes ), $form_id );
		echo '<form ';
		echo self::html_attributes(
			$form_atts['id'],
			array_map( 'esc_attr', $form_atts['class'] ),
			$form_atts['data'],
			$form_atts['atts']
		);
		echo '>';
		do_action( 'wpforms_frontend_output', $form_data, null, $form_atts );

		// <noscript> com a mensagem do plugin.
		$noscript_msg = apply_filters( 'wpforms_frontend_noscript_error_message', __( 'Please enable JavaScript in your browser to complete this form.', 'wpforms-lite' ), $form_data );
		if ( ! empty( $noscript_msg ) && ! empty( $form_data['fields'] ) ) {
			echo '<noscript class="wpforms-error-noscript">' . esc_html( $noscript_msg ) . '</noscript>';
		}

		if ( $title || $description ) {
			echo '<div class="wpforms-head-container">';
			if ( $title ) {
				printf( '<div class="wpforms-title">%s</div>', esc_html( $form_data['title'] ) );
			}
			if ( $description && ! empty( $settings['form_desc'] ) ) {
				printf( '<div class="wpforms-description">%s</div>', wp_kses_post( $settings['form_desc'] ) );
			}
			echo '</div>';
		}

		echo '<div class="wpforms-field-container">';
		foreach ( $fields as $field ) {
			self::render_field( $field, $form_data );
		}
		echo '</div>'; // .wpforms-field-container
		if ( ! empty( $settings['honeypot'] ) ) {
			self::render_honeypot( $form_id );
		}

		self::render_submit( $form_data );
		do_action( 'wpforms_frontend_output_end', $form_data );
		echo '</form></div>';
	}

	/**
	 * Atributos HTML (port do wpforms_html_attributes()).
	 */
	public static function html_attributes( $id = false, $classes = [], $datas = [], $atts = [] ) {
		$parts = [];
		if ( $id ) {
			$parts[] = 'id="' . esc_attr( $id ) . '"';
		}
		if ( ! empty( $classes ) ) {
			$parts[] = 'class="' . esc_attr( trim( implode( ' ', $classes ) ) ) . '"';
		}
		foreach ( (array) $datas as $k => $v ) {
			$parts[] = 'data-' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
		foreach ( (array) $atts as $k => $v ) {
			if ( is_bool( $v ) ) {
				if ( $v ) {
					$parts[] = esc_attr( $k );
				}
			} else {
				$parts[] = esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
			}
		}
		return implode( ' ', $parts );
	}

	/**
	 * Honeypot — markup exato do plugin.
	 */
	protected static function render_honeypot( $form_id ) {
		$names = [ 'Name', 'Phone', 'Comment', 'Message', 'Email', 'Website' ];
		$label = $names[ array_rand( $names ) ];
		printf(
			'<div class="wpforms-field wpforms-field-hp"><label for="wpforms-%1$d-field-hp" class="wpforms-field-label">%2$s</label><input type="text" name="wpforms[hp]" id="wpforms-%1$d-field-hp" class="wpforms-field-medium"></div>',
			$form_id,
			esc_html( $label )
		);
	}

	/**
	 * Área do submit.
	 */
	protected static function render_submit( $form_data ) {
		$form_id  = absint( $form_data['id'] );
		$settings = $form_data['settings'];
		$submit   = ! empty( $settings['submit_text'] ) ? $settings['submit_text'] : __( 'Submit', 'wpforms-lite' );
		$alt      = ! empty( $settings['submit_text_processing'] ) ? $settings['submit_text_processing'] : __( 'Sending...', 'wpforms-lite' );
		$classes  = [ 'wpforms-submit' ];
		if ( ! empty( $settings['submit_class'] ) ) {
			$classes = array_merge( $classes, array_map( 'trim', explode( ' ', $settings['submit_class'] ) ) );
		}
		echo '<div class="wpforms-submit-container">';
		printf( '<input type="hidden" name="wpforms[id]" value="%d">', $form_id );
		if ( is_user_logged_in() ) {
			printf( '<input type="hidden" name="wpforms[nonce]" value="%s">', esc_attr( wp_create_nonce( "wpforms::form_" . $form_id ) ) );
		}
		printf( '<input type="hidden" name="wpforms[author]" value="%d">', absint( get_the_author_meta( 'ID' ) ) );
		if ( get_the_ID() ) {
			printf( '<input type="hidden" name="wpforms[post_id]" value="%d">', absint( get_the_ID() ) );
		}
		printf(
			'<button type="submit" name="wpforms[submit]" id="wpforms-submit-%1$d" class="%2$s" data-alt-text="%3$s" data-submit-text="%4$s" aria-live="assertive" value="wpforms-submit">%5$s</button>',
			$form_id,
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $alt ),
			esc_attr( $submit ),
			esc_html( $submit )
		);
		printf(
			'<img src="%s" class="wpforms-submit-spinner" style="display: none;" width="26" height="26" alt="%s">',
			esc_url( self::asset_url( 'assets/images/submit-spin.svg' ) ),
			esc_attr__( 'Loading', 'wpforms-lite' )
		);
		echo '</div>';
	}

	/**
	 * Renderiza um campo (wrapper + label + input + descrição/sublabel).
	 */
	protected static function render_field( $field, $form_data ) {
		$type  = isset( $field['type'] ) ? $field['type'] : 'text';
		$fid   = absint( $field['id'] );
		$fid_s = "wpforms-{$form_data['id']}-field_{$fid}";

		// Classes do wrapper.
		$cls = [ 'wpforms-field', 'wpforms-field-' . $type ];
		// Select modern: a classe de estilo vai no WRAPPER.
		if ( 'select' === $type && ! empty( $field['style'] ) && 'modern' === $field['style'] ) {
			$cls[] = 'wpforms-field-select-style-modern';
		}
		foreach ( explode( ' ', isset( $field['css'] ) ? $field['css'] : '' ) as $c ) {
			if ( '' !== trim( $c ) ) {
				$cls[] = trim( $c );
			}
		}
		$size  = ! empty( $field['size'] ) ? $field['size'] : 'medium';
		$label = isset( $field['label'] ) ? $field['label'] : '';

		printf(
			'<div id="%s-container" class="%s" data-field-id="%d">',
			esc_attr( $fid_s ),
			esc_attr( implode( ' ', $cls ) ),
			$fid
		);

		// Label — divider não tem label, emite <h3> próprio.
		$label_hide = ! empty( $field['label_hide'] );
		if ( 'divider' !== $type && '' !== $label ) {
			$req = ! empty( $field['required'] ) ? ' <span class="wpforms-required-label">*</span>' : '';
			printf(
				'<label class="wpforms-field-label%s" for="%s">%s%s</label>',
				$label_hide ? ' wpforms-label-hide' : '',
				esc_attr( $fid_s ),
				esc_html( $label ),
				$req // phpcs:ignore — markup fixo controlado.
			);
		}

		$input_cls = [ 'wpforms-field-' . $size ];
		if ( ! empty( $field['required'] ) ) {
			$input_cls[] = 'wpforms-field-required';
		}
		$required    = ! empty( $field['required'] ) ? 'required' : '';
		$placeholder = isset( $field['placeholder'] ) && '' !== $field['placeholder']
			? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"'
			: '';

		switch ( $type ) {
			case 'name':
				// format simple = input único com simple_placeholder.
				$ph = $placeholder;
				if ( 'simple' === ( $field['format'] ?? 'simple' ) && isset( $field['simple_placeholder'] ) && '' !== $field['simple_placeholder'] ) {
					$ph = ' placeholder="' . esc_attr( $field['simple_placeholder'] ) . '"';
				}
				printf(
					'<input type="text" id="%s" class="%s" name="wpforms[fields][%d]"%s %s>',
					esc_attr( $fid_s ),
					esc_attr( implode( ' ', $input_cls ) ),
					$fid,
					$ph,
					$required
				);
				break;

			case 'text':
				printf(
					'<input type="text" id="%s" class="%s" name="wpforms[fields][%d]"%s %s>',
					esc_attr( $fid_s ),
					esc_attr( implode( ' ', $input_cls ) ),
					$fid,
					$placeholder,
					$required
				);
				break;

			case 'email':
				printf(
					'<input type="email" id="%s" class="%s" name="wpforms[fields][%d]"%s %s>',
					esc_attr( $fid_s ),
					esc_attr( implode( ' ', $input_cls ) ),
					$fid,
					$placeholder,
					$required
				);
				break;

			case 'url':
				printf(
					'<input type="url" id="%s" class="%s" name="wpforms[fields][%d]"%s %s>',
					esc_attr( $fid_s ),
					esc_attr( implode( ' ', $input_cls ) ),
					$fid,
					$placeholder,
					$required
				);
				break;

			case 'number':
				printf(
					'<input type="number" pattern="\d*" id="%s" class="%s" name="wpforms[fields][%d]"%s %s>',
					esc_attr( $fid_s ),
					esc_attr( implode( ' ', $input_cls ) ),
					$fid,
					$placeholder,
					$required
				);
				break;

			case 'phone':
				$input_cls[] = 'wpforms-smart-phone-field';
				printf(
					'<input type="tel" id="%s" class="%s" data-rule-smart-phone-field="true" name="wpforms[fields][%d]"%s %s>',
					esc_attr( $fid_s ),
					esc_attr( implode( ' ', $input_cls ) ),
					$fid,
					$placeholder,
					$required
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="%s" class="%s" name="wpforms[fields][%d]"%s %s></textarea>',
					esc_attr( $fid_s ),
					esc_attr( implode( ' ', $input_cls ) ),
					$fid,
					$placeholder,
					$required
				);
				break;

			case 'select':
				$is_modern   = ! empty( $field['style'] ) && 'modern' === $field['style'];
				$is_multiple = ! empty( $field['multiple'] );
				$choices     = isset( $field['choices'] ) ? $field['choices'] : [];

				$sel_cls = $input_cls;
				$attrs   = '';
				if ( $is_modern ) {
					$sel_cls[] = 'choicesjs-select';
					$attrs    .= sprintf( ' data-size-class="wpforms-field-row wpforms-field-%s"', esc_attr( $size ) );
					$attrs    .= sprintf( ' data-search-enabled="%s"', count( $choices ) >= 8 ? '1' : '' );
				}
				$name_attr = $is_multiple ? "wpforms[fields][{$fid}][]" : "wpforms[fields][{$fid}]";
				printf(
					'<select id="%s" class="%s"%s name="%s"%s%s>',
					esc_attr( $fid_s ),
					esc_attr( implode( ' ', $sel_cls ) ),
					$attrs,
					esc_attr( $name_attr ),
					$is_multiple ? ' multiple' : '',
					! empty( $field['required'] ) ? ' required="required"' : ''
				);
				// Placeholder: explícito ou fake (modern → label da 1ª choice).
				$field_placeholder = ! empty( $field['placeholder'] ) ? $field['placeholder'] : '';
				$has_default       = false;
				foreach ( $choices as $c ) {
					if ( ! empty( $c['default'] ) ) {
						$has_default = true;
						break;
					}
				}
				if ( $is_modern && '' === $field_placeholder && $choices ) {
					$first             = reset( $choices );
					$field_placeholder = isset( $first['label'] ) ? $first['label'] : '';
				}
				if ( '' !== $field_placeholder ) {
					printf(
						'<option value="" class="placeholder" disabled %s>%s</option>',
						( $has_default || $is_multiple ) ? '' : " selected='selected'",
						esc_html( $field_placeholder )
					);
				}
				foreach ( $choices as $choice ) {
					$clabel = isset( $choice['label'] ) ? $choice['label'] : '';
					$sel    = isset( $choice['value'] ) && '' !== $choice['value'] ? $choice['value'] : $clabel;
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $sel ),
						! empty( $choice['default'] ) ? "selected='selected'" : '',
						esc_html( $clabel )
					);
				}
				echo '</select>';
				break;

			case 'payment-single':
				$input_cls[] = 'wpforms-payment-user-input';
				$input_cls[] = 'wpforms-payment-price';
				printf(
					'<input type="text" id="%s" class="%s" data-rule-currency="[&quot;$&quot;,false]" data-rule-required-positive-number="1" name="wpforms[fields][%d]"%s>',
					esc_attr( $fid_s ),
					esc_attr( implode( ' ', $input_cls ) ),
					$fid,
					$placeholder
				);
				break;

			case 'divider':
				printf( '<h3 id="%s" name="wpforms[fields][%d]">%s</h3>', esc_attr( $fid_s ), $fid, esc_html( $label ) );
				break;

			case 'file-upload':
				self::render_file_upload( $field, $form_data, $fid_s, $input_cls, $required );
				break;

			default:
				printf(
					'<input type="text" id="%s" class="%s" name="wpforms[fields][%d]"%s %s>',
					esc_attr( $fid_s ),
					esc_attr( implode( ' ', $input_cls ) ),
					$fid,
					$placeholder,
					$required
				);
				break;
		}

		// Descrição do campo.
		if ( ! empty( $field['description'] ) && 'after' !== ( isset( $field['desc_position'] ) ? $field['desc_position'] : '' ) ) {
			printf( '<div class="wpforms-field-description">%s</div>', wp_kses_post( $field['description'] ) );
		}
		if ( ! empty( $field['sublabel_hide'] ) ) {
			// Nada — os sublabels só existem em name/email etc.; preservado por integridade.
		}

		echo '</div>';
	}

	/**
	 * Campo file-upload: classic (input file) ou modern (Dropzone).
	 */
	protected static function render_file_upload( $field, $form_data, $fid_s, $input_cls, $required ) {
		$style = ! empty( $field['style'] ) ? $field['style'] : 'classic';
		$fid   = absint( $field['id'] );
		$fname = 'wpforms_' . absint( $form_data['id'] ) . '_' . $fid;
		$exts  = ! empty( $field['extensions'] ) ? $field['extensions'] : Uploads::default_extensions();
		$maxsz = ! empty( $field['max_size'] ) ? abs( floatval( $field['max_size'] ) ) * 1024 * 1024 : wp_max_upload_size();
		$maxsz = (int) min( $maxsz, wp_max_upload_size() );
		$maxn  = ! empty( $field['max_file_number'] ) ? absint( $field['max_file_number'] ) : 1;

		if ( 'modern' === $style ) {
			$chunk = wp_max_upload_size();
			printf(
				'<div
					class="wpforms-uploader"
					data-field-id="%1$d"
					data-form-id="%2$d"
					data-input-name="%3$s"
					data-extensions="%4$s"
					data-max-size="%5$d"
					data-max-file-number="%6$d"
					data-post-max-size="%5$d"
					data-max-parallel-uploads="4"
					data-parallel-uploads="true"
					data-file-chunk-size="%7$d">',
				$fid,
				absint( $form_data['id'] ),
				esc_attr( $fname ),
				esc_attr( $exts ),
				$maxsz,
				$maxn,
				$chunk > 2097152 ? 2097152 : $chunk
			);
			echo '<div class="dz-message"> ';
			echo '<svg viewBox="0 0 1024 1024" focusable="false" data-icon="inbox" width="50px" height="50px" fill="#b1b1b1" aria-hidden="true"> ';
			echo '<path d="M885.2 446.3l-.2-.8-112.2-285.1c-5-16.1-19.9-27.2-36.8-27.2H281.2c-17 0-32.1 11.3-36.9 27.6L139.4 443l-.3.7-.2.8c-1.3 4.9-1.7 9.9-1 14.8-.1 1.6-.2 3.2-.2 4.8V830a60.9 60.9 0 0 0 60.8 60.8h627.2c33.5 0 60.8-27.3 60.9-60.8V464.1c0-1.3 0-2.6-.1-3.7.4-4.9 0-9.6-1.3-14.1zm-295.8-43l-.3 15.7c-.8 44.9-31.8 75.1-77.1 75.1-22.1 0-41.1-7.1-54.8-20.6S436 441.2 435.6 419l-.3-15.7H229.5L309 210h399.2l81.7 193.3H589.4zm-375 76.8h157.3c24.3 57.1 76 90.8 140.4 90.8 33.7 0 65-9.4 90.3-27.2 22.2-15.6 39.5-37.4 50.7-63.6h156.5V814H214.4V480.1z"></path> </svg> ';
			echo '<span class="modern-title">' . esc_html__( 'Clique ou arraste os arquivos para esta área para fazer upload.', 'wpforms-lite' ) . '</span> ';
			echo '<span class="modern-hint">' . esc_html( sprintf( __( 'Você pode enviar até %d arquivos.', 'wpforms-lite' ), $maxn ) ) . '</span>';
			echo '</div></div> ';
			printf(
				'<input
					type="text"
					autocomplete="off"
					class="dropzone-input"
					style="position:absolute!important;clip:rect(0,0,0,0)!important;height:1px!important;width:1px!important;border:0!important;overflow:hidden!important;padding:0!important;margin:0!important;"
					id="%s"
					name="%s" 		value="">',
				esc_attr( $fid_s ),
				esc_attr( $fname )
			);
		} else {
			// Classic: o input file NÃO carrega wpforms-field-{size}; só required.
			$accept = '.' . str_replace( ',', ',.', $exts );
			$fcls   = ! empty( $field['required'] ) ? ' class="wpforms-field-required"' : '';
			printf(
				'<input type="file" id="%s"%s data-rule-extension="%s" data-rule-maxsize="%d" name="%s" accept="%s" %s>',
				esc_attr( $fid_s ),
				$fcls, // phpcs:ignore — markup fixo.
				esc_attr( $exts ),
				$maxsz,
				esc_attr( $fname ),
				esc_attr( $accept ),
				! empty( $field['required'] ) ? 'required' : ''
			);
		}
	}

	/**
	 * Marca enqueue antecipado — os popups do Popup Maker renderizam forms em
	 * wp_footer, então registramos os assets antes e imprimimos no rodapé.
	 */
	public static function enqueue_marker() {
		wp_register_style( 'rc-wpforms-base', self::asset_url( 'assets/css/wpforms-base.min.css' ), [], '1.7.6' );
		wp_register_style( 'rc-wpforms-dropzone', self::asset_url( 'assets/pro/css/dropzone.min.css' ), [], '1.7.6' );
		wp_register_style( 'rc-wpforms-choices', self::asset_url( 'assets/css/choices.min.css' ), [], '1.7.6' );
		wp_register_style( 'rc-wpforms-phone', self::asset_url( 'assets/pro/css/fields/phone/intl-tel-input.min.css' ), [], '1.7.6' );

		wp_register_script( 'rc-wpforms-validate', self::asset_url( 'assets/lib/jquery.validate.min.js' ), [ 'jquery' ], '1.19.3', true );
		wp_register_script( 'rc-wpforms-inputmask', self::asset_url( 'assets/lib/jquery.inputmask.min.js' ), [ 'jquery' ], '5.0.6', true );
		wp_register_script( 'rc-wpforms-mailcheck', self::asset_url( 'assets/lib/mailcheck.min.js' ), [ 'jquery' ], '1.1.2', true );
		wp_register_script( 'rc-wpforms-punycode', self::asset_url( 'assets/lib/punycode.min.js' ), [], '1.4.1', true );
		wp_register_script( 'rc-wpforms-choices', self::asset_url( 'assets/lib/choices.min.js' ), [], '9.0.1', true );
		wp_register_script( 'rc-wpforms-itel', self::asset_url( 'assets/pro/lib/intl-tel-input/jquery.intl-tel-input.min.js' ), [ 'jquery' ], '17.0.13', true );
		wp_register_script( 'rc-wpforms-itel-utils', self::asset_url( 'assets/pro/lib/intl-tel-input/jquery.intl-tel-input-utils.min.js' ), [ 'rc-wpforms-itel' ], '17.0.13', true );
		wp_register_script( 'rc-wpforms-dropzone', self::asset_url( 'assets/pro/lib/dropzone.min.js' ), [], '5.9.2', true );
		wp_register_script( 'rc-wpforms-file-upload', self::asset_url( 'assets/pro/js/wpforms-file-upload.min.js' ), [ 'jquery', 'rc-wpforms', 'wp-util', 'rc-wpforms-dropzone' ], '1.7.6', true );
		wp_register_script( 'rc-wpforms-utils', self::asset_url( 'assets/js/utils.min.js' ), [ 'jquery' ], '1.7.6', true );
		wp_register_script(
			'rc-wpforms',
			self::asset_url( 'assets/js/wpforms.min.js' ),
			[ 'jquery', 'rc-wpforms-validate', 'rc-wpforms-mailcheck', 'rc-wpforms-punycode', 'rc-wpforms-utils', 'rc-wpforms-inputmask' ],
			'1.7.6',
			true
		);
	}

	/**
	 * Enfileira os assets que o plugin original enfileira quando há forms na página.
	 * Chamado em wp_footer(5) — antes dos popups (que rodam depois) — garante que
	 * os scripts saiam em wp_print_footer_scripts.
	 */
	public static function enqueue_assets() {
		if ( ! self::page_has_forms() ) {
			return;
		}
		wp_enqueue_style( 'rc-wpforms-base' );
		wp_enqueue_style( 'rc-wpforms-choices' );
		wp_enqueue_style( 'rc-wpforms-dropzone' );
		wp_enqueue_style( 'rc-wpforms-phone' );
		wp_enqueue_script( 'rc-wpforms-choices' );
		wp_enqueue_script( 'rc-wpforms-itel' );
		wp_enqueue_script( 'rc-wpforms-itel-utils' );
		wp_enqueue_script( 'rc-wpforms-file-upload' );
		wp_enqueue_script( 'rc-wpforms' );

		// Localize que o wpforms-file-upload.min.js espera (o plugin registra
		// em 'wpforms-dropzone' quando um form com upload moderno esta presente).
		wp_localize_script(
			'rc-wpforms-dropzone',
			'wpforms_file_upload',
			[
				'url'             => admin_url( 'admin-ajax.php' ),
				'errors'          => [
					'default_error'     => esc_html__( 'Something went wrong, please try again.', 'wpforms' ),
					'file_not_uploaded' => esc_html__( 'This file was not uploaded.', 'wpforms' ),
					'file_limit'        => wpforms_setting(
						'validation-maxfilenumber',
						/* translators: %s - max number of files allowed. */
						sprintf( esc_html__( 'File uploads exceed the maximum number allowed (%s).', 'wpforms' ), '{fileLimit}' )
					),
					'file_extension'    => wpforms_setting( 'validation-fileextension', esc_html__( 'File type is not allowed.', 'wpforms' ) ),
					'file_size'         => wpforms_setting( 'validation-filesize', esc_html__( 'File exceeds the max size allowed.', 'wpforms' ) ),
					'post_max_size'     => sprintf(
						/* translators: %s - max allowed file size by a server. */
						esc_html__( 'File exceeds the upload limit allowed (%s).', 'wpforms' ),
						size_format( wp_max_upload_size() )
					),
				],
				'loading_message' => esc_html__( 'File upload is in progress. Please submit the form once uploading is completed.', 'wpforms' ),
			]
		);
	}

	/**
	 * Detecta forms renderizáveis na página atual (popups do PUM contam).
	 */
	protected static function page_has_forms() {
		if ( self::has_wpforms_on_page() ) {
			return true;
		}
		return false;
	}

	/**
	 * Varredura leve: popups ativos com shortcode/wpforms_display ou posts com
	 * widget eael-wpforms/shortcode [wpforms].
	 */
	protected static function has_wpforms_on_page() {
		static $has = null;
		if ( null !== $has ) {
			return $has;
		}
		$has = false;
		// Popups ativos (Popup Maker) com shortcode [wpforms].
		$popups = get_posts(
			[
				'post_type'      => 'popup',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			]
		);
		foreach ( $popups as $pid ) {
			if ( has_shortcode( get_post_field( 'post_content', $pid ), 'wpforms' ) ) {
				$has = true;
				break;
			}
		}
		// Conteúdo da página atual (shortcode ou widget EA).
		if ( ! $has ) {
			$post = get_post();
			if ( $post ) {
				if ( has_shortcode( $post->post_content, 'wpforms' ) ) {
					$has = true;
				} else {
					$edata = get_post_meta( $post->ID, '_elementor_data', true );
					if ( $edata && false !== strpos( $edata, 'eael-wpforms' ) ) {
						$has = true;
					}
				}
			}
		}
		return $has;
	}

	/**
	 * Imprime wpforms_settings (localize do JS) no footer, antes dos scripts.
	 */
		public static function print_settings() {
		if ( ! self::has_wpforms_on_page() ) {
			return;
		}

		$lang    = explode( "_", get_user_locale() );
		$post_id = self::current_post_id();
		$strings = [
			"val_required"               => wpforms_setting( "validation-required", esc_html__( "This field is required.", "wpforms-lite" ) ),
			"val_email"                  => wpforms_setting( "validation-email", esc_html__( "Please enter a valid email address.", "wpforms-lite" ) ),
			"val_email_suggestion"       => wpforms_setting( "validation-email-suggestion", esc_html__( "Did you mean {suggestion}?", "wpforms-lite" ) ),
			"val_email_suggestion_title" => esc_attr__( "Click to accept this suggestion.", "wpforms-lite" ),
			"val_email_restricted"       => wpforms_setting( "validation-email-restricted", esc_html__( "This email address is not allowed.", "wpforms-lite" ) ),
			"val_number"                 => wpforms_setting( "validation-number", esc_html__( "Please enter a valid number.", "wpforms-lite" ) ),
			"val_number_positive"        => wpforms_setting( "validation-number-positive", esc_html__( "Please enter a valid positive number.", "wpforms-lite" ) ),
			"val_confirm"                => wpforms_setting( "validation-confirm", esc_html__( "Field values do not match.", "wpforms-lite" ) ),
			"val_checklimit"             => wpforms_setting( "validation-check-limit", esc_html__( "You have exceeded the number of allowed selections: {#}.", "wpforms-lite" ) ),
			"val_limit_characters"       => wpforms_setting( "validation-character-limit", esc_html__( "{count} of {limit} max characters.", "wpforms-lite" ) ),
			"val_limit_words"            => wpforms_setting( "validation-word-limit", esc_html__( "{count} of {limit} max words.", "wpforms-lite" ) ),
			"val_recaptcha_fail_msg"     => wpforms_setting( "recaptcha-fail-msg", esc_html__( "Google reCAPTCHA verification failed, please try again later.", "wpforms-lite" ) ),
			"val_inputmask_incomplete"   => wpforms_setting( "validation-inputmask-incomplete", esc_html__( "Please fill out the field in required format.", "wpforms-lite" ) ),
			"uuid_cookie"                => false,
			"locale"                     => ! empty( $lang[0] ) ? strtolower( $lang[0] ) : "en",
			"wpforms_plugin_url"         => self::asset_url(),
			"gdpr"                       => wpforms_setting( "gdpr" ),
			"ajaxurl"                    => admin_url( "admin-ajax.php" ),
			"mailcheck_enabled"          => (bool) apply_filters( "wpforms_mailcheck_enabled", true ),
			"mailcheck_domains"          => array_map( "sanitize_text_field", (array) apply_filters( "wpforms_mailcheck_domains", [] ) ),
			"mailcheck_toplevel_domains" => array_map( "sanitize_text_field", (array) apply_filters( "wpforms_mailcheck_toplevel_domains", [ "dev" ] ) ),
			"is_ssl"                     => is_ssl(),
			"page_title"                 => $post_id ? get_the_title( $post_id ) : "",
			"page_id"                    => $post_id ? (string) $post_id : "",
		];

		$strings["currency_code"]       = wpforms_setting( "currency", "USD" );
		$strings["currency_thousands"]  = ",";
		$strings["currency_decimals"]   = 2;
		$strings["currency_decimal"]    = ".";
		$strings["currency_symbol"]     = "$";
		$strings["currency_symbol_pos"] = "left";

		$strings = apply_filters( "wpforms_frontend_strings", $strings );

		foreach ( (array) $strings as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$strings[ $key ] = html_entity_decode( (string) $value, ENT_QUOTES, "UTF-8" );
		}

		echo "<script type=\"text/javascript\">
";
		echo "/* <![CDATA[ */
";
		echo "var wpforms_settings = " . wp_json_encode( $strings ) . ";
";
		echo "/* ]]> */
";
		echo "</script>
";

		do_action( "wpforms_wp_footer_end", self::$forms );
	}

	/**
	 * ID do post atual (para {page_id}/{page_title} no settings).
	 */
	protected static function current_post_id() {
		$post = get_post();
		return $post ? $post->ID : 0;
	}
}
