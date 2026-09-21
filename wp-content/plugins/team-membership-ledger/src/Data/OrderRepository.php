<?php
namespace OrillaEagles\Ledger\Data;

use OrillaEagles\Ledger\Status\OrderStatus;
use OrillaEagles\Ledger\Domain\Billables;
use OrillaEagles\Ledger\Domain\PaymentCalculator;
use OrillaEagles\Ledger\Domain\QuantityChange;

defined( 'ABSPATH' ) || exit;

final class OrderRepository {

	public const AMOUNT_PAID_META = '_tml_amount_paid';
	public const PLAYER_META      = '_tml_player_id';
	public const UNIT_PRICE_META  = '_tml_unit_price';

	/** Statuses that mean the charge was voided in WooCommerce. */
	public const VOID_STATUSES = array( 'cancelled', 'refunded', 'failed' );

	/** @return array<int,array{customer_id:int,player_id:int,order_id:int,status:string,qty:int,line_total:float,amount_paid:float,date:?string}> */
	public function productRecords( int $product_id, int $customer_id = 0 ): array {
		// One member's actions only need that member's orders; the full ledger
		// (customer 0) reads them all.
		$orders = $this->orders( $customer_id ? array( 'customer_id' => $customer_id ) : array() );

		$records = array();
		foreach ( $orders as $order ) {
			$customer_id = (int) $order->get_customer_id();
			if ( ! $customer_id ) {
				continue;
			}
			$status = $order->get_status(); // slug without wc- prefix
			$date   = $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : null;
			// 0 = a member-level charge; otherwise the player this charge is for.
			$player_id = (int) $order->get_meta( self::PLAYER_META );

			$items = $order->get_items();
			foreach ( $items as $item ) {
				if ( (int) $item->get_product_id() !== $product_id ) {
					continue;
				}
				// A single-item order is the charge, so its amount is the order total:
				// the same figure payments are checked against, including any tax or
				// fee. Only a line of several falls back to the line's own total.
				$line_total = 1 === count( $items ) ? (float) $order->get_total() : (float) $item->get_total();
				// Installment tracking assumes one product per order: the order-level
				// _tml_amount_paid meta is attributed to this line item. Orders that mix
				// products (or repeat a product across line items) would misattribute the
				// partial payment. The plugin's own Rollover always creates single-product
				// orders, matching the per-season product-per-order model in the README.
				// Completed counts as paid in full, and a higher stored payment wins
				// so lowering a paid order's quantity doesn't lose it — but only on a
				// single-item order, because the stored amount is order-wide and
				// would otherwise be credited to one line of several.
				$paid       = PaymentCalculator::linePaid( $status, (float) $order->get_meta( self::AMOUNT_PAID_META ), $line_total, 1 === count( $items ) );

				$records[] = array(
					'customer_id' => $customer_id,
					'player_id'   => $player_id,
					'order_id'    => (int) $order->get_id(),
					'status'      => $status,
					'qty'         => (int) $item->get_quantity(),
					'line_total'  => $line_total,
					'amount_paid' => $paid,
					'date'        => $date,
				);
			}
		}
		return $records;
	}

	/** @return string[] "member:player" keys that already have an order for the product. */
	public function existingBillableKeys( int $product_id ): array {
		$keys = array();
		foreach ( $this->productRecords( $product_id ) as $r ) {
			$keys[ Billables::key( $r['customer_id'], $r['player_id'] ) ] = true;
		}
		return array_map( 'strval', array_keys( $keys ) );
	}

	/**
	 * Whether this (member, player) already has an order for the product.
	 *
	 * Scoped to the one customer, so it is cheap enough to re-check inside a
	 * lock; existingBillableKeys() scans every order for a whole batch.
	 */
	public function hasCharge( int $product_id, int $customer_id, int $player_id ): bool {
		return in_array( $player_id, $this->chargedPlayerIds( $product_id, $customer_id ), true );
	}

	/**
	 * Whether the member has a charge for the product made under the other
	 * billing mode: a member-level order on a product that is now "Charge per
	 * player", or a player order on one that no longer is. The Ledger does not
	 * show those, so without this check a new charge would bill them again.
	 */
	public function hasChargeInOtherMode( int $product_id, int $customer_id, bool $per_player ): bool {
		foreach ( $this->chargedPlayerIds( $product_id, $customer_id ) as $player_id ) {
			if ( $per_player ? 0 === $player_id : 0 !== $player_id ) {
				return true;
			}
		}
		return false;
	}

