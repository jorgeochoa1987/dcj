<?php
/**
 * Artículo de la bitácora.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$dcj_video = dcj_video_de_post();
	$dcj_base  = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' );
	?>
	<article <?php post_class( 'wrap articulo' ); ?>>
		<?php if ( $dcj_video ) : ?>
			<aside class="articulo__video">
				<span class="blob blob--lima" aria-hidden="true"></span>
				<div class="video-vertical glass ring">
					<iframe
						src="<?php echo esc_url( 'https://www.youtube-nocookie.com/embed/' . $dcj_video . '?rel=0&modestbranding=1&playsinline=1' ); ?>"
						title="<?php echo esc_attr( get_the_title() ); ?>"
						loading="lazy"
						allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
						allowfullscreen></iframe>
				</div>
				<div class="mini-redes glass">
					<a href="<?php echo esc_url( 'https://youtube.com/shorts/' . $dcj_video ); ?>" target="_blank" rel="noopener">YouTube</a>
					<a href="<?php echo esc_url( dcj_redes()['tiktok']['url'] ); ?>" target="_blank" rel="noopener">TikTok</a>
					<a href="<?php echo esc_url( dcj_redes()['instagram']['url'] ); ?>" target="_blank" rel="noopener">Instagram</a>
				</div>
			</aside>
		<?php endif; ?>

		<div class="articulo__cuerpo">
			<a class="miga" href="<?php echo esc_url( $dcj_base ); ?>">← BITÁCORA / <?php echo esc_html( mb_strtoupper( dcj_etiqueta(), 'UTF-8' ) ); ?></a>
			<h1 class="articulo__titulo"><?php the_title(); ?></h1>

			<div class="byline glass">
				<span class="avatar avatar--lg" aria-hidden="true">DCJ</span>
				<span class="byline__who">
					<b><?php echo esc_html( dcj_firma() ); ?></b>
					<span class="muted">Desarrollador full stack · @devconjorge</span>
				</span>
				<span class="meta"><?php echo esc_html( mb_strtoupper( get_the_date( 'j M Y' ) . ' · ' . dcj_minutos_lectura() . ' MIN', 'UTF-8' ) ); ?></span>
			</div>

			<div class="prosa">
				<?php the_content(); ?>
			</div>

			<div class="comunidad glass">
				<div>
					<p class="eyebrow eyebrow--coral">¿TÚ QUÉ HARÍAS?</p>
					<p class="comunidad__t">Cuéntame en los comentarios del video y lo respondo en el próximo.</p>
				</div>
				<a class="btn btn--coral" href="<?php echo esc_url( $dcj_video ? 'https://youtube.com/shorts/' . $dcj_video : dcj_redes()['youtube']['url'] ); ?>" target="_blank" rel="noopener">Comentar en YouTube</a>
			</div>
		</div>
	</article>

	<?php
	$dcj_mas = new WP_Query(
		array(
			'posts_per_page'      => 3,
			'post__not_in'        => array( get_the_ID() ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	if ( $dcj_mas->have_posts() ) :
		?>
		<section class="wrap section section--tight">
			<p class="eyebrow">SIGUE LEYENDO</p>
			<div class="grid-3">
				<?php
				while ( $dcj_mas->have_posts() ) :
					$dcj_mas->the_post();
					?>
					<a class="lista__item glass tilt" href="<?php the_permalink(); ?>">
						<span class="eyebrow"><?php echo esc_html( dcj_etiqueta() ); ?></span>
						<span class="lista__title"><?php the_title(); ?></span>
					</a>
				<?php endwhile; ?>
			</div>
		</section>
		<?php
	endif;
	wp_reset_postdata();
endwhile;

get_footer();
