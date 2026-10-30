<?php
/**
 * Lectura y edición del home para el MCP: cifras, videos y fotos.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'dcj_registrar_ruta_home' );

/**
 * Ruta autenticada. La usa el MCP con la contraseña de aplicación.
 */
function dcj_registrar_ruta_home() {
	register_rest_route(
		'dcj/v1',
		'/home',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'dcj_rest_ver_home',
				'permission_callback' => 'dcj_rest_puede_editar_home',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'dcj_rest_actualizar_home',
				'permission_callback' => 'dcj_rest_puede_editar_home',
			),
		)
	);
}

/**
 * Solo quien puede cambiar el tema.
 *
 * @return bool
 */
function dcj_rest_puede_editar_home() {
	return current_user_can( 'edit_theme_options' );
}

/**
 * Estado actual del home.
 *
 * @return WP_REST_Response
 */
function dcj_rest_ver_home() {
	$cifras  = array();
	$enlaces = array();
	foreach ( dcj_redes() as $clave => $red ) {
		$cifras[ $clave ]  = (int) $red['cifra'];
		$enlaces[ $clave ] = $red['url'];
	}
	$videos = array();
	foreach ( dcj_youtube_videos( 8 ) as $video ) {
		$videos[] = array(
			'id'     => $video['id'],
			'titulo' => $video['titulo'],
			'url'    => $video['url'],
		);
	}
	return rest_ensure_response(
		array(
			'pagina_inicio' => (int) get_option( 'page_on_front' ),
			'foto_id'       => dcj_id_foto(),
			'fotos'         => dcj_fotos_editor(),
			'cifras'        => $cifras,
			'enlaces'       => $enlaces,
			'videos'        => $videos,
			'videos_fijos'  => (bool) dcj_videos_fijos(),
		)
	);
}

/**
 * Actualiza cifras, videos o fotos del home.
 *
 * @param WP_REST_Request $req Petición.
 * @return WP_REST_Response|WP_Error
 */
function dcj_rest_actualizar_home( WP_REST_Request $req ) {
	$datos  = $req->get_json_params();
	$datos  = is_array( $datos ) ? $datos : array();
	$inicio = (int) get_option( 'page_on_front' );

	if ( isset( $datos['cifras'] ) && is_array( $datos['cifras'] ) ) {
		foreach ( array_keys( dcj_redes_base() ) as $clave ) {
			if ( isset( $datos['cifras'][ $clave ] ) ) {
				set_theme_mod( "dcj_{$clave}_cifra", absint( $datos['cifras'][ $clave ] ) );
			}
		}
	}

	if ( ! empty( $datos['refrescar_videos'] ) ) {
		remove_theme_mod( 'dcj_videos_fijos' );
		delete_transient( 'dcj_yt_feed' );
		delete_transient( 'dcj_yt_feed_v2' );
		delete_transient( 'dcj_yt_feed_v3' );
	}

	if ( isset( $datos['videos'] ) && is_array( $datos['videos'] ) ) {
		$lista = array();
		foreach ( $datos['videos'] as $video ) {
			$id = is_array( $video ) ? (string) ( $video['id'] ?? '' ) : '';
			if ( ! preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $id ) ) {
				continue;
			}
			$lista[] = array(
				'id'     => $id,
				'titulo' => sanitize_text_field( (string) ( $video['titulo'] ?? '' ) ),
				'vistas' => isset( $video['vistas'] ) ? absint( $video['vistas'] ) : 0,
			);
		}
		if ( $lista ) {
			set_theme_mod( 'dcj_videos_fijos', wp_json_encode( $lista ) );
		} else {
			remove_theme_mod( 'dcj_videos_fijos' );
		}
	}

	if ( isset( $datos['foto_id'] ) ) {
		$foto = absint( $datos['foto_id'] );
		if ( ! $inicio ) {
			return new WP_Error( 'dcj_inicio', 'No hay página de inicio.', array( 'status' => 400 ) );
		}
		if ( $foto && ! wp_attachment_is_image( $foto ) ) {
			return new WP_Error( 'dcj_foto', 'Esa imagen no está en Medios.', array( 'status' => 400 ) );
		}
		if ( $foto ) {
			set_post_thumbnail( $inicio, $foto );
		} else {
			delete_post_thumbnail( $inicio );
		}
	}

	if ( isset( $datos['agregar_foto'] ) ) {
		$foto = absint( $datos['agregar_foto'] );
		if ( ! $inicio || ! wp_attachment_is_image( $foto ) ) {
			return new WP_Error( 'dcj_foto', 'No pude agregar esa foto al home.', array( 'status' => 400 ) );
		}
		$ids   = dcj_fotos_editor();
		$ids[] = $foto;
		dcj_guardar_fotos_home( $inicio, $ids );
	}

	if ( isset( $datos['fotos'] ) && is_array( $datos['fotos'] ) ) {
		if ( ! $inicio ) {
			return new WP_Error( 'dcj_inicio', 'No hay página de inicio.', array( 'status' => 400 ) );
		}
		$ids = array();
		foreach ( $datos['fotos'] as $id ) {
			$id = absint( $id );
			if ( $id && wp_attachment_is_image( $id ) ) {
				$ids[] = $id;
			}
		}
		dcj_guardar_fotos_home( $inicio, $ids );
	}

	return dcj_rest_ver_home();
}

/**
 * Deja en la página de inicio solo los bloques de imagen indicados.
 *
 * @param int   $inicio ID de la página.
 * @param int[] $ids    Adjuntos.
 */
function dcj_guardar_fotos_home( $inicio, $ids ) {
	$html = '';
	foreach ( $ids as $id ) {
		$url   = wp_get_attachment_url( $id );
		$html .= '<!-- wp:image {"id":' . (int) $id . ',"sizeSlug":"large"} -->' . "\n";
		$html .= '<figure class="wp-block-image size-large"><img src="' . esc_url( $url ) . '" alt="" class="wp-image-' . (int) $id . '"/></figure>' . "\n";
		$html .= "<!-- /wp:image -->\n";
	}
	wp_update_post(
		array(
			'ID'           => (int) $inicio,
			'post_content' => $html,
		)
	);
}
