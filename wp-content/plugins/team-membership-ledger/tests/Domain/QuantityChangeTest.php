<?php
namespace OrillaEagles\Ledger\Tests\Domain;

use OrillaEagles\Ledger\Domain\PaymentCalculator;
use OrillaEagles\Ledger\Domain\QuantityChange;
use PHPUnit\Framework\TestCase;

final class QuantityChangeTest extends TestCase {

	public function test_raising_quantity_multiplies_the_unit_price(): void {
		$plan = QuantityChange::plan( 1, 25.0, 3 );
		$this->assertSame( 75.0, $plan['new_total'] );
		$this->assertFalse( QuantityChange::isPaid( 0.0, $plan['new_total'] ) );
	}

	public function test_raising_a_paid_order_makes_it_owe_again(): void {
		// Paid $25 for 1 ticket, now wants 2.
		$plan = QuantityChange::plan( 1, 25.0, 2 );
		$this->assertSame( 50.0, $plan['new_total'] );
		$this->assertFalse( QuantityChange::isPaid( 25.0, $plan['new_total'] ) );
	}

	public function test_lowering_quantity_to_what_was_paid_completes_it(): void {
		// $75 for 3, $50 paid so far, drop to 2 tickets.
		$plan = QuantityChange::plan( 3, 75.0, 2 );
		$this->assertSame( 50.0, $plan['new_total'] );
		$this->assertTrue( QuantityChange::isPaid( 50.0, $plan['new_total'] ) );
	}

	public function test_unit_price_comes_from_the_existing_line(): void {
		// $100 for 3 (an uneven unit price) doubled is exactly $200.
		$plan = QuantityChange::plan( 3, 100.0, 6 );
		$this->assertSame( 200.0, $plan['new_total'] );
	}

	public function test_saved_unit_price_stops_rounding_drift(): void {
		// $100 for 3 → 2 is $66.67. Going back to 3 from that rounded line
		// would give $100.01; the saved unit price gives $100.00.
		$first = QuantityChange::plan( 3, 100.0, 2 );
		$this->assertSame( 66.67, $first['new_total'] );

		$drifted = QuantityChange::plan( 2, $first['new_total'], 3 );
		$this->assertSame( 100.01, $drifted['new_total'] );

		$second = QuantityChange::plan( 2, $first['new_total'], 3, $first['unit_price'] );
		$this->assertSame( 100.0, $second['new_total'] );
	}

	public function test_lowering_then_raising_a_paid_order_stays_paid(): void {
		// Paid $200 for 2 and lowered to 1: the stored $200 still covers 2 again.
		$paid = PaymentCalculator::paidSoFar( 'completed', 200.0, 100.0 );
		$plan = QuantityChange::plan( 1, 100.0, 2 );
		$this->assertSame( 200.0, $plan['new_total'] );
		$this->assertTrue( QuantityChange::isPaid( $paid, $plan['new_total'] ) );
	}

	public function test_paid_is_judged_against_the_order_total_not_the_line(): void {
		// $100 line plus $13 tax: $100 paid does not cover the $113 order.
		$this->assertFalse( QuantityChange::isPaid( 100.0, 113.0 ) );
		$this->assertTrue( QuantityChange::isPaid( 113.0, 113.0 ) );
	}

	public function test_quantity_below_one_is_rejected(): void {
		$this->expectException( \RuntimeException::class );
		QuantityChange::plan( 1, 25.0, 0 );
	}

	public function test_existing_quantity_of_zero_is_rejected(): void {
		$this->expectException( \RuntimeException::class );
		QuantityChange::plan( 0, 25.0, 2 );
	}
}
