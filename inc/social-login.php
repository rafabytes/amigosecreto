<?php
/**
 * Login com Google e Facebook (sem plugins).
 *
 * Google — botão oficial "Sign in with Google" (Google Identity Services), em src/js/lib/google.js.
 *   O Google entrega ao JavaScript um ID token (JWT) que é enviado para a API:
 *     GET  /wp-json/amigosecreto/v1/auth/google/nonce   nonce anti-repetição (também guardado em cookie)
 *     POST /wp-json/amigosecreto/v1/auth/google         { credential, redirectTo?, link? }
 *   Não usamos o redirecionamento OAuth do Google porque o retorno sempre traz
 *   iss=https://accounts.google.com, que firewalls como o ModSecurity bloqueiam (erro 406).
 *
 * Facebook — OAuth 2.0 com redirecionamento:
 *     /auth/facebook/            inicia o login (ou conecta a conta, se já logado)
 *     /auth/facebook/callback/   retorno do Facebook
 *
 * Credenciais em Configurações → Login social (inc/social-settings.php) ou constantes no wp-config.php:
 * AS_GOOGLE_CLIENT_ID, AS_FACEBOOK_APP_ID, AS_FACEBOOK_APP_SECRET.
 *
 * Regras:
 *  - Conta já vinculada ao provedor → entra.
 *  - E-mail já cadastrado (ex.: participante convidado) → vincula e entra.
 *  - Conta nova → criada como organizador, se o cadastro estiver liberado.
 *  - Foto e data de nascimento do provedor são salvas só se o usuário ainda não tiver.
 */

defined( 'ABSPATH' ) || exit;

const AS_OAUTH_COOKIE        = 'as_oauth_state';
const AS_GOOGLE_NONCE_COOKIE = 'as_google_nonce';

function as_social_providers() {
	return [
		'google'   => __( 'Google', 'amigosecreto' ),
		'facebook' => __( 'Facebook', 'amigosecreto' ),
	];
}

function as_social_setting( $key ) {
	$constant = 'AS_' . strtoupper( $key );
	if ( defined( $constant ) ) {
		return constant( $constant );
	}
	$options = get_option( 'as_social', [] );
	return $options[ $key ] ?? '';
}

function as_social_enabled( $provider ) {
	if ( 'google' === $provider ) {
		// O botão do Google só precisa do Client ID (o token é validado sem o secret).
		return '' !== (string) as_social_setting( 'google_client_id' );
	}
	if ( 'facebook' === $provider ) {
		return '' !== (string) as_social_setting( 'facebook_app_id' ) && '' !== (string) as_social_setting( 'facebook_app_secret' );
	}
	return false;
}

/**
 * Provedores configurados.
 *
 * @return array<string,string> slug => rótulo
 */
function as_social_enabled_providers() {
	return array_filter( as_social_providers(), 'as_social_enabled', ARRAY_FILTER_USE_KEY );
}

/**
 * Mensagens dos códigos de erro, exibidas em /entrar/, /perfil/ e nas respostas da API.
 */
function as_social_error_message( $code ) {
	$messages = [
		'disabled'     => __( 'Esse tipo de acesso não está disponível.', 'amigosecreto' ),
		'state'        => __( 'A sessão de login expirou. Recarregue a página e tente novamente.', 'amigosecreto' ),
		'denied'       => __( 'O acesso foi cancelado.', 'amigosecreto' ),
		'provider'     => __( 'Não conseguimos falar com o provedor de login. Tente novamente em instantes.', 'amigosecreto' ),
		'token'        => __( 'Não foi possível validar o login com o Google. Tente novamente.', 'amigosecreto' ),
		'no_email'     => __( 'Sua conta não compartilhou um e-mail verificado. Libere o acesso ao e-mail ou entre com e-mail e senha.', 'amigosecreto' ),
		'no_account'   => __( 'Não encontramos uma conta com esse e-mail. Peça ao organizador do grupo para convidar você.', 'amigosecreto' ),
		'linked_other' => __( 'Essa conta já está vinculada a outro usuário.', 'amigosecreto' ),
		'create'       => __( 'Não foi possível criar sua conta. Tente novamente.', 'amigosecreto' ),
	];
	return $messages[ $code ] ?? '';
}

