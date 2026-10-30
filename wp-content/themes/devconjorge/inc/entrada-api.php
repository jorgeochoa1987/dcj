<?php
/**
 * Etiquetas y SEO de Yoast para el MCP.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'dcj_registrar_ruta_entrada' );

/**
 * Ruta autenticada de una entrada.
 */
function dcj_registrar_ruta_entrada() {
	register_rest_route(
		'dcj/v1',
		'/entrada/(?P<id>\d+)',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'dcj_rest_ver_extra',
				'permission_callback' => 'dcj_rest_puede_editar_entrada',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'dcj_rest_guardar_extra',
				'permission_callback' => 'dcj_rest_puede_editar_entrada',
			),
		)
	);
}

/**
 * Quien puede editar entradas.
 *
 * @return bool
 */
function dcj_rest_puede_editar_entrada() {
	return current_user_can( 'edit_posts' );
}

/**
 * Etiquetas, SEO y video de una entrada.
 *
 * @param WP_REST_Request $req Petición.
 * @return WP_REST_Response|WP_Error
 */
function dcj_rest_ver_extra( WP_REST_Request $req ) {
	$id = (int) $req['id'];
	if ( 'post' !== get_post_type( $id ) ) {
		return new WP_Error( 'dcj_entrada', 'Esa entrada no existe.', array( 'status' => 404 ) );
	}
	$tags = wp_get_post_tags( $id, array( 'fields' => 'names' ) );
	return rest_ensure_response(
		array(
			'id'               => $id,
			'etiquetas'        => is_array( $tags ) ? $tags : array(),
			'seo_titulo'       => (string) get_post_meta( $id, '_yoast_wpseo_title', true ),
			'seo_descripcion'  => (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true ),
			'frase_clave'      => (string) get_post_meta( $id, '_yoast_wpseo_focuskw', true ),
			'video_id'         => (string) get_post_meta( $id, '_dcj_video_id', true ),
			'imagen_destacada' => (int) get_post_thumbnail_id( $id ),
		)
	);
}

/**
 * Guarda etiquetas y campos de Yoast.
 *
 * @param WP_REST_Request $req Petición.
 * @return WP_REST_Response|WP_Error
 */
function dcj_rest_guardar_extra( WP_REST_Request $req ) {
	$id = (int) $req['id'];
	if ( 'post' !== get_post_type( $id ) ) {
		return new WP_Error( 'dcj_entrada', 'Esa entrada no existe.', array( 'status' => 404 ) );
	}
	$datos = $req->get_json_params();
	$datos = is_array( $datos ) ? $datos : array();

	if ( isset( $datos['etiquetas'] ) && is_array( $datos['etiquetas'] ) ) {
		$nombres = array();
		foreach ( $datos['etiquetas'] as $nombre ) {
			$nombre = sanitize_text_field( (string) $nombre );
			if ( '' !== $nombre ) {
				$nombres[] = $nombre;
			}
		}
		wp_set_post_tags( $id, $nombres, true );
	}

	$campos = array(
		'seo_titulo'      => '_yoast_wpseo_title',
		'seo_descripcion' => '_yoast_wpseo_metadesc',
		'frase_clave'     => '_yoast_wpseo_focuskw',
		'video_id'        => '_dcj_video_id',
	);
	foreach ( $campos as $clave => $meta ) {
		if ( isset( $datos[ $clave ] ) && is_string( $datos[ $clave ] ) ) {
			update_post_meta( $id, $meta, sanitize_text_field( $datos[ $clave ] ) );
		}
	}

	return dcj_rest_ver_extra( $req );
}
