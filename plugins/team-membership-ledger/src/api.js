import apiFetch from '@wordpress/api-fetch';

/**
 * Fetch the ledger payload for a product.
 *
 * @param {number} productId Selected product id (0 = none).
 * @return {Promise<{rows:Array, products:Array, currency:Object}>} Ledger payload.
 */
export function fetchLedger( productId ) {
	return apiFetch( {
		path: `/tml/v1/ledger?product_id=${ encodeURIComponent( productId ) }`,
	} );
}
