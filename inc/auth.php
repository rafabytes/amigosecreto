<?php
/**
 * Login e cadastro no front-end, e identidade visual da tela wp-login.php
 * (usada para "esqueci minha senha" e "definir senha" dos convidados).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Erros do formulário atual, lidos pelos templates de login/cadastro.
 */
function as_form_errors( $errors = null ) {
	static $current = [];
	if ( null !== $errors ) {
		$current = (array) $errors;
	}
	return $current;
}

function as_safe_redirect_target( $fallback ) {
	$target = isset( $_REQUEST['redirect_to'] ) ? trim( wp_unslash( $_REQUEST['redirect_to'] ) ) : ''; // phpcs:ignore
	// wp_validate_redirect() devolve '' (e não o fallback) quando o destino está vazio.
	return '' === $target ? $fallback : wp_validate_redirect( $target, $fallback );
}

add_action( 'template_redirect', function () {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || is_user_logged_in() ) {
		return;
	}

	$route = get_query_var( 'as_route' );

	if ( 'entrar' === $route ) {
		as_handle_login();
	} elseif ( 'cadastro' === $route && get_option( 'users_can_register' ) ) {
		as_handle_register();
	}
}, 1 );

function as_handle_login() {
	if ( ! wp_verify_nonce( $_POST['_as_nonce'] ?? '', 'as_login' ) ) { // phpcs:ignore
		as_form_errors( [ __( 'Sessão expirada. Tente novamente.', 'amigosecreto' ) ] );
		return;
	}

	$user = wp_signon( [
		'user_login'    => sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) ),
		'user_password' => (string) wp_unslash( $_POST['pwd'] ?? '' ), // phpcs:ignore
		'remember'      => ! empty( $_POST['rememberme'] ),
	], is_ssl() );

	if ( is_wp_error( $user ) ) {
		as_form_errors( [ __( 'E-mail ou senha incorretos.', 'amigosecreto' ) ] );
		return;
	}

	wp_safe_redirect( as_safe_redirect_target( as_url( 'painel' ) ) );
	exit;
}

function as_handle_register() {
	if ( ! wp_verify_nonce( $_POST['_as_nonce'] ?? '', 'as_register' ) ) { // phpcs:ignore
		as_form_errors( [ __( 'Sessão expirada. Tente novamente.', 'amigosecreto' ) ] );
		return;
	}

	$name      = sanitize_text_field( wp_unslash( $_POST['as_name'] ?? '' ) );
	$email     = sanitize_email( wp_unslash( $_POST['as_email'] ?? '' ) );
	$birthdate = sanitize_text_field( wp_unslash( $_POST['as_birthdate'] ?? '' ) );
	$avatar    = $_FILES['as_avatar'] ?? null; // phpcs:ignore
	$has_photo = $avatar && UPLOAD_ERR_NO_FILE !== ( $avatar['error'] ?? UPLOAD_ERR_NO_FILE );
	$errors    = [];

	if ( '' === $name ) {
		$errors[] = __( 'Informe seu nome.', 'amigosecreto' );
	}
	if ( ! is_email( $email ) ) {
		$errors[] = __( 'Informe um e-mail válido.', 'amigosecreto' );
	}
	if ( '' !== $birthdate && ! as_sanitize_birthdate( $birthdate ) ) {
		$errors[] = __( 'Data de nascimento inválida.', 'amigosecreto' );
	}
	if ( $has_photo ) {
		$type = wp_check_filetype( $avatar['name'] ?? '', as_avatar_mimes() );
		if ( UPLOAD_ERR_OK !== $avatar['error'] || ! $type['type'] || $avatar['size'] > AS_AVATAR_MAX_BYTES ) {
			$errors[] = __( 'A foto deve ser JPG, PNG, WebP ou GIF com até 5 MB.', 'amigosecreto' );
		}
	}

	if ( $errors ) {
		as_form_errors( $errors );
		return;
	}

	$existing = get_user_by( 'email', $email );

	if ( $existing ) {
		// Conta criada por convite que nunca foi ativada: reenvia o link em vez de dar erro.
		if ( as_user_needs_password( $existing->ID ) ) {
			as_email_signup( $existing );
			wp_safe_redirect( add_query_arg( 'enviado', 1, as_url( 'cadastro' ) ) );
			exit;
		}
		as_form_errors( [ __( 'Já existe uma conta com esse e-mail. Entre ou recupere sua senha.', 'amigosecreto' ) ] );
		return;
	}

	$user_id = as_create_user( $email, $name, 'organizador' );
	if ( is_wp_error( $user_id ) ) {
		as_form_errors( [ $user_id->get_error_message() ] );
		return;
	}

	if ( $birthdate ) {
		as_set_birthdate( $user_id, $birthdate );
	}
	if ( $has_photo ) {
		as_upload_avatar( $user_id, 'as_avatar' );
	}

	// A conta só é usada depois que a pessoa confirma o e-mail criando a senha.
	as_email_signup( get_userdata( $user_id ) );

	wp_safe_redirect( add_query_arg( 'enviado', 1, as_url( 'cadastro' ) ) );
	exit;
}

// O cadastro padrão do WordPress (wp-login.php?action=register) usa o formulário do tema.
add_action( 'login_form_register', function () {
	wp_safe_redirect( as_url( get_option( 'users_can_register' ) ? 'cadastro' : 'entrar' ) );
	exit;
} );

/**
 * Quem entra pelo wp-login.php (ex.: depois de definir a senha do convite) vai para o painel.
 */
add_filter( 'login_redirect', function ( $redirect_to, $requested, $user ) {
	if ( $user instanceof WP_User && ! user_can( $user, 'manage_options' ) ) {
		return $requested && false === strpos( $requested, 'wp-admin' ) ? $requested : as_url( 'painel' );
	}
	return $redirect_to;
}, 10, 3 );

add_filter( 'logout_redirect', function () {
	return home_url( '/' );
} );

/**
 * Visual da tela wp-login.php alinhado ao tema.
 */
add_action( 'login_enqueue_scripts', function () {
	?>
	<style>
		body.login { background: #f7f3ff; font-family: ui-sans-serif, system-ui, sans-serif; }
		body.login h1 a {
			background: none; width: auto; height: auto; text-indent: 0; font-size: 26px;
			font-weight: 800; color: #5b21b6; text-decoration: none;
		}
		.login form { border: 0; border-radius: 20px; box-shadow: 0 10px 40px -12px rgba(91,33,182,.25); }
		.login .button-primary { background: #6d28d9 !important; border-color: #6d28d9 !important; border-radius: 999px !important; }
		.login .button-primary:hover { background: #5b21b6 !important; }
		.login input:focus { border-color: #7c3aed !important; box-shadow: 0 0 0 1px #7c3aed !important; }
		.login #nav a, .login #backtoblog a { color: #5b21b6 !important; }
	</style>
	<?php
} );

add_filter( 'login_headerurl', fn() => home_url( '/' ) );
add_filter( 'login_headertext', fn() => '🎁 ' . get_bloginfo( 'name' ) );
