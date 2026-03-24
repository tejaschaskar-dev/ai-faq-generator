<?php
/**
 * Plugin Name:  AI FAQ Generator
 * Plugin URI:   https://example.com/ai-faq-generator
 * Description:  Automatically generate SEO-optimized FAQs with JSON-LD schema for any post, page, or WooCommerce product using AI.
 * Version:      1.2.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author:       Tejas
 * License:      GPL v2 or later
 * Text Domain:  ai-faq-generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIFAQ_VERSION', '1.2.0' );
define( 'AIFAQ_PATH', plugin_dir_path( __FILE__ ) );
define( 'AIFAQ_URL', plugin_dir_url( __FILE__ ) );
define( 'AIFAQ_BASENAME', plugin_basename( __FILE__ ) );

require_once AIFAQ_PATH . 'includes/class-settings.php';
require_once AIFAQ_PATH . 'includes/class-ai-generator.php';
require_once AIFAQ_PATH . 'includes/class-metabox.php';
require_once AIFAQ_PATH . 'includes/class-schema.php';
require_once AIFAQ_PATH . 'includes/class-frontend.php';

/**
 * Bootstrap all plugin components.
 */
function aifaq_init() {
	new AIFAQ_Settings();
	new AIFAQ_Metabox();
	new AIFAQ_Schema();
	new AIFAQ_Frontend();
}
add_action( 'plugins_loaded', 'aifaq_init' );

/**
 * Activation: set default options.
 */
function aifaq_activate() {
	$defaults = array(
		'api_key'         => '',
		'model'           => 'gpt-4o-mini',
		'faq_count'       => 5,
		'tone'            => 'neutral',
		'post_types'      => array( 'post', 'page' ),
		'display_style'   => 'accordion',
		'auto_display'    => '1',
		'heading_tag'     => 'h3',
		'schema_enabled'  => '1',
	);
	foreach ( $defaults as $key => $value ) {
		if ( false === get_option( 'aifaq_' . $key ) ) {
			update_option( 'aifaq_' . $key, $value );
		}
	}
}
register_activation_hook( __FILE__, 'aifaq_activate' );

/**
 * Deactivation: flush rewrite rules (safe, non-destructive).
 */
function aifaq_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'aifaq_deactivate' );
