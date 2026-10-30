<?php
/**
 * Home de devconjorge.com.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

get_header();

$dcj_redes    = dcj_redes();
$dcj_total    = dcj_total_seguidores();
$dcj_videos   = dcj_youtube_videos( (int) get_theme_mod( 'dcj_feed_total', 8 ) );
$dcj_ultimo   = $dcj_videos[0] ?? null;
$dcj_bitacora = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/bitacora/' );
$dcj_suscrito = isset( $_GET['suscrito'] ) ? sanitize_key( wp_unslash( $_GET['suscrito'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
?>

<section class="hero wrap" id="inicio">
	<div class="ghost" aria-hidden="true"><div class="ghost__track"><span>IA SIN HUMO</span><span>IA SIN HUMO</span><span>IA SIN HUMO</span><span>IA SIN HUMO</span></div></div>

	<div class="hero__copy">
		<p class="eyebrow">IA EN ESPAÑOL · UN VIDEO AL DÍA</p>
		<h1 class="hero__title">
			<span class="word" style="--d:.05s">Explico</span>
			<span class="word hl" style="--d:.15s">IA</span>
			<span class="word" style="--d:.25s">en</span>
			<span class="word" style="--d:.35s">español,</span>
			<span class="word muted" style="--d:.5s">con</span>
			<span class="word muted" style="--d:.6s">el</span>
			<span class="word muted" style="--d:.7s">código</span>
			<span class="word muted" style="--d:.8s">abierto.</span>
		</h1>

		<div class="humo glass">
			<span class="humo__txt">desde el código, no desde el</span>
			<?php dcj_flap( 'HUMO' ); ?>
		</div>

		<p class="lead">Noticias de inteligencia artificial en 60 segundos, de lunes a viernes. Cada video trae la fuente en pantalla y se convierte en un artículo que puedes leer con calma.</p>

		<div class="row">
			<a class="btn btn--lima btn--lg glow" href="<?php echo esc_url( $dcj_redes['whatsapp']['url'] ); ?>">Únete al canal de WhatsApp <?php dcj_el_icono( 'flecha', 18 ); ?></a>
			<a class="btn btn--glass btn--lg" href="#videos">Míralo en 60 s</a>
		</div>

		<div class="dock glass">
			<?php foreach ( $dcj_redes as $dcj_red ) : ?>
				<a class="dock__item" href="<?php echo esc_url( $dcj_red['url'] ); ?>" aria-label="<?php echo esc_attr( $dcj_red['nombre'] ); ?>" rel="me noopener" target="_blank"><?php dcj_el_icono( $dcj_red['clave'] ); ?></a>
			<?php endforeach; ?>
			<span class="dock__txt">+<span data-count="<?php echo esc_attr( $dcj_total ); ?>" data-short="1"><?php echo esc_html( dcj_cifra_corta( $dcj_total ) ); ?></span> personas · <span class="mono">@devconjorge</span></span>
		</div>
	</div>

	<div class="hero__stage">
		<span class="blob blob--lima liquid" aria-hidden="true"></span>
		<span class="blob blob--coral" aria-hidden="true"></span>

		<?php if ( $dcj_ultimo ) : ?>
			<a class="phone glass float ring shine" href="#videos" data-abrir-reel="0">
				<img class="phone__bg" src="<?php echo esc_url( dcj_miniatura( $dcj_ultimo['id'] ) ); ?>" alt="" onerror="this.onerror=null;this.src='<?php echo esc_url( dcj_miniatura( $dcj_ultimo['id'], 'hqdefault' ) ); ?>'">
				<span class="phone__top">
					<span class="chip chip--lima">ÚLTIMO SHORT</span>
					<span class="mono small"><?php echo esc_html( dcj_fecha_corta( $dcj_ultimo['fecha'] ) ); ?></span>
				</span>
				<span class="phone__hook"><?php echo esc_html( $dcj_ultimo['hook'] ); ?></span>
				<span class="phone__bottom">
					<span class="bar"><span></span></span>
					<span class="btn btn--dark btn--block">Verlo ahora</span>
				</span>
			</a>
		<?php endif; ?>

		<div class="chip-float glass float" style="--delay:-3s">
			<span class="chip-float__icon"><?php dcj_el_icono( 'doc', 18 ); ?></span>
			<span>Artículo en la bitácora<br><span class="muted">desde cada video</span></span>
		</div>
		<div class="chip-float chip-float--b glass float" style="--delay:-5s">
			<span class="eyebrow eyebrow--coral">¿TÚ QUÉ HARÍAS?</span>
			<span>Comenta y lo respondo mañana</span>
		</div>
	</div>
</section>

<div class="marquee" aria-hidden="true">
	<div class="marquee__track">
		<?php for ( $dcj_i = 0; $dcj_i < 2; $dcj_i++ ) : ?>
			<span>IA LOCAL</span><b>/</b><span>AUTOMATIZACIÓN</span><b>/</b><span>MODELOS ABIERTOS</span><b>/</b><span>AGENTES</span><b>/</b><span>HERRAMIENTAS GRATIS</span><b>/</b><span>NOTICIAS SIN HUMO</span><b>/</b>
		<?php endfor; ?>
	</div>
</div>

<section class="section wrap" id="comunidad" aria-labelledby="comunidad-t">
	<div class="section__head reveal">
		<p class="eyebrow">LA COMUNIDAD</p>
		<h2 id="comunidad-t" class="h2"><span class="big-count">+<span data-count="<?php echo esc_attr( $dcj_total ); ?>"><?php echo esc_html( number_format_i18n( $dcj_total ) ); ?></span></span> personas aprendiendo IA conmigo.</h2>
	</div>
	<ul class="redes-grid">
		<?php $dcj_i = 0; foreach ( $dcj_redes as $dcj_red ) : ?>
			<li class="red glass tilt reveal red--<?php echo esc_attr( $dcj_red['clave'] ); ?>" style="--i:<?php echo (int) $dcj_i; ?>">
				<span class="red__top">
					<span class="red__icon"><?php dcj_el_icono( $dcj_red['clave'], 24 ); ?></span>
					<span class="red__name"><?php echo esc_html( $dcj_red['nombre'] ); ?></span>
					<?php if ( ! empty( $dcj_red['en_vivo'] ) ) : ?>
						<span class="live"><span class="pulse"></span>EN VIVO</span>
					<?php endif; ?>
				</span>
				<?php if ( $dcj_red['cifra'] > 0 ) : ?>
					<span class="red__num" data-count="<?php echo esc_attr( $dcj_red['cifra'] ); ?>"><?php echo esc_html( number_format_i18n( $dcj_red['cifra'] ) ); ?></span>
					<span class="red__unit"><?php echo esc_html( $dcj_red['unidad'] ); ?></span>
				<?php else : ?>
					<span class="red__num red__num--txt">Nuevo</span>
					<span class="red__unit"><?php echo esc_html( 'whatsapp' === $dcj_red['clave'] ? 'noticias diarias en tu chat' : $dcj_red['unidad'] ); ?></span>
				<?php endif; ?>
				<a class="btn btn--glass btn--block" href="<?php echo esc_url( $dcj_red['url'] ); ?>" rel="me noopener" target="_blank" aria-label="<?php echo esc_attr( $dcj_red['accion'] . ' en ' . $dcj_red['nombre'] ); ?>"><?php echo esc_html( $dcj_red['accion'] ); ?></a>
			</li>
			<?php ++$dcj_i; endforeach; ?>
	</ul>
</section>

<a class="wa-pegado" data-wa-pegado href="<?php echo esc_url( $dcj_redes['whatsapp']['url'] ); ?>" rel="noopener" target="_blank">
	<?php dcj_el_icono( 'whatsapp', 20 ); ?>
	<span>Únete a WhatsApp</span>
</a>

<section class="section wrap" aria-labelledby="porque-t">
	<div class="section__head reveal">
		<p class="eyebrow">POR QUÉ SEGUIRME</p>
		<h2 id="porque-t" class="h2">Lo que vas a ganar si te quedas.</h2>
	</div>
	<div class="grid-3">
		<article class="tile glass tilt reveal">
			<span class="tile__n">01</span>
			<h3 class="h3">La noticia de IA del día, en 60 segundos</h3>
			<p>Lo que cambió hoy y si te afecta, con la fuente en pantalla. Sin traducir titulares gringos.</p>
		</article>
		<article class="tile glass tilt reveal">
			<span class="tile__n">02</span>
			<h3 class="h3">IA conectada con tu día a día</h3>
			<p>Cada noticia aterrizada en algo que usas: tu celular, tu trabajo, tu bolsillo.</p>
		</article>
		<article class="tile glass tilt reveal">
			<span class="tile__n">03</span>
			<h3 class="h3">Herramientas que puedes abrir hoy</h3>
			<p>IA gratis y local, probada por un dev antes de recomendártela. Si no funciona, te lo digo.</p>
		</article>
	</div>
</section>

<section class="hype reveal" aria-label="Cero hype">
	<p class="eyebrow">LA ÚNICA REGLA DE ESTE CANAL</p>
	<?php dcj_flap( '0HYPE', 'xl', 0 ); ?>
	<p class="hype__txt">Evitemos el humo. Si no se puede abrir, citar o repetir, no va en el video.</p>
	<ul class="hype-reglas">
		<li>
			<span class="eyebrow">PROBARLO</span>
			<p>Lo abro en mi computador antes de recomendártelo. Si no arranca, no sale.</p>
		</li>
		<li>
			<span class="eyebrow">LA FUENTE</span>
			<p>Cada dato lleva de dónde salió. Si no la encuentro, ese dato no entra.</p>
		</li>
		<li>
			<span class="eyebrow">EL CÓMO</span>
			<p>Nada de «esto lo cambia todo» sin el comando, el límite o el precio.</p>
		</li>
		<li>
			<span class="eyebrow">SIN DISFRAZ</span>
			<p>Si es un anuncio, se nota. Si el producto no sirve, no lo publico.</p>
		</li>
	</ul>
</section>

<?php get_template_part( 'template-parts/reels', null, array( 'videos' => $dcj_videos ) ); ?>

<?php
$dcj_posts = new WP_Query(
	array(
		'posts_per_page'      => 4,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
if ( $dcj_posts->have_posts() ) :
	?>
	<section class="section wrap" id="bitacora" aria-labelledby="bitacora-t">
		<div class="section__head section__head--row reveal">
			<div>
				<p class="eyebrow">BITÁCORA · SE ACTUALIZA SOLA</p>
				<h2 id="bitacora-t" class="h2">Cada video se vuelve un artículo.</h2>
				<p class="lead">Con los enlaces, los comandos y las fuentes que no caben en 60 segundos.</p>
			</div>
			<a class="btn btn--glass" href="<?php echo esc_url( $dcj_bitacora ); ?>">Ver toda la bitácora</a>
		</div>
		<div class="bitacora-home">
			<?php
			$dcj_posts->the_post();
			?>
			<a class="destacado glass reveal" href="<?php the_permalink(); ?>">
				<span class="blob blob--sm" aria-hidden="true"></span>
				<span class="chip chip--lima">DESTACADO</span>
				<span class="destacado__body">
					<span class="destacado__title"><?php the_title(); ?></span>
					<span class="lead"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></span>
					<span class="meta"><?php echo esc_html( mb_strtoupper( dcj_firma() . ' · ' . get_the_date( 'j M Y' ) . ' · ' . dcj_minutos_lectura() . ' MIN', 'UTF-8' ) ); ?></span>
				</span>
			</a>
			<div class="lista">
				<?php
				while ( $dcj_posts->have_posts() ) :
					$dcj_posts->the_post();
					?>
					<a class="lista__item glass reveal" href="<?php the_permalink(); ?>">
						<span class="eyebrow"><?php echo esc_html( dcj_etiqueta() ); ?></span>
						<span class="lista__title"><?php the_title(); ?></span>
						<span class="meta"><?php echo esc_html( get_the_date( 'j M' ) . ' · ' . dcj_minutos_lectura() . ' min' ); ?></span>
					</a>
				<?php endwhile; ?>
			</div>
		</div>
	</section>
	<?php
endif;
wp_reset_postdata();
?>

<section class="section wrap" id="recursos" aria-labelledby="recursos-t">
	<div class="recursos glass reveal shine">
		<span class="blob blob--coral-soft" aria-hidden="true"></span>
		<div class="recursos__copy">
			<p class="eyebrow">RECURSOS Y TALLERES GRATIS</p>
			<h2 id="recursos-t" class="h2">Cosas que puedes abrir ahora, no un pitch.</h2>
			<ul class="checks">
				<li><?php dcj_el_icono( 'check', 20 ); ?>Grabación del taller "Tu propia IA gratis en tu computador"</li>
				<li><?php dcj_el_icono( 'check', 20 ); ?>Plantillas de prompts y configuraciones que uso a diario</li>
				<li><?php dcj_el_icono( 'check', 20 ); ?>Aviso del próximo taller en vivo antes que nadie</li>
			</ul>
		</div>
		<form class="form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="dcj_suscribir">
			<?php wp_nonce_field( 'dcj_suscribir', 'dcj_nonce' ); ?>
			<label class="hp" aria-hidden="true">No llenar <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label>
			<label for="correo">Tu correo</label>
			<input id="correo" name="correo" type="email" required autocomplete="email" placeholder="tucorreo@ejemplo.com">
			<button class="btn btn--lima btn--lg" type="submit">Quiero los recursos</button>
			<?php if ( '1' === $dcj_suscrito ) : ?>
				<p class="form__ok" role="status">Listo. Revisa tu correo en los próximos días.</p>
			<?php elseif ( 'error' === $dcj_suscrito ) : ?>
				<p class="form__error" role="alert">Ese correo no se ve bien. Inténtalo de nuevo.</p>
			<?php else : ?>
				<p class="small muted">Un correo cuando hay algo útil. Nada más.</p>
			<?php endif; ?>
		</form>
	</div>
</section>

<section class="section wrap" id="marcas" aria-labelledby="marcas-t">
	<div class="section__head reveal">
		<p class="eyebrow">PARA MARCAS</p>
		<h2 id="marcas-t" class="h2">Tu herramienta, explicada por alguien que la usa.</h2>
		<p class="lead">Pruebo el producto antes de grabar. Si sirve, lo muestro funcionando; si no, no lo publico. Por eso la audiencia confía.</p>
	</div>
	<div class="grid-4">
		<div class="stat glass tilt reveal"><span class="stat__n lima">+<span data-count="<?php echo esc_attr( $dcj_total ); ?>" data-short="1"><?php echo esc_html( dcj_cifra_corta( $dcj_total ) ); ?></span></span><span>seguidores entre todas las redes</span></div>
		<div class="stat glass tilt reveal"><span class="stat__n"><span data-count="5">5</span>×</span><span>videos por semana, de lunes a viernes</span></div>
		<div class="stat glass tilt reveal"><span class="stat__n">LatAm</span><span>audiencia hispanohablante, sobre todo Colombia y México</span></div>
		<div class="stat glass tilt reveal"><span class="stat__n"><span data-count="8">8</span>+</span><span>años escribiendo código en producción</span></div>
	</div>
	<div class="formatos glass reveal">
		<div class="row">
			<span class="tag">SHORT INTEGRADO</span>
			<span class="tag">VIDEO PARA TU CUENTA</span>
			<span class="tag">ARTÍCULO EN LA BITÁCORA</span>
			<span class="tag">MENCIÓN EN WHATSAPP</span>
		</div>
		<div class="row">
			<?php
			$dcj_kit    = get_theme_mod( 'dcj_media_kit_url', '' );
			$dcj_correo = get_theme_mod( 'dcj_correo_marcas', '' );
			if ( $dcj_kit ) :
				?>
				<a class="btn btn--lima" href="<?php echo esc_url( $dcj_kit ); ?>">Pide el media kit</a>
			<?php endif; ?>
			<?php if ( $dcj_correo ) : ?>
				<a class="btn btn--ghost" href="<?php echo esc_url( 'mailto:' . antispambot( $dcj_correo ) ); ?>">Escríbeme</a>
			<?php endif; ?>
		</div>
	</div>
</section>

<section class="section wrap sobre" id="sobre" aria-labelledby="sobre-t">
	<div class="sobre__foto">
		<span class="blob blob--lima" aria-hidden="true"></span>
		<span class="arco glass">
			<?php
			$dcj_foto = dcj_id_foto();
			if ( $dcj_foto ) {
				echo wp_get_attachment_image( $dcj_foto, 'large', false, array( 'class' => 'arco__img', 'alt' => dcj_firma() ) );
			} elseif ( current_user_can( 'edit_pages' ) ) {
				echo '<span class="mono small">Páginas → Inicio → imagen destacada</span>';
			}
			?>
		</span>
	</div>
	<div class="sobre__copy reveal">
		<p class="eyebrow"><?php echo esc_html( mb_strtoupper( dcj_firma(), 'UTF-8' ) ); ?></p>
		<h2 id="sobre-t" class="h2">Sigo escribiendo código. Por eso lo que explico se sostiene.</h2>
		<p class="lead">Soy ingeniero y desarrollador full stack con más de 8 años en producción: React, Node.js, APIs, Docker y liderazgo de equipos. Hoy construyo agentes y automatizaciones con IA, y cuento en español lo que aprendo en el camino.</p>
	</div>
	<?php
	$dcj_fotos = dcj_fotos_editor();
	if ( $dcj_fotos ) :
		?>
		<ul class="fotos-editor">
			<?php foreach ( $dcj_fotos as $dcj_fid ) : ?>
				<li><?php echo wp_get_attachment_image( $dcj_fid, 'large', false, array( 'alt' => dcj_firma() ) ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<?php
get_footer();
