<?php
namespace OrillaEagles\Ledger\Admin;

use OrillaEagles\Ledger\Data\ActionLock;
use OrillaEagles\Ledger\Data\BillableRepository;
use OrillaEagles\Ledger\Data\OrderRepository;
use OrillaEagles\Ledger\Data\PlayerRepository;
use OrillaEagles\Ledger\Data\ProductFlag;
use OrillaEagles\Ledger\Domain\Billables;
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
		$per_player = ProductFlag::isPerPlayer( $product_id );

		$by_key = array();
		foreach ( ( new BillableRepository() )->forProduct( $product_id ) as $b ) {
			$by_key[ Billables::key( $b['member_id'], $b['player_id'] ) ] = $b;
		}
		$plan = RolloverPlan::build( array_keys( $by_key ), $orders->existingBillableKeys( $product_id ) );

		// Group what is missing by member: one lock and one read of that member's
		// orders covers all of their players.
		$by_member = array();
		foreach ( $plan['to_create'] as $key ) {
			$by_member[ (int) $by_key[ $key ]['member_id'] ][] = $by_key[ $key ];
		}

		$created = 0;
		$busy    = 0;
		$skipped = count( $plan['to_skip'] );
		foreach ( $by_member as $member_id => $missing ) {
			$made = 0;
			try {
				// The plan is a snapshot. Take the same lock the Ledger's own
				// actions take, and look again inside it, so a charge created since
				// the snapshot isn't duplicated.
				$made = ActionLock::run(
					ActionLock::memberKey( $product_id, $member_id ),
					static function () use ( $orders, $product_id, $per_player, $member_id, $missing ) {
						// Already charged before the product's "Charge per player"
						// setting changed: charging again would bill them twice.
						// Voided orders count here: a charge someone cancelled on purpose
						// is not raised again by a bulk run.
						$charged = $orders->chargedPlayerIds( $product_id, $member_id, true );
						if ( OrderRepository::inOtherMode( $charged, $per_player ) ) {
							return 0;
						}
						$made = 0;
						foreach ( $missing as $b ) {
							$player_id = (int) $b['player_id'];
							if ( in_array( $player_id, $charged, true ) || $orders->playerChargedUnderAnother( $product_id, $player_id, $member_id ) ) {
								continue;
							}
							// The links were read before this loop began, and a big run
							// takes a while. A player moved or unlinked since must not be
							// charged under the member they have left.
							if ( $player_id && ! PlayerRepository::isLinkedTo( $player_id, $member_id, true ) ) {
								continue;
							}
							try {
								$orders->createRequestedOrder( $member_id, $product_id, $player_id );
								$made++;
							} catch ( \RuntimeException $e ) {
								// Skip a single failure and continue with the rest.
								continue;
							}
						}
						return $made;
					}
				);
			} catch ( \RuntimeException $e ) {
				// Someone is charging this member right now, or an earlier run died
				// holding their lock. Say so: they are not charged, and uncharged
				// members are hidden in the Ledger by default.
				$made = 0;
				$busy++;
			}
			$created += $made;
			$skipped += count( $missing ) - $made;
		}

		$notice = sprintf(
			/* translators: 1: created count, 2: skipped count. */
			__( '%1$d order(s) created, %2$d skipped.', 'team-membership-ledger' ),
			$created,
			$skipped
		);
		if ( $busy ) {
			$notice .= ' ' . sprintf(
				/* translators: %d: number of members */
				_n(
					'%d member was busy with another change and was NOT charged. Run this again in a couple of minutes.',
					'%d members were busy with another change and were NOT charged. Run this again in a couple of minutes.',
					$busy,
					'team-membership-ledger'
				),
				$busy
			);
		}
		set_transient( 'tml_rollover_notice', $notice, 30 );
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
			<p><?php esc_html_e( 'Creates one "Requested" order for the chosen product against every active member who does not already have one. For "Charge per player" products, it creates one order for each player linked to an active member instead.', 'team-membership-ledger' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'tml_rollover' ); ?>
				<input type="hidden" name="tml_rollover_action" value="generate" />
				<select name="product_id" required>
					<option value="0"><?php esc_html_e( '— Select a product —', 'team-membership-ledger' ); ?></option>
					<?php foreach ( $products as $p ) : ?>
						<option value="<?php echo esc_attr( $p['id'] ); ?>"><?php echo esc_html( $p['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Create charges for active members', 'team-membership-ledger' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
