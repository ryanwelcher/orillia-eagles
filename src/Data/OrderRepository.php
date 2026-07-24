<?php
namespace OrillaEagles\Ledger\Data;

use OrillaEagles\Ledger\Status\OrderStatus;
use OrillaEagles\Ledger\Domain\PaymentCalculator;

defined( 'ABSPATH' ) || exit;

final class OrderRepository {

	public const AMOUNT_PAID_META = '_tml_amount_paid';

	/** @return array<int,array{customer_id:int,order_id:int,status:string,qty:int,line_total:float,amount_paid:float,date:?string}> */
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

	/** @return int[] */
	public function existingCustomerIds( int $product_id ): array {
		$ids = array();
		foreach ( $this->productRecords( $product_id ) as $r ) {
			$ids[ $r['customer_id'] ] = true;
		}
		return array_map( 'intval', array_keys( $ids ) );
	}

	public function createRequestedOrder( int $customer_id, int $product_id ): int {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			throw new \RuntimeException( 'Product not found: ' . $product_id );
		}
		$order = wc_create_order( array( 'customer_id' => $customer_id ) );
		$order->add_product( $product, 1 );
		$order->set_created_via( 'team-membership-ledger' );
		$order->calculate_totals();
		$order->update_status( OrderStatus::SLUG, __( 'Season rollover charge.', 'team-membership-ledger' ) );
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
		$current = (float) $order->get_meta( self::AMOUNT_PAID_META );
		$total   = (float) $order->get_total();
		$result  = PaymentCalculator::apply( $current, $increment, $total );

		$order->update_meta_data( self::AMOUNT_PAID_META, $result['new_paid'] );
		$order->save();

		if ( $result['should_complete'] ) {
			$order->update_status( 'completed', __( 'Payment completed in Membership Ledger.', 'team-membership-ledger' ) );
		}
	}

	/** @return array<int,array{id:int,name:string}> */
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
				return array( 'id' => (int) $p->get_id(), 'name' => $p->get_name() );
			},
			$products
		);
	}
}
