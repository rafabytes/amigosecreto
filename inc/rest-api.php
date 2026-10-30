<?php
/**
 * API REST do tema: namespace amigosecreto/v1.
 * Autenticação por cookie + nonce wp_rest (enviado pelo front em X-WP-Nonce).
 */

defined( 'ABSPATH' ) || exit;

const AS_REST_NS = 'amigosecreto/v1';

add_action( 'rest_api_init', function () {
	$id = '(?P<id>\d+)';

	/* ---------- Grupos ---------- */

	register_rest_route( AS_REST_NS, '/groups', [
		[
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => fn() => array_map( 'as_group_payload', as_user_groups() ),
			'permission_callback' => 'is_user_logged_in',
		],
		[
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'as_rest_create_group',
			'permission_callback' => fn() => as_user_can_organize(),
			'args'                => [
				'title' => [ 'type' => 'string', 'required' => true ],
			],
		],
	] );

	register_rest_route( AS_REST_NS, "/groups/{$id}", [
		[
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => fn( $req ) => as_group_payload( (int) $req['id'] ),
			'permission_callback' => fn( $req ) => as_user_can_view_group( (int) $req['id'] ),
		],
		[
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => 'as_rest_update_group',
			'permission_callback' => 'as_rest_can_manage_group',
		],
		[
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => function ( $req ) {
				wp_trash_post( (int) $req['id'] );
				return [ 'deleted' => true, 'redirect' => as_url( 'painel' ) ];
			},
			'permission_callback' => 'as_rest_can_manage_group',
		],
	] );

	register_rest_route( AS_REST_NS, "/groups/{$id}/participants", [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => function ( $req ) {
			$user = as_invite_participant( (int) $req['id'], $req['email'], $req['name'] ?? '' );
			return is_wp_error( $user ) ? $user : as_group_payload( (int) $req['id'] );
		},
		'permission_callback' => 'as_rest_can_manage_group',
		'args'                => [
			'email' => [ 'type' => 'string', 'required' => true ],
			'name'  => [ 'type' => 'string' ],
		],
	] );

	register_rest_route( AS_REST_NS, "/groups/{$id}/participants/(?P<user>\d+)", [
		'methods'             => WP_REST_Server::DELETABLE,
		'callback'            => function ( $req ) {
			$group_id = (int) $req['id'];
			if ( as_group_is_drawn( $group_id ) ) {
				return new WP_Error( 'as_already_drawn', __( 'O sorteio já foi feito. Refaça o sorteio para alterar participantes.', 'amigosecreto' ), [ 'status' => 409 ] );
			}
			as_group_remove_participant( $group_id, (int) $req['user'] );
			return as_group_payload( $group_id );
		},
		'permission_callback' => 'as_rest_can_manage_group',
	] );

	register_rest_route( AS_REST_NS, "/groups/{$id}/draw", [
		[
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => function ( $req ) {
				$result = as_run_draw( (int) $req['id'], (bool) ( $req['notify'] ?? true ) );
				return is_wp_error( $result ) ? $result : as_group_payload( (int) $req['id'] );
			},
			'permission_callback' => 'as_rest_can_manage_group',
		],
		[
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => function ( $req ) {
				as_reset_draw( (int) $req['id'] );
				return as_group_payload( (int) $req['id'] );
			},
			'permission_callback' => 'as_rest_can_manage_group',
		],
	] );

	register_rest_route( AS_REST_NS, "/groups/{$id}/reveal", [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => function ( $req ) {
			$group_id = (int) $req['id'];
			$match    = as_group_match_for( $group_id, get_current_user_id() );
			if ( ! $match ) {
				return new WP_Error( 'as_no_match', __( 'O sorteio ainda não foi feito.', 'amigosecreto' ), [ 'status' => 404 ] );
			}
			as_mark_revealed( $group_id, get_current_user_id() );
			return [ 'match' => as_user_payload( $match ) ];
		},
		'permission_callback' => fn( $req ) => as_group_has_participant( (int) $req['id'], get_current_user_id() ),
	] );

	/* ---------- Lista de presentes ---------- */

	register_rest_route( AS_REST_NS, '/wishlist', [
		[
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => fn() => array_map( 'as_gift_payload', as_user_gifts( get_current_user_id() ) ),
			'permission_callback' => 'is_user_logged_in',
		],
		[
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => function ( $req ) {
				$gift = as_save_gift( get_current_user_id(), $req->get_params() );
				return is_wp_error( $gift ) ? $gift : as_gift_payload( $gift );
			},
			'permission_callback' => 'is_user_logged_in',
		],
	] );

	register_rest_route( AS_REST_NS, '/wishlist/order', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => function ( $req ) {
			$user_id = get_current_user_id();
			foreach ( array_values( (array) $req['ids'] ) as $position => $gift_id ) {
				$gift = as_get_gift( $gift_id );
				if ( $gift && (int) $gift->post_author === $user_id ) {
					wp_update_post( [ 'ID' => $gift->ID, 'menu_order' => $position ] );
				}
			}
			return array_map( 'as_gift_payload', as_user_gifts( $user_id ) );
		},
		'permission_callback' => 'is_user_logged_in',
		'args'                => [ 'ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'required' => true ] ],
	] );

	register_rest_route( AS_REST_NS, "/wishlist/{$id}", [
		[
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => function ( $req ) {
				$gift = as_save_gift( get_current_user_id(), $req->get_params(), (int) $req['id'] );
				return is_wp_error( $gift ) ? $gift : as_gift_payload( $gift );
			},
			'permission_callback' => 'as_rest_owns_gift',
		],
		[
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => function ( $req ) {
				wp_delete_post( (int) $req['id'], true );
				return [ 'deleted' => true ];
			},
			'permission_callback' => 'as_rest_owns_gift',
		],
	] );

	register_rest_route( AS_REST_NS, "/users/{$id}/wishlist", [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => fn( $req ) => array_map( 'as_gift_payload', as_user_gifts( (int) $req['id'] ) ),
		'permission_callback' => fn( $req ) => as_can_view_wishlist( (int) $req['id'] ),
	] );

	/* ---------- Perfil ---------- */

	register_rest_route( AS_REST_NS, '/me', [
		'methods'             => WP_REST_Server::EDITABLE,
		'callback'            => 'as_rest_update_me',
		'permission_callback' => 'is_user_logged_in',
	] );

	register_rest_route( AS_REST_NS, '/me/avatar', [
		[
			// multipart/form-data com o arquivo no campo "avatar".
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => function () {
				$url = as_upload_avatar( get_current_user_id(), 'avatar' );
				return is_wp_error( $url ) ? $url : as_me_payload( get_current_user_id() );
			},
			'permission_callback' => 'is_user_logged_in',
		],
		[
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => function () {
				as_delete_avatar( get_current_user_id() );
				return as_me_payload( get_current_user_id() );
			},
			'permission_callback' => 'is_user_logged_in',
		],
	] );

	register_rest_route( AS_REST_NS, '/me/social/(?P<provider>google|facebook)', [
		'methods'             => WP_REST_Server::DELETABLE,
		'callback'            => function ( $req ) {
			// Sem risco de ficar trancado fora: "Esqueci a senha" sempre funciona pelo e-mail.
			as_social_unlink( get_current_user_id(), $req['provider'] );
			return as_me_payload( get_current_user_id() );
		},
		'permission_callback' => 'is_user_logged_in',
	] );
} );

