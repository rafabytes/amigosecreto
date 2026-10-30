<?php
/**
 * /cadastro/ — cria uma conta de organizador (processado em inc/auth.php).
 * Só nome e e-mail são obrigatórios; a senha é criada pelo link enviado por e-mail.
 * Disponível apenas com "Qualquer pessoa pode se registrar" ativo em Configurações > Geral.
 */

$as_errors    = as_form_errors();
$as_sent      = ! empty( $_GET['enviado'] ); // phpcs:ignore
$as_name      = sanitize_text_field( wp_unslash( $_POST['as_name'] ?? '' ) ); // phpcs:ignore
$as_email     = sanitize_email( wp_unslash( $_POST['as_email'] ?? '' ) ); // phpcs:ignore
$as_birthdate = as_sanitize_birthdate( wp_unslash( $_POST['as_birthdate'] ?? '' ) ); // phpcs:ignore

get_header();
?>

<div class="container-site grid min-h-[70vh] place-items-center py-12">
	<div class="w-full max-w-md">
		<?php if ( $as_sent ) : ?>
			<div class="card p-8 text-center sm:p-10">
				<span class="mx-auto grid size-14 place-items-center rounded-2xl bg-grape text-white shadow-xl shadow-grape/30"><?php echo as_icon( 'mail', 'size-7' ); // phpcs:ignore ?></span>
				<h1 class="mt-5 text-2xl font-extrabold tracking-tight"><?php esc_html_e( 'Confira seu e-mail', 'amigosecreto' ); ?></h1>
				<p class="mt-3 text-ink/70"><?php esc_html_e( 'Enviamos um link para você confirmar o e-mail e criar sua senha. Depois é só entrar e montar seu primeiro grupo.', 'amigosecreto' ); ?></p>
				<p class="mt-4 text-sm text-ink/50"><?php esc_html_e( 'Não chegou? Olhe a caixa de spam ou tente se cadastrar de novo com o mesmo e-mail.', 'amigosecreto' ); ?></p>
				<a href="<?php echo esc_url( as_url( 'entrar' ) ); ?>" class="btn btn-ghost mt-6"><?php esc_html_e( 'Ir para o login', 'amigosecreto' ); ?></a>
			</div>
		<?php else : ?>
			<div class="text-center">
				<span class="mx-auto grid size-14 place-items-center rounded-2xl bg-grape text-white shadow-xl shadow-grape/30"><?php echo as_icon( 'sparkles', 'size-7' ); // phpcs:ignore ?></span>
				<h1 class="mt-5 text-3xl font-extrabold tracking-tight"><?php esc_html_e( 'Organize seu amigo secreto', 'amigosecreto' ); ?></h1>
				<p class="mt-2 text-ink/60"><?php esc_html_e( 'Crie sua conta para montar grupos e fazer o sorteio.', 'amigosecreto' ); ?></p>
			</div>

			<div class="card mt-8 p-6 sm:p-8">
				<?php as_social_buttons(); ?>

				<form method="post" action="<?php echo esc_url( as_url( 'cadastro' ) ); ?>" enctype="multipart/form-data" class="space-y-5" data-no-swup>
					<?php if ( $as_errors ) : ?>
						<div class="notice notice-error" role="alert">
							<?php foreach ( $as_errors as $as_error ) : ?>
								<p><?php echo esc_html( $as_error ); ?></p>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<label class="field">
						<span class="field-label"><?php esc_html_e( 'Seu nome', 'amigosecreto' ); ?></span>
						<input class="input" type="text" name="as_name" autocomplete="name" required value="<?php echo esc_attr( $as_name ); ?>">
					</label>

					<label class="field">
						<span class="field-label"><?php esc_html_e( 'E-mail', 'amigosecreto' ); ?></span>
						<input class="input" type="email" name="as_email" autocomplete="email" required value="<?php echo esc_attr( $as_email ); ?>">
						<span class="field-hint"><?php esc_html_e( 'Vamos enviar um link para confirmar o e-mail e criar sua senha.', 'amigosecreto' ); ?></span>
					</label>

					<details class="group rounded-2xl bg-snow p-4 ring-1 ring-ink/5" <?php echo $as_birthdate ? 'open' : ''; ?>>
						<summary class="flex cursor-pointer list-none items-center justify-between text-sm font-medium text-ink/70">
							<?php esc_html_e( 'Foto e aniversário (opcional)', 'amigosecreto' ); ?>
							<span class="transition group-open:rotate-180" aria-hidden="true">▾</span>
						</summary>
						<div class="mt-4 space-y-4">
							<label class="field">
								<span class="field-label"><?php esc_html_e( 'Data de nascimento', 'amigosecreto' ); ?></span>
								<input class="input bg-white" type="date" name="as_birthdate" max="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( $as_birthdate ); ?>">
								<span class="field-hint"><?php esc_html_e( 'Os participantes veem só o dia e o mês.', 'amigosecreto' ); ?></span>
							</label>
							<label class="field">
								<span class="field-label"><?php esc_html_e( 'Foto de perfil', 'amigosecreto' ); ?></span>
								<input class="block w-full text-sm text-ink/70 file:mr-3 file:rounded-full file:border-0 file:bg-grape/10 file:px-4 file:py-2 file:font-semibold file:text-grape hover:file:bg-grape/15" type="file" name="as_avatar" accept="image/jpeg,image/png,image/webp,image/gif">
								<span class="field-hint"><?php esc_html_e( 'JPG, PNG, WebP ou GIF, até 5 MB.', 'amigosecreto' ); ?></span>
							</label>
						</div>
					</details>

					<?php wp_nonce_field( 'as_register', '_as_nonce' ); ?>

					<button type="submit" class="btn btn-primary w-full"><?php esc_html_e( 'Criar conta', 'amigosecreto' ); ?></button>

					<?php if ( get_privacy_policy_url() ) : ?>
						<p class="text-center text-xs text-ink/50">
							<?php
							printf(
								/* translators: %s: link para a política de privacidade */
								esc_html__( 'Ao criar sua conta, com e-mail, Google ou Facebook, você concorda com a nossa %s.', 'amigosecreto' ),
								'<a href="' . esc_url( get_privacy_policy_url() ) . '" class="underline hover:text-grape" target="_blank">' . esc_html__( 'Política de privacidade', 'amigosecreto' ) . '</a>'
							);
							?>
						</p>
					<?php endif; ?>
				</form>
			</div>

			<p class="mt-6 text-center text-sm text-ink/60">
				<?php esc_html_e( 'Já tem conta?', 'amigosecreto' ); ?>
				<a href="<?php echo esc_url( as_url( 'entrar' ) ); ?>" class="font-semibold text-grape hover:underline"><?php esc_html_e( 'Entrar', 'amigosecreto' ); ?></a>
			</p>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
