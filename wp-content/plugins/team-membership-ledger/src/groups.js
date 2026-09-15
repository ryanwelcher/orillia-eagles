const round = ( value ) => Math.round( value * 100 ) / 100;

/**
 * Roll per-player rows up into one summary row per member.
 *
 * The summary carries the member's full amounts and keeps its player rows in
 * `players`. Status is shared when every player agrees, otherwise "owes".
 *
 * @param {Array} rows Serialized ledger rows (one per member + player).
 * @return {Array} Member summary rows, in first-seen order.
 */
export function groupByMember( rows ) {
	const groups = new Map();
	for ( const row of rows ) {
		if ( ! groups.has( row.memberId ) ) {
			groups.set( row.memberId, [] );
		}
		groups.get( row.memberId ).push( row );
	}

	return [ ...groups.values() ].map( ( players ) => {
		const sum = ( key ) => round( players.reduce( ( total, p ) => total + p[ key ], 0 ) );
		const statuses = new Set( players.map( ( p ) => p.status ) );
		const dates = players.map( ( p ) => p.date ).filter( Boolean ).sort();
		const { memberId, name, email } = players[ 0 ];

		return {
			id: `member-${ memberId }`,
			isMember: true,
			memberId,
			name,
			email,
			status: statuses.size === 1 ? players[ 0 ].status : 'owes',
			qty: sum( 'qty' ),
			total: sum( 'total' ),
			paid: sum( 'paid' ),
			balance: sum( 'balance' ),
			date: dates[ 0 ] ?? null,
			orderId: null,
			orderCount: 0,
			players,
		};
	} );
}

/**
 * Insert each member's player rows (at level 1, members are level 0) directly
 * after the member row. DataViews prefixes one dash per level.
 *
 * @param {Array} memberRows Rows from groupByMember(), already sorted/paginated.
 * @return {Array} Member and player rows, interleaved.
 */
export function withPlayers( memberRows ) {
	return memberRows.flatMap( ( member ) => [
		member,
		...member.players.map( ( player ) => ( { ...player, level: 1 } ) ),
	] );
}
