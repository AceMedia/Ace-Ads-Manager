/**
 * Ad slot block: pick a slot (from settings) and optionally pin one ad.
 * Preview is server-rendered so the editor shows what rules resolve.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ComboboxControl, Notice } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import './editor.scss';

const CFG = window.ace_ads_block || { slots: {} };

function Edit( { attributes, setAttributes, context } ) {
	const { slot, adId } = attributes;
	const blockProps = useBlockProps( { className: 'ace-ad-slot-editor' } );

	const ads = useSelect( ( select ) =>
		select( 'core' ).getEntityRecords( 'postType', 'ace_ad', { per_page: 100, status: [ 'publish', 'draft', 'future' ], _fields: 'id,title,status' } ) || [],
	[] );

	const slotOptions = Object.entries( CFG.slots ).map( ( [ value, label ] ) => ( { value, label } ) );
	if ( ! slotOptions.some( ( o ) => o.value === slot ) ) {
		slotOptions.unshift( { value: slot, label: slot } );
	}
	const adOptions = [
		{ value: 0, label: __( 'Resolve by placement rules', 'ace-ads-manager' ) },
		...ads.map( ( ad ) => ( { value: ad.id, label: `${ ad.title?.rendered || ad.id }${ ad.status !== 'publish' ? ` (${ ad.status })` : '' }` } ) ),
	];

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Placement', 'ace-ads-manager' ) }>
					<SelectControl label={ __( 'Slot', 'ace-ads-manager' ) } value={ slot } options={ slotOptions } onChange={ ( v ) => setAttributes( { slot: v } ) } />
					<ComboboxControl label={ __( 'Ad', 'ace-ads-manager' ) } value={ adId } options={ adOptions } onChange={ ( v ) => setAttributes( { adId: parseInt( v, 10 ) || 0 } ) } />
					{ ! adId && (
						<Notice status="info" isDismissible={ false }>
							{ __( 'Rules on each ad decide what appears here, based on where this block sits (archive, term, post type, loop position, parent block).', 'ace-ads-manager' ) }
						</Notice>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<span className="ace-ad-slot-editor__label">{ __( 'Ad slot', 'ace-ads-manager' ) }: { CFG.slots[ slot ] || slot }</span>
				{ ! context.postId && ! adId ? (
					<p className="ace-ad-slot-editor__empty">{ __( 'Resolved per page on the front end: the rules see the post, archive or term this template renders.', 'ace-ads-manager' ) }</p>
				) : (
					<ServerSideRender block="ace-ads/slot" attributes={ attributes } urlQueryArgs={ { post_id: context.postId } } EmptyResponsePlaceholder={ () => <p className="ace-ad-slot-editor__empty">{ __( 'No ad resolves here right now.', 'ace-ads-manager' ) }</p> } />
				) }
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit, save: () => null } );
