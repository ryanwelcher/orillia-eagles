<?php
/**
 * Plugin Name:       Team Membership Ledger
 * Description:        Track who owes / who has paid for dues and events (offline-friendly) for the Orillia Eagles.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       team-membership-ledger
 *
 * @package OrillaEagles\Ledger
 */

defined( 'ABSPATH' ) || exit;

define( 'TML_VERSION', '0.1.0' );
define( 'TML_FILE', __FILE__ );
define( 'TML_DIR', plugin_dir_path( __FILE__ ) );

// Composer autoloader if present, else a minimal PSR-4 fallback for src/.
if ( file_exists( TML_DIR . 'vendor/autoload.php' ) ) {
	require TML_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		function ( $class ) {
			$prefix = 'OrillaEagles\\Ledger\\';
			if ( strpos( $class, $prefix ) !== 0 ) {
				return;
			}
			$path = TML_DIR . 'src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( file_exists( $path ) ) {
				require $path;
			}
		}
	);
}

// Declare HPOS (custom order tables) compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', TML_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Team Membership Ledger requires WooCommerce to be installed and active.', 'team-membership-ledger' );
					echo '</p></div>';
				}
			);
			return;
		}
		\OrillaEagles\Ledger\Plugin::instance()->boot();
	}
);
