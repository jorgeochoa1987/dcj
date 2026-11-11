<?php
/**
 * Sala de Zombia. El juego ocupa la pantalla, sin el menú ni el reproductor.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="zombia-sala">
	<a class="zombia-sala__volver" href="<?php echo esc_url( home_url( '/' ) ); ?>">Volver</a>
	<iframe src="<?php echo esc_url( DCJ_URI . '/zombia/index.html?v=' . DCJ_VERSION ); ?>" title="Zombia" allow="autoplay; fullscreen" allowfullscreen></iframe>
</div>

<?php
get_footer();
