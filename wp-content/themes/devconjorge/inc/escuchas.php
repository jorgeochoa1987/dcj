<?php
/**
 * Cifras de la barra En el aire: plays, minutos, origen y dispositivo.
 * No guarda IP ni el identificador crudo del navegador.
 *
 * @package devconjorge
 */

defined( 'ABSPATH' ) || exit;

/**
 * Crea las tablas una sola vez.
 */
function dcj_escucha_tablas() {
	if ( '1' === get_option( 'dcj_escucha_db' ) ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$escucha = $wpdb->prefix . 'dcj_escucha';
	$oyente  = $wpdb->prefix . 'dcj_oyente';
	dbDelta(
		"CREATE TABLE {$escucha} (
			bucket char(40) NOT NULL,
			dia date NOT NULL,
			capitulo varchar(180) NOT NULL,
			origen varchar(120) NOT NULL,
			pagina varchar(180) NOT NULL,
			dispositivo varchar(16) NOT NULL,
			pais char(2) NOT NULL DEFAULT '',
			reproducciones int unsigned NOT NULL DEFAULT 0,
			segundos int unsigned NOT NULL DEFAULT 0,
			completadas int unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (bucket),
			KEY dia (dia)
		) {$charset};"
	);
	dbDelta(
		"CREATE TABLE {$oyente} (
			clave char(40) NOT NULL,
			dia date NOT NULL,
			capitulo varchar(180) NOT NULL,
			oyente char(32) NOT NULL,
			PRIMARY KEY  (clave),
			KEY oyente (oyente)
		) {$charset};"
	);
	$hay_escucha = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $escucha ) );
	$hay_oyente  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $oyente ) );
	if ( $hay_escucha === $escucha && $hay_oyente === $oyente ) {
		update_option( 'dcj_escucha_db', '1', false );
	}
}
add_action( 'init', 'dcj_escucha_tablas', 5 );

/**
 * El navegador avisa play, progreso y final.
 */
function dcj_registrar_ruta_escucha() {
	register_rest_route(
		'dcj/v1',
		'/escucha',
		array(
			'methods'             => 'POST',
			'callback'            => 'dcj_rest_escucha',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'dcj_registrar_ruta_escucha' );

/**
 * @param WP_REST_Request $req Aviso del reproductor.
 * @return WP_REST_Response
 */
function dcj_rest_escucha( WP_REST_Request $req ) {
	if ( ! dcj_escucha_mismo_sitio() ) {
		return new WP_REST_Response( array( 'ok' => false ), 403 );
	}
	if ( ! dcj_escucha_cupo() ) {
		return new WP_REST_Response( array( 'ok' => false ), 429 );
	}

	$tipo = (string) $req->get_param( 'tipo' );
	if ( ! in_array( $tipo, array( 'play', 'pulso', 'fin' ), true ) ) {
		return new WP_REST_Response( array( 'ok' => false ), 400 );
	}

	$capitulo = dcj_escucha_texto( $req->get_param( 'capitulo' ), 180 );
	$origen   = dcj_escucha_texto( $req->get_param( 'origen' ), 120 );
	$pagina   = dcj_escucha_texto( $req->get_param( 'pagina' ), 180 );
	if ( '' === $capitulo || '' === $origen || '' === $pagina || '/' !== substr( $pagina, 0, 1 ) ) {
		return new WP_REST_Response( array( 'ok' => false ), 400 );
	}
	$dispositivo = (string) $req->get_param( 'dispositivo' );
	if ( 'movil' !== $dispositivo && 'escritorio' !== $dispositivo ) {
		$dispositivo = 'escritorio';
	}
	$oyente = strtolower( (string) $req->get_param( 'oyente' ) );
	if ( ! preg_match( '/^[a-z0-9]{8,40}$/', $oyente ) ) {
		return new WP_REST_Response( array( 'ok' => false ), 400 );
	}

	$plays = 'play' === $tipo ? 1 : 0;
	$fins  = 'fin' === $tipo ? 1 : 0;
	$segs  = 0;
	if ( 'pulso' === $tipo ) {
		$segs = min( 60, max( 0, (int) $req->get_param( 'segundos' ) ) );
		if ( $segs < 1 ) {
			return new WP_REST_Response( array( 'ok' => true ), 204 );
		}
	}

	$pais = dcj_escucha_pais();
	$dia  = gmdate( 'Y-m-d' );
	dcj_escucha_sumar( $dia, $capitulo, $origen, $pagina, $dispositivo, $pais, $plays, $segs, $fins );
	if ( $plays ) {
		dcj_escucha_oyente( $dia, $capitulo, $oyente );
	}
	return new WP_REST_Response( null, 204 );
}

/**
 * Solo acepta avisos salidos de este sitio.
 */
function dcj_escucha_mismo_sitio() {
	$sitio = wp_parse_url( home_url(), PHP_URL_HOST );
	$cabeceras = array(
		isset( $_SERVER['HTTP_ORIGIN'] ) ? wp_unslash( $_SERVER['HTTP_ORIGIN'] ) : '',
		isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '',
	);
	foreach ( $cabeceras as $cabecera ) {
		$host = $cabecera ? wp_parse_url( $cabecera, PHP_URL_HOST ) : '';
		if ( $host && $sitio && strtolower( $host ) === strtolower( $sitio ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Frena un bucle de avisos. La IP solo vive una hora en un transitorio y no se lista.
 */
function dcj_escucha_cupo() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$partes = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
		$ip     = trim( $partes[0] );
	}
	$clave = 'dcj_esc_' . md5( $ip . wp_salt( 'auth' ) );
	$n     = (int) get_transient( $clave );
	if ( $n > 400 ) {
		return false;
	}
	set_transient( $clave, $n + 1, HOUR_IN_SECONDS );
	return true;
}

/**
 * @param mixed $valor Texto del reproductor.
 * @param int   $max   Largo.
 */
function dcj_escucha_texto( $valor, $max ) {
	$texto = sanitize_text_field( (string) $valor );
	if ( function_exists( 'mb_substr' ) ) {
		return mb_substr( $texto, 0, $max );
	}
	return substr( $texto, 0, $max );
}

/**
 * País solo si el proxy ya lo calculó. Si no, queda vacío.
 */
function dcj_escucha_pais() {
	$crudo = isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) : '';
	$pais  = strtoupper( substr( $crudo, 0, 2 ) );
	if ( preg_match( '/^[A-Z]{2}$/', $pais ) && 'XX' !== $pais && 'T1' !== $pais ) {
		return $pais;
	}
	return '';
}

/**
 * Suma al cubo del día.
 */
function dcj_escucha_sumar( $dia, $capitulo, $origen, $pagina, $dispositivo, $pais, $plays, $segs, $fins ) {
	global $wpdb;
	$tabla  = $wpdb->prefix . 'dcj_escucha';
	$bucket = sha1( $dia . '|' . $capitulo . '|' . $origen . '|' . $pagina . '|' . $dispositivo . '|' . $pais );
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$tabla} (bucket, dia, capitulo, origen, pagina, dispositivo, pais, reproducciones, segundos, completadas)
			VALUES (%s, %s, %s, %s, %s, %s, %s, %d, %d, %d)
			ON DUPLICATE KEY UPDATE
				reproducciones = reproducciones + VALUES(reproducciones),
				segundos = segundos + VALUES(segundos),
				completadas = completadas + VALUES(completadas)",
			$bucket,
			$dia,
			$capitulo,
			$origen,
			$pagina,
			$dispositivo,
			$pais,
			$plays,
			$segs,
			$fins
		)
	);
}

