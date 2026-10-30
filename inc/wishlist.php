<?php
/**
 * Lista de presentes: cada usuário tem uma única lista, usada em todos os grupos de que participa.
 */

defined( 'ABSPATH' ) || exit;

const AS_MAX_GIFTS = 50;

/**
 * @return WP_Post[]
 */
function as_user_gifts( $user_id ) {
	return get_posts( [
		'post_type'      => 'as_presente',
		'post_status'    => 'publish',
		'author'         => (int) $user_id,
		'posts_per_page' => AS_MAX_GIFTS,
		'orderby'        => [ 'menu_order' => 'ASC', 'date' => 'ASC' ],
		'no_found_rows'  => true,
	] );
}

function as_count_gifts( $user_id ) {
	$query = new WP_Query( [
		'post_type'      => 'as_presente',
		'post_status'    => 'publish',
		'author'         => (int) $user_id,
		'posts_per_page' => 1,
		'fields'         => 'ids',
	] );
	return (int) $query->found_posts;
}

function as_get_gift( $gift_id ) {
	$post = get_post( (int) $gift_id );
	return ( $post && 'as_presente' === $post->post_type && 'publish' === $post->post_status ) ? $post : null;
}

function as_gift_payload( $gift ) {
	$url = (string) get_post_meta( $gift->ID, '_as_url', true );
	return [
		'id'    => $gift->ID,
		'title' => get_the_title( $gift ),
		'note'  => $gift->post_content,
		'url'   => $url,
		'host'  => as_link_host( $url ),
		'price' => (string) get_post_meta( $gift->ID, '_as_price', true ),
		'order' => (int) $gift->menu_order,
	];
}

/**
 * Cria ou atualiza um presente do usuário.
 *
 * @return WP_Post|WP_Error
 */
function as_save_gift( $user_id, array $data, $gift_id = 0 ) {
	$title = sanitize_text_field( $data['title'] ?? '' );
	if ( '' === $title ) {
		return new WP_Error( 'as_gift_title', __( 'Dê um nome ao presente.', 'amigosecreto' ), [ 'status' => 400 ] );
	}

	if ( ! $gift_id && as_count_gifts( $user_id ) >= AS_MAX_GIFTS ) {
		return new WP_Error( 'as_gift_limit', __( 'Você atingiu o limite de itens na lista.', 'amigosecreto' ), [ 'status' => 400 ] );
	}

	$postarr = [
		'post_type'    => 'as_presente',
		'post_status'  => 'publish',
		'post_author'  => (int) $user_id,
		'post_title'   => $title,
		'post_content' => sanitize_textarea_field( $data['note'] ?? '' ),
	];

	if ( $gift_id ) {
		$postarr['ID'] = (int) $gift_id;
	} else {
		$postarr['menu_order'] = as_count_gifts( $user_id );
	}

	$id = $gift_id ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	update_post_meta( $id, '_as_url', as_sanitize_link( $data['url'] ?? '' ) );
	update_post_meta( $id, '_as_price', sanitize_text_field( $data['price'] ?? '' ) );

	return get_post( $id );
}

/**
 * O visitante atual pode ver a lista do usuário informado?
 */
function as_can_view_wishlist( $owner_id, $viewer_id = 0 ) {
	$viewer_id = $viewer_id ?: get_current_user_id();
	if ( ! $viewer_id ) {
		return false;
	}
	return as_user_is_admin( $viewer_id ) || as_users_share_group( $viewer_id, $owner_id );
}

// Remove os presentes quando o usuário é excluído.
add_action( 'delete_user', function ( $user_id ) {
	foreach ( as_user_gifts( $user_id ) as $gift ) {
		wp_delete_post( $gift->ID, true );
	}
} );
