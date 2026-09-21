import { useState, useEffect, useRef } from '@wordpress/element';
import { TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { parseQty } from './quantity';

const toDraft = ( qty ) => ( qty ? String( qty ) : '' );

/**
 * Inline quantity editor for a ledger row. Saves on Enter only; Escape, or
 * leaving the cell, reverts. A number input also changes on an arrow key or the
 * scroll wheel, and a change here can reopen a paid order or create a charge,
 * so it has to be confirmed rather than picked up on the way past.
 *
 * @param {{item:Object, onSetQty:(item:Object, qty:number)=>Promise<boolean>}} props Props.
 */
export default function QuantityCell( { item, onSetQty } ) {
	const [ draft, setDraft ] = useState( toDraft( item.qty ) );
	const [ busy, setBusy ] = useState( false );
	// Set by Enter, so the blur that follows knows this one is meant.
	const confirmed = useRef( false );

	useEffect( () => setDraft( toDraft( item.qty ) ), [ item.qty ] );

	const commit = async () => {
		if ( ! confirmed.current ) {
			setDraft( toDraft( item.qty ) );
			return;
		}
		confirmed.current = false;
		const qty = parseQty( draft, item.qty );
		if ( null === qty ) {
			setDraft( toDraft( item.qty ) );
			return;
		}
		setBusy( true );
		const ok = await onSetQty( item, qty );
		setBusy( false );
		if ( ! ok ) {
			setDraft( toDraft( item.qty ) );
		}
	};

	return (
		<TextControl
			label={ sprintf(
				/* translators: %s: member or player name */
				__( 'Quantity for %s', 'team-membership-ledger' ),
				item.playerName || item.name
			) }
			hideLabelFromVision
			type="number"
			min="1"
			step="1"
			placeholder="0"
			value={ draft }
			onChange={ setDraft }
			onBlur={ commit }
			help={ draft !== toDraft( item.qty ) ? __( 'Press Enter to save', 'team-membership-ledger' ) : undefined }
			// Scrolling the page over a focused number input would change it.
			onWheel={ ( event ) => event.currentTarget.blur() }
			onKeyDown={ ( event ) => {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
					confirmed.current = true;
					event.currentTarget.blur();
				} else if ( 'Escape' === event.key ) {
					event.currentTarget.blur();
				}
			} }
			disabled={ busy }
			style={ { width: '5em' } }
			__nextHasNoMarginBottom
		/>
	);
}
