<?php
namespace OrillaEagles\Ledger\Tests\Domain;

use OrillaEagles\Ledger\Domain\LedgerCalculator;
use OrillaEagles\Ledger\Domain\MemberStatus;
use PHPUnit\Framework\TestCase;

final class LedgerCalculatorTest extends TestCase {

	private array $members;

	protected function setUp(): void {
		$this->members = array(
			array( 'id' => 1, 'name' => 'Alice', 'email' => 'a@x.com' ),
			array( 'id' => 2, 'name' => 'Bob', 'email' => 'b@x.com' ),
			array( 'id' => 3, 'name' => 'Cara', 'email' => 'c@x.com' ),
		);
	}

	public function test_member_with_no_record_is_not_entered(): void {
		$rows = LedgerCalculator::forProduct( $this->members, array() );
		$this->assertCount( 3, $rows );
		$this->assertSame( MemberStatus::NOT_ENTERED, $rows[0]->status() );
		$this->assertSame( 0.0, $rows[0]->total() );
		$this->assertNull( $rows[0]->date() );
	}

	public function test_completed_record_is_paid(): void {
		$records = array(
			array( 'customer_id' => 1, 'status' => 'completed', 'qty' => 1, 'line_total' => 100.0, 'amount_paid' => 100.0, 'date' => '2026-01-05 09:00:00' ),
		);
		$rows = LedgerCalculator::forProduct( $this->members, $records );
		$this->assertSame( MemberStatus::PAID, $rows[0]->status() );
		$this->assertSame( 0.0, $rows[0]->balance() );
		$this->assertSame( '2026-01-05 09:00:00', $rows[0]->date() );
	}

	public function test_requested_record_owes_with_balance(): void {
		$records = array(
			array( 'customer_id' => 2, 'status' => 'requested', 'qty' => 2, 'line_total' => 200.0, 'amount_paid' => 50.0, 'date' => '2026-01-06 09:00:00' ),
		);
		$rows = LedgerCalculator::forProduct( $this->members, $records );
		$this->assertSame( MemberStatus::OWES, $rows[1]->status() );
		$this->assertSame( 150.0, $rows[1]->balance() );
		$this->assertSame( 2, $rows[1]->qty() );
	}

	public function test_multiple_records_roll_up_and_earliest_date_wins(): void {
		$records = array(
			array( 'customer_id' => 3, 'status' => 'completed', 'qty' => 1, 'line_total' => 50.0, 'amount_paid' => 50.0, 'date' => '2026-02-10 09:00:00' ),
			array( 'customer_id' => 3, 'status' => 'requested', 'qty' => 1, 'line_total' => 50.0, 'amount_paid' => 0.0, 'date' => '2026-01-01 09:00:00' ),
		);
		$rows = LedgerCalculator::forProduct( $this->members, $records );
		// Mixed statuses -> OWES; totals summed; earliest date.
		$this->assertSame( MemberStatus::OWES, $rows[2]->status() );
		$this->assertSame( 100.0, $rows[2]->total() );
		$this->assertSame( 50.0, $rows[2]->paid() );
		$this->assertSame( '2026-01-01 09:00:00', $rows[2]->date() );
	}

	public function test_multiple_completed_records_sum_and_stay_paid(): void {
		$records = array(
			array( 'customer_id' => 1, 'status' => 'completed', 'qty' => 1, 'line_total' => 60.0, 'amount_paid' => 60.0, 'date' => '2026-03-01 09:00:00' ),
			array( 'customer_id' => 1, 'status' => 'completed', 'qty' => 1, 'line_total' => 40.0, 'amount_paid' => 40.0, 'date' => '2026-03-02 09:00:00' ),
		);
		$rows = LedgerCalculator::forProduct( $this->members, $records );
		$this->assertSame( MemberStatus::PAID, $rows[0]->status() );
		$this->assertSame( 100.0, $rows[0]->total() );
		$this->assertSame( 100.0, $rows[0]->paid() );
		$this->assertSame( 2, $rows[0]->qty() );
		$this->assertSame( 0.0, $rows[0]->balance() );
		$this->assertSame( '2026-03-01 09:00:00', $rows[0]->date() );
	}

	public function test_single_record_with_null_date_keeps_null_date(): void {
		$records = array(
			array( 'customer_id' => 2, 'status' => 'requested', 'qty' => 1, 'line_total' => 25.0, 'amount_paid' => 0.0, 'date' => null ),
		);
		$rows = LedgerCalculator::forProduct( $this->members, $records );
		$this->assertSame( MemberStatus::OWES, $rows[1]->status() );
		$this->assertNull( $rows[1]->date() );
		$this->assertSame( 25.0, $rows[1]->balance() );
	}

	public function test_row_carries_distinct_order_ids(): void {
		$records = array(
			array( 'customer_id' => 1, 'order_id' => 501, 'status' => 'requested', 'qty' => 1, 'line_total' => 100.0, 'amount_paid' => 0.0, 'date' => '2026-01-05 09:00:00' ),
			array( 'customer_id' => 2, 'order_id' => 502, 'status' => 'requested', 'qty' => 1, 'line_total' => 50.0, 'amount_paid' => 0.0, 'date' => '2026-01-06 09:00:00' ),
			array( 'customer_id' => 2, 'order_id' => 502, 'status' => 'requested', 'qty' => 1, 'line_total' => 50.0, 'amount_paid' => 0.0, 'date' => '2026-01-06 09:00:00' ),
		);
		$rows = LedgerCalculator::forProduct( $this->members, $records );

		// Alice: one order.
		$this->assertSame( array( 501 ), $rows[0]->orderIds() );
		$this->assertSame( 501, $rows[0]->singleOrderId() );
		$this->assertSame( 1, $rows[0]->orderCount() );

		// Bob: two records, same order id -> collapsed to one.
		$this->assertSame( array( 502 ), $rows[1]->orderIds() );
		$this->assertSame( 502, $rows[1]->singleOrderId() );

		// Cara: no records -> no orders.
		$this->assertSame( array(), $rows[2]->orderIds() );
		$this->assertNull( $rows[2]->singleOrderId() );
		$this->assertSame( 0, $rows[2]->orderCount() );
	}

