/**
 * Front-end click tracking: beacon a click on any ad link to the REST endpoint.
 * No dependencies, deferred, and harmless if the endpoint is unreachable.
 */
( function () {
	const cfg = window.ace_ads_view;
	if ( ! cfg || ! cfg.endpoint ) {
		return;
	}
	document.addEventListener( 'click', function ( event ) {
		const link = event.target.closest( '.ace-ad a' );
		if ( ! link ) {
			return;
		}
		const ad = link.closest( '[data-ace-ad]' );
		if ( ! ad ) {
			return;
		}
		const payload = JSON.stringify( {
			ad_id: parseInt( ad.dataset.aceAd, 10 ),
			post_id: cfg.post_id || 0,
			slot: ad.dataset.aceSlot || '',
		} );
		try {
			if ( navigator.sendBeacon ) {
				navigator.sendBeacon( cfg.endpoint, new Blob( [ payload ], { type: 'application/json' } ) );
			} else {
				fetch( cfg.endpoint, { method: 'POST', body: payload, headers: { 'Content-Type': 'application/json' }, keepalive: true } );
			}
		} catch ( e ) {}
		document.dispatchEvent( new CustomEvent( 'ace_ads_click', { detail: { adId: ad.dataset.aceAd, slot: ad.dataset.aceSlot, href: link.href } } ) );
	}, { passive: true } );
} )();
