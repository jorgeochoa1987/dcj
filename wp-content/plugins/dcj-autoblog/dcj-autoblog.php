<?php
/**
 * Plugin Name: DevConJorge — Bitácora automática
 * Description: Cada video nuevo del canal de YouTube se convierte en un artículo de la bitácora, firmado por el autor que elijas. Con API key de Claude, el artículo se redacta con la voz de @devconjorge.
 * Version: 1.0.0
 * Author: Ing. Jorge Ochoa
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * Text Domain: dcj-autoblog
 *
 * @package dcj-autoblog
 */

defined( 'ABSPATH' ) || exit;

const DCJAB_OPCION = 'dcj_autoblog';
const DCJAB_CRON   = 'dcj_autoblog_revisar';

/**
 * Opciones con valores por defecto.
 *
 * @return array
 */
function dcjab_opciones() {
	return wp_parse_args(
		get_option( DCJAB_OPCION, array() ),
		array(
			'canal_id'  => 'UCQMAx4udQPfGPuzoPzh1XAQ',
			'api_key'   => '',
			'modelo'    => 'claude-sonnet-5-5',
			'estado'    => 'draft',
			'autor'     => 0,
			'categoria' => 0,
			'desde'     => 0,
			'portada'   => 1,
		)
	);
}

/* ---------------------------------------------------------------
 * Activación: programa la revisión cada hora y fija "desde" a hoy,
 * para no convertir en artículos los videos viejos del feed.
 * ------------------------------------------------------------- */
register_activation_hook(
	__FILE__,
	function () {
		$o = dcjab_opciones();
		if ( ! $o['desde'] ) {
			$o['desde'] = time();
			update_option( DCJAB_OPCION, $o, false );
		}
		if ( ! wp_next_scheduled( DCJAB_CRON ) ) {
			wp_schedule_event( time() + 60, 'hourly', DCJAB_CRON );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( DCJAB_CRON );
	}
);

add_action( DCJAB_CRON, 'dcjab_revisar' );

/**
 * Revisa el feed y crea los artículos que falten.
 *
 * @param bool $forzar_todos Ignora la fecha "desde" (botón de importar).
 * @return array Resumen: creados, saltados, errores.
 */
function dcjab_revisar( $forzar_todos = false ) {
	$res = array( 'creados' => 0, 'saltados' => 0, 'errores' => array() );

	if ( get_transient( 'dcjab_lock' ) ) {
		$res['errores'][] = 'Ya hay una revisión en curso.';
		return $res;
	}
	set_transient( 'dcjab_lock', 1, 10 * MINUTE_IN_SECONDS );

	$o      = dcjab_opciones();
	$videos = dcjab_leer_feed( $o['canal_id'] );

	if ( is_wp_error( $videos ) ) {
		delete_transient( 'dcjab_lock' );
		$res['errores'][] = $videos->get_error_message();
		update_option( 'dcj_autoblog_ultimo', array( 'fecha' => time(), 'res' => $res ), false );
		return $res;
	}

	// Del más viejo al más nuevo, para que las fechas queden en orden.
	foreach ( array_reverse( $videos ) as $v ) {
		if ( ! $forzar_todos && $v['fecha'] && $v['fecha'] < (int) $o['desde'] ) {
			++$res['saltados'];
			continue;
		}
		if ( dcjab_existe( $v['id'] ) ) {
			++$res['saltados'];
			continue;
		}
		$id = dcjab_crear_post( $v, $o );
		if ( is_wp_error( $id ) ) {
			$res['errores'][] = $v['id'] . ': ' . $id->get_error_message();
		} else {
			++$res['creados'];
		}
	}

	delete_transient( 'dcjab_lock' );
	delete_transient( 'dcj_yt_feed' );
	delete_transient( 'dcj_yt_feed_v2' );
	delete_transient( 'dcj_yt_feed_v3' );
	update_option( 'dcj_autoblog_ultimo', array( 'fecha' => time(), 'res' => $res ), false );
	return $res;
}

/**
 * Lee el RSS público del canal.
 *
 * @param string $canal_id ID del canal.
 * @return array|WP_Error
 */
function dcjab_leer_feed( $canal_id ) {
	$resp = wp_remote_get(
		'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode( $canal_id ),
		array( 'timeout' => 10 )
	);
	if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
		if ( function_exists( 'dcj_videos_desde_shorts' ) ) {
			$shorts = dcj_videos_desde_shorts();
			if ( $shorts ) {
				return $shorts;
			}
		}
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		return new WP_Error( 'dcjab_feed', 'YouTube respondió ' . wp_remote_retrieve_response_code( $resp ) . ' al pedir el feed.' );
	}

	$prev = libxml_use_internal_errors( true );
	$doc  = simplexml_load_string( wp_remote_retrieve_body( $resp ), 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
	libxml_use_internal_errors( $prev );
	if ( ! $doc ) {
		return new WP_Error( 'dcjab_xml', 'No se pudo leer el feed del canal.' );
	}

	$ns  = $doc->getNamespaces( true );
	$out = array();
	foreach ( $doc->entry as $entry ) {
		$yt    = $entry->children( $ns['yt'] ?? 'http://www.youtube.com/xml/schemas/2015' );
		$media = $entry->children( $ns['media'] ?? 'http://search.yahoo.com/mrss/' );
		$id    = (string) $yt->videoId;
		if ( ! preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $id ) ) {
			continue;
		}
		$out[] = array(
			'id'          => $id,
			'titulo'      => (string) $entry->title,
			'fecha'       => strtotime( (string) $entry->published ),
			'descripcion' => $media->group ? (string) $media->group->description : '',
		);
	}
	return $out;
}

