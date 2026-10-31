<?php
/**
 * Píldoras de IA productiva, con el show de Spotify.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="wrap pildoras">
	<p class="eyebrow">Spotify</p>
	<h1 class="mega">Píldoras<span class="lima">.</span></h1>
	<p class="lead lead--xl">Caps cortos para usar la IA en el trabajo. Elige uno y dale play.</p>
	<div class="pildoras-embed">
		<iframe src="<?php echo esc_url( dcj_spotify_embed() ); ?>" title="<?php echo esc_attr( dcj_programa() ); ?>" height="352" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"></iframe>
	</div>
	<p class="muted"><a href="<?php echo esc_url( 'https://open.spotify.com/show/' . dcj_spotify_show() ); ?>" rel="noopener" target="_blank">Abrir en Spotify</a></p>
</section>

<?php
get_footer();
