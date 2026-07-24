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
}
