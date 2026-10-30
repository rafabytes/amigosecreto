<?php
/**
 * Template padrão (posts e arquivos). O sistema usa as rotas de inc/router.php;
 * este arquivo cobre conteúdos comuns do WordPress que o admin venha a publicar.
 */

get_header();
?>

<div class="container-site max-w-3xl py-12">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class( 'card mb-6 p-8' ); ?>>
				<h2 class="text-2xl font-bold"><a href="<?php the_permalink(); ?>" class="hover:text-grape"><?php the_title(); ?></a></h2>
				<div class="prose-site mt-4"><?php the_excerpt(); ?></div>
			</article>
		<?php endwhile; ?>
		<div class="mt-8"><?php the_posts_pagination(); ?></div>
	<?php else : ?>
		<p class="text-ink/60"><?php esc_html_e( 'Nada por aqui ainda.', 'amigosecreto' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