function as_social_cookie( $name, $value, $ttl ) {
	setcookie( $name, $value, [
		'expires'  => $ttl > 0 ? time() + $ttl : time() - HOUR_IN_SECONDS,
		'path'     => COOKIEPATH ?: '/',
		'domain'   => COOKIE_DOMAIN ?: '',
		'secure'   => is_ssl(),
		'httponly' => true,
		'samesite' => 'Lax',
	] );
}

/* =========================================================================
 * Google (Google Identity Services)
 * ====================================================================== */

add_action( 'rest_api_init', function () {
	register_rest_route( 'amigosecreto/v1', '/auth/google/nonce', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => function () {
			$nonce = wp_generate_password( 32, false );
			as_social_cookie( AS_GOOGLE_NONCE_COOKIE, $nonce, 30 * MINUTE_IN_SECONDS );
			$response = rest_ensure_response( [ 'nonce' => $nonce ] );
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		},
		'permission_callback' => fn() => as_social_enabled( 'google' ),
	] );

	register_rest_route( 'amigosecreto/v1', '/auth/google', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'as_rest_google_auth',
		'permission_callback' => fn() => as_social_enabled( 'google' ),
		'args'                => [
			'credential' => [ 'type' => 'string', 'required' => true ],
			'redirectTo' => [ 'type' => 'string' ],
			'link'       => [ 'type' => 'boolean' ],
		],
	] );
} );

function as_social_rest_error( $code, $status = 400 ) {
	return new WP_Error( 'as_social_' . $code, as_social_error_message( $code ), [ 'status' => $status ] );
}

/**
 * Valida o ID token do Google e devolve o perfil normalizado.
 *
 * @return array|WP_Error
 */
function as_google_verify_credential( $credential, $nonce ) {
	$response = wp_remote_get(
		add_query_arg( 'id_token', rawurlencode( $credential ), 'https://oauth2.googleapis.com/tokeninfo' ),
		[ 'timeout' => 15 ]
	);
	if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 500 ) {
		return new WP_Error( 'provider' );
	}

	$claims = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $claims ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'token' );
	}

	$valid = hash_equals( (string) as_social_setting( 'google_client_id' ), (string) ( $claims['aud'] ?? '' ) )
		&& in_array( $claims['iss'] ?? '', [ 'accounts.google.com', 'https://accounts.google.com' ], true )
		&& (int) ( $claims['exp'] ?? 0 ) > time()
		&& ! empty( $claims['sub'] );
	if ( ! $valid ) {
		return new WP_Error( 'token' );
	}

	// O nonce do token precisa ser o mesmo entregue a este navegador (cookie).
	if ( '' === $nonce || ! hash_equals( $nonce, (string) ( $claims['nonce'] ?? '' ) ) ) {
		return new WP_Error( 'state' );
	}

	// O tokeninfo devolve email_verified como string "true".
	$verified = in_array( $claims['email_verified'] ?? null, [ true, 'true' ], true );
	if ( empty( $claims['email'] ) || ! $verified ) {
		return new WP_Error( 'no_email' );
	}

	return [
		'id'        => (string) $claims['sub'],
		'email'     => (string) $claims['email'],
		'name'      => (string) ( $claims['name'] ?? '' ),
		// Foto padrão do Google vem em 96px; pede 400px.
		'avatar'    => ! empty( $claims['picture'] ) ? preg_replace( '/=s\d+(-c)?$/', '=s400-c', $claims['picture'] ) : '',
		'birthdate' => '',
	];
}

/**
 * POST /auth/google — entra (ou conecta a conta, com link=true e usuário logado).
 */
