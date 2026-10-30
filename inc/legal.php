<?php
/**
 * Páginas legais: política de privacidade (página de privacidade do WordPress) e exclusão de dados.
 * Links no rodapé e no cadastro; e-mail de contato via shortcode.
 */

defined( 'ABSPATH' ) || exit;

const AS_DATA_DELETION_SLUG = 'exclusao-de-dados';

/**
 * [amigosecreto_email] — e-mail de administração do site (Configurações → Geral), protegido contra robôs.
 */
add_shortcode( 'amigosecreto_email', function () {
	$email = antispambot( get_option( 'admin_email' ) );
	return '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
} );

/**
 * URL da página de exclusão de dados (ou '' se não existir/publicada).
 */
function as_data_deletion_url() {
	$page = get_page_by_path( AS_DATA_DELETION_SLUG );
	return ( $page && 'publish' === $page->post_status ) ? get_permalink( $page ) : '';
}

/**
 * Links legais para o rodapé.
 *
 * @return array<string,string> rótulo => URL
 */
function as_legal_links() {
	$links = [];
	if ( get_privacy_policy_url() ) {
		$links[ __( 'Política de privacidade', 'amigosecreto' ) ] = get_privacy_policy_url();
	}
	if ( as_data_deletion_url() ) {
		$links[ __( 'Exclusão de dados', 'amigosecreto' ) ] = as_data_deletion_url();
	}
	return $links;
}
