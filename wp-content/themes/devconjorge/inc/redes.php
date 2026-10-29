<?php
/**
 * Redes sociales: enlaces, contadores e iconos.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

/**
 * Definición base de cada red. Las cifras por defecto son de referencia:
 * se cambian en Apariencia → Personalizar → Redes y seguidores.
 *
 * @return array
 */
function dcj_redes_base() {
	return array(
		'youtube'   => array(
			'nombre' => 'YouTube',
			'url'    => 'https://youtube.com/@devconjorge',
			'cifra'  => 3320,
			'unidad' => 'suscriptores',
			'accion' => 'Suscribirme',
		),
		'tiktok'    => array(
			'nombre' => 'TikTok',
			'url'    => 'https://tiktok.com/@devconjorge',
			'cifra'  => 15267,
			'unidad' => 'seguidores',
			'accion' => 'Seguir',
		),
		'instagram' => array(
			'nombre' => 'Instagram',
			'url'    => 'https://instagram.com/devconjorge',
			'cifra'  => 480,
			'unidad' => 'seguidores',
			'accion' => 'Seguir',
		),
		'facebook'  => array(
			'nombre' => 'Facebook',
			'url'    => 'https://facebook.com/devconjorge',
			'cifra'  => 823,
			'unidad' => 'seguidores',
			'accion' => 'Seguir',
		),
		'whatsapp'  => array(
			'nombre' => 'WhatsApp',
			'url'    => 'https://devconjorge.com/wp',
			'cifra'  => 0,
			'unidad' => 'en el canal',
			'accion' => 'Unirme',
		),
		'twitch'    => array(
			'nombre' => 'Twitch',
			'url'    => 'https://twitch.tv/devconjorge',
			'cifra'  => 0,
			'unidad' => 'seguidores · lunes 9 p. m.',
			'accion' => 'Seguir',
		),
	);
}

/**
 * Redes con los valores guardados en el Personalizador.
 * YouTube se actualiza solo si hay API key de YouTube.
 *
 * @return array
 */
function dcj_redes() {
	$redes = array();
	foreach ( dcj_redes_base() as $clave => $red ) {
		$red['clave'] = $clave;
		$red['url']   = get_theme_mod( "dcj_{$clave}_url", $red['url'] );
		$red['cifra'] = absint( get_theme_mod( "dcj_{$clave}_cifra", $red['cifra'] ) );

		if ( 'youtube' === $clave ) {
			$en_vivo = dcj_youtube_suscriptores();
			if ( $en_vivo ) {
				$red['cifra']   = $en_vivo;
				$red['en_vivo'] = true;
			}
		}

		$redes[ $clave ] = $red;
	}
	return $redes;
}

/**
 * Suma de seguidores de todas las redes.
 *
 * @return int
 */
function dcj_total_seguidores() {
	return array_sum( wp_list_pluck( dcj_redes(), 'cifra' ) );
}

/**
 * Cifra corta: 15267 → 15,3K.
 *
 * @param int $n Número.
 * @return string
 */
function dcj_cifra_corta( $n ) {
	if ( $n >= 1000000 ) {
		return number_format_i18n( $n / 1000000, 1 ) . 'M';
	}
	if ( $n >= 1000 ) {
		return number_format_i18n( $n / 1000, 1 ) . 'K';
	}
	return number_format_i18n( $n );
}

/**
 * Icono de trazo de cada red (SVG en línea, decorativo).
 *
 * @param string $clave Red.
 * @param int    $size  Tamaño en px.
 * @return string
 */
function dcj_icono( $clave, $size = 22 ) {
	$trazos = array(
		'youtube'   => '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="M10 9.5v5l4.5-2.5z"/>',
		'tiktok'    => '<path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 3c.4 2.6 2.2 4.2 5 4.4"/>',
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8"/>',
		'facebook'  => '<path d="M14 21v-8h3l.5-3.5H14V7.8c0-1 .3-1.8 1.8-1.8h1.8V3h-2.7C11.9 3 10.5 4.7 10.5 7.4v2.1H8V13h2.5v8"/>',
		'whatsapp'  => '<path d="M4 20l1.3-4A8 8 0 1 1 8.4 19z"/><path d="M9.5 9.5c.3 2 2 3.7 4 4l1-1.2 1.8.8-.4 1.6c-3.4 0-6.6-3.2-6.6-6.6l1.6-.4.8 1.8z"/>',
		'twitch'    => '<path d="M4 3h16v11l-5 5h-4l-3 3v-3H4z"/><path d="M11 8v4M15 8v4"/>',
		'flecha'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'check'     => '<path d="M5 12l5 5 9-10"/>',
		'doc'       => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
		'alerta'    => '<path d="M12 3l9 16H3z"/><path d="M12 10v4M12 17h.01"/>',
	);
	if ( ! isset( $trazos[ $clave ] ) ) {
		return '';
	}
	return sprintf(
		'<svg aria-hidden="true" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">%2$s</svg>',
		(int) $size,
		$trazos[ $clave ]
	);
}
