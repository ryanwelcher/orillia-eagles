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

/**
 * Record an additive payment; returns the updated row.
 *
 * @param {{productId:number, orderId:?number, memberId:?number, playerId:?number, amount:number}} args Payload.
 * @return {Promise<Object|null>} Updated serialized row, or null.
 */
export function addPayment( { productId, orderId, memberId, playerId, amount } ) {
	return apiFetch( {
		path: '/tml/v1/ledger/add-payment',
		method: 'POST',
		data: {
			product_id: productId,
			order_id: orderId || 0,
			member_id: memberId || 0,
			player_id: playerId || 0,
			amount,
		},
	} );
}

/**
 * Set a charge's quantity (creating the charge if needed); returns the updated row.
 *
 * @param {{productId:number, orderId:?number, memberId:?number, playerId:?number, qty:number}} args Payload.
 * @return {Promise<Object|null>} Updated serialized row, or null.
 */
export function setQuantity( { productId, orderId, memberId, playerId, qty } ) {
	return apiFetch( {
		path: '/tml/v1/ledger/set-quantity',
		method: 'POST',
		data: {
			product_id: productId,
			order_id: orderId || 0,
			member_id: memberId || 0,
			player_id: playerId || 0,
			qty,
		},
	} );
}

/**
 * Mark an order fully paid; returns the updated row.
 *
 * @param {{productId:number, orderId:?number, memberId:?number, playerId:?number}} args Payload.
 * @return {Promise<Object|null>} Updated serialized row, or null.
 */
export function markPaid( { productId, orderId, memberId, playerId } ) {
	return apiFetch( {
		path: '/tml/v1/ledger/mark-paid',
		method: 'POST',
		data: {
			product_id: productId,
			order_id: orderId || 0,
			member_id: memberId || 0,
			player_id: playerId || 0,
		},
	} );
}
