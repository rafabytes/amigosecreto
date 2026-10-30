<?php
/**
 * E-mails enviados pelo sistema (texto simples, via wp_mail).
 */

defined( 'ABSPATH' ) || exit;

function as_mail( $to, $subject, array $lines ) {
	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$body    = implode( "\n", $lines ) . "\n\n— " . $site . "\n" . home_url( '/' );
	$subject = sprintf( '[%s] %s', $site, $subject );
	return wp_mail( $to, $subject, $body );
}

/**
 * Linhas finais para quem ainda não criou senha: link de senha + opção de login social.
 */
function as_access_lines( WP_User $user, $intro ) {
	$url = as_set_password_url( $user );
	if ( ! $url ) {
		return [];
	}

	$lines = [ $intro, $url, '' ];

	$providers = as_social_enabled_providers();
	if ( $providers ) {
		$lines[] = sprintf( 'Se preferir, entre com %s usando este mesmo e-mail:', implode( ' ou ', $providers ) );
		$lines[] = as_url( 'entrar' );
		$lines[] = '';
	}

	$lines[] = 'Seu login é o seu e-mail: ' . $user->user_email;
	return $lines;
}

/**
 * Confirmação do cadastro feito em /cadastro/.
 */
function as_email_signup( WP_User $user ) {
	$lines = array_merge(
		[ sprintf( 'Olá, %s!', $user->display_name ), '', 'Recebemos seu cadastro. Falta só um passo:', '' ],
		as_access_lines( $user, 'Confirme seu e-mail e crie sua senha:' ),
		[ '', 'Se não foi você, é só ignorar esta mensagem.' ]
	);
	return as_mail( $user->user_email, 'Confirme seu cadastro', $lines );
}

/**
 * Convite para o grupo. Quem ainda não criou senha recebe o link para criá-la.
 */
function as_email_invite( $group_id, WP_User $user ) {
	$group     = as_get_group( $group_id );
	$organizer = get_userdata( (int) $group->post_author );

	$lines = [
		sprintf( 'Olá, %s!', $user->display_name ),
		'',
		sprintf(
			'%s adicionou você ao amigo secreto "%s".',
			$organizer ? $organizer->display_name : 'O organizador',
			get_the_title( $group )
		),
	];

	$date = get_post_meta( $group_id, '_as_event_date', true );
	if ( $date ) {
		$lines[] = 'Data da troca: ' . as_format_date( $date );
	}
	$budget = get_post_meta( $group_id, '_as_budget', true );
	if ( $budget ) {
		$lines[] = 'Valor sugerido: ' . $budget;
	}

	$lines[] = '';

	if ( as_user_needs_password( $user->ID ) ) {
		$lines = array_merge( $lines, as_access_lines( $user, 'Crie sua senha para acessar o site e montar sua lista de presentes:' ) );
	} else {
		$lines[] = 'Entre no site para ver o grupo e atualizar sua lista de presentes:';
		$lines[] = as_url( 'grupos', $group_id );
	}

	return as_mail( $user->user_email, sprintf( 'Você entrou no amigo secreto "%s"', get_the_title( $group ) ), $lines );
}

/**
 * Avisa cada participante que o sorteio foi feito.
 * Por padrão o nome sorteado NÃO vai no e-mail (evita spoilers em caixas compartilhadas);
 * use o filtro `as_email_reveal_match` para incluir.
 */
function as_email_draw_results( $group_id ) {
	$group  = as_get_group( $group_id );
	$reveal = apply_filters( 'as_email_reveal_match', false, $group_id );

	foreach ( as_group_draw( $group_id ) as $giver_id => $receiver_id ) {
		$giver    = get_userdata( $giver_id );
		$receiver = get_userdata( $receiver_id );
		if ( ! $giver || ! $receiver ) {
			continue;
		}

		$lines = [
			sprintf( 'Olá, %s!', $giver->display_name ),
			'',
			sprintf( 'O sorteio do amigo secreto "%s" foi realizado. 🎁', get_the_title( $group ) ),
			'',
		];

		if ( $reveal ) {
			$lines[] = sprintf( 'Você tirou: %s', $receiver->display_name );
			$lines[] = 'Veja a lista de presentes:';
			$lines[] = as_url( 'lista', $receiver_id );
		} else {
			$lines[] = 'Entre no site para descobrir quem você tirou e ver a lista de presentes:';
			$lines[] = as_url( 'grupos', $group_id );
		}

		as_mail( $giver->user_email, sprintf( 'Sorteio realizado: %s', get_the_title( $group ) ), $lines );
	}
}

/**
 * E-mails padrão do WordPress com remetente e textos do site.
 */
add_filter( 'wp_mail_from_name', function ( $name ) {
	return 'WordPress' === $name ? wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) : $name;
} );
