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

	public function test_only_players_of_active_families_are_chargeable(): void {
		$players = array(
			array( 'id' => 101, 'family_id' => 10 ), // Active family.
			array( 'id' => 102, 'family_id' => 10 ),
			array( 'id' => 201, 'family_id' => 30 ), // Deactivated family.
			array( 'id' => 301, 'family_id' => 0 ),  // No family.
		);

		$this->assertSame( array( 101 => 10, 102 => 10 ), RolloverPlan::chargeable( $players, array( 10, 20 ) ) );
	}
}
