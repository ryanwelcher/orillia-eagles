<?php
namespace OrillaEagles\Ledger\Domain;

final class RolloverPlan {

	/**
	 * Players to charge, mapped to the family to bill: every player whose
	 * family is active. Players of deactivated families aren't charged again.
	 *
	 * @param array $players           list of ['id','family_id'].
	 * @param int[] $active_family_ids
	 * @return array<int,int> player id => family id.
	 */
	public static function chargeable( array $players, array $active_family_ids ): array {
		$active = array_flip( array_map( 'intval', $active_family_ids ) );
		$out    = array();
		foreach ( $players as $p ) {
			$family_id = (int) $p['family_id'];
			if ( isset( $active[ $family_id ] ) ) {
				$out[ (int) $p['id'] ] = $family_id;
			}
		}
		return $out;
	}

	/**
	 * @param int[] $chargeable_ids Players to charge.
	 * @param int[] $existing_ids   Players who already have a charge for the product.
	 * @return array{to_create:int[],to_skip:int[]}
	 */
	public static function build( array $chargeable_ids, array $existing_ids ): array {
		$existing = array_flip( array_map( 'intval', $existing_ids ) );
		$seen     = array();
		$create   = array();
		$skip     = array();

		foreach ( $chargeable_ids as $raw ) {
			$id = (int) $raw;
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			if ( isset( $existing[ $id ] ) ) {
				$skip[] = $id;
			} else {
				$create[] = $id;
			}
		}

		return array( 'to_create' => $create, 'to_skip' => $skip );
	}
}
