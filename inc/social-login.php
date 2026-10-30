<?php
/**
 * Login com Google e Facebook (OAuth 2.0, sem plugins).
 *
 *   /auth/{google|facebook}/           inicia o login (ou conecta a conta, se já logado)
 *   /auth/{google|facebook}/callback/  retorno do provedor
 *
 * Credenciais em Configurações → Login social (inc/social-settings.php) ou constantes no wp-config.php:
 * AS_GOOGLE_CLIENT_ID, AS_GOOGLE_CLIENT_SECRET, AS_FACEBOOK_APP_ID, AS_FACEBOOK_APP_SECRET.
 *
 * Regras:
 *  - Conta já vinculada ao provedor → entra.
 *  - E-mail já cadastrado (ex.: participante convidado) → vincula e entra.
 *  - Conta nova → criada como organizador, se o cadastro estiver liberado.
 *  - Foto e data de nascimento do provedor são salvas só se o usuário ainda não tiver.
 */

defined( 'ABSPATH' ) || exit;

const AS_OAUTH_COOKIE = 'as_oauth_state';

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

function as_social_credentials( $provider ) {
	return 'google' === $provider
		? [ as_social_setting( 'google_client_id' ), as_social_setting( 'google_client_secret' ) ]
		: [ as_social_setting( 'facebook_app_id' ), as_social_setting( 'facebook_app_secret' ) ];
}

function as_social_enabled( $provider ) {
	[ $id, $secret ] = as_social_credentials( $provider );
	return isset( as_social_providers()[ $provider ] ) && '' !== $id && '' !== $secret;
}

/**
 * Provedores configurados.
 *
 * @return array<string,string> slug => rótulo
 */
function as_social_enabled_providers() {
	return array_filter( as_social_providers(), 'as_social_enabled', ARRAY_FILTER_USE_KEY );
}

function as_social_start_url( $provider, $redirect_to = '' ) {
	$url = as_url( 'auth', $provider );
	return $redirect_to ? add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $url ) : $url;
}

function as_social_callback_url( $provider ) {
	return as_url( 'auth', $provider, 'callback' );
}

function as_facebook_graph_url( $path ) {
	$version = apply_filters( 'as_facebook_graph_version', 'v23.0' );
	return 'https://graph.facebook.com/' . $version . '/' . ltrim( $path, '/' );
}

/* -------------------------------------------------------------------------
 * Rotas
 * ---------------------------------------------------------------------- */

add_action( 'init', function () {
	add_rewrite_rule( '^auth/(google|facebook)/?$', 'index.php?as_route=auth&as_provider=$matches[1]', 'top' );
	add_rewrite_rule( '^auth/(google|facebook)/callback/?$', 'index.php?as_route=auth-callback&as_provider=$matches[1]', 'top' );
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

	if ( ! as_social_enabled( $provider ) ) {
		as_social_fail( 'disabled' );
	}

	'auth' === $route ? as_social_begin( $provider ) : as_social_finish( $provider );
}, 0 );

/**
 * Volta para o login (ou perfil, se estava conectando conta) com um código de erro.
 */
function as_social_fail( $code, $linking = false ) {
	wp_safe_redirect( add_query_arg( 'as_error', $code, as_url( $linking ? 'perfil' : 'entrar' ) ) );
	exit;
}

/**
 * Mensagens dos códigos de erro, exibidas em /entrar/ e /perfil/.
 */
function as_social_error_message( $code ) {
	$messages = [
		'disabled'     => __( 'Esse tipo de acesso não está disponível.', 'amigosecreto' ),
		'state'        => __( 'A sessão de login expirou. Tente novamente.', 'amigosecreto' ),
		'denied'       => __( 'O acesso foi cancelado.', 'amigosecreto' ),
		'provider'     => __( 'Não conseguimos falar com o provedor de login. Tente novamente em instantes.', 'amigosecreto' ),
		'no_email'     => __( 'Sua conta não compartilhou um e-mail verificado. Libere o acesso ao e-mail ou entre com e-mail e senha.', 'amigosecreto' ),
		'no_account'   => __( 'Não encontramos uma conta com esse e-mail. Peça ao organizador do grupo para convidar você.', 'amigosecreto' ),
		'linked_other' => __( 'Essa conta já está vinculada a outro usuário.', 'amigosecreto' ),
		'create'       => __( 'Não foi possível criar sua conta. Tente novamente.', 'amigosecreto' ),
	];
	return $messages[ $code ] ?? '';
}

/* -------------------------------------------------------------------------
 * Início: redireciona para o provedor
 * ---------------------------------------------------------------------- */

