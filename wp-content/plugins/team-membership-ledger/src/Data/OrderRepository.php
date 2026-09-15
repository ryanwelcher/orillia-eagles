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

	/** @return array<int,array{customer_id:int,player_id:int,order_id:int,status:string,qty:int,line_total:float,amount_paid:float,date:?string}> */
	public function productRecords( int $product_id ): array {
		$statuses = array_keys( wc_get_order_statuses() ); // all statuses
		$orders   = array();
		$page     = 1;
		$per_page = 200;
		do {
			$batch = wc_get_orders(
				array(
					'limit'   => $per_page,
					'paged'   => $page,
					'type'    => 'shop_order',
					'status'  => $statuses,
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			);
			$orders = array_merge( $orders, $batch );
			$page++;
		} while ( count( $batch ) === $per_page );

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

			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_product_id() !== $product_id ) {
					continue;
				}
				$line_total = (float) $item->get_total();
				// Installment tracking assumes one product per order: the order-level
				// _tml_amount_paid meta is attributed to this line item. Orders that mix
				// products (or repeat a product across line items) would misattribute the
				// partial payment. The plugin's own Rollover always creates single-product
				// orders, matching the per-season product-per-order model in the README.
				$paid       = ( 'completed' === $status )
					? $line_total
					: (float) $order->get_meta( self::AMOUNT_PAID_META );

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

	public function createRequestedOrder( int $customer_id, int $product_id, int $player_id = 0, int $qty = 1 ): int {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			throw new \RuntimeException( 'Product not found: ' . $product_id );
		}
		$order = wc_create_order( array( 'customer_id' => $customer_id ) );
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
		return (int) $order->get_id();
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
			$owed = PaymentCalculator::owed( $current, $total );
			/* translators: %s: amount owed */
			throw new \RuntimeException( sprintf( __( 'That is more than the %s owed.', 'team-membership-ledger' ), html_entity_decode( wp_strip_all_tags( wc_price( $owed ) ) ) ) );
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
		// Keeps a stored payment that is higher than a lowered line total.
		$paid = PaymentCalculator::paidSoFar( $order->get_status(), (float) $order->get_meta( self::AMOUNT_PAID_META ), $line_total );
		// Saved at the first change, so repeated changes don't compound rounding.
		$saved_unit = $item->get_meta( self::UNIT_PRICE_META );
		$plan       = QuantityChange::plan( $old_qty, $line_total, $qty, $paid, '' === $saved_unit ? null : (float) $saved_unit );

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
		$new_status = $plan['is_paid'] ? 'completed' : OrderStatus::SLUG;
		if ( $order->get_status() !== $new_status ) {
			$order->update_status( $new_status );
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
