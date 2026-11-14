<?php
/**
 * Resumo de um sorteio na lista do painel: data, papel do usuário, situação do sorteio, detalhes,
 * participantes e o próximo passo (descobrir quem tirou, sortear, acompanhar quem já viu…).
 *
 * @var array $args { group: array } resultado de as_group_summary().
 */

$as_g     = $args['group'];
$as_days  = $as_g['daysUntil'];
$as_ts    = $as_g['eventDate'] ? strtotime( $as_g['eventDate'] . ' 12:00:00' ) : 0;
$as_shown = array_slice( $as_g['participants'], 0, 6 );
$as_more  = $as_g['count'] - count( $as_shown );
$as_left  = max( 0, $as_g['minParticipants'] - $as_g['count'] );

if ( null === $as_days ) {
	$as_when = '';
} elseif ( 0 === $as_days ) {
	$as_when = __( 'É hoje! 🎉', 'amigosecreto' );
} elseif ( 1 === $as_days ) {
	$as_when = __( 'É amanhã', 'amigosecreto' );
} elseif ( $as_days > 1 ) {
	/* translators: %d: número de dias */
	$as_when = sprintf( _n( 'Falta %d dia', 'Faltam %d dias', $as_days, 'amigosecreto' ), $as_days );
} elseif ( -1 === $as_days ) {
	$as_when = __( 'Foi ontem', 'amigosecreto' );
} else {
	/* translators: %d: número de dias */
	$as_when = sprintf( __( 'Foi há %d dias', 'amigosecreto' ), abs( $as_days ) );
}

