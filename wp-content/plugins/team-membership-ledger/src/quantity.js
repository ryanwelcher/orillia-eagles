/**
 * Parse a typed quantity.
 *
 * @param {string} raw     Input text.
 * @param {number} current The row's saved quantity (0 when no charge exists).
 * @return {?number} A whole number ≥ 1 that differs from current, else null.
 */
export function parseQty( raw, current ) {
	const text = String( raw ).trim();
	if ( ! /^\d+$/.test( text ) ) {
		return null;
	}
	const qty = Number( text );
	return qty >= 1 && qty !== current ? qty : null;
}
