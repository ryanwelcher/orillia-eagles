<?php
namespace OrillaEagles\Ledger;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;
	private bool $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;
		\OrillaEagles\Ledger\Status\OrderStatus::register();
		add_action( 'admin_menu', array( \OrillaEagles\Ledger\Admin\Menu::class, 'register' ) );
		add_action( 'admin_init', array( \OrillaEagles\Ledger\Admin\RosterScreen::class, 'handlePost' ) );
		add_action( 'admin_init', array( \OrillaEagles\Ledger\Admin\RolloverScreen::class, 'handlePost' ) );
		add_action( 'admin_init', array( \OrillaEagles\Ledger\Admin\LedgerScreen::class, 'handlePost' ) );
	}
}
