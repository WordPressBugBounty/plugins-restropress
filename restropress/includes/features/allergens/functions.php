<?php
/**
 * Allergen helpers: lookups, display mode and badge markup.
 *
 * @package RestroPress/Features/Allergens
 * @since 3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Allergens a menu item contains.
 *
 * @since 3.5
 * @param int $fooditem_id Food item ID.
 * @return WP_Term[]
 */
function rpress_get_fooditem_allergens( $fooditem_id ) {
	$fooditem_id = absint( $fooditem_id );
	if ( ! $fooditem_id || ! taxonomy_exists( RPRESS_ALLERGEN_TAXONOMY ) ) {
		return array();
	}
	$terms = get_the_terms( $fooditem_id, RPRESS_ALLERGEN_TAXONOMY );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return array();
	}
	usort(
		$terms,
		function ( $a, $b ) {
			return strcasecmp( $a->name, $b->name );
		}
	);
	return apply_filters( 'rpress_fooditem_allergens', $terms, $fooditem_id );
}

/**
 * Term IDs stored as "may contain traces of" for an item.
 *
 * @since 3.5
 * @param int $fooditem_id Food item ID.
 * @return int[]
 */
function rpress_get_fooditem_allergen_trace_ids( $fooditem_id ) {
	$ids = get_post_meta( absint( $fooditem_id ), RPRESS_ALLERGEN_TRACES_META, true );
	return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
}

/**
 * Allergens an item may contain traces of, minus any it already contains.
 *
 * @since 3.5
 * @param int $fooditem_id Food item ID.
 * @return WP_Term[]
 */
function rpress_get_fooditem_allergen_traces( $fooditem_id ) {
	$ids = rpress_get_fooditem_allergen_trace_ids( $fooditem_id );
	if ( empty( $ids ) || ! taxonomy_exists( RPRESS_ALLERGEN_TAXONOMY ) ) {
		return array();
	}
	$contains = wp_list_pluck( rpress_get_fooditem_allergens( $fooditem_id ), 'term_id' );
	$ids      = array_diff( $ids, array_map( 'intval', $contains ) );
	if ( empty( $ids ) ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => RPRESS_ALLERGEN_TAXONOMY,
			'include'    => $ids,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	$terms = is_wp_error( $terms ) ? array() : $terms;
	return apply_filters( 'rpress_fooditem_allergen_traces', $terms, $fooditem_id );
}

/**
 * Short badge code for an allergen (the badge "icon"). Uses the stored code,
 * falling back to the first two letters of the name.
 *
 * @since 3.5
 * @param WP_Term $term Allergen term.
 * @return string
 */
function rpress_allergen_code( $term ) {
	$code = (string) get_term_meta( $term->term_id, 'rpress_allergen_code', true );
	if ( '' === $code ) {
		$name = trim( wp_strip_all_tags( $term->name ) );
		$code = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 2 ) : substr( $name, 0, 2 );
		$code = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $code ) : strtoupper( $code );
	}
	return apply_filters( 'rpress_allergen_code', $code, $term );
}

/**
 * Shape terms for the REST response.
 *
 * @since 3.5
 * @param WP_Term[] $terms Terms.
 * @return array
 */
function rpress_allergens_for_rest( $terms ) {
	$out = array();
	foreach ( $terms as $term ) {
		$out[] = array(
			'id'   => (int) $term->term_id,
			'slug' => $term->slug,
			'name' => $term->name,
			'code' => rpress_allergen_code( $term ),
		);
	}
	return $out;
}

/**
 * Storefront display mode: 'all' (badges and filter), 'badges' or 'off'.
 *
 * @since 3.5
 * @return string
 */
function rpress_allergens_display_mode() {
	$mode = (string) rpress_get_option( 'allergens_display', 'all' );
	if ( ! in_array( $mode, array( 'all', 'badges', 'off' ), true ) ) {
		$mode = 'all';
	}
	return apply_filters( 'rpress_allergens_display_mode', $mode );
}

