<?php
namespace OrillaEagles\Ledger\Data;

use OrillaEagles\Ledger\Domain\Billables;

defined( 'ABSPATH' ) || exit;

final class BillableRepository {

	/**
	 * Who should be charged for a product: active members, expanded to their
	 * linked players when the product is "Charge per player".
	 *
	 * @return array<int,array{member_id:int,name:string,email:string,player_id:int,player_name:string}>
	 */
	public function forProduct( int $product_id ): array {
		$per_player = ProductFlag::isPerPlayer( $product_id );
		return Billables::build(
			( new RosterRepository() )->activeMembers(),
			$per_player ? ( new PlayerRepository() )->playersByMember() : array(),
			$per_player
		);
	}
}
