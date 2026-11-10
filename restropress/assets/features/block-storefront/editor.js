/**
 * RestroPress block storefront: editor registration (3.5).
 *
 * Plain JS with wp.* globals, no build step. Metadata (title, attributes)
 * comes from each block.json registered on the server; this file adds
 * icons, inspector controls and server-rendered previews.
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
	var RangeControl = wp.components.RangeControl;
	var TextControl = wp.components.TextControl;
	var FormTokenField = wp.components.FormTokenField;
	var Disabled = wp.components.Disabled;
	var useSelect = wp.data.useSelect;

	/** Category token picker backed by the food-category taxonomy. */
	function CategoryTokens( props ) {
		var terms = useSelect( function ( select ) {
			return select( 'core' ).getEntityRecords( 'taxonomy', 'food-category', { per_page: -1 } );
		}, [] );
		var list = terms || [];
		var current = ( props.value || '' ).split( ',' ).filter( Boolean );
		return el( FormTokenField, {
			label: props.label,
			value: current.map( function ( idOrSlug ) {
				var match = list.find( function ( t ) { return String( t.id ) === idOrSlug || t.slug === idOrSlug; } );
				return match ? match.name : idOrSlug;
			} ),
			suggestions: list.map( function ( t ) { return t.name; } ),
			__experimentalExpandOnFocus: true,
			onChange: function ( tokens ) {
				props.onChange( tokens.map( function ( token ) {
					var match = list.find( function ( t ) { return t.name === token; } );
					return match ? String( match.id ) : token;
				} ).join( ',' ) );
			},
		} );
	}

	function preview( name, attributes ) {
		return el(
			'div',
			{ className: 'rpress-ssr-preview' },
			el( Disabled, {}, el( ServerSideRender, { block: name, attributes: attributes } ) )
		);
	}

	function categoryPanel( props ) {
		var a = props.attributes;
		return [
			el( CategoryTokens, {
				key: 'include',
				label: __( 'Show only these categories', 'restropress' ),
				value: a.categories,
				onChange: function ( v ) { props.setAttributes( { categories: v } ); },
			} ),
			el( CategoryTokens, {
				key: 'exclude',
				label: __( 'Exclude these categories', 'restropress' ),
				value: a.categoryExclude,
				onChange: function ( v ) { props.setAttributes( { categoryExclude: v } ); },
			} ),
		];
	}

	registerBlockType( 'rpress/category-nav', {
		icon: 'menu',
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
						{ title: __( 'Categories', 'restropress' ) },
						categoryPanel( props ),
						el( SelectControl, {
							label: __( 'Orientation', 'restropress' ),
							value: a.orientation,
							options: [
								{ value: 'horizontal', label: __( 'Horizontal bar', 'restropress' ) },
								{ value: 'vertical', label: __( 'Vertical list', 'restropress' ) },
							],
							onChange: function ( v ) { props.setAttributes( { orientation: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Sticky while scrolling', 'restropress' ),
							checked: false !== a.sticky,
							onChange: function ( v ) { props.setAttributes( { sticky: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Filter instead of scroll', 'restropress' ),
							help: __( 'On: a category shows only its items. Off: jumps to the category and highlights it while scrolling.', 'restropress' ),
							checked: !! a.filter,
							onChange: function ( v ) { props.setAttributes( { filter: v } ); },
						} )
					)
				),
				preview( 'rpress/category-nav', a )
			);
		},
		save: function () { return null; },
	} );

	registerBlockType( 'rpress/item-grid', {
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
						{ title: __( 'Items', 'restropress' ) },
						categoryPanel( props ),
						el( SelectControl, {
							label: __( 'Layout', 'restropress' ),
							value: a.layout,
							options: [
								{ value: 'grid', label: __( 'Grid', 'restropress' ) },
								{ value: 'list', label: __( 'List', 'restropress' ) },
							],
							onChange: function ( v ) { props.setAttributes( { layout: v } ); },
						} ),
						'grid' === a.layout && el( RangeControl, {
							label: __( 'Columns', 'restropress' ),
							value: a.columns,
							min: 1,
							max: 4,
							onChange: function ( v ) { props.setAttributes( { columns: v } ); },
						} ),
						el( SelectControl, {
							label: __( 'Order items by', 'restropress' ),
							value: a.orderby,
							options: [
								{ value: 'title', label: __( 'Name', 'restropress' ) },
								{ value: 'date', label: __( 'Newest', 'restropress' ) },
								{ value: 'menu_order', label: __( 'Menu order', 'restropress' ) },
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
						{ title: __( 'Card', 'restropress' ), initialOpen: false },
						el( ToggleControl, {
							label: __( 'Image', 'restropress' ),
							checked: false !== a.showImage,
							onChange: function ( v ) { props.setAttributes( { showImage: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Description', 'restropress' ),
							checked: false !== a.showDescription,
							onChange: function ( v ) { props.setAttributes( { showDescription: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Price', 'restropress' ),
							checked: false !== a.showPrice,
							onChange: function ( v ) { props.setAttributes( { showPrice: v } ); },
						} )
					)
				),
				preview( 'rpress/item-grid', a )
			);
		},
		save: function () { return null; },
	} );

	registerBlockType( 'rpress/cart-panel', {
		icon: 'cart',
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
						{ title: __( 'Cart panel', 'restropress' ) },
						el( TextControl, {
							label: __( 'Title', 'restropress' ),
							help: __( 'Leave empty for "Your Order".', 'restropress' ),
							value: a.title,
							onChange: function ( v ) { props.setAttributes( { title: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Sticky while scrolling (desktop)', 'restropress' ),
							checked: false !== a.sticky,
							onChange: function ( v ) { props.setAttributes( { sticky: v } ); },
						} )
					)
				),
				preview( 'rpress/cart-panel', a )
			);
		},
		save: function () { return null; },
	} );

	registerBlockType( 'rpress/service-bar', {
		icon: 'clock',
		edit: function () {
			return el( 'div', useBlockProps(), preview( 'rpress/service-bar', {} ) );
		},
		save: function () { return null; },
	} );

	registerBlockType( 'rpress/menu-search', {
		icon: 'search',
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
						{ title: __( 'Search', 'restropress' ) },
						el( TextControl, {
							label: __( 'Placeholder', 'restropress' ),
							value: a.placeholder,
							onChange: function ( v ) { props.setAttributes( { placeholder: v } ); },
						} )
					)
				),
				preview( 'rpress/menu-search', a )
			);
		},
		save: function () { return null; },
	} );
}( window.wp ) );
