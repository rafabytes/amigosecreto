<?php
/**
 * /usuarios/ — todas as contas do site, com busca e filtro por papel. Cada linha leva ao perfil com o
 * histórico de atividade (templates/user.php). Só administradores (verificado em inc/router.php).
 */

$as_search = sanitize_text_field( wp_unslash( $_GET['busca'] ?? '' ) ); // phpcs:ignore
$as_role   = sanitize_key( wp_unslash( $_GET['papel'] ?? '' ) ); // phpcs:ignore
$as_page   = max( 1, absint( $_GET['pagina'] ?? 1 ) ); // phpcs:ignore
$as_per    = 30;
$as_counts = count_users();
$as_roles  = [ '' => [ __( 'Todos', 'amigosecreto' ), (int) $as_counts['total_users'] ] ];

foreach ( [ 'organizador', 'participante', 'administrator' ] as $as_r ) {
	if ( ! empty( $as_counts['avail_roles'][ $as_r ] ) ) {
		$as_roles[ $as_r ] = [ translate_user_role( wp_roles()->roles[ $as_r ]['name'] ?? $as_r ), (int) $as_counts['avail_roles'][ $as_r ] ];
	}
}
$as_role = isset( $as_roles[ $as_role ] ) ? $as_role : '';

$as_query_args = [
	'number'  => $as_per,
	'paged'   => $as_page,
	'orderby' => 'registered',
	'order'   => 'DESC',
];
if ( '' !== $as_search ) {
	$as_query_args['search']         = '*' . $as_search . '*';
	$as_query_args['search_columns'] = [ 'user_email', 'display_name', 'user_login' ];
}
if ( $as_role ) {
	$as_query_args['role'] = $as_role;
}

$as_query = new WP_User_Query( $as_query_args );
$as_users = $as_query->get_results();
$as_total = (int) $as_query->get_total();
$as_ids   = wp_list_pluck( $as_users, 'ID' );
$as_last  = as_users_last_seen( $as_ids );
$as_stats = as_users_stats( $as_ids );
$as_pages = (int) ceil( $as_total / $as_per );
$as_link  = fn( array $args ) => add_query_arg( array_filter( $args + [ 'busca' => $as_search, 'papel' => $as_role ] ), as_url( 'usuarios' ) );

get_header();
?>

