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
	var ServerSideRender = serverSideRender;

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
						} )
					)
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