/**
 * Badge markup for an item.
 *
 * The card variant shows compact code badges with the full names in
 * screen-reader text and a title tooltip; the popup variant spells the names
 * out. The wrapper carries `data-allergens` / `data-allergen-traces` (slugs)
 * for the storefront filter.
 *
 * @since 3.5
 * @param int    $fooditem_id Food item ID.
 * @param string $context     'card' or 'popup'.
 * @return string HTML, or '' when the item has no allergens.
 */
function rpress_get_allergen_badges_html( $fooditem_id, $context = 'card' ) {
	$contains = rpress_get_fooditem_allergens( $fooditem_id );
	$traces   = rpress_get_fooditem_allergen_traces( $fooditem_id );
	if ( empty( $contains ) && empty( $traces ) ) {
		return '';
	}

	$context        = 'popup' === $context ? 'popup' : 'card';
	$contains_names = wp_list_pluck( $contains, 'name' );
	$traces_names   = wp_list_pluck( $traces, 'name' );

	$summary = array();
	if ( $contains_names ) {
		/* translators: %s: comma-separated allergen names */
		$summary[] = sprintf( __( 'Contains: %s', 'restropress' ), implode( ', ', $contains_names ) );
	}
	if ( $traces_names ) {
		/* translators: %s: comma-separated allergen names */
		$summary[] = sprintf( __( 'May contain traces of: %s', 'restropress' ), implode( ', ', $traces_names ) );
	}
	$summary = implode( '. ', $summary );

	$badge = function ( $term, $is_trace ) use ( $context ) {
		$classes = 'rp-allergen rp-allergen--' . sanitize_html_class( $term->slug ) . ( $is_trace ? ' rp-allergen--trace' : '' );
		if ( 'popup' === $context ) {
			return '<li class="' . esc_attr( $classes ) . '"><span class="rp-allergen__code" aria-hidden="true">' . esc_html( rpress_allergen_code( $term ) ) . '</span><span class="rp-allergen__name">' . esc_html( $term->name ) . '</span></li>';
		}
		$code = '<span class="rp-allergen__code">' . esc_html( rpress_allergen_code( $term ) ) . '</span>';
		return '<span class="' . esc_attr( $classes ) . '" title="' . esc_attr( $term->name ) . '" aria-hidden="true">' . $code . '</span>';
	};

	$html = '<div class="rp-allergens rp-allergens--' . esc_attr( $context ) . '"'
		. ' data-allergens="' . esc_attr( implode( ' ', wp_list_pluck( $contains, 'slug' ) ) ) . '"'
		. ' data-allergen-traces="' . esc_attr( implode( ' ', wp_list_pluck( $traces, 'slug' ) ) ) . '">';

	if ( 'popup' === $context ) {
		if ( $contains ) {
			$html .= '<p class="rp-allergens__label">' . esc_html__( 'Contains', 'restropress' ) . '</p><ul class="rp-allergens__list">';
			foreach ( $contains as $term ) {
				$html .= $badge( $term, false );
			}
			$html .= '</ul>';
		}
		if ( $traces ) {
			$html .= '<p class="rp-allergens__label">' . esc_html__( 'May contain traces of', 'restropress' ) . '</p><ul class="rp-allergens__list">';
			foreach ( $traces as $term ) {
				$html .= $badge( $term, true );
			}
			$html .= '</ul>';
		}
		$html .= '<p class="rp-allergens__note">' . esc_html__( 'Allergen information is provided by the restaurant. If you have a severe allergy, please contact us before ordering.', 'restropress' ) . '</p>';
	} else {
		$html .= '<span class="rp-sr-only">' . esc_html( $summary ) . '</span>';
		foreach ( $contains as $term ) {
			$html .= $badge( $term, false );
		}
		foreach ( $traces as $term ) {
			$html .= $badge( $term, true );
		}
	}

	$html .= '</div>';

	return apply_filters( 'rpress_allergen_badges_html', $html, $fooditem_id, $context, $contains, $traces );
}
