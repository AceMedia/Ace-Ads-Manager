/**
 * Placement rules editor on the Ad edit screen. Keeps the rules array in the
 * hidden JSON field that the meta box saves; the UI is a thin editor over it.
 */
const CFG = window.ace_ads_editor || {};

const el = ( tag, attrs = {}, children = [] ) => {
	const node = document.createElement( tag );
	Object.entries( attrs ).forEach( ( [ k, v ] ) => {
		if ( k === 'class' ) node.className = v;
		else if ( k.startsWith( 'on' ) ) node.addEventListener( k.slice( 2 ), v );
		else if ( v !== null && v !== undefined && v !== false ) node.setAttribute( k, v === true ? '' : v );
	} );
	( Array.isArray( children ) ? children : [ children ] ).forEach( ( c ) => {
		if ( c !== null && c !== undefined ) node.append( typeof c === 'string' ? document.createTextNode( c ) : c );
	} );
	return node;
};

const TYPES_WITH_VALUES = {
	post_type: 'post_types',
	post_type_archive: 'post_types',
	taxonomy_archive: 'taxonomies',
	taxonomy_term: 'terms',
	post: 'posts',
	author: 'free',
};

let rules = [];
let jsonField;
let root;

function newRule() {
	return { slot: Object.keys( CFG.slots || {} )[ 0 ] || 'top', priority: 10, targets: [ { type: 'everywhere', values: [] } ], loop_index: '', parent_block: '', after_paragraphs: 0 };
}

function sync() {
	jsonField.value = JSON.stringify( rules );
}

async function searchTerms( taxonomy, term ) {
	const url = `${ CFG.rest_url }wp/v2/${ taxonomyRestBase( taxonomy ) }?search=${ encodeURIComponent( term ) }&per_page=20&_fields=id,name`;
	const res = await fetch( url, { headers: { 'X-WP-Nonce': CFG.nonce }, credentials: 'same-origin' } );
	return res.ok ? res.json() : [];
}

function taxonomyRestBase( taxonomy ) {
	return { category: 'categories', post_tag: 'tags' }[ taxonomy ] || taxonomy;
}

function valuesEditor( target, onChange ) {
	const kind = TYPES_WITH_VALUES[ target.type ];
	if ( ! kind ) return el( 'span', { class: 'description' }, '' );

	if ( kind === 'post_types' ) {
		return el( 'span', { class: 'ace-ad-values' }, Object.entries( CFG.post_types ).map( ( [ v, label ] ) =>
			el( 'label', {}, [ el( 'input', { type: 'checkbox', checked: target.values.includes( v ), onchange: ( e ) => { target.values = e.target.checked ? [ ...target.values, v ] : target.values.filter( ( x ) => x !== v ); onChange(); } } ), ` ${ label } ` ] )
		) );
	}
	if ( kind === 'taxonomies' ) {
		return el( 'span', { class: 'ace-ad-values' }, Object.entries( CFG.taxonomies ).map( ( [ v, label ] ) =>
			el( 'label', {}, [ el( 'input', { type: 'checkbox', checked: target.values.includes( v ), onchange: ( e ) => { target.values = e.target.checked ? [ ...target.values, v ] : target.values.filter( ( x ) => x !== v ); onChange(); } } ), ` ${ label } ` ] )
		) );
	}
	if ( kind === 'terms' ) {
		const taxSelect = el( 'select', {}, Object.entries( CFG.taxonomies ).map( ( [ v, label ] ) => el( 'option', { value: v }, label ) ) );
		const search = el( 'input', { type: 'search', placeholder: 'Search terms…' } );
		const results = el( 'select', { size: 5, class: 'ace-ad-term-results' } );
		const chips = el( 'span', { class: 'ace-ad-chips' }, target.values.map( ( v ) => chip( v, () => { target.values = target.values.filter( ( x ) => x !== v ); onChange(); } ) ) );
		let timer;
		search.addEventListener( 'input', () => {
			clearTimeout( timer );
			timer = setTimeout( async () => {
				const found = await searchTerms( taxSelect.value, search.value );
				results.replaceChildren( ...found.map( ( t ) => el( 'option', { value: `${ taxSelect.value }:${ t.id }` }, t.name ) ) );
			}, 300 );
		} );
		results.addEventListener( 'change', () => {
			if ( results.value && ! target.values.includes( results.value ) ) {
				target.values = [ ...target.values, results.value ];
				onChange();
			}
		} );
		return el( 'span', { class: 'ace-ad-values ace-ad-values--terms' }, [ chips, taxSelect, search, results ] );
	}
	// posts / free: comma-separated ids
	const input = el( 'input', { type: 'text', class: 'regular-text', value: target.values.join( ',' ), placeholder: kind === 'posts' ? 'Post ids, comma separated' : 'Ids, comma separated (empty = any)', onchange: ( e ) => { target.values = e.target.value.split( ',' ).map( ( s ) => s.trim() ).filter( Boolean ); onChange(); } } );
	return el( 'span', { class: 'ace-ad-values' }, input );
}

