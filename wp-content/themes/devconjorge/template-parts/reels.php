<?php
/**
 * "Míralo en 60 s": feed vertical de Shorts que se siente como una app.
 *
 * @package devconjorge
 *
 * @var array $args { videos: array }
 */

defined( 'ABSPATH' ) || exit;

$dcj_videos = $args['videos'] ?? array();
$dcj_canal  = dcj_redes()['youtube']['url'];
$dcj_sub    = add_query_arg( 'sub_confirmation', '1', untrailingslashit( $dcj_canal ) );
$dcj_n      = count( $dcj_videos );
?>
<section class="section wrap reels-section" id="videos" aria-labelledby="videos-t">
	<div class="reels-section__copy reveal">
		<p class="eyebrow">MÍRALO EN 60 S</p>
		<h2 id="videos-t" class="h2">Desliza como en tu app. Léelo con calma después.</h2>
		<p class="lead">Mis últimos Shorts, aquí mismo. Cada uno tiene su artículo en la bitácora con las fuentes y los enlaces.</p>

		<ul class="gestos">
			<li><span class="gestos__k">↕</span>Desliza o usa las flechas para cambiar de video</li>
			<li><span class="gestos__k">●</span>Toca una vez para pausar</li>
			<li><span class="gestos__k">♥</span>Toca dos veces si te gustó</li>
		</ul>

		<?php if ( $dcj_n > 1 ) : ?>
			<div class="siguiente">
				<p class="eyebrow">EN LA FILA</p>
				<ol class="siguiente__lista">
					<?php foreach ( $dcj_videos as $dcj_i => $dcj_v ) : ?>
						<li>
							<button type="button" class="siguiente__item" data-ir-reel="<?php echo esc_attr( $dcj_i ); ?>">
								<img src="<?php echo esc_url( dcj_miniatura( $dcj_v['id'], 'hqdefault' ) ); ?>" alt="" loading="lazy" width="64" height="36">
								<span><?php echo esc_html( $dcj_v['titulo'] ); ?></span>
							</button>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		<?php endif; ?>
	</div>

	<div class="reels" data-reels>
		<span class="blob blob--lima liquid reels__glow" aria-hidden="true"></span>
		<div class="reels__device glass ring">
			<div class="reels__bar">
				<span class="reels__tabs">
					<span class="reels__tab is-on">Para ti</span>
					<a class="reels__tab" href="<?php echo esc_url( get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/bitacora/' ) ); ?>">Bitácora</a>
				</span>
				<button class="reels__mute" type="button" data-mute aria-pressed="true">
					<svg class="i-off" aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H3v6h3l5 4z"/><path d="M22 9l-6 6M16 9l6 6"/></svg>
					<svg class="i-on" aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M19 5a10 10 0 0 1 0 14"/></svg>
					<span class="sr-only" data-mute-label>Activar sonido</span>
				</button>
			</div>

			<div class="reels__progress" aria-hidden="true">
				<?php for ( $dcj_i = 0; $dcj_i < $dcj_n; $dcj_i++ ) : ?>
					<span class="<?php echo 0 === $dcj_i ? 'is-on' : ''; ?>"></span>
				<?php endfor; ?>
			</div>

			<div class="reels__feed" tabindex="0" role="region" aria-roledescription="carrusel vertical" aria-label="Shorts de @devconjorge">
				<?php if ( ! $dcj_n ) : ?>
					<div class="reel reel--vacio">
						<p>Los videos aparecen aquí apenas el canal responda. Mientras tanto, <a href="<?php echo esc_url( $dcj_canal ); ?>">míralos en YouTube</a>.</p>
					</div>
				<?php endif; ?>
				<?php foreach ( $dcj_videos as $dcj_i => $dcj_v ) : ?>
					<article class="reel" data-id="<?php echo esc_attr( $dcj_v['id'] ); ?>" data-index="<?php echo esc_attr( $dcj_i ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Video %d de %d: %s', $dcj_i + 1, $dcj_n, $dcj_v['titulo'] ) ); ?>">
						<img class="reel__poster" src="<?php echo esc_url( dcj_miniatura( $dcj_v['id'] ) ); ?>" alt="" <?php echo $dcj_i > 1 ? 'loading="lazy"' : ''; ?> onerror="this.onerror=null;this.src='<?php echo esc_url( dcj_miniatura( $dcj_v['id'], 'hqdefault' ) ); ?>'">
						<div class="reel__player"></div>
						<div class="reel__tap" data-tap></div>
						<span class="reel__pause" aria-hidden="true"><svg width="34" height="34" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
						<span class="reel__shade" aria-hidden="true"></span>

						<div class="reel__info">
							<div class="reel__autor">
								<span class="avatar" aria-hidden="true">DCJ</span>
								<span class="reel__handle">@devconjorge</span>
								<a class="pill" href="<?php echo esc_url( $dcj_sub ); ?>" target="_blank" rel="noopener">Seguir</a>
							</div>
							<p class="reel__titulo"><?php echo esc_html( $dcj_v['titulo'] ); ?></p>
							<p class="reel__meta">
								<?php echo esc_html( dcj_fecha_corta( $dcj_v['fecha'] ) ); ?>
								<?php if ( $dcj_v['vistas'] ) : ?>
									· <?php echo esc_html( dcj_cifra_corta( $dcj_v['vistas'] ) ); ?> vistas
								<?php endif; ?>
							</p>
							<?php if ( $dcj_v['post_url'] ) : ?>
								<a class="reel__leer" href="<?php echo esc_url( $dcj_v['post_url'] ); ?>"><?php dcj_el_icono( 'doc', 16 ); ?> Leer el artículo</a>
							<?php endif; ?>
						</div>

						<div class="reel__rail">
							<button type="button" class="rail-btn" data-like aria-pressed="false">
								<svg aria-hidden="true" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/></svg>
								<span>Me gusta</span>
							</button>
							<a class="rail-btn" href="<?php echo esc_url( $dcj_v['url'] ); ?>" target="_blank" rel="noopener">
								<svg aria-hidden="true" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M4 5h16v11H9l-5 4z"/></svg>
								<span>Comentar</span>
							</a>
							<button type="button" class="rail-btn" data-share data-url="<?php echo esc_url( $dcj_v['url'] ); ?>" data-title="<?php echo esc_attr( $dcj_v['titulo'] ); ?>">
								<svg aria-hidden="true" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 5l7 7-7 7v-4c-6 0-9 2-11 5 1-6 4-10 11-11z"/></svg>
								<span>Compartir</span>
							</button>
							<a class="rail-btn" href="<?php echo esc_url( $dcj_v['url'] ); ?>" target="_blank" rel="noopener">
								<?php dcj_el_icono( 'youtube', 26 ); ?>
								<span>YouTube</span>
							</a>
						</div>
						<span class="reel__heart" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/></svg></span>
					</article>
				<?php endforeach; ?>
			</div>

			<div class="reels__nav">
				<button type="button" data-prev aria-label="Video anterior"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 15l6-6 6 6"/></svg></button>
				<button type="button" data-next aria-label="Video siguiente"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></button>
			</div>
			<div class="toast" role="status" aria-live="polite" data-toast></div>
		</div>
	</div>
</section>
