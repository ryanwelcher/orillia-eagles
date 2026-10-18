<?php
namespace OrillaEagles\Ledger\Tests\Domain;

use OrillaEagles\Ledger\Domain\RolloverPlan;
use PHPUnit\Framework\TestCase;

final class RolloverPlanTest extends TestCase {

	public function test_players_without_existing_charge_are_created(): void {
		$plan = RolloverPlan::build( array( 1, 2, 3 ), array() );
		$this->assertSame( array( 1, 2, 3 ), $plan['to_create'] );
		$this->assertSame( array(), $plan['to_skip'] );
	}

	public function test_players_with_existing_charge_are_skipped(): void {
		$plan = RolloverPlan::build( array( 1, 2, 3 ), array( 2 ) );
		$this->assertSame( array( 1, 3 ), $plan['to_create'] );
		$this->assertSame( array( 2 ), $plan['to_skip'] );
	}

	public function test_duplicate_player_ids_are_collapsed(): void {
		$plan = RolloverPlan::build( array( 1, 1, 2 ), array() );
		$this->assertSame( array( 1, 2 ), $plan['to_create'] );
	}
}
