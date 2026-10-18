<?php
namespace OrillaEagles\Ledger\Admin;

use OrillaEagles\Ledger\Data\OrderRepository;
use OrillaEagles\Ledger\Data\PlayerRepository;
use OrillaEagles\Ledger\Data\FamilyRepository;
use OrillaEagles\Ledger\Domain\RolloverPlan;

defined( 'ABSPATH' ) || exit;

final class RolloverScreen {

	public static function handlePost(): void {
		if ( empty( $_POST['tml_rollover_action'] ) ) {
			return;
		}
		if ( ! current_user_can( Menu::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'team-membership-ledger' ) );
		}
		check_admin_referer( 'tml_rollover' );

		$product_id = absint( $_POST['product_id'] ?? 0 );
		if ( ! $product_id ) {
			set_transient( 'tml_rollover_notice', __( 'Please choose a product.', 'team-membership-ledger' ), 30 );
			self::redirect();
		}

		$orders     = new OrderRepository();
		$family_for = RolloverPlan::chargeable(
			( new PlayerRepository() )->allPlayers(),
			array_column( ( new FamilyRepository() )->activeFamilies(), 'id' )
		);
		$plan = RolloverPlan::build( array_keys( $family_for ), $orders->existingPlayerIds( $product_id ) );

		$created = 0;
		foreach ( $plan['to_create'] as $player_id ) {
			try {
				$orders->createRequestedOrder( $family_for[ $player_id ], $product_id, $player_id );
				$created++;
			} catch ( \RuntimeException $e ) {
				// Skip a single failure; continue the batch.
				continue;
			}
		}

		set_transient(
			'tml_rollover_notice',
			sprintf(
				/* translators: 1: created count, 2: skipped count. */
				__( '%1$d charge(s) created, %2$d skipped (already charged).', 'team-membership-ledger' ),
				$created,
				count( $plan['to_skip'] )
			),
			30
		);
		self::redirect();
	}

	private static function redirect(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=tml-rollover' ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( Menu::CAP ) ) {
			return;
		}
		$products = ( new OrderRepository() )->sellableProducts();
		$notice   = get_transient( 'tml_rollover_notice' );
		delete_transient( 'tml_rollover_notice' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Create Charges', 'team-membership-ledger' ); ?></h1>
			<?php if ( $notice ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Creates one "Requested" order for the chosen product for every player whose family is active, billed to that family. Players who already have one are skipped.', 'team-membership-ledger' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'tml_rollover' ); ?>
				<input type="hidden" name="tml_rollover_action" value="generate" />
				<select name="product_id" required>
					<option value="0"><?php esc_html_e( '— Select a product —', 'team-membership-ledger' ); ?></option>
					<?php foreach ( $products as $p ) : ?>
						<option value="<?php echo esc_attr( $p['id'] ); ?>"><?php echo esc_html( $p['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Create charges for players', 'team-membership-ledger' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
