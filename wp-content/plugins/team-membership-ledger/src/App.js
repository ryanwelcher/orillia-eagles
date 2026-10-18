import { useState, useEffect, useMemo, useRef, Component } from '@wordpress/element';
import { SelectControl, Spinner, Snackbar, Button, ToggleControl, Modal } from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { fetchLedger, setQuantity } from './api';
import { memberSummaries, withPlayers } from './groups';
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

/**
 * The bundled DataViews leans on private @wordpress/components APIs of the host
 * (see webpack.config.js). If one is missing it throws while rendering; show
 * that instead of a blank screen.
 */
class TableBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { failed: false };
	}
	static getDerivedStateFromError() {
		return { failed: true };
	}
	render() {
		return this.state.failed ? (
			<div className="notice notice-error">
				<p>{ __( 'The ledger table could not be shown. Reload the page; if it keeps happening, the site’s Gutenberg version may not match this plugin.', 'team-membership-ledger' ) }</p>
			</div>
		) : (
			this.props.children
		);
	}
}

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

	const perPlayer = !! products.find( ( p ) => p.id === productId )?.perPlayer;
	const allowsQty = !! products.find( ( p ) => p.id === productId )?.allowsQty;

	// Row ids don't carry the product, so a reply that lands after the product
	// was switched must not be merged into the other product's rows.
	const productRef = useRef( productId );
	productRef.current = productId;

	// Accepts one updated row, or a list (member-level actions return every player row).
	const handleRowUpdated = ( updated, forProductId = productRef.current ) => {
		if ( forProductId !== productRef.current ) {
			return;
		}
		const isList = Array.isArray( updated );
		const list = isList ? updated : [ updated ];
		const byId = new Map( list.map( ( r ) => [ r.id, r ] ) );
		// A member-level reply is that member's whole set of rows, so a player no
		// longer in it (unlinked since the screen loaded) goes.
		const members = new Set( isList ? list.map( ( r ) => r.memberId ) : [] );
		setRows( ( current ) => {
			const known = new Set( current.map( ( r ) => r.id ) );
			// A row the screen hasn't seen (a player linked after it loaded) is
			// added rather than dropped, or a saved payment would not show at all.
			const added = list.filter( ( r ) => ! known.has( r.id ) );
			return [
				...current
					.filter( ( r ) => ! members.has( r.memberId ) || byId.has( r.id ) )
					.map( ( r ) => byId.get( r.id ) ?? r ),
				...added,
			];
		} );
	};

	const actions = useMemo(
		() =>
			makeActions( {
				productId,
				perPlayer,
				onRowUpdated: handleRowUpdated,
				onNotice: setNotice,
				onOpenPayment: setPaymentItem,
			} ),
		[ productId, perPlayer ]
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
					handleRowUpdated( updated, productId );
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
		// Filtering Status to "Not entered" asks for those rows as plainly as the
		// toggle does; without this the filter always gives an empty table.
		const keepUnentered =
			showUnentered ||
			( view.filters ?? [] ).some(
				( f ) => f.field === 'status' && [].concat( f.value ?? [] ).includes( 'not_entered' )
			);
		if ( ! perPlayer ) {
			const visibleRows = keepUnentered
				? rows
				: rows.filter( ( r ) => r.status !== 'not_entered' );
			return filterSortAndPaginate( visibleRows, view, fields );
		}
		const result = filterSortAndPaginate( memberSummaries( rows, { showUnentered: keepUnentered } ), view, fields );
		return { ...result, data: showPlayers ? withPlayers( result.data ) : result.data };
	}, [ rows, view, fields, perPlayer, showPlayers, showUnentered ] );

	// Fewer rows (the toggle, another product, a filter) can leave the view on a
	// page that no longer exists; DataViews then shows nothing and no pager.
	useEffect( () => {
		const last = Math.max( 1, paginationInfo.totalPages || 1 );
		if ( view.page > last ) {
			setView( ( current ) => ( { ...current, page: last } ) );
		}
	}, [ paginationInfo.totalPages, view.page ] );

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
				<TableBoundary>
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
				</TableBoundary>
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
						// Closes this row's modal only. A slow reply for a modal that
						// was dismissed must not shut the one opened since.
						closeModal={ () => setPaymentItem( ( current ) => ( current === paymentItem ? null : current ) ) }
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
