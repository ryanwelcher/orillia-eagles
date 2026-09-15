<?php
namespace OrillaEagles\Ledger\Tests\Domain;

use OrillaEagles\Ledger\Domain\Billables;
use PHPUnit\Framework\TestCase;

final class BillablesTest extends TestCase {

	private array $members;
	private array $players;

	protected function setUp(): void {
		$this->members = array(
			array( 'id' => 1, 'name' => 'Coach', 'email' => 'c@x.com' ),
			array( 'id' => 2, 'name' => 'Solo', 'email' => 's@x.com' ),
		);
		$this->players = array(
			1 => array(
				array( 'id' => 41, 'name' => 'Jake' ),
				array( 'id' => 42, 'name' => 'Mia' ),
				array( 'id' => 43, 'name' => 'Noah' ),
			),
		);
	}

	public function test_per_player_product_yields_one_entry_per_player(): void {
		$out = Billables::build( $this->members, $this->players, true );
		$this->assertCount( 3, $out );
		$this->assertSame( array( 41, 42, 43 ), array_column( $out, 'player_id' ) );
		$this->assertSame( 'Mia', $out[1]['player_name'] );
		$this->assertSame( 1, $out[1]['member_id'] );
	}

	public function test_per_player_product_skips_members_without_players(): void {
		$out = Billables::build( $this->members, $this->players, true );
		$this->assertNotContains( 2, array_column( $out, 'member_id' ) );
	}

	public function test_per_member_product_ignores_players(): void {
		$out = Billables::build( $this->members, $this->players, false );
		$this->assertCount( 2, $out );
		$this->assertSame( array( 0, 0 ), array_column( $out, 'player_id' ) );
	}

	public function test_key_combines_member_and_player(): void {
		$this->assertSame( '1:42', Billables::key( 1, 42 ) );
	}
}
