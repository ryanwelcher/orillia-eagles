<?php
namespace OrillaEagles\Ledger\Domain;

final class PaymentCalculator {

	/**
	 * @return array{new_paid:float,should_complete:bool}
	 */
	public static function apply( float $current_paid, float $increment, float $order_total ): array {
		$new_paid        = round( $current_paid + $increment, 2 );
		$should_complete = $new_paid >= round( $order_total, 2 );
		return array(
			'new_paid'        => $new_paid,
			'should_complete' => $should_complete,
		);
	}
}
