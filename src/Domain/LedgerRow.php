<?php
namespace OrillaEagles\Ledger\Domain;

final class LedgerRow {

	private int $member_id;
	private string $name;
	private string $email;
	private string $status;
	private int $qty;
	private float $total;
	private float $paid;
	private ?string $date;

	public function __construct(
		int $member_id,
		string $name,
		string $email,
		string $status,
		int $qty,
		float $total,
		float $paid,
		?string $date
	) {
		$this->member_id = $member_id;
		$this->name      = $name;
		$this->email     = $email;
		$this->status    = $status;
		$this->qty       = $qty;
		$this->total     = $total;
		$this->paid      = $paid;
		$this->date      = $date;
	}

	public function memberId(): int { return $this->member_id; }
	public function name(): string { return $this->name; }
	public function email(): string { return $this->email; }
	public function status(): string { return $this->status; }
	public function qty(): int { return $this->qty; }
	public function total(): float { return $this->total; }
	public function paid(): float { return $this->paid; }
	public function balance(): float { return round( $this->total - $this->paid, 2 ); }
	public function date(): ?string { return $this->date; }
}
