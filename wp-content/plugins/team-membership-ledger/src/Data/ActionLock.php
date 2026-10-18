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
 *
 * The stored value is a one-off token, not just a timestamp, so a request only
 * ever takes over or releases the exact lock it saw: two requests can't both
 * take over one stale lock, and a request that overran the timeout can't
 * release the lock that replaced it.
 */
final class ActionLock {

	/**
	 * How long before a held lock counts as abandoned.
	 *
	 * Deliberately far longer than any real request: a member payment re-reads
	 * the order store several times and can create an order per player, and if
	 * that overran the timeout, the next request would take the lock and write
	 * alongside it — the very thing this class exists to prevent. The cost of
	 * erring long is that a request killed mid-write blocks that one member for
	 * a couple of minutes.
	 */
	private const TIMEOUT = 120;

	private const PREFIX = 'tml_lock_';

	/** The lock covering one member's charges for one product. */
	public static function memberKey( int $product_id, int $member_id ): string {
		return sprintf( 'member_%d_%d', $product_id, $member_id );
	}

	/**
	 * Run $write while holding the named lock.
	 *
	 * @param callable $write
	 * @return mixed Whatever $write returns.
	 * @throws \RuntimeException When someone else holds the lock.
	 */
	public static function run( string $name, callable $write ) {
		$handle = self::acquire( $name );
		if ( null === $handle ) {
			throw new \RuntimeException( __( 'Another change to this member is still saving. Try again in a moment.', 'team-membership-ledger' ) );
		}
		try {
			return $write();
		} finally {
			self::release( $name, $handle );
		}
	}

	/** @return string|null The handle we now hold, or null if someone else holds it. */
	private static function acquire( string $name ): ?string {
		$mine = wp_generate_password( 12, false ) . '|' . time();
		if ( self::insert( $name, $mine ) ) {
			return $mine;
		}
		$held = self::held( $name );
		if ( '' === $held ) {
			// Released between our insert and this read; try once more.
			return self::insert( $name, $mine ) ? $mine : null;
		}
		if ( time() - self::startedAt( $held ) < self::TIMEOUT ) {
			return null;
		}
		// Stale, so that request died. Take it over only if it is still the exact
		// lock we read, so two requests can't both take over the same one.
		return self::steal( $name, $held, $mine ) ? $mine : null;
	}

	/** Delete our own lock only: a request that overran must not release its replacement. */
	private static function release( string $name, string $handle ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See insert().
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				self::option( $name ),
				$handle
			)
		);
		wp_cache_delete( self::option( $name ), 'options' );
	}

	private static function insert( string $name, string $handle ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The insert is the test-and-set; the Options API has no equivalent.
		$won = (bool) $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				self::option( $name ),
				$handle
			)
		);
		if ( $won ) {
			wp_cache_delete( self::option( $name ), 'options' );
		}
		return $won;
	}

	/** One atomic compare-and-set: only the request still matching $held wins. */
	private static function steal( string $name, string $held, string $handle ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See insert().
		$won = (bool) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
				$handle,
				self::option( $name ),
				$held
			)
		);
		if ( $won ) {
			wp_cache_delete( self::option( $name ), 'options' );
		}
		return $won;
	}

	/** Read past the options cache, because these writes go behind it. */
	private static function held( string $name ): string {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See insert().
		return (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::option( $name ) )
		);
	}

	/** A handle we can't parse counts as stale, which clears a lock left by an older version. */
	private static function startedAt( string $handle ): int {
		$parts = explode( '|', $handle );
		return (int) ( $parts[1] ?? 0 );
	}

	private static function option( string $name ): string {
		return self::PREFIX . $name;
	}
}
