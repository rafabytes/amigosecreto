<?php
/**
 * /usuarios/{id}/ — perfil de um usuário com os sorteios dele e a linha do tempo de atividade,
 * para auditoria. Só administradores (verificado em inc/router.php). Nunca mostra quem a pessoa tirou.
 */

$as_person = get_userdata( (int) get_query_var( 'as_id' ) );
$as_p      = as_user_payload( $as_person );
$as_groups = as_activity_groups();
$as_filter = sanitize_key( wp_unslash( $_GET['tipo'] ?? '' ) ); // phpcs:ignore
$as_filter = isset( $as_groups[ $as_filter ] ) ? $as_filter : 'tudo';
$as_page   = max( 1, absint( $_GET['pagina'] ?? 1 ) ); // phpcs:ignore
$as_per    = 100;
$as_rows   = as_user_activity( $as_person->ID, $as_groups[ $as_filter ][1], $as_per + 1, ( $as_page - 1 ) * $as_per );
$as_more   = count( $as_rows ) > $as_per;
$as_rows   = array_slice( $as_rows, 0, $as_per );
$as_counts = as_user_activity_counts( $as_person->ID );
$as_last   = max( $as_counts['login']['last'] ?? 0, $as_counts['view']['last'] ?? 0 );
$as_stats  = as_users_stats( [ $as_person->ID ] )[ $as_person->ID ];
$as_linked = array_keys( array_filter( as_social_linked( $as_person->ID ) ) );
$as_birth  = as_get_birthdate( $as_person->ID );
$as_url    = fn( array $args ) => add_query_arg( array_filter( $args ), as_url( 'usuarios', $as_person->ID ) );
$as_date   = 'j \d\e F \d\e Y';

// Sorteios da pessoa: só dados que o organizador também vê (nunca o par sorteado).
$as_person_groups = array_map( function ( $group ) use ( $as_person ) {
	$revealed = array_map( 'intval', get_post_meta( $group->ID, '_as_revealed', false ) );
	return [
		'title'       => get_the_title( $group ),
		'url'         => as_url( 'grupos', $group->ID ),
		'organizes'   => (int) $group->post_author === $as_person->ID,
		'participant' => as_group_has_participant( $group->ID, $as_person->ID ),
		'drawn'       => as_group_is_drawn( $group->ID ),
		'revealed'    => in_array( $as_person->ID, $revealed, true ),
		'count'       => count( as_group_participants( $group->ID ) ),
		'date'        => as_format_date( get_post_meta( $group->ID, '_as_event_date', true ) ),
	];
}, as_user_groups( $as_person->ID ) );

get_header();
?>

