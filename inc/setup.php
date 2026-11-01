<?php
/**
 * Configuração geral do tema: suportes, assets, barra de admin e acesso ao wp-admin.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	load_theme_textdomain( 'amigosecreto', AS_THEME_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );
	add_theme_support( 'custom-logo', [ 'height' => 64, 'width' => 64, 'flex-width' => true ] );
} );

/**
 * Assets compilados pelo Parcel (npm run build / npm run dev).
 */
add_action( 'wp_enqueue_scripts', function () {
	$css = AS_THEME_DIR . '/dist/css/app.css';
	$js  = AS_THEME_DIR . '/dist/js/app.js';

	wp_enqueue_style(
		'amigosecreto',
		AS_THEME_URI . '/dist/css/app.css',
		[],
		file_exists( $css ) ? (string) filemtime( $css ) : AS_THEME_VERSION
	);

	wp_enqueue_script(
		'amigosecreto',
		AS_THEME_URI . '/dist/js/app.js',
		[],
		file_exists( $js ) ? (string) filemtime( $js ) : AS_THEME_VERSION,
		[ 'in_footer' => true, 'strategy' => 'defer' ]
	);

	$user = wp_get_current_user();

	wp_localize_script( 'amigosecreto', 'AmigoSecreto', [
		'restUrl' => esc_url_raw( rest_url( 'amigosecreto/v1/' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
		'homeUrl' => esc_url_raw( home_url( '/' ) ),
		'icons'   => as_icon_paths(),
		// Botão "Sign in with Google" (src/js/lib/google.js); vazio se o Google não estiver configurado.
		'googleClientId' => as_social_enabled( 'google' ) ? (string) as_social_setting( 'google_client_id' ) : '',
		'routes'  => [
			'dashboard'    => as_url( 'painel' ),
			'wishlist'     => as_url( 'minha-lista' ),
			'myPublicList' => $user->exists() ? as_url( 'lista', $user->ID ) : null,
		],
		'user'    => $user->exists() ? [
			'id'           => $user->ID,
			'name'         => $user->display_name,
			'canOrganize'  => as_user_can_organize( $user->ID ),
		] : null,
	] );
} );

/*
 * Otimizadores de JS (LiteSpeed Cache etc.) não devem mexer no bundle nem nos dados do
 * wp_localize_script: o app.js já vem minificado pelo Parcel, e o minificador do LiteSpeed
 * corrompe o código do React (troca "for(...;;)" por "for(...;)"), quebrando todo o front.
 * Também evita o "Delay JS", que só carregaria o botão do Google após interação do usuário.
 */
add_filter( 'script_loader_tag', function ( $tag, $handle ) {
	if ( 'amigosecreto' !== $handle ) {
		return $tag;
	}

	return preg_replace( '/<script(?![^>]*data-no-optimize)/', '<script data-no-optimize="1"', $tag );
}, 10, 2 );

add_filter( 'wp_inline_script_attributes', function ( $attributes ) {
	if ( str_starts_with( (string) ( $attributes['id'] ?? '' ), 'amigosecreto-js-' ) ) {
		$attributes['data-no-optimize'] = '1';
	}

	return $attributes;
} );

// Remove estilos de blocos que não usamos no front.
add_action( 'wp_enqueue_scripts', function () {
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'global-styles' );
	wp_dequeue_style( 'classic-theme-styles' );
}, 100 );

/**
 * Só administradores veem a barra de admin e acessam o wp-admin.
 */
add_filter( 'show_admin_bar', function ( $show ) {
	return current_user_can( 'manage_options' ) ? $show : false;
} );

add_action( 'admin_init', function () {
	if ( wp_doing_ajax() || current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_safe_redirect( as_url( 'painel' ) );
	exit;
} );

add_filter( 'body_class', function ( $classes ) {
	$route = get_query_var( 'as_route' );
	if ( $route ) {
		$classes[] = 'route-' . sanitize_html_class( $route );
	}
	return $classes;
} );
