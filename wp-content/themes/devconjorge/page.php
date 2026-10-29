<?php
/**
 * Páginas sueltas (taller, marcas, recursos…).
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'wrap pagina' ); ?>>
		<h1 class="articulo__titulo"><?php the_title(); ?></h1>
		<div class="prosa"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;

get_footer();
