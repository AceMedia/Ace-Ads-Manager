/**
 * Front-end tracking: impressions, viewable impressions (IAB: 50% in view for
 * a continuous second) and clicks, batched and sent with sendBeacon so the page
 * is never slowed and it works behind full-page caching.
 *
 * Nothing personal leaves the browser: a random page-view id, the page's
 * context (already public), and the ad ids on the page.
 */
( function () {
	const cfg = window.ace_ads_view;
	if ( ! cfg || ! cfg.endpoint ) {
		return;
	}

	const pageview = ( Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 12 ) ).slice( 0, 24 );
	const seen = new Set();
	let queue = [];
	let timer = null;

	const base = () => ( {
		pv: pageview,
		post_id: cfg.post_id || 0,
		view: cfg.view || '',
		post_type: cfg.post_type || '',
		term: cfg.term || '',
		ref: document.referrer || '',
		li: document.body.classList.contains( 'logged-in' ) ? 1 : 0,
		wd: navigator.webdriver ? 1 : 0,
	} );

	function flush() {
		timer = null;
		if ( ! queue.length ) {
			return;
		}
		const payload = JSON.stringify( { ...base(), events: queue.splice( 0, 50 ) } );
		try {
			if ( navigator.sendBeacon && navigator.sendBeacon( cfg.endpoint, new Blob( [ payload ], { type: 'application/json' } ) ) ) {
				return;
			}
			fetch( cfg.endpoint, { method: 'POST', body: payload, headers: { 'Content-Type': 'application/json' }, keepalive: true, credentials: 'omit' } );
		} catch ( e ) {}
	}

	function push( event, ad, immediate ) {
		const key = `${ event }:${ ad.dataset.aceAd }:${ ad.dataset.aceSlot }`;
		if ( event !== 'click' ) {
			if ( seen.has( key ) ) {
				return;
			}
			seen.add( key );
		}
		queue.push( { e: event, ad: parseInt( ad.dataset.aceAd, 10 ), slot: ad.dataset.aceSlot || '', rule: ad.dataset.aceRule ?? '' } );
		document.dispatchEvent( new CustomEvent( 'ace_ads_event', { detail: { event, adId: ad.dataset.aceAd, slot: ad.dataset.aceSlot } } ) );
		if ( immediate ) {
			flush();
		} else if ( ! timer ) {
			timer = setTimeout( flush, 1500 );
		}
	}

	// Viewability: >= 50% visible for 1 continuous second.
	const timers = new WeakMap();
	const observer = 'IntersectionObserver' in window ? new IntersectionObserver( ( entries ) => {
		entries.forEach( ( entry ) => {
			const ad = entry.target;
			if ( entry.isIntersecting && entry.intersectionRatio >= 0.5 ) {
				if ( ! timers.has( ad ) ) {
					timers.set( ad, setTimeout( () => {
						push( 'viewable', ad, false );
						observer.unobserve( ad );
					}, 1000 ) );
				}
			} else if ( timers.has( ad ) ) {
				clearTimeout( timers.get( ad ) );
				timers.delete( ad );
			}
		} );
	}, { threshold: [ 0, 0.5, 1 ] } ) : null;

	function track( ad ) {
		if ( ad.dataset.aceTracked ) {
			return;
		}
		ad.dataset.aceTracked = '1';
		if ( cfg.impressions ) {
			push( 'impression', ad, false );
			if ( observer ) {
				observer.observe( ad );
			}
		}
	}

	function scan( root ) {
		( root || document ).querySelectorAll( '.ace-ad[data-ace-ad]' ).forEach( track );
	}

	if ( cfg.clicks ) {
		document.addEventListener( 'click', ( event ) => {
			const link = event.target.closest( '.ace-ad a' );
			const ad = link && link.closest( '[data-ace-ad]' );
			if ( ad ) {
				push( 'click', ad, true );
			}
		}, { passive: true, capture: true } );
	}

	// Ads injected after load (infinite scroll, lazy blocks) are picked up too.
	if ( 'MutationObserver' in window ) {
		new MutationObserver( ( mutations ) => {
			mutations.forEach( ( m ) => m.addedNodes.forEach( ( n ) => {
				if ( n.nodeType === 1 ) {
					scan( n );
				}
			} ) );
		} ).observe( document.documentElement, { childList: true, subtree: true } );
	}

	document.addEventListener( 'visibilitychange', () => {
		if ( document.visibilityState === 'hidden' ) {
			flush();
		}
	} );
	window.addEventListener( 'pagehide', flush );

	scan();
} )();
