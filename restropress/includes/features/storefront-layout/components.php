<?php
/**
 * Pack component settings (3.5.1).
 *
 * The theme.json idea for template packs: a pack describes how its shared
 * components look as data, and core prints the CSS. Packs register them with
 * the `components` argument of rpress_register_template_pack():
 *
 *     'components' => array(
 *         'card'     => array( 'border' => '1px solid #EDEDED', 'radius' => '12px', 'photo_size' => 108, 'add_button' => 'corner' ),
 *         'tabs'     => array( 'style' => 'underline', 'counts' => false ),
 *         'masthead' => array( 'cover_filter' => 'grayscale', 'meta' => 'text' ),
 *     )
 *
 * Core owns the selectors, so a pack never has to out-rank the menu's legacy
 * rules, and a markup change in core updates every pack at once. A pack's own
 * stylesheet is left for what settings cannot say.
 *
 * Every setting is optional: anything a pack leaves out keeps the look it has
 * today. Settings inherit through `extends` like the layout does.
 *
 * @package RestroPress
 * @since   3.5.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Component settings and their types.
 *
 * Types: 'css' (a CSS value, e.g. "1px solid #eee"), 'px' (a whole number of
 * pixels), 'int', 'bool', or a list of allowed values.
 *
 * @since 3.5.1
 * @return array Component => setting => type.
 */
function rpress_pack_component_schema() {
	return apply_filters(
		'rpress_pack_component_schema',
		array(
			'card'      => array(
				'border'       => 'css',
				'radius'       => 'css',
				'background'   => 'css',
				'padding'      => 'css',
				'shadow'       => 'css',
				'photo_size'   => 'px',
				'photo_radius' => 'css',
				'title_size'   => 'px',
				'title_weight' => 'int',
				'price_weight' => 'int',
				'price_first'  => 'bool',
				'desc_lines'   => 'int',
				'add_button'   => array( 'default', 'corner', 'block', 'ring' ),
				'leader'       => 'bool',
				'photo_side'   => array( 'right', 'left' ),
				'desc_font'    => array( 'body', 'display' ),
				'desc_italic'  => 'bool',
				'stepper'      => array( 'phone', 'always' ),
				'phone'        => array( 'default', 'rows' ),
				'photo_ratio'  => array( '1/1', '4/3', '16/10' ),
				'title_font'   => array( 'body', 'display' ),
				'price_font'   => array( 'body', 'display' ),
				'price_color'  => 'css',
				'price_size'   => 'px',
				'desc'         => array( 'show', 'hide' ),
			),
			'menu'      => array(
				'max_width' => 'px',
			),
			'section'   => array(
				'style' => array( 'default', 'rules' ),
			),
			'tabs'      => array(
				'style'  => array( 'default', 'underline', 'smallcaps' ),
				'counts' => 'bool',
			),
			'rail'      => array(
				'card_width'  => 'px',
				'photo_ratio' => array( '4/3', '16/10', '1/1' ),
				'bare'        => 'bool',
				'plus'        => 'bool',
			),
			'masthead'  => array(
				'cover_height'  => 'px',
				'cover_filter'  => array( 'none', 'grayscale' ),
				'monogram_size' => 'px',
				'name_size'     => 'px',
				'meta'          => array( 'chips', 'text' ),
			),
			'service'   => array(
				'style' => array( 'default', 'segmented' ),
			),
			'sheet'     => array(
				'groups'   => array( 'default', 'cards' ),
				'cta_case' => array( 'default', 'none' ),
			),
			'allergens' => array(
				'style' => array( 'default', 'mono', 'text' ),
			),
		)
	);
}

/**
 * Keep known settings with valid values.
 *
 * @since 3.5.1
 * @param array $components Raw settings.
 * @return array
 */
function rpress_sanitize_pack_components( $components ) {
	$schema = rpress_pack_component_schema();
	$clean  = array();
	foreach ( (array) $components as $component => $settings ) {
		if ( ! isset( $schema[ $component ] ) || ! is_array( $settings ) ) {
			continue;
		}
		foreach ( $settings as $key => $value ) {
			if ( ! isset( $schema[ $component ][ $key ] ) ) {
				continue;
			}
			$type = $schema[ $component ][ $key ];
			if ( 'css' === $type ) {
				// A single CSS value: no way out of the declaration.
				$value = trim( wp_strip_all_tags( (string) $value ) );
				if ( '' === $value || preg_match( '/[;{}<>\\\\]|\/\*/', $value ) || strlen( $value ) > 120 ) {
					continue;
				}
				// Unbalanced brackets or quotes would swallow the rules after it.
				if ( substr_count( $value, '(' ) !== substr_count( $value, ')' ) || 0 !== substr_count( $value, '"' ) % 2 || 0 !== substr_count( $value, "'" ) % 2 ) {
					continue;
				}
			} elseif ( 'px' === $type || 'int' === $type ) {
				if ( ! is_numeric( $value ) ) {
					continue;
				}
				$value = absint( $value );
			} elseif ( 'bool' === $type ) {
				$value = (bool) $value;
			} elseif ( is_array( $type ) ) {
				if ( ! is_scalar( $value ) || ! in_array( (string) $value, $type, true ) ) {
					continue;
				}
				$value = (string) $value;
			} else {
				continue;
			}
			$clean[ $component ][ $key ] = $value;
		}
	}
	return $clean;
}

/**
 * Component settings in effect: each ancestor pack's, then the active pack's.
 *
 * @since 3.5.1
 * @param array|null $pack Pack, default the active one.
 * @return array
 */
