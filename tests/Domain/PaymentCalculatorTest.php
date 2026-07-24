<?php
namespace OrillaEagles\Ledger\Tests\Domain;

use OrillaEagles\Ledger\Domain\PaymentCalculator;
use PHPUnit\Framework\TestCase;

final class PaymentCalculatorTest extends TestCase {

	public function test_first_partial_payment_does_not_complete(): void {
		$r = PaymentCalculator::apply( 0.0, 50.0, 200.0 );
		$this->assertSame( 50.0, $r['new_paid'] );
		$this->assertFalse( $r['should_complete'] );
	}

	public function test_incremental_payment_accumulates(): void {
		$r = PaymentCalculator::apply( 50.0, 30.0, 200.0 );
		$this->assertSame( 80.0, $r['new_paid'] );
		$this->assertFalse( $r['should_complete'] );
	}

	public function test_reaching_total_completes(): void {
		$r = PaymentCalculator::apply( 150.0, 50.0, 200.0 );
		$this->assertSame( 200.0, $r['new_paid'] );
		$this->assertTrue( $r['should_complete'] );
	}

	public function test_overpayment_completes(): void {
		$r = PaymentCalculator::apply( 150.0, 60.0, 200.0 );
		$this->assertSame( 210.0, $r['new_paid'] );
		$this->assertTrue( $r['should_complete'] );
	}

	public function test_exact_single_payment_completes(): void {
		$r = PaymentCalculator::apply( 0.0, 100.0, 100.0 );
		$this->assertSame( 100.0, $r['new_paid'] );
		$this->assertTrue( $r['should_complete'] );
	}

	public function test_float_rounding_is_stable(): void {
		$r = PaymentCalculator::apply( 0.1, 0.2, 0.3 );
		$this->assertSame( 0.3, $r['new_paid'] );
		$this->assertTrue( $r['should_complete'] );
	}
}
