<?php
namespace OrillaEagles\Ledger\Rest;

use OrillaEagles\Ledger\Admin\Menu;
use OrillaEagles\Ledger\Data\OrderRepository;
use OrillaEagles\Ledger\Data\RosterRepository;
use OrillaEagles\Ledger\Domain\LedgerCalculator;
use OrillaEagles\Ledger\Domain\LedgerRow;
use OrillaEagles\Ledger\Domain\LedgerSerializer;

defined( 'ABSPATH' ) || exit;

final class LedgerController {

	public const NAMESPACE = 'tml/v1';

	public static function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/ledger',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_ledger' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'product_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/ledger/add-payment',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'add_payment' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'product_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'order_id'   => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint', 'default' => 0 ),
					'member_id'  => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint', 'default' => 0 ),
					'amount'     => array( 'type' => 'number', 'required' => true ),
				),
			)
		);
	}

	public static function can_manage(): bool {
		return current_user_can( Menu::CAP );
	}

	public static function get_ledger( \WP_REST_Request $request ): \WP_REST_Response {
		$product_id = absint( $request['product_id'] );
		$orders     = new OrderRepository();
		$roster     = new RosterRepository();

		$rows = array();
		if ( $product_id ) {
			$members = $roster->activeMembers();
			$records = $orders->productRecords( $product_id );
			$rows    = LedgerSerializer::rows( LedgerCalculator::forProduct( $members, $records ) );
		}

		return new \WP_REST_Response(
			array(
				'rows'     => $rows,
				'products' => $orders->sellableProducts(),
				'currency' => self::currency(),
			),
			200
		);
	}

	public static function add_payment( \WP_REST_Request $request ) {
		$product_id = absint( $request['product_id'] );
		$order_id   = absint( $request['order_id'] );
		$member_id  = absint( $request['member_id'] );
		$amount     = round( (float) $request['amount'], 2 );

		if ( $amount <= 0 ) {
			return new \WP_Error( 'tml_invalid_amount', __( 'Enter a payment amount greater than zero.', 'team-membership-ledger' ), array( 'status' => 400 ) );
		}

		try {
			$order_id = self::resolve_order( $order_id, $member_id, $product_id );
			( new OrderRepository() )->addPayment( $order_id, $amount );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'tml_action_failed', $e->getMessage(), array( 'status' => 400 ) );
		}

		return self::row_response( $product_id, $member_id ?: null, $order_id );
	}

	/** Resolve an existing order id, or create the Requested order on the fly. */
	private static function resolve_order( int $order_id, int $member_id, int $product_id ): int {
		if ( $order_id ) {
			return $order_id;
		}
		if ( ! $member_id || ! $product_id ) {
			throw new \RuntimeException( __( 'No order to act on.', 'team-membership-ledger' ) );
		}
		return ( new OrderRepository() )->createRequestedOrder( $member_id, $product_id );
	}

	/** Recompute and return the single affected member's row after a write. */
	private static function row_response( int $product_id, ?int $member_id, int $order_id ): \WP_REST_Response {
		if ( ! $member_id && $order_id ) {
			$order     = wc_get_order( $order_id );
			$member_id = $order ? (int) $order->get_customer_id() : 0;
		}

		$orders  = new OrderRepository();
		$roster  = new RosterRepository();
		$members = $roster->activeMembers();
		$records = $orders->productRecords( $product_id );
		$rows    = LedgerCalculator::forProduct( $members, $records );

		foreach ( $rows as $row ) {
			if ( $row->memberId() === $member_id ) {
				return new \WP_REST_Response( LedgerSerializer::row( $row ), 200 );
			}
		}
		return new \WP_REST_Response( null, 200 );
	}

	private static function currency(): array {
		return array(
			'symbol'   => html_entity_decode( get_woocommerce_currency_symbol() ),
			'decimals' => wc_get_price_decimals(),
		);
	}
}
