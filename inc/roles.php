<?php
/**
 * Papéis de usuário:
 *  - administrator: gerencia o site (papel nativo + capacidade de organizar) e vê o perfil e o
 *                   histórico de atividade de todos os usuários (/usuarios/).
 *  - organizador:   conta criada pelo cadastro ou pelo login social.
 *  - participante:  conta criada quando alguém adiciona o e-mail a um sorteio.
 *
 * Organizador e participante podem fazer as mesmas coisas: participar de sorteios, manter a lista de
 * presentes e criar os próprios sorteios, dos quais passam a ser os organizadores
 * (as_user_manages_group). O papel só registra como a conta surgiu.
 */

defined( 'ABSPATH' ) || exit;

const AS_ROLES_VERSION = '2';
const AS_CAP_ORGANIZE  = 'as_organize_groups';

function as_register_roles() {
	add_role( 'organizador', __( 'Organizador', 'amigosecreto' ), [
		'read'          => true,
		AS_CAP_ORGANIZE => true,
	] );

	add_role( 'participante', __( 'Participante', 'amigosecreto' ), [
		'read'          => true,
		AS_CAP_ORGANIZE => true,
	] );

	// add_role() não altera papéis que já existem: garante a capacidade em sites já instalados.
	foreach ( [ 'administrator', 'organizador', 'participante' ] as $as_role_name ) {
		$as_role = get_role( $as_role_name );
		if ( $as_role ) {
			$as_role->add_cap( AS_CAP_ORGANIZE );
		}
	}

	update_option( 'as_roles_version', AS_ROLES_VERSION );
}

add_action( 'after_switch_theme', 'as_register_roles' );

// Garante os papéis caso o tema tenha sido ativado antes deste arquivo existir (ou mudado).
add_action( 'init', function () {
	if ( get_option( 'as_roles_version' ) !== AS_ROLES_VERSION ) {
		as_register_roles();
	}
}, 1 );

function as_user_can_organize( $user_id = 0 ) {
	$user_id = $user_id ?: get_current_user_id();
	return $user_id && user_can( $user_id, AS_CAP_ORGANIZE );
}

function as_user_is_admin( $user_id = 0 ) {
	$user_id = $user_id ?: get_current_user_id();
	return $user_id && user_can( $user_id, 'manage_options' );
}

/**
 * Nome do papel para exibição (ex.: "Participante").
 */
function as_role_label( WP_User $user ) {
	$names = array_map(
		fn( $role ) => translate_user_role( wp_roles()->roles[ $role ]['name'] ?? $role ),
		$user->roles
	);
	return implode( ', ', $names ) ?: '—';
}
