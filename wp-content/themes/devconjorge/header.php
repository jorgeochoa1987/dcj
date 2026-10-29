<?php
/**
 * Cabecera.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

$dcj_redes    = dcj_redes();
$dcj_bitacora = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/bitacora/' );
$dcj_inicio   = home_url( '/' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#0d0f0d">
	<script>document.documentElement.classList.add('js');</script>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip" href="#contenido">Saltar al contenido</a>

<svg class="defs" aria-hidden="true" width="0" height="0">
	<defs>
		<filter id="liquid" x="-30%" y="-30%" width="160%" height="160%">
			<feTurbulence type="fractalNoise" baseFrequency="0.011" numOctaves="2" seed="7" result="noise">
				<animate attributeName="baseFrequency" dur="16s" values="0.009;0.017;0.011;0.009" repeatCount="indefinite"/>
			</feTurbulence>
			<feDisplacementMap in="SourceGraphic" in2="noise" scale="70" xChannelSelector="R" yChannelSelector="G"/>
		</filter>
		<filter id="grain">
			<feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="3" stitchTiles="stitch"/>
			<feColorMatrix type="saturate" values="0"/>
		</filter>
	</defs>
</svg>
<svg class="grain" aria-hidden="true"><rect width="100%" height="100%" filter="url(#grain)"/></svg>

<div class="ambient" aria-hidden="true">
	<span class="orb orb--1 drift"></span>
	<span class="orb orb--2 drift"></span>
	<span class="orb orb--3 drift"></span>
</div>

<header class="topbar">
	<nav class="nav glass" aria-label="Principal">
		<a class="logo" href="<?php echo esc_url( $dcj_inicio ); ?>" aria-label="DevConJorge, inicio">DCJ</a>
		<button class="nav__toggle" type="button" aria-expanded="false" aria-controls="nav-links">
			<span class="sr-only">Abrir menú</span>
			<svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
		</button>
		<div class="nav__links" id="nav-links">
			<?php
			if ( has_nav_menu( 'principal' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'principal',
						'container'      => false,
						'items_wrap'     => '%3$s',
						'depth'          => 1,
						'fallback_cb'    => false,
					)
				);
			} else {
				$dcj_links = array(
					$dcj_inicio . '#videos'   => 'Videos',
					$dcj_bitacora             => 'Bitácora',
					$dcj_inicio . '#recursos' => 'Recursos',
					$dcj_inicio . '#marcas'   => 'Marcas',
					$dcj_inicio . '#sobre'    => 'Sobre mí',
				);
				foreach ( $dcj_links as $dcj_url => $dcj_txt ) {
					$dcj_actual = ( 'Bitácora' === $dcj_txt && ( is_home() || is_single() ) ) ? ' aria-current="page"' : '';
					printf( '<a href="%s"%s>%s</a>', esc_url( $dcj_url ), $dcj_actual, esc_html( $dcj_txt ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
			}
			?>
		</div>
		<a class="btn btn--lima btn--sm" href="<?php echo esc_url( $dcj_redes['whatsapp']['url'] ); ?>">Únete al canal</a>
	</nav>
</header>

<main id="contenido">
