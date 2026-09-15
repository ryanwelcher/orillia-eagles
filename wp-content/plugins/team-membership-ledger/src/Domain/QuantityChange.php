<?php
namespace OrillaEagles\Ledger\Domain;

final class QuantityChange {

	/**
	 * Work out a line's new total and paid state after a quantity change.
	 *
	 * The unit price is the one saved at the first change, or else comes from
	 * the existing line (line total ÷ quantity), so a later change to the
	 * product's price does not re-price the charge and rounding can't drift.
	 *
	 * @return array{unit_price:float,new_total:float,is_paid:bool}
	 */
	public static function plan( int $old_qty, float $line_total, int $new_qty, float $paid, ?float $unit_price = null ): array {
		if ( $old_qty < 1 ) {
			throw new \RuntimeException( 'The existing quantity must be at least 1.' );
		}
		if ( $new_qty < 1 ) {
			throw new \RuntimeException( 'Quantity must be at least 1.' );
		}
		$unit_price = $unit_price ?? $line_total / $old_qty;
		$new_total  = round( $unit_price * $new_qty, 2 );

		return array(
			'unit_price' => $unit_price,
			'new_total'  => $new_total,
			'is_paid'    => round( $paid, 2 ) >= $new_total,
		);
	}
}
