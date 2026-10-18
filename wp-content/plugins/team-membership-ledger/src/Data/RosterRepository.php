<?php
namespace OrillaEagles\Ledger\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Members are the users who pay for players. Being a member is a user meta
 * flag, not a role, so an existing account (an administrator who is also a
 * parent, say) can be billed without changing its role.
 */
final class RosterRepository {

	public const MEMBER_META = '_tml_member';
	public const ACTIVE_META = '_tml_active';

	/** Bump to re-run migrate(). */
	private const VERSION        = '1';
	private const VERSION_OPTION = 'tml_members_version';

	/**
	 * Members used to be every WooCommerce customer. Flag those once so they
	 * stay members now that the flag decides. Cheap on the hot path: a single
	 * autoloaded option read.
	 */
	public static function migrate(): void {
		if ( get_option( self::VERSION_OPTION ) === self::VERSION ) {
			return;
		}
		foreach ( get_users( array( 'role' => 'customer', 'fields' => 'ID' ) ) as $user_id ) {
			update_user_meta( (int) $user_id, self::MEMBER_META, '1' );
		}
		update_option( self::VERSION_OPTION, self::VERSION );
	}

	public function isMember( int $user_id ): bool {
		return get_userdata( $user_id ) && '1' === get_user_meta( $user_id, self::MEMBER_META, true );
	}

	/** @return array<int,array{id:int,name:string,email:string,active:bool,role:string}> */
	public function allMembers(): array {
		$users = get_users(
			array(
				'meta_key'   => self::MEMBER_META,
				'meta_value' => '1',
				'orderby'    => 'display_name',
				'order'      => 'ASC',
			)
		);
		return array_map( array( $this, 'shape' ), $users );
	}

	/**
	 * Accounts that could be added as a member: everyone who isn't one yet.
	 *
	 * @return array<int,array{id:int,name:string,email:string,active:bool,role:string}>
	 */
	public function nonMembers(): array {
		$users = get_users(
			array(
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::MEMBER_META,
						'compare' => 'NOT EXISTS',
					),
				),
				'orderby'    => 'display_name',
				'order'      => 'ASC',
			)
		);
		return array_map( array( $this, 'shape' ), $users );
	}

	/** Make an existing account a member. Its role is left alone. */
	public function addExisting( int $user_id ): void {
		if ( ! get_userdata( $user_id ) ) {
			throw new \RuntimeException( __( 'User not found.', 'team-membership-ledger' ) );
		}
		update_user_meta( $user_id, self::MEMBER_META, '1' );
		$this->setActive( $user_id, true );
	}

	/**
	 * Stop billing an account. Only the flag is cleared; the user is never
	 * deleted. Refused while players or orders still point at it.
	 */
	public function remove( int $user_id ): void {
		if ( ! $this->canRemove( $user_id ) ) {
			throw new \RuntimeException( __( 'Unlink this family\'s players and remove its orders first.', 'team-membership-ledger' ) );
		}
		delete_user_meta( $user_id, self::MEMBER_META );
		delete_user_meta( $user_id, self::ACTIVE_META );
	}

	public function canRemove( int $user_id ): bool {
		return empty( ( new PlayerRepository() )->playersByMember()[ $user_id ] ) && ! $this->hasOrders( $user_id );
	}

	/** Whether any order, in any status, is billed to the user. */
	public function hasOrders( int $user_id ): bool {
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 1,
				'type'        => 'shop_order',
				'status'      => array_keys( wc_get_order_statuses() ),
				'return'      => 'ids',
			)
		);
		return ! empty( $orders );
	}

	/** @return array<int,array{id:int,name:string,email:string,active:bool}> */
	public function activeMembers(): array {
		return array_values(
			array_filter(
				$this->allMembers(),
				static function ( $m ) {
					return $m['active'];
				}
			)
		);
	}

	public function setActive( int $user_id, bool $active ): void {
		update_user_meta( $user_id, self::ACTIVE_META, $active ? '1' : '0' );
	}

	public function createMember( string $first_name, string $last_name, string $email ): int {
		// Members get a login but aren't told about it (logins aren't used yet).
		add_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false' );
		$user_id = wc_create_new_customer( sanitize_email( $email ), '', '', array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
		) );
		remove_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false' );
		if ( is_wp_error( $user_id ) ) {
			if ( 'registration-error-email-exists' === $user_id->get_error_code() ) {
				throw new \RuntimeException( __( 'That email already has an account. Use "Add an existing account" instead.', 'team-membership-ledger' ) );
			}
			throw new \RuntimeException( $user_id->get_error_message() );
		}
		$this->addExisting( (int) $user_id );
		return (int) $user_id;
	}

	/**
	 * @param \WP_User $user
	 * @return array{id:int,name:string,email:string,active:bool,role:string}
	 */
	private function shape( $user ): array {
		$name = trim( $user->first_name . ' ' . $user->last_name );
		// Label staff accounts (e.g. "Administrator"); plain customers get ''.
		$roles = array_diff( (array) $user->roles, array( 'customer' ) );
		$role  = $roles ? translate_user_role( wp_roles()->role_names[ reset( $roles ) ] ?? reset( $roles ) ) : '';
		return array(
			'id'     => (int) $user->ID,
			'name'   => '' !== $name ? $name : $user->display_name,
			'email'  => (string) $user->user_email,
			'active' => '1' === get_user_meta( $user->ID, self::ACTIVE_META, true ),
			'role'   => $role,
		);
	}
}
