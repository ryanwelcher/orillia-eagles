<?php
namespace OrillaEagles\Ledger\Domain;

final class LedgerCalculator {

	/**
	 * One row per player, billed to a family.
	 *
	 * A player is listed when their family is active, or when they already have
	 * a charge for this product. A charged player is shown under the family the
	 * order was billed to, even if their link has changed since. Uncharged
	 * players with no family (coaches aside) are listed as unassigned so gaps
	 * are easy to spot.
	 *
	 * @param array $players  list of ['id','name','family_id','is_coach'].
	 * @param array $families list of ['id','name','active'] (all families, active or not).
	 * @param array $records  list of ['customer_id','player_id','order_id','status','line_total','amount_paid','date'] for one product.
	 * @return LedgerRow[] sorted by family name, then player name; unassigned last.
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
			$player_id   = (int) $p['id'];
			$player_name = (string) $p['name'];
			$own         = $by_player[ $player_id ] ?? array();

			$family_id = $own ? (int) $own[0]['customer_id'] : (int) $p['family_id'];
			$family    = $family_by_id[ $family_id ] ?? null;
			if ( ! $own && ! $family ) {
				if ( empty( $p['is_coach'] ) ) {
					$rows[] = new LedgerRow( $player_id, $player_name, 0, '', LedgerStatus::UNASSIGNED, 0.0, 0.0, null );
				}
				continue;
			}
			if ( ! $own && empty( $family['active'] ) ) {
				continue;
			}

			$rows[] = self::rollUp( $player_id, $player_name, $family_id, $family ? (string) $family['name'] : '', $own );
		}

		usort(
			$rows,
			static function ( LedgerRow $a, LedgerRow $b ) {
				return ( '' === $a->familyName() ) <=> ( '' === $b->familyName() )
					?: strcasecmp( $a->familyName(), $b->familyName() )
					?: strcasecmp( $a->playerName(), $b->playerName() );
			}
		);

		return $rows;
	}

	/** Roll a player's order records up into a single ledger row. */
	private static function rollUp( int $player_id, string $player_name, int $family_id, string $family_name, array $own ): LedgerRow {
		if ( empty( $own ) ) {
			return new LedgerRow( $player_id, $player_name, $family_id, $family_name, LedgerStatus::NOT_ENTERED, 0.0, 0.0, null );
		}

		$total     = 0.0;
		$paid      = 0.0;
		$all_paid  = true;
		$date      = null;
		$order_ids = array();
		foreach ( $own as $r ) {
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

		$status = $all_paid ? LedgerStatus::PAID : LedgerStatus::OWES;
		return new LedgerRow( $player_id, $player_name, $family_id, $family_name, $status, round( $total, 2 ), round( $paid, 2 ), $date, $order_ids );
	}
}