function chip( value, onRemove ) {
	return el( 'span', { class: 'ace-ad-chip' }, [ value, el( 'button', { type: 'button', class: 'ace-ad-chip__remove', 'aria-label': 'Remove', onclick: onRemove }, '×' ) ] );
}

function targetRow( rule, target, index ) {
	const row = el( 'div', { class: 'ace-ad-target' } );
	const typeSelect = el( 'select', { onchange: ( e ) => { target.type = e.target.value; target.values = []; sync(); render(); } }, Object.entries( CFG.target_types ).map( ( [ v, label ] ) => el( 'option', { value: v, selected: v === target.type }, label ) ) );
	row.append(
		typeSelect,
		valuesEditor( target, () => { sync(); render(); } ),
		el( 'button', { type: 'button', class: 'button-link-delete', onclick: () => { rule.targets.splice( index, 1 ); if ( ! rule.targets.length ) rule.targets.push( { type: 'everywhere', values: [] } ); sync(); render(); } }, 'Remove' )
	);
	return row;
}

function ruleCard( rule, index ) {
	const slotSelect = el( 'select', { onchange: ( e ) => { rule.slot = e.target.value; sync(); render(); } }, Object.entries( CFG.slots ).map( ( [ v, label ] ) => el( 'option', { value: v, selected: v === rule.slot }, label ) ) );
	const priority = el( 'input', { type: 'number', class: 'small-text', value: rule.priority, onchange: ( e ) => { rule.priority = parseInt( e.target.value, 10 ) || 0; sync(); } } );
	const loop = el( 'input', { type: 'text', class: 'small-text', value: rule.loop_index, placeholder: 'e.g. 3 or 3n', onchange: ( e ) => { rule.loop_index = e.target.value.replace( /[^0-9n]/g, '' ); sync(); } } );
	const parent = el( 'input', { type: 'text', class: 'regular-text', value: rule.parent_block, placeholder: 'e.g. core/group', onchange: ( e ) => { rule.parent_block = e.target.value.trim(); sync(); } } );
	const after = el( 'input', { type: 'number', class: 'small-text', min: 0, value: rule.after_paragraphs, onchange: ( e ) => { rule.after_paragraphs = parseInt( e.target.value, 10 ) || 0; sync(); } } );

	return el( 'div', { class: 'ace-ad-rule' }, [
		el( 'div', { class: 'ace-ad-rule__head' }, [
			el( 'label', {}, [ 'Slot ', slotSelect ] ),
			el( 'label', {}, [ 'Priority ', priority ] ),
			el( 'button', { type: 'button', class: 'button-link-delete', onclick: () => { rules.splice( index, 1 ); sync(); render(); } }, 'Delete rule' ),
		] ),
		el( 'div', { class: 'ace-ad-rule__targets' }, [
			el( 'p', { class: 'description' }, 'Show when ALL of these match:' ),
			...rule.targets.map( ( t, i ) => targetRow( rule, t, i ) ),
			el( 'button', { type: 'button', class: 'button', onclick: () => { rule.targets.push( { type: 'taxonomy_term', values: [] } ); sync(); render(); } }, '+ Add target' ),
		] ),
		el( 'div', { class: 'ace-ad-rule__extra' }, [
			el( 'label', {}, [ 'Loop item ', loop ] ),
			el( 'label', {}, [ 'Inside block ', parent ] ),
			rule.slot === 'in-content' ? el( 'label', {}, [ 'After paragraphs ', after ] ) : null,
		] ),
	] );
}

function render() {
	root.replaceChildren(
		...rules.map( ruleCard ),
		el( 'p', {}, el( 'button', { type: 'button', class: 'button button-secondary', onclick: () => { rules.push( newRule() ); sync(); render(); } }, '+ Add rule' ) )
	);
}

document.addEventListener( 'DOMContentLoaded', () => {
	root = document.getElementById( 'ace-ad-rules-app' );
	jsonField = document.getElementById( 'ace-ad-rules-json' );
	if ( ! root || ! jsonField ) return;
	try {
		rules = JSON.parse( jsonField.value || '[]' );
	} catch ( e ) {
		rules = [];
	}
	render();
	document.querySelector( '.ace-ad-rules-toggle-json' )?.addEventListener( 'click', ( e ) => {
		e.preventDefault();
		jsonField.hidden = ! jsonField.hidden;
		if ( ! jsonField.hidden ) {
			jsonField.addEventListener( 'change', () => { try { rules = JSON.parse( jsonField.value ); render(); } catch ( err ) { window.alert( 'Invalid JSON' ); } } );
		}
	} );
} );
