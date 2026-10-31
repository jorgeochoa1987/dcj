<?php
/**
 * Píldoras de IA productiva. La lista suena en la barra flotante.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

get_header();

$dcj_episodios = dcj_spotify_episodios();
?>

<section class="wrap pildoras">
	<p class="eyebrow">Spotify</p>
	<h1 class="mega">Píldoras<span class="lima">.</span></h1>
	<p class="lead lead--xl">Todos los caps están en la barra de abajo. Elige uno y suena ahí.</p>

	<?php if ( $dcj_episodios ) : ?>
		<div class="pildoras-grid">
			<?php foreach ( $dcj_episodios as $dcj_i => $dcj_ep ) : ?>
				<button class="pildora glass" type="button" data-episodio="<?php echo esc_attr( 'spotify:episode:' . $dcj_ep['id'] ); ?>">
					<span class="pildora__num"><?php echo esc_html( sprintf( '%02d', $dcj_i + 1 ) ); ?></span>
					<span class="pildora__cuerpo">
						<span class="pildora__titulo"><?php echo esc_html( $dcj_ep['titulo'] ); ?></span>
					</span>
				</button>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="lead">La lista aparece en la barra de abajo en cuanto Spotify la suelta.</p>
	<?php endif; ?>

	<p class="muted"><a href="<?php echo esc_url( 'https://open.spotify.com/show/' . dcj_spotify_show() ); ?>" rel="noopener" target="_blank">Abrir en Spotify</a></p>
</section>

<?php
get_footer();