	public function test_member_with_two_different_orders_has_no_single_target(): void {
		$records = array(
			array( 'customer_id' => 1, 'order_id' => 601, 'status' => 'requested', 'qty' => 1, 'line_total' => 100.0, 'amount_paid' => 0.0, 'date' => '2026-01-05 09:00:00' ),
			array( 'customer_id' => 1, 'order_id' => 602, 'status' => 'requested', 'qty' => 1, 'line_total' => 100.0, 'amount_paid' => 0.0, 'date' => '2026-01-06 09:00:00' ),
		);
		$rows = LedgerCalculator::forProduct( $this->members, $records );

		// Alice: two different order ids -> no single action target.
		$this->assertSame( 2, $rows[0]->orderCount() );
		$this->assertNull( $rows[0]->singleOrderId() );
		$this->assertSame( array( 601, 602 ), $rows[0]->orderIds() );
	}

	private function families(): array {
		return array(
			array( 'id' => 10, 'name' => 'Ed Smith', 'email' => 'ed@x.com', 'active' => true ),
			array( 'id' => 20, 'name' => 'Ann Jones', 'email' => 'ann@x.com', 'active' => true ),
			array( 'id' => 30, 'name' => 'Old Family', 'email' => 'old@x.com', 'active' => false ),
		);
	}

	public function test_for_players_bills_each_kid_to_their_family(): void {
		$players = array(
			array( 'id' => 101, 'name' => 'Liam Smith', 'family_id' => 10 ),
			array( 'id' => 102, 'name' => 'Emma Smith', 'family_id' => 10 ),
			array( 'id' => 103, 'name' => 'Jake Smith', 'family_id' => 10 ),
		);
		$records = array(
			array( 'customer_id' => 10, 'player_id' => 101, 'order_id' => 1, 'status' => 'requested', 'qty' => 1, 'line_total' => 300.0, 'amount_paid' => 100.0, 'date' => '2026-09-01 09:00:00' ),
			array( 'customer_id' => 10, 'player_id' => 102, 'order_id' => 2, 'status' => 'completed', 'qty' => 1, 'line_total' => 300.0, 'amount_paid' => 300.0, 'date' => '2026-09-01 09:00:00' ),
		);

		$rows = LedgerCalculator::forPlayers( $players, $this->families(), $records );

		$this->assertCount( 3, $rows );
		// Sorted by family then player name.
		$this->assertSame( array( 'Emma Smith', 'Jake Smith', 'Liam Smith' ), array_map( fn( $r ) => $r->playerName(), $rows ) );
		foreach ( $rows as $row ) {
			$this->assertSame( 10, $row->memberId() );
			$this->assertSame( 'Ed Smith', $row->name() );
		}
		$this->assertSame( MemberStatus::PAID, $rows[0]->status() );
		$this->assertSame( MemberStatus::NOT_ENTERED, $rows[1]->status() );
		$this->assertSame( MemberStatus::OWES, $rows[2]->status() );
		$this->assertSame( 200.0, $rows[2]->balance() );
		$this->assertSame( 1, $rows[2]->singleOrderId() );
	}

	public function test_for_players_keeps_charge_with_the_family_it_was_billed_to(): void {
		// Liam moved to Ann after Ed was charged.
		$players = array( array( 'id' => 101, 'name' => 'Liam Smith', 'family_id' => 20 ) );
		$records = array(
			array( 'customer_id' => 10, 'player_id' => 101, 'order_id' => 1, 'status' => 'requested', 'qty' => 1, 'line_total' => 300.0, 'amount_paid' => 0.0, 'date' => null ),
		);

		$rows = LedgerCalculator::forPlayers( $players, $this->families(), $records );

		$this->assertCount( 1, $rows );
		$this->assertSame( 10, $rows[0]->memberId() );
		$this->assertSame( 'Ed Smith', $rows[0]->name() );
	}

	public function test_for_players_hides_uncharged_players_of_inactive_or_missing_families(): void {
		$players = array(
			array( 'id' => 201, 'name' => 'Inactive Kid', 'family_id' => 30 ),
			array( 'id' => 202, 'name' => 'Unlinked Kid', 'family_id' => 0 ),
			array( 'id' => 203, 'name' => 'Charged Kid', 'family_id' => 30 ),
		);
		$records = array(
			array( 'customer_id' => 30, 'player_id' => 203, 'order_id' => 9, 'status' => 'requested', 'qty' => 1, 'line_total' => 50.0, 'amount_paid' => 0.0, 'date' => null ),
		);

		$rows = LedgerCalculator::forPlayers( $players, $this->families(), $records );

		$this->assertCount( 1, $rows );
		$this->assertSame( 203, $rows[0]->playerId() );
	}

	public function test_for_players_ignores_records_without_a_player(): void {
		$players = array( array( 'id' => 101, 'name' => 'Liam Smith', 'family_id' => 10 ) );
		$records = array(
			array( 'customer_id' => 10, 'player_id' => 0, 'order_id' => 1, 'status' => 'requested', 'qty' => 1, 'line_total' => 300.0, 'amount_paid' => 0.0, 'date' => null ),
		);

		$rows = LedgerCalculator::forPlayers( $players, $this->families(), $records );

		$this->assertSame( MemberStatus::NOT_ENTERED, $rows[0]->status() );
	}
}
