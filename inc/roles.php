<?php
/**
 * Papéis de usuário:
 *  - administrator: gerencia o site (papel nativo + capacidade de organizar).
 *  - organizador:   cria e gerencia grupos; também pode participar e ter lista de presentes.
 *  - participante:  cadastrado por e-mail pelo organizador; mantém lista de presentes e vê a dos outros.
 */

defined( 'ABSPATH' ) || exit;

const AS_ROLES_VERSION = '1';
const AS_CAP_ORGANIZE  = 'as_organize_groups';

function as_register_roles() {
	add_role( 'organizador', __( 'Organizador', 'amigosecreto' ), [
		'read'          => true,
		AS_CAP_ORGANIZE => true,
	] );

	add_role( 'participante', __( 'Participante', 'amigosecreto' ), [
		'read' => true,
	] );

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( AS_CAP_ORGANIZE );
	}

	update_option( 'as_roles_version', AS_ROLES_VERSION );
}

add_action( 'after_switch_theme', 'as_register_roles' );

// Garante os papéis caso o tema tenha sido ativado antes deste arquivo existir.
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
