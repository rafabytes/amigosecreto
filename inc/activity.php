<?php
/**
 * Histórico de atividade dos usuários, para auditoria: cadastro, acessos, páginas vistas e ações
 * (sorteios, participantes, lista de presentes, perfil). Fica numa tabela própria e só aparece para
 * administradores, em /usuarios/{id}/ (templates/user.php).
 *
 * O histórico nunca guarda o resultado do sorteio (quem tirou quem): só que a pessoa abriu o resultado.
 */

defined( 'ABSPATH' ) || exit;

const AS_ACTIVITY_DB = '1';

/** Registros mais antigos que isso são apagados (LGPD: guardar só o necessário). */
const AS_ACTIVITY_RETENTION_DAYS = 365;

function as_activity_table() {
	global $wpdb;
	return $wpdb->prefix . 'as_activity';
}

/**
 * Cria a tabela na primeira execução e preenche com o que já existia (cadastros, sorteios criados,
 * sorteios realizados e presentes), para os usuários antigos não começarem com o histórico vazio.
 */
add_action( 'init', function () {
	if ( get_option( 'as_activity_db' ) === AS_ACTIVITY_DB ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( 'CREATE TABLE ' . as_activity_table() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		action varchar(32) NOT NULL,
		object_id bigint(20) unsigned NOT NULL DEFAULT 0,
		url varchar(255) NOT NULL DEFAULT '',
		detail text NULL,
		ip varchar(45) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_time (user_id,created_at),
		KEY action_time (action,created_at)
	) {$wpdb->get_charset_collate()};" );

	if ( ! get_option( 'as_activity_db' ) ) {
		as_activity_backfill();
	}
	update_option( 'as_activity_db', AS_ACTIVITY_DB );
} );

function as_activity_backfill() {
	$old = [ 'ip' => false, 'backfill' => true ];

	foreach ( get_users( [ 'fields' => [ 'ID', 'user_registered' ] ] ) as $user ) {
		as_log_activity( (int) $user->ID, 'register', $old + [ 'time' => strtotime( $user->user_registered . ' UTC' ) ] );
	}

	$groups = get_posts( [ 'post_type' => 'as_grupo', 'post_status' => 'publish', 'posts_per_page' => -1 ] );
	foreach ( $groups as $group ) {
		$detail = [ 'title' => $group->post_title ];
		as_log_activity( (int) $group->post_author, 'group_create', $old + [
			'object_id' => $group->ID,
			'time'      => strtotime( $group->post_date_gmt . ' UTC' ),
			'detail'    => $detail,
		] );
		$drawn_at = (int) get_post_meta( $group->ID, '_as_drawn_at', true );
		if ( $drawn_at ) {
			as_log_activity( (int) $group->post_author, 'draw', $old + [
				'object_id' => $group->ID,
				'time'      => $drawn_at,
				'detail'    => $detail + [ 'count' => count( as_group_participants( $group->ID ) ) ],
			] );
		}
	}

	$gifts = get_posts( [ 'post_type' => 'as_presente', 'post_status' => 'publish', 'posts_per_page' => -1 ] );
	foreach ( $gifts as $gift ) {
		as_log_activity( (int) $gift->post_author, 'gift_add', $old + [
			'object_id' => $gift->ID,
			'time'      => strtotime( $gift->post_date_gmt . ' UTC' ),
			'detail'    => [ 'title' => $gift->post_title ],
		] );
	}
}

/**
 * Grava um evento.
 *
 * @param array $args object_id, url, detail (array), time (timestamp), ip (false = não gravar),
 *                    backfill (true = registro reconstruído a partir de dados antigos).
 */
function as_log_activity( $user_id, $action, array $args = [] ) {
	global $wpdb;
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return;
	}
	$detail = (array) ( $args['detail'] ?? [] );
	if ( ! empty( $args['backfill'] ) ) {
		$detail['backfill'] = 1;
	}
	$wpdb->insert( as_activity_table(), [
		'user_id'    => $user_id,
		'action'     => $action,
		'object_id'  => (int) ( $args['object_id'] ?? 0 ),
		'url'        => mb_substr( (string) ( $args['url'] ?? '' ), 0, 255 ),
		'detail'     => $detail ? wp_json_encode( $detail ) : null,
		'ip'         => false === ( $args['ip'] ?? true ) ? '' : as_client_ip(),
		'created_at' => gmdate( 'Y-m-d H:i:s', (int) ( $args['time'] ?? time() ) ),
	] );
}

