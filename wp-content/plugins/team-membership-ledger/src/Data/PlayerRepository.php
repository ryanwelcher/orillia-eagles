<?php
namespace OrillaEagles\Ledger\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Links `player` posts (from the orillia-players plugin) to the family
 * (WooCommerce customer) that pays for them.
 */
final class PlayerRepository {

	public const POST_TYPE   = 'player';
	public const FAMILY_META = '_tml_family_id';

	public static function registerMeta(): void {
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			return;
		}
		register_post_meta(
			self::POST_TYPE,
			self::FAMILY_META,
			array(
				'type'         => 'integer',
				'single'       => true,
				'default'      => 0,
				'show_in_rest' => false,
			)
		);
	}

	/** @return array<int,array{id:int,name:string,family_id:int,is_coach:bool}> */
	public function allPlayers(): array {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		return array_map( array( $this, 'shape' ), $posts );
	}

	/** @return array<int,array{id:int,name:string,family_id:int,is_coach:bool}> */
	public function playersForFamily( int $family_id ): array {
		return array_values(
			array_filter(
				$this->allPlayers(),
				static function ( $p ) use ( $family_id ) {
					return $p['family_id'] === $family_id;
				}
			)
		);
	}

	public function familyIdFor( int $player_id ): int {
		return (int) get_post_meta( $player_id, self::FAMILY_META, true );
	}

	/**
	 * Make $player_ids exactly the players this family pays for. Ticking a
	 * player linked to another family moves them here.
	 *
	 * @param int[] $player_ids
	 */
	public function setFamilyPlayers( int $family_id, array $player_ids ): void {
		$wanted = array_flip( array_map( 'intval', $player_ids ) );

		foreach ( $this->playersForFamily( $family_id ) as $p ) {
			if ( ! isset( $wanted[ $p['id'] ] ) ) {
				delete_post_meta( $p['id'], self::FAMILY_META );
			}
		}
		foreach ( array_keys( $wanted ) as $player_id ) {
			if ( self::POST_TYPE === get_post_type( $player_id ) ) {
				update_post_meta( $player_id, self::FAMILY_META, $family_id );
			}
		}
	}

	/**
	 * @param \WP_Post $post
	 * @return array{id:int,name:string,family_id:int,is_coach:bool}
	 */
	private function shape( $post ): array {
		return array(
			'id'        => (int) $post->ID,
			// get_the_title() returns HTML (&#8217; etc.); callers escape on output.
			'name'      => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'family_id' => (int) get_post_meta( $post->ID, self::FAMILY_META, true ),
			'is_coach'  => has_term( 'coach', 'roster_role', $post ),
		);
	}
}