function as_social_begin( $provider ) {
	$state = wp_generate_password( 32, false );

	set_transient( 'as_oauth_' . $state, [
		'provider'    => $provider,
		'redirect_to' => as_safe_redirect_target( '' ),
		'link_user'   => get_current_user_id(),
	], 10 * MINUTE_IN_SECONDS );

	// O state também fica num cookie: o retorno só é aceito no mesmo navegador que iniciou o login.
	setcookie( AS_OAUTH_COOKIE, $state, [
		'expires'  => time() + 10 * MINUTE_IN_SECONDS,
		'path'     => COOKIEPATH ?: '/',
		'domain'   => COOKIE_DOMAIN ?: '',
		'secure'   => is_ssl(),
		'httponly' => true,
		'samesite' => 'Lax',
	] );

	[ $client_id ] = as_social_credentials( $provider );
	$redirect_uri  = as_social_callback_url( $provider );

	if ( 'google' === $provider ) {
		$scopes = [ 'openid', 'email', 'profile' ];
		if ( as_social_setting( 'google_birthday' ) ) {
			$scopes[] = 'https://www.googleapis.com/auth/user.birthday.read';
		}
		$url = add_query_arg( array_map( 'rawurlencode', [
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => implode( ' ', $scopes ),
			'state'         => $state,
			'prompt'        => 'select_account',
			// Resposta no fragmento (#…): o Google inclui iss=https://accounts.google.com no retorno, e
			// firewalls como o ModSecurity bloqueiam parâmetros que começam com "https://". O fragmento
			// não chega ao servidor; as_social_fragment_relay() repassa só code/state/error.
			'response_mode' => 'fragment',
		] ), 'https://accounts.google.com/o/oauth2/v2/auth' );
	} else {
		$scopes = [ 'email', 'public_profile' ];
		if ( as_social_setting( 'facebook_birthday' ) ) {
			$scopes[] = 'user_birthday';
		}
		$url = add_query_arg( array_map( 'rawurlencode', [
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => implode( ',', $scopes ),
			'state'         => $state,
		] ), 'https://www.facebook.com/' . apply_filters( 'as_facebook_graph_version', 'v23.0' ) . '/dialog/oauth' );
	}

	wp_redirect( $url ); // phpcs:ignore -- domínio externo do provedor.
	exit;
}

/* -------------------------------------------------------------------------
 * Retorno: troca o código pelo perfil e autentica
 * ---------------------------------------------------------------------- */

/**
 * Página intermediária do retorno do Google (response_mode=fragment): lê code/state/error do
 * fragmento da URL e recarrega o callback com esses valores na query string.
 */
function as_social_fragment_relay() {
	status_header( 200 );
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	header( 'Referrer-Policy: no-referrer' );
	$fallback = add_query_arg( 'as_error', 'state', as_url( 'entrar' ) );
	?>
<!doctype html>
<html lang="pt-BR">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<title><?php esc_html_e( 'Concluindo login…', 'amigosecreto' ); ?></title>
	<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,sans-serif;background:#f7f5fc;color:#1b1135}</style>
</head>
<body>
	<p><?php esc_html_e( 'Concluindo login…', 'amigosecreto' ); ?></p>
	<noscript><p><?php esc_html_e( 'Ative o JavaScript para entrar com o Google.', 'amigosecreto' ); ?></p></noscript>
	<script>
		(function () {
			var hash = new URLSearchParams(location.hash.slice(1));
			var query = new URLSearchParams();
			['code', 'state', 'error'].forEach(function (key) {
				if (hash.get(key)) query.set(key, hash.get(key));
			});
			location.replace(query.get('state') ? location.pathname + '?' + query.toString() : <?php echo wp_json_encode( $fallback ); ?>);
		})();
	</script>
</body>
</html>
	<?php
	exit;
}

function as_social_finish( $provider ) {
	// Retorno do Google sem query string: os dados vieram no fragmento (#…).
	if ( 'google' === $provider && empty( $_GET['state'] ) && empty( $_GET['code'] ) && empty( $_GET['error'] ) ) { // phpcs:ignore
		as_social_fragment_relay();
	}

	$state  = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) ); // phpcs:ignore
	$cookie = sanitize_text_field( wp_unslash( $_COOKIE[ AS_OAUTH_COOKIE ] ?? '' ) );
	$data   = $state ? get_transient( 'as_oauth_' . $state ) : false;

	if ( $state ) {
		delete_transient( 'as_oauth_' . $state );
	}
	setcookie( AS_OAUTH_COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '' );

	if ( ! $data || ! hash_equals( $state, $cookie ) || $data['provider'] !== $provider ) {
		as_social_fail( 'state' );
	}

	$linking = (int) $data['link_user'];

	if ( isset( $_GET['error'] ) || empty( $_GET['code'] ) ) { // phpcs:ignore
		as_social_fail( 'denied', $linking );
	}

	$profile = as_social_fetch_profile( $provider, sanitize_text_field( wp_unslash( $_GET['code'] ) ) ); // phpcs:ignore
	if ( is_wp_error( $profile ) ) {
		as_social_fail( $profile->get_error_code(), $linking );
	}

	$user = as_social_resolve_user( $provider, $profile, $linking );
	if ( is_wp_error( $user ) ) {
		as_social_fail( $user->get_error_code(), $linking );
	}

	as_social_fill_profile( $user->ID, $profile );

	if ( $linking ) {
		wp_safe_redirect( add_query_arg( 'as_linked', $provider, as_url( 'perfil' ) ) );
		exit;
	}

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true, is_ssl() );
	do_action( 'wp_login', $user->user_login, $user );

	wp_safe_redirect( wp_validate_redirect( $data['redirect_to'] ?: as_url( 'painel' ), as_url( 'painel' ) ) );
	exit;
}

