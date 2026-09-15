import { useState } from '@wordpress/element';
import { TextControl, Button, Flex, FlexItem } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { addPayment, markPaid } from './api';

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
			const updated = await addPayment( {
				productId,
				orderId: item.orderId,
				memberId: item.memberId,
				playerId: item.playerId,
				amount: value,
			} );
			if ( updated ) {
				onRowUpdated( updated );
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
				{ item.playerName
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
					) }
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
 * @param {{productId:number, onRowUpdated:Function, onNotice:Function, onOpenPayment:Function}} ctx Context.
 * @return {Array} Actions.
 */
export function makeActions( { productId, onRowUpdated, onNotice, onOpenPayment } ) {
	return [
		{
			id: 'add-payment',
			label: __( 'Add payment', 'team-membership-ledger' ),
			// Member summary rows roll up several orders; pay against a player row.
			isEligible: ( item ) => ! item.isMember && item.status !== 'paid' && item.orderCount <= 1,
			callback: ( items ) => onOpenPayment( items[ 0 ] ),
		},
		{
			id: 'mark-paid',
			label: __( 'Mark paid', 'team-membership-ledger' ),
			// Member summary rows roll up several orders; pay against a player row.
			isEligible: ( item ) => ! item.isMember && item.status !== 'paid' && item.orderCount <= 1,
			callback: async ( items ) => {
				const item = items[ 0 ];
				try {
					const updated = await markPaid( {
						productId,
						orderId: item.orderId,
						memberId: item.memberId,
						playerId: item.playerId,
					} );
					if ( updated ) {
						onRowUpdated( updated );
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