/**
 * Quem fez a ação, quando é outra pessoa (ex.: o organizador que adicionou o participante).
 */
function as_activity_actor( $subject_id ) {
	$actor = wp_get_current_user();
	if ( ! $actor->exists() || $actor->ID === (int) $subject_id ) {
		return [];
	}
	return [ 'by' => $actor->display_name, 'by_id' => $actor->ID ];
}

function as_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

function as_user_agent() {
	return mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 200 );
}

/**
 * Eventos de um usuário, do mais recente ao mais antigo.
 *
 * @param string[] $actions Filtra por tipo; vazio traz todos.
 */
function as_user_activity( $user_id, array $actions = [], $limit = 100, $offset = 0 ) {
	global $wpdb;
	$where = $wpdb->prepare( 'user_id = %d', $user_id );
	if ( $actions ) {
		$where .= " AND action IN ('" . implode( "','", array_map( 'esc_sql', $actions ) ) . "')";
	}
	$rows = $wpdb->get_results( $wpdb->prepare(
		'SELECT * FROM ' . as_activity_table() . " WHERE $where ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", // phpcs:ignore
		$limit,
		$offset
	) );
	foreach ( $rows as $row ) {
		$row->detail = $row->detail ? (array) json_decode( $row->detail, true ) : [];
		$row->time   = strtotime( $row->created_at . ' UTC' );
	}
	return $rows;
}

/**
 * Total e data do último evento de cada tipo.
 *
 * @return array<string,array{total:int,last:int}>
 */
function as_user_activity_counts( $user_id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT action, COUNT(*) AS total, MAX(created_at) AS last FROM ' . as_activity_table() . ' WHERE user_id = %d GROUP BY action', $user_id ), OBJECT_K ); // phpcs:ignore
	return array_map( fn( $r ) => [ 'total' => (int) $r->total, 'last' => strtotime( $r->last . ' UTC' ) ], $rows );
}

/**
 * Último acesso (login ou página vista) de vários usuários de uma vez.
 *
 * @param int[] $user_ids
 * @return array<int,int> user_id => timestamp
 */
function as_users_last_seen( array $user_ids ) {
	global $wpdb;
	$user_ids = array_filter( array_map( 'intval', $user_ids ) );
	if ( ! $user_ids ) {
		return [];
	}
	$rows = $wpdb->get_results(
		'SELECT user_id, MAX(created_at) AS last FROM ' . as_activity_table() . // phpcs:ignore
		" WHERE action IN ('login','view') AND user_id IN (" . implode( ',', $user_ids ) . ') GROUP BY user_id'
	);
	$last = [];
	foreach ( $rows as $row ) {
		$last[ (int) $row->user_id ] = strtotime( $row->last . ' UTC' );
	}
	return $last;
}

/**
 * Números de vários usuários de uma vez, para a lista /usuarios/: sorteios que organiza, sorteios
 * em que participa e itens na lista de presentes.
 *
 * @param int[] $user_ids
 * @return array<int,array{organizes:int,joins:int,gifts:int}>
 */
function as_users_stats( array $user_ids ) {
	global $wpdb;
	$user_ids = array_filter( array_map( 'intval', $user_ids ) );
	$stats    = array_fill_keys( $user_ids, [ 'organizes' => 0, 'joins' => 0, 'gifts' => 0 ] );
	if ( ! $user_ids ) {
		return $stats;
	}
	$in      = implode( ',', $user_ids );
	$queries = [
		'organizes' => "SELECT post_author AS uid, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_type = 'as_grupo' AND post_status = 'publish' AND post_author IN ($in) GROUP BY post_author",
		'joins'     => "SELECT pm.meta_value AS uid, COUNT(DISTINCT pm.post_id) AS total FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_as_participant' AND p.post_type = 'as_grupo' AND p.post_status = 'publish' AND pm.meta_value IN ($in) GROUP BY pm.meta_value",
		'gifts'     => "SELECT post_author AS uid, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_type = 'as_presente' AND post_status = 'publish' AND post_author IN ($in) GROUP BY post_author",
	];
	foreach ( $queries as $key => $sql ) {
		foreach ( $wpdb->get_results( $sql ) as $row ) { // phpcs:ignore
			if ( isset( $stats[ (int) $row->uid ] ) ) {
				$stats[ (int) $row->uid ][ $key ] = (int) $row->total;
			}
		}
	}
	return $stats;
}

