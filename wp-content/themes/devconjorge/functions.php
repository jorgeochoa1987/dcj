<?php
/**
 * DevConJorge — funciones del tema.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

define( 'DCJ_VERSION', '1.3.0' );
define( 'DCJ_DIR', get_template_directory() );
define( 'DCJ_URI', get_template_directory_uri() );

require_once DCJ_DIR . '/inc/redes.php';
require_once DCJ_DIR . '/inc/customizer.php';
require_once DCJ_DIR . '/inc/youtube.php';
require_once DCJ_DIR . '/inc/suscriptores.php';
require_once DCJ_DIR . '/inc/template-tags.php';
require_once DCJ_DIR . '/inc/home-api.php';
require_once DCJ_DIR . '/inc/entrada-api.php';

/**
 * Soportes básicos del tema.
 */
function dcj_setup() {
	load_theme_textdomain( 'devconjorge', DCJ_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_image_size( 'dcj-vertical', 540, 960, true );

	register_nav_menus(
		array(
			'principal' => __( 'Menú principal', 'devconjorge' ),
		)
	);
}
add_action( 'after_setup_theme', 'dcj_setup' );

/**
 * Fuentes, estilos y scripts.
 */
function dcj_assets() {
	wp_enqueue_style(
		'dcj-fonts',
		'https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'dcj-main', DCJ_URI . '/assets/css/main.css', array( 'dcj-fonts' ), DCJ_VERSION );
	wp_enqueue_script( 'dcj-main', DCJ_URI . '/assets/js/main.js', array(), DCJ_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_localize_script(
		'dcj-main',
		'DCJ',
		array(
			'handle' => '@devconjorge',
			'copied' => __( 'Enlace copiado', 'devconjorge' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'dcj_assets' );

/**
 * Preconectar con Google Fonts y YouTube.
 *
 * @param array  $urls          URLs.
 * @param string $relation_type Tipo.
 * @return array
 */
function dcj_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
		$urls[] = 'https://i.ytimg.com';
		$urls[] = 'https://www.youtube-nocookie.com';
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'dcj_resource_hints', 10, 2 );

/**
 * Al activar el tema: crea Inicio y Bitácora y las deja configuradas.
 */
function dcj_activar_tema() {
	$inicio    = get_page_by_path( 'inicio' );
	$bitacora  = get_page_by_path( 'bitacora' );
	$inicio_id = $inicio ? $inicio->ID : wp_insert_post(
		array(
			'post_title'  => 'Inicio',
			'post_name'   => 'inicio',
			'post_type'   => 'page',
			'post_status' => 'publish',
		)
	);
	$bitacora_id = $bitacora ? $bitacora->ID : wp_insert_post(
		array(
			'post_title'  => 'Bitácora',
			'post_name'   => 'bitacora',
			'post_type'   => 'page',
			'post_status' => 'publish',
		)
	);

	if ( $inicio_id && ! is_wp_error( $inicio_id ) && $bitacora_id && ! is_wp_error( $bitacora_id ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $inicio_id );
		update_option( 'page_for_posts', $bitacora_id );
	}

	if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
		flush_rewrite_rules();
	}
}
add_action( 'after_switch_theme', 'dcj_activar_tema' );

/**
 * Extracto corto.
 *
 * @return int
 */
function dcj_excerpt_length() {
	return 22;
}
add_filter( 'excerpt_length', 'dcj_excerpt_length' );

/**
 * Sin "[...]" al final del extracto.
 *
 * @return string
 */
function dcj_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'dcj_excerpt_more' );

/**
 * En la página de inicio, la foto se pone desde el editor, no con un ID.
 *
 * @param WP_Post $post Entrada en edición.
 */
function dcj_ayuda_fotos( $post ) {
	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return;
	}
	if ( (int) get_option( 'page_on_front' ) !== (int) $post->ID ) {
		return;
	}
	echo '<p class="description" style="margin:12px 0 0">Retrato: imagen destacada, en el panel de la derecha. Fotos extra: bloques de imagen aquí abajo. Se ven solos en la home.</p>';
}
add_action( 'edit_form_after_title', 'dcj_ayuda_fotos' );
