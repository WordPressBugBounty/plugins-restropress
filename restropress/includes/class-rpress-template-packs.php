<?php
/**
 * Template Pack registry.
 *
 * A template pack is a skin for the customer-facing RestroPress screens:
 * design tokens + a skin stylesheet, plus optional PHP template overrides
 * for structural differences. Packs are registered on the
 * `rpress_register_template_packs` action; core registers Classic, other
 * plugins (e.g. RestroPress Pro) register theirs through the same API.
 *
 * Spec: restropress-planning/07-Template-Pack-Engineering-Plan.md
 *
 * @package RestroPress/Classes
 * @since 3.4
 */
defined( 'ABSPATH' ) || exit;

final class RPRESS_Template_Packs {

	/**
	 * Registered packs, keyed by id.
	 *
	 * @var array[]
	 */
	private static $packs = array();

	/**
	 * Whether the registration action has fired.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_packs' ), 5 );
		add_filter( 'rpress_template_paths', array( __CLASS__, 'add_pack_template_path' ) );
		add_filter( 'rpress_get_template_part', array( __CLASS__, 'add_pack_template_candidates' ), 10, 3 );
		add_filter( 'rpress_settings_styles', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Settings integration: the pack selector at the top of the Styles tab,
	 * and hiding the Classic-only cosmetic knobs when another pack is
	 * active (a designed skin ships opinionated buttons; those settings
	 * would fight it). Nothing is deleted, so switching back to Classic
	 * restores every saved value.
	 *
	 * @param array $settings Styles tab settings.
	 * @return array
	 */
	public static function register_settings( $settings ) {
		$selector = array(
			'template_pack_header' => array(
				'id'   => 'template_pack_header',
				'name' => '<strong>' . esc_html__( 'Template', 'restropress' ) . '</strong>',
				'desc' => '',
				'type' => 'header',
			),
			'template_pack' => array(
				'id'   => 'template_pack',
				'name' => esc_html__( 'Choose a template pack', 'restropress' ),
				'desc' => '',
				'type' => 'pack_selector',
				'std'  => 'classic',
			),
		);

		// The list|grid layout control ('template') sits at the bottom of the
		// styles fields by default; lift it up so it reads as part of the
		// Template section, directly under the pack selector. It stays a
		// separate control (wired through the resolution pipeline independently
		// of the chosen pack) — the pack picker only picks the pack.
		$layout = array();
		if ( isset( $settings['main']['template'] ) ) {
			$layout['template'] = $settings['main']['template'];
			unset( $settings['main']['template'] );
		}

		$settings['main'] = array_merge( $selector, $layout, $settings['main'] );

		return $settings;
	}

	/**
	 * Fire the registration action. Core's Classic pack registers first so
	 * it is always present as the fallback.
	 */
	public static function register_packs() {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;

		// Classic = the current core look. No dir (core templates ARE its
		// templates), no extra stylesheet, no token overrides. The legacy
		// `template` option (list|grid) is its layout variant.
		rpress_register_template_pack( array(
			'id'          => 'classic',
			'label'       => __( 'Classic', 'restropress' ),
			'description' => __( 'The original RestroPress design: a clean, neutral menu, cart, and checkout that inherit your theme\'s colors and fonts.', 'restropress' ),
			'version'     => RP_VERSION,
			'variants'    => array(
				'list' => __( 'List', 'restropress' ),
				'grid' => __( 'Grid', 'restropress' ),
			),
		) );

		// Modern: the free image-forward pack (design: Modern Pack Menu handoff).
		rpress_register_template_pack( array(
			'id'          => 'modern',
			'label'       => __( 'Modern', 'restropress' ),
			'description' => __( 'A polished, image-forward storefront styled like a food-delivery app, with large food photos, rounded cards, pill category tabs, and a redesigned sidebar cart.', 'restropress' ),
			'version'     => RP_VERSION,
			'dir'         => RP_PLUGIN_DIR . 'templates/packs/modern',
			'stylesheet'  => RP_PLUGIN_DIR . 'assets/css/packs/modern.css',
			'url'         => RP_PLUGIN_URL . 'assets/css/packs',
			'fonts'       => 'https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Nunito+Sans:opsz,wght@6..12,400;6..12,600;6..12,700;6..12,800&display=swap',
			'tokens'      => array(
				// Only the base primary is declared; its shades (-hover, -tint,
				// -tint-border, …) are derived from the merchant's effective
				// primary by enqueue_menu_styles(), so the whole UI follows the
				// colour the merchant picks.
				'primary'             => '#EB4C2A',
				'ink'                 => '#191919',
				'body'                => '#494949',
				'muted'               => '#767676',
				'border-subtle'       => '#EFECEA',
				'border-strong'       => '#DBD7D4',
				'surface'             => '#F7F5F3',
				'veg'                 => '#2E9E4F',
				'nonveg'              => '#D6331F',
				'font-body'           => "'Nunito Sans', sans-serif",
				'font-display'        => "'Nunito Sans', sans-serif",
			),
			'variants'    => array(
				'list' => __( 'List', 'restropress' ),
				'grid' => __( 'Grid', 'restropress' ),
			),
			// What Modern renders today; Pro packs extend Modern and override.
			'layout'      => array(
				'masthead' => 'band',
			),
		) );

		/**
		 * Register template packs. Fires once on init (priority 5).
		 *
		 * @since 3.4
		 */
		do_action( 'rpress_register_template_packs' );

		// Pack-conditional wiring (safe only after registration is complete).
		$active = self::get_active_pack();
		add_filter( 'body_class', array( __CLASS__, 'add_pack_body_class' ) );
		if ( 'modern' === $active['id'] ) {
			add_action( 'rpress_menu_page_top', array( __CLASS__, 'render_modern_store_header' ) );
		}
	}

