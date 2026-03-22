<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIFAQ_Schema {

	public function __construct() {
		add_action( 'wp_head', array( $this, 'inject_schema' ) );
	}

	public function inject_schema() {
		if ( ! get_option( 'aifaq_schema_enabled', '1' ) ) {
			return;
		}
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		$enabled = get_post_meta( $post_id, '_aifaq_enabled', true );
		if ( '0' === $enabled ) {
			return;
		}

		$faqs = get_post_meta( $post_id, '_aifaq_data', true );
		if ( empty( $faqs ) || ! is_array( $faqs ) ) {
			return;
		}

		$entities = array();
		foreach ( $faqs as $faq ) {
			if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) {
				continue;
			}
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( $faq['question'] ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( $faq['answer'] ),
				),
			);
		}

		if ( empty( $entities ) ) {
			return;
		}

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);

		echo '<script type="application/ld+json">' . "\n";
		echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		echo "\n</script>\n";
	}
}
