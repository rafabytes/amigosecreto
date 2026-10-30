<?php
/**
 * /painel/ — grupos do usuário, atalho para a lista de presentes e criação de grupos.
 */

$as_user       = wp_get_current_user();
$as_groups     = as_user_groups( $as_user->ID );
$as_can_create = as_user_can_organize( $as_user->ID );
$as_gift_count = as_count_gifts( $as_user->ID );
$as_first_name = current( explode( ' ', $as_user->display_name ) );

get_header();
?>

<div class="container-site py-10 sm:py-14">
	<div class="flex flex-wrap items-end justify-between gap-6">
		<div>
			<p class="eyebrow"><?php esc_html_e( 'Meu painel', 'amigosecreto' ); ?></p>
			<h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">
				<?php
				/* translators: %s: primeiro nome */
				printf( esc_html__( 'Olá, %s! 👋', 'amigosecreto' ), esc_html( $as_first_name ) );
				?>
			</h1>
		</div>
		<?php if ( $as_can_create ) : ?>
			<?php as_island( 'CreateGroup', [], '<button class="btn btn-primary" disabled>' . esc_html__( 'Novo grupo', 'amigosecreto' ) . '</button>' ); ?>
		<?php endif; ?>
	</div>

	<a href="<?php echo esc_url( as_url( 'minha-lista' ) ); ?>" class="group mt-8 flex items-center gap-5 overflow-hidden rounded-3xl bg-grape p-6 text-white shadow-xl shadow-grape/25 transition hover:-translate-y-0.5 sm:p-8">
		<span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-white/15"><?php echo as_icon( 'gift', 'size-7' ); // phpcs:ignore ?></span>
		<span class="flex-1">
			<span class="block text-xl font-bold"><?php esc_html_e( 'Minha lista de presentes', 'amigosecreto' ); ?></span>
			<span class="block text-white/75">
				<?php
				echo $as_gift_count
					? esc_html( sprintf( _n( '%d item na lista. Mantenha atualizada!', '%d itens na lista. Mantenha atualizada!', $as_gift_count, 'amigosecreto' ), $as_gift_count ) )
					: esc_html__( 'Sua lista está vazia. Conte o que você gostaria de ganhar!', 'amigosecreto' );
				?>
			</span>
		</span>
		<?php echo as_icon( 'arrow', 'size-6 transition group-hover:translate-x-1' ); // phpcs:ignore ?>
	</a>

	<h2 class="mt-12 text-xl font-bold"><?php esc_html_e( 'Meus grupos', 'amigosecreto' ); ?></h2>

	<?php if ( $as_groups ) : ?>
		<div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
			<?php foreach ( $as_groups as $as_group ) : ?>
				<?php get_template_part( 'template-parts/group-card', null, [ 'group' => as_group_payload( $as_group, $as_user->ID ) ] ); ?>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="card mt-5 grid place-items-center gap-3 p-12 text-center">
			<span class="text-5xl">🎄</span>
			<p class="text-lg font-semibold"><?php esc_html_e( 'Você ainda não está em nenhum grupo.', 'amigosecreto' ); ?></p>
			<p class="max-w-md text-ink/60">
				<?php
				echo $as_can_create
					? esc_html__( 'Crie um grupo e adicione os participantes pelo e-mail.', 'amigosecreto' )
					: esc_html__( 'Quando um organizador adicionar seu e-mail a um grupo, ele aparecerá aqui. Enquanto isso, já dá para montar sua lista!', 'amigosecreto' );
				?>
			</p>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
