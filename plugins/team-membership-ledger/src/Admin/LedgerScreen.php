<?php
namespace OrillaEagles\Ledger\Admin;

use OrillaEagles\Ledger\Data\OrderRepository;
use OrillaEagles\Ledger\Data\RosterRepository;
use OrillaEagles\Ledger\Domain\LedgerCalculator;
use OrillaEagles\Ledger\Domain\LedgerRow;
use OrillaEagles\Ledger\Domain\MemberStatus;

defined( 'ABSPATH' ) || exit;

final class LedgerScreen {

	private static function label( string $status ): string {
		switch ( $status ) {
			case MemberStatus::PAID:
				return __( 'Paid', 'team-membership-ledger' );
			case MemberStatus::OWES:
				return __( 'Owes', 'team-membership-ledger' );
			default:
				return __( 'Not entered', 'team-membership-ledger' );
		}
	}

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

	public static function handlePost(): void {
		if ( empty( $_POST['tml_ledger_action'] ) ) {
			return;
		}
		if ( ! current_user_can( Menu::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'team-membership-ledger' ) );
		}
		check_admin_referer( 'tml_ledger' );

		$action     = sanitize_key( wp_unslash( $_POST['tml_ledger_action'] ) );
		$product_id = absint( $_POST['product_id'] ?? 0 );
		$order_id   = absint( $_POST['order_id'] ?? 0 );
		$member_id  = absint( $_POST['member_id'] ?? 0 );

		$orders = new OrderRepository();

		try {
			// Validate the action and its own inputs before resolving/creating any order.
			$amount = 0.0;
			if ( 'add_payment' === $action ) {
				$amount = round( (float) wp_unslash( $_POST['amount'] ?? 0 ), 2 );
				if ( $amount <= 0 ) {
					throw new \RuntimeException( __( 'Enter a payment amount greater than zero.', 'team-membership-ledger' ) );
				}
			} elseif ( 'mark_paid' !== $action ) {
				throw new \RuntimeException( __( 'Unknown action.', 'team-membership-ledger' ) );
			}

			// Resolve the target order: an existing one, or create it on the fly.
			if ( ! $order_id ) {
				if ( ! $member_id || ! $product_id ) {
					throw new \RuntimeException( __( 'No order to act on.', 'team-membership-ledger' ) );
				}
				$order_id = $orders->createRequestedOrder( $member_id, $product_id );
			}

			if ( 'mark_paid' === $action ) {
				$orders->markPaid( $order_id );
				$msg = __( 'Marked paid.', 'team-membership-ledger' );
			} else {
				$orders->addPayment( $order_id, $amount );
				$msg = __( 'Payment recorded.', 'team-membership-ledger' );
			}

			set_transient( 'tml_ledger_notice', $msg, 30 );
		} catch ( \RuntimeException $e ) {
			set_transient( 'tml_ledger_error', $e->getMessage(), 30 );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'tml-ledger', 'product_id' => $product_id ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private static function actions( LedgerRow $row, int $product_id ): string {
		if ( MemberStatus::PAID === $row->status() ) {
			return '';
		}
		if ( $row->orderCount() > 1 ) {
			return esc_html__( 'Multiple orders — manage in WooCommerce', 'team-membership-ledger' );
		}

		$order_id = $row->singleOrderId();
		ob_start();
		?>
		<form method="post" style="display:inline-block;margin:0 .5em .25em 0">
			<?php wp_nonce_field( 'tml_ledger' ); ?>
			<input type="hidden" name="tml_ledger_action" value="mark_paid" />
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>" />
			<?php if ( $order_id ) : ?>
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>" />
			<?php else : ?>
				<input type="hidden" name="member_id" value="<?php echo esc_attr( $row->memberId() ); ?>" />
			<?php endif; ?>
			<button class="button button-primary"><?php esc_html_e( 'Mark Paid', 'team-membership-ledger' ); ?></button>
		</form>
		<form method="post" style="display:inline-block;margin:0">
			<?php wp_nonce_field( 'tml_ledger' ); ?>
			<input type="hidden" name="tml_ledger_action" value="add_payment" />
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>" />
			<?php if ( $order_id ) : ?>
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>" />
			<?php else : ?>
				<input type="hidden" name="member_id" value="<?php echo esc_attr( $row->memberId() ); ?>" />
			<?php endif; ?>
			<input type="number" step="0.01" min="0.01" name="amount" placeholder="<?php esc_attr_e( 'Amount', 'team-membership-ledger' ); ?>" style="width:6em" />
			<button class="button"><?php esc_html_e( 'Add', 'team-membership-ledger' ); ?></button>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function render(): void {
		if ( ! current_user_can( Menu::CAP ) ) {
			return;
		}
		$order_repo  = new OrderRepository();
		$roster_repo = new RosterRepository();

		$products   = $order_repo->sellableProducts();
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$rows = array();
		if ( $product_id ) {
			$members = $roster_repo->activeMembers();
			$records = $order_repo->productRecords( $product_id );
			$rows    = LedgerCalculator::forProduct( $members, $records );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ledger', 'team-membership-ledger' ); ?></h1>
			<div id="tml-ledger-root"></div>
			<?php
			$notice = get_transient( 'tml_ledger_notice' );
			$error  = get_transient( 'tml_ledger_error' );
			delete_transient( 'tml_ledger_notice' );
			delete_transient( 'tml_ledger_error' );
			if ( $notice ) {
				echo '<div class="notice notice-success"><p>' . esc_html( $notice ) . '</p></div>';
			}
			if ( $error ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
			}
			?>
			<form method="get">
				<input type="hidden" name="page" value="tml-ledger" />
				<select name="product_id" onchange="this.form.submit()">
					<option value="0"><?php esc_html_e( '— Select a product —', 'team-membership-ledger' ); ?></option>
					<?php foreach ( $products as $p ) : ?>
						<option value="<?php echo esc_attr( $p['id'] ); ?>" <?php selected( $product_id, $p['id'] ); ?>>
							<?php echo esc_html( $p['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</form>

			<?php if ( $product_id ) : ?>
			<table class="widefat striped" style="margin-top:1em">
				<thead><tr>
					<th><?php esc_html_e( 'Member', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Status', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Qty', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Total', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Paid', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Balance', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Date', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'team-membership-ledger' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->name() ); ?></td>
						<td><?php echo esc_html( self::label( $row->status() ) ); ?></td>
						<td><?php echo esc_html( (string) $row->qty() ); ?></td>
						<td><?php echo wp_kses_post( wc_price( $row->total() ) ); ?></td>
						<td><?php echo wp_kses_post( wc_price( $row->paid() ) ); ?></td>
						<td><?php echo wp_kses_post( wc_price( $row->balance() ) ); ?></td>
						<td><?php echo esc_html( $row->date() ? substr( $row->date(), 0, 10 ) : '—' ); ?></td>
						<td><?php echo self::actions( $row, $product_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with escaping internally ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
