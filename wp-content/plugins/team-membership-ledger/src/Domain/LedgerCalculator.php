<?php
namespace OrillaEagles\Ledger\Domain;

final class LedgerCalculator {

	/**
	 * @param array $members list of ['id','name','email'].
	 * @param array $records list of ['customer_id','status','qty','line_total','amount_paid','date'] for one product.
	 * @return LedgerRow[]
	 */
	public static function forProduct( array $members, array $records ): array {
		$by_member = array();
		foreach ( $records as $r ) {
			$by_member[ (int) $r['customer_id'] ][] = $r;
		}

		$rows = array();
		foreach ( $members as $m ) {
			$id     = (int) $m['id'];
			$rows[] = self::rollUp( $id, (string) $m['name'], (string) $m['email'], $by_member[ $id ] ?? array() );
		}

		return $rows;
	}

	/**
	 * One row per player, billed to a family.
	 *
	 * A player is listed when their family is active, or when they already have
	 * a charge for this product. A charged player is shown under the family the
	 * order was billed to, even if their link has changed since.
	 *
	 * @param array $players  list of ['id','name','family_id'].
	 * @param array $families list of ['id','name','email','active'] (all families, active or not).
	 * @param array $records  list of ['customer_id','player_id','order_id','status','qty','line_total','amount_paid','date'] for one product.
	 * @return LedgerRow[] sorted by family name, then player name.
	 */
	public static function forPlayers( array $players, array $families, array $records ): array {
		$family_by_id = array();
		foreach ( $families as $f ) {
			$family_by_id[ (int) $f['id'] ] = $f;
		}

		$by_player = array();
		foreach ( $records as $r ) {
			$player_id = (int) ( $r['player_id'] ?? 0 );
			if ( $player_id ) {
				$by_player[ $player_id ][] = $r;
			}
		}

		$rows = array();
		foreach ( $players as $p ) {
			$player_id = (int) $p['id'];
			$own       = $by_player[ $player_id ] ?? array();

			$family_id = $own ? (int) $own[0]['customer_id'] : (int) $p['family_id'];
			$family    = $family_by_id[ $family_id ] ?? null;
			if ( ! $own && ( ! $family || empty( $family['active'] ) ) ) {
				continue;
			}

			$rows[] = self::rollUp(
				$family_id,
				$family ? (string) $family['name'] : '',
				$family ? (string) $family['email'] : '',
				$own,
				$player_id,
				(string) $p['name']
			);
		}

		usort(
			$rows,
			static function ( LedgerRow $a, LedgerRow $b ) {
				return strcasecmp( $a->name(), $b->name() ) ?: strcasecmp( $a->playerName(), $b->playerName() );
			}
		);

		return $rows;
	}

	/** Roll a set of order records up into a single ledger row. */
	private static function rollUp( int $id, string $name, string $email, array $own, int $player_id = 0, string $player_name = '' ): LedgerRow {
		if ( empty( $own ) ) {
			return new LedgerRow( $id, $name, $email, MemberStatus::NOT_ENTERED, 0, 0.0, 0.0, null, array(), $player_id, $player_name );
		}

		$qty       = 0;
		$total     = 0.0;
		$paid      = 0.0;
		$all_paid  = true;
		$date      = null;
		$order_ids = array();
		foreach ( $own as $r ) {
			$qty   += (int) $r['qty'];
			$total += (float) $r['line_total'];
			$paid  += (float) $r['amount_paid'];
			if ( 'completed' !== $r['status'] ) {
				$all_paid = false;
			}
			if ( ! empty( $r['date'] ) && ( null === $date || $r['date'] < $date ) ) {
				$date = (string) $r['date'];
			}
			if ( ! empty( $r['order_id'] ) ) {
				$order_ids[] = (int) $r['order_id'];
			}
		}

		$status = $all_paid ? MemberStatus::PAID : MemberStatus::OWES;
		return new LedgerRow( $id, $name, $email, $status, $qty, round( $total, 2 ), round( $paid, 2 ), $date, $order_ids, $player_id, $player_name );
	}
}
