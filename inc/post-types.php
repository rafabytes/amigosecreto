<?php
/**
 * Tipos de conteúdo:
 *  - as_grupo:    grupo de sorteio. Autor = organizador. Participantes ficam em metadados
 *                 repetidos (_as_participant), um registro por usuário, para facilitar consultas.
 *  - as_presente: item da lista de presentes. Autor = dono da lista.
 *
 * Nenhum dos dois é público: o acesso no front é feito pelas rotas do tema e pela API REST,
 * que aplicam as regras de permissão.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	register_post_type( 'as_grupo', [
		'labels'          => [
			'name'          => __( 'Grupos', 'amigosecreto' ),
			'singular_name' => __( 'Grupo', 'amigosecreto' ),
			'add_new_item'  => __( 'Adicionar grupo', 'amigosecreto' ),
			'edit_item'     => __( 'Editar grupo', 'amigosecreto' ),
			'search_items'  => __( 'Buscar grupos', 'amigosecreto' ),
		],
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'show_in_rest'    => false,
		'menu_icon'       => 'dashicons-groups',
		'menu_position'   => 25,
		'supports'        => [ 'title', 'editor', 'author' ],
		'capability_type' => 'post',
		'map_meta_cap'    => true,
		'capabilities'    => [ 'create_posts' => 'manage_options' ],
	] );

	register_post_type( 'as_presente', [
		'labels'          => [
			'name'          => __( 'Presentes', 'amigosecreto' ),
			'singular_name' => __( 'Presente', 'amigosecreto' ),
			'edit_item'     => __( 'Editar presente', 'amigosecreto' ),
			'search_items'  => __( 'Buscar presentes', 'amigosecreto' ),
		],
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => 'edit.php?post_type=as_grupo',
		'show_in_rest'    => false,
		'supports'        => [ 'title', 'editor', 'author', 'page-attributes' ],
		'capability_type' => 'post',
		'map_meta_cap'    => true,
		'capabilities'    => [ 'create_posts' => 'manage_options' ],
	] );
} );