/**
 * ¿Ya hay un artículo para este video?
 *
 * @param string $video_id ID.
 * @return bool
 */
function dcjab_existe( $video_id ) {
	$q = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_dcj_video_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $video_id, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return (bool) $q;
}

/**
 * Quita hashtags y emojis.
 *
 * @param string $t Texto.
 * @return string
 */
function dcjab_limpiar( $t ) {
	$t = preg_replace( '/#[\p{L}\p{N}_]+/u', '', $t );
	$t = preg_replace( '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '', $t );
	return trim( preg_replace( '/[ \t]+/u', ' ', $t ) );
}

/**
 * Crea el artículo para un video.
 *
 * @param array $v Video.
 * @param array $o Opciones.
 * @return int|WP_Error
 */
function dcjab_crear_post( $v, $o ) {
	$titulo  = rtrim( dcjab_limpiar( $v['titulo'] ), ' .' );
	$desc    = dcjab_limpiar( $v['descripcion'] );
	$url     = 'https://youtube.com/shorts/' . $v['id'];
	$extracto = '';
	$cat_txt  = '';

	if ( $o['api_key'] ) {
		$ia = dcjab_redactar( $titulo, $desc, $url, $o );
		if ( is_wp_error( $ia ) ) {
			// Sin artículo con IA, se deja el borrador básico para no perder el video.
			$html = dcjab_html_basico( $desc, $url ) . "\n<!-- dcj-autoblog: " . esc_html( $ia->get_error_message() ) . ' -->';
		} else {
			$titulo   = $ia['titulo'] ? $ia['titulo'] : $titulo;
			$html     = $ia['html'];
			$extracto = $ia['extracto'];
			$cat_txt  = $ia['categoria'];
		}
	} else {
		$html = dcjab_html_basico( $desc, $url );
	}

	$cats = array();
	if ( $cat_txt ) {
		$term = term_exists( $cat_txt, 'category' );
		if ( ! $term ) {
			$term = wp_insert_term( $cat_txt, 'category' );
		}
		if ( ! is_wp_error( $term ) ) {
			$cats[] = (int) $term['term_id'];
		}
	} elseif ( $o['categoria'] ) {
		$cats[] = (int) $o['categoria'];
	}

	$post_id = wp_insert_post(
		array(
			'post_title'    => wp_strip_all_tags( $titulo ),
			'post_content'  => wp_kses_post( $html ),
			'post_excerpt'  => sanitize_text_field( $extracto ),
			'post_status'   => in_array( $o['estado'], array( 'draft', 'publish', 'pending' ), true ) ? $o['estado'] : 'draft',
			'post_author'   => $o['autor'] ? (int) $o['autor'] : dcjab_autor_por_defecto(),
			'post_date'     => wp_date( 'Y-m-d H:i:s', $v['fecha'] ? (int) $v['fecha'] : time() ),
			'post_category' => $cats,
			'meta_input'    => array(
				'_dcj_video_id'  => $v['id'],
				'_dcj_video_url' => esc_url_raw( $url ),
			),
		),
		true
	);

	if ( ! is_wp_error( $post_id ) && $o['portada'] ) {
		dcjab_portada( $post_id, $v['id'], $titulo );
	}

	return $post_id;
}

/**
 * Primer administrador, como autor por defecto.
 *
 * @return int
 */
function dcjab_autor_por_defecto() {
	$u = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	return $u ? (int) $u[0] : 1;
}

/**
 * Artículo sin IA: la descripción del video en párrafos.
 *
 * @param string $desc Descripción.
 * @param string $url  URL del video.
 * @return string
 */
function dcjab_html_basico( $desc, $url ) {
	$html = $desc ? wpautop( make_clickable( esc_html( $desc ) ) ) : '<p>El resumen escrito de este video llega pronto.</p>';
	return $html . sprintf( '<p><a href="%s">Mira el video en YouTube</a>.</p>', esc_url( $url ) );
}

/**
 * Pide a Claude el artículo con la voz de @devconjorge.
 *
 * @param string $titulo Título del video.
 * @param string $desc   Descripción del video.
 * @param string $url    URL del video.
 * @param array  $o      Opciones.
 * @return array|WP_Error {titulo, extracto, categoria, html}
 */
function dcjab_redactar( $titulo, $desc, $url, $o ) {
	$sistema = <<<TXT
Eres el editor de la bitácora de @devconjorge, el canal del Ing. Jorge Ochoa: un desarrollador que explica inteligencia artificial en español para Latinoamérica.

Escribes el artículo que acompaña a un Short de YouTube, a partir de su título y su descripción. Reglas de voz:
- Tú, no usted. Español neutro con sabor colombiano, sin "bro" ni jerga de influencer.
- Frases cortas, verbos concretos. Nada de hype ni "revolucionario".
- La autoridad viene del dato verificable. Usa solo lo que dicen el título y la descripción; no inventes cifras, fechas, nombres ni citas. Si algo no está claro, dilo o déjalo fuera.
- Si la descripción trae fuentes o enlaces, inclúyelos en una sección "Fuentes".
- Sin emojis. "IA" está bien en el cuerpo; en el título usa "inteligencia artificial" o "IA" según suene natural.
- Termina con una pregunta corta a la comunidad.

Formato del cuerpo: HTML simple con <p>, <h2>, <ul>, <li>, <strong>, <a>, <pre><code> para comandos. Sin <h1>, sin estilos, sin scripts. Entre 250 y 450 palabras.

Responde SOLO con un objeto JSON válido con estas claves:
{"titulo": "título del artículo, máximo 90 caracteres", "extracto": "una frase de máximo 160 caracteres", "categoria": "una de: Noticias, IA local, Herramientas, Agentes, Automatización, Seguridad", "html": "cuerpo del artículo"}
TXT;

	$usuario = "Título del video: {$titulo}\n\nDescripción del video:\n" . ( $desc ? $desc : '(sin descripción)' ) . "\n\nURL: {$url}";

	$resp = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		array(
			'timeout' => 90,
			'headers' => array(
				'x-api-key'         => $o['api_key'],
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'model'      => $o['modelo'],
					'max_tokens' => 2000,
					'system'     => $sistema,
					'messages'   => array(
						array( 'role' => 'user', 'content' => $usuario ),
					),
				)
			),
		)
	);

	if ( is_wp_error( $resp ) ) {
		return $resp;
	}
	$code = wp_remote_retrieve_response_code( $resp );
	$body = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( 200 !== $code ) {
		$msg = $body['error']['message'] ?? 'Error ' . $code;
		return new WP_Error( 'dcjab_claude', 'Claude API: ' . $msg );
	}

	$texto = '';
	foreach ( (array) ( $body['content'] ?? array() ) as $bloque ) {
		if ( 'text' === ( $bloque['type'] ?? '' ) ) {
			$texto .= $bloque['text'];
		}
	}
	// Por si viene envuelto en ```json.
	if ( preg_match( '/\{.*\}/s', $texto, $m ) ) {
		$texto = $m[0];
	}
	$data = json_decode( $texto, true );
	if ( ! is_array( $data ) || empty( $data['html'] ) ) {
		return new WP_Error( 'dcjab_json', 'La respuesta de Claude no trajo el artículo en el formato esperado.' );
	}

	return array(
		'titulo'    => isset( $data['titulo'] ) ? sanitize_text_field( $data['titulo'] ) : '',
		'extracto'  => isset( $data['extracto'] ) ? sanitize_text_field( $data['extracto'] ) : '',
		'categoria' => isset( $data['categoria'] ) ? sanitize_text_field( $data['categoria'] ) : '',
		'html'      => (string) $data['html'],
	);
}

