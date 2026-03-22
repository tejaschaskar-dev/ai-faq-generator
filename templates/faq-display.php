<?php
/**
 * FAQ display template.
 * Available variables: $faqs, $style, $heading_tag, $custom_title
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$section_title = ! empty( $custom_title ) ? $custom_title : __( 'Frequently Asked Questions', 'ai-faq-generator' );
$is_accordion  = ( 'accordion' === $style );
?>
<div class="aifaq-section aifaq-style-<?php echo esc_attr( $style ); ?>">

	<h2 class="aifaq-section-title"><?php echo esc_html( $section_title ); ?></h2>

	<div class="aifaq-list" <?php echo $is_accordion ? 'role="list"' : ''; ?>>
		<?php foreach ( $faqs as $index => $faq ) :
			if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) continue;
			$item_id = 'aifaq-item-' . $index;
		?>
		<div class="aifaq-item<?php echo $is_accordion ? ' aifaq-accordion-item' : ''; ?>" <?php echo $is_accordion ? 'role="listitem"' : ''; ?>>

			<?php if ( $is_accordion ) : ?>
				<button
					class="aifaq-question aifaq-accordion-trigger"
					aria-expanded="false"
					aria-controls="<?php echo esc_attr( $item_id ); ?>"
					type="button"
				>
					<<?php echo esc_attr( $heading_tag ); ?> class="aifaq-question-text">
						<?php echo esc_html( $faq['question'] ); ?>
					</<?php echo esc_attr( $heading_tag ); ?>>
					<span class="aifaq-icon" aria-hidden="true">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M3 6l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</span>
				</button>
				<div class="aifaq-answer" id="<?php echo esc_attr( $item_id ); ?>" hidden>
					<div class="aifaq-answer-inner">
						<?php echo wp_kses_post( $faq['answer'] ); ?>
					</div>
				</div>

			<?php else : ?>
				<<?php echo esc_attr( $heading_tag ); ?> class="aifaq-question aifaq-question-plain">
					<?php echo esc_html( $faq['question'] ); ?>
				</<?php echo esc_attr( $heading_tag ); ?>>
				<div class="aifaq-answer aifaq-answer-plain">
					<?php echo wp_kses_post( $faq['answer'] ); ?>
				</div>
			<?php endif; ?>

		</div><!-- .aifaq-item -->
		<?php endforeach; ?>
	</div><!-- .aifaq-list -->

</div><!-- .aifaq-section -->
