<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIFAQ_Metabox {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'save_post',      array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_aifaq_generate',      array( $this, 'ajax_generate' ) );
		add_action( 'wp_ajax_aifaq_bulk_generate', array( $this, 'ajax_bulk_generate' ) );
	}

	public function register() {
		$post_types = (array) get_option( 'aifaq_post_types', array( 'post', 'page' ) );
		foreach ( $post_types as $pt ) {
			add_meta_box(
				'aifaq_metabox',
				__( 'AI FAQ Generator', 'ai-faq-generator' ),
				array( $this, 'render' ),
				$pt,
				'normal',
				'high'
			);
		}
	}

	public function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		$post_types = (array) get_option( 'aifaq_post_types', array( 'post', 'page' ) );
		if ( ! $screen || ! in_array( $screen->post_type, $post_types, true ) ) {
			return;
		}

		wp_enqueue_style(
			'aifaq-admin',
			AIFAQ_URL . 'assets/css/admin.css',
			array(),
			AIFAQ_VERSION
		);
		wp_enqueue_script(
			'aifaq-admin',
			AIFAQ_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			AIFAQ_VERSION,
			true
		);
		// Use the current request host to support non-standard ports (e.g. Local by Flywheel).
		$ajax_url = admin_url( 'admin-ajax.php' );
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$parsed   = wp_parse_url( $ajax_url );
			$ajax_url = ( isset( $parsed['scheme'] ) ? $parsed['scheme'] : 'http' ) . '://' .
				sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) .
				( isset( $parsed['path'] ) ? $parsed['path'] : '/wp-admin/admin-ajax.php' );
		}
		wp_localize_script( 'aifaq-admin', 'AIFAQ', array(
			'ajax_url' => $ajax_url,
			'nonce'    => wp_create_nonce( 'aifaq_generate' ),
			'i18n'     => array(
				'generating'   => __( 'Generating FAQs…', 'ai-faq-generator' ),
				'error'        => __( 'Error: ', 'ai-faq-generator' ),
				'confirm_del'  => __( 'Delete all FAQs for this post?', 'ai-faq-generator' ),
				'confirm_regen'=> __( 'This will replace all existing FAQs. Continue?', 'ai-faq-generator' ),
			),
		) );
	}

	public function render( $post ) {
		wp_nonce_field( 'aifaq_save_' . $post->ID, 'aifaq_nonce' );

		$faqs      = get_post_meta( $post->ID, '_aifaq_data', true );
		$faq_count = (int) get_option( 'aifaq_faq_count', 5 );
		$enabled   = get_post_meta( $post->ID, '_aifaq_enabled', true );
		$enabled   = ( '' === $enabled ) ? '1' : $enabled; // default on
		$post_tone = get_post_meta( $post->ID, '_aifaq_tone', true );
		$post_tone = $post_tone ? $post_tone : get_option( 'aifaq_tone', 'neutral' );
		$tones     = array(
			'neutral'   => __( 'Neutral', 'ai-faq-generator' ),
			'formal'    => __( 'Formal & Professional', 'ai-faq-generator' ),
			'simple'    => __( 'Simple & Easy', 'ai-faq-generator' ),
			'friendly'  => __( 'Friendly & Conversational', 'ai-faq-generator' ),
			'technical' => __( 'Technical & Detailed', 'ai-faq-generator' ),
		);
		?>
		<div class="aifaq-metabox-wrap">

			<div class="aifaq-toolbar">
				<div class="aifaq-toolbar-left">
					<label class="aifaq-toggle-label">
						<input type="checkbox" name="aifaq_enabled" id="aifaq_enabled" value="1" <?php checked( $enabled, '1' ); ?> />
						<?php esc_html_e( 'Show FAQs on this post', 'ai-faq-generator' ); ?>
					</label>
				</div>
				<div class="aifaq-toolbar-right">
					<label for="aifaq_tone_override" class="aifaq-count-label">
						<?php esc_html_e( 'Tone:', 'ai-faq-generator' ); ?>
					</label>
					<select id="aifaq_tone_override" class="aifaq-tone-select">
						<?php foreach ( $tones as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $post_tone, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<label for="aifaq_count_override" class="aifaq-count-label">
						<?php esc_html_e( 'FAQs:', 'ai-faq-generator' ); ?>
					</label>
					<input type="number" id="aifaq_count_override" min="1" max="20" value="<?php echo esc_attr( $faq_count ); ?>" class="aifaq-count-input" />

					<button type="button" id="aifaq-generate-btn" class="button button-primary" data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-has-faqs="<?php echo ! empty( $faqs ) ? '1' : '0'; ?>">
						<span class="dashicons dashicons-superhero-alt"></span>
						<?php echo empty( $faqs ) ? esc_html__( 'Generate FAQs', 'ai-faq-generator' ) : esc_html__( 'Regenerate FAQs', 'ai-faq-generator' ); ?>
					</button>

					<?php if ( ! empty( $faqs ) ) : ?>
					<button type="button" id="aifaq-delete-btn" class="button button-secondary">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Clear', 'ai-faq-generator' ); ?>
					</button>
					<?php endif; ?>
				</div>
			</div>

			<div id="aifaq-status" class="aifaq-status" style="display:none;"></div>

			<div id="aifaq-editor" class="aifaq-editor">
				<?php if ( ! empty( $faqs ) ) : ?>
					<?php foreach ( $faqs as $i => $faq ) : ?>
						<?php $this->render_faq_row( $i, $faq ); ?>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="aifaq-placeholder">
						<?php esc_html_e( 'No FAQs yet. Click "Generate FAQs" to create them automatically from your post content.', 'ai-faq-generator' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<div id="aifaq-add-row" style="margin-top:10px;<?php echo empty( $faqs ) ? 'display:none;' : ''; ?>">
				<button type="button" id="aifaq-add-btn" class="button">
					+ <?php esc_html_e( 'Add FAQ manually', 'ai-faq-generator' ); ?>
				</button>
			</div>

			<!-- Hidden template for JS cloning -->
			<script type="text/html" id="aifaq-row-template">
				<?php $this->render_faq_row( '__INDEX__', array( 'question' => '', 'answer' => '' ) ); ?>
			</script>

		</div><!-- .aifaq-metabox-wrap -->
		<?php
	}

	private function render_faq_row( $index, $faq ) {
		?>
		<div class="aifaq-faq-row" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="aifaq-faq-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'ai-faq-generator' ); ?>"></div>
			<div class="aifaq-faq-fields">
				<input
					type="text"
					name="aifaq_faqs[<?php echo esc_attr( $index ); ?>][question]"
					placeholder="<?php esc_attr_e( 'Question', 'ai-faq-generator' ); ?>"
					value="<?php echo esc_attr( isset( $faq['question'] ) ? $faq['question'] : '' ); ?>"
					class="widefat aifaq-question-input"
				/>
				<textarea
					name="aifaq_faqs[<?php echo esc_attr( $index ); ?>][answer]"
					placeholder="<?php esc_attr_e( 'Answer', 'ai-faq-generator' ); ?>"
					rows="3"
					class="widefat aifaq-answer-input"
				><?php echo esc_textarea( isset( $faq['answer'] ) ? $faq['answer'] : '' ); ?></textarea>
			</div>
			<button type="button" class="aifaq-remove-row button-link" title="<?php esc_attr_e( 'Remove', 'ai-faq-generator' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<?php
	}

	public function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['aifaq_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aifaq_nonce'] ) ), 'aifaq_save_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save enabled toggle.
		$enabled = isset( $_POST['aifaq_enabled'] ) ? '1' : '0';
		update_post_meta( $post_id, '_aifaq_enabled', $enabled );

		// Save tone.
		if ( isset( $_POST['aifaq_tone'] ) ) {
			$allowed_tones = array( 'neutral', 'formal', 'simple', 'friendly', 'technical' );
			$tone = sanitize_text_field( wp_unslash( $_POST['aifaq_tone'] ) );
			if ( in_array( $tone, $allowed_tones, true ) ) {
				update_post_meta( $post_id, '_aifaq_tone', $tone );
			}
		}

		// Save FAQ rows.
		if ( isset( $_POST['aifaq_faqs'] ) && is_array( $_POST['aifaq_faqs'] ) ) {
			$raw  = wp_unslash( $_POST['aifaq_faqs'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$faqs = array();
			foreach ( $raw as $item ) {
				$q = sanitize_text_field( $item['question'] ?? '' );
				$a = wp_kses_post( $item['answer'] ?? '' );
				if ( ! empty( $q ) && ! empty( $a ) ) {
					$faqs[] = array( 'question' => $q, 'answer' => $a );
				}
			}
			update_post_meta( $post_id, '_aifaq_data', $faqs );
		} else {
			delete_post_meta( $post_id, '_aifaq_data' );
		}
	}

	public function ajax_generate() {
		check_ajax_referer( 'aifaq_generate', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'ai-faq-generator' ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$count   = isset( $_POST['count'] )   ? absint( $_POST['count'] )   : null;
		$tone    = isset( $_POST['tone'] )    ? sanitize_text_field( wp_unslash( $_POST['tone'] ) ) : null;

		if ( ! $post_id ) {
			wp_send_json_error( __( 'Invalid post ID.', 'ai-faq-generator' ) );
		}

		require_once AIFAQ_PATH . 'includes/class-ai-generator.php';
		$generator = new AIFAQ_AI_Generator();
		$result    = $generator->generate( $post_id, $count, $tone );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// Persist generated FAQs and tone.
		update_post_meta( $post_id, '_aifaq_data', $result );
		update_post_meta( $post_id, '_aifaq_enabled', '1' );
		if ( $tone ) {
			update_post_meta( $post_id, '_aifaq_tone', $tone );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Bulk generate FAQs for a single post (called repeatedly from bulk generate page).
	 */
	public function ajax_bulk_generate() {
		check_ajax_referer( 'aifaq_generate', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'ai-faq-generator' ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$count   = isset( $_POST['count'] )   ? absint( $_POST['count'] )   : null;
		$tone    = isset( $_POST['tone'] )    ? sanitize_text_field( wp_unslash( $_POST['tone'] ) ) : null;

		if ( ! $post_id ) {
			wp_send_json_error( __( 'Invalid post ID.', 'ai-faq-generator' ) );
		}

		require_once AIFAQ_PATH . 'includes/class-ai-generator.php';
		$generator = new AIFAQ_AI_Generator();
		$result    = $generator->generate( $post_id, $count, $tone );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'post_id' => $post_id,
				'message' => $result->get_error_message(),
			) );
		}

		update_post_meta( $post_id, '_aifaq_data', $result );
		update_post_meta( $post_id, '_aifaq_enabled', '1' );
		if ( $tone ) {
			update_post_meta( $post_id, '_aifaq_tone', $tone );
		}

		wp_send_json_success( array(
			'post_id' => $post_id,
			'title'   => get_the_title( $post_id ),
			'count'   => count( $result ),
		) );
	}
}
