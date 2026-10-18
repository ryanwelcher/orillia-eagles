<?php
namespace OrillaEagles\Ledger\Tests\Domain;

use OrillaEagles\Ledger\Domain\LedgerCalculator;
use OrillaEagles\Ledger\Domain\LedgerStatus;
use PHPUnit\Framework\TestCase;

final class LedgerCalculatorTest extends TestCase {

	private function families(): array {
		return array(
			array( 'id' => 10, 'name' => 'Ed Smith', 'active' => true ),
			array( 'id' => 20, 'name' => 'Ann Jones', 'active' => true ),
			array( 'id' => 30, 'name' => 'Old Family', 'active' => false ),
		);
	}

	/** A single player in Ed's family, rolled up against $records. */
	private function edsKid( array $records ) {
		$players = array( array( 'id' => 101, 'name' => 'Liam Smith', 'family_id' => 10 ) );
		return LedgerCalculator::forPlayers( $players, $this->families(), $records )[0];
	}

	private function record( array $overrides ): array {
		return array_merge(
			array( 'customer_id' => 10, 'player_id' => 101, 'order_id' => 1, 'status' => 'requested', 'line_total' => 100.0, 'amount_paid' => 0.0, 'date' => null ),
			$overrides
		);
	}

	public function test_player_with_no_record_is_not_entered(): void {
		$row = $this->edsKid( array() );
		$this->assertSame( LedgerStatus::NOT_ENTERED, $row->status() );
		$this->assertSame( 0.0, $row->total() );
		$this->assertNull( $row->date() );
		$this->assertSame( array(), $row->orderIds() );
		$this->assertNull( $row->singleOrderId() );
	}

	public function test_completed_record_is_paid(): void {
		$row = $this->edsKid( array( $this->record( array( 'status' => 'completed', 'amount_paid' => 100.0, 'date' => '2026-01-05 09:00:00' ) ) ) );
		$this->assertSame( LedgerStatus::PAID, $row->status() );
		$this->assertSame( 0.0, $row->balance() );
		$this->assertSame( '2026-01-05 09:00:00', $row->date() );
	}

	public function test_requested_record_owes_with_balance(): void {
		$row = $this->edsKid( array( $this->record( array( 'line_total' => 200.0, 'amount_paid' => 50.0 ) ) ) );
		$this->assertSame( LedgerStatus::OWES, $row->status() );
		$this->assertSame( 150.0, $row->balance() );
		$this->assertNull( $row->date() );
	}

	public function test_multiple_records_roll_up_and_earliest_date_wins(): void {
		$row = $this->edsKid(
			array(
				$this->record( array( 'order_id' => 1, 'status' => 'completed', 'line_total' => 50.0, 'amount_paid' => 50.0, 'date' => '2026-02-10 09:00:00' ) ),
				$this->record( array( 'order_id' => 2, 'line_total' => 50.0, 'date' => '2026-01-01 09:00:00' ) ),
			)
		);
		// Mixed statuses -> OWES; totals summed; earliest date.
		$this->assertSame( LedgerStatus::OWES, $row->status() );
		$this->assertSame( 100.0, $row->total() );
		$this->assertSame( 50.0, $row->paid() );
		$this->assertSame( '2026-01-01 09:00:00', $row->date() );
	}

	public function test_multiple_completed_records_stay_paid(): void {
		$row = $this->edsKid(
			array(
				$this->record( array( 'order_id' => 1, 'status' => 'completed', 'line_total' => 60.0, 'amount_paid' => 60.0 ) ),
				$this->record( array( 'order_id' => 2, 'status' => 'completed', 'line_total' => 40.0, 'amount_paid' => 40.0 ) ),
			)
		);
		$this->assertSame( LedgerStatus::PAID, $row->status() );
		$this->assertSame( 100.0, $row->total() );
		$this->assertSame( 0.0, $row->balance() );
	}

	public function test_duplicate_order_ids_collapse_to_one_target(): void {
		$row = $this->edsKid( array( $this->record( array( 'order_id' => 502 ) ), $this->record( array( 'order_id' => 502 ) ) ) );
		$this->assertSame( array( 502 ), $row->orderIds() );
		$this->assertSame( 502, $row->singleOrderId() );
	}

	public function test_two_different_orders_have_no_single_target(): void {
		$row = $this->edsKid( array( $this->record( array( 'order_id' => 601 ) ), $this->record( array( 'order_id' => 602 ) ) ) );
		$this->assertSame( 2, $row->orderCount() );
		$this->assertNull( $row->singleOrderId() );
		$this->assertSame( array( 601, 602 ), $row->orderIds() );
	}

