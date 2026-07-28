/**
 * RestroPress block editor registration (3.4).
 *
 * Plain JS on purpose: block metadata (title, attributes, keywords) comes
 * from each block's block.json registered server-side; this file only adds
 * icons, inspector controls, and previews. No build step required.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var ServerSideRender = wp.serverSideRender;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var FormTokenField = wp.components.FormTokenField;
	var Disabled = wp.components.Disabled;
	var useSelect = wp.data.useSelect;

	/**
	 * Static editor placeholder for blocks whose real output depends on
	 * session state (cart contents, login, payment key).
	 */
	function placeholder( title, note ) {
		return el(
			'div',
			{ className: 'rpress-block-placeholder' },
			el( 'span', { className: 'rpress-block-placeholder-icon dashicons dashicons-food' } ),
			el( 'strong', {}, title ),
			el( 'p', {}, note )
		);
	}

	/** Category token picker backed by the food-category taxonomy. */
	function CategoryTokens( props ) {
		var terms = useSelect( function ( select ) {
			return select( 'core' ).getEntityRecords( 'taxonomy', 'food-category', { per_page: -1 } );
		}, [] );
		var names = ( terms || [] ).map( function ( t ) { return t.name; } );
		var bySlugOrId = function ( token ) {
			var match = ( terms || [] ).find( function ( t ) { return t.name === token; } );
			return match ? String( match.id ) : null;
		};
		var current = ( props.value || '' ).split( ',' ).filter( Boolean );
		var currentNames = current.map( function ( idOrSlug ) {
			var match = ( terms || [] ).find( function ( t ) {
				return String( t.id ) === idOrSlug || t.slug === idOrSlug;
			} );
			return match ? match.name : idOrSlug;
		} );
		return el( FormTokenField, {
			label: props.label,
			value: currentNames,
			suggestions: names,
			__experimentalExpandOnFocus: true,
			onChange: function ( tokens ) {
				var ids = tokens.map( function ( token ) {
					return bySlugOrId( token ) || token;
				} );
				props.onChange( ids.join( ',' ) );
			},
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Food Menu                                                           */
	/* ------------------------------------------------------------------ */
	registerBlockType( 'rpress/food-menu', {
		icon: 'food',
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
						{ title: __( 'Menu settings', 'restropress' ) },
						el( CategoryTokens, {
							label: __( 'Show only these categories', 'restropress' ),
							value: a.category,
							onChange: function ( v ) { props.setAttributes( { category: v } ); },
						} ),
						el( CategoryTokens, {
							label: __( 'Exclude these categories', 'restropress' ),
							value: a.categoryExclude,
							onChange: function ( v ) { props.setAttributes( { categoryExclude: v } ); },
						} ),
						el( SelectControl, {
							label: __( 'Layout', 'restropress' ),
							value: a.layout,
							options: [
								{ value: '', label: __( 'Store default', 'restropress' ) },
								{ value: 'list', label: __( 'List', 'restropress' ) },
								{ value: 'grid', label: __( 'Grid', 'restropress' ) },
							],
							onChange: function ( v ) { props.setAttributes( { layout: v } ); },
						} ),
						el( SelectControl, {
							label: __( 'Order items by', 'restropress' ),
							value: a.orderby,
							options: [
								{ value: 'title', label: __( 'Name', 'restropress' ) },
								{ value: 'date', label: __( 'Newest', 'restropress' ) },
								{ value: 'menu_order', label: __( 'Menu order', 'restropress' ) },
								{ value: 'rand', label: __( 'Random', 'restropress' ) },
							],
							onChange: function ( v ) { props.setAttributes( { orderby: v } ); },
						} ),
						el( SelectControl, {
							label: __( 'Direction', 'restropress' ),
							value: a.order,
							options: [
								{ value: 'ASC', label: __( 'Ascending', 'restropress' ) },
								{ value: 'DESC', label: __( 'Descending', 'restropress' ) },
							],
							onChange: function ( v ) { props.setAttributes( { order: v } ); },
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Layout', 'restropress' ), initialOpen: false },
						el( ToggleControl, {
							label: __( 'Category navigation', 'restropress' ),
							checked: false !== a.showCategories,
							onChange: function ( v ) { props.setAttributes( { showCategories: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Delivery/pickup and time bar', 'restropress' ),
							checked: false !== a.showServiceBar,
							onChange: function ( v ) { props.setAttributes( { showServiceBar: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Search box', 'restropress' ),
							checked: false !== a.showSearch,
							onChange: function ( v ) { props.setAttributes( { showSearch: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Cart sidebar', 'restropress' ),
							help: __( 'Turn off for a full-width menu. Customers can still check out from the floating cart or a checkout page.', 'restropress' ),
							checked: false !== a.showCart,
							onChange: function ( v ) { props.setAttributes( { showCart: v } ); },
						} )
					)
				),
				el(
					'div',
					{ className: 'rpress-ssr-preview' },
					el( Disabled, {}, el( ServerSideRender, { block: 'rpress/food-menu', attributes: a } ) ),
					el( 'div', { className: 'rpress-ssr-overlay' } )
				)
			);
		},
		save: function () { return null; },
	} );

	/* ------------------------------------------------------------------ */
	/* Cart & Checkout                                                     */
	/* ------------------------------------------------------------------ */
	registerBlockType( 'rpress/checkout', {
		icon: 'cart',
		edit: function ( props ) {
			var mode = props.attributes.mode;
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Checkout settings', 'restropress' ) },
						el( SelectControl, {
							label: __( 'Show', 'restropress' ),
							value: mode,
							options: [
								{ value: 'checkout', label: __( 'Full checkout (includes cart)', 'restropress' ) },
								{ value: 'cart', label: __( 'Cart only', 'restropress' ) },
							],
							onChange: function ( v ) { props.setAttributes( { mode: v } ); },
						} )
					)
				),
				placeholder(
					'cart' === mode ? __( 'RestroPress Cart', 'restropress' ) : __( 'RestroPress Checkout', 'restropress' ),
					__( 'Shown to customers with their live cart contents. Preview it on the storefront.', 'restropress' )
				)
			);
		},
		save: function () { return null; },
	} );

	/* ------------------------------------------------------------------ */
	/* Order History                                                       */
	/* ------------------------------------------------------------------ */
	registerBlockType( 'rpress/order-history', {
		icon: 'backup',
		edit: function ( props ) {
			var type = props.attributes.historyType;
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'History settings', 'restropress' ) },
						el( SelectControl, {
							label: __( 'Show', 'restropress' ),
							value: type,
							options: [
								{ value: 'orders', label: __( 'Past orders with reorder', 'restropress' ) },
								{ value: 'items', label: __( 'Previously ordered items', 'restropress' ) },
							],
							onChange: function ( v ) { props.setAttributes( { historyType: v } ); },
						} )
					)
				),
				placeholder(
					__( 'Order History', 'restropress' ),
					__( 'Shows the logged-in customer their own orders on the storefront.', 'restropress' )
				)
			);
		},
		save: function () { return null; },
	} );

	/* ------------------------------------------------------------------ */
	/* Order Receipt                                                       */
	/* ------------------------------------------------------------------ */
	registerBlockType( 'rpress/receipt', {
		icon: 'media-text',
		edit: function ( props ) {
			var a = props.attributes;
			var toggles = [
				[ 'showProducts', __( 'Ordered items', 'restropress' ) ],
				[ 'showPrice', __( 'Prices and totals', 'restropress' ) ],
				[ 'showDiscount', __( 'Discounts', 'restropress' ) ],
				[ 'showDate', __( 'Order date', 'restropress' ) ],
				[ 'showNotes', __( 'Order notes', 'restropress' ) ],
				[ 'showPaymentMethod', __( 'Payment method', 'restropress' ) ],
				[ 'showPaymentId', __( 'Order number', 'restropress' ) ],
			];
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Receipt contents', 'restropress' ) },
						toggles.map( function ( pair ) {
							return el( ToggleControl, {
								key: pair[ 0 ],
								label: pair[ 1 ],
								checked: !! a[ pair[ 0 ] ],
								onChange: function ( v ) {
									var change = {};
									change[ pair[ 0 ] ] = v;
									props.setAttributes( change );
								},
							} );
						} )
					)
				),
				placeholder(
					__( 'Order Receipt', 'restropress' ),
					__( 'Fills in with the customer’s order after checkout. Place on the order confirmation page.', 'restropress' )
				)
			);
		},
		save: function () { return null; },
	} );

	/* ------------------------------------------------------------------ */
	/* Food Search                                                         */
	/* ------------------------------------------------------------------ */
	registerBlockType( 'rpress/food-search', {
		icon: 'search',
		edit: function () {
			return el(
				'div',
				useBlockProps(),
				el(
					'div',
					{ className: 'rpress-ssr-preview' },
					el( Disabled, {}, el( ServerSideRender, { block: 'rpress/food-search' } ) ),
					el( 'div', { className: 'rpress-ssr-overlay' } )
				)
			);
		},
		save: function () { return null; },
	} );

	/* ------------------------------------------------------------------ */
	/* Opening Hours                                                       */
	/* ------------------------------------------------------------------ */
	registerBlockType( 'rpress/opening-hours', {
		icon: 'clock',
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
						{ title: __( 'Hours settings', 'restropress' ) },
						el( ToggleControl, {
							label: __( 'Highlight today', 'restropress' ),
							checked: !! a.highlightToday,
							onChange: function ( v ) { props.setAttributes( { highlightToday: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Show closed days', 'restropress' ),
							checked: !! a.showClosedDays,
							onChange: function ( v ) { props.setAttributes( { showClosedDays: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Show upcoming holidays', 'restropress' ),
							checked: !! a.showHolidays,
							onChange: function ( v ) { props.setAttributes( { showHolidays: v } ); },
						} )
					)
				),
				el(
					'div',
					{ className: 'rpress-ssr-preview' },
					el( Disabled, {}, el( ServerSideRender, { block: 'rpress/opening-hours', attributes: a } ) ),
					el( 'div', { className: 'rpress-ssr-overlay' } )
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp );
