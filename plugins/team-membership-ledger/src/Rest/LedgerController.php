<?php
namespace OrillaEagles\Ledger\Rest;

use OrillaEagles\Ledger\Admin\Menu;
use OrillaEagles\Ledger\Data\OrderRepository;
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

	private static function currency(): array {
		return array(
			'symbol'   => html_entity_decode( get_woocommerce_currency_symbol() ),
			'decimals' => wc_get_price_decimals(),
		);
	}
}
