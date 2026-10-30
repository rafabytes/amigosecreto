<?php
/**
 * Regras de negócio dos grupos: participantes, permissões e sorteio.
 */

defined( 'ABSPATH' ) || exit;

const AS_MIN_PARTICIPANTS = 3;

function as_get_group( $group_id ) {
	$post = get_post( (int) $group_id );
	return ( $post && 'as_grupo' === $post->post_type && 'trash' !== $post->post_status ) ? $post : null;
}

/**
 * IDs dos participantes do grupo.
 *
 * @return int[]
 */
function as_group_participants( $group_id ) {
	$ids = array_map( 'intval', get_post_meta( $group_id, '_as_participant', false ) );
	return array_values( array_unique( array_filter( $ids ) ) );
}

function as_group_has_participant( $group_id, $user_id ) {
	return in_array( (int) $user_id, as_group_participants( $group_id ), true );
}

function as_group_add_participant( $group_id, $user_id ) {
	if ( ! as_group_has_participant( $group_id, $user_id ) ) {
		add_post_meta( $group_id, '_as_participant', (int) $user_id );
	}
}

function as_group_remove_participant( $group_id, $user_id ) {
	delete_post_meta( $group_id, '_as_participant', (int) $user_id );
}

/**
 * O usuário administra o grupo? (organizador dono ou admin do site)
 */
function as_user_manages_group( $group_id, $user_id = 0 ) {
	$user_id = $user_id ?: get_current_user_id();
	$group   = as_get_group( $group_id );
	if ( ! $group || ! $user_id ) {
		return false;
	}
	return as_user_is_admin( $user_id ) || (int) $group->post_author === (int) $user_id;
}

/**
 * O usuário pode ver o grupo? (participante, organizador ou admin)
 */
function as_user_can_view_group( $group_id, $user_id = 0 ) {
	$user_id = $user_id ?: get_current_user_id();
	return as_user_manages_group( $group_id, $user_id ) || as_group_has_participant( $group_id, $user_id );
}

/**
 * Grupos em que o usuário participa ou que organiza.
 *
 * @return WP_Post[]
 */
function as_user_groups( $user_id = 0 ) {
	$user_id = $user_id ?: get_current_user_id();
	if ( ! $user_id ) {
		return [];
	}

	$common = [
		'post_type'      => 'as_grupo',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	];

	$owned  = get_posts( $common + [ 'author' => $user_id ] );
	$joined = get_posts( $common + [
		'meta_query' => [ [ 'key' => '_as_participant', 'value' => $user_id, 'type' => 'NUMERIC' ] ],
	] );

	$ids = array_unique( array_merge( $owned, $joined ) );
	if ( ! $ids ) {
		return [];
	}

	return get_posts( [
		'post_type'      => 'as_grupo',
		'post__in'       => $ids,
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	] );
}

/**
 * Dois usuários compartilham algum grupo? Usado para liberar a visualização de listas.
 */
function as_users_share_group( $user_a, $user_b ) {
	if ( (int) $user_a === (int) $user_b ) {
		return true;
	}
	foreach ( as_user_groups( $user_a ) as $group ) {
		$members   = as_group_participants( $group->ID );
		$members[] = (int) $group->post_author;
		if ( in_array( (int) $user_b, $members, true ) ) {
			return true;
		}
	}
	return false;
}

/* -------------------------------------------------------------------------
 * Sorteio
 * ---------------------------------------------------------------------- */

function as_group_draw( $group_id ) {
	$draw = get_post_meta( $group_id, '_as_draw', true );
	return is_array( $draw ) ? array_map( 'intval', $draw ) : [];
}

function as_group_is_drawn( $group_id ) {
	return (bool) as_group_draw( $group_id );
}

/**
 * Quem o usuário tirou no grupo (ou null).
 */
function as_group_match_for( $group_id, $user_id ) {
	$draw = as_group_draw( $group_id );
	return $draw[ (int) $user_id ] ?? null;
}

/**
 * Gera um sorteio válido: ninguém tira a si mesmo e todos formam um único ciclo
 * (A → B → C → … → A), o que garante que cada pessoa dá e recebe exatamente um presente.
 *
 * @param int[] $participants
 * @return array<int,int> giver_id => receiver_id
 */
function as_generate_draw( array $participants ) {
	$participants = array_values( array_unique( array_map( 'intval', $participants ) ) );
	$count        = count( $participants );

	// Embaralhamento criptograficamente seguro (Fisher–Yates).
	for ( $i = $count - 1; $i > 0; $i-- ) {
		$j = random_int( 0, $i );
		[ $participants[ $i ], $participants[ $j ] ] = [ $participants[ $j ], $participants[ $i ] ];
	}

	$draw = [];
	foreach ( $participants as $index => $giver ) {
		$draw[ $giver ] = $participants[ ( $index + 1 ) % $count ];
	}
	return $draw;
}

/**
 * Executa o sorteio do grupo e notifica os participantes por e-mail.
 *
 * @return true|WP_Error
 */
function as_run_draw( $group_id, $notify = true ) {
	$participants = as_group_participants( $group_id );

	if ( count( $participants ) < AS_MIN_PARTICIPANTS ) {
		return new WP_Error(
			'as_not_enough',
			sprintf(
				/* translators: %d: número mínimo de participantes */
				__( 'O sorteio precisa de pelo menos %d participantes.', 'amigosecreto' ),
				AS_MIN_PARTICIPANTS
			),
			[ 'status' => 400 ]
		);
	}

	$draw = as_generate_draw( $participants );

	update_post_meta( $group_id, '_as_draw', $draw );
	update_post_meta( $group_id, '_as_drawn_at', time() );
	delete_post_meta( $group_id, '_as_revealed' );

	if ( $notify ) {
		as_email_draw_results( $group_id );
	}

	do_action( 'as_group_drawn', $group_id, $draw );

	return true;
}

