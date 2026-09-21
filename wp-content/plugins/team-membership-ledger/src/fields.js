import { __, _n, sprintf } from '@wordpress/i18n';
import { formatMoney } from './format';
import QuantityCell from './QuantityCell';

const STATUS_LABELS = {
	paid: __( 'Paid', 'team-membership-ledger' ),
	owes: __( 'Owes', 'team-membership-ledger' ),
	not_entered: __( 'Not entered', 'team-membership-ledger' ),
};

/**
 * Build the DataViews field definitions.
 *
 * @param {{symbol:string, decimals:number}}           currency Currency config.
 * @param {{allowsQty?:boolean, onSetQty?:Function}} options  Quantity editing for the selected product.
 * @return {Array} Field definitions.
 */
export function makeFields( currency, { allowsQty = false, onSetQty } = {} ) {
	const money = ( getValue ) => ( { item } ) => formatMoney( getValue( { item } ), currency );

	return [
		{
			id: 'name',
			label: __( 'Member', 'team-membership-ledger' ),
			enableGlobalSearch: true,
			// Search runs on this value. A member summary also carries its players'
			// names, so a player can be found without knowing who pays for them; the
			// member's name stays first, so sorting is unchanged.
			getValue: ( { item } ) =>
				item.isMember
					? [ item.name, ...item.players.map( ( p ) => p.playerName ) ].join( ' ' )
					: item.name,
			render: ( { item } ) => {
				// Player rows (level 1) sit under their member, so show the player.
				if ( item.level === 1 ) {
					return item.playerName;
				}
				if ( item.isMember ) {
					return sprintf(
						/* translators: 1: member name, 2: number of linked players */
						_n( '%1$s (%2$d player)', '%1$s (%2$d players)', item.players.length, 'team-membership-ledger' ),
						item.name,
						item.players.length
					);
				}
				return item.name;
			},
		},
		{
			id: 'status',
			label: __( 'Status', 'team-membership-ledger' ),
			elements: Object.entries( STATUS_LABELS ).map( ( [ value, label ] ) => ( { value, label } ) ),
			// A member summary answers with every status its players have, so
			// filtering to Paid still finds a member with one paid player and one
			// who owes. The filter matches when any of the statuses is selected.
			getValue: ( { item } ) =>
				item.isMember ? [ ...new Set( item.players.map( ( p ) => p.status ) ) ].sort() : item.status,
			sort: ( a, b, direction ) => {
				const order = [].concat( a ).join( ' ' ).localeCompare( [].concat( b ).join( ' ' ) );
				return direction === 'asc' ? order : -order;
			},
			render: ( { item } ) => {
				const label = STATUS_LABELS[ item.status ] ?? item.status;
				return item.orderCount > 1
					? `${ label } — ${ __( 'multiple orders, manage in WooCommerce', 'team-membership-ledger' ) }`
					: label;
			},
		},
		{
			id: 'qty',
			label: __( 'Qty', 'team-membership-ledger' ),
			getValue: ( { item } ) => item.qty,
			// Editable only on a single charge; member summaries and multi-order rows stay read-only.
			render: ( { item } ) =>
				allowsQty && onSetQty && ! item.isMember && item.orderCount <= 1 ? (
					<QuantityCell item={ item } onSetQty={ onSetQty } />
				) : (
					item.qty
				),
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
