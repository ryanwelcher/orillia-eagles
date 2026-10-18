<?php
namespace OrillaEagles\Ledger\Tests\Domain;

use OrillaEagles\Ledger\Domain\LedgerRow;
use OrillaEagles\Ledger\Domain\LedgerSerializer;
use OrillaEagles\Ledger\Domain\LedgerStatus;
use PHPUnit\Framework\TestCase;

final class LedgerSerializerTest extends TestCase {

	public function test_serializes_a_single_order_row(): void {
		$row = new LedgerRow( 101, 'Liam Smith', 10, 'Ed Smith', LedgerStatus::OWES, 200.0, 80.0, '2026-07-24 10:00:00', array( 55 ) );
		$out = LedgerSerializer::row( $row );

		$this->assertSame( 101, $out['id'] );
		$this->assertSame( 101, $out['playerId'] );
		$this->assertSame( 'Liam Smith', $out['playerName'] );
		$this->assertSame( 10, $out['familyId'] );
		$this->assertSame( 'Ed Smith', $out['familyName'] );
		$this->assertSame( LedgerStatus::OWES, $out['status'] );
		$this->assertSame( 200.0, $out['total'] );
		$this->assertSame( 80.0, $out['paid'] );
		$this->assertSame( 120.0, $out['balance'] );
		$this->assertSame( '2026-07-24', $out['date'] );
		$this->assertSame( 55, $out['orderId'] );
		$this->assertSame( 1, $out['orderCount'] );
	}

	public function test_null_date_and_multi_order_have_null_order_id(): void {
		$row = new LedgerRow( 9, 'No Orders', 10, 'Ed Smith', LedgerStatus::NOT_ENTERED, 0.0, 0.0, null );
		$out = LedgerSerializer::row( $row );
		$this->assertNull( $out['date'] );
		$this->assertNull( $out['orderId'] );
		$this->assertSame( 0, $out['orderCount'] );

		$multi = new LedgerRow( 3, 'Two Orders', 10, 'Ed Smith', LedgerStatus::OWES, 400.0, 0.0, '2026-01-01 00:00:00', array( 1, 2 ) );
		$this->assertNull( LedgerSerializer::row( $multi )['orderId'] );
		$this->assertSame( 2, LedgerSerializer::row( $multi )['orderCount'] );
	}

	public function test_rows_maps_a_list(): void {
		$rows = array(
			new LedgerRow( 1, 'A', 10, 'Ed Smith', LedgerStatus::PAID, 10.0, 10.0, null, array( 4 ) ),
			new LedgerRow( 2, 'B', 0, '', LedgerStatus::UNASSIGNED, 0.0, 0.0, null ),
		);
		$out = LedgerSerializer::rows( $rows );
		$this->assertCount( 2, $out );
		$this->assertSame( 1, $out[0]['id'] );
		$this->assertSame( 2, $out[1]['id'] );
		$this->assertSame( 0, $out[1]['familyId'] );
	}
}
