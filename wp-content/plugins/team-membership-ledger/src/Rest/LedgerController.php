<?php
namespace OrillaEagles\Ledger\Rest;

use OrillaEagles\Ledger\Admin\Menu;
use OrillaEagles\Ledger\Data\BillableRepository;
use OrillaEagles\Ledger\Data\OrderRepository;
use OrillaEagles\Ledger\Data\PlayerRepository;
use OrillaEagles\Ledger\Data\ProductFlag;
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
					'player_id'  => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint', 'default' => 0 ),
					'amount'     => array( 'type' => 'number', 'required' => true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/ledger/mark-paid',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'mark_paid' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'product_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'order_id'   => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint', 'default' => 0 ),
					'member_id'  => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint', 'default' => 0 ),
					'player_id'  => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint', 'default' => 0 ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/ledger/set-quantity',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'set_quantity' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'product_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'order_id'   => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint', 'default' => 0 ),
					'member_id'  => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint', 'default' => 0 ),
					'player_id'  => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint', 'default' => 0 ),
					'qty'        => array( 'type' => 'integer', 'required' => true, 'minimum' => 1 ),
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

		$rows = array();
		if ( $product_id ) {
			$rows = LedgerSerializer::rows( self::ledger_rows( $product_id, $orders ) );
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
		$player_id  = absint( $request['player_id'] );
		$amount     = round( (float) $request['amount'], 2 );

		if ( $amount <= 0 ) {
			return new \WP_Error( 'tml_invalid_amount', __( 'Enter a payment amount greater than zero.', 'team-membership-ledger' ), array( 'status' => 400 ) );
		}

		try {
			$order_id = self::resolve_order( $order_id, $member_id, $player_id, $product_id );
			( new OrderRepository() )->addPayment( $order_id, $amount );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'tml_action_failed', $e->getMessage(), array( 'status' => 400 ) );
		}

		return self::row_response( $product_id, $order_id );
	}

	public static function mark_paid( \WP_REST_Request $request ) {
		$product_id = absint( $request['product_id'] );
		$order_id   = absint( $request['order_id'] );
		$member_id  = absint( $request['member_id'] );
		$player_id  = absint( $request['player_id'] );

		try {
			$order_id = self::resolve_order( $order_id, $member_id, $player_id, $product_id );
			( new OrderRepository() )->markPaid( $order_id );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'tml_action_failed', $e->getMessage(), array( 'status' => 400 ) );
		}

		return self::row_response( $product_id, $order_id );
	}

	public static function set_quantity( \WP_REST_Request $request ) {
		$product_id = absint( $request['product_id'] );
		$order_id   = absint( $request['order_id'] );
		$member_id  = absint( $request['member_id'] );
		$player_id  = absint( $request['player_id'] );
		$qty        = (int) $request['qty'];

		if ( $qty < 1 ) {
			return new \WP_Error( 'tml_invalid_qty', __( 'Quantity must be at least 1.', 'team-membership-ledger' ), array( 'status' => 400 ) );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || ! ProductFlag::allowsQuantity( $product ) ) {
			return new \WP_Error( 'tml_qty_locked', __( 'This product is limited to one per charge.', 'team-membership-ledger' ), array( 'status' => 400 ) );
		}

		try {
			if ( $order_id ) {
				( new OrderRepository() )->setQuantity( $order_id, $product_id, $qty );
			} else {
				$order_id = self::resolve_order( 0, $member_id, $player_id, $product_id, $qty );
			}
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'tml_action_failed', $e->getMessage(), array( 'status' => 400 ) );
		}

		return self::row_response( $product_id, $order_id );
	}

	/** Resolve an existing order id, or create the Requested order on the fly. */
	private static function resolve_order( int $order_id, int $member_id, int $player_id, int $product_id, int $qty = 1 ): int {
		if ( $order_id ) {
			return $order_id;
		}
		if ( ! $member_id || ! $product_id ) {
			throw new \RuntimeException( __( 'No order to act on.', 'team-membership-ledger' ) );
		}
		// Only charge a player to the member they are linked to.
		if ( $player_id && (int) get_post_meta( $player_id, PlayerRepository::MEMBER_META, true ) !== $member_id ) {
			throw new \RuntimeException( __( 'That player is not linked to this member.', 'team-membership-ledger' ) );
		}
		return ( new OrderRepository() )->createRequestedOrder( $member_id, $product_id, $player_id, $qty );
	}

	/** Recompute and return the single affected (member, player) row after a write. */
	private static function row_response( int $product_id, int $order_id ): \WP_REST_Response {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_REST_Response( null, 200 );
		}
		// The order itself is the source of truth for who the row belongs to.
		$member_id = (int) $order->get_customer_id();
		$player_id = (int) $order->get_meta( OrderRepository::PLAYER_META );

		foreach ( self::ledger_rows( $product_id, new OrderRepository() ) as $row ) {
			if ( $row->memberId() === $member_id && $row->playerId() === $player_id ) {
				return new \WP_REST_Response( LedgerSerializer::row( $row ), 200 );
			}
		}
		return new \WP_REST_Response( null, 200 );
	}

	/** @return LedgerRow[] */
	private static function ledger_rows( int $product_id, OrderRepository $orders ): array {
		return LedgerCalculator::forProduct(
			( new BillableRepository() )->forProduct( $product_id ),
			$orders->productRecords( $product_id )
		);
	}

	private static function currency(): array {
		return array(
			'symbol'   => html_entity_decode( get_woocommerce_currency_symbol() ),
			'decimals' => wc_get_price_decimals(),
		);
	}
}
