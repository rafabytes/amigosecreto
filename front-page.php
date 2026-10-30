<?php
/**
 * Página inicial para visitantes. Usuários logados são levados ao painel (inc/router.php).
 */

get_header();

$as_steps = [
	[ 'users', __( 'Monte o grupo', 'amigosecreto' ), __( 'O organizador cria o grupo e adiciona os participantes pelo e-mail. Cada um recebe um convite para entrar.', 'amigosecreto' ) ],
	[ 'gift', __( 'Cada um faz sua lista', 'amigosecreto' ), __( 'Participantes listam o que gostariam de ganhar, com links das lojas, faixa de preço e observações.', 'amigosecreto' ) ],
	[ 'shuffle', __( 'Sorteio com um clique', 'amigosecreto' ), __( 'Ninguém tira a si mesmo e só você descobre quem tirou — nem o organizador fica sabendo.', 'amigosecreto' ) ],
];
?>

<section class="relative overflow-hidden">
	<div aria-hidden="true" class="pointer-events-none absolute -top-40 right-[-10rem] size-[36rem] rounded-full bg-grape/20 blur-3xl"></div>

	<div class="container-site relative grid items-center gap-12 py-16 md:grid-cols-2 md:py-28">
		<div>
			<p class="eyebrow mb-5"><?php echo as_icon( 'sparkles', 'size-4' ); // phpcs:ignore ?> <?php esc_html_e( 'Amigo secreto sem planilha', 'amigosecreto' ); ?></p>
			<h1 class="text-4xl font-extrabold leading-[1.05] tracking-tight text-balance sm:text-6xl">
				<?php esc_html_e( 'Sorteie, monte sua lista e', 'amigosecreto' ); ?>
				<span class="text-grape"><?php esc_html_e( 'acerte no presente.', 'amigosecreto' ); ?></span>
			</h1>
			<p class="mt-6 max-w-lg text-lg text-ink/70 text-pretty">
				<?php esc_html_e( 'Organize o amigo secreto da família, dos amigos ou do trabalho em minutos. Cada participante cria uma lista de presentes com links e todo mundo sabe exatamente o que dar.', 'amigosecreto' ); ?>
			</p>
			<div class="mt-8 flex flex-wrap gap-3">
				<?php if ( get_option( 'users_can_register' ) ) : ?>
					<a href="<?php echo esc_url( as_url( 'cadastro' ) ); ?>" class="btn btn-primary btn-lg">
						<?php esc_html_e( 'Organizar um amigo secreto', 'amigosecreto' ); ?>
						<?php echo as_icon( 'arrow', 'size-5' ); // phpcs:ignore ?>
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( as_url( 'entrar' ) ); ?>" class="btn btn-ghost btn-lg"><?php esc_html_e( 'Já tenho conta', 'amigosecreto' ); ?></a>
			</div>
		</div>

		<div class="relative mx-auto w-full max-w-sm" aria-hidden="true">
			<div class="card rotate-[-4deg] p-6">
				<p class="text-sm font-medium text-ink/50"><?php esc_html_e( 'Você tirou', 'amigosecreto' ); ?></p>
				<div class="mt-3 flex items-center gap-3">
					<span class="avatar size-12 text-lg">MC</span>
					<p class="text-2xl font-bold">Maria Clara</p>
				</div>
				<ul class="mt-6 space-y-3">
					<li class="flex items-center justify-between rounded-2xl bg-snow p-3"><span class="font-medium">📚 Livro de receitas</span><span class="chip">R$ 80</span></li>
					<li class="flex items-center justify-between rounded-2xl bg-snow p-3"><span class="font-medium">🪴 Vaso de cerâmica</span><span class="chip">R$ 60</span></li>
					<li class="flex items-center justify-between rounded-2xl bg-snow p-3"><span class="font-medium">☕ Caneca térmica</span><span class="chip">R$ 95</span></li>
				</ul>
			</div>
			<div class="absolute -right-4 -top-6 rotate-[8deg] rounded-2xl bg-berry px-4 py-2 font-bold text-white shadow-xl shadow-berry/30">🎁 <?php esc_html_e( 'Sorteado!', 'amigosecreto' ); ?></div>
		</div>
	</div>
</section>

<section class="container-site pb-24">
	<div class="grid gap-6 md:grid-cols-3">
		<?php foreach ( $as_steps as $as_i => [ $as_ico, $as_title, $as_text ] ) : ?>
			<article class="card p-6">
				<div class="flex items-center gap-3">
					<span class="grid size-11 place-items-center rounded-2xl bg-grape/10 text-grape"><?php echo as_icon( $as_ico, 'size-6' ); // phpcs:ignore ?></span>
					<span class="text-sm font-semibold text-ink/40"><?php echo esc_html( sprintf( '0%d', $as_i + 1 ) ); ?></span>
				</div>
				<h2 class="mt-4 text-xl font-bold"><?php echo esc_html( $as_title ); ?></h2>
				<p class="mt-2 text-ink/70"><?php echo esc_html( $as_text ); ?></p>
			</article>
		<?php endforeach; ?>
	</div>
</section>

<?php
get_footer();
