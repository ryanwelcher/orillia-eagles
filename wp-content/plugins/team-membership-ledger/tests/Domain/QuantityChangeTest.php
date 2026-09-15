<?php
namespace OrillaEagles\Ledger\Tests\Domain;

use OrillaEagles\Ledger\Domain\QuantityChange;
use PHPUnit\Framework\TestCase;

final class QuantityChangeTest extends TestCase {

	public function test_raising_quantity_multiplies_the_unit_price(): void {
		$plan = QuantityChange::plan( 1, 25.0, 3, 0.0 );
		$this->assertSame( 75.0, $plan['new_total'] );
		$this->assertFalse( $plan['is_paid'] );
	}

	public function test_raising_a_paid_order_makes_it_owe_again(): void {
		// Paid $25 for 1 ticket, now wants 2.
		$plan = QuantityChange::plan( 1, 25.0, 2, 25.0 );
		$this->assertSame( 50.0, $plan['new_total'] );
		$this->assertFalse( $plan['is_paid'] );
	}

	public function test_lowering_quantity_to_what_was_paid_completes_it(): void {
		// $75 for 3, $50 paid so far, drop to 2 tickets.
		$plan = QuantityChange::plan( 3, 75.0, 2, 50.0 );
		$this->assertSame( 50.0, $plan['new_total'] );
		$this->assertTrue( $plan['is_paid'] );
	}

	public function test_unit_price_comes_from_the_existing_line(): void {
		// $100 for 3 (an uneven unit price) doubled is exactly $200.
		$plan = QuantityChange::plan( 3, 100.0, 6, 0.0 );
		$this->assertSame( 200.0, $plan['new_total'] );
	}

	public function test_quantity_below_one_is_rejected(): void {
		$this->expectException( \RuntimeException::class );
		QuantityChange::plan( 1, 25.0, 0, 0.0 );
	}

	public function test_existing_quantity_of_zero_is_rejected(): void {
		$this->expectException( \RuntimeException::class );
		QuantityChange::plan( 0, 25.0, 2, 0.0 );
	}
}
