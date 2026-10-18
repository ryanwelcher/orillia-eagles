import { familyTotals, makeFields } from './fields';

describe( 'family grouping', () => {
	const cad = { symbol: '$', decimals: 2 };
	const rows = [
		{ familyId: 10, familyName: 'Ed Smith', playerName: 'Liam', balance: 350 },
		{ familyId: 10, familyName: 'Ed Smith', playerName: 'Emma', balance: 0 },
		{ familyId: 10, familyName: 'Ed Smith', playerName: 'Jake', balance: 450 },
		{ familyId: 20, familyName: 'Ann Jones', playerName: 'Zoe', balance: 0 },
		{ familyId: 0, familyName: '', playerName: 'Aaron', balance: 0 },
	];
	const group = makeFields( cad, familyTotals( rows ) ).find( ( f ) => f.id === 'group' );

	it( 'totals players and balance per family', () => {
		const totals = familyTotals( rows );
		expect( totals.get( 10 ) ).toEqual( { players: 3, balance: 800 } );
		expect( totals.get( 0 ) ).toEqual( { players: 1, balance: 0 } );
	} );

	it( 'labels each group with its count and amount owing', () => {
		expect( group.getValue( { item: rows[ 0 ] } ) ).toBe( 'Ed Smith · 3 players · $800.00 owing' );
		expect( group.getValue( { item: rows[ 3 ] } ) ).toBe( 'Ann Jones · 1 player · nothing owing' );
		expect( group.getValue( { item: rows[ 4 ] } ) ).toMatch( /^Unassigned · 1 player/ );
	} );

	it( 'sorts families alphabetically with Unassigned last in either direction', () => {
		// DataViews sorts on the getValue() labels.
		const label = ( item ) => group.getValue( { item } );
		const order = ( direction ) =>
			[ rows[ 4 ], rows[ 0 ], rows[ 3 ] ]
				.sort( ( a, b ) => group.sort( label( a ), label( b ), direction ) )
				.map( ( r ) => r.familyId );
		expect( order( 'asc' ) ).toEqual( [ 20, 10, 0 ] );
		expect( order( 'desc' ) ).toEqual( [ 10, 20, 0 ] );
	} );
} );
