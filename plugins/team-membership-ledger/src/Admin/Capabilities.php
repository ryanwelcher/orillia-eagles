<?php
namespace OrillaEagles\Ledger\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Dedicated capability that unlocks the Membership screens (Members, Ledger,
 * Create Charges) WITHOUT granting WooCommerce store-management access.
 *
 * Granted to administrators and editors so coordinators can record dues and
 * payments without seeing the rest of the WooCommerce admin. The plugin's own
 * order reads/writes run programmatically, so they work for any user who holds
 * this capability — no `manage_woocommerce` required.
 */
final class Capabilities {

	public const CAP = 'manage_team_membership';

	/** Roles that receive the capability. */
	private const ROLES = array( 'administrator', 'editor' );

	/**
	 * Bump when the granted role set changes so ensure() re-applies it to
	 * already-active installs.
	 */
	private const VERSION = '1';

	private const VERSION_OPTION = 'tml_caps_version';

	/** Add the capability to the target roles. Runs on activation. */
	public static function grant(): void {
		foreach ( self::ROLES as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && ! $role->has_cap( self::CAP ) ) {
				$role->add_cap( self::CAP );
			}
		}
	}

	/** Remove the capability from any role that has it. Runs on uninstall. */
	public static function revoke(): void {
		$roles = array( 'administrator', 'editor', 'shop_manager' );
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && $role->has_cap( self::CAP ) ) {
				$role->remove_cap( self::CAP );
			}
		}
		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Grant once for already-active installs, and re-run when the granted role
	 * set changes (VERSION bump). Cheap on the hot path: a single autoloaded
	 * option read, writing only when the version differs.
	 */
	public static function ensure(): void {
		if ( get_option( self::VERSION_OPTION ) === self::VERSION ) {
			return;
		}
		self::grant();
		update_option( self::VERSION_OPTION, self::VERSION );
	}
}
