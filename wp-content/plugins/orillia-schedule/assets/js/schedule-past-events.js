( function () {
	// Level filters. The Intermediate filter also covers Senior, as on the roster.
	const levelFilters = [
		{ key: 'all', label: 'All', levels: [] },
		{ key: 'junior', label: 'Junior', levels: [ 'junior' ] },
		{ key: 'intermediate', label: 'Intermediate', levels: [ 'intermediate', 'senior' ] },
	];
	const knownLevels = [ 'junior', 'intermediate', 'senior' ];

	// Events tagged Mixed, or with no level, apply to every level.
	function matchesLevel( item, filter ) {
		if ( ! filter.levels.length ) {
			return true;
		}
		const isAllLevels = ! knownLevels.some( ( level ) => item.classList.contains( 'event_level-' + level ) );
		return isAllLevels || filter.levels.some( ( level ) => item.classList.contains( 'event_level-' + level ) );
	}

	function setupSchedule( table, index ) {
		const query = table.querySelector( '.schedule-query' );
		if ( ! query ) {
			return;
		}

		const items = Array.from( query.querySelectorAll( '.schedule-body > li' ) );
		if ( ! items.length ) {
			return;
		}

		const isPast = ( item ) => !! item.querySelector( '.schedule-row--past' );
		const hasPast = items.some( isPast );
		const listId = 'schedule-past-events-' + index;
		const state = { filter: levelFilters[ 0 ], showPast: false };

		const controls = document.createElement( 'div' );
		controls.className = 'schedule-past-controls';

		const filterGroup = document.createElement( 'div' );
		filterGroup.className = 'schedule-level-filters';
		filterGroup.setAttribute( 'role', 'group' );
		filterGroup.setAttribute( 'aria-label', 'Filter schedule by level' );

		const filterButtons = levelFilters.map( ( filter ) => {
			const button = document.createElement( 'button' );
			button.className = 'schedule-level-filter';
			button.type = 'button';
			button.textContent = filter.label;
			button.setAttribute( 'aria-controls', query.id || listId );
			button.addEventListener( 'click', function () {
				state.filter = filter;
				update();
			} );
			filterGroup.appendChild( button );
			return button;
		} );
		controls.appendChild( filterGroup );

		let pastButton = null;
		if ( hasPast ) {
			pastButton = document.createElement( 'button' );
			pastButton.className = 'schedule-past-toggle';
			pastButton.type = 'button';
			pastButton.setAttribute( 'aria-controls', query.id || listId );
			pastButton.addEventListener( 'click', function () {
				state.showPast = ! state.showPast;
				update();
			} );
			controls.appendChild( pastButton );
		}

		const empty = document.createElement( 'p' );
		empty.className = 'schedule-filter-empty';
		empty.setAttribute( 'role', 'status' );
		query.appendChild( empty );

		function update() {
			let visibleCount = 0;
			let pastCount = 0;

			items.forEach( ( item ) => {
				const levelMatch = matchesLevel( item, state.filter );
				const past = isPast( item );
				if ( levelMatch && past ) {
					pastCount++;
				}
				item.hidden = ! levelMatch || ( past && ! state.showPast );
				if ( ! item.hidden ) {
					visibleCount++;
				}
			} );

			filterButtons.forEach( ( button, buttonIndex ) => {
				button.setAttribute( 'aria-pressed', levelFilters[ buttonIndex ] === state.filter ? 'true' : 'false' );
			} );

			if ( pastButton ) {
				pastButton.hidden = ! pastCount;
				pastButton.setAttribute( 'aria-expanded', state.showPast ? 'true' : 'false' );
				pastButton.textContent = state.showPast ? 'Hide past events' : 'Show past events (' + pastCount + ')';
			}

			empty.hidden = visibleCount > 0;
			empty.textContent = visibleCount > 0 ? '' : 'No upcoming events for this level.';
		}

		query.id = query.id || listId;
		update();
		table.classList.add( 'schedule-past-filter-ready' );
		table.insertBefore( controls, table.firstElementChild );
	}

	function initialize() {
		document.querySelectorAll( '.schedule-table' ).forEach( setupSchedule );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initialize );
	} else {
		initialize();
	}
} )();
