import { useState, useEffect, useMemo } from '@wordpress/element';
import { SelectControl, Spinner } from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { fetchLedger } from './api';
import { makeFields } from './fields';

const DEFAULT_VIEW = {
	type: 'table',
	page: 1,
	perPage: 25,
	search: '',
	filters: [],
	sort: { field: 'name', direction: 'asc' },
	fields: [ 'status', 'qty', 'total', 'paid', 'balance', 'date' ],
	titleField: 'name',
};

export default function App() {
	const [ productId, setProductId ] = useState( window.tmlLedger?.productId || 0 );
	const [ products, setProducts ] = useState( [] );
	const [ currency, setCurrency ] = useState( { symbol: '$', decimals: 2 } );
	const [ rows, setRows ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ view, setView ] = useState( DEFAULT_VIEW );

	useEffect( () => {
		let active = true;
		setIsLoading( true );
		setError( null );
		fetchLedger( productId )
			.then( ( data ) => {
				if ( ! active ) {
					return;
				}
				setProducts( data.products );
				setCurrency( data.currency );
				setRows( data.rows );
			} )
			.catch( ( e ) => {
				if ( ! active ) {
					return;
				}
				setRows( [] );
				setError(
					e.message ||
						__( 'Failed to load the ledger.', 'team-membership-ledger' )
				);
			} )
			.finally( () => active && setIsLoading( false ) );
		return () => {
			active = false;
		};
	}, [ productId ] );

	const fields = useMemo( () => makeFields( currency ), [ currency ] );
	const { data: shownData, paginationInfo } = useMemo(
		() => filterSortAndPaginate( rows, view, fields ),
		[ rows, view, fields ]
	);

	const productOptions = [
		{ value: 0, label: __( '— Select a product —', 'team-membership-ledger' ) },
		...products.map( ( p ) => ( { value: p.id, label: p.name } ) ),
	];

	return (
		<div>
			<SelectControl
				label={ __( 'Product', 'team-membership-ledger' ) }
				value={ productId }
				options={ productOptions }
				onChange={ ( value ) => setProductId( Number( value ) ) }
				__nextHasNoMarginBottom
			/>
			{ error && (
				<div className="notice notice-error"><p>{ error }</p></div>
			) }
			{ productId === 0 ? (
				<p>{ __( 'Select a product to view the ledger.', 'team-membership-ledger' ) }</p>
			) : isLoading ? (
				<Spinner />
			) : (
				<DataViews
					data={ shownData }
					fields={ fields }
					view={ view }
					onChangeView={ setView }
					paginationInfo={ paginationInfo }
					defaultLayouts={ { table: {} } }
					getItemId={ ( item ) => String( item.memberId ) }
					isLoading={ isLoading }
				/>
			) }
		</div>
	);
}
