<?php
/**
 * YouTube: últimos videos por RSS y suscriptores por API.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

/**
 * ID del canal configurado.
 *
 * @return string
 */
function dcj_canal_id() {
	return (string) get_theme_mod( 'dcj_canal_id', 'UCQMAx4udQPfGPuzoPzh1XAQ' );
}

/**
 * Suscriptores de YouTube en vivo (cache de 6 horas). 0 si no hay API key.
 *
 * @return int
 */
function dcj_youtube_suscriptores() {
	$key = trim( (string) get_theme_mod( 'dcj_yt_api_key', '' ) );
	if ( '' === $key ) {
		return 0;
	}

	$cache = get_transient( 'dcj_yt_subs' );
	if ( false !== $cache ) {
		return (int) $cache;
	}

	$url  = add_query_arg(
		array(
			'part' => 'statistics',
			'id'   => dcj_canal_id(),
			'key'  => $key,
		),
		'https://www.googleapis.com/youtube/v3/channels'
	);
	$resp = wp_remote_get( $url, array( 'timeout' => 8 ) );
	$subs = 0;
	if ( ! is_wp_error( $resp ) && 200 === wp_remote_retrieve_response_code( $resp ) ) {
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		$subs = isset( $data['items'][0]['statistics']['subscriberCount'] ) ? (int) $data['items'][0]['statistics']['subscriberCount'] : 0;
	}

	// Si falla, se reintenta en 30 minutos en vez de 6 horas.
	set_transient( 'dcj_yt_subs', $subs, $subs ? 6 * HOUR_IN_SECONDS : 30 * MINUTE_IN_SECONDS );
	return $subs;
}

/**
 * Últimos videos del canal desde el RSS público (cache de 30 minutos).
 *
 * @param int $total Cuántos devolver.
 * @return array[] Cada video: id, titulo, hook, fecha (timestamp), descripcion, vistas, url, post_url.
 */
function dcj_youtube_videos( $total = 8 ) {
	$videos = get_transient( 'dcj_yt_feed_v2' );

	if ( false === $videos ) {
		$videos = dcj_videos_desde_rss();
		if ( ! $videos ) {
			// El RSS del canal responde 404. Los Shorts públicos no piden OAuth ni API key.
			$videos = dcj_videos_desde_shorts();
		}

		set_transient( 'dcj_yt_feed_v2', $videos, $videos ? 30 * MINUTE_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
	}

	$videos = array_slice( $videos, 0, max( 1, (int) $total ) );

	foreach ( $videos as &$v ) {
		$v['post_url'] = dcj_post_de_video( $v['id'] );
	}
	unset( $v );

	return $videos;
}

/**
 * Últimos videos por el RSS público. Vacío si YouTube no lo entrega.
 *
 * @return array
 */
function dcj_videos_desde_rss() {
	$resp = wp_remote_get(
		'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode( dcj_canal_id() ),
		array(
			'timeout'    => 10,
			'user-agent' => 'Mozilla/5.0 (compatible; DevConJorge/1.0; +https://devconjorge.com)',
		)
	);
	if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
		return array();
	}
	return dcj_parsear_feed( wp_remote_retrieve_body( $resp ) );
}

/**
 * Handle del canal a partir del enlace guardado en el personalizador.
 *
 * @return string
 */
function dcj_youtube_handle() {
	$url = (string) get_theme_mod( 'dcj_youtube_url', 'https://youtube.com/@devconjorge' );
	if ( preg_match( '~youtube\.com/@([A-Za-z0-9._-]{2,40})~', $url, $m ) ) {
		return $m[1];
	}
	return 'devconjorge';
}

/**
 * Shorts públicos de la pestaña /shorts. Sin OAuth y sin API key.
 *
 * @return array Misma forma que el RSS: id, titulo, hook, fecha, descripcion, vistas, url.
 */
function dcj_videos_desde_shorts() {
	$resp = wp_remote_get(
		'https://www.youtube.com/@' . rawurlencode( dcj_youtube_handle() ) . '/shorts',
		array(
			'timeout'    => 12,
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
			'headers'    => array( 'Accept-Language' => 'es-CO,es;q=0.9' ),
		)
	);
	if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
		return array();
	}

	$cuerpo = wp_remote_retrieve_body( $resp );
	if ( ! preg_match_all( '/"entityId":"shorts-shelf-item-([A-Za-z0-9_-]{11})","accessibilityText":"((?:\\\\.|[^"\\\\])*)"/', $cuerpo, $hallados, PREG_SET_ORDER ) ) {
		return array();
	}

	$videos = array();
	$vistos = array();
	foreach ( $hallados as $fila ) {
		$id = $fila[1];
		if ( isset( $vistos[ $id ] ) ) {
			continue;
		}
		$vistos[ $id ] = true;
		$texto         = json_decode( '"' . $fila[2] . '"' );
		if ( ! is_string( $texto ) ) {
			$texto = $fila[2];
		}
		$vistas = dcj_vistas_de_texto( $texto );
		$titulo = preg_replace( '/,\s*[\d.,]+\s*(?:mil|mill[oó]n|millones|[kKmM])?\s*vistas?\s*-\s*(?:reproducir|play)\s+Short$/iu', '', $texto );
		$titulo = dcj_limpiar_titulo( (string) $titulo );
		if ( '' === $titulo ) {
			continue;
		}
		$videos[] = array(
			'id'          => $id,
			'titulo'      => $titulo,
			'hook'        => dcj_hook( $titulo ),
			'fecha'       => 0,
			'descripcion' => '',
			'vistas'      => $vistas,
			'url'         => 'https://youtube.com/shorts/' . $id,
		);
		if ( count( $videos ) >= 12 ) {
			break;
		}
	}
	return $videos;
}