function as_rest_can_manage_group( WP_REST_Request $req ) {
	return as_user_manages_group( (int) $req['id'] );
}

function as_rest_owns_gift( WP_REST_Request $req ) {
	$gift = as_get_gift( (int) $req['id'] );
	return $gift && (int) $gift->post_author === get_current_user_id();
}

function as_rest_create_group( WP_REST_Request $req ) {
	$title = sanitize_text_field( $req['title'] );
	if ( '' === $title ) {
		return new WP_Error( 'as_group_title', __( 'Dê um nome ao grupo.', 'amigosecreto' ), [ 'status' => 400 ] );
	}

	$group_id = wp_insert_post( [
		'post_type'    => 'as_grupo',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_content' => sanitize_textarea_field( $req['description'] ?? '' ),
		'post_author'  => get_current_user_id(),
	], true );

	if ( is_wp_error( $group_id ) ) {
		return $group_id;
	}

	as_save_group_fields( $group_id, $req->get_params() );

	// O organizador participa do sorteio por padrão.
	if ( false !== $req['joinAsParticipant'] ) {
		as_group_add_participant( $group_id, get_current_user_id() );
	}

	$response = rest_ensure_response( as_group_payload( $group_id ) );
	$response->set_status( 201 );
	return $response;
}

function as_rest_update_group( WP_REST_Request $req ) {
	$group_id = (int) $req['id'];
	$postarr  = [ 'ID' => $group_id ];

	if ( isset( $req['title'] ) ) {
		$title = sanitize_text_field( $req['title'] );
		if ( '' === $title ) {
			return new WP_Error( 'as_group_title', __( 'Dê um nome ao grupo.', 'amigosecreto' ), [ 'status' => 400 ] );
		}
		$postarr['post_title'] = $title;
	}
	if ( isset( $req['description'] ) ) {
		$postarr['post_content'] = sanitize_textarea_field( $req['description'] );
	}

	if ( count( $postarr ) > 1 ) {
		wp_update_post( $postarr );
	}

	as_save_group_fields( $group_id, $req->get_params() );

	// Organizador pode entrar/sair do próprio sorteio enquanto ele não acontece.
	if ( isset( $req['joinAsParticipant'] ) && ! as_group_is_drawn( $group_id ) ) {
		$req['joinAsParticipant']
			? as_group_add_participant( $group_id, get_current_user_id() )
			: as_group_remove_participant( $group_id, get_current_user_id() );
	}

	return as_group_payload( $group_id );
}

