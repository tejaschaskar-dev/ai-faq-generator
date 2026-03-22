<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIFAQ_Frontend {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'the_content',        array( $this, 'auto_append' ) );
		add_shortcode( 'ai_faq',          array( $this, 'shortcode' ) );
	}

	public function enqueue() {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = get_the_ID();
		$faqs    = get_post_meta( $post_id, '_aifaq_data', true );
		$enabled = get_post_meta( $post_id, '_aifaq_enabled', true );

		if ( empty( $faqs ) || '0' === $enabled ) {
			return;
		}

		wp_enqueue_style(
			'aifaq-frontend',
			AIFAQ_URL . 'assets/css/frontend.css',
			array(),
			AIFAQ_VERSION
		);

		$style = get_option( 'aifaq_display_style', 'accordion' );
		if ( 'accordion' === $style ) {
			wp_enqueue_script(
				'aifaq-frontend',
				AIFAQ_URL . 'assets/js/frontend.js',
				array(),
				AIFAQ_VERSION,
				true
			);
		}
	}

	public function auto_append( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! get_option( 'aifaq_auto_display', '1' ) ) {
			return $content;
		}

		$post_id    = get_the_ID();
		$post_types = (array) get_option( 'aifaq_post_types', array( 'post', 'page' ) );
		if ( ! in_array( get_post_type( $post_id ), $post_types, true ) ) {
			return $content;
		}

		$faq_html = $this->render_faqs( $post_id );
		if ( ! empty( $faq_html ) ) {
			$content .= $faq_html;
		}

		return $content;
	}

	public function shortcode( $atts ) {
		$atts    = shortcode_atts( array( 'title' => '', 'post_id' => 0 ), $atts, 'ai_faq' );
		$post_id = $atts['post_id'] ? absint( $atts['post_id'] ) : get_the_ID();
		$title   = $atts['title'];

		return $this->render_faqs( $post_id, $title );
	}

	private function render_faqs( $post_id, $custom_title = '' ) {
		$enabled = get_post_meta( $post_id, '_aifaq_enabled', true );
		if ( '0' === $enabled ) {
			return '';
		}

		$faqs = get_post_meta( $post_id, '_aifaq_data', true );
		if ( empty( $faqs ) || ! is_array( $faqs ) ) {
			return '';
		}

		$style       = get_option( 'aifaq_display_style', 'accordion' );
		$heading_tag = get_option( 'aifaq_heading_tag', 'h3' );
		$allowed_tags = array( 'h2', 'h3', 'h4', 'strong' );
		if ( ! in_array( $heading_tag, $allowed_tags, true ) ) {
			$heading_tag = 'h3';
		}

		ob_start();
		include AIFAQ_PATH . 'templates/faq-display.php';
		return ob_get_clean();
	}
}
