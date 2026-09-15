<?php
namespace OrillaEagles\Ledger\Domain;

final class PaymentAllocation {

	/**
	 * Spread a member-level payment across orders, paying each off in turn.
	 *
	 * @param array<int,float> $balances order id => balance still owing, in pay-off order.
	 * @return array<int,float> order id => amount to apply (orders that get nothing are left out).
	 */
	public static function fill( array $balances, float $amount ): array {
		$left = round( $amount, 2 );
		$out  = array();
		foreach ( $balances as $order_id => $balance ) {
			if ( $left <= 0 ) {
				break;
			}
			$balance = round( (float) $balance, 2 );
			if ( $balance <= 0 ) {
				continue;
			}
			$apply                  = min( $left, $balance );
			$out[ (int) $order_id ] = $apply;
			$left                   = round( $left - $apply, 2 );
		}
		return $out;
	}

	/** @param array<int,float> $balances order id => balance. */
	public static function owed( array $balances ): float {
		return round( array_sum( array_map( static fn( $b ) => max( 0.0, (float) $b ), $balances ) ), 2 );
	}
}
