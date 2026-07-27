<?php
namespace OrillaEagles\Ledger\Admin;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public const CAP  = Capabilities::CAP;
	public const SLUG = 'tml-roster';

	public static function register(): void {
		add_menu_page(
			__( 'Membership', 'team-membership-ledger' ),
			__( 'Membership', 'team-membership-ledger' ),
			self::CAP,
			self::SLUG,
			array( RosterScreen::class, 'render' ),
			'dashicons-groups',
			56
		);
		add_submenu_page( self::SLUG, __( 'Members', 'team-membership-ledger' ), __( 'Members', 'team-membership-ledger' ), self::CAP, self::SLUG, array( RosterScreen::class, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Ledger', 'team-membership-ledger' ), __( 'Ledger', 'team-membership-ledger' ), self::CAP, 'tml-ledger', array( LedgerScreen::class, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Create Charges', 'team-membership-ledger' ), __( 'Create Charges', 'team-membership-ledger' ), self::CAP, 'tml-rollover', array( RolloverScreen::class, 'render' ) );
	}
}
