<?php
/**
 * Dados opcionais do perfil: foto e data de nascimento.
 * Também controla contas que ainda não definiram senha (criadas por convite, cadastro ou login social).
 */

defined( 'ABSPATH' ) || exit;

const AS_AVATAR_MAX_BYTES = 5 * MB_IN_BYTES;

add_action( 'after_setup_theme', function () {
	add_image_size( 'as_avatar', 256, 256, true );
} );

/* -------------------------------------------------------------------------
 * Senha pendente
 * ---------------------------------------------------------------------- */

/**
 * A conta ainda não tem uma senha escolhida pelo usuário?
 */
function as_user_needs_password( $user_id ) {
	return (bool) get_user_meta( (int) $user_id, 'as_needs_password', true );
}

function as_set_needs_password( $user_id, $needs = true ) {
	$needs
		? update_user_meta( (int) $user_id, 'as_needs_password', 1 )
		: delete_user_meta( (int) $user_id, 'as_needs_password' );
}

// Ao criar a senha pelo link do e-mail (wp-login.php?action=rp).
add_action( 'after_password_reset', function ( $user ) {
	as_set_needs_password( $user->ID, false );
} );

/**
 * Cria uma conta sem senha escolhida (convite, cadastro ou login social).
 * O login (user_login) é derivado do e-mail; as pessoas entram sempre pelo e-mail.
 *
 * @return int|WP_Error
 */
function as_create_user( $email, $name, $role ) {
	$base  = sanitize_user( current( explode( '@', $email ) ), true ) ?: 'usuario';
	$login = $base;
	$i     = 1;
	while ( username_exists( $login ) ) {
		$login = $base . ++$i;
	}

	$user_id = wp_insert_user( [
		'user_login'   => $login,
		'user_email'   => $email,
		'user_pass'    => wp_generate_password( 24 ),
		'display_name' => $name ?: $base,
		'first_name'   => $name,
		'role'         => $role,
	] );

	if ( ! is_wp_error( $user_id ) ) {
		as_set_needs_password( $user_id );
	}

	return $user_id;
}

/**
 * Link para o usuário criar/redefinir a senha (também confirma o e-mail).
 */
function as_set_password_url( WP_User $user ) {
	$key = get_password_reset_key( $user );
	if ( is_wp_error( $key ) ) {
		return '';
	}
	return network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );
}

/* -------------------------------------------------------------------------
 * Data de nascimento (Y-m-d)
 * ---------------------------------------------------------------------- */

function as_get_birthdate( $user_id ) {
	return (string) get_user_meta( (int) $user_id, 'as_birthdate', true );
}

/**
 * Valida e normaliza uma data de nascimento. Retorna '' se inválida.
 */
function as_sanitize_birthdate( $value ) {
	$date = as_sanitize_date( $value );
	if ( ! $date ) {
		return '';
	}
	[ $y, $m, $d ] = array_map( 'intval', explode( '-', $date ) );
	if ( ! checkdate( $m, $d, $y ) || $y < 1900 || $date > wp_date( 'Y-m-d' ) ) {
		return '';
	}
	return $date;
}

function as_set_birthdate( $user_id, $value ) {
	$date = as_sanitize_birthdate( $value );
	$date
		? update_user_meta( (int) $user_id, 'as_birthdate', $date )
		: delete_user_meta( (int) $user_id, 'as_birthdate' );
	return $date;
}

/**
 * "12 de março" — só dia e mês, para exibir aos outros participantes.
 */
function as_format_birthday( $ymd ) {
	if ( ! $ymd ) {
		return '';
	}
	$ts = strtotime( $ymd . ' 12:00:00' );
	return $ts ? date_i18n( 'j \d\e F', $ts ) : '';
}

/* -------------------------------------------------------------------------
 * Foto de perfil (anexo na biblioteca de mídia)
 * ---------------------------------------------------------------------- */

function as_get_avatar_url( $user_id ) {
	$attachment_id = (int) get_user_meta( (int) $user_id, 'as_avatar_id', true );
	if ( ! $attachment_id ) {
		return '';
	}
	return (string) wp_get_attachment_image_url( $attachment_id, 'as_avatar' );
}

function as_has_avatar( $user_id ) {
	return '' !== as_get_avatar_url( $user_id );
}

