<?php
namespace OrillaEagles\Ledger\Status;

defined( 'ABSPATH' ) || exit;

final class OrderStatus {

	public const SLUG   = 'requested';       // Used with $order->update_status().
	public const WC_KEY = 'wc-requested';    // Registered post status / wc_order_statuses key.

	public static function register(): void {
		add_action( 'init', array( self::class, 'register_post_status' ) );
		add_filter( 'wc_order_statuses', array( self::class, 'add_to_order_statuses' ) );
	}

	public static function register_post_status(): void {
		register_post_status(
			self::WC_KEY,
			array(
				'label'                     => _x( 'Requested', 'Order status', 'team-membership-ledger' ),
				'public'                    => false,
				'internal'                  => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders. */
				'label_count'               => _n_noop( 'Requested <span class="count">(%s)</span>', 'Requested <span class="count">(%s)</span>', 'team-membership-ledger' ),
			)
		);
	}

	/**
	 * Insert "Requested" right after Pending payment in the status dropdown.
	 *
	 * @param array<string,string> $statuses
	 * @return array<string,string>
	 */
	public static function add_to_order_statuses( array $statuses ): array {
		$new = array();
		foreach ( $statuses as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'wc-pending' === $key ) {
				$new[ self::WC_KEY ] = _x( 'Requested', 'Order status', 'team-membership-ledger' );
			}
		}
		return $new;
	}
}
