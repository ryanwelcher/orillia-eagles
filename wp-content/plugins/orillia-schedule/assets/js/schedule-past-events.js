( function () {
	function setupPastEvents( table, index ) {
		const rows = Array.from( table.querySelectorAll( '.schedule-row--past' ) );
		const items = rows
			.map( ( row ) => row.closest( 'li' ) )
			.filter( ( item, itemIndex, allItems ) => item && allItems.indexOf( item ) === itemIndex );

		if ( ! items.length ) {
			return;
		}

		const query = table.querySelector( '.schedule-query' );
		if ( ! query ) {
			return;
		}

		const controls = document.createElement( 'div' );
		const button = document.createElement( 'button' );
		const listId = 'schedule-past-events-' + index;
		const showLabel = 'Show past events (' + items.length + ')';
		const hideLabel = 'Hide past events';

		controls.className = 'schedule-past-controls';
		button.className = 'schedule-past-toggle';
		button.type = 'button';
		button.setAttribute( 'aria-expanded', 'false' );
		button.setAttribute( 'aria-controls', listId );
		button.textContent = showLabel;

		query.id = query.id || listId;
		items.forEach( ( item ) => {
			item.hidden = true;
		} );

		button.addEventListener( 'click', function () {
			const expanded = 'true' === button.getAttribute( 'aria-expanded' );
			items.forEach( ( item ) => {
				item.hidden = expanded;
			} );
			button.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
			button.textContent = expanded ? showLabel : hideLabel;
		} );

		controls.appendChild( button );
		table.classList.add( 'schedule-past-filter-ready' );
		table.insertBefore( controls, table.firstElementChild );
	}

	function initialize() {
		document.querySelectorAll( '.schedule-table' ).forEach( setupPastEvents );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initialize );
	} else {
		initialize();
	}
} )();