	/** Whether this player has a charge under this member that is not settled yet. */
	public function hasOpenPlayerCharge( int $customer_id, int $player_id ): bool {
		foreach ( $this->customerOrders( $customer_id ) as $order ) {
			if ( (int) $order->get_meta( self::PLAYER_META ) === $player_id && ! in_array( $order->get_status(), array_merge( array( 'completed' ), self::VOID_STATUSES ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/** @return int[] the player id (0 = member-level) of each of the customer's orders for the product. */
	public function chargedPlayerIds( int $product_id, int $customer_id ): array {
		$ids = array();
		foreach ( $this->customerOrders( $customer_id ) as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_product_id() === $product_id ) {
					$ids[] = (int) $order->get_meta( self::PLAYER_META );
					break;
				}
			}
		}
		return $ids;
	}

	/**
	 * Whether the player already has a live charge for the product under a
	 * different member, which happens when a player is moved after being charged.
	 * Voided orders don't count, so cancelling the old charge frees the player.
	 */
	public function playerChargedUnderAnother( int $product_id, int $player_id, int $customer_id ): bool {
		if ( ! $player_id ) {
			return false;
		}
		// The meta query narrows the read where the order store supports it; the
		// checks below hold either way.
		$where = array( 'meta_query' => array( array( 'key' => self::PLAYER_META, 'value' => (string) $player_id ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		foreach ( $this->orders( $where ) as $order ) {
			if (
				(int) $order->get_meta( self::PLAYER_META ) !== $player_id
				|| (int) $order->get_customer_id() === $customer_id
				|| ! $order->get_customer_id()
				|| in_array( $order->get_status(), self::VOID_STATUSES, true )
			) {
				continue;
			}
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_product_id() === $product_id ) {
					return true;
				}
			}
		}
		return false;
	}

	/** @return \WC_Order[] every order of one customer, in any status. */
	private function customerOrders( int $customer_id ): array {
		return $this->orders( array( 'customer_id' => $customer_id ) );
	}

	/**
	 * @param array $where extra wc_get_orders() arguments.
	 * @return \WC_Order[] matching orders in any status, oldest first.
	 */
	private function orders( array $where = array() ): array {
		$statuses = array_keys( wc_get_order_statuses() );
		$orders   = array();
		$page     = 1;
		$per_page = 200;
		do {
			$batch  = wc_get_orders(
				array_merge(
					array(
						'limit'   => $per_page,
						'paged'   => $page,
						'type'    => 'shop_order',
						'status'  => $statuses,
						'orderby' => 'ID',
						'order'   => 'ASC',
					),
					$where
				)
			);
			$orders = array_merge( $orders, $batch );
			$page++;
		} while ( count( $batch ) === $per_page );
		return $orders;
	}

	public function createRequestedOrder( int $customer_id, int $product_id, int $player_id = 0, int $qty = 1 ): int {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			throw new \RuntimeException( 'Product not found: ' . $product_id );
		}
		// WooCommerce reports failures as WP_Error or WC_Data_Exception; callers
		// only handle RuntimeException, so a failed order must not escape as a fatal.
		try {
			$order = wc_create_order( array( 'customer_id' => $customer_id ) );
			if ( is_wp_error( $order ) ) {
				throw new \RuntimeException( $order->get_error_message() );
			}
			$order->add_product( $product, max( 1, $qty ) );
			$order->set_created_via( 'team-membership-ledger' );
			$note = __( 'Season rollover charge.', 'team-membership-ledger' );
			if ( $player_id ) {
				$order->update_meta_data( self::PLAYER_META, $player_id );
				/* translators: %s: player name */
				$note = sprintf( __( 'Season rollover charge for %s.', 'team-membership-ledger' ), get_post_field( 'post_title', $player_id, 'raw' ) );
			}
			$order->calculate_totals();
			$order->update_status( OrderStatus::SLUG, $note );
		} catch ( \RuntimeException $e ) {
			throw $e;
		} catch ( \Exception $e ) {
			throw new \RuntimeException( $e->getMessage() );
		}
		return (int) $order->get_id();
	}

	/** Throw when the order was voided in WooCommerce, so the Ledger never revives it. */
	public function assertNotVoid( \WC_Order $order ): void {
		if ( in_array( $order->get_status(), self::VOID_STATUSES, true ) ) {
			/* translators: %s: order status label */
			throw new \RuntimeException( sprintf( __( 'That order is %s in WooCommerce; manage it there.', 'team-membership-ledger' ), wc_get_order_status_name( $order->get_status() ) ) );
		}
	}

	private static function overpaymentMessage( float $owed ): string {
		/* translators: %s: amount owed */
		return sprintf( __( 'That is more than the %s owed.', 'team-membership-ledger' ), html_entity_decode( wp_strip_all_tags( wc_price( $owed ) ) ) );
	}

	public function markPaid( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new \RuntimeException( 'Order not found: ' . $order_id );
		}
		$order->update_meta_data( self::AMOUNT_PAID_META, (float) $order->get_total() );
		$order->save();
		$order->update_status( 'completed', __( 'Marked paid in Membership Ledger.', 'team-membership-ledger' ) );
	}

	/**
	 * Throw unless this increment could be applied to the order right now.
	 *
	 * Lets a caller paying several orders check them all before writing any,
	 * because the writes are separate order saves with no rollback between them.
	 *
	 * @throws \RuntimeException When the order is gone or the increment overpays.
	 */
	public function assertCanPay( int $order_id, float $increment ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new \RuntimeException( __( 'A charge for this member no longer exists; reload the Ledger and try again.', 'team-membership-ledger' ) );
		}
		$total   = (float) $order->get_total();
		$current = PaymentCalculator::paidSoFar( $order->get_status(), (float) $order->get_meta( self::AMOUNT_PAID_META ), $total );
		try {
			PaymentCalculator::apply( $current, $increment, $total );
		} catch ( \RuntimeException $e ) {
			throw new \RuntimeException( self::overpaymentMessage( PaymentCalculator::owed( $current, $total ) ) );
		}
	}

	public function addPayment( int $order_id, float $increment ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new \RuntimeException( 'Order not found: ' . $order_id );
		}
		$total   = (float) $order->get_total();
		$current = PaymentCalculator::paidSoFar( $order->get_status(), (float) $order->get_meta( self::AMOUNT_PAID_META ), $total );
		// Re-read here, not just in the controller, so a stale or concurrent
		// request can't push the paid amount past the total.
		try {
			$result = PaymentCalculator::apply( $current, $increment, $total );
		} catch ( \RuntimeException $e ) {
			throw new \RuntimeException( self::overpaymentMessage( PaymentCalculator::owed( $current, $total ) ) );
		}

		$order->update_meta_data( self::AMOUNT_PAID_META, $result['new_paid'] );
		$order->save();

		if ( $result['should_complete'] ) {
			$order->update_status( 'completed', __( 'Payment completed in Membership Ledger.', 'team-membership-ledger' ) );
		}
	}