	/**
	 * Body class for the active pack (including Classic) so skins can scope with
	 * winning specificity (body.rp-pack-modern / body.rp-pack-classic
	 * .rpress-menu-v2 ...) and style elements outside the menu wrapper (modals,
	 * page background).
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public static function add_pack_body_class( $classes ) {
		$pack = self::get_active_pack();
		if ( ! empty( $pack['id'] ) ) {
			$classes[] = 'rp-pack-' . sanitize_html_class( $pack['id'] );
		}
		return $classes;
	}

	/**
	 * Modern pack store header band: logo monogram, store name, open-until
	 * status, and stat chips built only from data core actually has
	 * (prep time, delivery fee, minimum order).
	 */
	public static function render_modern_store_header() {
		$store_name = get_bloginfo( 'name' );

		$always_open = ! empty( rpress_get_option( 'enable_always_open' ) );
		$is_open     = rpress_is_store_open( rpress_get_default_enabled_service() );
		if ( $always_open ) {
			$status = __( 'Open 24 hours', 'restropress' );
		} elseif ( $is_open ) {
			$hours  = rpress_get_store_hours_for_date( rpress_get_wp_now()->format( 'Y-m-d' ) );
			$status = ! empty( $hours['close'] )
				/* translators: %s: closing time */
				? sprintf( __( 'Open until %s', 'restropress' ), $hours['close'] )
				: __( 'Open now', 'restropress' );
		} else {
			$status = __( 'Currently closed', 'restropress' );
		}

		$chips = array();
		$prep  = (int) rpress_get_option( 'prep_time', 0 );
		if ( $prep > 0 ) {
			/* translators: %d: preparation minutes */
			$chips[] = sprintf( __( 'Ready in ~%d min', 'restropress' ), $prep );
		}
		// The core flat fee only applies while it is in charge; a fee extension
		// (Delivery Fee: zone/distance pricing) replaces it and can supply its
		// own chip text through the filter, or '' to show none.
		$delivery_chip = '';
		$flat_fee      = function_exists( 'rpress_flat_delivery_fee_active' ) && rpress_flat_delivery_fee_active()
			? rpress_get_option( 'flat_delivery_fee', '' )
			: '';
		if ( '' !== $flat_fee && is_numeric( $flat_fee ) && (float) $flat_fee > 0 ) {
			/* translators: %s: formatted delivery fee */
			$delivery_chip = sprintf( __( '%s delivery', 'restropress' ), rpress_currency_filter( rpress_format_amount( $flat_fee ) ) );
		}
		/**
		 * Filters the delivery chip text in the store header.
		 *
		 * @since 3.5.1
		 * @param string $delivery_chip Chip text, '' for no chip.
		 * @param string $context       'header' (Modern pack store header) or
		 *                              'store_card' (menu page store card).
		 */
		$delivery_chip = (string) apply_filters( 'rpress_store_header_delivery_chip', $delivery_chip, 'header' );
		if ( '' !== $delivery_chip ) {
			$chips[] = $delivery_chip;
		}
		// Pickup fee from the Extra Fees extension, when it resolves a positive
		// amount (flat fees resolve pre-cart; percentage ones need a cart).
		if ( function_exists( 'rp_get_service_extra_fee_total' ) ) {
			$pickup_fee = (float) rp_get_service_extra_fee_total( 'pickup' );
			if ( $pickup_fee > 0 ) {
				/* translators: %s: formatted pickup fee */
				$chips[] = sprintf( __( '%s pickup', 'restropress' ), rpress_currency_filter( rpress_format_amount( $pickup_fee ) ) );
			}
		}
		if ( ! empty( rpress_get_option( 'allow_minimum_order' ) ) ) {
			$min = rpress_get_option( 'minimum_order_price', '' );
			if ( '' !== $min && is_numeric( $min ) && (float) $min > 0 ) {
				/* translators: %s: formatted minimum order */
				$chips[] = sprintf( __( 'Min %s', 'restropress' ), rpress_currency_filter( rpress_format_amount( $min ) ) );
			}
		}
		?>
		<div class="rp-store-header">
			<div class="rp-store-header-main">
				<span class="rp-store-id">
					<span class="rp-store-name"><?php echo esc_html( $store_name ); ?></span>
					<span class="rp-store-meta <?php echo $is_open || $always_open ? 'is-open' : 'is-closed'; ?>"><?php echo esc_html( $status ); ?></span>
				</span>
			</div>
			<?php if ( $chips ) : ?>
				<div class="rp-store-chips">
					<?php foreach ( $chips as $chip ) : ?>
						<span class="rp-store-chip"><?php echo esc_html( $chip ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Register a pack.
	 *
	 * @param array $args See spec. Required: id, label.
	 * @return bool True on success.
	 */
	public static function register( $args ) {
		$defaults = array(
			'id'          => '',
			'label'       => '',
			'description' => '',
			'version'     => '1.0.0',
			'preview'     => '',      // Selector card image URL.
			'dir'         => '',      // Template override directory (path).
			'url'         => '',      // Asset base URL for the pack.
			'stylesheet'  => '',      // Skin CSS: absolute path or registered handle.
			'tokens'      => array(), // --rpc-* token overrides.
			'fonts'       => '',      // Fonts URL to enqueue.
			'variants'    => array(), // Optional layout variants.
			'pro'         => false,   // Shown locked without entitlement.
			'extends'     => '',      // Parent pack id: its templates fill in what this pack does not override (3.5.1).
			'layout'      => array(), // Recommended storefront layout, see rpress_get_storefront_layout() (3.5.1).
			'components'  => array(), // How shared components look, see rpress_pack_component_schema() (3.5.1).
		);
		$pack = wp_parse_args( $args, $defaults );
		$pack['id'] = sanitize_key( $pack['id'] );

		if ( '' === $pack['id'] || '' === $pack['label'] ) {
			_doing_it_wrong( __METHOD__, 'Template packs require an id and a label.', '3.4' );
			return false;
		}
		if ( $pack['dir'] ) {
			$pack['dir'] = trailingslashit( $pack['dir'] );
		}
		$pack['extends'] = sanitize_key( $pack['extends'] );
		if ( $pack['extends'] === $pack['id'] ) {
			$pack['extends'] = '';
		}
		$pack['layout'] = is_array( $pack['layout'] ) ? self::sanitize_layout( $pack['layout'] ) : array();
		$pack['components'] = ( is_array( $pack['components'] ) && function_exists( 'rpress_sanitize_pack_components' ) ) ? rpress_sanitize_pack_components( $pack['components'] ) : array();

		self::$packs[ $pack['id'] ] = $pack;
		return true;
	}

	/**
	 * A pack and its ancestors, nearest first. Unknown parents and loops stop
	 * the walk, so a missing parent plugin never breaks the storefront.
	 *
	 * @since 3.5.1
	 * @param array $pack Pack.
	 * @return array[]
	 */
	public static function get_lineage( $pack ) {
		$lineage = array();
		$seen    = array();
		while ( ! empty( $pack['id'] ) && ! isset( $seen[ $pack['id'] ] ) && count( $lineage ) < 5 ) {
			$seen[ $pack['id'] ] = true;
			$lineage[]           = $pack;
			$parent              = isset( $pack['extends'] ) ? $pack['extends'] : '';
			if ( '' === $parent || ! isset( self::$packs[ $parent ] ) ) {
				break;
			}
			$pack = self::$packs[ $parent ];
		}
		return $lineage;
	}

	/**
	 * Storefront layout keys and their allowed values. The same keys back the
	 * pack recommendations and (later) the merchant's own layout settings, so
	 * the two never disagree. Spec: restropress-planning/STOREFRONT-CUSTOMIZER-PLAN.md.
	 *
	 * @since 3.5.1
	 * @return array Key => allowed values (array) or 'bool' / 'int'.
	 */
	public static function layout_schema() {
		return apply_filters( 'rpress_storefront_layout_schema', array(
			'masthead'   => array( 'none', 'band', 'cover', 'strip', 'editorial' ),
			'nav_style'  => array( 'topbar', 'sidebar', 'tabs', 'accordion', 'dropdown' ),
			'nav_media'  => array( 'none', 'icons', 'images' ),
			'columns'    => array( 1, 2, 3, 4 ),
			'card_style' => array( 'beside', 'top', 'overlay', 'text' ),
			'show_image' => 'bool',
			'cart_style' => array( 'sidebar', 'drawer' ),
			'item_style' => array( 'modal', 'side_sheet' ),
			'rails'      => array( 'popular', 'order_again', 'deals' ),
			// Order of the parts above the dishes (3.5.1). Missing parts keep
			// their default place; see rpress_menu_section_order().
			'sections'   => array( 'rails', 'nav', 'service' ),
		) );
	}

	/**
	 * Core defaults: today's storefront, so nothing changes until a pack or
	 * the merchant asks for something else.
	 *
	 * @since 3.5.1
	 * @return array
	 */
	public static function layout_defaults() {
		return array(
			'masthead'   => 'none',
			'nav_style'  => 'sidebar',
			'nav_media'  => 'none',
			'columns'    => 1,
			'card_style' => 'beside',
			'show_image' => true,
			'cart_style' => 'sidebar',
			'item_style' => 'modal',
			'rails'      => array(),
			'sections'   => array( 'rails', 'nav', 'service' ),
		);
	}

	/**
	 * Keep only known keys with allowed values.
	 *
	 * @since 3.5.1
	 * @param array $layout Raw layout.
	 * @return array
	 */
	public static function sanitize_layout( $layout ) {
		$schema = self::layout_schema();
		$clean  = array();
		foreach ( (array) $layout as $key => $value ) {
			if ( ! isset( $schema[ $key ] ) ) {
				continue;
			}
			$allowed = $schema[ $key ];
			if ( 'bool' === $allowed ) {
				$clean[ $key ] = (bool) $value;
			} elseif ( in_array( $key, array( 'rails', 'sections' ), true ) ) {
				// Lists keep the given order, known values only, once each.
				$clean[ $key ] = array_values( array_unique( array_intersect( (array) $value, $allowed ) ) );
			} elseif ( is_scalar( $value ) && ! is_bool( $value ) && in_array( (string) $value, array_map( 'strval', $allowed ), true ) ) {
				// Compared as strings: columns arrive as strings from settings.
				$clean[ $key ] = is_int( $allowed[0] ) ? (int) $value : (string) $value;
			}
		}
		return $clean;
	}

	/**
	 * The storefront layout in effect: core defaults, then each ancestor
	 * pack's recommendation, then the active pack's, then the merchant's
	 * saved layout (the customizer writes `storefront_layout`).
	 *
	 * @since 3.5.1
	 * @return array
	 */
	public static function get_storefront_layout() {
		$layout  = self::layout_defaults();
		$lineage = array_reverse( self::get_lineage( self::get_active_pack() ) );
		foreach ( $lineage as $pack ) {
			if ( ! empty( $pack['layout'] ) ) {
				$layout = array_merge( $layout, $pack['layout'] );
			}
		}
		$saved = rpress_get_option( 'storefront_layout', array() );
		if ( is_array( $saved ) && $saved ) {
			$layout = array_merge( $layout, self::sanitize_layout( $saved ) );
		}
		/**
		 * Filter the storefront layout in effect.
		 *
		 * @since 3.5.1
		 * @param array $layout Layout.
		 * @param array $pack   Active pack.
		 */
		return apply_filters( 'rpress_storefront_layout', $layout, self::get_active_pack() );
	}

	/**
	 * All registered packs.
	 *
	 * @return array[]
	 */
	public static function get_packs() {
		return self::$packs;
	}

	/**
	 * The active pack. Unknown/invalid saved ids resolve to Classic so a
	 * deactivated pack plugin can never fatal the storefront.
	 *
	 * @return array
	 */
	public static function get_active_pack() {
		$id = rpress_get_option( 'template_pack', 'classic' );
		// Legacy mode pins the selector to Classic.
		if ( ! empty( rpress_get_option( 'old_ui_ux' ) ) ) {
			$id = 'classic';
		}
		if ( ! isset( self::$packs[ $id ] ) ) {
			$id = 'classic';
		}
		return isset( self::$packs[ $id ] ) ? self::$packs[ $id ] : array( 'id' => 'classic' );
	}

	/**
	 * Insert the active pack's template dir between theme overrides (1/10)
	 * and core templates (100).
	 *
	 * @param array $file_paths Priority-keyed template paths.
	 * @return array
	 */
	public static function add_pack_template_path( $file_paths ) {
		// The pack's own dir first (50), then each parent's (51, 52, ...), so a
		// pack only ships the templates it changes (3.5.1).
		$priority = 50;
		foreach ( self::get_lineage( self::get_active_pack() ) as $pack ) {
			if ( ! empty( $pack['dir'] ) && is_dir( $pack['dir'] ) ) {
				$file_paths[ $priority ] = $pack['dir'];
				$priority++;
			}
		}
		return $file_paths;
	}

	/**
	 * Prepend per-pack candidates so themes can override a single pack file
	 * at theme/restropress/{pack-id}/{template}. Classic keeps today's
	 * paths untouched.
	 *
	 * @param array  $templates Candidate file names, highest priority first.
	 * @param string $slug      Template slug.
	 * @param string $name      Template name.
	 * @return array
	 */
	public static function add_pack_template_candidates( $templates, $slug, $name ) {
		$pack = self::get_active_pack();
		if ( 'classic' === $pack['id'] ) {
			return $templates;
		}
		$prefixed = array();
		foreach ( $templates as $template ) {
			$prefixed[] = $pack['id'] . '/' . ltrim( $template, '/' );
		}
		return array_merge( $prefixed, $templates );
	}
}

/**
 * Public API: register a template pack.
 *
 * Call from the `rpress_register_template_packs` action.
 *
 * @since 3.4
 * @param array $args Pack arguments.
 * @return bool
 */
function rpress_register_template_pack( $args ) {
	return RPRESS_Template_Packs::register( $args );
}

/**
 * All registered template packs.
 *
 * @since 3.4
 * @return array[]
 */
function rpress_get_template_packs() {
	return RPRESS_Template_Packs::get_packs();
}

/**
 * The active template pack (always resolves; falls back to Classic).
 *
 * @since 3.4
 * @return array
 */
function rpress_get_active_template_pack() {
	return RPRESS_Template_Packs::get_active_pack();
}

/**
 * The storefront layout in effect (core defaults, pack recommendation, then
 * the merchant's saved layout). Packs and the customizer share these keys.
 *
 * @since 3.5.1
 * @return array
 */
function rpress_get_storefront_layout() {
	return RPRESS_Template_Packs::get_storefront_layout();
}

/**
 * Settings renderer for the `pack_selector` field type: a card per pack
 * (preview, name, description, Active badge). Pro packs without an
 * entitlement render locked with an upgrade link. The list|grid layout is a
 * separate standalone setting, not part of the pack card.
 *
 * @since 3.4
 * @param array $args Settings field args.
 */
function rpress_pack_selector_callback( $args ) {
	$packs      = rpress_get_template_packs();
	$active     = rpress_get_active_template_pack();
	$legacy_on  = ! empty( rpress_get_option( 'old_ui_ux' ) );
	$name_attr  = 'rpress_settings[' . esc_attr( $args['id'] ) . ']';

	static $css_printed = false;
	if ( ! $css_printed ) {
		$css_printed = true;
		// Orange accent (mockup) rather than WP admin blue, so the Template
		// section reads as its own designed control. The Menu Layout radios
		// (radio_image type) are restyled here into a segmented List|Grid
		// toggle — this <style> only prints on the Styles tab, where `template`
		// is the sole radio_image field.
		echo <<<'CSS'
		<style>
		.rpress-pack-intro { display:flex; gap:14px; align-items:flex-start; max-width:1100px; margin:0 0 20px; padding:16px 18px; background:#fdf1ec; border:1px solid #f3d9cd; border-radius:14px; }
		.rpress-pack-intro-icon { flex:0 0 auto; display:flex; align-items:center; justify-content:center; width:40px; height:40px; border-radius:10px; background:#e0521f; color:#fff; }
		.rpress-pack-intro-icon svg { width:22px; height:22px; display:block; }
		.rpress-pack-intro p { margin:0; font-size:14px; line-height:1.55; color:#5a4640; }
		.rpress-pack-intro strong { color:#2b1b15; font-weight:700; }
		.rpress-pack-legacy-note { margin:0 0 12px; }

		.rpress-pack-cards { display:flex; flex-wrap:wrap; gap:20px; max-width:1100px; }
		.rpress-pack-card { position:relative; flex:1 1 340px; display:flex; align-items:center; gap:16px; padding:16px; border:1.5px solid #e2e4e7; border-radius:14px; background:#fff; cursor:pointer; transition:border-color .15s ease, background .15s ease, box-shadow .15s ease; }
		.rpress-pack-card:hover { border-color:#c9ccd1; }
		/* Selected = live radio state (click feedback); .is-selected is set by JS
		   on load from the checked radio, and :has() is the no-JS fallback. */
		.rpress-pack-card.is-selected,
		.rpress-pack-card:has(.rpress-pack-radio:checked) { border-color:#e0521f; background:#fdf1ec; box-shadow:0 0 0 1px #e0521f; }
		.rpress-pack-card.is-locked { cursor:default; }
		.rpress-pack-card.is-locked:hover { border-color:#e2e4e7; }
		.rpress-pack-radio { position:absolute; opacity:0; pointer-events:none; }

		.rpress-pack-thumb { flex:0 0 auto; width:76px; height:66px; border-radius:12px; overflow:hidden; background:#f1f1f2; }
		.rpress-pack-thumb svg, .rpress-pack-thumb img { display:block; width:100%; height:100%; object-fit:cover; }

		.rpress-pack-card-body { flex:1 1 auto; min-width:0; }
		.rpress-pack-card-title { display:flex; align-items:center; gap:8px; font-weight:700; font-size:16px; color:#1d2327; }
		.rpress-pack-badge { font-size:11px; font-weight:700; line-height:1; padding:4px 9px; border-radius:999px; }
		.rpress-pack-badge.active { background:#fbe0d2; color:#b3400f; }
		.rpress-pack-badge.pro { background:#fcf0e4; color:#996800; }
		.rpress-pack-badge.locked { background:#fcf0f1; color:#8a2424; }
		.rpress-pack-lapsed { max-width:1100px; box-sizing:border-box; margin:0 0 16px !important; }
		.rpress-pack-card-desc { display:block; margin:5px 0 0; font-size:13px; line-height:1.4; color:#646970; }
		.rpress-pack-locked-note { display:block; margin-top:7px; font-size:12px; }

		.rpress-pack-check { flex:0 0 auto; position:relative; align-self:center; width:26px; height:26px; border-radius:50%; border:2px solid #cdd0d4; background:#fff; transition:border-color .15s ease, background .15s ease; }
		.rpress-pack-card.is-selected .rpress-pack-check,
		.rpress-pack-card:has(.rpress-pack-radio:checked) .rpress-pack-check { border-color:#e0521f; background:#e0521f; }
		.rpress-pack-card.is-selected .rpress-pack-check::after,
		.rpress-pack-card:has(.rpress-pack-radio:checked) .rpress-pack-check::after { content:""; position:absolute; left:8px; top:4px; width:5px; height:10px; border:solid #fff; border-width:0 2px 2px 0; transform:rotate(45deg); }
		.rpress-pack-card.is-locked .rpress-pack-check { display:none; }

		/* Menu Layout: segmented List|Grid toggle (restyle the radio_image field). */
		.rpress-radio-image-options { display:inline-flex; gap:4px; padding:4px; background:#eef0f2; border-radius:12px; }
		.rpress-radio-image-label { position:relative; margin:0; cursor:pointer; }
		.rpress-radio-image-label img { display:none; }
		.rpress-radio-image-label input { position:absolute; opacity:0; pointer-events:none; }
		.rpress-radio-image-label > div { margin:0; padding:9px 30px; border-radius:9px; font-weight:600; font-size:14px; line-height:1.2; color:#646970; transition:background .15s ease, color .15s ease, box-shadow .15s ease; }
		.rpress-radio-image-label:hover > div { color:#1d2327; }
		.rpress-radio-image-label:has(input:checked) > div { background:#fff; color:#1d2327; box-shadow:0 1px 3px rgba(16,24,40,.14); }
		</style>
CSS;
	}

	// Explainer banner.
	echo '<div class="rpress-pack-intro">'
		. '<span class="rpress-pack-intro-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg></span>'
		. '<p><strong>' . esc_html__( 'A template controls how your menu, cart, and checkout look to customers.', 'restropress' ) . '</strong> '
		. esc_html__( 'Switching templates changes the design only. Your menu items, prices, and settings stay exactly as they are.', 'restropress' )
		. '</p></div>';

	if ( $legacy_on ) {
		echo '<p class="description rpress-pack-legacy-note">'
			. esc_html__( 'The Old RestroPress UI/UX option is enabled, so the Classic pack is in use. Disable it below to switch packs.', 'restropress' )
			. '</p>';
	}

	// The saved pack, even when its card is disabled (a lapsed Pro license or
	// legacy mode). Disabled radios are not submitted, and settings missing
	// from the form are deleted on save, so without this hidden field saving
	// the Styles tab would silently reset the pack to Classic. A checked
	// radio later in the form overrides it.
	$saved_id = sanitize_key( (string) rpress_get_option( $args['id'], 'classic' ) );
	if ( isset( $packs[ $saved_id ] ) ) {
		echo '<input type="hidden" name="' . esc_attr( $name_attr ) . '" value="' . esc_attr( $saved_id ) . '" />';
	}

	// A Pro pack is saved but no longer entitled: customers see Classic.
	$saved_pack = isset( $packs[ $saved_id ] ) ? $packs[ $saved_id ] : array();
	if ( ! $legacy_on && ! empty( $saved_pack['pro'] ) && ! apply_filters( 'rpress_template_pack_entitled', false, $saved_pack ) ) {
		/**
		 * Where to send a merchant whose Pro pack is locked. The Pro plugin
		 * points this at its license screen.
		 *
		 * @since 3.5.1
		 * @param string $url  Link.
		 * @param array  $pack Locked pack.
		 */
		$renew_url = apply_filters( 'rpress_template_pack_locked_url', 'https://www.restropress.com/pricing/', $saved_pack );
		echo '<div class="notice notice-warning inline rpress-pack-lapsed"><p><strong>'
			. esc_html(
				sprintf(
					/* translators: %s: pack name */
					__( 'Customers are seeing the Classic design instead of %s.', 'restropress' ),
					$saved_pack['label']
				)
			)
			. '</strong> '
			. esc_html__( 'Its RestroPress Pro license is not active on this site. Your choice is kept: renew or reconnect the license and the design comes back on its own.', 'restropress' )
			. ' <a href="' . esc_url( $renew_url ) . '">' . esc_html__( 'Check license', 'restropress' ) . '</a></p></div>';
	}

	echo '<div class="rpress-pack-cards">';
	foreach ( $packs as $pack ) {
		$is_active = ( $pack['id'] === $active['id'] );
		/**
		 * Whether a Pro pack is entitled to run. The Pro plugin filters
		 * this; without it Pro packs render locked.
		 *
		 * @since 3.4
		 */
		$is_locked = ! empty( $pack['pro'] ) && ! apply_filters( 'rpress_template_pack_entitled', false, $pack );
		$disabled  = ( $is_locked || $legacy_on ) ? ' disabled' : '';

		$classes = 'rpress-pack-card' . ( $is_active ? ' is-selected' : '' ) . ( $is_locked ? ' is-locked' : '' );
		// A div (not a label) whose hidden radio a JS handler checks on click —
		// the card can hold links (the Pro upgrade note), which nested labels
		// don't allow.
		echo '<div class="' . esc_attr( $classes ) . '">';
		echo '<input type="radio" class="rpress-pack-radio" name="' . esc_attr( $name_attr ) . '" value="' . esc_attr( $pack['id'] ) . '" ' . checked( $is_active, true, false ) . esc_attr( $disabled ) . ' />';

		echo '<span class="rpress-pack-thumb">';
		if ( ! empty( $pack['preview'] ) ) {
			echo '<img src="' . esc_url( $pack['preview'] ) . '" alt="' . esc_attr( $pack['label'] ) . '" />';
		} else {
			echo rpress_pack_thumb_svg( $pack['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG.
		}
		echo '</span>';

		echo '<span class="rpress-pack-card-body">';
		echo '<span class="rpress-pack-card-title">' . esc_html( $pack['label'] );
		if ( $is_active && $is_locked ) {
			echo ' <span class="rpress-pack-badge locked">' . esc_html__( 'Locked', 'restropress' ) . '</span>';
		} elseif ( $is_active ) {
			echo ' <span class="rpress-pack-badge active">' . esc_html__( 'Active', 'restropress' ) . '</span>';
		}
		if ( ! empty( $pack['pro'] ) ) {
			echo ' <span class="rpress-pack-badge pro">' . esc_html__( 'Pro', 'restropress' ) . '</span>';
		}
		echo '</span>';
		if ( ! empty( $pack['description'] ) ) {
			echo '<span class="rpress-pack-card-desc">' . esc_html( $pack['description'] ) . '</span>';
		}

		if ( $is_locked ) {
			echo '<span class="rpress-pack-locked-note">' . wp_kses_post(
				sprintf(
					/* translators: %s: upgrade link */
					__( 'Included in <a href="%s" target="_blank">RestroPress Pro</a>.', 'restropress' ),
					'https://www.restropress.com/pricing/'
				)
			) . '</span>';
		}

		echo '</span>';
		echo '<span class="rpress-pack-check" aria-hidden="true"></span>';
		echo '</div>';
	}
	echo '</div>';

	if ( ! empty( $args['desc'] ) ) {
		echo '<p class="description">' . wp_kses_post( $args['desc'] ) . '</p>';
	}

	// Live selected-state feedback (also a fallback for browsers without :has).
	// Clicking a card checks its hidden radio; mark that card selected and
	// clear the others so the orange highlight tracks the choice before the
	// settings are even saved.
	?>
	<script>
	( function () {
		var cards  = document.querySelectorAll( '.rpress-pack-card' );
		var radios = document.querySelectorAll( 'input.rpress-pack-radio' );
		function sync() {
			radios.forEach( function ( r ) {
				var card = r.closest( '.rpress-pack-card' );
				if ( card ) { card.classList.toggle( 'is-selected', r.checked ); }
			} );
		}
		cards.forEach( function ( card ) {
			var radio = card.querySelector( 'input.rpress-pack-radio' );
			if ( ! radio || radio.disabled ) { return; }
			card.addEventListener( 'click', function ( e ) {
				// Let links (e.g. the Pro upgrade note) do their own thing.
				if ( e.target.closest( 'a' ) ) { return; }
				if ( radio.checked ) { return; }
				radio.checked = true;
				radio.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
		} );
		radios.forEach( function ( r ) { r.addEventListener( 'change', sync ); } );
		sync();
	} )();
	</script>
	<?php
}

/**
 * Decorative inline-SVG thumbnail for a pack card when the pack ships no
 * preview image. Recognises the two core packs; anything else gets a neutral
 * placeholder.
 *
 * @since 3.4
 * @param string $pack_id Pack identifier.
 * @return string SVG markup.
 */
function rpress_pack_thumb_svg( $pack_id ) {
	switch ( $pack_id ) {
		case 'classic':
			// Light "list" card: an accent line over muted rows.
			return '<svg viewBox="0 0 76 66" xmlns="http://www.w3.org/2000/svg"><rect width="76" height="66" fill="#f1f1f2"/><rect x="14" y="18" width="30" height="7" rx="3.5" fill="#e0521f"/><rect x="14" y="32" width="48" height="5" rx="2.5" fill="#d5d7da"/><rect x="14" y="43" width="48" height="5" rx="2.5" fill="#d5d7da"/></svg>';
		case 'modern':
			// Dark image-forward grid: four colored tiles.
			return '<svg viewBox="0 0 76 66" xmlns="http://www.w3.org/2000/svg"><rect width="76" height="66" fill="#1b1b1f"/><rect x="16" y="14" width="17" height="17" rx="4" fill="#e0521f"/><rect x="43" y="14" width="17" height="17" rx="4" fill="#6c5ce7"/><rect x="16" y="36" width="17" height="17" rx="4" fill="#2e9e4f"/><rect x="43" y="36" width="17" height="17" rx="4" fill="#f0c040"/></svg>';
		default:
			return '<svg viewBox="0 0 76 66" xmlns="http://www.w3.org/2000/svg"><rect width="76" height="66" fill="#f1f1f2"/><rect x="16" y="16" width="44" height="34" rx="5" fill="#d5d7da"/></svg>';
	}
}

add_filter( 'rpress_settings_sanitize_pack_selector', 'sanitize_key' );

RPRESS_Template_Packs::init();
