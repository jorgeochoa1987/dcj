<?php
/**
 * Píldoras de IA productiva, con la lista completa del show.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

get_header();

$dcj_episodios = dcj_spotify_episodios();
$dcj_primero   = $dcj_episodios ? $dcj_episodios[0]['id'] : '';
?>

<section class="wrap pildoras">
	<p class="eyebrow">Spotify</p>
	<h1 class="mega">Píldoras<span class="lima">.</span></h1>
	<p class="lead lead--xl">Todos los caps del programa. Elige uno y suena aquí.</p>

	<?php if ( $dcj_primero ) : ?>
		<div id="dcj-spotify-player" class="pildoras-embed"></div>
		<div class="pildoras-grid">
			<?php foreach ( $dcj_episodios as $dcj_i => $dcj_ep ) : ?>
				<button class="pildora glass" type="button" data-episodio="<?php echo esc_attr( 'spotify:episode:' . $dcj_ep['id'] ); ?>"<?php echo 0 === $dcj_i ? ' aria-current="true"' : ''; ?>>
					<span class="pildora__num"><?php echo esc_html( sprintf( '%02d', $dcj_i + 1 ) ); ?></span>
					<span class="pildora__cuerpo">
						<span class="pildora__titulo"><?php echo esc_html( $dcj_ep['titulo'] ); ?></span>
					</span>
				</button>
			<?php endforeach; ?>
		</div>
		<script>
		window.onSpotifyIframeApiReady = function (IFrameAPI) {
			var caja = document.getElementById('dcj-spotify-player');
			var botones = document.querySelectorAll('[data-episodio]');
			if (!caja || !botones.length) { return; }
			IFrameAPI.createController(caja, {
				width: '100%',
				height: '152',
				uri: botones[0].getAttribute('data-episodio')
			}, function (reproductor) {
				botones.forEach(function (btn) {
					btn.addEventListener('click', function () {
						var uri = btn.getAttribute('data-episodio');
						reproductor.loadUri(uri);
						botones.forEach(function (otro) { otro.removeAttribute('aria-current'); });
						btn.setAttribute('aria-current', 'true');
						if (reproductor.play) { reproductor.play(); }
					});
				});
			});
		};
		</script>
		<?php
		wp_enqueue_script( 'dcj-spotify-api', 'https://open.spotify.com/embed/iframe-api/v1', array(), null, array( 'in_footer' => true ) );
		?>
	<?php else : ?>
		<div class="pildoras-embed">
			<iframe src="<?php echo esc_url( dcj_spotify_embed() ); ?>" title="<?php echo esc_attr( dcj_programa() ); ?>" height="152" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"></iframe>
		</div>
	<?php endif; ?>

	<p class="muted"><a href="<?php echo esc_url( 'https://open.spotify.com/show/' . dcj_spotify_show() ); ?>" rel="noopener" target="_blank">Abrir en Spotify</a></p>
</section>

<?php
get_footer();
