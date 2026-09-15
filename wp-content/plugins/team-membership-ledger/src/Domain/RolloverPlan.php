<?php
namespace OrillaEagles\Ledger\Domain;

final class RolloverPlan {

	/**
	 * @param string[] $billable_keys "member:player" keys that should have a charge (see Billables::key()).
	 * @param string[] $existing_keys keys that already have one.
	 * @return array{to_create:string[],to_skip:string[]}
	 */
	public static function build( array $billable_keys, array $existing_keys ): array {
		$existing = array_flip( array_map( 'strval', $existing_keys ) );
		$seen     = array();
		$create   = array();
		$skip     = array();

		foreach ( $billable_keys as $raw ) {
			$key = (string) $raw;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			if ( isset( $existing[ $key ] ) ) {
				$skip[] = $key;
			} else {
				$create[] = $key;
			}
		}

		return array( 'to_create' => $create, 'to_skip' => $skip );
	}
}
