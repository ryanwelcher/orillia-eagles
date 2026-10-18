<?php
namespace OrillaEagles\Ledger\Domain;

defined( 'ABSPATH' ) || defined( 'PHPUNIT_COMPOSER_INSTALL' ) || exit;

final class LedgerSerializer {

	/**
	 * @param LedgerRow[] $rows
	 * @return array<int,array<string,mixed>>
	 */
	public static function rows( array $rows ): array {
		return array_map( array( self::class, 'row' ), $rows );
	}

	/** @return array<string,mixed> */
	public static function row( LedgerRow $row ): array {
		return array(
			'memberId'   => $row->memberId(),
			'name'       => $row->name(),
			'email'      => $row->email(),
			'status'     => $row->status(),
			'qty'        => $row->qty(),
			'total'      => $row->total(),
			'paid'       => $row->paid(),
			'balance'    => $row->balance(),
			'date'       => $row->date() ? substr( $row->date(), 0, 10 ) : null,
			'orderId'    => $row->singleOrderId(),
			'orderCount' => $row->orderCount(),
		);
	}
}
