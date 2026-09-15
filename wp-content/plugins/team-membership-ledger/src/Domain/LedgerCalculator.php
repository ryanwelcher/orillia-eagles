<?php
namespace OrillaEagles\Ledger\Domain;

final class LedgerCalculator {

	/**
	 * @param array $billables list of ['member_id','name','email','player_id','player_name'] (see Billables::build()).
	 * @param array $records   list of ['customer_id','player_id','status','qty','line_total','amount_paid','date'] for one product.
	 * @return LedgerRow[]
	 */
	public static function forProduct( array $billables, array $records ): array {
		$by_key = array();
		foreach ( $records as $r ) {
			$by_key[ Billables::key( (int) $r['customer_id'], (int) ( $r['player_id'] ?? 0 ) ) ][] = $r;
		}

		$rows = array();
		foreach ( $billables as $b ) {
			$id          = (int) $b['member_id'];
			$player_id   = (int) ( $b['player_id'] ?? 0 );
			$player_name = (string) ( $b['player_name'] ?? '' );
			$own         = $by_key[ Billables::key( $id, $player_id ) ] ?? array();

			if ( empty( $own ) ) {
				$rows[] = new LedgerRow( $id, (string) $b['name'], (string) $b['email'], MemberStatus::NOT_ENTERED, 0, 0.0, 0.0, null, array(), $player_id, $player_name );
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
			$rows[] = new LedgerRow( $id, (string) $b['name'], (string) $b['email'], $status, $qty, round( $total, 2 ), round( $paid, 2 ), $date, $order_ids, $player_id, $player_name );
		}

		return $rows;
	}
}
