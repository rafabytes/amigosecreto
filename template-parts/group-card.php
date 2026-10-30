<?php
/**
 * Card de grupo no painel.
 *
 * @var array $args { group: array } payload de as_group_payload().
 */

$as_g = $args['group'];
?>
<a href="<?php echo esc_url( $as_g['url'] ); ?>" class="card card-hover group flex flex-col p-6">
	<div class="flex items-start justify-between gap-3">
		<h3 class="text-lg font-bold leading-snug group-hover:text-grape"><?php echo esc_html( $as_g['title'] ); ?></h3>
		<?php if ( $as_g['isDrawn'] ) : ?>
			<span class="chip chip-success shrink-0"><?php echo as_icon( 'check', 'size-3.5' ); // phpcs:ignore ?> <?php esc_html_e( 'Sorteado', 'amigosecreto' ); ?></span>
		<?php else : ?>
			<span class="chip shrink-0"><?php esc_html_e( 'Aguardando', 'amigosecreto' ); ?></span>
		<?php endif; ?>
	</div>

	<ul class="mt-4 space-y-1.5 text-sm text-ink/60">
		<li class="flex items-center gap-2"><?php echo as_icon( 'users', 'size-4' ); // phpcs:ignore ?>
			<?php echo esc_html( sprintf( _n( '%d participante', '%d participantes', count( $as_g['participants'] ), 'amigosecreto' ), count( $as_g['participants'] ) ) ); ?>
		</li>
		<?php if ( $as_g['eventDateText'] ) : ?>
			<li class="flex items-center gap-2"><?php echo as_icon( 'calendar', 'size-4' ); // phpcs:ignore ?> <?php echo esc_html( $as_g['eventDateText'] ); ?></li>
		<?php endif; ?>
		<?php if ( $as_g['budget'] ) : ?>
			<li class="flex items-center gap-2"><?php echo as_icon( 'money', 'size-4' ); // phpcs:ignore ?> <?php echo esc_html( $as_g['budget'] ); ?></li>
		<?php endif; ?>
	</ul>

	<div class="mt-auto flex items-center justify-between gap-3 pt-6">
		<div class="flex -space-x-2">
			<?php foreach ( array_slice( $as_g['participants'], 0, 5 ) as $as_p ) : ?>
				<?php echo as_avatar_html( $as_p, 'size-8 text-xs ring-2 ring-white' ); // phpcs:ignore ?>
			<?php endforeach; ?>
			<?php if ( count( $as_g['participants'] ) > 5 ) : ?>
				<span class="avatar size-8 bg-ink/10 text-xs text-ink ring-2 ring-white">+<?php echo (int) ( count( $as_g['participants'] ) - 5 ); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( $as_g['isManager'] ) : ?>
			<span class="text-xs font-semibold uppercase tracking-wide text-grape"><?php esc_html_e( 'Organizador', 'amigosecreto' ); ?></span>
		<?php elseif ( $as_g['isDrawn'] && $as_g['isParticipant'] ) : ?>
			<span class="text-xs font-semibold uppercase tracking-wide text-berry"><?php esc_html_e( 'Ver quem tirei', 'amigosecreto' ); ?></span>
		<?php endif; ?>
	</div>
</a>
