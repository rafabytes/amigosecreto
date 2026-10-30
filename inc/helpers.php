<?php
/**
 * Funções utilitárias usadas pelos templates e pela API.
 */

defined( 'ABSPATH' ) || exit;

/**
 * URL de uma rota do tema. Ex.: as_url( 'grupos', 12 ) => /grupos/12/
 */
function as_url( $route = '', ...$parts ) {
	$path = trim( implode( '/', array_merge( [ $route ], array_map( 'strval', $parts ) ) ), '/' );
	return home_url( $path ? user_trailingslashit( $path ) : '/' );
}

/**
 * Renderiza um ponto de montagem de componente React ("ilha").
 * O JS em src/js/app.js monta/desmonta as ilhas a cada navegação do Swup.
 */
function as_island( $component, array $props = [], $placeholder = '' ) {
	printf(
		'<div data-island="%s" data-props="%s">%s</div>',
		esc_attr( $component ),
		esc_attr( wp_json_encode( $props ) ),
		$placeholder // phpcs:ignore WordPress.Security.EscapeOutput -- HTML do próprio tema.
	);
}

/**
 * Dados públicos de um usuário, para listagens.
 */
function as_user_payload( $user ) {
	$user = $user instanceof WP_User ? $user : get_userdata( (int) $user );
	if ( ! $user ) {
		return null;
	}
	return [
		'id'       => $user->ID,
		'name'     => $user->display_name,
		'initials' => as_initials( $user->display_name ),
		'avatar'   => as_get_avatar_url( $user->ID ) ?: null,
		'birthday' => as_format_birthday( as_get_birthdate( $user->ID ) ),
		'listUrl'  => as_url( 'lista', $user->ID ),
		'gifts'    => as_count_gifts( $user->ID ),
	];
}

/**
 * Foto do usuário ou círculo com as iniciais.
 *
 * @param array $user Payload de as_user_payload().
 */
function as_avatar_html( array $user, $class = 'size-10 text-sm' ) {
	if ( ! empty( $user['avatar'] ) ) {
		return sprintf(
			'<img src="%s" alt="" class="avatar object-cover %s" title="%s" loading="lazy">',
			esc_url( $user['avatar'] ),
			esc_attr( $class ),
			esc_attr( $user['name'] )
		);
	}
	return sprintf( '<span class="avatar %s" title="%s">%s</span>', esc_attr( $class ), esc_attr( $user['name'] ), esc_html( $user['initials'] ) );
}

function as_initials( $name ) {
	$words = preg_split( '/\s+/', trim( (string) $name ) );
	$first = mb_substr( $words[0] ?? '', 0, 1 );
	$last  = count( $words ) > 1 ? mb_substr( end( $words ), 0, 1 ) : '';
	return mb_strtoupper( $first . $last ) ?: '?';
}

/**
 * Formata Y-m-d para exibição (ex.: 20 de dezembro de 2026).
 */
function as_format_date( $ymd ) {
	if ( ! $ymd ) {
		return '';
	}
	$ts = strtotime( $ymd . ' 12:00:00' );
	return $ts ? date_i18n( 'j \d\e F \d\e Y', $ts ) : '';
}

function as_sanitize_date( $value ) {
	$value = sanitize_text_field( (string) $value );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
}

/**
 * Garante que a URL de um presente seja http(s).
 */
function as_sanitize_link( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	// Outro esquema (javascript:, data:, mailto:…) — descarta. "site.com:8080" passa (porta numérica).
	if ( preg_match( '#^([a-z][a-z0-9+.-]*):(?!\d)#i', $url, $m ) && ! in_array( strtolower( $m[1] ), [ 'http', 'https' ], true ) ) {
		return '';
	}
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		$url = 'https://' . $url;
	}
	return esc_url_raw( $url, [ 'http', 'https' ] );
}

/**
 * Host legível de uma URL (ex.: amazon.com.br).
 */
function as_link_host( $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	return $host ? preg_replace( '/^www\./', '', $host ) : '';
}

/**
 * URL absoluta da página atual.
 * Usa $wp->request (caminho relativo à home), e não REQUEST_URI, para não duplicar o
 * subdiretório quando o site está em rafabytes.com/amigosecreto.
 */
function as_current_url() {
	global $wp;
	$url   = home_url( $wp->request ? user_trailingslashit( $wp->request ) : '/' );
	$query = $_SERVER['QUERY_STRING'] ?? ''; // phpcs:ignore
	return $query ? $url . '?' . $query : $url;
}

/**
 * Redireciona para o login se o visitante não estiver autenticado.
 */
function as_require_login() {
	if ( is_user_logged_in() ) {
		return;
	}
	wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( as_current_url() ), as_url( 'entrar' ) ) );
	exit;
}
