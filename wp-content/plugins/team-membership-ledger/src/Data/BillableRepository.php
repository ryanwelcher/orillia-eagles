<?php
namespace OrillaEagles\Ledger\Data;

use OrillaEagles\Ledger\Domain\Billables;

defined( 'ABSPATH' ) || exit;

final class BillableRepository {

	/**
	 * Who should be charged for a product: active members, expanded to their
	 * linked players when the product is "Charge per player".
	 *
	 * @param int $member_id limit to one member (0 = everyone).
	 * @return array<int,array{member_id:int,name:string,email:string,player_id:int,player_name:string}>
	 */
	public function forProduct( int $product_id, int $member_id = 0 ): array {
		$per_player = ProductFlag::isPerPlayer( $product_id );
		$members    = ( new RosterRepository() )->activeMembers();
		if ( $member_id ) {
			// One member's action only needs that member's rows.
			$members = array_values( array_filter( $members, static fn( $m ) => (int) $m['id'] === $member_id ) );
		}
		return Billables::build(
			$members,
			$per_player ? ( new PlayerRepository() )->playersByMember() : array(),
			$per_player
		);
	}
}
