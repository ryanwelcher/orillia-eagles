<?php
namespace OrillaEagles\Ledger\Domain;

final class Billables {

	/**
	 * Expand members into the (member, player) pairs that owe a charge.
	 *
	 * Per-player products produce one entry per linked player (members with no
	 * players produce none); other products produce one entry per member with
	 * player_id 0.
	 *
	 * @param array $members           list of ['id','name','email'].
	 * @param array $players_by_member map of member id => list of ['id','name'].
	 * @return array<int,array{member_id:int,name:string,email:string,player_id:int,player_name:string}>
	 */
	public static function build( array $members, array $players_by_member, bool $per_player ): array {
		$out = array();
		foreach ( $members as $m ) {
			$id   = (int) $m['id'];
			$base = array(
				'member_id'   => $id,
				'name'        => (string) $m['name'],
				'email'       => (string) $m['email'],
				'player_id'   => 0,
				'player_name' => '',
			);
			if ( ! $per_player ) {
				$out[] = $base;
				continue;
			}
			foreach ( $players_by_member[ $id ] ?? array() as $p ) {
				$out[] = array_merge( $base, array( 'player_id' => (int) $p['id'], 'player_name' => (string) $p['name'] ) );
			}
		}
		return $out;
	}

	public static function key( int $member_id, int $player_id ): string {
		return $member_id . ':' . $player_id;
	}
}
