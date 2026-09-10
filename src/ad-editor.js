/**
 * Ad edit screen (block editor): Offer and Placement rules panels in the
 * document sidebar, saving straight to post meta over REST.
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useState, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	TextControl,
	SelectControl,
	CheckboxControl,
	Button,
	ComboboxControl,
	Notice,
	__experimentalNumberControl as NumberControl,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
	__experimentalText as Text,
	Card,
	CardBody,
	CardHeader,
	CardFooter,
	ExternalLink,
} from '@wordpress/components';

const CFG = window.ace_ads_editor || { slots: {}, target_types: {}, taxonomies: {}, post_types: {} };
const META = {
	code: '_ace_ad_offer_code',
	link: '_ace_ad_link',
	cta: '_ace_ad_cta',
	start: '_ace_ad_start',
	end: '_ace_ad_end',
	rel: '_ace_ad_rel',
	image: '_ace_ad_image_mode',
	rules: '_ace_ad_rules',
};

const TYPES_WITH_VALUES = {
	post_type: 'post_types',
	post_type_archive: 'post_types',
	taxonomy_archive: 'taxonomies',
	taxonomy_term: 'terms',
	post: 'posts',
	author: 'free',
};

const restBase = ( taxonomy ) => ( { category: 'categories', post_tag: 'tags' }[ taxonomy ] || taxonomy );

function useMeta() {
	const [ meta, setMeta ] = useEntityProp( 'postType', 'ace_ad', 'meta' );
	const set = ( key, value ) => setMeta( { ...meta, [ key ]: value } );
	return [ meta || {}, set ];
}

function OfferPanel() {
	const [ meta, set ] = useMeta();
	return (
		<PluginDocumentSettingPanel name="ace-ad-offer" title={ __( 'Offer', 'ace-ads-manager' ) } className="ace-ad-offer-panel">
			<VStack spacing={ 3 }>
				<Text variant="muted">{ __( 'The title is the headline and the content is the copy. An ad is live when published and inside its window.', 'ace-ads-manager' ) } <ExternalLink href={ CFG.guide_url }>{ __( 'Guide', 'ace-ads-manager' ) }</ExternalLink></Text>
				<TextControl label={ __( 'Offer code', 'ace-ads-manager' ) } value={ meta[ META.code ] || '' } onChange={ ( v ) => set( META.code, v ) } __nextHasNoMarginBottom />
				<TextControl label={ __( 'Link', 'ace-ads-manager' ) } type="url" value={ meta[ META.link ] || '' } onChange={ ( v ) => set( META.link, v ) } help={ __( 'No link, no button.', 'ace-ads-manager' ) } __nextHasNoMarginBottom />
				<TextControl label={ __( 'Call to action', 'ace-ads-manager' ) } value={ meta[ META.cta ] || '' } placeholder={ CFG.cta_default } onChange={ ( v ) => set( META.cta, v ) } __nextHasNoMarginBottom />
				<TextControl label={ __( 'Start', 'ace-ads-manager' ) } type="datetime-local" value={ meta[ META.start ] || '' } onChange={ ( v ) => set( META.start, v ) } help={ __( 'Empty starts immediately.', 'ace-ads-manager' ) } __nextHasNoMarginBottom />
				<TextControl label={ __( 'End', 'ace-ads-manager' ) } type="datetime-local" value={ meta[ META.end ] || '' } onChange={ ( v ) => set( META.end, v ) } help={ __( 'Empty runs until unpublished.', 'ace-ads-manager' ) } __nextHasNoMarginBottom />
				<SelectControl
					label={ __( 'Link rel', 'ace-ads-manager' ) }
					value={ meta[ META.rel ] || '' }
					options={ [
						{ value: '', label: __( 'Site default', 'ace-ads-manager' ) },
						{ value: 'nofollow', label: __( 'nofollow sponsored', 'ace-ads-manager' ) },
						{ value: 'follow', label: __( 'Follow (no nofollow)', 'ace-ads-manager' ) },
					] }
					onChange={ ( v ) => set( META.rel, v ) }
					help={ __( 'Paid placements should stay nofollow sponsored.', 'ace-ads-manager' ) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Featured image', 'ace-ads-manager' ) }
					value={ meta[ META.image ] || 'background' }
					options={ [
						{ value: 'background', label: __( 'Background behind the copy', 'ace-ads-manager' ) },
						{ value: 'above', label: __( 'Above the copy', 'ace-ads-manager' ) },
						{ value: 'only', label: __( 'Image only (banner)', 'ace-ads-manager' ) },
						{ value: 'none', label: __( 'Do not show', 'ace-ads-manager' ) },
					] }
					onChange={ ( v ) => set( META.image, v ) }
					__nextHasNoMarginBottom
				/>
			</VStack>
		</PluginDocumentSettingPanel>
	);
}

function TermPicker( { values, onChange } ) {
	const taxonomies = Object.entries( CFG.taxonomies );
	const [ taxonomy, setTaxonomy ] = useState( taxonomies[ 0 ]?.[ 0 ] || 'category' );
	const [ search, setSearch ] = useState( '' );
	const found = useSelect( ( select ) => {
		if ( search.length < 2 ) return [];
		return select( 'core' ).getEntityRecords( 'taxonomy', taxonomy, { search, per_page: 20, _fields: 'id,name' } ) || [];
	}, [ taxonomy, search ] );
	const chosen = useSelect( ( select ) => values.map( ( v ) => {
		const [ tax, id ] = v.split( ':' );
		const record = select( 'core' ).getEntityRecord( 'taxonomy', tax, parseInt( id, 10 ) );
		return { value: v, label: record ? `${ CFG.taxonomies[ tax ] || tax }: ${ record.name }` : v };
	} ), [ values ] );
	return (
		<VStack spacing={ 2 }>
			{ chosen.map( ( c ) => (
				<HStack key={ c.value } justify="space-between">
					<Text>{ c.label }</Text>
					<Button size="small" variant="tertiary" isDestructive onClick={ () => onChange( values.filter( ( v ) => v !== c.value ) ) }>{ __( 'Remove', 'ace-ads-manager' ) }</Button>
				</HStack>
			) ) }
			<VStack spacing={ 1 }>
				<SelectControl label={ __( 'Taxonomy', 'ace-ads-manager' ) } hideLabelFromVision value={ taxonomy } options={ taxonomies.map( ( [ value, label ] ) => ( { value, label } ) ) } onChange={ setTaxonomy } __nextHasNoMarginBottom />
				<ComboboxControl
					label={ __( 'Add term', 'ace-ads-manager' ) }
					hideLabelFromVision
					value={ null }
					options={ found.map( ( t ) => ( { value: `${ taxonomy }:${ t.id }`, label: t.name } ) ) }
					onFilterValueChange={ setSearch }
					onChange={ ( v ) => { if ( v && ! values.includes( v ) ) onChange( [ ...values, v ] ); } }
					placeholder={ __( 'Search…', 'ace-ads-manager' ) }
					__nextHasNoMarginBottom
				/>
			</VStack>
		</VStack>
	);
}

function PostPicker( { values, onChange } ) {
	const [ search, setSearch ] = useState( '' );
	const found = useSelect( ( select ) => {
		if ( search.length < 2 ) return [];
		return select( 'core' ).getEntityRecords( 'postType', 'post', { search, per_page: 20, _fields: 'id,title' } ) || [];
	}, [ search ] );
	return (
		<VStack spacing={ 2 }>
			{ values.map( ( v ) => (
				<HStack key={ v } justify="space-between">
					<Text>#{ v }</Text>
					<Button size="small" variant="tertiary" isDestructive onClick={ () => onChange( values.filter( ( x ) => x !== v ) ) }>{ __( 'Remove', 'ace-ads-manager' ) }</Button>
				</HStack>
			) ) }
			<ComboboxControl
				label={ __( 'Add post', 'ace-ads-manager' ) }
				hideLabelFromVision
				value={ null }
				options={ found.map( ( p ) => ( { value: String( p.id ), label: p.title?.rendered || `#${ p.id }` } ) ) }
				onFilterValueChange={ setSearch }
				onChange={ ( v ) => { if ( v && ! values.includes( v ) ) onChange( [ ...values, v ] ); } }
				placeholder={ __( 'Search posts…', 'ace-ads-manager' ) }
				__nextHasNoMarginBottom
			/>
		</VStack>
	);
}

function Values( { target, onChange } ) {
	const kind = TYPES_WITH_VALUES[ target.type ];
	const toggle = ( v ) => onChange( target.values.includes( v ) ? target.values.filter( ( x ) => x !== v ) : [ ...target.values, v ] );
	if ( kind === 'post_types' || kind === 'taxonomies' ) {
		const source = kind === 'post_types' ? CFG.post_types : CFG.taxonomies;
		return (
			<VStack spacing={ 1 }>
				{ Object.entries( source ).map( ( [ value, label ] ) => (
					<CheckboxControl key={ value } label={ label } checked={ target.values.includes( value ) } onChange={ () => toggle( value ) } __nextHasNoMarginBottom />
				) ) }
			</VStack>
		);
	}
	if ( kind === 'terms' ) return <TermPicker values={ target.values } onChange={ onChange } />;
	if ( kind === 'posts' ) return <PostPicker values={ target.values } onChange={ onChange } />;
	if ( kind === 'free' ) return <TextControl label={ __( 'Author ids (empty = any)', 'ace-ads-manager' ) } value={ target.values.join( ',' ) } onChange={ ( v ) => onChange( v.split( ',' ).map( ( s ) => s.trim() ).filter( Boolean ) ) } __nextHasNoMarginBottom />;
	return null;
}

function RuleCard( { rule, index, update, remove } ) {
	const slots = Object.entries( CFG.slots ).map( ( [ value, label ] ) => ( { value, label } ) );
	const types = Object.entries( CFG.target_types ).map( ( [ value, label ] ) => ( { value, label } ) );
	const setTarget = ( i, patch ) => update( { ...rule, targets: rule.targets.map( ( t, j ) => ( j === i ? { ...t, ...patch } : t ) ) } );
	return (
		<Card size="small">
			<CardHeader>
				<Text weight={ 600 }>{ sprintf( __( 'Rule %d', 'ace-ads-manager' ), index + 1 ) }</Text>
				<Button size="small" variant="tertiary" isDestructive onClick={ remove }>{ __( 'Delete', 'ace-ads-manager' ) }</Button>
			</CardHeader>
			<CardBody>
				<VStack spacing={ 3 }>
					<HStack>
						<SelectControl label={ __( 'Slot', 'ace-ads-manager' ) } value={ rule.slot } options={ slots } onChange={ ( v ) => update( { ...rule, slot: v } ) } __nextHasNoMarginBottom />
						<NumberControl label={ __( 'Priority', 'ace-ads-manager' ) } value={ rule.priority } onChange={ ( v ) => update( { ...rule, priority: parseInt( v, 10 ) || 0 } ) } __nextHasNoMarginBottom />
					</HStack>
					<Text variant="muted">{ __( 'Show when ALL of these match:', 'ace-ads-manager' ) }</Text>
					{ rule.targets.map( ( target, i ) => (
						<VStack key={ i } spacing={ 2 } className="ace-ad-target">
							<HStack>
								<CheckboxControl label={ __( 'Except', 'ace-ads-manager' ) } checked={ !! target.negate } onChange={ ( v ) => setTarget( i, { negate: v } ) } __nextHasNoMarginBottom />
								<SelectControl value={ target.type } options={ types } onChange={ ( v ) => setTarget( i, { type: v, values: [] } ) } __nextHasNoMarginBottom />
								<Button size="small" variant="tertiary" isDestructive onClick={ () => update( { ...rule, targets: rule.targets.length > 1 ? rule.targets.filter( ( _, j ) => j !== i ) : [ { type: 'everywhere', values: [] } ] } ) }>{ __( 'Remove', 'ace-ads-manager' ) }</Button>
							</HStack>
							<Values target={ target } onChange={ ( values ) => setTarget( i, { values } ) } />
						</VStack>
					) ) }
					<Button variant="secondary" size="small" onClick={ () => update( { ...rule, targets: [ ...rule.targets, { type: 'taxonomy_term', values: [] } ] } ) }>{ __( '+ Add target', 'ace-ads-manager' ) }</Button>
					<HStack>
						<TextControl label={ __( 'Loop item', 'ace-ads-manager' ) } value={ rule.loop_index } placeholder="3 or 3n" onChange={ ( v ) => update( { ...rule, loop_index: v.replace( /[^0-9n]/g, '' ) } ) } __nextHasNoMarginBottom />
						<TextControl label={ __( 'Inside block', 'ace-ads-manager' ) } value={ rule.parent_block } placeholder="core/group" onChange={ ( v ) => update( { ...rule, parent_block: v.trim() } ) } __nextHasNoMarginBottom />
					</HStack>
					{ rule.slot === 'in-content' && (
						<NumberControl label={ __( 'After paragraphs (0 = default)', 'ace-ads-manager' ) } min={ 0 } value={ rule.after_paragraphs } onChange={ ( v ) => update( { ...rule, after_paragraphs: parseInt( v, 10 ) || 0 } ) } __nextHasNoMarginBottom />
					) }
				</VStack>
			</CardBody>
		</Card>
	);
}

function RulesPanel() {
	const [ meta, set ] = useMeta();
	const rules = useMemo( () => ( Array.isArray( meta[ META.rules ] ) ? meta[ META.rules ] : [] ), [ meta ] );
	const setRules = ( next ) => set( META.rules, next );
	const newRule = () => ( { slot: Object.keys( CFG.slots )[ 0 ] || 'top', priority: 10, targets: [ { type: 'everywhere', values: [] } ], loop_index: '', parent_block: '', after_paragraphs: 0 } );
	return (
		<PluginDocumentSettingPanel name="ace-ad-rules" title={ __( 'Placement rules', 'ace-ads-manager' ) } className="ace-ad-rules-panel">
			<VStack spacing={ 3 }>
				<Text variant="muted">{ __( 'Each rule places this ad into a slot when every target matches. Highest priority wins, then the more specific rule.', 'ace-ads-manager' ) } <ExternalLink href={ CFG.rules_guide_url }>{ __( 'Guide', 'ace-ads-manager' ) }</ExternalLink></Text>
				{ ! rules.length && <Notice status="warning" isDismissible={ false }>{ __( 'No rules yet: this ad will only show where it is pinned in an Ad block.', 'ace-ads-manager' ) }</Notice> }
				{ rules.map( ( rule, i ) => (
					<RuleCard key={ i } rule={ rule } index={ i } update={ ( r ) => setRules( rules.map( ( x, j ) => ( j === i ? r : x ) ) ) } remove={ () => setRules( rules.filter( ( _, j ) => j !== i ) ) } />
				) ) }
				<Button variant="secondary" onClick={ () => setRules( [ ...rules, newRule() ] ) }>{ __( '+ Add rule', 'ace-ads-manager' ) }</Button>
				{ CFG.overview_url && <ExternalLink href={ CFG.overview_url }>{ __( 'See every placement', 'ace-ads-manager' ) }</ExternalLink> }
			</VStack>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'ace-ads-editor', {
	render: () => (
		<>
			<OfferPanel />
			<RulesPanel />
		</>
	),
} );
