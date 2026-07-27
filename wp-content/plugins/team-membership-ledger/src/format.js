/**
 * Format a numeric amount with a currency symbol and fixed decimals.
 *
 * @param {number} amount   Raw amount.
 * @param {{symbol:string, decimals:number}} currency Currency config.
 * @return {string} Formatted money string.
 */
export function formatMoney( amount, currency ) {
	const value = Number.isFinite( Number( amount ) ) ? Number( amount ) : 0;
	const decimals = Number.isFinite( Number( currency?.decimals ) ) ? Number( currency.decimals ) : 2;
	return `${ currency?.symbol ?? '' }${ value.toFixed( decimals ) }`;
}
