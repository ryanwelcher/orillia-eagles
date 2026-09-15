<?php
namespace OrillaEagles\Ledger\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Links `player` posts (from the orillia-players plugin) to the member who pays
 * for them. The link lives on the player, so each player has one member.
 */
final class PlayerRepository {

	public const POST_TYPE   = 'player';
	public const MEMBER_META = '_tml_member_id';

	/** @return array<int,array{id:int,name:string,member_id:int}> */
	public function allPlayers(): array {
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		return array_map(
			static function ( $post ) {
				return array(
					// Raw title: this also feeds JSON, where get_the_title()'s entity encoding would leak through.
					'id'        => (int) $post->ID,
					'name'      => (string) $post->post_title,
					'member_id' => (int) get_post_meta( $post->ID, self::MEMBER_META, true ),
				);
			},
			$posts
		);
	}

	/** @return array<int,array<int,array{id:int,name:string}>> member id => linked players. */
	public function playersByMember(): array {
		$out = array();
		foreach ( $this->allPlayers() as $p ) {
			if ( $p['member_id'] ) {
				$out[ $p['member_id'] ][] = array( 'id' => $p['id'], 'name' => $p['name'] );
			}
		}
		return $out;
	}

	public function link( int $player_id, int $member_id ): void {
		if ( self::POST_TYPE !== get_post_type( $player_id ) ) {
			throw new \RuntimeException( __( 'That player could not be found.', 'team-membership-ledger' ) );
		}
		if ( ! get_userdata( $member_id ) ) {
			throw new \RuntimeException( __( 'That member could not be found.', 'team-membership-ledger' ) );
		}
		update_post_meta( $player_id, self::MEMBER_META, $member_id );
	}

	public function unlink( int $player_id ): void {
		if ( self::POST_TYPE !== get_post_type( $player_id ) ) {
			return;
		}
		delete_post_meta( $player_id, self::MEMBER_META );
	}
}