<div class="container-site py-10 sm:py-14">
	<a href="<?php echo esc_url( as_url( 'usuarios' ) ); ?>" class="back-link"><?php echo as_icon( 'back', 'size-4' ); // phpcs:ignore ?> <?php esc_html_e( 'Usuários', 'amigosecreto' ); ?></a>

	<header class="mt-4 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
		<div class="flex items-center gap-5">
			<?php echo as_avatar_html( $as_p, 'size-20 text-2xl' ); // phpcs:ignore ?>
			<div class="min-w-0">
				<p class="eyebrow"><?php echo as_icon( 'shield-check', 'size-4' ); // phpcs:ignore ?> <?php esc_html_e( 'Auditoria · só administradores', 'amigosecreto' ); ?></p>
				<h1 class="mt-1 text-3xl font-extrabold tracking-tight sm:text-4xl"><?php echo esc_html( $as_person->display_name ); ?></h1>
				<p class="mt-1 text-sm break-all text-ink/60">
					<a href="mailto:<?php echo esc_attr( $as_person->user_email ); ?>" class="hover:text-grape" data-no-swup><?php echo esc_html( $as_person->user_email ); ?></a>
					· <?php echo esc_html( as_role_label( $as_person ) ); ?>
					· <a href="<?php echo esc_url( get_edit_user_link( $as_person->ID ) ); ?>" class="underline decoration-ink/20 underline-offset-4 hover:text-grape" data-no-swup><?php esc_html_e( 'Editar no wp-admin', 'amigosecreto' ); ?></a>
				</p>
				<p class="mt-2 flex flex-wrap gap-1.5">
					<?php if ( as_user_needs_password( $as_person->ID ) ) : ?>
						<span class="chip chip-berry"><?php esc_html_e( 'Conta não ativada: ainda não criou a senha', 'amigosecreto' ); ?></span>
					<?php endif; ?>
					<?php foreach ( $as_linked as $as_provider ) : ?>
						<span class="chip"><?php echo as_icon( 'link', 'size-3.5' ); // phpcs:ignore ?> <?php echo esc_html( as_social_providers()[ $as_provider ] ); ?></span>
					<?php endforeach; ?>
					<?php if ( $as_birth ) : ?>
						<span class="chip">🎂 <?php echo esc_html( as_format_date( $as_birth ) ); ?></span>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<dl class="grid grid-cols-2 gap-x-8 gap-y-4 sm:grid-cols-3 lg:flex lg:gap-x-8">
			<div><dt class="text-xs font-semibold tracking-wide text-ink/40 uppercase"><?php esc_html_e( 'Conta criada', 'amigosecreto' ); ?></dt><dd class="mt-1 font-semibold"><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $as_person->user_registered . ' UTC' ) ) ); ?></dd></div>
			<div><dt class="text-xs font-semibold tracking-wide text-ink/40 uppercase"><?php esc_html_e( 'Último acesso', 'amigosecreto' ); ?></dt><dd class="mt-1 font-semibold"><?php echo $as_last ? esc_html( wp_date( 'd/m/Y H:i', $as_last ) ) : '—'; ?></dd></div>
			<div><dt class="text-xs font-semibold tracking-wide text-ink/40 uppercase"><?php esc_html_e( 'Logins', 'amigosecreto' ); ?></dt><dd class="mt-1 font-semibold"><?php echo (int) ( $as_counts['login']['total'] ?? 0 ); ?></dd></div>
			<div><dt class="text-xs font-semibold tracking-wide text-ink/40 uppercase"><?php esc_html_e( 'Organiza', 'amigosecreto' ); ?></dt><dd class="mt-1 font-semibold"><?php echo (int) $as_stats['organizes']; ?></dd></div>
			<div><dt class="text-xs font-semibold tracking-wide text-ink/40 uppercase"><?php esc_html_e( 'Participa', 'amigosecreto' ); ?></dt><dd class="mt-1 font-semibold"><?php echo (int) $as_stats['joins']; ?></dd></div>
			<div><dt class="text-xs font-semibold tracking-wide text-ink/40 uppercase"><?php esc_html_e( 'Na lista', 'amigosecreto' ); ?></dt><dd class="mt-1 font-semibold"><a href="<?php echo esc_url( as_url( 'lista', $as_person->ID ) ); ?>" class="hover:text-grape"><?php echo (int) $as_stats['gifts']; ?></a></dd></div>
		</dl>
	</header>

	<div class="mt-12 grid items-start gap-10 lg:grid-cols-[1fr_20rem]">
		<section class="min-w-0" aria-labelledby="as-activity-title">
			<h2 id="as-activity-title" class="text-xl font-bold"><?php esc_html_e( 'Histórico de atividade', 'amigosecreto' ); ?></h2>

			<nav class="tabs mt-4" aria-label="<?php esc_attr_e( 'Filtrar atividade', 'amigosecreto' ); ?>">
				<?php foreach ( $as_groups as $as_key => [ $as_label ] ) : ?>
					<a href="<?php echo esc_url( $as_url( [ 'tipo' => 'tudo' === $as_key ? '' : $as_key ] ) ); ?>" <?php echo $as_key === $as_filter ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $as_label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php if ( ! $as_rows ) : ?>
				<div class="card mt-6 p-10 text-center text-ink/60">
					<p class="text-4xl" aria-hidden="true">🕰️</p>
					<p class="mt-3"><?php esc_html_e( 'Nada registrado para este filtro ainda.', 'amigosecreto' ); ?></p>
				</div>
			<?php else : ?>
				<div class="card mt-6 p-4 sm:p-6">
					<?php
					$as_day = '';
					foreach ( $as_rows as $as_row ) :
						[ $as_ico, $as_text, $as_tone ] = as_activity_describe( $as_row );
						$as_row_day = wp_date( 'Y-m-d', $as_row->time );
						if ( $as_row_day !== $as_day ) :
							if ( $as_day ) {
								echo '</ol>';
							}
							$as_day   = $as_row_day;
							$as_label = match ( $as_row_day ) {
								wp_date( 'Y-m-d' )                          => __( 'Hoje', 'amigosecreto' ),
								wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ) => __( 'Ontem', 'amigosecreto' ),
								default                                     => wp_date( $as_date, $as_row->time ),
							};
							?>
							<h3 class="mt-8 mb-2 pl-1 text-xs font-semibold tracking-wide text-grape uppercase first:mt-0"><?php echo esc_html( $as_label ); ?></h3>
							<ol class="activity">
						<?php endif; ?>
						<li class="activity-item<?php echo $as_tone ? ' is-' . esc_attr( $as_tone ) : ''; ?>">
							<time class="activity-time" datetime="<?php echo esc_attr( gmdate( 'c', $as_row->time ) ); ?>" title="<?php echo esc_attr( wp_date( $as_date . ', H:i:s', $as_row->time ) ); ?>"><?php echo esc_html( wp_date( 'H:i', $as_row->time ) ); ?></time>
							<span class="activity-icon"><?php echo as_icon( $as_ico, 'size-4' ); // phpcs:ignore ?></span>
							<div class="min-w-0">
								<p class="activity-text"><?php echo wp_kses_post( $as_text ); ?></p>
								<?php
								$as_meta = array_filter( [
									$as_row->ip ? 'IP ' . $as_row->ip : '',
									as_agent_label( $as_row->detail['agent'] ?? '' ),
									! empty( $as_row->detail['backfill'] ) ? __( 'registro anterior ao histórico (data aproximada)', 'amigosecreto' ) : '',
								] );
								if ( $as_meta ) :
									?>
									<p class="activity-meta"><?php echo esc_html( implode( ' · ', $as_meta ) ); ?></p>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
					</ol>
				</div>

				<?php if ( $as_more || $as_page > 1 ) : ?>
					<div class="mt-6 flex flex-wrap gap-2">
						<?php if ( $as_page > 1 ) : ?>
							<a href="<?php echo esc_url( $as_url( [ 'tipo' => 'tudo' === $as_filter ? '' : $as_filter, 'pagina' => $as_page > 2 ? $as_page - 1 : 0 ] ) ); ?>" class="btn btn-ghost btn-sm"><?php echo as_icon( 'back', 'size-4' ); // phpcs:ignore ?> <?php esc_html_e( 'Mais recentes', 'amigosecreto' ); ?></a>
						<?php endif; ?>
						<?php if ( $as_more ) : ?>
							<a href="<?php echo esc_url( $as_url( [ 'tipo' => 'tudo' === $as_filter ? '' : $as_filter, 'pagina' => $as_page + 1 ] ) ); ?>" class="btn btn-ghost btn-sm"><?php esc_html_e( 'Mais antigos', 'amigosecreto' ); ?> <?php echo as_icon( 'arrow', 'size-4' ); // phpcs:ignore ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<p class="mt-6 text-xs text-ink/40">
				<?php
				/* translators: %d: dias */
				printf( esc_html__( 'Os registros são guardados por %d dias. O histórico mostra que a pessoa abriu o resultado de um sorteio, mas nunca quem ela tirou.', 'amigosecreto' ), (int) AS_ACTIVITY_RETENTION_DAYS );
				?>
			</p>
		</section>

		<aside class="min-w-0 space-y-4 lg:sticky lg:top-24" aria-labelledby="as-person-groups">
			<h2 id="as-person-groups" class="text-xl font-bold"><?php esc_html_e( 'Sorteios', 'amigosecreto' ); ?></h2>
			<?php if ( $as_person_groups ) : ?>
				<ul class="card divide-y divide-ink/5">
					<?php foreach ( $as_person_groups as $as_g ) : ?>
						<li>
							<a href="<?php echo esc_url( $as_g['url'] ); ?>" class="group block p-4 transition hover:bg-snow">
								<span class="block font-semibold leading-snug group-hover:text-grape"><?php echo esc_html( $as_g['title'] ); ?></span>
								<span class="mt-1.5 flex flex-wrap gap-1.5">
									<?php if ( $as_g['organizes'] ) : ?>
										<span class="chip chip-grape"><?php esc_html_e( 'organiza', 'amigosecreto' ); ?></span>
									<?php endif; ?>
									<?php if ( $as_g['participant'] ) : ?>
										<span class="chip"><?php esc_html_e( 'participa', 'amigosecreto' ); ?></span>
									<?php endif; ?>
									<?php if ( ! $as_g['drawn'] ) : ?>
										<span class="chip"><?php esc_html_e( 'aguardando sorteio', 'amigosecreto' ); ?></span>
									<?php elseif ( $as_g['participant'] ) : ?>
										<span class="chip <?php echo $as_g['revealed'] ? 'chip-success' : 'chip-berry'; ?>"><?php echo $as_g['revealed'] ? esc_html__( 'já viu quem tirou', 'amigosecreto' ) : esc_html__( 'ainda não viu quem tirou', 'amigosecreto' ); ?></span>
									<?php else : ?>
										<span class="chip chip-success"><?php esc_html_e( 'sorteado', 'amigosecreto' ); ?></span>
									<?php endif; ?>
								</span>
								<span class="mt-1.5 block text-xs text-ink/50">
									<?php echo esc_html( implode( ' · ', array_filter( [ sprintf( _n( '%d participante', '%d participantes', $as_g['count'], 'amigosecreto' ), $as_g['count'] ), $as_g['date'] ] ) ) ); ?>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="card p-5 text-sm text-ink/60"><?php esc_html_e( 'Não está em nenhum sorteio.', 'amigosecreto' ); ?></p>
			<?php endif; ?>
		</aside>
	</div>
</div>

<?php
get_footer();