function as_rest_google_auth( WP_REST_Request $req ) {
	$nonce   = sanitize_text_field( wp_unslash( $_COOKIE[ AS_GOOGLE_NONCE_COOKIE ] ?? '' ) );
	$linking = $req['link'] && is_user_logged_in() ? get_current_user_id() : 0;

	$profile = as_google_verify_credential( (string) $req['credential'], $nonce );
	if ( is_wp_error( $profile ) ) {
		return as_social_rest_error( $profile->get_error_code(), 'provider' === $profile->get_error_code() ? 502 : 400 );
	}

	$user = as_social_resolve_user( 'google', $profile, $linking );
	if ( is_wp_error( $user ) ) {
		// Mantém o nonce: a pessoa pode tentar de novo com outra conta sem recarregar a página.
		return as_social_rest_error( $user->get_error_code(), 'linked_other' === $user->get_error_code() ? 409 : 403 );
	}

	// Nonce de uso único: consumido quando o login/vinculação dá certo.
	as_social_cookie( AS_GOOGLE_NONCE_COOKIE, '', 0 );

	as_social_fill_profile( $user->ID, $profile );

	if ( $linking ) {
		return as_me_payload( $user->ID );
	}

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true, is_ssl() );
	do_action( 'wp_login', $user->user_login, $user );

	$target = (string) ( $req['redirectTo'] ?? '' );
	return [ 'redirect' => '' !== $target ? wp_validate_redirect( $target, as_url( 'painel' ) ) : as_url( 'painel' ) ];
}

/* =========================================================================
 * Facebook (OAuth 2.0 com redirecionamento)
 * ====================================================================== */

function as_facebook_graph_url( $path ) {
	$version = apply_filters( 'as_facebook_graph_version', 'v23.0' );
	return 'https://graph.facebook.com/' . $version . '/' . ltrim( $path, '/' );
}

function as_social_start_url( $provider, $redirect_to = '' ) {
	$url = as_url( 'auth', $provider );
	return $redirect_to ? add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $url ) : $url;
}

function as_social_callback_url( $provider ) {
	return as_url( 'auth', $provider, 'callback' );
}

add_action( 'init', function () {
	add_rewrite_rule( '^auth/(facebook)/?$', 'index.php?as_route=auth&as_provider=$matches[1]', 'top' );
	add_rewrite_rule( '^auth/(facebook)/callback/?$', 'index.php?as_route=auth-callback&as_provider=$matches[1]', 'top' );
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'as_provider';
	return $vars;
} );

add_action( 'template_redirect', function () {
	$route    = get_query_var( 'as_route' );
	$provider = get_query_var( 'as_provider' );

	if ( ! in_array( $route, [ 'auth', 'auth-callback' ], true ) ) {
		return;
	}

	nocache_headers();

	if ( 'facebook' !== $provider || ! as_social_enabled( $provider ) ) {
		as_social_fail( 'disabled' );
	}

	'auth' === $route ? as_facebook_begin() : as_facebook_finish();
}, 0 );

/**
 * Volta para o login (ou perfil, se estava conectando conta) com um código de erro.
 */
function as_social_fail( $code, $linking = false ) {
	wp_safe_redirect( add_query_arg( 'as_error', $code, as_url( $linking ? 'perfil' : 'entrar' ) ) );
	exit;
}

function as_facebook_begin() {
	$state = wp_generate_password( 32, false );

	set_transient( 'as_oauth_' . $state, [
		'redirect_to' => as_safe_redirect_target( '' ),
		'link_user'   => get_current_user_id(),
	], 10 * MINUTE_IN_SECONDS );

	// O state também fica num cookie: o retorno só é aceito no mesmo navegador que iniciou o login.
	as_social_cookie( AS_OAUTH_COOKIE, $state, 10 * MINUTE_IN_SECONDS );

	$scopes = [ 'email', 'public_profile' ];
	if ( as_social_setting( 'facebook_birthday' ) ) {
		$scopes[] = 'user_birthday';
	}

	wp_redirect( add_query_arg( array_map( 'rawurlencode', [ // phpcs:ignore -- domínio externo do provedor.
		'client_id'     => as_social_setting( 'facebook_app_id' ),
		'redirect_uri'  => as_social_callback_url( 'facebook' ),
		'response_type' => 'code',
		'scope'         => implode( ',', $scopes ),
		'state'         => $state,
	] ), 'https://www.facebook.com/' . apply_filters( 'as_facebook_graph_version', 'v23.0' ) . '/dialog/oauth' ) );
	exit;
}

