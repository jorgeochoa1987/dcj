<?php
/**
 * Lista de correos de "Recursos y talleres", guardada dentro de WordPress.
 * Se ve en el admin en "Suscriptores" y se exporta a CSV.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tipo de contenido privado para los correos.
 */
function dcj_registrar_suscriptores() {
	register_post_type(
		'dcj_suscriptor',
		array(
			'labels'          => array(
				'name'          => 'Suscriptores',
				'singular_name' => 'Suscriptor',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-email-alt',
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
		)
	);
}
add_action( 'init', 'dcj_registrar_suscriptores' );

/**
 * Recibe el formulario.
 */
function dcj_guardar_suscriptor() {
	$volver = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	$volver = remove_query_arg( 'suscrito', $volver );

	if ( ! isset( $_POST['dcj_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['dcj_nonce'] ) ), 'dcj_suscribir' ) ) {
		wp_safe_redirect( add_query_arg( 'suscrito', 'error', $volver ) . '#recursos' );
		exit;
	}

	// Trampa para bots: campo invisible que una persona deja vacío.
	if ( ! empty( $_POST['sitio_web'] ) ) {
		wp_safe_redirect( add_query_arg( 'suscrito', '1', $volver ) . '#recursos' );
		exit;
	}

	$correo = isset( $_POST['correo'] ) ? sanitize_email( wp_unslash( $_POST['correo'] ) ) : '';
	if ( ! is_email( $correo ) ) {
		wp_safe_redirect( add_query_arg( 'suscrito', 'error', $volver ) . '#recursos' );
		exit;
	}

	$existe = get_posts(
		array(
			'post_type'      => 'dcj_suscriptor',
			'title'          => $correo,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post_status'    => 'any',
		)
	);

	if ( ! $existe ) {
		wp_insert_post(
			array(
				'post_type'   => 'dcj_suscriptor',
				'post_title'  => $correo,
				'post_status' => 'private',
			)
		);
	}

	wp_safe_redirect( add_query_arg( 'suscrito', '1', $volver ) . '#recursos' );
	exit;
}
add_action( 'admin_post_nopriv_dcj_suscribir', 'dcj_guardar_suscriptor' );
add_action( 'admin_post_dcj_suscribir', 'dcj_guardar_suscriptor' );

/**
 * Botón "Exportar CSV" en la lista de suscriptores.
 *
 * @param string $which Posición.
 */
function dcj_boton_exportar( $which ) {
	if ( 'top' !== $which || 'dcj_suscriptor' !== get_current_screen()->post_type ) {
		return;
	}
	$url = wp_nonce_url( admin_url( 'admin-post.php?action=dcj_exportar' ), 'dcj_exportar' );
	printf( '<a class="button" href="%s">Exportar CSV</a>', esc_url( $url ) );
}
add_action( 'manage_posts_extra_tablenav', 'dcj_boton_exportar' );

/**
 * Descarga el CSV.
 */
function dcj_exportar_csv() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Sin permiso.' );
	}
	check_admin_referer( 'dcj_exportar' );

	$ids = get_posts(
		array(
			'post_type'      => 'dcj_suscriptor',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=suscriptores-devconjorge.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'correo', 'fecha' ) );
	foreach ( $ids as $id ) {
		fputcsv( $out, array( get_the_title( $id ), get_the_date( 'Y-m-d H:i', $id ) ) );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}
add_action( 'admin_post_dcj_exportar', 'dcj_exportar_csv' );
