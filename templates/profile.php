<?php
/**
 * /perfil/ — nome, e-mail e senha (React: ProfileForm).
 */

$as_user = wp_get_current_user();

$as_role_labels = [
	'administrator' => __( 'Administrador', 'amigosecreto' ),
	'organizador'   => __( 'Organizador', 'amigosecreto' ),
	'participante'  => __( 'Participante', 'amigosecreto' ),
];
$as_role = $as_role_labels[ $as_user->roles[0] ?? '' ] ?? __( 'Participante', 'amigosecreto' );

get_header();
?>

<div class="container-site max-w-2xl py-10 sm:py-14">
	<p class="eyebrow"><?php esc_html_e( 'Meu perfil', 'amigosecreto' ); ?></p>
	<div class="mt-2 flex flex-wrap items-center gap-3">
		<h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl"><?php echo esc_html( $as_user->display_name ); ?></h1>
		<span class="chip"><?php echo esc_html( $as_role ); ?></span>
	</div>

	<div class="mt-8">
		<?php
		// Retorno de "Conectar Google/Facebook" (inc/social-login.php).
		$as_linked = sanitize_key( $_GET['as_linked'] ?? '' ); // phpcs:ignore
		$as_error  = as_social_error_message( sanitize_key( $_GET['as_error'] ?? '' ) ); // phpcs:ignore
		$as_notice = null;
		if ( $as_error ) {
			$as_notice = [ 'type' => 'error', 'message' => $as_error ];
		} elseif ( isset( as_social_providers()[ $as_linked ] ) ) {
			/* translators: %s: Google ou Facebook */
			$as_notice = [ 'type' => 'success', 'message' => sprintf( __( 'Conta %s conectada!', 'amigosecreto' ), as_social_providers()[ $as_linked ] ) ];
		}

		as_island(
			'ProfileForm',
			[ 'initialProfile' => as_me_payload( $as_user->ID ), 'notice' => $as_notice, 'today' => wp_date( 'Y-m-d' ) ],
			'<div class="skeleton h-96"></div>'
		);
		?>
	</div>
</div>

<?php
get_footer();
