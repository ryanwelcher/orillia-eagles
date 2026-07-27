import { formatMoney } from './format';

describe( 'formatMoney', () => {
	const cad = { symbol: '$', decimals: 2 };

	it( 'formats a whole number with symbol and decimals', () => {
		expect( formatMoney( 200, cad ) ).toBe( '$200.00' );
	} );

	it( 'formats a fractional amount', () => {
		expect( formatMoney( 80.5, cad ) ).toBe( '$80.50' );
	} );

	it( 'respects a zero-decimal currency', () => {
		expect( formatMoney( 1500, { symbol: '¥', decimals: 0 } ) ).toBe( '¥1500' );
	} );

	it( 'treats non-numeric input as zero', () => {
		expect( formatMoney( null, cad ) ).toBe( '$0.00' );
	} );
} );
