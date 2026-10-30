<?php
/**
 * Opciones en Apariencia → Personalizar.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registra las secciones del tema.
 *
 * @param WP_Customize_Manager $wp_customize Personalizador.
 */
function dcj_customize_register( $wp_customize ) {
	$wp_customize->add_panel(
		'dcj_panel',
		array(
			'title'    => 'DevConJorge',
			'priority' => 20,
		)
	);

	// Redes y seguidores.
	$wp_customize->add_section(
		'dcj_redes',
		array(
			'title'       => 'Redes y seguidores',
			'panel'       => 'dcj_panel',
			'description' => 'Enlaces y cifras del contador. Si pones la API key de YouTube (sección Canal), los suscriptores de YouTube se actualizan solos.',
		)
	);

	foreach ( dcj_redes_base() as $clave => $red ) {
		$wp_customize->add_setting(
			"dcj_{$clave}_url",
			array(
				'default'           => $red['url'],
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		$wp_customize->add_control(
			"dcj_{$clave}_url",
			array(
				'label'   => $red['nombre'] . ' — enlace',
				'section' => 'dcj_redes',
				'type'    => 'url',
			)
		);
		$wp_customize->add_setting(
			"dcj_{$clave}_cifra",
			array(
				'default'           => $red['cifra'],
				'sanitize_callback' => 'absint',
			)
		);
		$wp_customize->add_control(
			"dcj_{$clave}_cifra",
			array(
				'label'       => $red['nombre'] . ' — ' . $red['unidad'],
				'description' => 0 === $red['cifra'] ? 'En 0 la tarjeta muestra solo el botón, sin cifra.' : '',
				'section'     => 'dcj_redes',
				'type'        => 'number',
				'input_attrs' => array( 'min' => 0 ),
			)
		);
	}

	// Canal de YouTube.
	$wp_customize->add_section(
		'dcj_canal',
		array(
			'title'       => 'Canal de YouTube',
			'panel'       => 'dcj_panel',
			'description' => 'El feed "Míralo en 60 s" lee los Shorts públicos del canal. No usa OAuth. La API key solo sirve para el contador de suscriptores en vivo.',
		)
	);
	$campos = array(
		'dcj_canal_id'   => array( 'ID del canal', 'UCQMAx4udQPfGPuzoPzh1XAQ', 'sanitize_text_field', 'text' ),
		'dcj_yt_api_key' => array( 'API key de YouTube Data v3 (opcional)', '', 'sanitize_text_field', 'password' ),
		'dcj_feed_total' => array( 'Videos en el feed', 8, 'absint', 'number' ),
	);
	foreach ( $campos as $id => $c ) {
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $c[1],
				'sanitize_callback' => $c[2],
			)
		);
		$wp_customize->add_control(
			$id,
			array(
				'label'   => $c[0],
				'section' => 'dcj_canal',
				'type'    => $c[3],
			)
		);
	}

	// Audio del encabezado.
	$wp_customize->add_section(
		'dcj_audio',
		array(
			'title'       => 'En el aire',
			'panel'       => 'dcj_panel',
			'description' => 'Barra de reproducción arriba del menú. Sin archivo, no se muestra.',
		)
	);
	$wp_customize->add_setting(
		'dcj_audio_id',
		array(
			'default'           => 0,
			'sanitize_callback' => 'absint',
		)
	);
	$wp_customize->add_control(
		new WP_Customize_Media_Control(
			$wp_customize,
			'dcj_audio_id',
			array(
				'label'       => 'Audio',
				'description' => 'Mp3, m4a o wav. Al cambiar el archivo, la barra usa el nuevo.',
				'section'     => 'dcj_audio',
				'mime_type'   => 'audio',
			)
		)
	);
	$wp_customize->add_setting(
		'dcj_audio_titulo',
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_control(
		'dcj_audio_titulo',
		array(
			'label'       => 'Título del episodio',
			'description' => 'Si lo dejas vacío, usa el nombre del archivo.',
			'section'     => 'dcj_audio',
			'type'        => 'text',
		)
	);

	// Marca y contacto.
	$wp_customize->add_section(
		'dcj_marca',
		array(
			'title' => 'Firma y contacto',
			'panel' => 'dcj_panel',
		)
	);
	$campos = array(
		'dcj_firma'         => array( 'Firma de autor', 'Ing. Jorge Ochoa', 'sanitize_text_field', 'text' ),
		'dcj_correo_marcas' => array( 'Correo para marcas', '', 'sanitize_email', 'email' ),
		'dcj_media_kit_url' => array( 'Enlace al media kit', '', 'esc_url_raw', 'url' ),
	);
	foreach ( $campos as $id => $c ) {
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $c[1],
				'sanitize_callback' => $c[2],
			)
		);
		$wp_customize->add_control(
			$id,
			array(
				'label'   => $c[0],
				'section' => 'dcj_marca',
				'type'    => $c[3],
			)
		);
	}
}
add_action( 'customize_register', 'dcj_customize_register' );

/**
 * El logo usa el ajuste nativo de WordPress y se muestra en Firma y contacto.
 *
 * @param WP_Customize_Manager $wp_customize Personalizador.
 */
function dcj_logo_control( $wp_customize ) {
	$wp_customize->add_control(
		new WP_Customize_Media_Control(
			$wp_customize,
			'dcj_logo_media',
			array(
				'label'       => 'Logo del encabezado',
				'description' => 'Si no hay imagen, el encabezado sigue mostrando DCJ.',
				'section'     => 'dcj_marca',
				'mime_type'   => 'image',
				'settings'    => 'custom_logo',
			)
		)
	);
}
add_action( 'customize_register', 'dcj_logo_control', 30 );

/**
 * Al guardar el Personalizador, borra cachés de YouTube.
 */
function dcj_limpiar_cache_youtube() {
	delete_transient( 'dcj_yt_subs' );
	delete_transient( 'dcj_yt_feed' );
	delete_transient( 'dcj_yt_feed_v2' );
	delete_transient( 'dcj_yt_feed_v3' );
}
add_action( 'customize_save_after', 'dcj_limpiar_cache_youtube' );
