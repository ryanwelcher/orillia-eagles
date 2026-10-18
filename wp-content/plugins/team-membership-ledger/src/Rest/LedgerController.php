<?php
namespace OrillaEagles\Ledger\Rest;

use OrillaEagles\Ledger\Admin\Menu;
use OrillaEagles\Ledger\Data\OrderRepository;
use OrillaEagles\Ledger\Data\PlayerRepository;
use OrillaEagles\Ledger\Data\RosterRepository;
use OrillaEagles\Ledger\Domain\LedgerCalculator;
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
					'player_id'  => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint', 'default' => 0 ),
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

		$rows = $product_id ? LedgerSerializer::rows( self::rows_for( $product_id ) ) : array();

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
		$player_id  = absint( $request['player_id'] );
		$amount     = round( (float) $request['amount'], 2 );

		if ( $amount <= 0 ) {
			return new \WP_Error( 'tml_invalid_amount', __( 'Enter a payment amount greater than zero.', 'team-membership-ledger' ), array( 'status' => 400 ) );
		}

		try {
			$order_id = self::resolve_order( $order_id, $player_id, $product_id );
			( new OrderRepository() )->addPayment( $order_id, $amount );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'tml_action_failed', $e->getMessage(), array( 'status' => 400 ) );
		}

		return self::row_response( $product_id, $player_id, $order_id );
	}

	public static function mark_paid( \WP_REST_Request $request ) {
		$product_id = absint( $request['product_id'] );
		$order_id   = absint( $request['order_id'] );
		$player_id  = absint( $request['player_id'] );

		try {
			$order_id = self::resolve_order( $order_id, $player_id, $product_id );
			( new OrderRepository() )->markPaid( $order_id );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'tml_action_failed', $e->getMessage(), array( 'status' => 400 ) );
		}

		return self::row_response( $product_id, $player_id, $order_id );
	}

	/** Resolve an existing order id, or create the Requested order on the fly for the player's family. */
	private static function resolve_order( int $order_id, int $player_id, int $product_id ): int {
		if ( $order_id ) {
			return $order_id;
		}
		if ( ! $player_id || ! $product_id ) {
			throw new \RuntimeException( __( 'No order to act on.', 'team-membership-ledger' ) );
		}
		$family_id = ( new PlayerRepository() )->familyIdFor( $player_id );
		if ( ! $family_id ) {
			throw new \RuntimeException( __( 'This player has no family to bill.', 'team-membership-ledger' ) );
		}
		return ( new OrderRepository() )->createRequestedOrder( $family_id, $product_id, $player_id );
	}

	/** @return \OrillaEagles\Ledger\Domain\LedgerRow[] */
	private static function rows_for( int $product_id ): array {
		return LedgerCalculator::forPlayers(
			( new PlayerRepository() )->allPlayers(),
			( new RosterRepository() )->allMembers(),
			( new OrderRepository() )->productRecords( $product_id )
		);
	}

	/** Recompute and return the single affected player's row after a write. */
	private static function row_response( int $product_id, int $player_id, int $order_id ): \WP_REST_Response {
		if ( ! $player_id && $order_id ) {
			$order     = wc_get_order( $order_id );
			$player_id = $order ? (int) $order->get_meta( OrderRepository::PLAYER_META ) : 0;
		}

		foreach ( self::rows_for( $product_id ) as $row ) {
			if ( $row->playerId() === $player_id ) {
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
