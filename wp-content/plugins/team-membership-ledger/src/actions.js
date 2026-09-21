import { useState } from '@wordpress/element';
import { TextControl, Button, Flex, FlexItem } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { addPayment, markPaid, memberAddPayment, memberMarkPaid } from './api';

export function AddPaymentModal( { item, productId, onRowUpdated, onNotice, closeModal } ) {
	const [ amount, setAmount ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const submit = async () => {
		const value = Math.round( parseFloat( amount ) * 100 ) / 100;
		if ( ! ( value > 0 ) ) {
			onNotice( { type: 'error', message: __( 'Enter a payment amount greater than zero.', 'team-membership-ledger' ) } );
			return;
		}
		setBusy( true );
		try {
			const updated = item.isMember
				? await memberAddPayment( { productId, memberId: item.memberId, amount: value } )
				: await addPayment( {
					productId,
					orderId: item.orderId,
					memberId: item.memberId,
					playerId: item.playerId,
					amount: value,
				} );
			if ( updated ) {
				onRowUpdated( updated, productId );
				onNotice( { type: 'success', message: __( 'Payment recorded.', 'team-membership-ledger' ) } );
			} else {
				// Payment saved, but the refreshed row could not be resolved;
				// tell the user so the stale on-screen row isn't mistaken for current.
				onNotice( {
					type: 'success',
					message: __( 'Payment recorded. Reload to refresh the ledger.', 'team-membership-ledger' ),
				} );
			}
			closeModal();
		} catch ( e ) {
			onNotice( { type: 'error', message: e.message || __( 'Payment failed.', 'team-membership-ledger' ) } );
		} finally {
			setBusy( false );
		}
	};

	// Modal CONTENT only; App wraps it in its own <Modal>. (DataViews' RenderModal
	// is not used: its modal calls a components private API, kebabCase, that the
	// site's @wordpress/components does not provide, which crashes the page.)
	return (
		<div>
			<p>
				{ item.isMember && sprintf(
					/* translators: 1: member name, 2: number of players */
					_n(
						'Recording a payment for %1$s (%2$d player).',
						'Recording a payment for %1$s (%2$d players).',
						item.players.length,
						'team-membership-ledger'
					),
					item.name,
					item.players.length
				) }
				{ ! item.isMember && ( item.playerName
					? sprintf(
						/* translators: 1: player name, 2: member name */
						__( 'Recording a payment for %1$s (paid by %2$s).', 'team-membership-ledger' ),
						item.playerName,
						item.name
					)
					: sprintf(
						/* translators: %s: member name */
						__( 'Recording a payment for %s.', 'team-membership-ledger' ),
						item.name
					) ) }
			</p>
			<TextControl
				label={ __( 'Amount received now', 'team-membership-ledger' ) }
				type="number"
				min="0.01"
				step="0.01"
				value={ amount }
				onChange={ setAmount }
				__nextHasNoMarginBottom
			/>
			<Flex justify="flex-end" style={ { marginTop: '1em' } }>
				<FlexItem>
					<Button variant="tertiary" onClick={ closeModal } disabled={ busy }>
						{ __( 'Cancel', 'team-membership-ledger' ) }
					</Button>
				</FlexItem>
				<FlexItem>
					<Button variant="primary" onClick={ submit } isBusy={ busy } disabled={ busy }>
						{ __( 'Add payment', 'team-membership-ledger' ) }
					</Button>
				</FlexItem>
			</Flex>
		</div>
	);
}

/**
 * Build DataViews actions.
 *
 * @param {{productId:number, perPlayer:boolean, onRowUpdated:Function, onNotice:Function, onOpenPayment:Function}} ctx Context.
 * @return {Array} Actions.
 */
export function makeActions( { productId, perPlayer, onRowUpdated, onNotice, onOpenPayment } ) {
	// Per-player products are paid on the member line (player rows are a
	// read-only breakdown); other products on their single-order row.
	const canPay = ( item ) =>
		item.status !== 'paid' &&
		( perPlayer
			? !! item.isMember && item.players.every( ( p ) => p.orderCount <= 1 )
			: ! item.isMember && item.orderCount <= 1 );

	return [
		{
			id: 'add-payment',
			label: __( 'Add payment', 'team-membership-ledger' ),
			isEligible: canPay,
			callback: ( items ) => onOpenPayment( items[ 0 ] ),
		},
		{
			id: 'mark-paid',
			label: __( 'Mark paid', 'team-membership-ledger' ),
			isEligible: canPay,
			callback: async ( items ) => {
				const item = items[ 0 ];
				try {
					const updated = item.isMember
						? await memberMarkPaid( { productId, memberId: item.memberId } )
						: await markPaid( {
							productId,
							orderId: item.orderId,
							memberId: item.memberId,
							playerId: item.playerId,
						} );
					if ( updated ) {
						onRowUpdated( updated, productId );
						onNotice( { type: 'success', message: __( 'Marked paid.', 'team-membership-ledger' ) } );
					} else {
						// Payment saved, but the refreshed row could not be resolved;
						// tell the user so the stale on-screen row isn't mistaken for current.
						onNotice( {
							type: 'success',
							message: __( 'Marked paid. Reload to refresh the ledger.', 'team-membership-ledger' ),
						} );
					}
				} catch ( e ) {
					onNotice( { type: 'error', message: e.message || __( 'Action failed.', 'team-membership-ledger' ) } );
				}
			},
		},
	];
}