/**
 * Grupos do filtro da linha do tempo: chave => [ rótulo, tipos ].
 */
function as_activity_groups() {
	return [
		'tudo'      => [ __( 'Tudo', 'amigosecreto' ), [] ],
		'sorteios'  => [ __( 'Sorteios', 'amigosecreto' ), [ 'group_create', 'group_update', 'group_delete', 'group_join', 'group_leave', 'participant_add', 'participant_remove', 'added_to_group', 'removed_from_group', 'draw', 'draw_reset', 'reveal' ] ],
		'lista'     => [ __( 'Lista de presentes', 'amigosecreto' ), [ 'gift_add', 'gift_update', 'gift_delete' ] ],
		'acesso'    => [ __( 'Acesso e segurança', 'amigosecreto' ), [ 'register', 'login', 'login_failed', 'logout', 'password_reset_request', 'password_reset', 'profile_update', 'avatar_update', 'avatar_remove', 'social_link', 'social_unlink' ] ],
		'navegacao' => [ __( 'Navegação', 'amigosecreto' ), [ 'view' ] ],
	];
}

/* ---------------------------------------------------------------------------
 * O que é registrado: conta e acesso
 * ------------------------------------------------------------------------- */

add_action( 'as_user_registered', function ( $user_id, $source ) {
	$detail = [ 'method' => $source ];
	if ( 'invite' === $source ) {
		$detail += as_activity_actor( $user_id );
	}
	as_log_activity( $user_id, 'register', [ 'detail' => $detail ] );
}, 10, 2 );

add_action( 'wp_login', function ( $login, $user ) {
	$route  = (string) ( $GLOBALS['wp']->query_vars['rest_route'] ?? '' );
	$method = 'wp-login';
	if ( str_contains( $route, '/auth/google' ) ) {
		$method = 'google';
	} elseif ( 'auth-callback' === get_query_var( 'as_route' ) ) {
		$method = get_query_var( 'as_provider' ) ?: 'facebook';
	} elseif ( 'entrar' === get_query_var( 'as_route' ) ) {
		$method = 'password';
	}
	as_log_activity( $user->ID, 'login', [ 'detail' => [ 'method' => $method, 'agent' => as_user_agent() ] ] );
}, 10, 2 );

add_action( 'wp_login_failed', function ( $login ) {
	$user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
	if ( $user ) {
		as_log_activity( $user->ID, 'login_failed', [ 'detail' => [ 'agent' => as_user_agent() ] ] );
	}
} );

add_action( 'wp_logout', function ( $user_id ) {
	as_log_activity( (int) $user_id, 'logout' );
} );

/*
 * "Esqueci a senha" (ou "Enviar link de redefinição" no wp-admin). O WordPress também dispara
 * retrieve_password ao gerar o link dos e-mails de convite e cadastro; esses não contam: só vale
 * quando passou pelo retrieve_password(), que dispara lostpassword_post antes.
 */
add_action( 'retrieve_password', function ( $login ) {
	$user = get_user_by( 'login', $login );
	if ( $user && did_action( 'lostpassword_post' ) ) {
		as_log_activity( $user->ID, 'password_reset_request', [ 'detail' => as_activity_actor( $user->ID ) ] );
	}
} );

// Prioridade 1: antes de inc/profile.php limpar a marca de "senha pendente".
add_action( 'after_password_reset', function ( $user ) {
	as_log_activity( $user->ID, 'password_reset', [ 'detail' => [ 'first' => as_user_needs_password( $user->ID ) ? 1 : 0 ] ] );
}, 1 );

/**
 * Alterações feitas fora do /perfil/ (ex.: administrador editando no wp-admin).
 * As do /perfil/ são registradas por as_rest_update_me(), que também conhece a data de nascimento.
 */
