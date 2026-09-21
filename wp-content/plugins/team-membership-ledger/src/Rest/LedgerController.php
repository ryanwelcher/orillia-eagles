<?php
namespace OrillaEagles\Ledger\Rest;

use OrillaEagles\Ledger\Admin\Menu;
use OrillaEagles\Ledger\Data\ActionLock;
use OrillaEagles\Ledger\Data\BillableRepository;
use OrillaEagles\Ledger\Data\OrderRepository;
use OrillaEagles\Ledger\Data\PlayerRepository;
use OrillaEagles\Ledger\Data\ProductFlag;
use OrillaEagles\Ledger\Data\RosterRepository;
use OrillaEagles\Ledger\Domain\LedgerCalculator;
use OrillaEagles\Ledger\Domain\LedgerRow;
use OrillaEagles\Ledger\Domain\LedgerSerializer;
use OrillaEagles\Ledger\Domain\PaymentAllocation;

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

		// Member-level payments for "Charge per player" products: act on all of
		// the member's player charges at once.
		register_rest_route(
			self::NAMESPACE,
			'/ledger/member/add-payment',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'member_add_payment' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'product_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'member_id'  => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'amount'     => array( 'type' => 'number', 'required' => true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/ledger/member/mark-paid',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'member_mark_paid' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'product_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'member_id'  => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
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
			$order_id = self::with_member_lock(
				$product_id,
				$member_id,
				static function () use ( $order_id, $member_id, $player_id, $product_id, $amount ) {
					$id = self::resolve_order( $order_id, $member_id, $player_id, $product_id );
					( new OrderRepository() )->addPayment( $id, $amount );
					return $id;
				}
			);
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
			$order_id = self::with_member_lock(
				$product_id,
				$member_id,
				static function () use ( $order_id, $member_id, $player_id, $product_id ) {
					$id = self::resolve_order( $order_id, $member_id, $player_id, $product_id );
					( new OrderRepository() )->markPaid( $id );
					return $id;
				}
			);
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
			$order_id = self::with_member_lock(
				$product_id,
				$member_id,
				static function () use ( $order_id, $member_id, $player_id, $product_id, $qty ) {
					if ( ! $order_id ) {
						return self::resolve_order( 0, $member_id, $player_id, $product_id, $qty );
					}
					$id = self::resolve_order( $order_id, $member_id, $player_id, $product_id );
					( new OrderRepository() )->setQuantity( $id, $product_id, $qty );
					return $id;
				}
			);
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'tml_action_failed', $e->getMessage(), array( 'status' => 400 ) );
		}

		return self::row_response( $product_id, $order_id );
	}

	public static function member_add_payment( \WP_REST_Request $request ) {
		$product_id = absint( $request['product_id'] );
		$member_id  = absint( $request['member_id'] );
		$amount     = round( (float) $request['amount'], 2 );

		if ( $amount <= 0 ) {
			return new \WP_Error( 'tml_invalid_amount', __( 'Enter a payment amount greater than zero.', 'team-membership-ledger' ), array( 'status' => 400 ) );
		}

		try {
			return self::with_member_lock(
				$product_id,
				$member_id,
				static function () use ( $product_id, $member_id, $amount ) {
					$rows = self::member_rows_checked( $product_id, $member_id );

					// Create any missing charge first, then check the amount against the
					// real balances. An estimate from the product price would use a
					// different figure than the orders do once tax or a fee applies.
					$orders   = new OrderRepository();
					$balances = array();
					foreach ( self::ensure_member_charges( $product_id, $member_id, $rows ) as $row ) {
						$balances[ $row->singleOrderId() ] = $row->balance();
					}
					try {
						$plan = PaymentAllocation::fill( $balances, $amount );
					} catch ( \RuntimeException $e ) {
						throw new \RuntimeException( self::overpayment_message( PaymentAllocation::owed( $balances ) ) );
					}
					// Each order is a separate save with no rollback between them, so
					// check every one before writing any.
					foreach ( $plan as $order_id => $increment ) {
						$orders->assertCanPay( $order_id, $increment );
					}
					$applied = 0;
					foreach ( $plan as $order_id => $increment ) {
						try {
							$orders->addPayment( $order_id, $increment );
						} catch ( \RuntimeException $e ) {
							// Never report a plain failure once money is recorded: a
							// retry would pay the earlier charges a second time.
							throw new \RuntimeException(
								$applied
									? __( 'Part of that payment was recorded before a charge could not be updated. Reload the Ledger to see what was applied.', 'team-membership-ledger' )
									: $e->getMessage()
							);
						}
						$applied++;
					}

					return self::member_response( $product_id, $member_id );
				}
			);
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'tml_action_failed', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public static function member_mark_paid( \WP_REST_Request $request ) {
		$product_id = absint( $request['product_id'] );
		$member_id  = absint( $request['member_id'] );

		try {
			return self::with_member_lock(
				$product_id,
				$member_id,
				static function () use ( $product_id, $member_id ) {
					$rows   = self::member_rows_checked( $product_id, $member_id );
					$orders = new OrderRepository();
					foreach ( self::ensure_member_charges( $product_id, $member_id, $rows ) as $row ) {
						if ( 'paid' !== $row->status() ) {
							$orders->markPaid( $row->singleOrderId() );
						}
					}

					return self::member_response( $product_id, $member_id );
				}
			);
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'tml_action_failed', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	/**
	 * The member's player rows for a per-player product, refusing anything a
	 * member-level action can't safely act on.
	 *
	 * @return LedgerRow[]
	 */
	private static function member_rows_checked( int $product_id, int $member_id ): array {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! ProductFlag::isPerPlayerProduct( $product ) ) {
			throw new \RuntimeException( __( 'Member payments are only for "Charge per player" products.', 'team-membership-ledger' ) );
		}
		$rows = self::member_rows( $product_id, $member_id );
		if ( ! $rows ) {
			throw new \RuntimeException( __( 'This member has no linked players.', 'team-membership-ledger' ) );
		}
		// An older member-level charge is not shown here, so creating player
		// charges on top of it would bill the member twice.
		if ( ( new OrderRepository() )->hasChargeInOtherMode( $product_id, $member_id, true ) ) {
			throw new \RuntimeException( self::other_mode_message() );
		}
		foreach ( $rows as $row ) {
			if ( $row->orderCount() > 1 ) {
				/* translators: %s: player name */
				throw new \RuntimeException( sprintf( __( '%s has more than one order for this product; manage it in WooCommerce.', 'team-membership-ledger' ), $row->playerName() ) );
			}
			// Payments act on the whole order, so other items would be paid too.
			$order = $row->singleOrderId() ? wc_get_order( $row->singleOrderId() ) : null;
			if ( $order && 1 !== count( $order->get_items() ) ) {
				/* translators: %s: player name */
				throw new \RuntimeException( sprintf( __( "%s's order has other items; manage it in WooCommerce.", 'team-membership-ledger' ), $row->playerName() ) );
			}
			if ( $order ) {
				( new OrderRepository() )->assertNotVoid( $order );
			}
		}
		return $rows;
	}

	/**
	 * Create the Requested order for any of the member's players without one.
	 *
	 * @param LedgerRow[] $rows
	 * @return LedgerRow[] rows that all have exactly one order.
	 */
	private static function ensure_member_charges( int $product_id, int $member_id, array $rows ): array {
		$created = false;
		foreach ( $rows as $row ) {
			if ( 0 === $row->orderCount() ) {
				self::assert_not_charged_elsewhere( $product_id, $member_id, $row->playerId(), $row->playerName() );
				( new OrderRepository() )->createRequestedOrder( $member_id, $product_id, $row->playerId() );
				$created = true;
			}
		}
		if ( ! $created ) {
			return $rows;
		}
		// member_rows_checked() vetted the rows before anything was created. Vet
		// the re-read too, so a charge that appeared meanwhile can't reach the
		// payment loop as a null order id and apply only part of a payment.
		$rows = self::member_rows( $product_id, $member_id );
		foreach ( $rows as $row ) {
			if ( 1 !== $row->orderCount() ) {
				/* translators: %s: player name */
				throw new \RuntimeException( sprintf( __( "%s's charges changed while this was saving; reload the Ledger and try again.", 'team-membership-ledger' ), $row->playerName() ) );
			}
		}
		return $rows;
	}

	/** @return LedgerRow[] */
	private static function member_rows( int $product_id, int $member_id ): array {
		// Runs inside the member lock, so read this member's orders only.
		return array_values(
			array_filter(
				self::ledger_rows( $product_id, new OrderRepository(), $member_id ),
				static fn( LedgerRow $row ) => $row->memberId() === $member_id
			)
		);
	}

	private static function member_response( int $product_id, int $member_id ): \WP_REST_Response {
		return new \WP_REST_Response( LedgerSerializer::rows( self::member_rows( $product_id, $member_id ) ), 200 );
	}

	/**
	 * Serialize the writes for one member's charges on a product, so two
	 * requests can't each create a charge or each spend the same balance.
	 *
	 * @param callable $write
	 * @return mixed Whatever $write returns.
	 */
	private static function with_member_lock( int $product_id, int $member_id, callable $write ) {
		return ActionLock::run( ActionLock::memberKey( $product_id, $member_id ), $write );
	}

	private static function overpayment_message( float $owed ): string {
		/* translators: %s: amount owed */
		return sprintf( __( 'That is more than the %s owed.', 'team-membership-ledger' ), html_entity_decode( wp_strip_all_tags( wc_price( $owed ) ) ) );
	}

	/** Resolve an existing order id, or create the Requested order on the fly. */
	private static function resolve_order( int $order_id, int $member_id, int $player_id, int $product_id, int $qty = 1 ): int {
		// Every charge belongs to a member. Without one there is nothing to check
		// an order against, and a guest order (customer 0) would match.
		if ( ! $member_id || ! $product_id ) {
			throw new \RuntimeException( __( 'No order to act on.', 'team-membership-ledger' ) );
		}
		// The ledger lists customers, so a charge against any other user would be
		// invisible in it. Membership, not active membership: an inactive member's
		// outstanding charges still have to be payable.
		if ( ! ( new RosterRepository() )->isMember( $member_id ) ) {
			throw new \RuntimeException( __( 'That member is not in the ledger.', 'team-membership-ledger' ) );
		}
		if ( $order_id ) {
			// Only act on the order behind this ledger row, not any order id sent in.
			$order = wc_get_order( $order_id );
			if (
				! $order instanceof \WC_Order
				|| $order instanceof \WC_Order_Refund
				|| (int) $order->get_customer_id() !== $member_id
				|| (int) $order->get_meta( OrderRepository::PLAYER_META ) !== $player_id
				|| ! array_filter( $order->get_items(), static fn( $item ) => (int) $item->get_product_id() === $product_id )
			) {
				throw new \RuntimeException( __( 'That order does not belong to this ledger row.', 'team-membership-ledger' ) );
			}
			// A payment and Mark paid act on the whole order, so any other item on
			// it would be paid off too while the row shows only this line. Quantity
			// changes and the member-level path refuse these already.
			if ( 1 !== count( $order->get_items() ) ) {
				throw new \RuntimeException( __( 'That order has other items; manage it in WooCommerce.', 'team-membership-ledger' ) );
			}
			// A cancelled or refunded order still lists as a row; paying it or
			// changing its quantity would bring it back to life.
			( new OrderRepository() )->assertNotVoid( $order );
			return $order_id;
		}
		// The row said "no charge yet", but that can be stale: another tab, Create
		// Charges or a retried request may have made one since. The lock only
		// queues requests, so look again before creating a second charge.
		$orders     = new OrderRepository();
		$per_player = ProductFlag::isPerPlayer( $product_id );
		if ( $orders->hasCharge( $product_id, $member_id, $player_id ) ) {
			throw new \RuntimeException( __( 'This charge already exists; reload the Ledger and try again.', 'team-membership-ledger' ) );
		}
		if ( $orders->hasChargeInOtherMode( $product_id, $member_id, $per_player ) ) {
			throw new \RuntimeException( self::other_mode_message() );
		}
		// A new charge has to match how the product is billed, or it lands in
		// neither ledger: a player charge on a per-member product is never shown,
		// and a member-level charge on a per-player product is skipped.
		if ( $per_player ) {
			if ( ! $player_id ) {
				throw new \RuntimeException( __( 'This product is charged per player, so the charge needs a player.', 'team-membership-ledger' ) );
			}
			if ( ! PlayerRepository::isBillable( $player_id ) ) {
				throw new \RuntimeException( __( 'Only players can be charged for this product.', 'team-membership-ledger' ) );
			}
		} elseif ( $player_id ) {
			throw new \RuntimeException( __( 'This product is not charged per player.', 'team-membership-ledger' ) );
		}
		// Only charge a player to the member they are linked to.
		if ( $player_id && (int) get_post_meta( $player_id, PlayerRepository::MEMBER_META, true ) !== $member_id ) {
			throw new \RuntimeException( __( 'That player is not linked to this member.', 'team-membership-ledger' ) );
		}
		self::assert_not_charged_elsewhere( $product_id, $member_id, $player_id, (string) get_post_field( 'post_title', $player_id, 'raw' ) );
		return $orders->createRequestedOrder( $member_id, $product_id, $player_id, $qty );
	}

	/** A player moved to this member after being charged must not be charged again. */
	private static function assert_not_charged_elsewhere( int $product_id, int $member_id, int $player_id, string $player_name ): void {
		if ( ( new OrderRepository() )->playerChargedUnderAnother( $product_id, $player_id, $member_id ) ) {
			/* translators: %s: player name */
			throw new \RuntimeException( sprintf( __( '%s was already charged for this product under another member; manage that order in WooCommerce.', 'team-membership-ledger' ), $player_name ) );
		}
	}

	private static function other_mode_message(): string {
		return __( 'This member already has a charge for this product from before its "Charge per player" setting changed; manage it in WooCommerce.', 'team-membership-ledger' );
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

		foreach ( self::ledger_rows( $product_id, new OrderRepository(), $member_id ) as $row ) {
			if ( $row->memberId() === $member_id && $row->playerId() === $player_id ) {
				return new \WP_REST_Response( LedgerSerializer::row( $row ), 200 );
			}
		}
		return new \WP_REST_Response( null, 200 );
	}

	/** @return LedgerRow[] */
	private static function ledger_rows( int $product_id, OrderRepository $orders, int $member_id = 0 ): array {
		return LedgerCalculator::forProduct(
			( new BillableRepository() )->forProduct( $product_id ),
			$orders->productRecords( $product_id, $member_id )
		);
	}

	private static function currency(): array {
		return array(
			'symbol'   => html_entity_decode( get_woocommerce_currency_symbol() ),
			'decimals' => wc_get_price_decimals(),
		);
	}
}
