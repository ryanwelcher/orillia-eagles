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
	public const PLAYER_ROLE   = 'player';

	public static function register(): void {
		add_filter( 'pre_trash_post', array( self::class, 'guardRemoval' ), 10, 2 );
		add_filter( 'pre_delete_post', array( self::class, 'guardRemoval' ), 10, 2 );
	}

	/**
	 * Refuse to trash or delete a player who still has an unpaid charge. Rows
	 * come from the roster, so removing the player would drop the charge, and
	 * what was paid toward it, out of the Ledger, exactly what unlink() refuses.
	 *
	 * @param bool|null $check null to let WordPress carry on.
	 * @param \WP_Post  $post
	 * @return bool|null
	 */
	public static function guardRemoval( $check, $post ) {
		if ( null !== $check || ! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type ) {
			return $check;
		}
		$member_id = (int) get_post_meta( $post->ID, self::MEMBER_META, true );
		if ( ! $member_id || ! ( new OrderRepository() )->hasOpenPlayerCharge( $member_id, (int) $post->ID ) ) {
			return $check;
		}
		$message = sprintf(
			/* translators: %s: player name */
			__( '%s still has an unpaid charge in the Membership Ledger. Settle or cancel that order in WooCommerce before removing the player.', 'team-membership-ledger' ),
			$post->post_title
		);
		// The list table and editor only show a generic failure, so explain it
		// where a page can be shown; elsewhere (REST, WP-CLI) just refuse.
		if ( is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			wp_die( esc_html( $message ), '', array( 'back_link' => true ) );
		}
		return false;
	}

	/**
	 * Only players are billed. Coaches share the post type, and the taxonomy can
	 * gain roles later, so a post tagged with some other role is left out. A post
	 * with no role yet is still a player, so a new roster entry is never silently
	 * left off the ledger, and a post tagged Player as well as another role is
	 * billed — the Player tag decides.
	 *
	 * `roster_role` is hierarchical, and allPlayers() matches child terms the way
	 * WP_Tax_Query does, so a role nested under Player (a "Goalie", say) counts
	 * here too. Otherwise a roster entry could be listed and then refused a link.
	 */
	public static function isBillable( int $post_id ): bool {
		if ( ! taxonomy_exists( self::ROLE_TAXONOMY ) ) {
			return true;
		}
		$roles = wp_get_object_terms( $post_id, self::ROLE_TAXONOMY );
		if ( is_wp_error( $roles ) || ! $roles ) {
			return true;
		}
		$player = get_term_by( 'slug', self::PLAYER_ROLE, self::ROLE_TAXONOMY );
		foreach ( $roles as $role ) {
			if ( self::PLAYER_ROLE === $role->slug ) {
				return true;
			}
			if ( $player && term_is_ancestor_of( $player->term_id, $role->term_id, self::ROLE_TAXONOMY ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the player exists, is not in the trash, and is linked to the member.
	 *
	 * @param bool $fresh read the link past the post-meta cache, for a long-running
	 *                    request whose cache was primed before someone moved the player.
	 */
	public static function isLinkedTo( int $player_id, int $member_id, bool $fresh = false ): bool {
		if ( $fresh ) {
			clean_post_cache( $player_id );
		}
		if ( self::POST_TYPE !== get_post_type( $player_id ) || 'trash' === get_post_status( $player_id ) ) {
			return false;
		}
		return (int) get_post_meta( $player_id, self::MEMBER_META, true ) === $member_id;
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
		// Players only, matching isBillable(): tagged Player, or not tagged yet.
		if ( taxonomy_exists( self::ROLE_TAXONOMY ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'relation' => 'OR',
				array(
					'taxonomy' => self::ROLE_TAXONOMY,
					'field'    => 'slug',
					'terms'    => array( self::PLAYER_ROLE ),
				),
				array(
					'taxonomy' => self::ROLE_TAXONOMY,
					'operator' => 'NOT EXISTS',
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
		if ( self::POST_TYPE !== get_post_type( $player_id ) || 'trash' === get_post_status( $player_id ) ) {
			throw new \RuntimeException( __( 'That player could not be found.', 'team-membership-ledger' ) );
		}
		if ( ! self::isBillable( $player_id ) ) {
			throw new \RuntimeException( __( 'Only players can be linked to a member.', 'team-membership-ledger' ) );
		}
		if ( ! get_userdata( $member_id ) ) {
			throw new \RuntimeException( __( 'That member could not be found.', 'team-membership-ledger' ) );
		}
		// The Members list only manages customers, so a link to any other user
		// would never show there and never be billed.
		if ( ! ( new RosterRepository() )->isMember( $member_id ) ) {
			throw new \RuntimeException( __( 'Players can only be linked to a member from the Members list.', 'team-membership-ledger' ) );
		}
		// Moving a player is unlink, then link. A link over an existing one is a
		// stale screen, and would skip the unpaid-charge check in unlink().
		$current = (int) get_post_meta( $player_id, self::MEMBER_META, true );
		if ( $current && $current !== $member_id && ( new RosterRepository() )->isMember( $current ) ) {
			throw new \RuntimeException( __( 'That player is already linked to another member. Unlink them first.', 'team-membership-ledger' ) );
		}
		update_post_meta( $player_id, self::MEMBER_META, $member_id );
	}

	/**
	 * @throws \RuntimeException When the player still has an unpaid charge under the member.
	 */
	public function unlink( int $player_id ): void {
		if ( self::POST_TYPE !== get_post_type( $player_id ) ) {
			return;
		}
		// Charges are found through the link, so unlinking would drop an unpaid
		// charge, and what was paid toward it, out of the Ledger. A link to a user
		// who is no longer a member can always be cleared.
		$member_id = (int) get_post_meta( $player_id, self::MEMBER_META, true );
		if ( $member_id && ( new RosterRepository() )->isMember( $member_id ) && ( new OrderRepository() )->hasOpenPlayerCharge( $member_id, $player_id ) ) {
			throw new \RuntimeException( __( 'That player still has an unpaid charge under this member. Settle or cancel it in WooCommerce before unlinking.', 'team-membership-ledger' ) );
		}
		delete_post_meta( $player_id, self::MEMBER_META );
	}
}
