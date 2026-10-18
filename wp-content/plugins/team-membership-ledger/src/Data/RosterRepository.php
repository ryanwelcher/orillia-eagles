<?php
namespace OrillaEagles\Ledger\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Members are the users who pay for players: every WooCommerce customer, plus
 * any administrator who has been added as one (an admin who is also a parent).
 * Admins keep their role; a user meta flag marks the ones who pay.
 */
final class RosterRepository {

	public const ROLE        = 'customer';
	public const ADMIN_ROLE  = 'administrator';
	public const MEMBER_META = '_tml_member';
	public const ACTIVE_META = '_tml_active';

	public function isMember( int $user_id ): bool {
		$user = get_userdata( $user_id );
		return $user && self::qualifies( $user );
	}

	/** @param \WP_User $user */
	private static function qualifies( $user ): bool {
		$roles = (array) $user->roles;
		return in_array( self::ROLE, $roles, true )
			|| ( in_array( self::ADMIN_ROLE, $roles, true ) && '1' === get_user_meta( $user->ID, self::MEMBER_META, true ) );
	}

	/** @return array<int,array{id:int,name:string,email:string,active:bool,role:string,optedIn:bool}> */
	public function allMembers(): array {
		$users = get_users(
			array(
				'role__in' => array( self::ROLE, self::ADMIN_ROLE ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
			)
		);
		return array_map( array( $this, 'shape' ), array_values( array_filter( $users, array( self::class, 'qualifies' ) ) ) );
	}

	/**
	 * Administrators who could be added as a member.
	 *
	 * @return array<int,array{id:int,name:string,email:string,active:bool,role:string,optedIn:bool}>
	 */
	public function adminCandidates(): array {
		$users = get_users(
			array(
				'role'    => self::ADMIN_ROLE,
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);
		$candidates = array_filter(
			$users,
			static function ( $user ) {
				return ! self::qualifies( $user );
			}
		);
		return array_map( array( $this, 'shape' ), array_values( $candidates ) );
	}

	/** Make an administrator a member. Their role is left alone. */
	public function addAdmin( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( self::ADMIN_ROLE, (array) $user->roles, true ) ) {
			throw new \RuntimeException( __( 'Only administrators can be added this way. Add anyone else as a new family.', 'team-membership-ledger' ) );
		}
		update_user_meta( $user_id, self::MEMBER_META, '1' );
		$this->setActive( $user_id, true );
	}

	/**
	 * Stop billing an administrator. Only the flag is cleared; the account is
	 * never deleted. Customers are always members (deactivate them instead), and
	 * the flag stays while players or orders still point at the admin.
	 */
	public function removeAdmin( int $user_id ): void {
		if ( ! $this->canRemoveAdmin( $user_id ) ) {
			throw new \RuntimeException( __( 'Unlink this family\'s players and remove its orders first.', 'team-membership-ledger' ) );
		}
		delete_user_meta( $user_id, self::MEMBER_META );
		delete_user_meta( $user_id, self::ACTIVE_META );
	}

	public function canRemoveAdmin( int $user_id ): bool {
		$user = get_userdata( $user_id );
		return $user
			&& ! in_array( self::ROLE, (array) $user->roles, true )
			&& '1' === get_user_meta( $user_id, self::MEMBER_META, true )
			&& empty( ( new PlayerRepository() )->playersByMember()[ $user_id ] )
			&& ! $this->hasOrders( $user_id );
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
				throw new \RuntimeException( __( 'That email already has an account. If it belongs to an administrator, use "Add an administrator" instead.', 'team-membership-ledger' ) );
			}
			throw new \RuntimeException( $user_id->get_error_message() );
		}
		$this->setActive( (int) $user_id, true );
		return (int) $user_id;
	}

	/**
	 * @param \WP_User $user
	 * @return array{id:int,name:string,email:string,active:bool,role:string,optedIn:bool}
	 */
	private function shape( $user ): array {
		$name = trim( $user->first_name . ' ' . $user->last_name );
		// Label staff accounts (e.g. "Administrator"); plain customers get ''.
		$roles = array_diff( (array) $user->roles, array( 'customer' ) );
		$role  = $roles ? translate_user_role( wp_roles()->role_names[ reset( $roles ) ] ?? reset( $roles ) ) : '';
		return array(
			'id'      => (int) $user->ID,
			'name'    => '' !== $name ? $name : $user->display_name,
			'email'   => (string) $user->user_email,
			'active'  => '1' === get_user_meta( $user->ID, self::ACTIVE_META, true ),
			'role'    => $role,
			// An administrator added as a member, rather than a customer.
			'optedIn' => ! in_array( self::ROLE, (array) $user->roles, true ),
		);
	}
}
