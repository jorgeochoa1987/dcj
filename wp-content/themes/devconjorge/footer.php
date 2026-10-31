<?php
/**
 * Pie de página.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

$dcj_redes    = dcj_redes();
$dcj_bitacora = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/bitacora/' );
?>
</main>

<footer class="footer">
	<div class="wrap footer__inner">
		<div class="footer__top">
			<div class="footer__cta">
				<p class="footer__lema">Un video al día. Una conversación todos los días.</p>
				<a class="btn btn--lima" href="<?php echo esc_url( $dcj_redes['whatsapp']['url'] ); ?>">Únete al canal de WhatsApp</a>
			</div>
			<ul class="footer__redes">
				<?php foreach ( $dcj_redes as $dcj_red ) : ?>
					<li>
						<a href="<?php echo esc_url( $dcj_red['url'] ); ?>" rel="me noopener" target="_blank">
							<?php dcj_el_icono( $dcj_red['clave'], 18 ); ?>
							<span><?php echo esc_html( $dcj_red['nombre'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
				<li><a href="<?php echo esc_url( $dcj_bitacora ); ?>"><?php dcj_el_icono( 'doc', 18 ); ?><span>Bitácora</span></a></li>
			</ul>
		</div>
		<div class="footer__giant" aria-hidden="true">DEVCONJORGE</div>
		<div class="footer__legal">
			<span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( mb_strtoupper( dcj_firma(), 'UTF-8' ) ); ?> · @DEVCONJORGE</span>
			<span>HECHO DESDE EL CÓDIGO, NO DESDE EL HUMO</span>
		</div>
	</div>
</footer>

<?php
$dcj_caps    = dcj_playlist();
$dcj_primero = $dcj_caps ? $dcj_caps[0] : null;
$dcj_n_caps  = count( $dcj_caps );
$dcj_show    = dcj_programa();
if ( $dcj_primero ) :
	?>
<div class="aire-dock">
	<div class="aire glass" data-aire aria-label="<?php echo esc_attr( $dcj_show ); ?>">
		<button class="aire__play" type="button" data-aire-play aria-label="Reproducir">
			<svg class="aire__ico aire__ico--play" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
			<svg class="aire__ico aire__ico--pause" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>
		</button>
		<div class="aire__meta">
			<p class="aire__kicker">
				<span class="aire__eq" aria-hidden="true"><span></span><span></span><span></span></span>
				<?php echo esc_html( $dcj_show ); ?>
			</p>
			<p class="aire__titulo" data-aire-titulo><?php echo esc_html( $dcj_primero['titulo'] ); ?></p>
		</div>
		<div class="aire__pista">
			<span class="aire__tiempo" data-aire-ahora>0:00</span>
			<input class="aire__seek" type="range" min="0" max="1000" value="0" step="1" data-aire-seek aria-label="Posición del audio" style="--p:0%">
			<span class="aire__tiempo" data-aire-total>0:00</span>
		</div>
		<button class="aire__caps" type="button" data-aire-lista aria-expanded="false" aria-controls="aire-lista">
			<?php echo esc_html( (string) $dcj_n_caps ); ?> <?php echo 1 === $dcj_n_caps ? 'cap' : 'caps'; ?>
		</button>
		<ol class="aire__lista glass" id="aire-lista" data-aire-caps hidden>
			<li class="aire__show"><?php echo esc_html( $dcj_show ); ?></li>
			<?php foreach ( $dcj_caps as $dcj_i => $dcj_cap ) : ?>
			<li>
				<button type="button" data-src="<?php echo esc_url( $dcj_cap['url'] ); ?>" data-mime="<?php echo esc_attr( $dcj_cap['mime'] ); ?>" data-titulo="<?php echo esc_attr( $dcj_cap['titulo'] ); ?>"<?php echo 0 === $dcj_i ? ' aria-current="true"' : ''; ?>>
					<span class="aire__num"><?php echo esc_html( sprintf( '%02d', $dcj_i + 1 ) ); ?></span>
					<span><?php echo esc_html( $dcj_cap['titulo'] ); ?></span>
				</button>
			</li>
			<?php endforeach; ?>
		</ol>
		<audio preload="metadata" data-aire-audio>
			<source src="<?php echo esc_url( $dcj_primero['url'] ); ?>" type="<?php echo esc_attr( $dcj_primero['mime'] ); ?>">
		</audio>
	</div>
</div>
	<?php
endif;
wp_footer();
?>
</body>
</html>