/**
 * Una persona por día y capítulo. Se guarda un hash, no el id del navegador.
 */
function dcj_escucha_oyente( $dia, $capitulo, $oyente ) {
	global $wpdb;
	$tabla = $wpdb->prefix . 'dcj_oyente';
	$hash  = substr( hash( 'sha256', $oyente . '|' . wp_salt( 'auth' ) ), 0, 32 );
	$clave = sha1( $dia . '|' . $capitulo . '|' . $hash );
	$wpdb->query(
		$wpdb->prepare(
			"INSERT IGNORE INTO {$tabla} (clave, dia, capitulo, oyente) VALUES (%s, %s, %s, %s)",
			$clave,
			$dia,
			$capitulo,
			$hash
		)
	);
}

/**
 * Pantalla en Capítulos → Escuchas.
 */
function dcj_escucha_menu() {
	add_submenu_page(
		'edit.php?post_type=dcj_cap',
		'Escuchas',
		'Escuchas',
		'edit_posts',
		'dcj-escuchas',
		'dcj_escucha_pantalla'
	);
}
add_action( 'admin_menu', 'dcj_escucha_menu' );

/**
 * Resumen para decidir si el audio ya merece Spotify.
 */
function dcj_escucha_pantalla() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	global $wpdb;
	$escucha = $wpdb->prefix . 'dcj_escucha';
	$oyente  = $wpdb->prefix . 'dcj_oyente';
	$totales = $wpdb->get_row( "SELECT COALESCE(SUM(reproducciones),0) AS plays, COALESCE(SUM(segundos),0) AS segundos, COALESCE(SUM(completadas),0) AS fins FROM {$escucha}", ARRAY_A );
	$personas = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT oyente) FROM {$oyente}" );
	$plays    = $totales ? (int) $totales['plays'] : 0;
	$segundos = $totales ? (int) $totales['segundos'] : 0;
	$fins     = $totales ? (int) $totales['fins'] : 0;
	$porc     = $plays > 0 ? (int) round( ( $fins / $plays ) * 100 ) : 0;

	$caps = $wpdb->get_results( "SELECT capitulo, SUM(reproducciones) AS plays, SUM(segundos) AS segundos, SUM(completadas) AS fins FROM {$escucha} GROUP BY capitulo ORDER BY plays DESC LIMIT 20", ARRAY_A );
	$origenes = $wpdb->get_results( "SELECT origen, SUM(reproducciones) AS plays FROM {$escucha} GROUP BY origen ORDER BY plays DESC LIMIT 12", ARRAY_A );
	$paginas = $wpdb->get_results( "SELECT pagina, SUM(reproducciones) AS plays FROM {$escucha} GROUP BY pagina ORDER BY plays DESC LIMIT 12", ARRAY_A );
	$dispositivos = $wpdb->get_results( "SELECT dispositivo, SUM(reproducciones) AS plays FROM {$escucha} GROUP BY dispositivo ORDER BY plays DESC", ARRAY_A );
	$paises = $wpdb->get_results( "SELECT pais, SUM(reproducciones) AS plays FROM {$escucha} WHERE pais <> '' GROUP BY pais ORDER BY plays DESC LIMIT 12", ARRAY_A );
	$dias = $wpdb->get_results( "SELECT dia, SUM(reproducciones) AS plays, SUM(segundos) AS segundos FROM {$escucha} GROUP BY dia ORDER BY dia DESC LIMIT 14", ARRAY_A );
	?>
	<div class="wrap">
		<h1>Escuchas</h1>
		<p>Cada play, los minutos y si llegaron al final. El origen es el sitio del que vinieron. La página es dónde le dieron play. No se guarda la IP.</p>
		<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0 24px">
			<?php
			dcj_escucha_cifra( 'Reproducciones', number_format_i18n( $plays ) );
			dcj_escucha_cifra( 'Oyentes', number_format_i18n( $personas ) );
			dcj_escucha_cifra( 'Minutos', number_format_i18n( (int) round( $segundos / 60 ) ) );
			dcj_escucha_cifra( 'Lo terminaron', $porc . '%' );
			?>
		</div>
		<?php if ( ! $plays ) : ?>
			<p>Todavía no hay escuchas. Cuando alguien le dé play en la barra, aparecen aquí.</p>
		<?php else : ?>
			<h2>Por capítulo</h2>
			<?php dcj_escucha_tabla( array( 'Capítulo', 'Plays', 'Minutos', 'Finales' ), $caps, array( 'capitulo', 'plays', 'segundos', 'fins' ), true ); ?>
			<h2>De dónde llegaron</h2>
			<?php dcj_escucha_tabla( array( 'Origen', 'Plays' ), $origenes, array( 'origen', 'plays' ), false ); ?>
			<h2>Dónde le dieron play</h2>
			<?php dcj_escucha_tabla( array( 'Página', 'Plays' ), $paginas, array( 'pagina', 'plays' ), false ); ?>
			<h2>Cómo lo oyen</h2>
			<?php dcj_escucha_tabla( array( 'Dispositivo', 'Plays' ), $dispositivos, array( 'dispositivo', 'plays' ), false ); ?>
			<?php if ( $paises ) : ?>
				<h2>País</h2>
				<p>Solo aparece si el servidor ya trae el país. Si esta tabla no está, igual tienes el origen y el dispositivo.</p>
				<?php dcj_escucha_tabla( array( 'País', 'Plays' ), $paises, array( 'pais', 'plays' ), false ); ?>
			<?php endif; ?>
			<h2>Últimos días</h2>
			<?php dcj_escucha_tabla( array( 'Día', 'Plays', 'Minutos' ), $dias, array( 'dia', 'plays', 'segundos' ), true ); ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * @param string $etiqueta Nombre.
 * @param string $valor    Cifra ya formateada.
 */
