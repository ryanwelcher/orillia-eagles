import '@wordpress/dataviews/build-style/style.css';
import { createRoot } from '@wordpress/element';
import App from './App';

const el = document.getElementById( 'tml-ledger-root' );
if ( el ) {
	createRoot( el ).render( <App /> );
}