/**
 * Perfil normalizado: id, email, name, avatar, birthdate (Y-m-d ou '').
 *
 * @return array|WP_Error
 */
function as_social_fetch_profile( $provider, $code ) {
	[ $client_id, $secret ] = as_social_credentials( $provider );
	$redirect_uri           = as_social_callback_url( $provider );

	if ( 'google' === $provider ) {
		$token = as_social_json( wp_remote_post( 'https://oauth2.googleapis.com/token', [
			'timeout' => 15,
			'body'    => [
				'code'          => $code,
				'client_id'     => $client_id,
				'client_secret' => $secret,
				'redirect_uri'  => $redirect_uri,
				'grant_type'    => 'authorization_code',
			],
		] ) );
		if ( empty( $token['access_token'] ) ) {
			return new WP_Error( 'provider' );
		}
		$auth = [ 'timeout' => 15, 'headers' => [ 'Authorization' => 'Bearer ' . $token['access_token'] ] ];

		$info = as_social_json( wp_remote_get( 'https://openidconnect.googleapis.com/v1/userinfo', $auth ) );
		if ( empty( $info['sub'] ) ) {
			return new WP_Error( 'provider' );
		}
		if ( empty( $info['email'] ) || empty( $info['email_verified'] ) ) {
			return new WP_Error( 'no_email' );
		}

		$birthdate = '';
		if ( as_social_setting( 'google_birthday' ) ) {
			$people = as_social_json( wp_remote_get( 'https://people.googleapis.com/v1/people/me?personFields=birthdays', $auth ) );
			foreach ( (array) ( $people['birthdays'] ?? [] ) as $birthday ) {
				$d = $birthday['date'] ?? [];
				if ( ! empty( $d['year'] ) && ! empty( $d['month'] ) && ! empty( $d['day'] ) ) {
					$birthdate = sprintf( '%04d-%02d-%02d', $d['year'], $d['month'], $d['day'] );
					break;
				}
			}
		}

		return [
			'id'        => (string) $info['sub'],
			'email'     => (string) $info['email'],
			'name'      => (string) ( $info['name'] ?? '' ),
			// Foto padrão do Google vem em 96px; pede 400px.
			'avatar'    => ! empty( $info['picture'] ) ? preg_replace( '/=s\d+(-c)?$/', '=s400-c', $info['picture'] ) : '',
			'birthdate' => $birthdate,
		];
	}

	// Facebook
	$token = as_social_json( wp_remote_get( add_query_arg( array_map( 'rawurlencode', [
		'client_id'     => $client_id,
		'client_secret' => $secret,
		'redirect_uri'  => $redirect_uri,
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

/**
 * Botões "Continuar com Google/Facebook" (login e cadastro).
 */
function as_social_buttons( $redirect_to = '' ) {
	$providers = as_social_enabled_providers();
	if ( ! $providers ) {
		return;
	}

	$logos = [
		'google'   => '<svg class="size-5" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M23.52 12.27c0-.85-.08-1.67-.22-2.45H12v4.63h6.46a5.52 5.52 0 0 1-2.4 3.62v3h3.88c2.27-2.09 3.58-5.17 3.58-8.8Z"/><path fill="#34A853" d="M12 24c3.24 0 5.96-1.07 7.94-2.9l-3.88-3c-1.07.72-2.45 1.15-4.06 1.15-3.13 0-5.78-2.11-6.72-4.95H1.27v3.1A12 12 0 0 0 12 24Z"/><path fill="#FBBC05" d="M5.28 14.3a7.2 7.2 0 0 1 0-4.6V6.6H1.27a12 12 0 0 0 0 10.8l4-3.1Z"/><path fill="#EA4335" d="M12 4.75c1.76 0 3.34.6 4.59 1.8l3.44-3.44A11.97 11.97 0 0 0 1.27 6.6l4.01 3.1C6.22 6.86 8.87 4.75 12 4.75Z"/></svg>',
		'facebook' => '<svg class="size-5" viewBox="0 0 24 24" aria-hidden="true"><path fill="#1877F2" d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.26h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07Z"/></svg>',
	];

	echo '<div class="space-y-3">';
	foreach ( $providers as $provider => $label ) {
		printf(
			'<a href="%s" class="btn btn-ghost w-full bg-white" data-no-swup>%s %s</a>',
			esc_url( as_social_start_url( $provider, $redirect_to ) ),
			$logos[ $provider ], // phpcs:ignore -- SVG fixo do tema.
			/* translators: %s: Google ou Facebook */
			esc_html( sprintf( __( 'Continuar com %s', 'amigosecreto' ), $label ) )
		);
	}
	echo '</div>';
	echo '<div class="my-6 flex items-center gap-3 text-xs font-medium tracking-wide text-ink/40 uppercase"><span class="h-px flex-1 bg-ink/10"></span>' . esc_html__( 'ou', 'amigosecreto' ) . '<span class="h-px flex-1 bg-ink/10"></span></div>';
}
