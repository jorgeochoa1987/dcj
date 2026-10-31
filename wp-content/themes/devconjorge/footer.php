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
$dcj_episodios = dcj_spotify_episodios();
$dcj_n         = count( $dcj_episodios );
$dcj_ultimo    = $dcj_n ? $dcj_episodios[ $dcj_n - 1 ] : null;
$dcj_frame     = $dcj_ultimo
	? 'https://open.spotify.com/embed/episode/' . $dcj_ultimo['id'] . '?utm_source=generator&theme=0'
	: dcj_spotify_embed();
?>
<div class="spotify-dock" data-spotify-dock>
	<?php if ( $dcj_n ) : ?>
	<div class="spotify-dock__panel" id="spotify-caps" hidden>
		<p class="spotify-dock__show"><?php echo esc_html( dcj_programa() ); ?></p>
		<div class="spotify-dock__grid">
			<?php foreach ( $dcj_episodios as $dcj_i => $dcj_ep ) : ?>
				<?php $dcj_src = 'https://open.spotify.com/embed/episode/' . $dcj_ep['id'] . '?utm_source=generator&theme=0'; ?>
				<button type="button" data-spotify-ep="<?php echo esc_url( $dcj_src ); ?>"<?php echo $dcj_ultimo && $dcj_ep['id'] === $dcj_ultimo['id'] ? ' aria-current="true"' : ''; ?>>
					<span class="spotify-dock__num"><?php echo esc_html( sprintf( '%02d', $dcj_i + 1 ) ); ?></span>
					<span class="spotify-dock__titulo"><?php echo esc_html( $dcj_ep['titulo'] ); ?></span>
				</button>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>
	<div class="spotify-dock__bar">
		<?php if ( $dcj_n ) : ?>
		<button class="spotify-dock__caps" type="button" data-spotify-lista aria-expanded="false" aria-controls="spotify-caps"><?php echo esc_html( (string) $dcj_n ); ?> <?php echo 1 === $dcj_n ? 'cap' : 'caps'; ?></button>
		<?php endif; ?>
		<iframe data-spotify-frame src="<?php echo esc_url( $dcj_frame ); ?>" title="<?php echo esc_attr( dcj_programa() ); ?>" height="152" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"></iframe>
	</div>
</div>
<script>
(function () {
	var dock = document.querySelector('[data-spotify-dock]');
	if (!dock) { return; }
	var frame = dock.querySelector('[data-spotify-frame]');
	var lista = dock.querySelector('[data-spotify-lista]');
	var panel = dock.querySelector('.spotify-dock__panel');
	var botones = dock.querySelectorAll('[data-spotify-ep]');
	function marcar(btn) {
		botones.forEach(function (otro) { otro.removeAttribute('aria-current'); });
		if (btn) { btn.setAttribute('aria-current', 'true'); }
	}
	if (lista && panel) {
		lista.addEventListener('click', function () {
			var abierto = !panel.hidden;
			panel.hidden = abierto;
			lista.setAttribute('aria-expanded', abierto ? 'false' : 'true');
		});
	}
	function guardar(src) {
		var id = (String(src).match(/episode\/([A-Za-z0-9]+)/) || [])[1];
		if (!id) { return; }
		try { window.localStorage.setItem('dcj_spotify_cap', id); } catch (e) { /* modo privado */ }
	}
	function cerrar() {
		if (!panel || !lista) { return; }
		panel.hidden = true;
		lista.setAttribute('aria-expanded', 'false');
	}
	var recordado = '';
	try { recordado = window.localStorage.getItem('dcj_spotify_cap') || ''; } catch (e) { recordado = ''; }
	if (recordado) {
		var previo = dock.querySelector('[data-spotify-ep*="' + recordado + '"]');
		if (previo && frame) {
			frame.src = previo.getAttribute('data-spotify-ep');
			marcar(previo);
		}
	}
	botones.forEach(function (btn) {
		btn.addEventListener('click', function () {
			var src = btn.getAttribute('data-spotify-ep');
			if (frame) { frame.src = src; }
			guardar(src);
			marcar(btn);
			cerrar();
		});
	});
	document.querySelectorAll('[data-episodio]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var uri = btn.getAttribute('data-episodio') || '';
			var id = uri.split(':').pop();
			var match = id ? dock.querySelector('[data-spotify-ep*="' + id + '"]') : null;
			if (match) { match.click(); }
			document.querySelectorAll('[data-episodio]').forEach(function (otro) { otro.removeAttribute('aria-current'); });
			btn.setAttribute('aria-current', 'true');
		});
	});
})();
</script>
<?php wp_footer(); ?>
</body>
</html>
