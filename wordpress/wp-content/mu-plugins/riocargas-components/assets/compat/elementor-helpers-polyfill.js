/* Polyfill para helpers removidos do elementorCommon no Elementor 4.
 * Plugins antigos (Elementor Pro 3.x etc.) ainda chamam essas funcoes;
 * sem elas a excecao interrompe a cadeia elementor/frontend/init. */
(function () {
	if (!window.elementorCommon || !elementorCommon.helpers) {
		return;
	}
	var helpers = elementorCommon.helpers;
	if (!helpers.softDeprecated) {
		helpers.softDeprecated = function (name, version, replacement) {
			if (window.elementorWebCliConfig && elementorWebCliConfig.isDebug) {
				console.warn('`' + name + '` is soft deprecated since ' + version + (replacement ? ' - Use `' + replacement + '` instead' : ''));
			}
		};
	}
	if (!helpers.hardDeprecated) {
		helpers.hardDeprecated = function (name, version, replacement) {
			console.warn('`' + name + '` is hard deprecated since ' + version + (replacement ? ' - Use `' + replacement + '` instead' : ''));
		};
	}
	if (!helpers.deprecatedMessage) {
		helpers.deprecatedMessage = function (type, name, version, replacement) {
			console.warn('`' + name + '` is ' + type + ' deprecated since ' + version + (replacement ? ' - Use `' + replacement + '` instead' : ''));
		};
	}
	if (!helpers.deprecatedFont) {
		helpers.deprecatedFont = function () {};
	}
})();
