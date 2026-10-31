<?php
/**
 * Píldoras de IA productiva. Capítulos agrupados por categoría.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

get_header();

$dcj_tema = isset( $_GET['tema'] ) ? sanitize_title( wp_unslash( $_GET['tema'] ) ) : '';
$dcj_term = $dcj_tema ? get_term_by( 'slug', $dcj_tema, 'dcj_categoria' ) : null;
$dcj_cats = get_terms(
	array(
		'taxonomy'   => 'dcj_categoria',
		'hide_empty' => true,
	)
);
if ( is_wp_error( $dcj_cats ) ) {
	$dcj_cats = array();
}
$dcj_base = home_url( '/pildoras/' );
$dcj_caps = dcj_caps_publicados( $dcj_term ? (int) $dcj_term->term_id : 0 );
?>

<section class="wrap pildoras">
	<p class="eyebrow"><?php echo esc_html( dcj_programa() ); ?></p>
	<h1 class="mega">Píldoras<span class="lima">.</span></h1>
	<p class="lead lead--xl">Caps cortos para usar la IA en el trabajo. Elige uno y suena abajo.</p>

	<?php if ( $dcj_cats ) : ?>
		<nav class="filtros" aria-label="Categorías">
			<a class="filtro<?php echo $dcj_term ? '' : ' is-on'; ?>" href="<?php echo esc_url( $dcj_base ); ?>"<?php echo $dcj_term ? '' : ' aria-current="page"'; ?>>Todo</a>
			<?php foreach ( $dcj_cats as $dcj_cat ) : ?>
				<?php $dcj_on = $dcj_term && (int) $dcj_term->term_id === (int) $dcj_cat->term_id; ?>
				<a class="filtro<?php echo $dcj_on ? ' is-on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tema', $dcj_cat->slug, $dcj_base ) ); ?>"<?php echo $dcj_on ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $dcj_cat->name ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php if ( $dcj_caps ) : ?>
		<div class="pildoras-grid">
			<?php
			$dcj_n = 1;
			foreach ( $dcj_caps as $dcj_cap ) {
				dcj_tarjeta_pildora( $dcj_cap, $dcj_n );
				++$dcj_n;
			}
			?>
		</div>
	<?php else : ?>
		<p class="lead">Todavía no hay capítulos publicados en esta categoría.</p>
	<?php endif; ?>
</section>

<?php
get_footer();
