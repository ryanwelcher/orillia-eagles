<?php
namespace OrillaEagles\Ledger\Tests\Domain;

use OrillaEagles\Ledger\Domain\PaymentAllocation;
use PHPUnit\Framework\TestCase;

final class PaymentAllocationTest extends TestCase {

	public function test_pays_off_one_order_at_a_time(): void {
		$out = PaymentAllocation::fill( array( 11 => 200.0, 12 => 200.0, 13 => 200.0 ), 250.0 );
		$this->assertSame( array( 11 => 200.0, 12 => 50.0 ), $out );
	}

	public function test_skips_orders_already_paid(): void {
		$out = PaymentAllocation::fill( array( 11 => 0.0, 12 => 150.0 ), 100.0 );
		$this->assertSame( array( 12 => 100.0 ), $out );
	}

	public function test_exact_total_pays_everything(): void {
		$out = PaymentAllocation::fill( array( 11 => 120.5, 12 => 79.5 ), 200.0 );
		$this->assertSame( array( 11 => 120.5, 12 => 79.5 ), $out );
	}

	public function test_refuses_a_payment_the_balances_cannot_absorb(): void {
		// A concurrent payment took the balance after the caller checked it.
		$this->expectException( \RuntimeException::class );
		PaymentAllocation::fill( array( 11 => 50.0 ), 100.0 );
	}

	public function test_refuses_a_payment_when_every_order_is_already_paid(): void {
		$this->expectException( \RuntimeException::class );
		PaymentAllocation::fill( array( 11 => 0.0, 12 => 0.0 ), 25.0 );
	}

	public function test_owed_sums_positive_balances(): void {
		$this->assertSame( 250.0, PaymentAllocation::owed( array( 11 => 200.0, 12 => 50.0, 13 => 0.0 ) ) );
	}
}
