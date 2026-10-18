<?php
namespace OrillaEagles\Ledger\Domain;

final class RolloverPlan {

	/**
	 * @param int[] $active_member_ids
	 * @param int[] $existing_customer_ids
	 * @return array{to_create:int[],to_skip:int[]}
	 */
	public static function build( array $active_member_ids, array $existing_customer_ids ): array {
		$existing = array_flip( array_map( 'intval', $existing_customer_ids ) );
		$seen     = array();
		$create   = array();
		$skip     = array();

		foreach ( $active_member_ids as $raw ) {
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