function dcj_escucha_cifra( $etiqueta, $valor ) {
	echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;min-width:140px">';
	echo '<div style="font-size:12px;color:#646970;text-transform:uppercase;letter-spacing:.04em">' . esc_html( $etiqueta ) . '</div>';
	echo '<div style="font-size:28px;font-weight:600;line-height:1.2">' . esc_html( $valor ) . '</div>';
	echo '</div>';
}

/**
 * @param string[] $cabezas Columnas.
 * @param array[]  $filas   Resultados.
 * @param string[] $claves  Campos.
 * @param bool     $minutos La columna segundos se muestra en minutos.
 */
function dcj_escucha_tabla( $cabezas, $filas, $claves, $minutos ) {
	echo '<table class="widefat striped" style="max-width:820px;margin-bottom:24px"><thead><tr>';
	foreach ( $cabezas as $cabeza ) {
		echo '<th>' . esc_html( $cabeza ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	if ( ! $filas ) {
		echo '<tr><td colspan="' . count( $cabezas ) . '">Sin datos</td></tr>';
	}
	foreach ( $filas as $fila ) {
		echo '<tr>';
		foreach ( $claves as $clave ) {
			$valor = isset( $fila[ $clave ] ) ? (string) $fila[ $clave ] : '';
			if ( $minutos && 'segundos' === $clave ) {
				$valor = (string) (int) round( ( (int) $valor ) / 60 );
			}
			if ( 'plays' === $clave || 'fins' === $clave || ( $minutos && 'segundos' === $clave ) ) {
				$valor = number_format_i18n( (int) $valor );
			}
			echo '<td>' . esc_html( $valor ) . '</td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table>';
}
