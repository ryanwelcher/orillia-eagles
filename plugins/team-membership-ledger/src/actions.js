import { useState } from '@wordpress/element';
import { TextControl, Button, Flex, FlexItem } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { addPayment } from './api';

function AddPaymentModal( { item, productId, onRowUpdated, onNotice, closeModal } ) {
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
				amount: value,
			} );
			if ( updated ) {
				onRowUpdated( updated );
			}
			onNotice( { type: 'success', message: __( 'Payment recorded.', 'team-membership-ledger' ) } );
			closeModal();
		} catch ( e ) {
			onNotice( { type: 'error', message: e.message || __( 'Payment failed.', 'team-membership-ledger' ) } );
		} finally {
			setBusy( false );
		}
	};

	// DataViews' RenderModal already provides the Modal wrapper (titled with the
	// action label), so this returns the modal CONTENT only — wrapping it in
	// another <Modal> would nest two modals.
	return (
		<div>
			<p>
				{ sprintf(
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
 * @param {{productId:number, onRowUpdated:Function, onNotice:Function}} ctx Context.
 * @return {Array} Actions.
 */
export function makeActions( { productId, onRowUpdated, onNotice } ) {
	return [
		{
			id: 'add-payment',
			label: __( 'Add payment', 'team-membership-ledger' ),
			isEligible: ( item ) => item.status !== 'paid' && item.orderCount <= 1,
			RenderModal: ( { items, closeModal } ) => (
				<AddPaymentModal
					item={ items[ 0 ] }
					productId={ productId }
					onRowUpdated={ onRowUpdated }
					onNotice={ onNotice }
					closeModal={ closeModal }
				/>
			),
		},
	];
}
