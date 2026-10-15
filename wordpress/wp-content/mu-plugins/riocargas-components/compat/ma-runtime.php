<?php
// Runtime mínimo para os widgets portados do Master Addons Pro:
// constante de textdomain, helper copiado do plugin e stub do freemius (ma_el_fs).

if ( ! defined( 'MELA_TD' ) ) {
	define( 'MELA_TD', 'master-addons' );
}

require_once __DIR__ . '/ma-helper.php';

if ( ! function_exists( 'ma_el_fs' ) ) {
	// Stub do SDK Freemius: os widgets substitutos tratam tudo como "premium liberado".
	class RC_MA_FS_Stub {
		public function can_use_premium_code() {
			return true;
		}
		public function can_use_premium_code__premium_only() {
			return true;
		}
		public function is_not_paying() {
			return false;
		}
		public function is_free_plan() {
			return false;
		}
		public function is_paying() {
			return true;
		}
		public function get_upgrade_url() {
			return '#';
		}
		public function is_premium() {
			return true;
		}
		public function __call( $name, $args ) {
			return null;
		}
	}
	function ma_el_fs() {
		static $fs = null;
		if ( null === $fs ) {
			$fs = new RC_MA_FS_Stub();
		}
		return $fs;
	}
}
