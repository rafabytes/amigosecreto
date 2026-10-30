<?php
/**
 * Telas do wp-admin (apenas administradores acessam): colunas e resumo dos grupos.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'manage_as_grupo_posts_columns', function ( $columns ) {
	$date = $columns['date'] ?? null;
	unset( $columns['date'] );
	$columns['as_organizer']   = __( 'Organizador', 'amigosecreto' );
	$columns['as_participants'] = __( 'Participantes', 'amigosecreto' );
	$columns['as_status']      = __( 'Sorteio', 'amigosecreto' );
	$columns['as_event']       = __( 'Data da troca', 'amigosecreto' );
	if ( $date ) {
		$columns['date'] = $date;
	}
	return $columns;
} );

add_action( 'manage_as_grupo_posts_custom_column', function ( $column, $post_id ) {
	switch ( $column ) {
		case 'as_organizer':
			$author = get_userdata( (int) get_post_field( 'post_author', $post_id ) );
			echo $author ? esc_html( $author->display_name ) : '—';
			break;
		case 'as_participants':
			echo (int) count( as_group_participants( $post_id ) );
			break;
		case 'as_status':
			echo as_group_is_drawn( $post_id ) ? '✅ ' . esc_html__( 'Realizado', 'amigosecreto' ) : '⏳ ' . esc_html__( 'Pendente', 'amigosecreto' );
			break;
		case 'as_event':
			echo esc_html( as_format_date( get_post_meta( $post_id, '_as_event_date', true ) ) ?: '—' );
			break;
	}
}, 10, 2 );

add_action( 'add_meta_boxes_as_grupo', function () {
	add_meta_box( 'as_group_summary', __( 'Participantes e sorteio', 'amigosecreto' ), function ( $post ) {
		$draw = as_group_draw( $post->ID );
		echo '<p><a class="button" href="' . esc_url( as_url( 'grupos', $post->ID ) ) . '" target="_blank">' . esc_html__( 'Abrir no site', 'amigosecreto' ) . '</a></p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Participante', 'amigosecreto' ) . '</th><th>E-mail</th><th>' . esc_html__( 'Tirou', 'amigosecreto' ) . '</th></tr></thead><tbody>';
		foreach ( as_group_participants( $post->ID ) as $user_id ) {
			$user  = get_userdata( $user_id );
			$match = isset( $draw[ $user_id ] ) ? get_userdata( $draw[ $user_id ] ) : null;
			if ( ! $user ) {
				continue;
			}
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $user->display_name ),
				esc_html( $user->user_email ),
				$match ? esc_html( $match->display_name ) : '—'
			);
		}
		echo '</tbody></table>';
	}, 'as_grupo', 'normal' );
} );

add_filter( 'manage_as_presente_posts_columns', function ( $columns ) {
	$columns['as_link'] = __( 'Link', 'amigosecreto' );
	return $columns;
} );

add_action( 'manage_as_presente_posts_custom_column', function ( $column, $post_id ) {
	if ( 'as_link' === $column ) {
		$url = get_post_meta( $post_id, '_as_url', true );
		echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( as_link_host( $url ) ) . '</a>' : '—';
	}
}, 10, 2 );
