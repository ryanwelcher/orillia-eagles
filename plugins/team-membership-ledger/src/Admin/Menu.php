<?php
namespace OrillaEagles\Ledger\Admin;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public const CAP  = 'manage_woocommerce';
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
		add_submenu_page( self::SLUG, __( 'Roster', 'team-membership-ledger' ), __( 'Roster', 'team-membership-ledger' ), self::CAP, self::SLUG, array( RosterScreen::class, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Ledger', 'team-membership-ledger' ), __( 'Ledger', 'team-membership-ledger' ), self::CAP, 'tml-ledger', array( LedgerScreen::class, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Season Rollover', 'team-membership-ledger' ), __( 'Season Rollover', 'team-membership-ledger' ), self::CAP, 'tml-rollover', array( RolloverScreen::class, 'render' ) );
	}
}
