<?php
/**
 * Fachada mínima para wpforms() — alguns integradores/plugins esperam o objeto.
 *
 * @package RioCargas\Components\Forms
 */

namespace RioCargas\Components\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Retorna instâncias lazily via __get (ex.: wpforms()->process).
 */
class Facade {

	/** @var array Instâncias cacheadas. */
	private $instances = [];

	/**
	 * Acesso a "serviços" (process, frontend, form).
	 */
	public function __get( $name ) {
		if ( isset( $this->instances[ $name ] ) ) {
			return $this->instances[ $name ];
		}
		$inst = null;
		switch ( $name ) {
			case 'process':
				$inst = new FacadeProcess();
				break;
			case 'frontend':
				$inst = new FacadeFrontend();
				break;
			case 'form':
				$inst = new FacadeForm();
				break;
			default:
				$inst = new \stdClass();
				break;
		}
		$this->instances[ $name ] = $inst;
		return $inst;
	}

	/**
	 * is_pro() — o substituto cobre features Pro, mas reportar true faz
	 * integradores acreditarem em APIs que não existem; devolve false.
	 */
	public function is_pro() {
		return false;
	}
}

/**
 * wpforms()->process — proxy por referência para os estáticos do Process,
 * para que hooks externos leiam/escrevam erros e fields de verdade.
 */
class FacadeProcess {
	// phpcs:ignore Squiz.Commenting
	public $fields = [];
	public $errors = [];
	public $form_data;
	public $entry_id = 0;
	public $confirmation_message;
	public $valid_hash = false;

	/**
	 * Vincula as propriedades às variáveis estáticas do engine real.
	 */
	public function __construct() {
		$this->fields    = &Process::$fields;
		$this->errors    = &Process::$errors;
		$this->form_data = &Process::$form_data;
		$this->entry_id  = &Process::$entry_id;
	}
}

/**
 * wpforms()->frontend — o PowerPack chamava assets_css() globalmente.
 */
class FacadeFrontend {
	/**
	 * Enfileira o CSS base — mantém a compatibilidade com o hook do PowerPack.
	 */
	public function assets_css() {
		wp_enqueue_style(
			'wpforms-base',
			Renderer::asset_url( 'assets/css/wpforms-base.min.css' ),
			[],
			'1.7.6'
		);
	}

	/**
	 * Alguns integradores chamam assets_js().
	 */
	public function assets_js() {}
}

/**
 * wpforms()->form->get().
 */
class FacadeForm {
	/**
	 * get($form_id, $args) — compatível com wpforms()->form->get(content_only).
	 */
	public function get( $form_id, $args = [] ) {
		$content_only = ! empty( $args['content_only'] );
		if ( $content_only ) {
			return Renderer::get_form_data( absint( $form_id ) );
		}
		$post = get_post( absint( $form_id ) );
		return $post ? $post : null;
	}
}
