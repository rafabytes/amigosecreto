<?php
/**
 * Páginas e posts avulsos (ex.: política de privacidade, regras do sorteio).
 */

get_header();
?>

<div class="container-site max-w-3xl py-12">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class( 'card p-8 sm:p-12' ); ?>>
			<h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl"><?php the_title(); ?></h1>
			<div class="prose-site mt-6"><?php the_content(); ?></div>
		</article>
	<?php endwhile; ?>
</div>

<?php
get_footer();