function rpress_get_pack_components( $pack = null ) {
	$pack = null === $pack ? RPRESS_Template_Packs::get_active_pack() : $pack;
	if ( empty( $pack ) ) {
		return array();
	}
	$merged = array();
	foreach ( array_reverse( RPRESS_Template_Packs::get_lineage( $pack ) ) as $p ) {
		if ( ! empty( $p['components'] ) ) {
			foreach ( $p['components'] as $component => $settings ) {
				$merged[ $component ] = array_merge( isset( $merged[ $component ] ) ? $merged[ $component ] : array(), $settings );
			}
		}
	}
	/**
	 * Filter the component settings in effect.
	 *
	 * @since 3.5.1
	 * @param array $merged Component => settings.
	 * @param array $pack   Active pack.
	 */
	return rpress_sanitize_pack_components( apply_filters( 'rpress_pack_components', $merged, $pack ) );
}

/**
 * CSS for the component settings in effect.
 *
 * Selectors are strong on purpose: the menu's legacy rules use high
 * specificity and !important, and this is the one place that has to beat them.
 *
 * @since 3.5.1
 * @param array  $c     Component settings.
 * @param string $scope Body selector, e.g. "body.rp-pack-onyx".
 * @return string
 */
function rpress_pack_components_css( $c, $scope ) {
	$css   = array();
	$menu  = $scope . ' .rpress-menu-v2';
	$list  = $menu . ' .rpress_fooditems_list';
	$card  = $list . ' .rp-card.rp-card';
	$modal = $scope . ' #rpressModal';
	$get   = static function ( $component, $key, $default = null ) use ( $c ) {
		return isset( $c[ $component ][ $key ] ) ? $c[ $component ][ $key ] : $default;
	};
	$rule  = static function ( $selector, $decls ) use ( &$css ) {
		$decls = array_filter( $decls, static function ( $v ) {
			return null !== $v && '' !== $v;
		} );
		if ( $decls ) {
			$body = '';
			foreach ( $decls as $prop => $value ) {
				$body .= $prop . ':' . $value . ' !important;';
			}
			$css[] = $selector . '{' . $body . '}';
		}
	};

	/* Card ---------------------------------------------------------------- */
	$rule( $card, array(
		'border'        => $get( 'card', 'border' ),
		'border-radius' => $get( 'card', 'radius' ),
		'background'    => $get( 'card', 'background' ),
		'padding'       => $get( 'card', 'padding' ),
		'box-shadow'    => $get( 'card', 'shadow' ),
	) );
	// Core draws its card box on the inner wrapper (rpress-menu.css). When a
	// pack styles the card, that box goes so only one shows.
	if ( $get( 'card', 'border' ) || $get( 'card', 'background' ) || $get( 'card', 'padding' ) || $get( 'card', 'radius' ) ) {
		$rule( $card . ' .rpress_fooditem_inner,' . $card . ' .rpress_fooditem_inner:hover', array( 'padding' => '0', 'border' => '0', 'border-radius' => '0', 'background' => 'transparent', 'box-shadow' => 'none' ) );
	}
	if ( $get( 'card', 'shadow' ) ) {
		$rule( $card . ':hover', array( 'box-shadow' => $get( 'card', 'shadow' ), 'transform' => 'none' ) );
	}
	$rule( $card . ' .rp-card__title,' . $card . ' .rp-card__title .food-title', array(
		'font-size'   => $get( 'card', 'title_size' ) ? $get( 'card', 'title_size' ) . 'px' : null,
		'font-weight' => $get( 'card', 'title_weight' ) ? (string) $get( 'card', 'title_weight' ) : null,
		'line-height' => $get( 'card', 'title_size' ) ? '1.3' : null,
	) );
	if ( $get( 'card', 'price_weight' ) ) {
		$rule( $card . ' .rp-card__price,' . $card . ' .rp-card__price *', array( 'font-weight' => (string) $get( 'card', 'price_weight' ) ) );
	}
	if ( $get( 'card', 'price_first' ) ) {
		$rule( $card . ' .rp-card__text', array( 'display' => 'flex', 'flex-direction' => 'column', 'gap' => '6px' ) );
		$css[] = $card . ' .rp-card__text > *{order:4;}';
		$css[] = $card . ' .rp-card__text > .rp-name-row,' . $card . ' .rp-card__text > .rp-card__title{order:1;}';
		$css[] = $card . ' .rp-card__text > .rp-card__price{order:2;margin:0 !important;}';
		$css[] = $card . ' .rp-card__text > .rp-card__desc,' . $card . ' .rp-card__text > .description-tag-wrap{order:3;margin:0 !important;}';
	}
	if ( $get( 'card', 'desc_lines' ) ) {
		$rule( $card . ' .rp-card__desc,' . $card . ' .rp-card__desc p', array(
			'display'            => '-webkit-box',
			'-webkit-line-clamp' => (string) $get( 'card', 'desc_lines' ),
			'-webkit-box-orient' => 'vertical',
			'overflow'           => 'hidden',
		) );
	}
	// Photo size applies to cards with the photo beside the text.
	$size = $get( 'card', 'photo_size' );
	$beside = $scope . ':not(.rp-sf-card-top):not(.rp-sf-card-overlay):not(.rp-sf-card-text)';
	$bcard  = $beside . ' .rpress-menu-v2 .rpress_fooditems_list .rp-card.rp-card';
	if ( $size ) {
		$rule( $bcard . ' .rpress_fooditem_inner', array( 'display' => 'flex', 'align-items' => 'stretch', 'gap' => '16px' ) );
		$rule( $bcard . ' .rp-card__body', array( 'flex' => '1 1 auto', 'width' => 'auto', 'max-width' => 'none', 'min-width' => '0', 'padding' => '0' ) );
		$rule( $bcard . ' .rpress_fooditem_inner > .rp-col-md-4', array( 'flex' => '0 0 ' . $size . 'px', 'width' => $size . 'px', 'max-width' => $size . 'px', 'padding' => '0' ) );
		$rule( $bcard . ' .rp-card__media,' . $bcard . ' .rp-card__photo', array( 'width' => $size . 'px', 'height' => $size . 'px', 'margin' => '0' ) );
		$rule( $bcard . ' .rp-card__photo img', array( 'width' => '100%', 'height' => '100%', 'object-fit' => 'cover', 'margin' => '0', 'border-radius' => '0' ) );
		// Older card markup has no rp-card__photo: size its thumbnail holder.
		$rule( $bcard . ' .rp-card__media .rpress-thumbnail-holder', array( 'width' => $size . 'px', 'height' => $size . 'px', 'margin' => '0', 'padding' => '0' ) );
		$rule( $bcard . ' .rp-card__media .rpress-thumbnail-holder img', array( 'width' => '100%', 'height' => '100%', 'max-height' => 'none', 'object-fit' => 'cover', 'margin' => '0', 'border-radius' => '0' ) );
	}
	if ( $get( 'card', 'photo_radius' ) ) {
		$rule( $card . ' .rp-card__photo,' . $card . ' .rp-card__media .rpress-thumbnail-holder', array( 'border-radius' => $get( 'card', 'photo_radius' ), 'overflow' => 'hidden' ) );
	}
	if ( 'corner' === $get( 'card', 'add_button' ) ) {
		// A round + on the photo's corner (Uber Eats style). The label stays for screen readers.
		$rule( $bcard . ' .rp-card__media', array( 'position' => 'relative' ) );
		$rule( $bcard . ' .rp-card__media .rp-card__add', array( 'position' => 'absolute', 'inset-inline-end' => '-6px', 'inset-inline-start' => 'auto', 'bottom' => '-6px', 'top' => 'auto', 'width' => 'auto', 'margin' => '0', 'transform' => 'none' ) );
		$rule( $card . ' .rp-card__add .rpress-add-to-cart', array(
			'display'         => 'flex',
			'align-items'     => 'center',
			'justify-content' => 'center',
			'width'           => '36px',
			'height'          => '36px',
			'min-width'       => '0',
			'min-height'      => '0',
			'padding'         => '0',
			'border'          => '1px solid var(--rpc-border-subtle, #EDEDED)',
			'border-radius'   => '18px',
			'background'      => 'var(--rpc-panel, #fff)',
			'color'           => 'var(--rpc-ink, #111)',
			'box-shadow'      => '0 1px 4px rgba(0,0,0,.14)',
		) );
		$rule( $card . ' .rp-card__add .rpress-add-to-cart:hover', array( 'background' => 'var(--rpc-surface, #F6F6F6)' ) );
		$rule( $card . ' .rp-card__add .rpress-add-to-cart-label', array( 'position' => 'absolute', 'width' => '1px', 'height' => '1px', 'overflow' => 'hidden', 'clip-path' => 'inset(50%)', 'white-space' => 'nowrap' ) );
		// The + itself is core's masked ::before (rpress-menu.css); the old
		// svg icon stays hidden so only one + shows.
		$rule( $card . ' .rp-card__add .add-icon', array( 'display' => 'none' ) );
		$rule( $card . ' .rp-card__add .rpress-add-to-cart::before', array( 'width' => '14px', 'height' => '14px' ) );
	}
	$rule( $card . ' .rp-card__title,' . $card . ' .rp-card__title .food-title', array(
		'font-family' => $get( 'card', 'title_font' ) ? 'var(--rpc-font-' . $get( 'card', 'title_font' ) . ', inherit)' : null,
	) );
	$rule( $card . ' .rp-card__price,' . $card . ' .rp-card__price *', array(
		'font-family' => $get( 'card', 'price_font' ) ? 'var(--rpc-font-' . $get( 'card', 'price_font' ) . ', inherit)' : null,
		'color'       => $get( 'card', 'price_color' ),
		'font-size'   => $get( 'card', 'price_size' ) ? $get( 'card', 'price_size' ) . 'px' : null,
		'line-height' => $get( 'card', 'price_size' ) ? '1.1' : null,
	) );
	if ( 'hide' === $get( 'card', 'desc' ) ) {
		$rule( $card . ' .rp-card__desc', array( 'display' => 'none' ) );
	}
	// Photo shape for cards with the photo on top.
	$top = $scope . '.rp-sf-card-top .rpress-menu-v2 .rpress_fooditems_list .rp-card.rp-card';
	if ( $get( 'card', 'photo_ratio' ) ) {
		$rule( $top . ' .rp-card__media,' . $top . ' .rp-card__photo', array( 'width' => '100%', 'height' => 'auto', 'aspect-ratio' => str_replace( '/', ' / ', $get( 'card', 'photo_ratio' ) ), 'margin' => '0' ) );
		$rule( $top . ' .rp-card__media .rpress-thumbnail-holder', array( 'width' => '100%', 'height' => '100%', 'margin' => '0', 'padding' => '0' ) );
		$rule( $top . ' .rp-card__media img', array( 'width' => '100%', 'height' => '100%', 'max-height' => 'none', 'object-fit' => 'cover', 'margin' => '0', 'border-radius' => '0' ) );
		if ( $get( 'card', 'photo_radius' ) ) {
			$rule( $top . ' .rp-card__media', array( 'border-radius' => $get( 'card', 'photo_radius' ), 'overflow' => 'hidden' ) );
		}
	}
	if ( 'block' === $get( 'card', 'add_button' ) ) {
		// A full-width "Add" pill along the bottom of the card. It stays in
		// the photo's markup, so it is placed against the card body, which
		// keeps room for it.
		$inner = $card . ' .rpress_fooditem_inner';
		// :hover too, or the hover reset above (padding 0) wins and the
		// button covers the price.
		$rule( $inner . ',' . $inner . ':hover', array( 'position' => 'relative', 'padding-bottom' => '50px' ) );
		$rule( $inner . ' > [class*="rp-col-"],' . $card . ' .rp-card__media', array( 'position' => 'static' ) );
		$rule( $card . ' .rp-card__add', array( 'position' => 'absolute', 'left' => '0', 'right' => '0', 'bottom' => '0', 'top' => 'auto', 'width' => 'auto', 'margin' => '0', 'transform' => 'none' ) );
		$rule( $card . ' .rp-card__add form,' . $card . ' .rp-card__add .rpress_purchase_submit_wrapper', array( 'display' => 'block', 'width' => '100%', 'margin' => '0' ) );
		$rule( $card . ' .rp-card__add .rpress-add-to-cart', array(
			'display'         => 'flex',
			'align-items'     => 'center',
			'justify-content' => 'center',
			'width'           => '100%',
			'height'          => 'auto',
			'min-height'      => '40px',
			'padding'         => '0 16px',
			'border'          => '0',
			'border-radius'   => '999px',
			'background'      => 'var(--rpc-addbtn-bg, var(--rpc-primary-press, var(--rpc-primary)))',
			'color'           => 'var(--rpc-addbtn-color, var(--rpc-on-primary, #fff))',
			'font-size'       => '14px',
			'font-weight'     => '800',
			'line-height'     => '1',
			'text-transform'  => 'none',
			'box-shadow'      => 'none',
		) );
		$rule( $card . ' .rp-card__add .rpress-add-to-cart-label', array( 'display' => 'inline', 'position' => 'static', 'width' => 'auto', 'height' => 'auto', 'overflow' => 'visible', 'clip-path' => 'none', 'font-size' => '14px', 'color' => 'inherit', 'text-transform' => 'none', 'letter-spacing' => '0' ) );
		$rule( $card . ' .rp-card__add .add-icon', array( 'display' => 'none' ) );
		$css[] = $card . ' .rp-card__add .rpress-add-to-cart::before{content:none !important;display:none !important;}';
		$rule( $card . ' .rp-card__add .rpress-qty-stepper.rp-mo-stepper', array( 'width' => '100%', 'min-width' => '0', 'box-sizing' => 'border-box' ) );
	}
	if ( 'always' === $get( 'card', 'stepper' ) ) {
		// The in-card stepper on desktop too (core shows it on phones only).
		$css[] = $menu . ' .rpress-qty-stepper.rp-mo-stepper{display:inline-flex !important;}';
		$css[] = $menu . ' .rpress_fooditem.rp-mo-has-stepper .rpress_purchase_submit_wrapper > .rpress-add-to-cart,' . $menu . ' .rpress_fooditem.rp-mo-has-stepper .rp-mo-incart,' . $card . '.rp-mo-has-stepper .rp-card__add .rpress-add-to-cart{display:none !important;}';
	}
	$rule( $card . ' .rp-card__desc,' . $card . ' .rp-card__desc p', array(
		'font-family' => $get( 'card', 'desc_font' ) ? 'var(--rpc-font-' . $get( 'card', 'desc_font' ) . ', inherit)' : null,
		'font-style'  => $get( 'card', 'desc_italic' ) ? 'italic' : null,
	) );
	if ( $get( 'card', 'leader' ) ) {
		// Menu-card row: name, a dotted leader, then the price on one line;
		// description and allergens underneath.
		$text = $card . ' .rp-card__text';
		$rule( $text, array( 'display' => 'grid', 'grid-template-columns' => 'minmax(0, max-content) minmax(24px, 1fr) auto', 'column-gap' => '10px', 'row-gap' => '5px', 'align-items' => 'end' ) );
		$css[] = $text . ' > *{grid-column:1 / -1;order:0 !important;margin:0 !important;}';
		$css[] = $text . '::before{content:"";grid-column:2;grid-row:1;align-self:end;margin-bottom:7px;border-bottom:1px dotted var(--rpc-border-strong, #999);}';
		$css[] = $text . ' > .rp-name-row,' . $text . ' > .rp-card__title{grid-column:1 !important;grid-row:1;min-width:0;}';
		$css[] = $text . ' > .rp-card__price{grid-column:3 !important;grid-row:1;white-space:nowrap;font-variant-numeric:tabular-nums;}';
		// Phones: the name gets the width, the price goes under it, no leader.
		$css[] = '@media (max-width: 767px){' . $text . '{grid-template-columns:minmax(0, 1fr) !important;}' . $text . '::before{display:none;}' . $text . ' > .rp-card__price{grid-column:1 !important;grid-row:auto;order:-1 !important;}' . $text . ' > .rp-name-row,' . $text . ' > .rp-card__title{grid-row:auto;order:-2 !important;}}';
	}
	if ( 'left' === $get( 'card', 'photo_side' ) ) {
		$rule( $bcard . ' .rpress_fooditem_inner > .rp-col-md-4', array( 'order' => '-1' ) );
	}
	if ( 'ring' === $get( 'card', 'add_button' ) ) {
		// A round outlined + at the row's top right, whether or not the row
		// has a photo; fills on hover. Label kept for screen readers.
		// :hover too, or the hover reset above (padding 0) wins and the ring
		// covers the price.
		$rule( $card . ' .rpress_fooditem_inner,' . $card . ' .rpress_fooditem_inner:hover', array( 'position' => 'relative', 'padding-inline-end' => '52px' ) );
		$rule( $card . ' .rpress_fooditem_inner > [class*="rp-col-"],' . $card . ' .rp-card__media', array( 'position' => 'static' ) );
		// Doubled class: core's no-photo rule pins the button in place at 0,7,1.
		$rule( $card . ' .rp-card__add.rp-card__add.rp-card__add', array( 'position' => 'absolute', 'inset-inline-end' => '0', 'inset-inline-start' => 'auto', 'top' => '0', 'bottom' => 'auto', 'width' => 'auto', 'margin' => '0', 'transform' => 'none' ) );
		// No photo (store-wide or for this dish): the photo column only holds
		// the button, so it leaves the row instead of keeping its width.
		$nocol = array( 'position' => 'absolute', 'inset-inline-end' => '0', 'inset-inline-start' => 'auto', 'top' => '0', 'width' => 'auto', 'max-width' => 'none', 'flex' => 'none' );
		$rule( $scope . '.rp-sf-noimg .rpress-menu-v2 .rpress_fooditems_list .rp-card.rp-card .rpress_fooditem_inner.rpress_fooditem_inner > .rp-col-md-4.rp-col-md-4,' . $card . ' .rpress_fooditem_inner.rp-no-img > .rp-col-md-4.rp-col-md-4', $nocol );
		// The round + stays on phones too; a count shows what is in the cart.
		$css[] = $card . ' .rp-card__add .rpress-qty-stepper.rp-mo-stepper{display:none !important;}';
		$css[] = $card . '.rp-mo-has-stepper .rp-card__add .rpress-add-to-cart{display:flex !important;}';
		$css[] = $card . ' .rp-card__add .rp-mo-incart{display:block !important;}';
		$rule( $card . ' .rp-card__add .rpress-add-to-cart', array(
			'display'         => 'flex',
			'align-items'     => 'center',
			'justify-content' => 'center',
			'width'           => '36px',
			'height'          => '36px',
			'min-width'       => '0',
			'min-height'      => '0',
			'padding'         => '0',
			'border'          => '1px solid var(--rpc-accent, var(--rpc-primary))',
			'border-radius'   => '18px',
			'background'      => 'transparent',
			'color'           => 'var(--rpc-accent, var(--rpc-primary))',
			'box-shadow'      => 'none',
		) );
		$rule( $card . ' .rp-card__add .rpress-add-to-cart:hover,' . $card . ' .rp-card__add .rpress-add-to-cart:focus-visible', array( 'background' => 'var(--rpc-accent, var(--rpc-primary))', 'color' => 'var(--rpc-accent-ink, var(--rpc-on-primary, #fff))' ) );
		$rule( $card . ' .rp-card__add .rpress-add-to-cart-label', array( 'position' => 'absolute', 'width' => '1px', 'height' => '1px', 'overflow' => 'hidden', 'clip-path' => 'inset(50%)', 'white-space' => 'nowrap' ) );
		$rule( $card . ' .rp-card__add .add-icon', array( 'display' => 'none' ) );
		$rule( $card . ' .rp-card__add .rpress-add-to-cart::before', array( 'width' => '14px', 'height' => '14px' ) );
	}
	if ( $get( 'menu', 'max_width' ) ) {
		$rule( $list, array( 'max-width' => $get( 'menu', 'max_width' ) . 'px', 'margin-left' => 'auto', 'margin-right' => 'auto', 'float' => 'none' ) );
	}

	/* Section headings ------------------------------------------------------ */
	if ( 'rules' === $get( 'section', 'style' ) ) {
		// Centred small-caps title between two hairlines, note in italics.
		$h = $menu . ' .rp-menu-section__title';
		$rule( $h, array( 'display' => 'flex', 'align-items' => 'center', 'gap' => '18px', 'width' => '100%', 'margin' => '0 0 8px', 'font-family' => 'var(--rpc-font-display, inherit)', 'font-size' => '22px', 'font-weight' => '500', 'letter-spacing' => '0.16em', 'text-transform' => 'uppercase', 'text-align' => 'center', 'color' => 'var(--rpc-accent, var(--rpc-primary))' ) );
		$css[] = $h . '::before,' . $h . '::after{content:"";flex:1;height:1px;background:var(--rpc-border-strong, #ccc);}';
		$rule( $menu . ' .rp-menu-section__desc,' . $menu . ' .rp-menu-section__desc p', array( 'text-align' => 'center', 'font-family' => 'var(--rpc-font-display, inherit)', 'font-style' => 'italic', 'color' => 'var(--rpc-muted, #666)' ) );
	}
	if ( 'rows' === $get( 'card', 'phone' ) ) {
		$phone  = array();
		$phone[] = $card . '{padding:14px 0 !important;border:0 !important;border-top:1px solid var(--rpc-border-subtle, #EDEDED) !important;border-radius:0 !important;background:transparent !important;box-shadow:none !important;}';
		if ( $size ) {
			$small   = max( 64, (int) round( $size * 0.89 ) );
			$phone[] = $bcard . ' .rpress_fooditem_inner > .rp-col-md-4{flex-basis:' . $small . 'px !important;width:' . $small . 'px !important;max-width:' . $small . 'px !important;}';
			$phone[] = $bcard . ' .rp-card__media,' . $bcard . ' .rp-card__photo{width:' . $small . 'px !important;height:' . $small . 'px !important;}';
		}
		$css[] = '@media (max-width: 767px){' . implode( '', $phone ) . '}';
	}

	/* Section headings ----------------------------------------------------- */
	// (headings follow the pack's display font via tokens; nothing to set yet)

	/* Tabs ----------------------------------------------------------------- */
	$tab = $menu . ' .rp-menu-nav .pn-ProductNav .pn-ProductNav_Link';
	if ( 'underline' === $get( 'tabs', 'style' ) ) {
		$rule( $menu . ' .rp-menu-nav.rp-menu-nav', array(
			'border-top'    => '1px solid var(--rpc-border-subtle, #EDEDED)',
			'border-bottom' => '1px solid var(--rpc-border-subtle, #EDEDED)',
			'background'    => 'var(--rpc-page, #fff)',
			'box-shadow'    => 'none',
		) );
		$rule( $tab . ',' . $tab . '.mnuactive', array(
			'min-height'    => '48px',
			'margin'        => '0',
			'margin-inline-end' => '28px',
			'padding'       => '16px 0 13px',
			'border'        => '0',
			'border-bottom' => '3px solid transparent',
			'border-radius' => '0',
			'background'    => 'transparent',
			'box-shadow'    => 'none',
			'color'         => 'var(--rpc-muted, #666)',
			'font-weight'   => '700',
			'font-size'     => '14px',
			'line-height'   => '1',
		) );
		$rule( $tab . ':hover', array( 'color' => 'var(--rpc-ink, #111)' ) );
		$rule( $tab . '.mnuactive', array( 'border-bottom-color' => 'var(--rpc-ink, #111)', 'color' => 'var(--rpc-ink, #111)' ) );
		$rule( $menu . ' .rp-menu-nav #pnIndicator,' . $menu . ' .rp-menu-nav .pn-ProductNav_Indicator', array( 'display' => 'none' ) );
		$css[] = '@media (max-width: 767px){' . $tab . ',' . $tab . '.mnuactive{margin-inline-end:22px !important;padding:14px 0 11px !important;min-height:46px !important;}}';
	}
	if ( 'smallcaps' === $get( 'tabs', 'style' ) ) {
		// Course links: small caps, centred, a dot under the current one.
		$rule( $menu . ' .rp-menu-nav.rp-menu-nav', array( 'border-bottom' => '1px solid var(--rpc-border-subtle, #EDEDED)', 'background' => 'var(--rpc-page, #fff)', 'box-shadow' => 'none' ) );
		$rule( $tab . ',' . $tab . '.mnuactive', array(
			'position'       => 'relative',
			'min-height'     => '48px',
			'margin'         => '0',
			'margin-inline-end' => '30px',
			'padding'        => '16px 0 14px',
			'border'         => '0',
			'border-radius'  => '0',
			'background'     => 'transparent',
			'box-shadow'     => 'none',
			'color'          => 'var(--rpc-muted, #666)',
			'font-size'      => '13px',
			'font-weight'    => '600',
			'letter-spacing' => '0.18em',
			'text-transform' => 'uppercase',
			'line-height'    => '1',
		) );
		$rule( $tab . ':hover,' . $tab . '.mnuactive', array( 'color' => 'var(--rpc-ink, #111)' ) );
		$css[] = $tab . '.mnuactive::after{content:"";position:absolute;left:50%;bottom:4px;width:5px;height:5px;margin-left:-2.5px;border-radius:3px;background:var(--rpc-accent, var(--rpc-primary));}';
		$rule( $menu . ' .rp-menu-nav #pnIndicator,' . $menu . ' .rp-menu-nav .pn-ProductNav_Indicator', array( 'display' => 'none' ) );
	}
	if ( false === $get( 'tabs', 'counts' ) ) {
		$rule( $menu . ' .rp-menu-nav .pn-ProductNav_Count', array( 'display' => 'none' ) );
	}

	/* Rail ------------------------------------------------------------------ */
	if ( $get( 'rail', 'card_width' ) ) {
		$w = $get( 'rail', 'card_width' );
		$rule( $menu . ' .rp-sf-rail-track', array( 'grid-auto-columns' => $w . 'px' ) );
		$css[] = '@media (max-width: 767px){' . $menu . ' .rp-sf-rail-track{grid-auto-columns:' . max( 120, (int) round( $w * 0.74 ) ) . 'px !important;}}';
	}
	if ( $get( 'rail', 'photo_ratio' ) ) {
		$rule( $menu . ' .rp-sf-rail-img', array( 'aspect-ratio' => str_replace( '/', ' / ', $get( 'rail', 'photo_ratio' ) ) ) );
	}
	if ( $get( 'rail', 'bare' ) ) {
		$rule( $menu . ' .rp-sf-rail-card', array( 'gap' => '8px', 'padding' => '0', 'border' => '0', 'border-radius' => '0', 'background' => 'transparent', 'box-shadow' => 'none' ) );
		$rule( $menu . ' .rp-sf-rail-img', array( 'border-radius' => '10px', 'overflow' => 'hidden' ) );
		$rule( $menu . ' .rp-sf-rail-name,' . $menu . ' .rp-sf-rail-price', array( 'padding' => '0' ) );
	}
	if ( $get( 'rail', 'plus' ) ) {
		// Decorative: the whole rail card is the button that opens the dish.
		$rule( $menu . ' .rp-sf-rail-img', array( 'position' => 'relative' ) );
		$css[] = $menu . ' .rp-sf-rail-img::after{content:"+";position:absolute;right:8px;bottom:8px;width:36px;height:36px;border-radius:18px;background:var(--rpc-panel, #fff);color:var(--rpc-ink, #111);font:500 24px/36px var(--rpc-font-body, sans-serif);text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.18);}';
	}

	/* Masthead -------------------------------------------------------------- */
	$mh = $menu . ' .rp-sf-masthead';
	if ( $get( 'masthead', 'cover_height' ) ) {
		$h = $get( 'masthead', 'cover_height' );
		$rule( $mh . '--cover .rp-sf-cover', array( 'height' => $h . 'px' ) );
		$rule( $mh . '--cover.no-cover .rp-sf-cover', array( 'height' => max( 80, (int) round( $h * 0.46 ) ) . 'px' ) );
		$css[] = '@media (max-width: 767px){' . $mh . '--cover .rp-sf-cover{height:' . max( 96, (int) round( $h * 0.58 ) ) . 'px !important;}' . $mh . '--cover.no-cover .rp-sf-cover{height:' . max( 64, (int) round( $h * 0.34 ) ) . 'px !important;}}';
	}
	if ( 'grayscale' === $get( 'masthead', 'cover_filter' ) ) {
		$rule( $mh . '--cover:not(.no-cover) .rp-sf-cover', array( 'filter' => 'grayscale(1) contrast(1.05) brightness(.92)' ) );
	}
	if ( $get( 'masthead', 'monogram_size' ) ) {
		$m = $get( 'masthead', 'monogram_size' );
		$rule( $mh . '--cover .rp-sf-monogram', array( 'width' => $m . 'px', 'height' => $m . 'px', 'margin-top' => '-' . (int) round( $m / 2 ) . 'px' ) );
		$sm = max( 44, (int) round( $m * 0.78 ) );
		$css[] = '@media (max-width: 767px){' . $mh . '--cover .rp-sf-monogram{width:' . $sm . 'px !important;height:' . $sm . 'px !important;margin-top:-' . (int) round( $sm / 2 ) . 'px !important;}}';
	}
	if ( $get( 'masthead', 'name_size' ) ) {
		$n = $get( 'masthead', 'name_size' );
		$rule( $mh . ' .rp-mo-store-name', array( 'font-size' => $n . 'px', 'line-height' => '1.05', 'letter-spacing' => '-0.02em' ) );
		$css[] = '@media (max-width: 767px){' . $mh . ' .rp-mo-store-name{font-size:' . max( 22, (int) round( $n * 0.7 ) ) . 'px !important;}}';
	}
	if ( 'text' === $get( 'masthead', 'meta' ) ) {
		// One quiet line: open status, then the facts, no chip boxes.
		$rule( $mh . ' .rp-mo-store', array( 'display' => 'flex', 'flex-wrap' => 'wrap', 'align-items' => 'center', 'gap' => '8px 16px', 'padding' => '0', 'border' => '0', 'border-radius' => '0', 'background' => 'transparent', 'box-shadow' => 'none' ) );
		$css[] = $mh . ' .rp-mo-store-head,' . $mh . ' .rp-mo-store-chips{display:contents !important;}';
		$rule( $mh . ' .rp-mo-store-name', array( 'flex' => '1 0 100%', 'margin' => '0 0 4px' ) );
		$rule( $mh . ' .rp-mo-store-status', array( 'padding' => '0', 'border' => '0', 'background' => 'transparent', 'font-size' => '14px', 'font-weight' => '600', 'color' => 'var(--rpc-ink, #111)' ) );
		$rule( $mh . ' .rp-mo-store-chips li', array( 'margin' => '0', 'padding' => '0', 'border' => '0', 'border-radius' => '0', 'background' => 'transparent', 'font-size' => '14px', 'color' => 'var(--rpc-muted, #666)' ) );
		$rule( $mh . ' .rp-mo-closed', array( 'flex-basis' => '100%' ) );
	}

	/* Service row ----------------------------------------------------------- */
	if ( 'segmented' === $get( 'service', 'style' ) ) {
		$row = $menu . ' .rp-service-row';
		$rule( $row . ' #rpressdeliveryTab', array( 'display' => 'inline-flex', 'gap' => '0', 'margin' => '0', 'padding' => '4px', 'border' => '0', 'border-radius' => '999px', 'background' => 'var(--rpc-surface, #F6F6F6)' ) );
		$rule( $row . ' #rpressdeliveryTab .nav-item', array( 'margin' => '0', 'border' => '0', 'background' => 'transparent' ) );
		$rule( $row . ' #rpressdeliveryTab .nav-link', array( 'display' => 'flex', 'align-items' => 'center', 'min-height' => '40px', 'padding' => '0 18px', 'border' => '0', 'border-radius' => '999px', 'background' => 'transparent', 'color' => 'var(--rpc-body, #333)', 'font-weight' => '600', 'box-shadow' => 'none' ) );
		$rule( $row . ' #rpressdeliveryTab .nav-link.active', array( 'background' => 'var(--rpc-ink, #111)', 'color' => 'var(--rpc-panel, #fff)', 'font-weight' => '700' ) );
		$rule( $row . ' .rpress-order-address-inline', array( 'display' => 'inline-flex', 'align-items' => 'center', 'gap' => '8px', 'min-height' => '44px', 'margin' => '0', 'padding' => '0 16px', 'border' => '1px solid var(--rpc-border-strong, #D9D9D9)', 'border-radius' => '999px', 'background' => 'var(--rpc-panel, #fff)', 'max-width' => '100%' ) );
		$rule( $row . ' .left-brdr', array( 'display' => 'none' ) );
		$rule( $row . ' .rpress-search-input-wrap input', array( 'min-height' => '44px', 'border' => '0', 'border-radius' => '999px', 'background' => 'var(--rpc-surface, #F6F6F6)' ) );
		$rule( $row . ' .rp-allergen-filter__toggle', array( 'min-height' => '44px', 'border-radius' => '999px' ) );
		$css[] = '@media (max-width: 767px){' . $row . ' .rp-service-row__time{flex:0 1 auto !important;min-width:0 !important;}' . $row . ' .rpress-order-address-summary{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}' . $row . ' .rpress-order-address-inline{padding:0 12px !important;font-size:13px !important;}}';
	}

	/* Dish sheet ------------------------------------------------------------ */
	if ( 'cards' === $get( 'sheet', 'groups' ) ) {
		$box = 'border:1px solid var(--rpc-border-subtle, #EDEDED) !important;background:var(--rpc-panel, #fff) !important;';
		$css[] = $modal . ' .addons-wrapper,' . $modal . ' .rp-variable-price-wrapper{margin:0 0 14px !important;' . $box . 'border-radius:12px !important;overflow:hidden !important;}';
		$css[] = $modal . ' h6:has(+ .rp-variable-price-wrapper){display:flex !important;align-items:center;justify-content:space-between;gap:12px;margin:0 !important;padding:14px 16px !important;' . $box . 'border-bottom:0 !important;border-radius:12px 12px 0 0 !important;background:var(--rpc-surface, #F6F6F6) !important;font-size:15px !important;font-weight:700 !important;color:var(--rpc-ink, #111) !important;}';
		$css[] = $modal . ' h6:has(+ .rp-variable-price-wrapper) + .rp-variable-price-wrapper{border-radius:0 0 12px 12px !important;}';
		$css[] = $modal . ' .addons-wrapper > .rpress-addon-category{display:flex !important;align-items:center;justify-content:space-between;gap:12px;margin:0 !important;padding:14px 16px !important;background:var(--rpc-surface, #F6F6F6) !important;font-size:15px !important;font-weight:700 !important;color:var(--rpc-ink, #111) !important;}';
		$css[] = $modal . ' .rp-variable-price-wrapper > .food-item-list,' . $modal . ' .addons-wrapper > .food-item-list{margin:0 !important;padding:13px 16px !important;min-height:48px;box-sizing:border-box;border:0 !important;border-top:1px solid var(--rpc-border-subtle, #EDEDED) !important;}';
		$css[] = $modal . ' .rp-variable-price-wrapper > .food-item-list:first-child{border-top:0 !important;}';
		$css[] = $modal . ' .rp-mo-rule.rp-mo-rule{margin-left:auto !important;padding:4px 9px !important;border:1px solid var(--rpc-border-strong, #D9D9D9) !important;border-radius:999px !important;background:var(--rpc-panel, #fff) !important;color:var(--rpc-body, #333) !important;font-size:12px !important;font-weight:700 !important;text-transform:none !important;letter-spacing:0 !important;}';
		$css[] = $modal . ' .rp-mo-rule.rp-mo-rule.is-required{border-color:var(--rpc-ink, #111) !important;background:var(--rpc-ink, #111) !important;color:var(--rpc-panel, #fff) !important;}';
	}
	if ( 'none' === $get( 'sheet', 'cta_case' ) ) {
		$css[] = $modal . ' .modal__footer .submit-fooditem-button,' . $modal . ' .modal__footer .submit-fooditem-button *{text-transform:none !important;letter-spacing:0 !important;}';
	}

	/* Allergens ------------------------------------------------------------- */
	if ( 'text' === $get( 'allergens', 'style' ) ) {
		// Codes as quiet small caps on the card, no chips.
		$css[] = $menu . ' .rp-allergens--card .rp-allergen__code{width:auto !important;height:auto !important;min-width:0 !important;padding:0 !important;border:0 !important;border-radius:0 !important;background:transparent !important;box-shadow:none !important;color:var(--rpc-muted, #666) !important;font-size:11.5px !important;font-weight:600 !important;letter-spacing:0.12em !important;}';
	}
	if ( 'mono' === $get( 'allergens', 'style' ) ) {
		$css[] = $menu . ' .rp-allergens--card .rp-allergen__code,' . $modal . ' .rp-allergens--popup .rp-allergen__code{border:1px solid var(--rpc-border-strong, #D9D9D9) !important;background:var(--rpc-panel, #fff) !important;color:var(--rpc-body, #333) !important;box-shadow:none !important;}';
		$css[] = $modal . ' .rp-allergens--popup,' . $modal . ' .rp-allergen-box{border:1px solid var(--rpc-border-subtle, #EDEDED) !important;background:var(--rpc-surface, #F6F6F6) !important;color:var(--rpc-body, #333) !important;}';
		$css[] = $modal . ' .rp-allergens--popup .rp-allergen,' . $modal . ' .rp-allergens--popup .rp-allergen__name{border-color:var(--rpc-border-strong, #D9D9D9) !important;color:var(--rpc-body, #333) !important;}';
		$css[] = $modal . ' .rp-allergens--popup .rp-allergens__label{color:var(--rpc-ink, #111) !important;}';
	}

	return implode( "\n", $css );
}

