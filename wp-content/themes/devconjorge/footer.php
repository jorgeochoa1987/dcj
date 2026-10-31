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

<?php if ( ! is_page( 'pildoras' ) ) : ?>
<div class="spotify-dock">
	<iframe src="<?php echo esc_url( dcj_spotify_embed() ); ?>" title="<?php echo esc_attr( dcj_programa() ); ?>" height="152" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"></iframe>
</div>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