add_action( 'profile_update', function ( $user_id, $old, $data ) {
	$route = (string) ( $GLOBALS['wp']->query_vars['rest_route'] ?? '' );
	if ( str_ends_with( untrailingslashit( $route ), '/me' ) ) {
		return;
	}
	$changed = [];
	if ( isset( $data['display_name'] ) && $data['display_name'] !== $old->display_name ) {
		$changed[] = __( 'nome', 'amigosecreto' );
	}
	if ( isset( $data['user_email'] ) && $data['user_email'] !== $old->user_email ) {
		$changed[] = __( 'e-mail', 'amigosecreto' );
	}
	if ( isset( $data['user_pass'] ) && $data['user_pass'] !== $old->user_pass ) {
		$changed[] = __( 'senha', 'amigosecreto' );
	}
	if ( $changed ) {
		as_log_activity( (int) $user_id, 'profile_update', [ 'detail' => [ 'fields' => $changed ] + as_activity_actor( $user_id ) ] );
	}
}, 10, 3 );

add_action( 'deleted_user', function ( $user_id ) {
	global $wpdb;
	$wpdb->delete( as_activity_table(), [ 'user_id' => (int) $user_id ] );
} );

/* ---------------------------------------------------------------------------
 * O que é registrado: sorteios (chamado pela API em inc/rest-api.php e inc/groups.php)
 * ------------------------------------------------------------------------- */

/**
 * Evento de um sorteio, no histórico de quem fez a ação.
 */
function as_log_group( $action, $group_id, array $detail = [] ) {
	as_log_activity( get_current_user_id(), $action, [
		'object_id' => (int) $group_id,
		'detail'    => [ 'title' => get_the_title( $group_id ) ] + $detail,
	] );
}

/**
 * Entrada ou saída de um participante: no histórico de quem fez e no de quem foi adicionado/removido.
 */
function as_log_participant_change( $group_id, $user, $added ) {
	$user  = $user instanceof WP_User ? $user : get_userdata( (int) $user );
	$actor = get_current_user_id();
	if ( ! $user ) {
		return;
	}
	if ( $actor === $user->ID ) {
		as_log_group( $added ? 'group_join' : 'group_leave', $group_id );
		return;
	}
	as_log_group( $added ? 'participant_add' : 'participant_remove', $group_id, [ 'name' => $user->display_name, 'user' => $user->ID ] );
	as_log_activity( $user->ID, $added ? 'added_to_group' : 'removed_from_group', [
		'object_id' => (int) $group_id,
		'ip'        => false,
		'detail'    => [ 'title' => get_the_title( $group_id ) ] + as_activity_actor( $user->ID ),
	] );
}

function as_log_gift( $action, $gift ) {
	if ( $gift instanceof WP_Post ) {
		as_log_activity( (int) $gift->post_author, $action, [ 'object_id' => $gift->ID, 'detail' => [ 'title' => $gift->post_title ] ] );
	}
}

/* ---------------------------------------------------------------------------
 * Páginas vistas: o navegador avisa a cada página exibida (carregamento e navegação pelo
 * Swup, em src/js/lib/activity.js). Pelo servidor não dá, porque o Swup pode pré-carregar links.
 * ------------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
	register_rest_route( AS_REST_NS, '/activity/view', [
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => 'is_user_logged_in',
		'callback'            => 'as_rest_activity_view',
	] );
} );

function as_rest_activity_view( WP_REST_Request $req ) {
	global $wpdb;
	$path = (string) $req->get_param( 'path' );
	$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ?: '/';
	if ( strlen( $path ) > 255 || str_starts_with( $path, '//' ) || ! str_starts_with( $path, $home ) ) {
		return [ 'ok' => false ];
	}
	$url     = esc_url_raw( home_url( substr( $path, strlen( $home ) - 1 ) ) );
	$user_id = get_current_user_id();

	// Recarregar a mesma página logo em seguida não vira outro registro.
	$last = $wpdb->get_row( $wpdb->prepare( 'SELECT url, created_at FROM ' . as_activity_table() . " WHERE user_id = %d AND action = 'view' ORDER BY id DESC LIMIT 1", $user_id ) ); // phpcs:ignore
	if ( $last && $last->url === $url && time() - strtotime( $last->created_at . ' UTC' ) < MINUTE_IN_SECONDS ) {
		return [ 'ok' => true ];
	}

	$title = sanitize_text_field( (string) $req->get_param( 'title' ) );
	// Tira o sufixo " – Nome do site" (ou " – Slogan", que o WordPress usa nas rotas do tema).
	$suffixes = array_filter( [ get_bloginfo( 'name' ), get_bloginfo( 'description' ) ] );
	if ( $suffixes ) {
		$title = preg_replace( '/\s+[–—|-]\s+(' . implode( '|', array_map( fn( $s ) => preg_quote( $s, '/' ), $suffixes ) ) . ')$/u', '', $title );
	}
	as_log_activity( $user_id, 'view', [
		'url'    => $url,
		'detail' => [ 'title' => mb_substr( $title, 0, 160 ) ],
	] );
	return [ 'ok' => true ];
}

/* ---------------------------------------------------------------------------
 * Retenção
 * ------------------------------------------------------------------------- */

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'as_activity_prune' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'as_activity_prune' );
	}
} );