	/**
	 * Change a single-product order's quantity at its original unit price, then
	 * re-derive paid/owes from what has been paid so far.
	 */
	public function setQuantity( int $order_id, int $product_id, int $qty ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new \RuntimeException( 'Order not found: ' . $order_id );
		}
		$items = $order->get_items();
		$item  = reset( $items );
		if ( 1 !== count( $items ) || (int) $item->get_product_id() !== $product_id ) {
			throw new \RuntimeException( __( 'This order has other items; change the quantity in WooCommerce.', 'team-membership-ledger' ) );
		}

		$old_qty = (int) $item->get_quantity();
		if ( $old_qty === $qty ) {
			return;
		}
		$line_total = (float) $item->get_total();
		// Keeps a stored payment that is higher than a lowered total.
		$paid = PaymentCalculator::paidSoFar( $order->get_status(), (float) $order->get_meta( self::AMOUNT_PAID_META ), (float) $order->get_total() );
		// Saved at the first change, so repeated changes don't compound rounding.
		$saved_unit = $item->get_meta( self::UNIT_PRICE_META );
		$saved_unit = '' === $saved_unit ? null : (float) $saved_unit;
		// The saved unit only holds while the line is still what it produced. If
		// the line was re-priced in WooCommerce since, price from the line instead.
		if ( null !== $saved_unit && abs( round( $saved_unit * $old_qty, 2 ) - $line_total ) > 0.005 ) {
			$saved_unit = null;
		}
		$plan = QuantityChange::plan( $old_qty, $line_total, $qty, $paid, $saved_unit );

		$item->update_meta_data( self::UNIT_PRICE_META, $plan['unit_price'] );
		$item->set_quantity( $qty );
		$item->set_subtotal( $plan['new_total'] );
		$item->set_total( $plan['new_total'] );
		$item->save();
		$order->calculate_totals();
		// Keep what was already paid, including when a Completed order reopens.
		$order->update_meta_data( self::AMOUNT_PAID_META, $paid );
		$order->save();

		$order->add_order_note(
			/* translators: 1: old quantity, 2: new quantity */
			sprintf( __( 'Quantity changed from %1$d to %2$d in Membership Ledger.', 'team-membership-ledger' ), $old_qty, $qty )
		);
		// Only move between paid and unpaid. An unpaid order keeps whatever open
		// status it has (Requested, On hold, ...) rather than being forced to one.
		// Paid is judged against the recalculated order total, the figure every
		// payment is checked against, not the line (they differ with tax or fees).
		$is_paid = round( $paid, 2 ) >= round( (float) $order->get_total(), 2 );
		if ( $is_paid && 'completed' !== $order->get_status() ) {
			$order->update_status( 'completed' );
		} elseif ( ! $is_paid && 'completed' === $order->get_status() ) {
			$order->update_status( OrderStatus::SLUG );
		}
	}

	/** @return array<int,array{id:int,name:string,perPlayer:bool,allowsQty:bool}> */
	public function sellableProducts(): array {
		$products = wc_get_products(
			array(
				'limit'   => -1,
				'status'  => 'publish',
				'orderby' => 'name',
				'order'   => 'ASC',
			)
		);
		return array_map(
			static function ( $p ) {
				return array(
					'id'        => (int) $p->get_id(),
					'name'      => $p->get_name(),
					'perPlayer' => ProductFlag::isPerPlayerProduct( $p ),
					'allowsQty' => ProductFlag::allowsQuantity( $p ),
				);
			},
			$products
		);
	}
}
