<?php
/**
 * Feature: AI across the admin (3.5).
 *
 * - RPRESS_AI: provider-agnostic service (generate_text, extract_structured),
 *   shared with the menu importer (RPress_Onboarding).
 * - RPRESS_AI_Admin: Settings > General > AI Assistant, food item editor
 *   helpers (write / improve copy, dietary and allergen suggestions, image alt
 *   text) and the bulk "Generate missing descriptions" review screen.
 *
 * Guardrails: AI output is always reviewed by the merchant before it is
 * saved, nothing is published automatically and prices are never touched.
 *
 * @package RPRESS\Features\AI
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-rpress-ai.php';

if ( is_admin() ) {
	require_once __DIR__ . '/class-rpress-ai-admin.php';
	RPRESS_AI_Admin::init();
}
