/**
 * Runs the real DataViews filter against the field definitions, because a field
 * whose value and operators disagree fails silently: the table is just empty.
 */
import { makeFields } from './fields';
import { groupByMember } from './groups';

jest.mock( './QuantityCell', () => () => null );
// Only the date operators use it, and it needs WordPress's date settings to load.
jest.mock( '@wordpress/date', () => ( { getDate: () => new Date() } ) );

// The package index, and its filterSortAndPaginate, load UI that jest can't
// parse. The operators are what decide a match, so run those: the same
// functions filterSortAndPaginate calls for each filter.
const operators = require( '../node_modules/@wordpress/dataviews/build/utils/operators.cjs' );
const byName = ( name ) => operators.getOperatorByName( name );

// What filterSortAndPaginate does with one filter, without the UI imports.
const filterRows = ( rows, fields, { field: id, operator, value } ) => {
	const field = fields.find( ( f ) => f.id === id );
	return rows.filter( ( item ) => byName( operator ).filter( item, field, value ) );
};

const player = ( memberId, playerId, status ) => ( {
	id: `${ memberId }:${ playerId }`,
	memberId,
	playerId,
	playerName: `Player ${ playerId }`,
	name: `Member ${ memberId }`,
	email: '',
	status,
	qty: 1,
	total: 100,
	paid: status === 'paid' ? 100 : 0,
	balance: status === 'paid' ? 0 : 100,
	date: null,
	orderId: playerId,
	orderCount: 1,
} );

const fields = makeFields( { symbol: '$', decimals: 2 } );
const names = ( rows ) => rows.map( ( row ) => row.memberId );

describe( 'status filter', () => {
	// Member 1 all paid, member 2 mixed, member 3 owes.
	const members = groupByMember( [
		player( 1, 11, 'paid' ),
		player( 2, 21, 'paid' ),
		player( 2, 22, 'owes' ),
		player( 3, 31, 'owes' ),
	] );

	it( 'finds members with any player in the chosen status', () => {
		const owes = filterRows( members, fields, { field: 'status', operator: 'isAny', value: [ 'owes' ] } );
		expect( names( owes ) ).toEqual( [ 2, 3 ] );
		const paid = filterRows( members, fields, { field: 'status', operator: 'isAny', value: [ 'paid' ] } );
		expect( names( paid ) ).toEqual( [ 1, 2 ] );
	} );

	it( 'would match nothing with the default `is` operator, which is why it is not offered', () => {
		expect( filterRows( members, fields, { field: 'status', operator: 'is', value: 'owes' } ) ).toEqual( [] );
	} );

	it( 'only offers operators that work on a list of statuses', () => {
		const status = fields.find( ( f ) => f.id === 'status' );
		expect( status.filterBy.operators ).toEqual( [ 'isAny', 'isNone' ] );
	} );

	it( 'still filters plain rows on per-member products', () => {
		const rows = [ player( 1, 0, 'paid' ), player( 2, 0, 'owes' ) ];
		const owes = filterRows( rows, fields, { field: 'status', operator: 'isAny', value: [ 'owes' ] } );
		expect( names( owes ) ).toEqual( [ 2 ] );
	} );
} );

describe( 'search', () => {
	it( 'puts each player\'s name in the searchable value of their member', () => {
		const [ , second ] = groupByMember( [ player( 1, 11, 'paid' ), player( 2, 21, 'owes' ) ] );
		const name = fields.find( ( f ) => f.id === 'name' );
		expect( name.enableGlobalSearch ).toBe( true );
		expect( name.getValue( { item: second } ) ).toBe( 'Member 2 Player 21' );
	} );
} );
