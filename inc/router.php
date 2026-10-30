<?php
/**
 * Rotas do front-end. Não dependem de páginas criadas no admin:
 *
 *   /painel/           painel do usuário (grupos e atalhos)
 *   /grupos/{id}/      página do grupo (participantes, sorteio, quem eu tirei)
 *   /minha-lista/      editor da minha lista de presentes
 *   /lista/{user_id}/  lista de presentes de outro participante
 *   /perfil/           nome, e-mail e senha
 *   /entrar/           login
 *   /cadastro/         cadastro de organizadores (se "Qualquer pessoa pode se registrar" estiver ativo)
 */

defined( 'ABSPATH' ) || exit;

const AS_ROUTES_VERSION = '2';

function as_routes() {
	return [
		'painel'      => [ 'template' => 'dashboard', 'title' => __( 'Meu painel', 'amigosecreto' ), 'auth' => true ],
		'grupos'      => [ 'template' => 'group', 'title' => __( 'Grupo', 'amigosecreto' ), 'auth' => true, 'id' => true ],
		'minha-lista' => [ 'template' => 'wishlist-edit', 'title' => __( 'Minha lista de presentes', 'amigosecreto' ), 'auth' => true ],
		'lista'       => [ 'template' => 'wishlist-view', 'title' => __( 'Lista de presentes', 'amigosecreto' ), 'auth' => true, 'id' => true ],
		'perfil'      => [ 'template' => 'profile', 'title' => __( 'Meu perfil', 'amigosecreto' ), 'auth' => true ],
		'entrar'      => [ 'template' => 'login', 'title' => __( 'Entrar', 'amigosecreto' ), 'auth' => false ],
		'cadastro'    => [ 'template' => 'register', 'title' => __( 'Criar conta', 'amigosecreto' ), 'auth' => false ],
	];
}

add_action( 'init', function () {
	foreach ( as_routes() as $slug => $route ) {
		if ( ! empty( $route['id'] ) ) {
			add_rewrite_rule( '^' . $slug . '/(\d+)/?$', 'index.php?as_route=' . $slug . '&as_id=$matches[1]', 'top' );
		} else {
			add_rewrite_rule( '^' . $slug . '/?$', 'index.php?as_route=' . $slug, 'top' );
		}
	}
} );

/*
 * Regrava as regras sempre que o conjunto registrado muda (hash das regras "top", que inclui as de
 * social-login.php). Prioridade alta: roda depois que todos os arquivos registraram as suas.
 */
add_action( 'init', function () {
	global $wp_rewrite;
	$hash = AS_ROUTES_VERSION . ':' . md5( wp_json_encode( $wp_rewrite->extra_rules_top ) );
	if ( get_option( 'as_routes_version' ) !== $hash ) {
		flush_rewrite_rules( false );
		update_option( 'as_routes_version', $hash );
	}
}, 99 );

add_action( 'after_switch_theme', function () {
	delete_option( 'as_routes_version' );
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'as_route';
	$vars[] = 'as_id';
	return $vars;
} );

function as_current_route() {
	$route = get_query_var( 'as_route' );
	return isset( as_routes()[ $route ] ) ? $route : '';
}

/**
 * Título da página atual (pode ser sobrescrito pelo template via as_set_title()).
 */
function as_set_title( $title = null ) {
	static $current = '';
	if ( null !== $title ) {
		$current = $title;
	}
	return $current;
}

add_action( 'template_redirect', function () {
	// Usuário logado vai direto para o painel ao acessar a home.
	if ( is_front_page() && ! as_current_route() && is_user_logged_in() ) {
		wp_safe_redirect( as_url( 'painel' ) );
		exit;
	}

	$route = as_current_route();
	if ( ! $route ) {
		return;
	}

	$config = as_routes()[ $route ];

	if ( $config['auth'] ) {
		as_require_login();
	} elseif ( is_user_logged_in() ) {
		wp_safe_redirect( as_url( 'painel' ) );
		exit;
	}

	if ( 'cadastro' === $route && ! get_option( 'users_can_register' ) ) {
		as_set_404();
		return;
	}

	$id    = (int) get_query_var( 'as_id' );
	$title = $config['title'];

	// Recursos com ID: 404 se não existir ou se o usuário não tiver acesso.
	if ( 'grupos' === $route ) {
		if ( ! as_get_group( $id ) || ! as_user_can_view_group( $id ) ) {
			as_set_404();
			return;
		}
		$title = get_the_title( $id );
	}

	if ( 'lista' === $route ) {
		$owner = get_userdata( $id );
		if ( ! $owner || ! as_can_view_wishlist( $id ) ) {
			as_set_404();
			return;
		}
		$title = sprintf( __( 'Lista de %s', 'amigosecreto' ), $owner->display_name );
	}

	as_set_title( $title );
	status_header( 200 );
	nocache_headers();
}, 5 );

function as_set_404() {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}

add_filter( 'template_include', function ( $template ) {
	$route = as_current_route();
	if ( ! $route || is_404() ) {
		return $template;
	}
	$file = locate_template( 'templates/' . as_routes()[ $route ]['template'] . '.php' );
	return $file ?: $template;
}, 20 );

add_filter( 'document_title_parts', function ( $parts ) {
	if ( as_current_route() && as_set_title() ) {
		$parts['title'] = as_set_title();
	}
	return $parts;
} );

// Evita que o WordPress "adivinhe" e redirecione /grupos/12 para algum post.
add_filter( 'redirect_canonical', function ( $redirect ) {
	return as_current_route() ? false : $redirect;
} );
