<?php
/**
 * Amigo Secreto — bootstrap do tema.
 */

defined( 'ABSPATH' ) || exit;

define( 'AS_THEME_VERSION', '1.1.0' );
define( 'AS_THEME_DIR', get_template_directory() );
define( 'AS_THEME_URI', get_template_directory_uri() );

require_once AS_THEME_DIR . '/inc/setup.php';
require_once AS_THEME_DIR . '/inc/roles.php';
require_once AS_THEME_DIR . '/inc/post-types.php';
require_once AS_THEME_DIR . '/inc/helpers.php';
require_once AS_THEME_DIR . '/inc/icons.php';
require_once AS_THEME_DIR . '/inc/site-icons.php';
require_once AS_THEME_DIR . '/inc/profile.php';
require_once AS_THEME_DIR . '/inc/groups.php';
require_once AS_THEME_DIR . '/inc/wishlist.php';
require_once AS_THEME_DIR . '/inc/emails.php';
require_once AS_THEME_DIR . '/inc/router.php';
require_once AS_THEME_DIR . '/inc/auth.php';
require_once AS_THEME_DIR . '/inc/social-login.php';
require_once AS_THEME_DIR . '/inc/social-settings.php';
require_once AS_THEME_DIR . '/inc/rest-api.php';
require_once AS_THEME_DIR . '/inc/admin.php';
require_once AS_THEME_DIR . '/inc/legal.php';
