<?php
namespace OrillaEagles\Ledger\Domain;

final class PaymentCalculator {

	/**
	 * What an order has been paid so far.
	 *
	 * Completed orders count as fully paid, but the stored amount wins when it
	 * is higher (e.g. a paid order whose quantity was lowered), so real payments
	 * are never forgotten.
	 */
	public static function paidSoFar( string $status, float $stored_paid, float $total ): float {
		return 'completed' === $status ? max( $stored_paid, $total ) : $stored_paid;
	}

	/**
	 * What one line item of an order has been paid.
	 *
	 * Same rule as paidSoFar(), except that the stored amount is order-wide: it
	 * may only outrank the line total on an order with a single line, or a
	 * payment covering several products would be credited to one of them and
	 * report that line as overpaid.
	 */
	public static function linePaid( string $status, float $stored_paid, float $line_total, bool $single_item ): float {
		if ( 'completed' !== $status ) {
			return $stored_paid;
		}
		return $single_item ? max( $stored_paid, $line_total ) : $line_total;
	}

	public static function owed( float $current_paid, float $order_total ): float {
		return max( 0.0, round( $order_total - $current_paid, 2 ) );
	}

	/**
	 * @return array{new_paid:float,should_complete:bool}
	 * @throws \RuntimeException When the increment is more than what is owed.
	 */
	public static function apply( float $current_paid, float $increment, float $order_total ): array {
		$owed = self::owed( $current_paid, $order_total );
		if ( round( $increment, 2 ) > $owed ) {
			throw new \RuntimeException( sprintf( 'That is more than the %s owed.', number_format( $owed, 2, '.', '' ) ) );
		}
		$new_paid        = round( $current_paid + $increment, 2 );
		$should_complete = $new_paid >= round( $order_total, 2 );
		return array(
			'new_paid'        => $new_paid,
			'should_complete' => $should_complete,
		);
	}
}
