<?php
/**
 * Cabeçalho do site. #site-header e #swup são os containers trocados pelo Swup a cada navegação.
 */

$as_route = as_current_route();
$as_nav   = is_user_logged_in() ? [
	'painel'      => [ __( 'Painel', 'amigosecreto' ), 'home' ],
	'minha-lista' => [ __( 'Minha lista', 'amigosecreto' ), 'gift' ],
	'perfil'      => [ __( 'Perfil', 'amigosecreto' ), 'user' ],
] : [];
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'min-h-dvh flex flex-col bg-snow text-ink font-sans antialiased' ); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#swup"><?php esc_html_e( 'Pular para o conteúdo', 'amigosecreto' ); ?></a>

<header id="site-header" class="sticky top-0 z-40 border-b border-ink/5 bg-snow/80 backdrop-blur">
	<div class="container-site flex h-16 items-center justify-between gap-4">
		<a href="<?php echo esc_url( is_user_logged_in() ? as_url( 'painel' ) : home_url( '/' ) ); ?>" class="flex items-center gap-2.5 text-lg font-extrabold tracking-tight">
			<span class="grid size-9 place-items-center rounded-xl bg-grape text-white shadow-lg shadow-grape/30"><?php echo as_icon( 'gift' ); // phpcs:ignore ?></span>
			<span class="hidden xs:inline"><?php bloginfo( 'name' ); ?></span>
		</a>

		<?php if ( is_user_logged_in() ) : ?>
			<nav class="flex items-center gap-1" aria-label="<?php esc_attr_e( 'Principal', 'amigosecreto' ); ?>">
				<?php foreach ( $as_nav as $as_slug => [ $as_label, $as_ico ] ) : ?>
					<a href="<?php echo esc_url( as_url( $as_slug ) ); ?>"
						class="nav-link<?php echo $as_route === $as_slug ? ' is-active' : ''; ?>"
						<?php echo $as_route === $as_slug ? 'aria-current="page"' : ''; ?>>
						<?php echo as_icon( $as_ico, 'size-5 sm:size-4' ); // phpcs:ignore ?>
						<span class="sr-only sm:not-sr-only"><?php echo esc_html( $as_label ); ?></span>
					</a>
				<?php endforeach; ?>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a href="<?php echo esc_url( admin_url() ); ?>" class="nav-link" data-no-swup title="<?php esc_attr_e( 'Administração', 'amigosecreto' ); ?>">
						<span class="text-xs font-bold">WP</span>
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" class="nav-link" data-no-swup title="<?php esc_attr_e( 'Sair', 'amigosecreto' ); ?>">
					<?php echo as_icon( 'logout', 'size-5 sm:size-4' ); // phpcs:ignore ?>
					<span class="sr-only"><?php esc_html_e( 'Sair', 'amigosecreto' ); ?></span>
				</a>
			</nav>
		<?php else : ?>
			<nav class="flex items-center gap-2">
				<?php if ( get_option( 'users_can_register' ) ) : ?>
					<a href="<?php echo esc_url( as_url( 'cadastro' ) ); ?>" class="btn btn-ghost hidden sm:inline-flex"><?php esc_html_e( 'Criar conta', 'amigosecreto' ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( as_url( 'entrar' ) ); ?>" class="btn btn-primary"><?php esc_html_e( 'Entrar', 'amigosecreto' ); ?></a>
			</nav>
		<?php endif; ?>
	</div>
</header>

<main id="swup" class="transition-fade flex-1">
