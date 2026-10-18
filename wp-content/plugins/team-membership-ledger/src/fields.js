import { __, _n, sprintf } from '@wordpress/i18n';
import { formatMoney } from './format';

const STATUS_LABELS = {
	paid: __( 'Paid', 'team-membership-ledger' ),
	owes: __( 'Owes', 'team-membership-ledger' ),
	not_entered: __( 'Not entered', 'team-membership-ledger' ),
	unassigned: __( 'No family', 'team-membership-ledger' ),
};

const UNASSIGNED = __( 'Unassigned', 'team-membership-ledger' );
const UNASSIGNED_PREFIX = `${ UNASSIGNED } · `;

/**
 * Roll rows up by family for the group headers.
 *
 * @param {Array} rows All ledger rows for the product (not just the current page).
 * @return {Map<number, {players:number, balance:number}>} Totals keyed by family id (0 = unassigned).
 */
export function familyTotals( rows ) {
	const totals = new Map();
	rows.forEach( ( row ) => {
		const current = totals.get( row.familyId ) ?? { players: 0, balance: 0 };
		totals.set( row.familyId, {
			players: current.players + 1,
			balance: Math.round( ( current.balance + row.balance ) * 100 ) / 100,
		} );
	} );
	return totals;
}

/**
 * Build the DataViews field definitions.
 *
 * @param {{symbol:string, decimals:number}} currency Currency config.
 * @param {Map}                               totals   Output of familyTotals().
 * @return {Array} Field definitions.
 */
export function makeFields( currency, totals = new Map() ) {
	// Unassigned players haven't been charged, so show a dash rather than $0.00.
	const money = ( getValue ) => ( { item } ) =>
		item.status === 'unassigned' ? '—' : formatMoney( getValue( { item } ), currency );

	// Group header text, e.g. "Ed Smith · 3 players · $600.00 owing".
	const groupLabel = ( item ) => {
		const { players = 0, balance = 0 } = totals.get( item.familyId ) ?? {};
		const count = sprintf(
			/* translators: %d: number of players. */
			_n( '%d player', '%d players', players, 'team-membership-ledger' ),
			players
		);
		if ( ! item.familyId ) {
			return `${ UNASSIGNED_PREFIX }${ count } · ${ __( 'link them to a family to charge', 'team-membership-ledger' ) }`;
		}
		const owing = balance > 0
			? sprintf(
				/* translators: %s: amount owing. */
				__( '%s owing', 'team-membership-ledger' ),
				formatMoney( balance, currency )
			)
			: __( 'nothing owing', 'team-membership-ledger' );
		return `${ item.familyName } · ${ count } · ${ owing }`;
	};

	return [
		{
			// Only used to group the table by family; not shown as a column.
			id: 'group',
			label: __( 'Family summary', 'team-membership-ledger' ),
			getValue: ( { item } ) => groupLabel( item ),
			// DataViews passes the getValue() labels here, not the items.
			// Alphabetical by family, with Unassigned always last.
			sort: ( a, b, direction ) => {
				const aUnassigned = a.startsWith( UNASSIGNED_PREFIX );
				if ( aUnassigned !== b.startsWith( UNASSIGNED_PREFIX ) ) {
					return aUnassigned ? 1 : -1;
				}
				const compare = a.localeCompare( b );
				return direction === 'asc' ? compare : -compare;
			},
		},
		{
			id: 'player',
			label: __( 'Player', 'team-membership-ledger' ),
			enableGlobalSearch: true,
			getValue: ( { item } ) => item.playerName,
			render: ( { item } ) => item.playerName,
		},
		{
			id: 'family',
			label: __( 'Family', 'team-membership-ledger' ),
			enableGlobalSearch: true,
			getValue: ( { item } ) => item.familyName,
			render: ( { item } ) => item.familyName || '—',
		},
		{
			id: 'status',
			label: __( 'Status', 'team-membership-ledger' ),
			elements: Object.entries( STATUS_LABELS ).map( ( [ value, label ] ) => ( { value, label } ) ),
			getValue: ( { item } ) => item.status,
			render: ( { item } ) => {
				const label = STATUS_LABELS[ item.status ] ?? item.status;
				return item.orderCount > 1
					? `${ label } — ${ __( 'multiple orders, manage in WooCommerce', 'team-membership-ledger' ) }`
					: label;
			},
		},
		{
			id: 'total',
			label: __( 'Total', 'team-membership-ledger' ),
			getValue: ( { item } ) => item.total,
			render: money( ( { item } ) => item.total ),
		},
		{
			id: 'paid',
			label: __( 'Paid', 'team-membership-ledger' ),
			getValue: ( { item } ) => item.paid,
			render: money( ( { item } ) => item.paid ),
		},
		{
			id: 'balance',
			label: __( 'Balance', 'team-membership-ledger' ),
			getValue: ( { item } ) => item.balance,
			render: money( ( { item } ) => item.balance ),
		},
		{
			id: 'date',
			label: __( 'Date', 'team-membership-ledger' ),
			getValue: ( { item } ) => item.date ?? '',
			render: ( { item } ) => item.date ?? '—',
		},
	];
}
