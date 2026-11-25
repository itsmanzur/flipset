/**
 * Flipset Library block — editor registration (no build step).
 *
 * Grid is server-rendered so the editor matches the front-end shortcode.
 */
( function ( blocks, element, blockEditor, components, i18n, serverSideRender ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var RangeControl = components.RangeControl;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;
	var Button = components.Button;
	var MediaUpload = blockEditor.MediaUpload;
	var MediaUploadCheck = blockEditor.MediaUploadCheck;
	var ServerSideRender = serverSideRender;

	/**
	 * "Only these PDFs": pick PDFs from the Media Library. An empty list means every PDF
	 * that is not hidden in Docs & Features.
	 *
	 * @param {Object}   attributes    Block attributes.
	 * @param {Function} setAttributes Attribute setter.
	 * @return {Object} Element.
	 */
	function selectionPanel( attributes, setAttributes ) {
		var ids = Array.isArray( attributes.ids ) ? attributes.ids : [];
		var count = ids.length;

		return el(
			PanelBody,
			{ title: __( 'Which PDFs', 'flipset' ), initialOpen: count > 0 },
			el(
				'p',
				{ className: 'components-base-control__help' },
				count
					? i18n.sprintf(
							/* translators: %d: number of PDFs picked for the library. */
							i18n._n( 'Only %d PDF is listed.', 'Only %d PDFs are listed.', count, 'flipset' ),
							count
					  )
					: __( 'Every PDF in the Media Library is listed, except the ones hidden in Flipset → Docs & Features.', 'flipset' )
			),
			el(
				MediaUploadCheck,
				null,
				el( MediaUpload, {
					title: __( 'Only these PDFs', 'flipset' ),
					allowedTypes: [ 'application/pdf' ],
					multiple: true,
					gallery: false,
					value: ids,
					onSelect: function ( media ) {
						var picked = ( Array.isArray( media ) ? media : [ media ] )
							.map( function ( item ) {
								return parseInt( item && item.id, 10 );
							} )
							.filter( function ( id ) {
								return id > 0;
							} );
						setAttributes( { ids: picked } );
					},
					render: function ( obj ) {
						return el(
							Button,
							{ variant: 'secondary', onClick: obj.open },
							count ? __( 'Change PDFs', 'flipset' ) : __( 'Only these PDFs…', 'flipset' )
						);
					},
				} )
			),
			count
				? el(
						Button,
						{
							variant: 'link',
							isDestructive: true,
							style: { marginLeft: '8px' },
							onClick: function () {
								setAttributes( { ids: [] } );
							},
						},
						__( 'List every PDF', 'flipset' )
				  )
				: null
		);
	}

	registerBlockType( 'flipset/library', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Library Settings', 'flipset' ), initialOpen: true },
						el( RangeControl, {
							label: __( 'Columns', 'flipset' ),
							value: attributes.columns,
							min: 1,
							max: 6,
							onChange: function ( value ) {
								setAttributes( { columns: value } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Documents per page', 'flipset' ),
							value: attributes.per_page,
							min: 1,
							max: 48,
							onChange: function ( value ) {
								setAttributes( { per_page: value } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Order by', 'flipset' ),
							value: attributes.orderby,
							options: [
								{ label: __( 'Date', 'flipset' ), value: 'date' },
								{ label: __( 'Title', 'flipset' ), value: 'title' },
								{ label: __( 'Last modified', 'flipset' ), value: 'modified' },
							],
							onChange: function ( value ) {
								setAttributes( { orderby: value } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Order', 'flipset' ),
							value: attributes.order,
							options: [
								{ label: __( 'Newest first', 'flipset' ), value: 'DESC' },
								{ label: __( 'Oldest first', 'flipset' ), value: 'ASC' },
							],
							onChange: function ( value ) {
								setAttributes( { order: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show search', 'flipset' ),
							checked: false !== attributes.search,
							onChange: function ( value ) {
								setAttributes( { search: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show sort menu', 'flipset' ),
							checked: true === attributes.sort_control,
							onChange: function ( value ) {
								setAttributes( { sort_control: value } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Layout', 'flipset' ),
							value: 'list' === attributes.layout ? 'list' : 'grid',
							options: [
								{ label: __( 'Grid', 'flipset' ), value: 'grid' },
								{ label: __( 'List', 'flipset' ), value: 'list' },
							],
							onChange: function ( value ) {
								setAttributes( { layout: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show grid / list switch', 'flipset' ),
							checked: true === attributes.layout_toggle,
							onChange: function ( value ) {
								setAttributes( { layout_toggle: value } );
							},
						} )
					),
					selectionPanel( attributes, setAttributes )
				),
				el( ServerSideRender, {
					block: 'flipset/library',
					attributes: attributes,
				} )
			);
		},

		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
);