/**
 * Descarga la miniatura del video como imagen destacada (sirve para compartir en redes).
 *
 * @param int    $post_id  Post.
 * @param string $video_id Video.
 * @param string $titulo   Texto alternativo.
 */
function dcjab_portada( $post_id, $video_id, $titulo ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	foreach ( array( 'maxresdefault', 'hqdefault' ) as $calidad ) {
		$img = media_sideload_image( 'https://i.ytimg.com/vi/' . rawurlencode( $video_id ) . '/' . $calidad . '.jpg', $post_id, $titulo, 'id' );
		if ( ! is_wp_error( $img ) ) {
			set_post_thumbnail( $post_id, $img );
			return;
		}
	}
}

/* ---------------------------------------------------------------
 * Ajustes en el admin: Ajustes → Bitácora automática
 * ------------------------------------------------------------- */
add_action(
	'admin_menu',
	function () {
		add_options_page( 'Bitácora automática', 'Bitácora automática', 'manage_options', 'dcj-autoblog', 'dcjab_pagina' );
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'dcj_autoblog',
			DCJAB_OPCION,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'dcjab_sanear',
			)
		);
	}
);

/**
 * Sanea las opciones.
 *
 * @param array $in Entrada.
 * @return array
 */
function dcjab_sanear( $in ) {
	$o = dcjab_opciones();
	$o['canal_id']  = isset( $in['canal_id'] ) ? sanitize_text_field( $in['canal_id'] ) : $o['canal_id'];
	$o['modelo']    = isset( $in['modelo'] ) ? sanitize_text_field( $in['modelo'] ) : $o['modelo'];
	$o['estado']    = isset( $in['estado'] ) && in_array( $in['estado'], array( 'draft', 'pending', 'publish' ), true ) ? $in['estado'] : 'draft';
	$o['autor']     = isset( $in['autor'] ) ? absint( $in['autor'] ) : 0;
	$o['categoria'] = isset( $in['categoria'] ) ? absint( $in['categoria'] ) : 0;
	$o['portada']   = empty( $in['portada'] ) ? 0 : 1;
	// La API key solo se cambia si escriben una nueva (el campo se muestra vacío).
	if ( ! empty( $in['api_key'] ) ) {
		$o['api_key'] = sanitize_text_field( $in['api_key'] );
	}
	if ( ! empty( $in['borrar_key'] ) ) {
		$o['api_key'] = '';
	}
	return $o;
}