function as_reset_draw( $group_id ) {
	delete_post_meta( $group_id, '_as_draw' );
	delete_post_meta( $group_id, '_as_drawn_at' );
	delete_post_meta( $group_id, '_as_revealed' );
}

/**
 * Marca que o participante já abriu o resultado (o organizador vê o progresso, não o par).
 */
function as_mark_revealed( $group_id, $user_id ) {
	$revealed = array_map( 'intval', get_post_meta( $group_id, '_as_revealed', false ) );
	if ( ! in_array( (int) $user_id, $revealed, true ) ) {
		add_post_meta( $group_id, '_as_revealed', (int) $user_id );
	}
}

/* -------------------------------------------------------------------------
 * Participantes por e-mail
 * ---------------------------------------------------------------------- */

/**
 * Encontra ou cria o usuário pelo e-mail e o adiciona ao grupo.
 * Usuários novos recebem o papel "participante" e um e-mail para definir senha.
 *
 * @return WP_User|WP_Error
 */
function as_invite_participant( $group_id, $email, $name = '' ) {
	$email = sanitize_email( $email );
	$name  = sanitize_text_field( $name );

	if ( ! is_email( $email ) ) {
		return new WP_Error( 'as_invalid_email', __( 'Informe um e-mail válido.', 'amigosecreto' ), [ 'status' => 400 ] );
	}

	if ( as_group_is_drawn( $group_id ) ) {
		return new WP_Error( 'as_already_drawn', __( 'O sorteio já foi feito. Refaça o sorteio para alterar participantes.', 'amigosecreto' ), [ 'status' => 409 ] );
	}

	$user = get_user_by( 'email', $email );

	if ( ! $user ) {
		$user_id = as_create_user( $email, $name, 'participante' );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		$user = get_userdata( $user_id );
	}

	if ( as_group_has_participant( $group_id, $user->ID ) ) {
		return new WP_Error( 'as_duplicate', __( 'Essa pessoa já está no grupo.', 'amigosecreto' ), [ 'status' => 409 ] );
	}

	as_group_add_participant( $group_id, $user->ID );
	as_email_invite( $group_id, $user );

	return $user;
}

/* -------------------------------------------------------------------------
 * Payload para templates e API
 * ---------------------------------------------------------------------- */

function as_group_payload( $group, $viewer_id = 0 ) {
	$group     = $group instanceof WP_Post ? $group : as_get_group( $group );
	$viewer_id = $viewer_id ?: get_current_user_id();
	$manages   = as_user_manages_group( $group->ID, $viewer_id );
	$drawn     = as_group_is_drawn( $group->ID );
	$revealed  = array_map( 'intval', get_post_meta( $group->ID, '_as_revealed', false ) );

	$participants = array_values( array_filter( array_map( function ( $id ) use ( $manages, $drawn, $revealed ) {
		$data = as_user_payload( $id );
		if ( $data && $manages ) {
			$user          = get_userdata( $id );
			$data['email'] = $user->user_email;
			if ( $drawn ) {
				$data['revealed'] = in_array( (int) $id, $revealed, true );
			}
		}
		return $data;
	}, as_group_participants( $group->ID ) ) ) );

	usort( $participants, fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );

	$match_id = $drawn ? as_group_match_for( $group->ID, $viewer_id ) : null;
	$date     = get_post_meta( $group->ID, '_as_event_date', true );

	return [
		'id'            => $group->ID,
		'title'         => get_the_title( $group ),
		'description'   => $group->post_content,
		'eventDate'     => $date,
		'eventDateText' => as_format_date( $date ),
		'location'      => (string) get_post_meta( $group->ID, '_as_location', true ),
		'budget'        => (string) get_post_meta( $group->ID, '_as_budget', true ),
		'url'           => as_url( 'grupos', $group->ID ),
		'organizer'     => as_user_payload( (int) $group->post_author ),
		'participants'  => $participants,
		'isManager'     => $manages,
		'isParticipant' => as_group_has_participant( $group->ID, $viewer_id ),
		'isDrawn'       => $drawn,
		'drawnAt'       => $drawn ? (int) get_post_meta( $group->ID, '_as_drawn_at', true ) : null,
		'myMatch'       => $match_id ? as_user_payload( $match_id ) : null,
		'minParticipants' => AS_MIN_PARTICIPANTS,
	];
}

function as_save_group_fields( $group_id, array $data ) {
	if ( array_key_exists( 'eventDate', $data ) ) {
		update_post_meta( $group_id, '_as_event_date', as_sanitize_date( $data['eventDate'] ) );
	}
	if ( array_key_exists( 'location', $data ) ) {
		update_post_meta( $group_id, '_as_location', sanitize_text_field( $data['location'] ) );
	}
	if ( array_key_exists( 'budget', $data ) ) {
		update_post_meta( $group_id, '_as_budget', sanitize_text_field( $data['budget'] ) );
	}
}

// Limpa metadados de participação quando um usuário é removido do site.
add_action( 'deleted_user', function ( $user_id ) {
	delete_metadata( 'post', 0, '_as_participant', (int) $user_id, true );
} );
