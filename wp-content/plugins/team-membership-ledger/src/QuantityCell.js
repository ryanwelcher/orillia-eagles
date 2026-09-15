import { useState, useEffect, useRef } from '@wordpress/element';
import { TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { parseQty } from './quantity';

const toDraft = ( qty ) => ( qty ? String( qty ) : '' );

/**
 * Inline quantity editor for a ledger row. Saves on Enter or blur; Escape reverts.
 *
 * @param {{item:Object, onSetQty:(item:Object, qty:number)=>Promise<boolean>}} props Props.
 */
export default function QuantityCell( { item, onSetQty } ) {
	const [ draft, setDraft ] = useState( toDraft( item.qty ) );
	const [ busy, setBusy ] = useState( false );
	// Set by Escape so the blur that follows doesn't save the stale draft.
	const cancelled = useRef( false );

	useEffect( () => setDraft( toDraft( item.qty ) ), [ item.qty ] );

	const commit = async () => {
		if ( cancelled.current ) {
			cancelled.current = false;
			return;
		}
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
			onKeyDown={ ( event ) => {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
					event.currentTarget.blur();
				} else if ( 'Escape' === event.key ) {
					cancelled.current = true;
					setDraft( toDraft( item.qty ) );
					event.currentTarget.blur();
				}
			} }
			disabled={ busy }
			style={ { width: '5em' } }
			__nextHasNoMarginBottom
		/>
	);
}
