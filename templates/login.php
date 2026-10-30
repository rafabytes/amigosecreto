<?php
/**
 * /entrar/ — login por e-mail e senha (inc/auth.php) ou Google/Facebook (inc/social-login.php).
 */

$as_errors   = as_form_errors();
$as_social   = as_social_error_message( sanitize_key( $_GET['as_error'] ?? '' ) ); // phpcs:ignore
if ( $as_social ) {
	$as_errors[] = $as_social;
}
$as_redirect = as_safe_redirect_target( '' );
$as_login    = sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) ); // phpcs:ignore

get_header();
?>

<div class="container-site grid min-h-[70vh] place-items-center py-12">
	<div class="w-full max-w-md">
		<div class="text-center">
			<span class="mx-auto grid size-14 place-items-center rounded-2xl bg-grape text-white shadow-xl shadow-grape/30"><?php echo as_icon( 'gift', 'size-7' ); // phpcs:ignore ?></span>
			<h1 class="mt-5 text-3xl font-extrabold tracking-tight"><?php esc_html_e( 'Entrar', 'amigosecreto' ); ?></h1>
			<p class="mt-2 text-ink/60"><?php esc_html_e( 'Use o e-mail que o organizador cadastrou.', 'amigosecreto' ); ?></p>
		</div>

		<div class="card mt-8 p-6 sm:p-8">
			<?php as_social_buttons( $as_redirect ); ?>

			<form method="post" action="<?php echo esc_url( as_url( 'entrar' ) ); ?>" class="space-y-5" data-no-swup>
				<?php if ( $as_errors ) : ?>
					<div class="notice notice-error" role="alert"><?php echo esc_html( implode( ' ', $as_errors ) ); ?></div>
				<?php endif; ?>

				<label class="field">
					<span class="field-label"><?php esc_html_e( 'E-mail', 'amigosecreto' ); ?></span>
					<input class="input" type="text" name="log" autocomplete="username" required autofocus value="<?php echo esc_attr( $as_login ); ?>">
				</label>

				<label class="field">
					<span class="field-label flex justify-between">
						<?php esc_html_e( 'Senha', 'amigosecreto' ); ?>
						<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="font-medium text-grape hover:underline" data-no-swup><?php esc_html_e( 'Esqueci a senha', 'amigosecreto' ); ?></a>
					</span>
					<span class="relative block">
						<input class="input pr-12" type="password" name="pwd" autocomplete="current-password" required>
						<?php echo as_password_toggle(); // phpcs:ignore ?>
					</span>
				</label>

				<label class="flex items-center gap-2 text-sm text-ink/70">
					<input type="checkbox" name="rememberme" value="1" class="checkbox" checked>
					<?php esc_html_e( 'Manter conectado', 'amigosecreto' ); ?>
				</label>

				<?php wp_nonce_field( 'as_login', '_as_nonce' ); ?>
				<?php if ( $as_redirect ) : ?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $as_redirect ); ?>">
				<?php endif; ?>

				<button type="submit" class="btn btn-primary w-full"><?php esc_html_e( 'Entrar', 'amigosecreto' ); ?></button>
			</form>
		</div>

		<?php if ( get_option( 'users_can_register' ) ) : ?>
			<p class="mt-6 text-center text-sm text-ink/60">
				<?php esc_html_e( 'Quer organizar um amigo secreto?', 'amigosecreto' ); ?>
				<a href="<?php echo esc_url( as_url( 'cadastro' ) ); ?>" class="font-semibold text-grape hover:underline"><?php esc_html_e( 'Crie sua conta', 'amigosecreto' ); ?></a>
			</p>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