<div class="container-site py-10 sm:py-14">
	<div class="flex flex-wrap items-end justify-between gap-6">
		<div>
			<p class="eyebrow"><?php echo as_icon( 'shield-check', 'size-4' ); // phpcs:ignore ?> <?php esc_html_e( 'Administração', 'amigosecreto' ); ?></p>
			<h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl"><?php esc_html_e( 'Usuários', 'amigosecreto' ); ?></h1>
			<p class="mt-2 text-ink/60"><?php esc_html_e( 'Abra um perfil para ver os sorteios e o histórico de atividade da pessoa.', 'amigosecreto' ); ?></p>
		</div>

		<form method="get" action="<?php echo esc_url( as_url( 'usuarios' ) ); ?>" class="relative w-full sm:w-80" role="search">
			<?php if ( $as_role ) : ?>
				<input type="hidden" name="papel" value="<?php echo esc_attr( $as_role ); ?>">
			<?php endif; ?>
			<label for="as-user-search" class="sr-only"><?php esc_html_e( 'Buscar usuários', 'amigosecreto' ); ?></label>
			<span class="pointer-events-none absolute inset-y-0 left-4 grid place-items-center text-ink/40"><?php echo as_icon( 'search', 'size-5' ); // phpcs:ignore ?></span>
			<input id="as-user-search" type="search" name="busca" value="<?php echo esc_attr( $as_search ); ?>" class="input pl-12" placeholder="<?php esc_attr_e( 'Nome ou e-mail', 'amigosecreto' ); ?>">
		</form>
	</div>

	<nav class="tabs mt-8" aria-label="<?php esc_attr_e( 'Filtrar por papel', 'amigosecreto' ); ?>">
		<?php foreach ( $as_roles as $as_key => [ $as_label, $as_count ] ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array_filter( [ 'papel' => $as_key, 'busca' => $as_search ] ), as_url( 'usuarios' ) ) ); ?>" <?php echo $as_key === $as_role ? 'aria-current="page"' : ''; ?>>
				<?php echo esc_html( $as_label ); ?>
				<span class="tabs-count"><?php echo (int) $as_count; ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( '' !== $as_search ) : ?>
		<p class="mt-6 text-sm text-ink/60">
			<?php
			/* translators: 1: número de resultados, 2: termo buscado */
			printf( esc_html( _n( '%1$d resultado para "%2$s".', '%1$d resultados para "%2$s".', $as_total, 'amigosecreto' ) ), (int) $as_total, esc_html( $as_search ) );
			?>
			<a href="<?php echo esc_url( add_query_arg( array_filter( [ 'papel' => $as_role ] ), as_url( 'usuarios' ) ) ); ?>" class="font-semibold text-grape underline"><?php esc_html_e( 'Limpar busca', 'amigosecreto' ); ?></a>
		</p>
	<?php endif; ?>

	<?php if ( $as_users ) : ?>
		<ul class="card mt-6 divide-y divide-ink/5">
			<?php
			foreach ( $as_users as $as_u ) :
				$as_p    = as_user_payload( $as_u );
				$as_s    = $as_stats[ $as_u->ID ];
				$as_seen = $as_last[ $as_u->ID ] ?? 0;
				?>
				<li>
					<a href="<?php echo esc_url( as_url( 'usuarios', $as_u->ID ) ); ?>" class="group flex items-center gap-4 p-4 transition hover:bg-snow sm:px-6">
						<?php echo as_avatar_html( $as_p, 'size-11 text-sm' ); // phpcs:ignore ?>
						<div class="min-w-0 flex-1">
							<p class="flex flex-wrap items-center gap-x-2 gap-y-1">
								<span class="font-semibold group-hover:text-grape"><?php echo esc_html( $as_u->display_name ); ?></span>
								<span class="chip<?php echo user_can( $as_u, 'manage_options' ) ? ' chip-grape' : ''; ?>"><?php echo esc_html( as_role_label( $as_u ) ); ?></span>
								<?php if ( as_user_needs_password( $as_u->ID ) ) : ?>
									<span class="chip chip-berry" title="<?php esc_attr_e( 'Ainda não criou a senha nem entrou com Google/Facebook', 'amigosecreto' ); ?>"><?php esc_html_e( 'não ativada', 'amigosecreto' ); ?></span>
								<?php endif; ?>
							</p>
							<p class="truncate text-sm text-ink/50"><?php echo esc_html( $as_u->user_email ); ?></p>
							<p class="mt-1 text-xs text-ink/50">
								<?php
								echo esc_html( implode( ' · ', array_filter( [
									/* translators: %d: número de sorteios */
									$as_s['organizes'] ? sprintf( _n( 'organiza %d sorteio', 'organiza %d sorteios', $as_s['organizes'], 'amigosecreto' ), $as_s['organizes'] ) : '',
									/* translators: %d: número de sorteios */
									sprintf( _n( 'participa de %d', 'participa de %d', $as_s['joins'], 'amigosecreto' ), $as_s['joins'] ),
									/* translators: %d: número de itens */
									sprintf( _n( '%d item na lista', '%d itens na lista', $as_s['gifts'], 'amigosecreto' ), $as_s['gifts'] ),
								] ) ) );
								?>
							</p>
						</div>
						<dl class="hidden shrink-0 text-right text-xs sm:block">
							<dt class="text-ink/40"><?php esc_html_e( 'Último acesso', 'amigosecreto' ); ?></dt>
							<dd class="font-medium text-ink/70"><?php echo $as_seen ? esc_html( wp_date( 'd/m/Y H:i', $as_seen ) ) : '—'; ?></dd>
							<dt class="mt-1 text-ink/40"><?php esc_html_e( 'Conta criada', 'amigosecreto' ); ?></dt>
							<dd class="font-medium text-ink/70"><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $as_u->user_registered . ' UTC' ) ) ); ?></dd>
						</dl>
						<?php echo as_icon( 'arrow', 'size-5 shrink-0 text-ink/30 transition group-hover:translate-x-0.5 group-hover:text-grape' ); // phpcs:ignore ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $as_pages > 1 ) : ?>
			<div class="mt-8 flex flex-wrap items-center justify-between gap-3">
				<p class="text-sm text-ink/50">
					<?php
					/* translators: 1: página atual, 2: total de páginas */
					printf( esc_html__( 'Página %1$d de %2$d', 'amigosecreto' ), (int) $as_page, (int) $as_pages );
					?>
				</p>
				<div class="flex gap-2">
					<?php if ( $as_page > 1 ) : ?>
						<a href="<?php echo esc_url( $as_link( [ 'pagina' => $as_page > 2 ? $as_page - 1 : 0 ] ) ); ?>" class="btn btn-ghost btn-sm"><?php echo as_icon( 'back', 'size-4' ); // phpcs:ignore ?> <?php esc_html_e( 'Anterior', 'amigosecreto' ); ?></a>
					<?php endif; ?>
					<?php if ( $as_page < $as_pages ) : ?>
						<a href="<?php echo esc_url( $as_link( [ 'pagina' => $as_page + 1 ] ) ); ?>" class="btn btn-ghost btn-sm"><?php esc_html_e( 'Próxima', 'amigosecreto' ); ?> <?php echo as_icon( 'arrow', 'size-4' ); // phpcs:ignore ?></a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="card mt-6 p-12 text-center text-ink/60"><?php esc_html_e( 'Nenhum usuário encontrado.', 'amigosecreto' ); ?></div>
	<?php endif; ?>
</div>

<?php
get_footer();
