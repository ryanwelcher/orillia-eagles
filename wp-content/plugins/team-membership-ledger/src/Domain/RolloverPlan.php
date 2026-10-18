<?php
namespace OrillaEagles\Ledger\Domain;

final class RolloverPlan {

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
