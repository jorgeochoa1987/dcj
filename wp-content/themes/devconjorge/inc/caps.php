<?php
/**
 * Capítulos de la barra En el aire.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lista de capítulos en el escritorio.
 */
function dcj_registrar_caps() {
	register_post_type(
		'dcj_cap',
		array(
			'labels'              => array(
				'name'          => 'Capítulos',
				'singular_name' => 'Capítulo',
				'add_new_item'  => 'Nuevo capítulo',
				'edit_item'     => 'Editar capítulo',
				'all_items'     => 'Todos los capítulos',
				'search_items'  => 'Buscar capítulos',
				'not_found'     => 'Todavía no hay capítulos',
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-playlist-audio',
			'menu_position'       => 21,
			'supports'            => array( 'title' ),
			'capability_type'     => 'post',
			'exclude_from_search' => true,
			'rewrite'             => false,
		)
	);
}
add_action( 'init', 'dcj_registrar_caps' );

/**
 * Caja para el audio y el orden del playlist.
 */
function dcj_cap_metabox() {
	add_meta_box(
		'dcj_cap_audio',
		'Audio del capítulo',
		'dcj_cap_audio_box',
		'dcj_cap',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'dcj_cap_metabox' );

/**
 * @param WP_Post $post Capítulo.
 */
function dcj_cap_audio_box( $post ) {
	wp_nonce_field( 'dcj_guardar_cap', 'dcj_cap_nonce' );
	$audio_id = (int) get_post_meta( $post->ID, '_dcj_audio_id', true );
	$nombre   = $audio_id ? get_the_title( $audio_id ) : 'Ningún archivo';
	$orden    = (int) $post->menu_order;
	?>
	<p>
		<button type="button" class="button" data-dcj-audio>Elegir audio</button>
		<span data-dcj-audio-nombre><?php echo esc_html( $nombre ); ?></span>
		<input type="hidden" name="dcj_audio_id" value="<?php echo esc_attr( (string) $audio_id ); ?>" data-dcj-audio-id>
	</p>
	<p>
		<label for="dcj_orden">Orden en el playlist</label><br>
		<input id="dcj_orden" name="dcj_orden" type="number" min="0" step="1" value="<?php echo esc_attr( (string) $orden ); ?>" style="width:80px">
	</p>
	<p class="description">1 es el primero. Si varios quedan en 0, se ordenan por fecha, del más antiguo al más nuevo. Publica el capítulo para que salga en la barra.</p>
	<?php
}

/**
 * Medios solo en la pantalla del capítulo.
 *
 * @param string $hook Pantalla.
 */
function dcj_cap_assets( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'dcj_cap' !== $screen->post_type ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'dcj-cap-admin', DCJ_URI . '/assets/js/cap-admin.js', array( 'media-editor' ), DCJ_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'dcj_cap_assets' );

/**
 * @param int $post_id Capítulo.
 */
function dcj_guardar_cap( $post_id ) {
	if ( ! isset( $_POST['dcj_cap_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dcj_cap_nonce'] ) ), 'dcj_guardar_cap' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$audio_id = isset( $_POST['dcj_audio_id'] ) ? absint( wp_unslash( $_POST['dcj_audio_id'] ) ) : 0;
	$mime     = $audio_id ? (string) get_post_mime_type( $audio_id ) : '';
	if ( $audio_id && 0 !== strpos( $mime, 'audio/' ) ) {
		$audio_id = 0;
	}
	if ( $audio_id ) {
		update_post_meta( $post_id, '_dcj_audio_id', $audio_id );
	} else {
		delete_post_meta( $post_id, '_dcj_audio_id' );
	}

	$orden = isset( $_POST['dcj_orden'] ) ? absint( wp_unslash( $_POST['dcj_orden'] ) ) : 0;
	global $wpdb;
	$wpdb->update(
		$wpdb->posts,
		array( 'menu_order' => $orden ),
		array( 'ID' => $post_id ),
		array( '%d' ),
		array( '%d' )
	);
	clean_post_cache( $post_id );
}
add_action( 'save_post_dcj_cap', 'dcj_guardar_cap' );

/**
 * Columna para ver si el capítulo ya tiene archivo.
 *
 * @param array $columnas Columnas.
 * @return array
 */
function dcj_cap_columnas( $columnas ) {
	$columnas['dcj_audio'] = 'Audio';
	$columnas['dcj_orden'] = 'Orden';
	return $columnas;
}
add_filter( 'manage_dcj_cap_posts_columns', 'dcj_cap_columnas' );

/**
 * @param string $columna Columna.
 * @param int    $post_id Capítulo.
 */
function dcj_cap_columna( $columna, $post_id ) {
	if ( 'dcj_audio' === $columna ) {
		$id = (int) get_post_meta( $post_id, '_dcj_audio_id', true );
		echo $id && wp_get_attachment_url( $id ) ? 'Listo' : 'Sin audio';
		return;
	}
	if ( 'dcj_orden' === $columna ) {
		$post = get_post( $post_id );
		echo esc_html( (string) ( $post ? (int) $post->menu_order : 0 ) );
	}
}
add_action( 'manage_dcj_cap_posts_custom_column', 'dcj_cap_columna', 10, 2 );

/**
 * Playlist publicado. Si no hay capítulos, usa el audio suelto del Personalizador.
 *
 * @return array<int, array{titulo:string,url:string,mime:string}>
 */
function dcj_playlist() {
	$posts = get_posts(
		array(
			'post_type'      => 'dcj_cap',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'ASC',
			),
		)
	);
	$items = array();
	foreach ( $posts as $post ) {
		$id  = (int) get_post_meta( $post->ID, '_dcj_audio_id', true );
		$url = $id ? wp_get_attachment_url( $id ) : '';
		if ( ! $url ) {
			continue;
		}
		$mime    = get_post_mime_type( $id );
		$items[] = array(
			'titulo' => get_the_title( $post ),
			'url'    => $url,
			'mime'   => $mime ? $mime : 'audio/mpeg',
		);
	}
	if ( $items ) {
		return $items;
	}
	$suelto = dcj_audio_barra();
	if ( ! $suelto ) {
		return array();
	}
	return array(
		array(
			'titulo' => $suelto['titulo'],
			'url'    => $suelto['url'],
			'mime'   => $suelto['mime'],
		),
	);
}
