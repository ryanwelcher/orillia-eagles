<?php
namespace OrillaEagles\Ledger\Domain;

final class LedgerStatus {
	public const PAID        = 'paid';
	public const OWES        = 'owes';
	public const NOT_ENTERED = 'not_entered';
	public const UNASSIGNED  = 'unassigned';
}
