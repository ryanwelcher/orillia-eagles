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
		$wanted = isset( $_POST[ self::META ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		// Charges are matched to members or to players by this setting. Changing it
		// once charges exist would drop every one of them, and what was paid toward
		// them, out of the Ledger, so refuse and say why.
		if ( $wanted !== self::isPerPlayerProduct( $product ) && $product->get_id() && ( new OrderRepository() )->hasLiveCharges( (int) $product->get_id() ) ) {
			// Only there on the product edit screen, which is where this runs.
			class_exists( '\WC_Admin_Meta_Boxes' ) && \WC_Admin_Meta_Boxes::add_error( __( '"Charge per player" was not changed: this product already has charges in the Membership Ledger, and changing it would hide them. Create a new product for the new way of charging.', 'team-membership-ledger' ) );
			return;
		}
		$product->update_meta_data( self::META, $wanted ? 'yes' : 'no' );
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
	 * tickets). Uses WooCommerce's "Sold individually" setting, through the
	 * filtered is_sold_individually() so extensions are respected; per-player
	 * charges are always one per player.
	 */
	public static function allowsQuantity( \WC_Product $product ): bool {
		return ! self::isPerPlayerProduct( $product ) && ! $product->is_sold_individually();
	}
}
