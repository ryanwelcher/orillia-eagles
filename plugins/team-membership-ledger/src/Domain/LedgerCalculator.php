<?php
namespace OrillaEagles\Ledger\Domain;

final class LedgerCalculator {

	/**
	 * @param array $members list of ['id','name','email'].
	 * @param array $records list of ['customer_id','status','qty','line_total','amount_paid','date'] for one product.
	 * @return LedgerRow[]
	 */
	public static function forProduct( array $members, array $records ): array {
		$by_member = array();
		foreach ( $records as $r ) {
			$by_member[ (int) $r['customer_id'] ][] = $r;
		}

		$rows = array();
		foreach ( $members as $m ) {
			$id  = (int) $m['id'];
			$own = $by_member[ $id ] ?? array();

			if ( empty( $own ) ) {
				$rows[] = new LedgerRow( $id, (string) $m['name'], (string) $m['email'], MemberStatus::NOT_ENTERED, 0, 0.0, 0.0, null );
				continue;
			}

			$qty      = 0;
			$total    = 0.0;
			$paid     = 0.0;
			$all_paid = true;
			$date     = null;
			$order_ids = array();
			foreach ( $own as $r ) {
				$qty   += (int) $r['qty'];
				$total += (float) $r['line_total'];
				$paid  += (float) $r['amount_paid'];
				if ( 'completed' !== $r['status'] ) {
					$all_paid = false;
				}
				if ( ! empty( $r['date'] ) && ( null === $date || $r['date'] < $date ) ) {
					$date = (string) $r['date'];
				}
				if ( ! empty( $r['order_id'] ) ) {
					$order_ids[] = (int) $r['order_id'];
				}
			}

			$status = $all_paid ? MemberStatus::PAID : MemberStatus::OWES;
			$rows[] = new LedgerRow( $id, (string) $m['name'], (string) $m['email'], $status, $qty, round( $total, 2 ), round( $paid, 2 ), $date, $order_ids );
		}

		return $rows;
	}
}
