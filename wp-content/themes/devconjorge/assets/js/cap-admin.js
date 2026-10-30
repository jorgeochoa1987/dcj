/* Elige el audio de un capítulo desde la biblioteca. */
(function () {
	'use strict';

	var boton = document.querySelector('[data-dcj-audio]');
	var campo = document.querySelector('[data-dcj-audio-id]');
	var nombre = document.querySelector('[data-dcj-audio-nombre]');
	if (!boton || !campo || !window.wp || !wp.media) { return; }

	var marco;
	boton.addEventListener('click', function (evento) {
		evento.preventDefault();
		if (!marco) {
			marco = wp.media({
				title: 'Audio del capítulo',
				library: { type: 'audio' },
				button: { text: 'Usar este audio' },
				multiple: false
			});
			marco.on('select', function () {
				var archivo = marco.state().get('selection').first().toJSON();
				campo.value = String(archivo.id || '');
				nombre.textContent = archivo.filename || archivo.title || 'Audio elegido';
			});
		}
		marco.open();
	});
}());
