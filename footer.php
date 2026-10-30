<?php
/**
 * Rodapé do site.
 */
?>
</main>

<footer class="border-t border-ink/5 py-8 text-center text-sm text-ink/50">
	<div class="container-site space-y-3">
		<p>
			<?php
			printf(
				/* translators: %s: nome do site */
				esc_html__( '%s · sorteie, monte sua lista e surpreenda. 🎄', 'amigosecreto' ),
				esc_html( get_bloginfo( 'name' ) )
			);
			?>
		</p>
		<?php if ( as_legal_links() ) : ?>
			<nav class="flex flex-wrap justify-center gap-x-5 gap-y-1" aria-label="<?php esc_attr_e( 'Informações legais', 'amigosecreto' ); ?>">
				<?php foreach ( as_legal_links() as $as_label => $as_url ) : ?>
					<a href="<?php echo esc_url( $as_url ); ?>" class="hover:text-grape hover:underline"><?php echo esc_html( $as_label ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