function as_facebook_finish() {
	$state  = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) ); // phpcs:ignore
	$cookie = sanitize_text_field( wp_unslash( $_COOKIE[ AS_OAUTH_COOKIE ] ?? '' ) );
	$data   = $state ? get_transient( 'as_oauth_' . $state ) : false;

	if ( $state ) {
		delete_transient( 'as_oauth_' . $state );
	}
	as_social_cookie( AS_OAUTH_COOKIE, '', 0 );

	if ( ! $data || ! hash_equals( $state, $cookie ) ) {
		as_social_fail( 'state' );
	}

	$linking = (int) $data['link_user'];

	if ( isset( $_GET['error'] ) || empty( $_GET['code'] ) ) { // phpcs:ignore
		as_social_fail( 'denied', $linking );
	}

	$profile = as_facebook_fetch_profile( sanitize_text_field( wp_unslash( $_GET['code'] ) ) ); // phpcs:ignore
	if ( is_wp_error( $profile ) ) {
		as_social_fail( $profile->get_error_code(), $linking );
	}

	$user = as_social_resolve_user( 'facebook', $profile, $linking );
	if ( is_wp_error( $user ) ) {
		as_social_fail( $user->get_error_code(), $linking );
	}

	as_social_fill_profile( $user->ID, $profile );

	if ( $linking ) {
		wp_safe_redirect( add_query_arg( 'as_linked', 'facebook', as_url( 'perfil' ) ) );
		exit;
	}

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true, is_ssl() );
	do_action( 'wp_login', $user->user_login, $user );

	wp_safe_redirect( wp_validate_redirect( $data['redirect_to'] ?: as_url( 'painel' ), as_url( 'painel' ) ) );
	exit;
}

/**
 * Perfil normalizado do Facebook: id, email, name, avatar, birthdate (Y-m-d ou '').
 *
 * @return array|WP_Error
 */
function as_facebook_fetch_profile( $code ) {
	$secret = (string) as_social_setting( 'facebook_app_secret' );

	$token = as_social_json( wp_remote_get( add_query_arg( array_map( 'rawurlencode', [
		'client_id'     => as_social_setting( 'facebook_app_id' ),
		'client_secret' => $secret,
		'redirect_uri'  => as_social_callback_url( 'facebook' ),
		'code'          => $code,
	] ), as_facebook_graph_url( 'oauth/access_token' ) ), [ 'timeout' => 15 ] ) );
	if ( empty( $token['access_token'] ) ) {
		return new WP_Error( 'provider' );
	}

	$me = as_social_json( wp_remote_get( add_query_arg( array_map( 'rawurlencode', [
		'fields'          => 'id,name,email,birthday,picture.width(400).height(400)',
		'access_token'    => $token['access_token'],
		'appsecret_proof' => hash_hmac( 'sha256', $token['access_token'], $secret ),
	] ), as_facebook_graph_url( 'me' ) ), [ 'timeout' => 15 ] ) );
	if ( empty( $me['id'] ) ) {
		return new WP_Error( 'provider' );
	}
	if ( empty( $me['email'] ) ) {
		return new WP_Error( 'no_email' );
	}

	// Formato do Facebook: MM/DD/YYYY (ou parcial, que ignoramos).
	$birthdate = '';
	if ( ! empty( $me['birthday'] ) && preg_match( '#^(\d{2})/(\d{2})/(\d{4})$#', $me['birthday'], $m ) ) {
		$birthdate = "{$m[3]}-{$m[1]}-{$m[2]}";
	}

	$picture = $me['picture']['data'] ?? [];

	return [
		'id'        => (string) $me['id'],
		'email'     => (string) $me['email'],
		'name'      => (string) ( $me['name'] ?? '' ),
		'avatar'    => empty( $picture['is_silhouette'] ) ? (string) ( $picture['url'] ?? '' ) : '',
		'birthdate' => $birthdate,
	];
}

function as_social_json( $response ) {
	if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 400 ) {
		return [];
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	return is_array( $data ) ? $data : [];
}

/* =========================================================================
 * Contas vinculadas (comum aos dois provedores)
 * ====================================================================== */

function as_social_meta_key( $provider ) {
	return 'as_' . $provider . '_id';
}

/**
 * Usuário vinculado ao ID do provedor.
 */
