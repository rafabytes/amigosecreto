<?php
/**
 * Ícones do site, manifest (Android/"instalar app") e meta tags de compartilhamento (Open Graph).
 *
 * Os arquivos ficam em assets/icons/ e são gerados por `npm run icons` (scripts/icons.js).
 * Usamos o "Ícone do site" nativo do WordPress: enquanto nenhum ícone for definido em
 * Aparência → Personalizar → Identidade do site, ele usa os ícones do tema. Assim o próprio
 * WordPress imprime as tags (site, login e admin) e responde /favicon.ico.
 */

defined( 'ABSPATH' ) || exit;

const AS_THEME_COLOR = '#6a00f4';

function as_icon_asset( $file ) {
	return AS_THEME_URI . '/assets/icons/' . $file;
}

add_filter( 'get_site_icon_url', function ( $url, $size ) {
	if ( get_option( 'site_icon' ) ) {
		return $url; // ícone definido pelo administrador tem prioridade
	}
	if ( $size <= 32 ) {
		return as_icon_asset( 'favicon-32.png' );
	}
	if ( 180 === (int) $size ) {
		return as_icon_asset( 'apple-touch-icon.png' );
	}
	return as_icon_asset( $size <= 192 ? 'icon-192.png' : 'icon-512.png' );
}, 10, 2 );

/**
 * Favicon SVG (nítido em qualquer tela), manifest e cor da barra do navegador no celular.
 */
function as_print_icon_tags() {
	if ( ! get_option( 'site_icon' ) ) {
		printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( as_icon_asset( 'favicon.svg' ) ) );
	}
	printf( '<link rel="manifest" href="%s">' . "\n", esc_url( home_url( 'manifest.webmanifest' ) ) );
	printf( '<meta name="theme-color" content="%s">' . "\n", esc_attr( AS_THEME_COLOR ) );
}
add_action( 'wp_head', 'as_print_icon_tags', 98 );
add_action( 'login_head', 'as_print_icon_tags', 98 );

/**
 * Open Graph / Twitter: título, descrição e imagem ao compartilhar links (WhatsApp, Facebook…).
 */
add_action( 'wp_head', function () {
	$description = get_bloginfo( 'description' )
		?: __( 'Organize o amigo secreto em minutos: monte o grupo, faça o sorteio e cada um cria sua lista de presentes com links.', 'amigosecreto' );

	$tags = [
		'og:type'             => 'website',
		'og:locale'           => 'pt_BR',
		'og:site_name'        => get_bloginfo( 'name' ),
		'og:title'            => wp_get_document_title(),
		'og:description'      => $description,
		'og:url'              => as_current_url(),
		'og:image'            => as_icon_asset( 'og-image.png' ),
		'og:image:width'      => '1200',
		'og:image:height'     => '630',
		'og:image:alt'        => get_bloginfo( 'name' ),
		'twitter:card'        => 'summary_large_image',
	];

	printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
	foreach ( $tags as $property => $content ) {
		$attr = str_starts_with( $property, 'twitter:' ) ? 'name' : 'property';
		printf( '<meta %s="%s" content="%s">' . "\n", $attr, esc_attr( $property ), esc_attr( $content ) );
	}
}, 5 );

/* -------------------------------------------------------------------------
 * Manifest: /manifest.webmanifest (dinâmico, para conhecer a URL do site)
 * ---------------------------------------------------------------------- */

add_action( 'init', function () {
	add_rewrite_rule( '^manifest\.webmanifest/?$', 'index.php?as_route=manifest', 'top' );
} );

add_action( 'template_redirect', function () {
	if ( 'manifest' !== get_query_var( 'as_route' ) ) {
		return;
	}

	$name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	status_header( 200 );
	header( 'Content-Type: application/manifest+json; charset=utf-8' );
	header( 'Cache-Control: public, max-age=86400' );

	echo wp_json_encode( [
		'name'             => $name,
		'short_name'       => $name,
		'description'      => get_bloginfo( 'description' ),
		'lang'             => 'pt-BR',
		'start_url'        => as_url( 'painel' ),
		'scope'            => home_url( '/' ),
		'display'          => 'standalone',
		'background_color' => '#f7f5fc',
		'theme_color'      => AS_THEME_COLOR,
		'icons'            => [
			[ 'src' => as_icon_asset( 'icon-192.png' ), 'sizes' => '192x192', 'type' => 'image/png' ],
			[ 'src' => as_icon_asset( 'icon-512.png' ), 'sizes' => '512x512', 'type' => 'image/png' ],
			[ 'src' => as_icon_asset( 'icon-maskable-512.png' ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ],
		],
	], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	exit;
}, 0 );
