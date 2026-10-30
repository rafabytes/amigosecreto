<?php
/**
 * /lista/{user_id}/ — lista de presentes de outro participante (somente leitura, renderizada em PHP).
 * Permissão já verificada em inc/router.php.
 */

$as_owner_id = (int) get_query_var( 'as_id' );
$as_owner    = as_user_payload( $as_owner_id );
$as_gifts    = array_map( 'as_gift_payload', as_user_gifts( $as_owner_id ) );
$as_is_me    = get_current_user_id() === $as_owner_id;
$as_back     = wp_get_referer();
$as_back     = $as_back && 0 === strpos( $as_back, home_url() ) ? $as_back : as_url( 'painel' );

get_header();
?>

<div class="container-site max-w-3xl py-10 sm:py-14">
	<a href="<?php echo esc_url( $as_back ); ?>" class="back-link"><?php echo as_icon( 'back', 'size-4' ); // phpcs:ignore ?> <?php esc_html_e( 'Voltar', 'amigosecreto' ); ?></a>

	<div class="mt-4 flex items-center gap-4">
		<?php echo as_avatar_html( $as_owner, 'size-16 text-xl' ); // phpcs:ignore ?>
		<div>
			<p class="eyebrow"><?php esc_html_e( 'Lista de presentes', 'amigosecreto' ); ?></p>
			<h1 class="mt-1 text-3xl font-extrabold tracking-tight"><?php echo esc_html( $as_owner['name'] ); ?></h1>
			<?php if ( $as_owner['birthday'] ) : ?>
				<p class="mt-1 text-sm text-ink/60">
					<?php
					/* translators: %s: dia e mês, ex.: 12 de março */
					printf( esc_html__( '🎂 Aniversário: %s', 'amigosecreto' ), esc_html( $as_owner['birthday'] ) );
					?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $as_is_me ) : ?>
		<div class="notice mt-6">
			<?php esc_html_e( 'É assim que os outros participantes veem a sua lista.', 'amigosecreto' ); ?>
			<a href="<?php echo esc_url( as_url( 'minha-lista' ) ); ?>" class="font-semibold underline"><?php esc_html_e( 'Editar lista', 'amigosecreto' ); ?></a>
		</div>
	<?php endif; ?>

	<?php if ( $as_gifts ) : ?>
		<ol class="mt-8 space-y-4">
			<?php foreach ( $as_gifts as $as_i => $as_gift ) : ?>
				<li class="card flex gap-4 p-5 sm:p-6">
					<span class="grid size-9 shrink-0 place-items-center rounded-xl bg-grape/10 text-sm font-bold text-grape"><?php echo (int) $as_i + 1; ?></span>
					<div class="min-w-0 flex-1">
						<div class="flex flex-wrap items-center gap-2">
							<h2 class="text-lg font-bold"><?php echo esc_html( $as_gift['title'] ); ?></h2>
							<?php if ( $as_gift['price'] ) : ?>
								<span class="chip"><?php echo esc_html( $as_gift['price'] ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( $as_gift['note'] ) : ?>
							<p class="mt-1 whitespace-pre-line text-ink/70"><?php echo esc_html( $as_gift['note'] ); ?></p>
						<?php endif; ?>
						<?php if ( $as_gift['url'] ) : ?>
							<a href="<?php echo esc_url( $as_gift['url'] ); ?>" target="_blank" rel="noopener noreferrer nofollow" class="btn btn-soft btn-sm mt-3" data-no-swup>
								<?php echo as_icon( 'link', 'size-4' ); // phpcs:ignore ?>
								<?php
								/* translators: %s: domínio da loja */
								printf( esc_html__( 'Ver em %s', 'amigosecreto' ), esc_html( $as_gift['host'] ) );
								?>
							</a>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php else : ?>
		<div class="card mt-8 p-12 text-center">
			<p class="text-5xl">🤔</p>
			<p class="mt-4 font-semibold"><?php esc_html_e( 'Essa pessoa ainda não adicionou presentes à lista.', 'amigosecreto' ); ?></p>
			<p class="mt-1 text-ink/60"><?php esc_html_e( 'Volte mais tarde — ou arrisque uma surpresa!', 'amigosecreto' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
