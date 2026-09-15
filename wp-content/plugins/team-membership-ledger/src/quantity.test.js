import { parseQty } from './quantity';

describe( 'parseQty', () => {
	it( 'accepts a whole number that differs from the saved quantity', () => {
		expect( parseQty( '4', 1 ) ).toBe( 4 );
		expect( parseQty( ' 2 ', 0 ) ).toBe( 2 );
	} );

	it( 'rejects zero, decimals, negatives and blanks', () => {
		expect( parseQty( '0', 1 ) ).toBeNull();
		expect( parseQty( '1.5', 1 ) ).toBeNull();
		expect( parseQty( '-2', 1 ) ).toBeNull();
		expect( parseQty( '', 1 ) ).toBeNull();
	} );

	it( 'returns null when the quantity is unchanged', () => {
		expect( parseQty( '3', 3 ) ).toBeNull();
	} );
} );
