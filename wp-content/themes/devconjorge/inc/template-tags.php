<?php
/**
 * Piezas reutilizables de las plantillas.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

/**
 * Imprime un icono (markup estático propio del tema).
 *
 * @param string $clave Icono.
 * @param int    $size  Tamaño.
 */
function dcj_el_icono( $clave, $size = 22 ) {
	echo dcj_icono( $clave, $size ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG fijo del tema.
}

/**
 * ID de la foto de Jorge: imagen destacada de Inicio, si no la del personalizador.
 *
 * @return int
 */
function dcj_id_foto() {
	$inicio = (int) get_option( 'page_on_front' );
	$foto   = $inicio ? (int) get_post_thumbnail_id( $inicio ) : 0;
	if ( $foto ) {
		return $foto;
	}
	return (int) get_theme_mod( 'dcj_foto_id', 0 );
}

/**
 * Fotos extra puestas como bloques de imagen en Páginas → Inicio.
 *
 * @return int[]
 */
function dcj_fotos_editor() {
	$inicio = (int) get_option( 'page_on_front' );
	if ( ! $inicio ) {
		return array();
	}
	$html = (string) get_post_field( 'post_content', $inicio );
	if ( ! preg_match_all( '/wp-image-(\d+)/', $html, $m ) ) {
		return array();
	}
	$portada = dcj_id_foto();
	$ids     = array();
	foreach ( $m[1] as $id ) {
		$id = (int) $id;
		if ( $id && $id !== $portada && ! in_array( $id, $ids, true ) ) {
			$ids[] = $id;
		}
	}
	return $ids;
}

/**
 * Tablero split-flap: una celda por letra (máximo 6, regla de marca).
 *
 * @param string   $palabra Palabra.
 * @param string   $tamano  sm | xl.
 * @param int      $lima    Índice de la celda rellena de lima (-1 ninguna).
 * @param string[] $ciclo   Otras palabras de hasta 6 letras. El tablero las va mostrando.
 */
function dcj_flap( $palabra, $tamano = 'sm', $lima = -1, $ciclo = array() ) {
	$palabras = array();
	foreach ( array_merge( array( $palabra ), $ciclo ) as $item ) {
		$item = mb_substr( mb_strtoupper( $item, 'UTF-8' ), 0, 6 );
		if ( '' !== $item && ! in_array( $item, $palabras, true ) ) {
			$palabras[] = $item;
		}
	}
	$ancho = 1;
	foreach ( $palabras as $item ) {
		$ancho = max( $ancho, mb_strlen( $item ) );
	}
	$letras = mb_str_split( str_pad( $palabras[0], $ancho ) );
	$attrs  = count( $palabras ) > 1 ? ' data-palabras="' . esc_attr( implode( ',', $palabras ) ) . '"' : '';
	printf( '<span class="flaps flaps--%s" role="img" aria-label="%s"%s>', esc_attr( $tamano ), esc_attr( implode( ', ', $palabras ) ), $attrs );
	foreach ( $letras as $i => $letra ) {
		printf(
			'<span class="flap%s" style="--i:%d" aria-hidden="true">%s</span>',
			$i === $lima ? ' flap--lima' : '',
			(int) $i,
			esc_html( ' ' === $letra ? '' : $letra )
		);
	}
	echo '</span>';
}

/**
 * Firma de autor del tema.
 *
 * @return string
 */
function dcj_firma() {
	return (string) get_theme_mod( 'dcj_firma', 'Ing. Jorge Ochoa' );
}

/**
 * ID de YouTube asociado a un artículo (lo guarda el plugin de bitácora).
 *
 * @param int|null $post_id Post.
 * @return string
 */
function dcj_video_de_post( $post_id = null ) {
	$id = (string) get_post_meta( $post_id ? $post_id : get_the_ID(), '_dcj_video_id', true );
	return preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $id ) ? $id : '';
}

/**
 * Minutos de lectura.
 *
 * @param int|null $post_id Post.
 * @return int
 */
function dcj_minutos_lectura( $post_id = null ) {
	$palabras = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $post_id ? $post_id : get_the_ID() ) ) );
	return max( 1, (int) round( $palabras / 220 ) );
}

/**
 * Etiqueta corta (primera categoría que no sea "Sin categoría").
 *
 * @return string
 */
function dcj_etiqueta() {
	foreach ( (array) get_the_category() as $cat ) {
		if ( 'uncategorized' !== $cat->slug && 'sin-categoria' !== $cat->slug ) {
			return $cat->name;
		}
	}
	return 'Bitácora';
}

/**
 * Miniatura vertical del artículo: la imagen destacada o la del video.
 *
 * @param string $hook Texto de respaldo.
 */
