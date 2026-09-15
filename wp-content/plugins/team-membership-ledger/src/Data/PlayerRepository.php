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
	public const ROLE_TAXONOMY = 'roster_role';
	public const COACH_ROLE    = 'coach';

	public static function isCoach( int $post_id ): bool {
		return taxonomy_exists( self::ROLE_TAXONOMY ) && has_term( self::COACH_ROLE, self::ROLE_TAXONOMY, $post_id );
	}

	/** @return array<int,array{id:int,name:string,member_id:int}> */
	public function allPlayers(): array {
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			return array();
		}
		$args = array(
			'post_type'   => self::POST_TYPE,
			'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		);
		// Coaches share the post type; they are never billed as players.
		// Untagged posts still count as players.
		if ( taxonomy_exists( self::ROLE_TAXONOMY ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => self::ROLE_TAXONOMY,
					'field'    => 'slug',
					'terms'    => array( self::COACH_ROLE ),
					'operator' => 'NOT IN',
				),
			);
		}
		$posts = get_posts( $args );
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
		if ( self::isCoach( $player_id ) ) {
			throw new \RuntimeException( __( 'Coaches cannot be linked to a member.', 'team-membership-ledger' ) );
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
