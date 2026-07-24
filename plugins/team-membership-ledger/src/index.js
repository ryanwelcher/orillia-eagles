import { createRoot } from '@wordpress/element';

const el = document.getElementById( 'tml-ledger-root' );
if ( el ) {
	createRoot( el ).render( 'Ledger app mounted.' );
}