/**
 * Botón "Revisar ahora".
 */
add_action(
	'admin_post_dcjab_ahora',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'dcjab_ahora' );
		$todos = ! empty( $_POST['todos'] );
		$res   = dcjab_revisar( $todos );
		set_transient( 'dcjab_aviso', $res, 60 );
		wp_safe_redirect( admin_url( 'options-general.php?page=dcj-autoblog' ) );
		exit;
	}
);

/**
 * Página de ajustes.
 */
function dcjab_pagina() {
	$o      = dcjab_opciones();
	$ultimo = get_option( 'dcj_autoblog_ultimo' );
	$aviso  = get_transient( 'dcjab_aviso' );
	delete_transient( 'dcjab_aviso' );
	?>
	<div class="wrap">
		<h1>Bitácora automática</h1>
		<p>Cada hora se revisa el canal. Por cada video nuevo se crea un artículo con el video arriba y la firma del autor que elijas.</p>

		<?php if ( $aviso ) : ?>
			<div class="notice notice-<?php echo $aviso['errores'] ? 'warning' : 'success'; ?>"><p>
				<?php echo esc_html( sprintf( 'Artículos creados: %d. Videos que ya estaban o eran anteriores: %d.', $aviso['creados'], $aviso['saltados'] ) ); ?>
				<?php foreach ( $aviso['errores'] as $e ) : ?>
					<br><?php echo esc_html( $e ); ?>
				<?php endforeach; ?>
			</p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'dcj_autoblog' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="canal_id">ID del canal</label></th>
					<td><input class="regular-text" id="canal_id" name="<?php echo esc_attr( DCJAB_OPCION ); ?>[canal_id]" value="<?php echo esc_attr( $o['canal_id'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="estado">Al crear el artículo</label></th>
					<td>
						<select id="estado" name="<?php echo esc_attr( DCJAB_OPCION ); ?>[estado]">
							<option value="draft" <?php selected( $o['estado'], 'draft' ); ?>>Dejarlo como borrador (recomendado)</option>
							<option value="pending" <?php selected( $o['estado'], 'pending' ); ?>>Pendiente de revisión</option>
							<option value="publish" <?php selected( $o['estado'], 'publish' ); ?>>Publicarlo de una vez</option>
						</select>
						<p class="description">Con borrador revisas el texto y das publicar. Un dato mal sacado del video le resta credibilidad al canal.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="autor">Autor</label></th>
					<td>
						<?php
						wp_dropdown_users(
							array(
								'name'             => DCJAB_OPCION . '[autor]',
								'id'               => 'autor',
								'selected'         => $o['autor'],
								'show_option_none' => 'Primer administrador',
								'option_none_value' => 0,
								'capability'       => 'edit_posts',
							)
						);
						?>
						<p class="description">Pon "Ing. Jorge Ochoa" como nombre público de ese usuario (Usuarios → Perfil → Mostrar públicamente como).</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="categoria">Categoría si no hay IA</label></th>
					<td>
						<?php
						wp_dropdown_categories(
							array(
								'name'            => DCJAB_OPCION . '[categoria]',
								'id'              => 'categoria',
								'selected'        => $o['categoria'],
								'show_option_none' => 'Ninguna',
								'option_none_value' => 0,
								'hide_empty'      => false,
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="api_key">API key de Claude</label></th>
					<td>
						<input class="regular-text" type="password" id="api_key" autocomplete="off" name="<?php echo esc_attr( DCJAB_OPCION ); ?>[api_key]" placeholder="<?php echo $o['api_key'] ? 'Guardada · escribe otra para cambiarla' : 'sk-ant-…'; ?>">
						<?php if ( $o['api_key'] ) : ?>
							<label><input type="checkbox" name="<?php echo esc_attr( DCJAB_OPCION ); ?>[borrar_key]" value="1"> Borrar la key guardada</label>
						<?php endif; ?>
						<p class="description">Opcional. Sin key, el artículo se arma con la descripción del video. Con key, Claude lo redacta con tu voz a partir del título y la descripción. Se crea en console.anthropic.com.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="modelo">Modelo</label></th>
					<td><input class="regular-text" id="modelo" name="<?php echo esc_attr( DCJAB_OPCION ); ?>[modelo]" value="<?php echo esc_attr( $o['modelo'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row">Imagen destacada</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( DCJAB_OPCION ); ?>[portada]" value="1" <?php checked( $o['portada'] ); ?>> Usar la miniatura del video (se ve al compartir el artículo)</label></td>
				</tr>
			</table>
			<?php submit_button( 'Guardar' ); ?>
		</form>

		<hr>
		<h2>Revisar ahora</h2>
		<p>
			<?php
			echo esc_html(
				sprintf(
					'Solo se convierten los videos publicados desde el %s. Próxima revisión automática: %s.',
					wp_date( 'j M Y, g:i a', (int) $o['desde'] ),
					wp_next_scheduled( DCJAB_CRON ) ? wp_date( 'g:i a', wp_next_scheduled( DCJAB_CRON ) ) : 'sin programar'
				)
			);
			?>
			<?php if ( $ultimo ) : ?>
				<br><?php echo esc_html( 'Última revisión: ' . wp_date( 'j M, g:i a', $ultimo['fecha'] ) . ' · creados ' . $ultimo['res']['creados'] ); ?>
			<?php endif; ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="dcjab_ahora">
			<?php wp_nonce_field( 'dcjab_ahora' ); ?>
			<label><input type="checkbox" name="todos" value="1"> Incluir también los videos anteriores que hoy están en el feed (hasta 15)</label>
			<?php submit_button( 'Revisar el canal ahora', 'secondary' ); ?>
		</form>
	</div>
	<?php
}
