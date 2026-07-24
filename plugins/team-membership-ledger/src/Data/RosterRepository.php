<?php
namespace OrillaEagles\Ledger\Data;

defined( 'ABSPATH' ) || exit;

final class RosterRepository {

	public const ACTIVE_META = '_tml_active';

	/** @return array<int,array{id:int,name:string,email:string,active:bool}> */
	public function allMembers(): array {
		$users = get_users(
			array(
				'role'    => 'customer',
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);
		return array_map( array( $this, 'shape' ), $users );
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
		$user_id = wc_create_new_customer( sanitize_email( $email ), '', '', array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
		) );
		if ( is_wp_error( $user_id ) ) {
			throw new \RuntimeException( $user_id->get_error_message() );
		}
		$this->setActive( (int) $user_id, true );
		return (int) $user_id;
	}

	/**
	 * @param \WP_User $user
	 * @return array{id:int,name:string,email:string,active:bool}
	 */
	private function shape( $user ): array {
		$name = trim( $user->first_name . ' ' . $user->last_name );
		return array(
			'id'     => (int) $user->ID,
			'name'   => '' !== $name ? $name : $user->display_name,
			'email'  => (string) $user->user_email,
			'active' => '1' === get_user_meta( $user->ID, self::ACTIVE_META, true ),
		);
	}
}
