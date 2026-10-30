<?php
/**
 * /minha-lista/ — editor da lista de presentes do usuário logado (React: WishlistEditor).
 */

$as_gifts = array_map( 'as_gift_payload', as_user_gifts( get_current_user_id() ) );

get_header();
?>

<div class="container-site max-w-3xl py-10 sm:py-14">
	<p class="eyebrow"><?php esc_html_e( 'Minha lista', 'amigosecreto' ); ?></p>
	<h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl"><?php esc_html_e( 'O que você gostaria de ganhar?', 'amigosecreto' ); ?></h1>
	<p class="mt-3 text-ink/60"><?php esc_html_e( 'Adicione ideias com links das lojas. Quem tirar você vê esta lista em todos os grupos de que você participa.', 'amigosecreto' ); ?></p>

	<div class="mt-8">
		<?php as_island( 'WishlistEditor', [ 'initialGifts' => $as_gifts ], '<div class="skeleton h-40"></div>' ); ?>
	</div>
</div>

<?php
get_footer();
