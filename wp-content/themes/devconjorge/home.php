<?php
/**
 * Bitácora: lista de artículos.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

get_header();

$dcj_cats    = get_categories( array( 'hide_empty' => true ) );
$dcj_actual  = is_category() ? get_queried_object_id() : 0;
$dcj_base    = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' );
?>

<section class="wrap bitacora-hero">
	<span class="blob blob--top" aria-hidden="true"></span>
	<span class="live glass"><span class="pulse"></span>SE ACTUALIZA CON CADA VIDEO</span>
	<h1 class="mega">
		<?php
		if ( is_category() ) {
			single_cat_title();
		} else {
			echo 'Bitácora';
		}
		?>
		<span class="lima">.</span></h1>
	<p class="lead lead--xl">Lo que cuento en 60 segundos, escrito con calma: los enlaces, los comandos y las fuentes que no caben en un Short. Por el <?php echo esc_html( dcj_firma() ); ?>.</p>

	<ol class="pasos">
		<li class="glass"><span class="eyebrow">01 · PUBLICO EL VIDEO</span><span>Sale en YouTube como siempre</span></li>
		<li class="glass"><span class="eyebrow">02 · SE LEE EL VIDEO</span><span>Título, descripción y fuentes</span></li>
		<li class="glass"><span class="eyebrow">03 · SE ESCRIBE EL ARTÍCULO</span><span>Con mi voz, y lo reviso antes de publicar</span></li>
		<li class="glass"><span class="eyebrow">04 · APARECE AQUÍ</span><span>Con el video arriba</span></li>
	</ol>
</section>

<section class="wrap bitacora-lista">
	<?php if ( $dcj_cats ) : ?>
		<nav class="filtros" aria-label="Temas">
			<a class="filtro<?php echo $dcj_actual ? '' : ' is-on'; ?>" href="<?php echo esc_url( $dcj_base ); ?>"<?php echo $dcj_actual ? '' : ' aria-current="page"'; ?>>Todo</a>
			<?php foreach ( $dcj_cats as $dcj_cat ) : ?>
				<a class="filtro<?php echo $dcj_actual === $dcj_cat->term_id ? ' is-on' : ''; ?>" href="<?php echo esc_url( get_category_link( $dcj_cat ) ); ?>"<?php echo $dcj_actual === $dcj_cat->term_id ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $dcj_cat->name ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<div class="posts-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				dcj_tarjeta_post();
			endwhile;
			?>
		</div>
		<div class="paginacion">
			<?php
			the_posts_pagination(
				array(
					'prev_text' => '← Anteriores',
					'next_text' => 'Siguientes →',
				)
			);
			?>
		</div>
	<?php else : ?>
		<p class="lead">Todavía no hay artículos. El primero aparece con el próximo video.</p>
	<?php endif; ?>
</section>

<section class="wrap section">
	<div class="cta-ws glass reveal">
		<div>
			<p class="cta-ws__t">Recibe el artículo del día en WhatsApp.</p>
			<p class="muted">De lunes a viernes, 2 o 3 líneas y el enlace. Sin cadenas, sin spam.</p>
		</div>
		<a class="btn btn--lima btn--lg" href="<?php echo esc_url( dcj_redes()['whatsapp']['url'] ); ?>">Únete al canal</a>
	</div>
</section>

<?php
get_footer();
