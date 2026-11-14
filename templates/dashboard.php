<?php
/**
 * /painel/ — início de quem está logado: os sorteios do usuário, cada um com um resumo
 * (template-parts/group-card.php), filtros, atalho para a lista de presentes e criação de sorteios.
 * Qualquer usuário pode criar um sorteio e passa a ser o organizador dele.
 * Administradores também podem ver todos os sorteios do site (?ver=site).
 */

$as_user       = wp_get_current_user();
$as_admin      = as_user_is_admin( $as_user->ID );
$as_can_create = as_user_can_organize( $as_user->ID );
$as_gift_count = as_count_gifts( $as_user->ID );
$as_first_name = current( explode( ' ', $as_user->display_name ) );
$as_summarize  = fn( array $groups ) => as_sort_group_summaries( array_map( fn( $g ) => as_group_summary( $g, $as_user->ID ), $groups ) );

$as_mine = $as_summarize( as_user_groups( $as_user->ID ) );
$as_tabs = [
	'todos'     => [ __( 'Todos', 'amigosecreto' ), $as_mine ],
	'organizo'  => [ __( 'Que organizo', 'amigosecreto' ), array_filter( $as_mine, fn( $g ) => 'organizer' === $g['role'] ) ],
	'participo' => [ __( 'Que participo', 'amigosecreto' ), array_filter( $as_mine, fn( $g ) => $g['isParticipant'] ) ],
];
$as_tab = sanitize_key( wp_unslash( $_GET['ver'] ?? '' ) ); // phpcs:ignore

if ( $as_admin ) {
	$as_site_groups  = as_all_groups();
	$as_tabs['site'] = [ __( 'Todo o site', 'amigosecreto' ), 'site' === $as_tab ? $as_summarize( $as_site_groups ) : $as_site_groups ];
}

$as_tab      = isset( $as_tabs[ $as_tab ] ) ? $as_tab : 'todos';
$as_items    = array_values( $as_tabs[ $as_tab ][1] );
$as_upcoming = array_filter( $as_items, fn( $g ) => ! $g['isPast'] );
$as_past     = array_filter( $as_items, fn( $g ) => $g['isPast'] );

// Destaques: sorteios já feitos em que a pessoa ainda não viu quem tirou.
$as_to_reveal = array_filter( $as_mine, fn( $g ) => $g['isParticipant'] && $g['isDrawn'] && ! $g['iRevealed'] && ! $g['isPast'] );
$as_organizes = count( $as_tabs['organizo'][1] );
$as_next      = current( array_filter( $as_mine, fn( $g ) => null !== $g['daysUntil'] && $g['daysUntil'] >= 0 ) );

get_header();
?>

