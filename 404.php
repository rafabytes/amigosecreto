<?php
/**
 * Página não encontrada (também usada quando o usuário não tem acesso a um grupo ou lista).
 */

get_header();
?>

<div class="container-site grid min-h-[60vh] place-items-center py-16 text-center">
	<div>
		<p class="text-7xl">🎁</p>
		<h1 class="mt-6 text-3xl font-extrabold tracking-tight"><?php esc_html_e( 'Esse presente não está aqui', 'amigosecreto' ); ?></h1>
		<p class="mx-auto mt-3 max-w-md text-ink/60"><?php esc_html_e( 'A página não existe ou você não tem acesso a ela.', 'amigosecreto' ); ?></p>
		<a href="<?php echo esc_url( is_user_logged_in() ? as_url( 'painel' ) : home_url( '/' ) ); ?>" class="btn btn-primary mt-8"><?php esc_html_e( 'Voltar ao início', 'amigosecreto' ); ?></a>
	</div>
</div>

<?php
get_footer();
