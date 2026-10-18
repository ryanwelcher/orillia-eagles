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
			'id'         => $row->playerId(),
			'playerId'   => $row->playerId(),
			'playerName' => $row->playerName(),
			'familyId'   => $row->familyId(),
			'familyName' => $row->familyName(),
			'status'     => $row->status(),
			'total'      => $row->total(),
			'paid'       => $row->paid(),
			'balance'    => $row->balance(),
			'date'       => $row->date() ? substr( $row->date(), 0, 10 ) : null,
			'orderId'    => $row->singleOrderId(),
			'orderCount' => $row->orderCount(),
		);
	}
}
