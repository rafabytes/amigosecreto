<?php
/**
 * /grupos/{id}/ — a página inteira é um componente React (GroupPage) com os dados iniciais do grupo.
 * Permissão já verificada em inc/router.php.
 */

$as_group = as_group_payload( (int) get_query_var( 'as_id' ) );

get_header();
?>

<div class="container-site py-10 sm:py-14">
	<a href="<?php echo esc_url( as_url( 'painel' ) ); ?>" class="back-link"><?php echo as_icon( 'back', 'size-4' ); // phpcs:ignore ?> <?php esc_html_e( 'Meus grupos', 'amigosecreto' ); ?></a>

	<?php
	as_island(
		'GroupPage',
		[ 'initialGroup' => $as_group ],
		'<h1 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-4xl">' . esc_html( $as_group['title'] ) . '</h1><div class="skeleton mt-8 h-64"></div>'
	);
	?>
</div>

<?php
get_footer();