function dcj_poster_post( $hook = '' ) {
	$video = dcj_video_de_post();
	if ( has_post_thumbnail() ) {
		the_post_thumbnail( 'dcj-vertical', array( 'class' => 'poster__img', 'loading' => 'lazy' ) );
	} elseif ( $video ) {
		printf(
			'<img class="poster__img" src="%1$s" alt="" loading="lazy" onerror="this.onerror=null;this.src=\'%2$s\'">',
			esc_url( dcj_miniatura( $video ) ),
			esc_url( dcj_miniatura( $video, 'hqdefault' ) )
		);
	} else {
		printf( '<span class="poster__hook">%s</span>', esc_html( $hook ? $hook : mb_strtoupper( get_the_title(), 'UTF-8' ) ) );
	}
}

/**
 * Nombre del programa en la barra.
 *
 * @return string
 */
function dcj_programa() {
	$nombre = trim( (string) get_theme_mod( 'dcj_programa', 'Píldoras de IA productiva' ) );
	return '' !== $nombre ? $nombre : 'Píldoras de IA productiva';
}

/**
 * Show de Spotify de Píldoras.
 *
 * @return string
 */
function dcj_spotify_show() {
	return '4m5IO1QQxJeA5FVfD6x6Q2';
}

/**
 * Embed oscuro del show.
 *
 * @return string
 */
function dcj_spotify_embed() {
	return 'https://open.spotify.com/embed/show/' . dcj_spotify_show() . '?utm_source=generator&theme=0';
}

/**
 * Capítulos públicos del show. Spotify solo embebe el último, así que la lista se lee de la página del programa.
 *
 * @return array<int, array{id:string,titulo:string}>
 */
function dcj_spotify_episodios() {
	$cache = get_transient( 'dcj_spotify_episodios' );
	if ( is_array( $cache ) ) {
		return $cache;
	}

	$respuesta = wp_remote_get(
		'https://open.spotify.com/show/' . dcj_spotify_show(),
		array(
			'timeout' => 8,
			'headers' => array(
				'User-Agent' => 'Mozilla/5.0 (compatible; DevConJorge/1.0)',
			),
		)
	);
	$items = array();
	if ( ! is_wp_error( $respuesta ) && 200 === (int) wp_remote_retrieve_response_code( $respuesta ) ) {
		$html  = wp_remote_retrieve_body( $respuesta );
		$vistos = array();
		if ( preg_match_all( '#href="/episode/([A-Za-z0-9]{22})"[\s\S]{0,600}?data-testid="episodeTitle"[\s\S]{0,300}?>([^<]+)#', $html, $found, PREG_SET_ORDER ) ) {
			foreach ( $found as $fila ) {
				$id = $fila[1];
				if ( isset( $vistos[ $id ] ) ) {
					continue;
				}
				$vistos[ $id ] = true;
				$titulo        = trim( html_entity_decode( wp_strip_all_tags( $fila[2] ), ENT_QUOTES, 'UTF-8' ) );
				if ( '' === $titulo ) {
					continue;
				}
				$items[] = array(
					'id'     => $id,
					'titulo' => $titulo,
				);
			}
		}
		$items = array_reverse( $items );
	}

	set_transient( 'dcj_spotify_episodios', $items, $items ? 30 * MINUTE_IN_SECONDS : 2 * MINUTE_IN_SECONDS );
	return $items;
}

/**
 * Audio del encabezado. Sin archivo, la barra no se pinta.
 *
 * @return array{url:string,mime:string,titulo:string}|null
 */
function dcj_audio_barra() {
	$id = (int) get_theme_mod( 'dcj_audio_id', 0 );
	if ( ! $id ) {
		return null;
	}
	$url = wp_get_attachment_url( $id );
	if ( ! $url ) {
		return null;
	}
	$titulo = trim( (string) get_theme_mod( 'dcj_audio_titulo', '' ) );
	if ( '' === $titulo ) {
		$titulo = get_the_title( $id );
	}
	if ( '' === trim( (string) $titulo ) ) {
		$titulo = 'Episodio';
	}
	$mime = get_post_mime_type( $id );
	return array(
		'url'    => $url,
		'mime'   => $mime ? $mime : 'audio/mpeg',
		'titulo' => $titulo,
	);
}

/**
 * Tarjeta de artículo de la bitácora.
 */
function dcj_tarjeta_post() {
	?>
	<a class="post-card glass tilt reveal" href="<?php the_permalink(); ?>">
		<span class="poster">
			<?php dcj_poster_post(); ?>
			<?php if ( dcj_video_de_post() ) : ?>
				<span class="poster__play" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
			<?php endif; ?>
		</span>
		<span class="post-card__body">
			<span class="eyebrow"><?php echo esc_html( dcj_etiqueta() ); ?></span>
			<span class="post-card__title"><?php the_title(); ?></span>
			<span class="post-card__excerpt"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></span>
			<span class="meta"><?php echo esc_html( mb_strtoupper( dcj_firma() . ' · ' . get_the_date( 'j M Y' ), 'UTF-8' ) ); ?></span>
		</span>
	</a>
	<?php
}
