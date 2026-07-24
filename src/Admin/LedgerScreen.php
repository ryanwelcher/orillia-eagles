<?php
namespace OrillaEagles\Ledger\Admin;

use OrillaEagles\Ledger\Data\OrderRepository;
use OrillaEagles\Ledger\Data\RosterRepository;
use OrillaEagles\Ledger\Domain\LedgerCalculator;
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
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