add_action( 'as_activity_prune', function () {
	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		'DELETE FROM ' . as_activity_table() . ' WHERE created_at < %s', // phpcs:ignore
		gmdate( 'Y-m-d H:i:s', time() - AS_ACTIVITY_RETENTION_DAYS * DAY_IN_SECONDS )
	) );
} );

add_action( 'switch_theme', function () {
	wp_clear_scheduled_hook( 'as_activity_prune' );
} );

/* ---------------------------------------------------------------------------
 * Atalho no wp-admin → Usuários
 * ------------------------------------------------------------------------- */

add_filter( 'user_row_actions', function ( $actions, $user ) {
	if ( as_user_is_admin() ) {
		$actions['as_activity'] = '<a href="' . esc_url( as_url( 'usuarios', $user->ID ) ) . '">' . esc_html__( 'Atividade', 'amigosecreto' ) . '</a>';
	}
	return $actions;
}, 10, 2 );

/* ---------------------------------------------------------------------------
 * Apresentação
 * ------------------------------------------------------------------------- */

/** "Chrome no Windows" a partir do user agent. */
function as_agent_label( $agent ) {
	$agent   = (string) $agent;
	$browser = match ( true ) {
		str_contains( $agent, 'Edg/' )    => 'Edge',
		str_contains( $agent, 'OPR/' )    => 'Opera',
		str_contains( $agent, 'SamsungBrowser' ) => 'Samsung Internet',
		str_contains( $agent, 'Firefox' ) => 'Firefox',
		str_contains( $agent, 'Chrome' )  => 'Chrome',
		str_contains( $agent, 'Safari' )  => 'Safari',
		default                           => '',
	};
	$os = match ( true ) {
		str_contains( $agent, 'Android' )                                   => 'Android',
		str_contains( $agent, 'iPhone' ) || str_contains( $agent, 'iPad' ) => 'iOS',
		str_contains( $agent, 'Windows' )                                   => 'Windows',
		str_contains( $agent, 'Mac OS X' )                                  => 'macOS',
		str_contains( $agent, 'Linux' )                                     => 'Linux',
		default                                                             => '',
	};
	return trim( $browser . ( $browser && $os ? ' no ' : '' ) . $os );
}

/**
 * Ícone, texto (HTML seguro) e tom de um evento. Tom: '' normal, 'muted' navegação, 'alert' atenção.
 */