<div class="container-site py-10 sm:py-14">
	<div class="flex flex-wrap items-end justify-between gap-6">
		<div>
			<p class="eyebrow"><?php esc_html_e( 'Meus sorteios', 'amigosecreto' ); ?></p>
			<h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">
				<?php
				/* translators: %s: primeiro nome */
				printf( esc_html__( 'Olá, %s! 👋', 'amigosecreto' ), esc_html( $as_first_name ) );
				?>
			</h1>
			<p class="mt-2 max-w-xl text-ink/60 text-pretty">
				<?php
				if ( ! $as_mine ) {
					esc_html_e( 'Você ainda não está em nenhum sorteio. Crie o seu ou aguarde o convite de alguém.', 'amigosecreto' );
				} else {
					$as_parts = [ sprintf( _n( 'Você está em %d sorteio', 'Você está em %d sorteios', count( $as_mine ), 'amigosecreto' ), count( $as_mine ) ) ];
					if ( $as_organizes ) {
						/* translators: %d: número de sorteios */
						$as_parts[] = sprintf( __( 'organiza %d', 'amigosecreto' ), $as_organizes );
					}
					echo esc_html( implode( __( ' e ', 'amigosecreto' ), $as_parts ) . '.' );
					if ( $as_next ) {
						echo ' ';
						printf(
							/* translators: 1: nome do sorteio, 2: data */
							esc_html__( 'Próxima troca: %1$s, %2$s.', 'amigosecreto' ),
							'<strong class="font-semibold text-ink/80">' . esc_html( $as_next['title'] ) . '</strong>',
							esc_html( 0 === $as_next['daysUntil'] ? __( 'hoje', 'amigosecreto' ) : $as_next['eventDateText'] )
						);
					}
				}
				?>
			</p>
		</div>
		<?php if ( $as_can_create ) : ?>
			<?php as_island( 'CreateGroup', [], '<button class="btn btn-primary" disabled>' . esc_html__( 'Criar sorteio', 'amigosecreto' ) . '</button>' ); ?>
		<?php endif; ?>
	</div>

	<?php foreach ( array_slice( $as_to_reveal, 0, 3 ) as $as_g ) : ?>
		<a href="<?php echo esc_url( $as_g['url'] ); ?>" class="group mt-6 flex items-center gap-4 rounded-3xl bg-berry p-5 text-white shadow-xl shadow-berry/25 transition hover:-translate-y-0.5 sm:px-6">
			<span class="text-3xl" aria-hidden="true">🎁</span>
			<span class="min-w-0 flex-1">
				<span class="block font-bold">
					<?php
					/* translators: %s: nome do sorteio */
					printf( esc_html__( 'O sorteio "%s" já aconteceu!', 'amigosecreto' ), esc_html( $as_g['title'] ) );
					?>
				</span>
				<span class="block text-sm text-white/80"><?php esc_html_e( 'Descubra quem você tirou e veja a lista de presentes da pessoa.', 'amigosecreto' ); ?></span>
			</span>
			<?php echo as_icon( 'arrow', 'size-6 shrink-0 transition group-hover:translate-x-1' ); // phpcs:ignore ?>
		</a>
	<?php endforeach; ?>

	<div class="mt-10 grid items-start gap-8 lg:grid-cols-[1fr_20rem]">
		<section class="min-w-0" aria-labelledby="as-list-title">
			<h2 id="as-list-title" class="sr-only"><?php esc_html_e( 'Sorteios', 'amigosecreto' ); ?></h2>

			<?php if ( $as_mine || $as_admin ) : ?>
				<nav class="tabs" aria-label="<?php esc_attr_e( 'Filtrar sorteios', 'amigosecreto' ); ?>">
					<?php foreach ( $as_tabs as $as_key => [ $as_label, $as_list ] ) : ?>
						<a href="<?php echo esc_url( 'todos' === $as_key ? as_url( 'painel' ) : add_query_arg( 'ver', $as_key, as_url( 'painel' ) ) ); ?>" <?php echo $as_key === $as_tab ? 'aria-current="page"' : ''; ?>>
							<?php echo esc_html( $as_label ); ?>
							<span class="tabs-count"><?php echo (int) count( $as_list ); ?></span>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<?php if ( $as_items ) : ?>
				<div class="mt-5 space-y-4">
					<?php foreach ( $as_upcoming as $as_g ) : ?>
						<?php get_template_part( 'template-parts/group-card', null, [ 'group' => $as_g ] ); ?>
					<?php endforeach; ?>
				</div>

				<?php if ( $as_past ) : ?>
					<h3 class="mt-12 flex items-center gap-2 text-sm font-semibold tracking-wide text-ink/50 uppercase">
						<?php echo as_icon( 'clock', 'size-4' ); // phpcs:ignore ?> <?php esc_html_e( 'Já aconteceram', 'amigosecreto' ); ?>
					</h3>
					<div class="mt-4 space-y-4">
						<?php foreach ( $as_past as $as_g ) : ?>
							<?php get_template_part( 'template-parts/group-card', null, [ 'group' => $as_g ] ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php elseif ( $as_mine || 'site' === $as_tab ) : ?>
				<div class="card mt-5 p-10 text-center text-ink/60">
					<?php esc_html_e( 'Nenhum sorteio neste filtro.', 'amigosecreto' ); ?>
				</div>
			<?php else : ?>
				<div class="card mt-5 grid place-items-center gap-3 p-10 text-center sm:p-12">
					<span class="text-5xl" aria-hidden="true">🎄</span>
					<p class="text-lg font-semibold"><?php esc_html_e( 'Nenhum sorteio por aqui ainda.', 'amigosecreto' ); ?></p>
					<p class="max-w-md text-ink/60">
						<?php esc_html_e( 'Crie um sorteio para a família, os amigos ou o trabalho e adicione os participantes pelo e-mail — você será o organizador. Se alguém adicionar o seu e-mail a um sorteio, ele também aparece aqui.', 'amigosecreto' ); ?>
					</p>
					<?php if ( $as_can_create ) : ?>
						<div class="mt-2"><?php as_island( 'CreateGroup', [ 'label' => __( 'Criar meu primeiro sorteio', 'amigosecreto' ) ] ); ?></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</section>

		<aside class="min-w-0 space-y-4 lg:sticky lg:top-24">
			<a href="<?php echo esc_url( as_url( 'minha-lista' ) ); ?>" class="group flex items-center gap-4 rounded-3xl bg-grape p-5 text-white shadow-xl shadow-grape/25 transition hover:-translate-y-0.5">
				<span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-white/15"><?php echo as_icon( 'gift', 'size-6' ); // phpcs:ignore ?></span>
				<span class="min-w-0 flex-1">
					<span class="block font-bold"><?php esc_html_e( 'Minha lista de presentes', 'amigosecreto' ); ?></span>
					<span class="block text-sm text-white/75">
						<?php
						echo $as_gift_count
							? esc_html( sprintf( _n( '%d item na lista', '%d itens na lista', $as_gift_count, 'amigosecreto' ), $as_gift_count ) )
							: esc_html__( 'Lista vazia: conte o que você quer ganhar!', 'amigosecreto' );
						?>
					</span>
				</span>
				<?php echo as_icon( 'arrow', 'size-5 shrink-0 transition group-hover:translate-x-1' ); // phpcs:ignore ?>
			</a>

			<div class="card p-5 text-sm text-ink/70">
				<p class="flex items-center gap-2 font-semibold text-ink"><?php echo as_icon( 'sparkles', 'size-4 text-grape' ); // phpcs:ignore ?> <?php esc_html_e( 'Organize o seu', 'amigosecreto' ); ?></p>
				<p class="mt-1.5"><?php esc_html_e( 'Qualquer participante pode criar um sorteio. Quem cria é o organizador: adiciona as pessoas, faz o sorteio e acompanha quem já viu o resultado — sem descobrir os pares.', 'amigosecreto' ); ?></p>
			</div>

			<?php if ( $as_admin ) : ?>
				<a href="<?php echo esc_url( as_url( 'usuarios' ) ); ?>" class="card card-hover group flex items-center gap-4 p-5">
					<span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-ink/5 text-ink/70"><?php echo as_icon( 'shield-check', 'size-6' ); // phpcs:ignore ?></span>
					<span class="min-w-0 flex-1">
						<span class="block font-bold group-hover:text-grape"><?php esc_html_e( 'Usuários e auditoria', 'amigosecreto' ); ?></span>
						<span class="block text-sm text-ink/60">
							<?php
							$as_total_users = (int) count_users()['total_users'];
							echo esc_html( sprintf( _n( '%d conta · histórico de atividade', '%d contas · histórico de atividade', $as_total_users, 'amigosecreto' ), $as_total_users ) );
							?>
						</span>
					</span>
					<?php echo as_icon( 'arrow', 'size-5 shrink-0 text-ink/40 transition group-hover:translate-x-1' ); // phpcs:ignore ?>
				</a>
			<?php endif; ?>
		</aside>
	</div>
</div>

<?php
get_footer();
