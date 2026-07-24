import { __ } from '@wordpress/i18n';
import { formatMoney } from './format';

const STATUS_LABELS = {
	paid: __( 'Paid', 'team-membership-ledger' ),
	owes: __( 'Owes', 'team-membership-ledger' ),
	not_entered: __( 'Not entered', 'team-membership-ledger' ),
};

/**
 * Build the DataViews field definitions.
 *
 * @param {{symbol:string, decimals:number}} currency Currency config.
 * @return {Array} Field definitions.
 */
export function makeFields( currency ) {
	const money = ( getValue ) => ( { item } ) => formatMoney( getValue( { item } ), currency );

	return [
		{
			id: 'name',
			label: __( 'Member', 'team-membership-ledger' ),
			enableGlobalSearch: true,
			getValue: ( { item } ) => item.name,
			render: ( { item } ) => item.name,
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
			id: 'qty',
			label: __( 'Qty', 'team-membership-ledger' ),
			getValue: ( { item } ) => item.qty,
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
