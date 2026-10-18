<?php
namespace OrillaEagles\Ledger\Domain;

/** One player's charges for a product, and the family billed for them. */
final class LedgerRow {

	private int $player_id;
	private string $player_name;
	private int $family_id;
	private string $family_name;
	private string $status;
	private float $total;
	private float $paid;
	private ?string $date;
	private array $order_ids;

	public function __construct(
		int $player_id,
		string $player_name,
		int $family_id,
		string $family_name,
		string $status,
		float $total,
		float $paid,
		?string $date,
		array $order_ids = array()
	) {
		$this->player_id   = $player_id;
		$this->player_name = $player_name;
		$this->family_id   = $family_id;
		$this->family_name = $family_name;
		$this->status      = $status;
		$this->total       = $total;
		$this->paid        = $paid;
		$this->date        = $date;
		$this->order_ids   = array_values( array_unique( array_map( 'intval', $order_ids ) ) );
	}

	public function playerId(): int { return $this->player_id; }
	public function playerName(): string { return $this->player_name; }
	/** 0 when the player has no family. */
	public function familyId(): int { return $this->family_id; }
	public function familyName(): string { return $this->family_name; }
	public function status(): string { return $this->status; }
	public function total(): float { return $this->total; }
	public function paid(): float { return $this->paid; }
	public function balance(): float { return round( $this->total - $this->paid, 2 ); }
	public function date(): ?string { return $this->date; }

	/** @return int[] */
	public function orderIds(): array { return $this->order_ids; }
	public function orderCount(): int { return count( $this->order_ids ); }
	public function singleOrderId(): ?int { return 1 === count( $this->order_ids ) ? $this->order_ids[0] : null; }
}
