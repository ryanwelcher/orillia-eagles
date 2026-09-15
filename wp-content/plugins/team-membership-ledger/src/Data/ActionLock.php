<?php
namespace OrillaEagles\Ledger\Data;

defined( 'ABSPATH' ) || exit;

/**
 * A short-lived lock around one member's charges, so two people acting on the
 * same member at the same time can't each create a charge for the same player
 * or each spend the same balance.
 *
 * `INSERT IGNORE` either wins or does nothing, so exactly one request takes the
 * lock. This is how WordPress core locks in `WP_Upgrader::create_lock()`;
 * `add_option()` can't do it, because it overwrites instead of failing.
 */
final class ActionLock {

	/** Long enough for a slow order write, short enough to clear a request that died. */
	private const TIMEOUT = 30;

	private const PREFIX = 'tml_lock_';

	/**
	 * Run $write while holding the named lock.
	 *
	 * @param callable $write
	 * @return mixed Whatever $write returns.
	 * @throws \RuntimeException When someone else holds the lock.
	 */
	public static function run( string $name, callable $write ) {
		if ( ! self::acquire( $name ) ) {
			throw new \RuntimeException( __( 'Another change to this member is still saving. Try again in a moment.', 'team-membership-ledger' ) );
		}
		try {
			return $write();
		} finally {
			self::release( $name );
		}
	}

	private static function acquire( string $name ): bool {
		if ( self::insert( $name ) ) {
			return true;
		}
		// Someone holds it. Only take it over once the timeout has passed, which
		// means that request died before it could release.
		if ( time() - self::heldSince( $name ) < self::TIMEOUT ) {
			return false;
		}
		self::release( $name );
		return self::insert( $name );
	}

	private static function release( string $name ): void {
		delete_option( self::option( $name ) );
	}

	private static function insert( string $name ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The insert is the test-and-set; the Options API has no equivalent.
		return (bool) $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				self::option( $name ),
				(string) time()
			)
		);
	}

	/** Read past the options cache, because insert() writes behind it. */
	private static function heldSince( string $name ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See insert().
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::option( $name ) )
		);
	}

	private static function option( string $name ): string {
		return self::PREFIX . $name;
	}
}