function as_activity_describe( $row ) {
	$d    = $row->detail;
	$link = fn( $html, $url ) => $url ? '<a href="' . esc_url( $url ) . '">' . $html . '</a>' : $html;

	// Sorteio: link para a página do grupo enquanto ele existir.
	$group = $link(
		'<strong>' . esc_html( $d['title'] ?? __( 'sorteio removido', 'amigosecreto' ) ) . '</strong>',
		$row->object_id && as_get_group( $row->object_id ) ? as_url( 'grupos', $row->object_id ) : ''
	);
	$person = fn( $name, $id ) => $link( '<strong>' . esc_html( $name ) . '</strong>', $id && get_userdata( (int) $id ) ? as_url( 'usuarios', (int) $id ) : '' );
	$by     = ! empty( $d['by'] ) ? ' <span class="text-ink/50">' . esc_html__( 'por', 'amigosecreto' ) . ' ' . $person( $d['by'], $d['by_id'] ?? 0 ) . '</span>' : '';
	$gift   = '<strong>' . esc_html( $d['title'] ?? '' ) . '</strong>';
	$prov   = esc_html( as_social_providers()[ $d['provider'] ?? '' ] ?? ( $d['provider'] ?? '' ) );
	$fields = esc_html( wp_sprintf( '%l', (array) ( $d['fields'] ?? [] ) ) );

	$methods = [
		'form'     => __( 'pelo formulário de cadastro', 'amigosecreto' ),
		'invite'   => __( 'por convite', 'amigosecreto' ),
		'google'   => __( 'com o Google', 'amigosecreto' ),
		'facebook' => __( 'com o Facebook', 'amigosecreto' ),
		'password' => __( 'com e-mail e senha', 'amigosecreto' ),
		'wp-login' => __( 'pelo wp-login.php', 'amigosecreto' ),
	];
	$method = isset( $methods[ $d['method'] ?? '' ] ) ? ' ' . esc_html( $methods[ $d['method'] ] ) : '';

	switch ( $row->action ) {
		case 'register':
			if ( 'invite' === ( $d['method'] ?? '' ) && ! empty( $d['by'] ) ) {
				/* translators: %s: nome de quem convidou */
				return [ 'sparkles', sprintf( esc_html__( 'Conta criada a convite de %s', 'amigosecreto' ), $person( $d['by'], $d['by_id'] ?? 0 ) ), '' ];
			}
			return [ 'sparkles', esc_html__( 'Conta criada', 'amigosecreto' ) . $method, '' ];
		case 'login':
			return [ 'login', esc_html__( 'Entrou no site', 'amigosecreto' ) . $method, '' ];
		case 'login_failed':
			return [ 'shield', esc_html__( 'Errou a senha ao tentar entrar', 'amigosecreto' ), 'alert' ];
		case 'logout':
			return [ 'logout', esc_html__( 'Saiu da conta', 'amigosecreto' ), '' ];
		case 'password_reset_request':
			return ! empty( $d['by'] )
				? [ 'mail', esc_html__( 'Recebeu um link para redefinir a senha', 'amigosecreto' ) . $by, 'alert' ]
				: [ 'mail', esc_html__( 'Pediu um link para redefinir a senha', 'amigosecreto' ), 'alert' ];
		case 'password_reset':
			return ! empty( $d['first'] )
				? [ 'lock', esc_html__( 'Criou a senha pelo link do e-mail e ativou a conta', 'amigosecreto' ), '' ]
				: [ 'lock', esc_html__( 'Redefiniu a senha pelo link do e-mail', 'amigosecreto' ), 'alert' ];
		case 'profile_update':
			/* translators: %s: lista de campos, ex.: "nome e e-mail" */
			return [ 'user', sprintf( esc_html__( 'Alterou %s no perfil', 'amigosecreto' ), $fields ) . $by, '' ];
		case 'avatar_update':
			return [ 'user', esc_html__( 'Trocou a foto do perfil', 'amigosecreto' ), '' ];
		case 'avatar_remove':
			return [ 'user', esc_html__( 'Removeu a foto do perfil', 'amigosecreto' ), '' ];
		case 'social_link':
			/* translators: %s: Google ou Facebook */
			return [ 'link', sprintf( empty( $d['auto'] ) ? esc_html__( 'Conectou a conta do %s', 'amigosecreto' ) : esc_html__( 'Conta do %s conectada automaticamente (mesmo e-mail)', 'amigosecreto' ), $prov ), '' ];
		case 'social_unlink':
			/* translators: %s: Google ou Facebook */
			return [ 'link', sprintf( esc_html__( 'Desconectou a conta do %s', 'amigosecreto' ), $prov ), 'alert' ];
		case 'group_create':
			/* translators: %s: nome do sorteio */
			return [ 'sparkles', sprintf( esc_html__( 'Criou o sorteio %s', 'amigosecreto' ), $group ), '' ];
		case 'group_update':
			$what = $fields ? ' <span class="text-ink/50">(' . $fields . ')</span>' : '';
			/* translators: %s: nome do sorteio */
			return [ 'pencil', sprintf( esc_html__( 'Editou o sorteio %s', 'amigosecreto' ), $group ) . $what, '' ];
		case 'group_delete':
			/* translators: %s: nome do sorteio */
			return [ 'trash', sprintf( esc_html__( 'Excluiu o sorteio %s', 'amigosecreto' ), $group ), 'alert' ];
		case 'group_join':
			/* translators: %s: nome do sorteio */
			return [ 'user-plus', sprintf( esc_html__( 'Entrou como participante no sorteio %s', 'amigosecreto' ), $group ), '' ];
		case 'group_leave':
			/* translators: %s: nome do sorteio */
			return [ 'user-minus', sprintf( esc_html__( 'Saiu da lista de participantes do sorteio %s', 'amigosecreto' ), $group ), '' ];
		case 'participant_add':
			/* translators: 1: nome da pessoa, 2: nome do sorteio */
			return [ 'user-plus', sprintf( esc_html__( 'Adicionou %1$s ao sorteio %2$s', 'amigosecreto' ), $person( $d['name'] ?? '', $d['user'] ?? 0 ), $group ), '' ];
		case 'participant_remove':
			/* translators: 1: nome da pessoa, 2: nome do sorteio */
			return [ 'user-minus', sprintf( esc_html__( 'Removeu %1$s do sorteio %2$s', 'amigosecreto' ), $person( $d['name'] ?? '', $d['user'] ?? 0 ), $group ), 'alert' ];
		case 'added_to_group':
			/* translators: %s: nome do sorteio */
			return [ 'user-plus', sprintf( esc_html__( 'Foi adicionado ao sorteio %s', 'amigosecreto' ), $group ) . $by, '' ];
		case 'removed_from_group':
			/* translators: %s: nome do sorteio */
			return [ 'user-minus', sprintf( esc_html__( 'Foi removido do sorteio %s', 'amigosecreto' ), $group ) . $by, 'alert' ];
		case 'draw':
			$extra = isset( $d['count'] ) ? ' <span class="text-ink/50">(' . esc_html( sprintf( _n( '%d participante', '%d participantes', (int) $d['count'], 'amigosecreto' ), (int) $d['count'] ) ) . ( isset( $d['notify'] ) && ! $d['notify'] ? ', ' . esc_html__( 'sem e-mail', 'amigosecreto' ) : '' ) . ')</span>' : '';
			/* translators: %s: nome do sorteio */
			return [ 'shuffle', sprintf( esc_html__( 'Fez o sorteio %s', 'amigosecreto' ), $group ) . $extra, '' ];
		case 'draw_reset':
			/* translators: %s: nome do sorteio */
			return [ 'shuffle', sprintf( esc_html__( 'Desfez o sorteio %s', 'amigosecreto' ), $group ), 'alert' ];
		case 'reveal':
			return ! empty( $d['again'] )
				/* translators: %s: nome do sorteio */
				? [ 'eye', sprintf( esc_html__( 'Viu de novo quem tirou no sorteio %s', 'amigosecreto' ), $group ), 'muted' ]
				/* translators: %s: nome do sorteio */
				: [ 'gift', sprintf( esc_html__( 'Descobriu quem tirou no sorteio %s', 'amigosecreto' ), $group ), '' ];
		case 'gift_add':
			/* translators: %s: nome do presente */
			return [ 'gift', sprintf( esc_html__( 'Adicionou %s à lista de presentes', 'amigosecreto' ), $gift ), '' ];
		case 'gift_update':
			/* translators: %s: nome do presente */
			return [ 'pencil', sprintf( esc_html__( 'Editou %s na lista de presentes', 'amigosecreto' ), $gift ), '' ];
		case 'gift_delete':
			/* translators: %s: nome do presente */
			return [ 'trash', sprintf( esc_html__( 'Tirou %s da lista de presentes', 'amigosecreto' ), $gift ), '' ];
		case 'view':
			$label = esc_html( ( $d['title'] ?? '' ) ?: (string) wp_parse_url( $row->url, PHP_URL_PATH ) );
			/* translators: %s: título da página */
			return [ 'eye', sprintf( esc_html__( 'Viu %s', 'amigosecreto' ), $link( $label, $row->url ) ), 'muted' ];
	}
	return [ 'clock', esc_html( $row->action ), '' ];
}
