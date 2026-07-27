<?php
namespace OrillaEagles\Ledger\Admin;

defined( 'ABSPATH' ) || exit;

final class LedgerScreen {

	public static function enqueue( string $hook ): void {
		// Submenu hook suffix contains the page slug for the Ledger screen.
		if ( strpos( $hook, 'tml-ledger' ) === false ) {
			return;
		}
		$asset_file = TML_DIR . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'tml-ledger-app',
			plugins_url( 'build/index.js', TML_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'tml-ledger-app', 'team-membership-ledger' );
		// DataViews styles are bundled into build/index.js; the host's component
		// styles are still needed for Modal/Button/SelectControl chrome.
		wp_enqueue_style( 'wp-components' );

		wp_localize_script(
			'tml-ledger-app',
			'tmlLedger',
			array(
				'productId' => isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			)
		);
	}

	public static function render(): void {
		if ( ! current_user_can( Menu::CAP ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ledger', 'team-membership-ledger' ); ?></h1>
			<div id="tml-ledger-root"></div>
		</div>
		<?php
	}
}