	public function test_bills_each_kid_to_their_family(): void {
		$players = array(
			array( 'id' => 101, 'name' => 'Liam Smith', 'family_id' => 10 ),
			array( 'id' => 102, 'name' => 'Emma Smith', 'family_id' => 10 ),
			array( 'id' => 103, 'name' => 'Jake Smith', 'family_id' => 10 ),
		);
		$records = array(
			$this->record( array( 'player_id' => 101, 'order_id' => 1, 'line_total' => 300.0, 'amount_paid' => 100.0 ) ),
			$this->record( array( 'player_id' => 102, 'order_id' => 2, 'status' => 'completed', 'line_total' => 300.0, 'amount_paid' => 300.0 ) ),
		);

		$rows = LedgerCalculator::forPlayers( $players, $this->families(), $records );

		// Sorted by family then player name.
		$this->assertSame( array( 'Emma Smith', 'Jake Smith', 'Liam Smith' ), array_map( fn( $r ) => $r->playerName(), $rows ) );
		foreach ( $rows as $row ) {
			$this->assertSame( 10, $row->familyId() );
			$this->assertSame( 'Ed Smith', $row->familyName() );
		}
		$this->assertSame( LedgerStatus::PAID, $rows[0]->status() );
		$this->assertSame( LedgerStatus::NOT_ENTERED, $rows[1]->status() );
		$this->assertSame( LedgerStatus::OWES, $rows[2]->status() );
		$this->assertSame( 200.0, $rows[2]->balance() );
	}

	public function test_keeps_charge_with_the_family_it_was_billed_to(): void {
		// Liam moved to Ann after Ed was charged.
		$players = array( array( 'id' => 101, 'name' => 'Liam Smith', 'family_id' => 20 ) );
		$rows    = LedgerCalculator::forPlayers( $players, $this->families(), array( $this->record( array() ) ) );

		$this->assertCount( 1, $rows );
		$this->assertSame( 10, $rows[0]->familyId() );
		$this->assertSame( 'Ed Smith', $rows[0]->familyName() );
	}

	public function test_deactivated_family_keeps_existing_charges_visible(): void {
		$players = array(
			array( 'id' => 201, 'name' => 'Uncharged Kid', 'family_id' => 30 ),
			array( 'id' => 203, 'name' => 'Charged Kid', 'family_id' => 30 ),
		);
		$records = array( $this->record( array( 'customer_id' => 30, 'player_id' => 203, 'order_id' => 9 ) ) );

		$rows = LedgerCalculator::forPlayers( $players, $this->families(), $records );

		$this->assertCount( 1, $rows );
		$this->assertSame( 203, $rows[0]->playerId() );
		$this->assertSame( 'Old Family', $rows[0]->familyName() );
		$this->assertSame( LedgerStatus::OWES, $rows[0]->status() );
	}

	public function test_ignores_records_without_a_player(): void {
		$row = $this->edsKid( array( $this->record( array( 'player_id' => 0 ) ) ) );
		$this->assertSame( LedgerStatus::NOT_ENTERED, $row->status() );
	}

	public function test_lists_unlinked_players_as_unassigned_last(): void {
		$players = array(
			array( 'id' => 301, 'name' => 'Aaron Unlinked', 'family_id' => 0, 'is_coach' => false ),
			array( 'id' => 302, 'name' => 'Gone Family Kid', 'family_id' => 99, 'is_coach' => false ),
			array( 'id' => 303, 'name' => 'Coach Carter', 'family_id' => 0, 'is_coach' => true ),
			array( 'id' => 304, 'name' => 'Zed Smith', 'family_id' => 10, 'is_coach' => false ),
		);

		$rows = LedgerCalculator::forPlayers( $players, $this->families(), array() );

		$this->assertSame( array( 304, 301, 302 ), array_map( fn( $r ) => $r->playerId(), $rows ) );
		$this->assertSame( LedgerStatus::NOT_ENTERED, $rows[0]->status() );
		$this->assertSame( LedgerStatus::UNASSIGNED, $rows[1]->status() );
		$this->assertSame( 0, $rows[1]->familyId() );
		$this->assertSame( '', $rows[1]->familyName() );
		$this->assertSame( LedgerStatus::UNASSIGNED, $rows[2]->status() );
	}
}
