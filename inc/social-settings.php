<?php
/**
 * Configurações → Login social: credenciais dos apps Google e Facebook.
 * Valores definidos por constante no wp-config.php têm prioridade e aparecem bloqueados aqui.
 */

defined( 'ABSPATH' ) || exit;

function as_social_fields() {
	return [
		'google'   => [
			'google_client_id'     => [ 'label' => __( 'Client ID', 'amigosecreto' ), 'type' => 'text' ],
			'google_client_secret' => [ 'label' => __( 'Client secret', 'amigosecreto' ), 'type' => 'password' ],
			'google_birthday'      => [
				'label' => __( 'Pedir data de nascimento', 'amigosecreto' ),
				'type'  => 'checkbox',
				'help'  => __( 'Usa o escopo user.birthday.read (People API). É um escopo sensível: em produção o app precisa passar pela verificação do Google.', 'amigosecreto' ),
			],
		],
		'facebook' => [
			'facebook_app_id'     => [ 'label' => __( 'App ID', 'amigosecreto' ), 'type' => 'text' ],
			'facebook_app_secret' => [ 'label' => __( 'App secret', 'amigosecreto' ), 'type' => 'password' ],
			'facebook_birthday'   => [
				'label' => __( 'Pedir data de nascimento', 'amigosecreto' ),
				'type'  => 'checkbox',
				'help'  => __( 'Usa a permissão user_birthday, que exige revisão do app (App Review) para usuários fora da equipe do app.', 'amigosecreto' ),
			],
		],
	];
}

add_action( 'admin_menu', function () {
	add_options_page(
		__( 'Login social', 'amigosecreto' ),
		__( 'Login social', 'amigosecreto' ),
		'manage_options',
		'as-social',
		'as_social_settings_page'
	);
} );

add_action( 'admin_init', function () {
	register_setting( 'as_social', 'as_social', [
		'type'              => 'array',
		'sanitize_callback' => function ( $input ) {
			$saved = get_option( 'as_social', [] );
			$clean = [];
			foreach ( as_social_fields() as $fields ) {
				foreach ( $fields as $key => $field ) {
					if ( 'checkbox' === $field['type'] ) {
						$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
					} elseif ( 'password' === $field['type'] && '' === trim( $input[ $key ] ?? '' ) ) {
						// Campo de segredo em branco = manter o valor salvo.
						$clean[ $key ] = $saved[ $key ] ?? '';
					} else {
						$clean[ $key ] = sanitize_text_field( $input[ $key ] ?? '' );
					}
				}
			}
			return $clean;
		},
	] );
} );

function as_social_settings_page() {
	$options = get_option( 'as_social', [] );
	$titles  = as_social_providers();
	$consoles = [
		'google'   => 'https://console.cloud.google.com/apis/credentials',
		'facebook' => 'https://developers.facebook.com/apps/',
	];
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Login social', 'amigosecreto' ); ?></h1>
		<p><?php esc_html_e( 'Permite que as pessoas entrem com Google ou Facebook. Contas com o mesmo e-mail são vinculadas automaticamente; foto e data de nascimento do provedor preenchem o perfil quando ainda estão vazios.', 'amigosecreto' ); ?></p>
		<?php if ( ! get_option( 'users_can_register' ) ) : ?>
			<div class="notice notice-info inline"><p>
				<?php
				printf(
					/* translators: %s: link para Configurações > Geral */
					esc_html__( 'O cadastro público está desligado: o login social só funciona para quem já tem conta (ex.: participantes convidados). Para permitir novas contas, ative "Qualquer pessoa pode se registrar" em %s.', 'amigosecreto' ),
					'<a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">' . esc_html__( 'Configurações → Geral', 'amigosecreto' ) . '</a>'
				);
				?>
			</p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'as_social' ); ?>

			<?php foreach ( as_social_fields() as $provider => $fields ) : ?>
				<h2>
					<?php echo esc_html( $titles[ $provider ] ); ?>
					<?php if ( as_social_enabled( $provider ) ) : ?>
						<span style="color:#00a32a;font-size:13px;font-weight:600;margin-left:6px">● <?php esc_html_e( 'ativo', 'amigosecreto' ); ?></span>
					<?php endif; ?>
				</h2>
				<p>
					<?php esc_html_e( 'URL de redirecionamento autorizada (cadastre no console do provedor):', 'amigosecreto' ); ?><br>
					<code><?php echo esc_html( as_social_callback_url( $provider ) ); ?></code>
					— <a href="<?php echo esc_url( $consoles[ $provider ] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'abrir console', 'amigosecreto' ); ?></a>
				</p>
				<table class="form-table" role="presentation">
					<?php foreach ( $fields as $key => $field ) : ?>
						<?php
						$constant = 'AS_' . strtoupper( $key );
						$locked   = defined( $constant );
						$value    = $options[ $key ] ?? '';
						?>
						<tr>
							<th scope="row"><label for="as-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
							<td>
								<?php if ( 'checkbox' === $field['type'] ) : ?>
									<label>
										<input type="checkbox" id="as-<?php echo esc_attr( $key ); ?>" name="as_social[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $locked ? constant( $constant ) : $value ); ?> <?php disabled( $locked ); ?>>
										<?php esc_html_e( 'Ativar', 'amigosecreto' ); ?>
									</label>
								<?php elseif ( 'password' === $field['type'] ) : ?>
									<input type="password" class="regular-text" id="as-<?php echo esc_attr( $key ); ?>" name="as_social[<?php echo esc_attr( $key ); ?>]" value="" autocomplete="new-password"
										placeholder="<?php echo esc_attr( $locked ? __( 'definido no wp-config.php', 'amigosecreto' ) : ( $value ? __( '•••••••• (salvo — deixe em branco para manter)', 'amigosecreto' ) : '' ) ); ?>" <?php disabled( $locked ); ?>>
								<?php else : ?>
									<input type="text" class="regular-text" id="as-<?php echo esc_attr( $key ); ?>" name="as_social[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $locked ? constant( $constant ) : $value ); ?>" <?php disabled( $locked ); ?>>
								<?php endif; ?>
								<?php if ( $locked ) : ?>
									<p class="description"><?php printf( esc_html__( 'Definido pela constante %s.', 'amigosecreto' ), '<code>' . esc_html( $constant ) . '</code>' ); ?></p>
								<?php endif; ?>
								<?php if ( ! empty( $field['help'] ) ) : ?>
									<p class="description"><?php echo esc_html( $field['help'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endforeach; ?>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
