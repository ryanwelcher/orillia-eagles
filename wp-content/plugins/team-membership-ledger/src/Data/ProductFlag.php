<?php
namespace OrillaEagles\Ledger\Data;

defined( 'ABSPATH' ) || exit;

/**
 * The "Charge per player" product setting: one charge per linked player
 * instead of one per member.
 */
final class ProductFlag {

	public const META = '_tml_per_player';

	public static function register(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( self::class, 'renderField' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save' ) );
	}

	public static function renderField(): void {
		woocommerce_wp_checkbox(
			array(
				'id'          => self::META,
				'label'       => __( 'Charge per player', 'team-membership-ledger' ),
				'description' => __( 'Create one charge for each player linked to a member, instead of one per member.', 'team-membership-ledger' ),
			)
		);
	}

	/** WooCommerce has already checked the product-save nonce and capability here. */
	public static function save( \WC_Product $product ): void {
		$product->update_meta_data( self::META, isset( $_POST[ self::META ] ) ? 'yes' : 'no' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	public static function isPerPlayer( int $product_id ): bool {
		$product = wc_get_product( $product_id );
		return $product ? self::isPerPlayerProduct( $product ) : false;
	}

	public static function isPerPlayerProduct( \WC_Product $product ): bool {
		return 'yes' === $product->get_meta( self::META );
	}

	/**
	 * Whether a charge for this product can have a quantity above 1 (e.g. event
	 * tickets). Uses WooCommerce's "Sold individually" setting; per-player
	 * charges are always one per player.
	 */
	public static function allowsQuantity( \WC_Product $product ): bool {
		return ! self::isPerPlayerProduct( $product ) && ! $product->get_sold_individually();
	}
}