/**
 * Print the active pack's component CSS on menu pages.
 *
 * Its own (empty) handle, enqueued late, so it lands after the menu, the
 * storefront layout and the pack's stylesheet.
 *
 * @since 3.5.1
 * @return void
 */
function rpress_pack_components_enqueue() {
	if ( is_admin() || ! function_exists( 'rpress_storefront_layout_current' ) || ! rpress_storefront_layout_current() ) {
		return;
	}
	$pack = RPRESS_Template_Packs::get_active_pack();
	$c    = rpress_get_pack_components( $pack );
	if ( ! $c || empty( $pack['id'] ) ) {
		return;
	}
	$css = rpress_pack_components_css( $c, 'body.rp-pack-' . sanitize_html_class( $pack['id'] ) );
	if ( '' === $css ) {
		return;
	}
	wp_register_style( 'rpress-pack-components', false, array(), RP_VERSION );
	wp_enqueue_style( 'rpress-pack-components' );
	wp_add_inline_style( 'rpress-pack-components', $css );
}
add_action( 'wp_enqueue_scripts', 'rpress_pack_components_enqueue', 60 );

/**
 * Packs with component settings render the list markup.
 *
 * The card settings, columns and card shapes are built on the list menu's
 * markup; its columns setting already lays dishes out as a grid. The older
 * grid markup would show such a pack half styled, so the storefront uses the
 * list templates while one of these packs is active. Admin screens keep the
 * saved value.
 *
 * @since 3.5.1
 * @param mixed $value Saved "template" option (list or grid).
 * @return mixed
 */
function rpress_pack_components_force_list( $value ) {
	if ( 'grid' !== $value || ( is_admin() && ! wp_doing_ajax() ) || ! class_exists( 'RPRESS_Template_Packs' ) ) {
		return $value;
	}
	// Read once per dish otherwise: remember the answer per active pack.
	static $uses = array();
	$pack = RPRESS_Template_Packs::get_active_pack();
	$id   = isset( $pack['id'] ) ? $pack['id'] : '';
	if ( ! isset( $uses[ $id ] ) || defined( 'RPRESS_TESTS' ) ) {
		$uses[ $id ] = (bool) rpress_get_pack_components( $pack );
	}
	return $uses[ $id ] ? 'list' : $value;
}
add_filter( 'rpress_get_option_template', 'rpress_pack_components_force_list' );
