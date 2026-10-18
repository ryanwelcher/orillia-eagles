<?php
namespace OrillaEagles\Ledger\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Families are users who pay for one or more players. Being a family is a
 * user meta flag, not a role, so an existing account (e.g. an administrator
 * who is also a parent) can be a family without changing its role.
 */
final class FamilyRepository {

	public const FAMILY_META = '_tml_family';
	public const ACTIVE_META = '_tml_active';

	/** @return array<int,array{id:int,name:string,email:string,active:bool,role:string}> */
	public function allFamilies(): array {
		$users = get_users(
			array(
				'meta_key'   => self::FAMILY_META,
				'meta_value' => '1',
				'orderby'    => 'display_name',
				'order'      => 'ASC',
			)
		);
		return array_map( array( $this, 'shape' ), $users );
	}

	/**
	 * Accounts that could be added as a family: everyone who isn't one yet.
	 *
	 * @return array<int,array{id:int,name:string,email:string,active:bool,role:string}>
	 */
	public function nonFamilyUsers(): array {
		$users = get_users(
			array(
				'meta_query' => array(
					array(
						'key'     => self::FAMILY_META,
						'compare' => 'NOT EXISTS',
					),
				),
				'orderby'    => 'display_name',
				'order'      => 'ASC',
			)
		);
		return array_map( array( $this, 'shape' ), $users );
	}

	/** @return array<int,array{id:int,name:string,email:string,active:bool}> */
	public function activeFamilies(): array {
		return array_values(
			array_filter(
				$this->allFamilies(),
				static function ( $m ) {
					return $m['active'];
				}
			)
		);
	}

	public function setActive( int $user_id, bool $active ): void {
		update_user_meta( $user_id, self::ACTIVE_META, $active ? '1' : '0' );
	}

	/** Make an existing account a family. Its role is left alone. */
	public function addExisting( int $user_id ): void {
		if ( ! get_userdata( $user_id ) ) {
			throw new \RuntimeException( __( 'User not found.', 'team-membership-ledger' ) );
		}
		update_user_meta( $user_id, self::FAMILY_META, '1' );
		$this->setActive( $user_id, true );
	}

	/**
	 * Stop treating an account as a family. Only the flag is cleared; the user
	 * account is never deleted. Refused while players or orders point at it.
	 */
	public function remove( int $user_id ): void {
		if ( ! $this->canRemove( $user_id ) ) {
			throw new \RuntimeException( __( 'Unlink this family\'s players and remove its orders first.', 'team-membership-ledger' ) );
		}
		delete_user_meta( $user_id, self::FAMILY_META );
		delete_user_meta( $user_id, self::ACTIVE_META );
	}

	public function canRemove( int $user_id ): bool {
		if ( ( new PlayerRepository() )->playersForFamily( $user_id ) ) {
			return false;
		}
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 1,
				'type'        => 'shop_order',
				'status'      => array_keys( wc_get_order_statuses() ),
				'return'      => 'ids',
			)
		);
		return empty( $orders );
	}

	public function createFamily( string $first_name, string $last_name, string $email ): int {
		// Families get an account but aren't told about it (logins aren't used yet).
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