/**
 * "1,2 mil vistas" o "206 vistas" → entero.
 *
 * @param string $texto Texto de accesibilidad del Short.
 * @return int
 */
function dcj_vistas_de_texto( $texto ) {
	if ( ! preg_match( '/([\d.,]+)\s*(mil|mill[oó]n|millones|[kKmM])?\s*vistas?/iu', $texto, $m ) ) {
		return 0;
	}
	$n = str_replace( '.', '', $m[1] );
	$n = (float) str_replace( ',', '.', $n );
	$u = isset( $m[2] ) ? mb_strtolower( $m[2], 'UTF-8' ) : '';
	if ( false !== mb_strpos( $u, 'mill' ) || 'm' === $u ) {
		$n *= 1000000;
	} elseif ( 'mil' === $u || 'k' === $u ) {
		$n *= 1000;
	}
	return (int) round( $n );
}

/**
 * Convierte el XML del feed de YouTube en un arreglo.
 *
 * @param string $xml Cuerpo del feed.
 * @return array
 */
function dcj_parsear_feed( $xml ) {
	if ( ! function_exists( 'simplexml_load_string' ) ) {
		return array();
	}
	$prev = libxml_use_internal_errors( true );
	$doc  = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
	libxml_use_internal_errors( $prev );
	if ( ! $doc ) {
		return array();
	}

	$ns     = $doc->getNamespaces( true );
	$videos = array();

	foreach ( $doc->entry as $entry ) {
		$yt    = $entry->children( $ns['yt'] ?? 'http://www.youtube.com/xml/schemas/2015' );
		$media = $entry->children( $ns['media'] ?? 'http://search.yahoo.com/mrss/' );
		$id    = (string) $yt->videoId;
		if ( ! preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $id ) ) {
			continue;
		}

		$grupo  = $media->group;
		$desc   = $grupo ? (string) $grupo->description : '';
		$vistas = 0;
		if ( $grupo && $grupo->community && $grupo->community->statistics ) {
			$vistas = (int) $grupo->community->statistics->attributes()->views;
		}
		$titulo = (string) $entry->title;

		$videos[] = array(
			'id'          => $id,
			'titulo'      => dcj_limpiar_titulo( $titulo ),
			'hook'        => dcj_hook( $titulo ),
			'fecha'       => strtotime( (string) $entry->published ),
			'descripcion' => $desc,
			'vistas'      => $vistas,
			'url'         => 'https://youtube.com/shorts/' . $id,
		);
	}

	return $videos;
}

/**
 * Quita hashtags y emojis del título.
 *
 * @param string $titulo Título original.
 * @return string
 */
function dcj_limpiar_titulo( $titulo ) {
	$t = preg_replace( '/#[\p{L}\p{N}_]+/u', '', $titulo );
	$t = preg_replace( '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '', $t );
	return trim( preg_replace( '/\s+/u', ' ', $t ), " \t\n\r\0\x0B." ) . '';
}

/**
 * Hook corto en mayúsculas para la tarjeta: la primera frase, máximo 6 palabras.
 *
 * @param string $titulo Título original.
 * @return string
 */
function dcj_hook( $titulo ) {
	$t     = dcj_limpiar_titulo( $titulo );
	$parte = preg_split( '/[:.…]|\s—\s|\s-\s/u', $t );
	$frase = trim( $parte[0] ?? $t );
	$pal   = preg_split( '/\s+/u', $frase );
	if ( count( $pal ) > 6 ) {
		$frase = implode( ' ', array_slice( $pal, 0, 6 ) ) . '…';
	}
	return mb_strtoupper( $frase, 'UTF-8' );
}

/**
 * URL del artículo de la bitácora creado para un video, si existe.
 *
 * @param string $video_id ID del video.
 * @return string
 */
function dcj_post_de_video( $video_id ) {
	$q = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_dcj_video_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $video_id, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return $q ? get_permalink( $q[0] ) : '';
}

/**
 * Miniatura del video. oardefault es la vertical de los Shorts.
 *
 * @param string $id      ID del video.
 * @param string $calidad oardefault | hqdefault | maxresdefault.
 * @return string
 */
function dcj_miniatura( $id, $calidad = 'oardefault' ) {
	return 'https://i.ytimg.com/vi/' . rawurlencode( $id ) . '/' . $calidad . '.jpg';
}

/**
 * Fecha relativa corta: "hoy", "ayer", "29 oct".
 *
 * @param int $ts Timestamp.
 * @return string
 */
function dcj_fecha_corta( $ts ) {
	if ( ! $ts ) {
		return '';
	}
	$hoy = wp_date( 'Y-m-d' );
	$dia = wp_date( 'Y-m-d', $ts );
	if ( $dia === $hoy ) {
		return 'hoy';
	}
	if ( wp_date( 'Y-m-d', strtotime( '-1 day' ) ) === $dia ) {
		return 'ayer';
	}
	return wp_date( 'j M', $ts );
}