function as_social_find_user( $provider, $provider_id ) {
	$users = get_users( [
		'meta_key'   => as_social_meta_key( $provider ),
		'meta_value' => $provider_id,
		'number'     => 1,
		'fields'     => 'all',
	] );
	return $users[0] ?? null;
}

/**
 * Encontra, vincula ou cria o usuário do perfil social.
 *
 * @return WP_User|WP_Error
 */
function as_social_resolve_user( $provider, array $profile, $link_user_id = 0 ) {
	$key    = as_social_meta_key( $provider );
	$linked = as_social_find_user( $provider, $profile['id'] );

	// Conectando a partir do perfil (usuário já logado).
	if ( $link_user_id ) {
		if ( $linked && $linked->ID !== $link_user_id ) {
			return new WP_Error( 'linked_other' );
		}
		update_user_meta( $link_user_id, $key, $profile['id'] );
		return get_userdata( $link_user_id );
	}

	if ( $linked ) {
		return $linked;
	}

	// Mesmo e-mail (verificado pelo provedor): vincula à conta existente.
	$user = get_user_by( 'email', $profile['email'] );
	if ( $user ) {
		update_user_meta( $user->ID, $key, $profile['id'] );
		return $user;
	}

	if ( ! get_option( 'users_can_register' ) ) {
		return new WP_Error( 'no_account' );
	}

	$user_id = as_create_user( $profile['email'], $profile['name'], 'organizador' );
	if ( is_wp_error( $user_id ) ) {
		return new WP_Error( 'create' );
	}
	update_user_meta( $user_id, $key, $profile['id'] );

	return get_userdata( $user_id );
}

/**
 * Completa foto e aniversário com os dados do provedor, sem sobrescrever o que o usuário já definiu.
 */
function as_social_fill_profile( $user_id, array $profile ) {
	if ( $profile['avatar'] && ! as_has_avatar( $user_id ) ) {
		as_avatar_from_url( $user_id, $profile['avatar'] );
	}
	if ( $profile['birthdate'] && ! as_get_birthdate( $user_id ) ) {
		as_set_birthdate( $user_id, $profile['birthdate'] );
	}
}

/**
 * Contas sociais vinculadas ao usuário.
 *
 * @return array<string,bool>
 */
function as_social_linked( $user_id ) {
	$linked = [];
	foreach ( array_keys( as_social_providers() ) as $provider ) {
		$linked[ $provider ] = '' !== (string) get_user_meta( (int) $user_id, as_social_meta_key( $provider ), true );
	}
	return $linked;
}

function as_social_unlink( $user_id, $provider ) {
	delete_user_meta( (int) $user_id, as_social_meta_key( $provider ) );
}

/* =========================================================================
 * Botões (login e cadastro)
 * ====================================================================== */

function as_social_buttons( $redirect_to = '' ) {
	$providers = as_social_enabled_providers();
	if ( ! $providers ) {
		return;
	}

	echo '<div class="space-y-3">';

	if ( isset( $providers['google'] ) ) {
		// Botão oficial renderizado pelo Google (src/js/lib/google.js); altura reservada evita "pulo".
		printf(
			'<div class="google-signin" data-google-signin data-redirect="%s"></div>',
			esc_attr( $redirect_to ?: as_url( 'painel' ) )
		);
	}

	if ( isset( $providers['facebook'] ) ) {
		printf(
			'<a href="%s" class="btn btn-ghost w-full bg-white" data-no-swup>%s %s</a>',
			esc_url( as_social_start_url( 'facebook', $redirect_to ) ),
			'<svg class="size-5" viewBox="0 0 24 24" aria-hidden="true"><path fill="#1877F2" d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.26h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07Z"/></svg>',
			esc_html__( 'Continuar com Facebook', 'amigosecreto' )
		);
	}

	echo '</div>';
	echo '<div class="my-6 flex items-center gap-3 text-xs font-medium tracking-wide text-ink/40 uppercase"><span class="h-px flex-1 bg-ink/10"></span>' . esc_html__( 'ou', 'amigosecreto' ) . '<span class="h-px flex-1 bg-ink/10"></span></div>';
}
