import { groupByMember, memberSummaries, withPlayers } from './groups';

const row = ( memberId, playerId, status, total, paid, date = null ) => ( {
	id: `${ memberId }-${ playerId }`,
	memberId,
	playerId,
	name: `Member ${ memberId }`,
	email: `m${ memberId }@x.com`,
	status,
	qty: 1,
	total,
	paid,
	balance: total - paid,
	date,
} );

describe( 'groupByMember', () => {
	it( 'sums every player into one member row', () => {
		const [ member ] = groupByMember( [
			row( 2, 41, 'paid', 200, 200, '2026-09-02' ),
			row( 2, 42, 'owes', 200, 50, '2026-09-01' ),
			row( 2, 43, 'not_entered', 0, 0 ),
		] );

		expect( member.id ).toBe( 'member-2' );
		expect( member.isMember ).toBe( true );
		expect( member.total ).toBe( 400 );
		expect( member.paid ).toBe( 250 );
		expect( member.balance ).toBe( 150 );
		expect( member.status ).toBe( 'owes' );
		expect( member.date ).toBe( '2026-09-01' );
		expect( member.players ).toHaveLength( 3 );
	} );

	it( 'keeps a shared status when every player agrees', () => {
		const [ member ] = groupByMember( [ row( 2, 41, 'paid', 200, 200 ), row( 2, 42, 'paid', 200, 200 ) ] );
		expect( member.status ).toBe( 'paid' );
	} );

	it( 'makes one row per member', () => {
		const members = groupByMember( [ row( 2, 41, 'owes', 1, 0 ), row( 5, 44, 'owes', 1, 0 ), row( 2, 42, 'owes', 1, 0 ) ] );
		expect( members.map( ( m ) => m.memberId ) ).toEqual( [ 2, 5 ] );
	} );
} );

describe( 'memberSummaries', () => {
	const rows = [
		row( 2, 41, 'paid', 200, 200 ),
		row( 2, 42, 'not_entered', 0, 0 ),
		row( 5, 44, 'not_entered', 0, 0 ),
	];

	it( 'keeps an uncharged player under a member who has other charges', () => {
		const [ member ] = memberSummaries( rows, { showUnentered: false } );
		expect( member.memberId ).toBe( 2 );
		expect( member.players.map( ( p ) => p.playerId ) ).toEqual( [ 41, 42 ] );
		// Not "paid": one player still has no charge.
		expect( member.status ).toBe( 'owes' );
	} );

	it( 'hides members with no charges at all', () => {
		expect( memberSummaries( rows, { showUnentered: false } ).map( ( m ) => m.memberId ) ).toEqual( [ 2 ] );
	} );

	it( 'shows members with no charges when asked', () => {
		expect( memberSummaries( rows, { showUnentered: true } ).map( ( m ) => m.memberId ) ).toEqual( [ 2, 5 ] );
	} );
} );

describe( 'withPlayers', () => {
	it( 'puts level-1 player rows right after their member', () => {
		const members = groupByMember( [ row( 2, 41, 'owes', 1, 0 ), row( 5, 44, 'owes', 1, 0 ) ] );
		const out = withPlayers( members );
		expect( out.map( ( r ) => r.id ) ).toEqual( [ 'member-2', '2-41', 'member-5', '5-44' ] );
		expect( out[ 1 ].level ).toBe( 1 );
		expect( out[ 0 ].level ).toBeUndefined();
	} );
} );
