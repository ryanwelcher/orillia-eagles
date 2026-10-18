<?php
namespace OrillaEagles\Ledger\Tests\Domain;

use OrillaEagles\Ledger\Domain\RolloverPlan;
use PHPUnit\Framework\TestCase;

final class RolloverPlanTest extends TestCase {

	public function test_billables_without_existing_order_are_created(): void {
		$plan = RolloverPlan::build( array( '1:0', '2:0', '3:0' ), array() );
		$this->assertSame( array( '1:0', '2:0', '3:0' ), $plan['to_create'] );
		$this->assertSame( array(), $plan['to_skip'] );
	}

	public function test_billables_with_existing_order_are_skipped(): void {
		$plan = RolloverPlan::build( array( '1:0', '2:0', '3:0' ), array( '2:0' ) );
		$this->assertSame( array( '1:0', '3:0' ), $plan['to_create'] );
		$this->assertSame( array( '2:0' ), $plan['to_skip'] );
	}

	public function test_duplicate_keys_are_collapsed(): void {
		$plan = RolloverPlan::build( array( '1:0', '1:0', '2:0' ), array() );
		$this->assertSame( array( '1:0', '2:0' ), $plan['to_create'] );
	}

	public function test_only_the_existing_player_of_a_member_is_skipped(): void {
		$plan = RolloverPlan::build( array( '1:41', '1:42', '1:43' ), array( '1:42', '1:0' ) );
		$this->assertSame( array( '1:41', '1:43' ), $plan['to_create'] );
		$this->assertSame( array( '1:42' ), $plan['to_skip'] );
	}
}
