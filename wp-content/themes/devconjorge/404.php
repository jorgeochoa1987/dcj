<?php
/**
 * Página no encontrada.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="wrap no-encontrado">
	<?php dcj_flap( '404', 'xl', 1 ); ?>
	<h1 class="h2">Esta página es puro humo.</h1>
	<p class="lead">No existe o cambió de lugar. Lo que sí existe:</p>
	<div class="row row--center">
		<a class="btn btn--lima" href="<?php echo esc_url( home_url( '/' ) ); ?>">Ir al inicio</a>
		<a class="btn btn--glass" href="<?php echo esc_url( get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' ) ); ?>">Ver la bitácora</a>
	</div>
</section>
<?php
get_footer();
