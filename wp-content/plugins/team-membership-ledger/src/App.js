import { useState, useEffect, useMemo } from '@wordpress/element';
import { SelectControl, Spinner, Snackbar, Button, ToggleControl, Modal } from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { fetchLedger, setQuantity } from './api';
import { groupByMember, withPlayers } from './groups';
import { makeFields } from './fields';
import { makeActions, AddPaymentModal } from './actions';

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
	const [ showPlayers, setShowPlayers ] = useState( true );
	// "Not entered" rows (no charge yet) are hidden unless asked for.
	const [ showUnentered, setShowUnentered ] = useState( false );
	// Row the Add payment modal is open for (null = closed).
	const [ paymentItem, setPaymentItem ] = useState( null );
	// Bumped by the error "Retry" button to re-run the load effect.
	const [ reloadKey, setReloadKey ] = useState( 0 );

	const handleRowUpdated = ( updated ) => {
		setRows( ( current ) =>
			current.map( ( r ) => ( r.id === updated.id ? updated : r ) )
		);
	};

	const actions = useMemo(
		() =>
			makeActions( {
				productId,
				onRowUpdated: handleRowUpdated,
				onNotice: setNotice,
				onOpenPayment: setPaymentItem,
			} ),
		[ productId ]
	);

	// Returns whether the save worked so the cell can revert on failure.
	const onSetQty = useMemo(
		() => async ( item, qty ) => {
			try {
				const updated = await setQuantity( {
					productId,
					orderId: item.orderId,
					memberId: item.memberId,
					playerId: item.playerId,
					qty,
				} );
				if ( updated ) {
					handleRowUpdated( updated );
					setNotice( { type: 'success', message: __( 'Quantity updated.', 'team-membership-ledger' ) } );
				} else {
					setNotice( {
						type: 'success',
						message: __( 'Quantity updated. Reload to refresh the ledger.', 'team-membership-ledger' ),
					} );
				}
				return true;
			} catch ( e ) {
				setNotice( { type: 'error', message: e.message || __( 'Could not update the quantity.', 'team-membership-ledger' ) } );
				return false;
			}
		},
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

	const perPlayer = !! products.find( ( p ) => p.id === productId )?.perPlayer;
	const allowsQty = !! products.find( ( p ) => p.id === productId )?.allowsQty;

	// Per-player products show each member's player rows indented under it.
	const shownView = useMemo(
		() => ( perPlayer ? { ...view, showLevels: true } : view ),
		[ view, perPlayer ]
	);

	const fields = useMemo(
		() => makeFields( currency, { allowsQty, onSetQty } ),
		[ currency, allowsQty, onSetQty ]
	);
	// Per-player products sort, filter and paginate member summary rows (full
	// amounts), then slot each member's players underneath so they stay together.
	const { data: shownData, paginationInfo } = useMemo( () => {
		// Filter before grouping so a member whose players all lack a charge drops out.
		const visibleRows = showUnentered
			? rows
			: rows.filter( ( r ) => r.status !== 'not_entered' );
		if ( ! perPlayer ) {
			return filterSortAndPaginate( visibleRows, view, fields );
		}
		const result = filterSortAndPaginate( groupByMember( visibleRows ), view, fields );
		return { ...result, data: showPlayers ? withPlayers( result.data ) : result.data };
	}, [ rows, view, fields, perPlayer, showPlayers, showUnentered ] );

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
						__( 'Member ledger', 'team-membership-ledger' ) }
				</h2>
			) }
			{ productId !== 0 && (
				<div
					style={ {
						display: 'flex',
						flexWrap: 'wrap',
						gap: '12px 24px',
						marginBottom: '16px',
					} }
				>
					{ perPlayer && (
						<ToggleControl
							label={ __( 'Show linked players', 'team-membership-ledger' ) }
							checked={ showPlayers }
							onChange={ setShowPlayers }
							__nextHasNoMarginBottom
						/>
					) }
					<ToggleControl
						label={ __( 'Show members without charges', 'team-membership-ledger' ) }
						checked={ showUnentered }
						onChange={ setShowUnentered }
						__nextHasNoMarginBottom
					/>
				</div>
			) }
			{ productId === 0 ? (
				<p>{ __( 'Select a product to view the ledger.', 'team-membership-ledger' ) }</p>
			) : isLoading ? (
				<Spinner />
			) : (
				<DataViews
					data={ shownData }
					fields={ fields }
					view={ shownView }
					onChangeView={ setView }
					paginationInfo={ paginationInfo }
					defaultLayouts={ { table: {} } }
					getItemId={ ( item ) => item.id }
					getItemLevel={ ( item ) => item.level ?? 0 }
					isLoading={ isLoading }
					actions={ actions }
				/>
			) }
			{ paymentItem && (
				<Modal
					title={ __( 'Add payment', 'team-membership-ledger' ) }
					onRequestClose={ () => setPaymentItem( null ) }
				>
					<AddPaymentModal
						item={ paymentItem }
						productId={ productId }
						onRowUpdated={ handleRowUpdated }
						onNotice={ setNotice }
						closeModal={ () => setPaymentItem( null ) }
					/>
				</Modal>
			) }
			{ notice && (
				<div style={ { position: 'fixed', bottom: 20, left: 20, zIndex: 100000 } }>
					<Snackbar onRemove={ () => setNotice( null ) }>{ notice.message }</Snackbar>
				</div>
			) }
		</div>
	);
}