function as_rest_update_me( WP_REST_Request $req ) {
	$user    = wp_get_current_user();
	$userarr = [ 'ID' => $user->ID ];

	$name = sanitize_text_field( $req['name'] ?? '' );
	if ( '' !== $name ) {
		$userarr['display_name'] = $name;
		$userarr['first_name']   = $name;
	}

	$email        = sanitize_email( $req['email'] ?? '' );
	$new_password = (string) ( $req['newPassword'] ?? '' );
	$changing     = ( $email && strtolower( $email ) !== strtolower( $user->user_email ) ) || '' !== $new_password;

	// Quem entrou só por Google/Facebook (ou convite) ainda não tem senha para confirmar.
	if ( $changing && ! as_user_needs_password( $user->ID ) && ! wp_check_password( (string) ( $req['currentPassword'] ?? '' ), $user->user_pass, $user->ID ) ) {
		return new WP_Error( 'as_wrong_password', __( 'Senha atual incorreta.', 'amigosecreto' ), [ 'status' => 403 ] );
	}

	if ( isset( $req['birthdate'] ) ) {
		$birthdate = sanitize_text_field( (string) $req['birthdate'] );
		if ( '' !== $birthdate && ! as_sanitize_birthdate( $birthdate ) ) {
			return new WP_Error( 'as_birthdate', __( 'Data de nascimento inválida.', 'amigosecreto' ), [ 'status' => 400 ] );
		}
	}

	if ( $email && strtolower( $email ) !== strtolower( $user->user_email ) ) {
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'as_invalid_email', __( 'Informe um e-mail válido.', 'amigosecreto' ), [ 'status' => 400 ] );
		}
		if ( email_exists( $email ) ) {
			return new WP_Error( 'as_email_exists', __( 'Esse e-mail já está em uso.', 'amigosecreto' ), [ 'status' => 409 ] );
		}
		$userarr['user_email'] = $email;
	}

	if ( '' !== $new_password ) {
		if ( strlen( $new_password ) < 8 ) {
			return new WP_Error( 'as_weak_password', __( 'A nova senha precisa ter pelo menos 8 caracteres.', 'amigosecreto' ), [ 'status' => 400 ] );
		}
		$userarr['user_pass'] = $new_password;
	}

	$result = wp_update_user( $userarr );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	if ( isset( $userarr['user_pass'] ) ) {
		as_set_needs_password( $user->ID, false );
	}
	if ( isset( $req['birthdate'] ) ) {
		as_set_birthdate( $user->ID, $req['birthdate'] );
	}

	return as_me_payload( $user->ID );
}

/**
 * Dados do perfil do usuário logado (página /perfil/ e respostas da API).
 */
function as_me_payload( $user_id ) {
	$user      = get_userdata( (int) $user_id );
	$linked    = as_social_linked( $user->ID );
	$providers = [];

	foreach ( as_social_providers() as $slug => $label ) {
		if ( as_social_enabled( $slug ) || $linked[ $slug ] ) {
			$providers[] = [
				'slug'       => $slug,
				'label'      => $label,
				'linked'     => $linked[ $slug ],
				'enabled'    => as_social_enabled( $slug ),
				// Facebook conecta por redirecionamento; Google pelo botão oficial (src/js/lib/google.js).
				'connectUrl' => 'facebook' === $slug && as_social_enabled( $slug ) ? as_social_start_url( $slug ) : null,
			];
		}
	}

	return [
		'name'          => $user->display_name,
		'email'         => $user->user_email,
		'initials'      => as_initials( $user->display_name ),
		'avatar'        => as_get_avatar_url( $user->ID ) ?: null,
		'birthdate'     => as_get_birthdate( $user->ID ),
		'needsPassword' => as_user_needs_password( $user->ID ),
		'providers'     => $providers,
	];
}