function as_set_avatar_attachment( $user_id, $attachment_id ) {
	as_delete_avatar( $user_id );
	update_user_meta( (int) $user_id, 'as_avatar_id', (int) $attachment_id );
}

function as_delete_avatar( $user_id ) {
	$old = (int) get_user_meta( (int) $user_id, 'as_avatar_id', true );
	if ( $old ) {
		wp_delete_attachment( $old, true );
	}
	delete_user_meta( (int) $user_id, 'as_avatar_id' );
}

function as_avatar_mimes() {
	return [
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
		'gif'          => 'image/gif',
	];
}

function as_require_media_functions() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
}

/**
 * Salva a foto enviada por formulário ($_FILES[ $field ]).
 *
 * @return string|WP_Error URL da nova foto.
 */
function as_upload_avatar( $user_id, $field ) {
	$file = $_FILES[ $field ] ?? null; // phpcs:ignore
	if ( ! $file || UPLOAD_ERR_NO_FILE === ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
		return new WP_Error( 'as_no_file', __( 'Escolha uma imagem.', 'amigosecreto' ), [ 'status' => 400 ] );
	}
	if ( ( $file['size'] ?? 0 ) > AS_AVATAR_MAX_BYTES ) {
		return new WP_Error( 'as_file_size', __( 'A imagem pode ter no máximo 5 MB.', 'amigosecreto' ), [ 'status' => 400 ] );
	}

	as_require_media_functions();

	$attachment_id = media_handle_upload( $field, 0, [ 'post_author' => (int) $user_id ], [
		'test_form' => false,
		'mimes'     => as_avatar_mimes(),
	] );

	if ( is_wp_error( $attachment_id ) ) {
		return new WP_Error( 'as_upload', __( 'Não foi possível salvar a imagem. Use JPG, PNG, WebP ou GIF.', 'amigosecreto' ), [ 'status' => 400 ] );
	}

	as_set_avatar_attachment( $user_id, $attachment_id );
	return as_get_avatar_url( $user_id );
}

/**
 * Baixa a foto de um provedor (Google/Facebook) para a biblioteca de mídia.
 * As URLs desses provedores expiram ou mudam, então guardamos uma cópia.
 *
 * @return string|WP_Error
 */
function as_avatar_from_url( $user_id, $url ) {
	as_require_media_functions();

	$tmp = download_url( esc_url_raw( $url ), 15 );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}

	// URLs de provedores não têm extensão: descobre pelo conteúdo.
	$info = @getimagesize( $tmp ); // phpcs:ignore
	$ext  = $info ? image_type_to_extension( $info[2], false ) : '';
	if ( ! in_array( $ext, [ 'jpeg', 'png', 'webp', 'gif' ], true ) || filesize( $tmp ) > AS_AVATAR_MAX_BYTES ) {
		wp_delete_file( $tmp );
		return new WP_Error( 'as_avatar_type', 'Imagem inválida.' );
	}

	$attachment_id = media_handle_sideload(
		[ 'name' => 'avatar-' . (int) $user_id . '.' . ( 'jpeg' === $ext ? 'jpg' : $ext ), 'tmp_name' => $tmp ],
		0,
		null,
		[ 'post_author' => (int) $user_id ]
	);

	if ( is_wp_error( $attachment_id ) ) {
		wp_delete_file( $tmp );
		return $attachment_id;
	}

	as_set_avatar_attachment( $user_id, $attachment_id );
	return as_get_avatar_url( $user_id );
}

// get_avatar()/get_avatar_url() passam a usar a foto enviada.
add_filter( 'pre_get_avatar_data', function ( $args, $id_or_email ) {
	$user_id = 0;
	if ( is_numeric( $id_or_email ) ) {
		$user_id = (int) $id_or_email;
	} elseif ( $id_or_email instanceof WP_User ) {
		$user_id = $id_or_email->ID;
	} elseif ( is_string( $id_or_email ) && ( $user = get_user_by( 'email', $id_or_email ) ) ) {
		$user_id = $user->ID;
	}

	$url = $user_id ? as_get_avatar_url( $user_id ) : '';
	if ( $url ) {
		$args['url']          = $url;
		$args['found_avatar'] = true;
	}
	return $args;
}, 10, 2 );

// Remove a foto quando o usuário é excluído.
add_action( 'delete_user', 'as_delete_avatar' );
