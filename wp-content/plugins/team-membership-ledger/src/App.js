import { useState, useEffect, useMemo } from '@wordpress/element';
import { SelectControl, Spinner, Snackbar, Button } from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { fetchLedger } from './api';
import { makeFields, familyTotals } from './fields';
import { makeActions } from './actions';

const DEFAULT_VIEW = {
	type: 'table',
	page: 1,
	// Large enough that a family's players rarely split across pages.
	perPage: 100,
	search: '',
	filters: [],
	groupBy: { field: 'group', direction: 'asc', showLabel: false },
	sort: { field: 'player', direction: 'asc' },
	fields: [ 'status', 'total', 'paid', 'balance', 'date' ],
	titleField: 'player',
};

export default function App() {
	// wp_localize_script stringifies values, so coerce the initial id to a number
	// (it must match the numeric product ids from the REST response).
	const [ productId, setProductId ] = useState(
		Number( window.tmlLedger?.productId ) || 0
	);
	const [ products, setProducts ] = useState( [] );
	const [ currency, setCurrency ] = useState( { symbol: '$', decimals: 2 } );
	const [ rows, setRows ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ view, setView ] = useState( DEFAULT_VIEW );
	const [ notice, setNotice ] = useState( null );
	// Bumped by the error "Retry" button to re-run the load effect.
	const [ reloadKey, setReloadKey ] = useState( 0 );

	const handleRowUpdated = ( updated ) => {
		setRows( ( current ) =>
			current.map( ( r ) => ( r.id === updated.id ? updated : r ) )
		);
	};

	const actions = useMemo(
		() => makeActions( { productId, onRowUpdated: handleRowUpdated, onNotice: setNotice } ),
		[ productId ]
	);

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
	}, [ productId, reloadKey ] );

	const fields = useMemo(
		() => makeFields( currency, familyTotals( rows ) ),
		[ currency, rows ]
	);
	const { data: shownData, paginationInfo } = useMemo(
		() => filterSortAndPaginate( rows, view, fields ),
		[ rows, view, fields ]
	);

	const productOptions = [
		{ value: 0, label: __( '— Select a product —', 'team-membership-ledger' ) },
		...products.map( ( p ) => ( { value: p.id, label: p.name } ) ),
	];

	const productName = products.find( ( p ) => p.id === productId )?.name || '';

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
				<div className="notice notice-error">
					<p>{ error }</p>
					<p>
						<Button
							variant="secondary"
							onClick={ () => setReloadKey( ( key ) => key + 1 ) }
							disabled={ isLoading }
						>
							{ __( 'Retry', 'team-membership-ledger' ) }
						</Button>
					</p>
				</div>
			) }
			{ productId !== 0 && (
				<h2
					className="tml-ledger__title"
					style={ { marginTop: '24px', marginBottom: '12px' } }
				>
					{ productName ||
						__( 'Player ledger', 'team-membership-ledger' ) }
				</h2>
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
					getItemId={ ( item ) => String( item.id ) }
					isLoading={ isLoading }
					actions={ actions }
				/>
			) }
			{ notice && (
				<div style={ { position: 'fixed', bottom: 20, left: 20, zIndex: 100000 } }>
					<Snackbar onRemove={ () => setNotice( null ) }>{ notice.message }</Snackbar>
				</div>
			) }
		</div>
	);
}