$as_roles = [
	'organizer'   => [ __( 'Você organiza', 'amigosecreto' ), 'chip-grape' ],
	'participant' => [ __( 'Você participa', 'amigosecreto' ), '' ],
	'admin'       => [ __( 'Administração', 'amigosecreto' ), '' ],
];
[ $as_role_label, $as_role_class ] = $as_roles[ $as_g['role'] ];
?>
<a href="<?php echo esc_url( $as_g['url'] ); ?>" class="card card-hover group flex gap-4 p-5 sm:gap-5 sm:p-6<?php echo $as_g['isPast'] ? ' opacity-75 hover:opacity-100' : ''; ?>">
	<div class="flex h-fit w-14 shrink-0 flex-col items-center justify-center rounded-2xl py-2.5 sm:w-16 <?php echo $as_g['isPast'] ? 'bg-ink/5 text-ink/50' : 'bg-grape/10 text-grape'; ?>"
		<?php echo $as_ts ? 'title="' . esc_attr( $as_g['eventDateText'] ) . '"' : ''; ?>>
		<?php if ( $as_ts ) : ?>
			<span class="text-2xl font-extrabold leading-none"><?php echo esc_html( date_i18n( 'j', $as_ts ) ); ?></span>
			<span class="mt-0.5 text-xs font-semibold uppercase tracking-wide"><?php echo esc_html( date_i18n( 'M', $as_ts ) ); ?></span>
			<?php if ( wp_date( 'Y' ) !== date_i18n( 'Y', $as_ts ) ) : ?>
				<span class="text-[0.65rem] font-medium opacity-70"><?php echo esc_html( date_i18n( 'Y', $as_ts ) ); ?></span>
			<?php endif; ?>
		<?php else : ?>
			<?php echo as_icon( 'gift', 'size-7' ); // phpcs:ignore ?>
			<span class="mt-1 text-[0.65rem] font-medium leading-tight opacity-70"><?php esc_html_e( 'sem data', 'amigosecreto' ); ?></span>
		<?php endif; ?>
	</div>

	<div class="min-w-0 flex-1">
		<div class="flex flex-wrap items-center gap-1.5">
			<span class="chip <?php echo esc_attr( $as_role_class ); ?>"><?php echo esc_html( $as_role_label ); ?></span>
			<?php if ( $as_g['isDrawn'] ) : ?>
				<span class="chip chip-success"><?php echo as_icon( 'check', 'size-3.5' ); // phpcs:ignore ?> <?php esc_html_e( 'Sorteado', 'amigosecreto' ); ?></span>
			<?php else : ?>
				<span class="chip"><?php echo as_icon( 'clock', 'size-3.5' ); // phpcs:ignore ?> <?php esc_html_e( 'Aguardando sorteio', 'amigosecreto' ); ?></span>
			<?php endif; ?>
			<?php if ( $as_when ) : ?>
				<span class="chip <?php echo ( $as_days >= 0 && $as_days <= 7 ) ? 'chip-berry' : ''; ?>"><?php echo esc_html( $as_when ); ?></span>
			<?php endif; ?>
		</div>

		<h3 class="mt-2.5 text-lg font-bold leading-snug text-balance group-hover:text-grape"><?php echo esc_html( $as_g['title'] ); ?></h3>

		<?php if ( $as_g['excerpt'] ) : ?>
			<p class="mt-1 line-clamp-2 text-sm text-ink/60"><?php echo esc_html( $as_g['excerpt'] ); ?></p>
		<?php endif; ?>

		<ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-sm text-ink/60">
			<?php if ( 'organizer' !== $as_g['role'] && $as_g['organizer'] ) : ?>
				<li class="flex items-center gap-1.5"><?php echo as_icon( 'user', 'size-4 shrink-0' ); // phpcs:ignore ?>
					<?php
					/* translators: %s: nome do organizador */
					printf( esc_html__( 'Organizado por %s', 'amigosecreto' ), '<strong class="font-semibold text-ink/80">' . esc_html( $as_g['organizer']['name'] ) . '</strong>' );
					?>
				</li>
			<?php endif; ?>
			<li class="flex items-center gap-1.5"><?php echo as_icon( 'users', 'size-4 shrink-0' ); // phpcs:ignore ?>
				<?php echo esc_html( sprintf( _n( '%d participante', '%d participantes', $as_g['count'], 'amigosecreto' ), $as_g['count'] ) ); ?>
			</li>
			<?php if ( $as_g['location'] ) : ?>
				<li class="flex min-w-0 items-center gap-1.5"><?php echo as_icon( 'pin', 'size-4 shrink-0' ); // phpcs:ignore ?> <span class="truncate"><?php echo esc_html( $as_g['location'] ); ?></span></li>
			<?php endif; ?>
			<?php if ( $as_g['budget'] ) : ?>
				<li class="flex items-center gap-1.5"><?php echo as_icon( 'money', 'size-4 shrink-0' ); // phpcs:ignore ?> <?php echo esc_html( $as_g['budget'] ); ?></li>
			<?php endif; ?>
		</ul>

		<div class="mt-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-t border-ink/5 pt-4">
			<div class="flex -space-x-2">
				<?php foreach ( $as_shown as $as_p ) : ?>
					<?php echo as_avatar_html( $as_p, 'size-8 text-xs ring-2 ring-white' ); // phpcs:ignore ?>
				<?php endforeach; ?>
				<?php if ( $as_more > 0 ) : ?>
					<span class="avatar size-8 bg-none bg-ink/10 text-xs text-ink ring-2 ring-white">+<?php echo (int) $as_more; ?></span>
				<?php endif; ?>
				<?php if ( ! $as_shown ) : ?>
					<span class="text-sm text-ink/40"><?php esc_html_e( 'Ninguém adicionado ainda', 'amigosecreto' ); ?></span>
				<?php endif; ?>
			</div>

			<div class="flex flex-wrap items-center justify-end gap-x-4 gap-y-2 text-sm">
				<?php if ( $as_g['isParticipant'] && $as_g['isDrawn'] ) : ?>
					<?php if ( $as_g['iRevealed'] && $as_g['myMatch'] ) : ?>
						<span class="flex items-center gap-2 text-ink/60">
							<?php esc_html_e( 'Você tirou', 'amigosecreto' ); ?>
							<?php echo as_avatar_html( $as_g['myMatch'], 'size-6 text-[0.6rem]' ); // phpcs:ignore ?>
							<strong class="font-semibold text-ink"><?php echo esc_html( $as_g['myMatch']['name'] ); ?></strong>
						</span>
					<?php elseif ( ! $as_g['isPast'] ) : ?>
						<span class="btn btn-accent btn-sm">🎁 <?php esc_html_e( 'Descobrir quem tirei', 'amigosecreto' ); ?></span>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $as_g['isManager'] && $as_g['isDrawn'] && $as_g['count'] ) : ?>
					<span class="flex items-center gap-2 text-ink/60" title="<?php esc_attr_e( 'Quantos participantes já abriram o resultado', 'amigosecreto' ); ?>">
						<span class="h-1.5 w-16 overflow-hidden rounded-full bg-ink/10"><span class="block h-full rounded-full bg-mint" style="width: <?php echo (int) round( 100 * $as_g['revealedCount'] / $as_g['count'] ); ?>%"></span></span>
						<?php
						/* translators: 1: quantos viram, 2: total de participantes */
						printf( esc_html__( '%1$d de %2$d já viram', 'amigosecreto' ), (int) $as_g['revealedCount'], (int) $as_g['count'] );
						?>
					</span>
				<?php elseif ( $as_g['isManager'] && ! $as_g['isDrawn'] ) : ?>
					<?php if ( $as_left ) : ?>
						<span class="text-ink/60">
							<?php
							/* translators: %d: participantes que faltam */
							echo esc_html( sprintf( _n( 'Adicione mais %d participante para sortear', 'Adicione mais %d participantes para sortear', $as_left, 'amigosecreto' ), $as_left ) );
							?>
						</span>
					<?php else : ?>
						<span class="flex items-center gap-1.5 font-semibold text-grape"><?php esc_html_e( 'Pronto para sortear', 'amigosecreto' ); ?> <?php echo as_icon( 'arrow', 'size-4 transition group-hover:translate-x-0.5' ); // phpcs:ignore ?></span>
					<?php endif; ?>
				<?php elseif ( ! $as_g['isDrawn'] ) : ?>
					<span class="text-ink/50"><?php esc_html_e( 'O organizador ainda vai sortear', 'amigosecreto' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	</div>
</a>
