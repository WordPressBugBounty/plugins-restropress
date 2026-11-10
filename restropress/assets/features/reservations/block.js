/**
 * RestroPress Table Reservation block (3.5).
 *
 * Server-rendered: the editor shows a disabled preview of [rpress_reservation].
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var ServerSideRender = wp.serverSideRender;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var Disabled = wp.components.Disabled;

	wp.blocks.registerBlockType( 'rpress/reservation-form', {
		icon: 'calendar-alt',
		edit: function ( props ) {
			var a = props.attributes;
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Reservation form', 'restropress' ) },
						el( TextControl, {
							label: __( 'Heading', 'restropress' ),
							help: __( 'Leave empty to hide the heading.', 'restropress' ),
							value: a.title || '',
							onChange: function ( v ) { props.setAttributes( { title: v } ); },
						} )
					)
				),
				el(
					'div',
					{ className: 'rpress-ssr-preview' },
					el( Disabled, {}, el( ServerSideRender, { block: 'rpress/reservation-form', attributes: a } ) )
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp );
