<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIFAQ_Settings {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . AIFAQ_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	public function add_menu() {
		add_options_page(
			__( 'AI FAQ Generator', 'ai-faq-generator' ),
			__( 'AI FAQ Generator', 'ai-faq-generator' ),
			'manage_options',
			'ai-faq-generator',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		$fields = array(
			'api_key'        => 'sanitize_text_field',
			'model'          => 'sanitize_text_field',
			'faq_count'      => 'absint',
			'tone'           => 'sanitize_text_field',
			'post_types'     => array( $this, 'sanitize_post_types' ),
			'display_style'  => 'sanitize_text_field',
			'auto_display'   => 'sanitize_text_field',
			'heading_tag'    => 'sanitize_text_field',
			'schema_enabled' => 'sanitize_text_field',
		);

		foreach ( $fields as $key => $callback ) {
			register_setting( 'aifaq_settings_group', 'aifaq_' . $key, array( 'sanitize_callback' => $callback ) );
		}
	}

	public function sanitize_post_types( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_map( 'sanitize_text_field', $value );
	}

	public function plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=ai-faq-generator' ) ) . '">' . __( 'Settings', 'ai-faq-generator' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$api_key        = get_option( 'aifaq_api_key', '' );
		$model          = get_option( 'aifaq_model', 'gpt-4o-mini' );
		$faq_count      = (int) get_option( 'aifaq_faq_count', 5 );
		$tone           = get_option( 'aifaq_tone', 'neutral' );
		$post_types     = (array) get_option( 'aifaq_post_types', array( 'post', 'page' ) );
		$display_style  = get_option( 'aifaq_display_style', 'accordion' );
		$auto_display   = get_option( 'aifaq_auto_display', '1' );
		$heading_tag    = get_option( 'aifaq_heading_tag', 'h3' );
		$schema_enabled = get_option( 'aifaq_schema_enabled', '1' );

		$all_post_types = get_post_types( array( 'public' => true ), 'objects' );

		// Detect provider from key prefix
		$provider = 'openai';
		if ( strpos( $api_key, 'sk-or-' ) === 0 ) {
			$provider = 'openrouter';
		} elseif ( strpos( $api_key, 'AIza' ) === 0 ) {
			$provider = 'gemini';
		} elseif ( strpos( $api_key, 'gsk_' ) === 0 ) {
			$provider = 'groq';
		}

		$models_by_provider = array(
			'openai'     => array(
				'gpt-4o-mini'   => '⚡ GPT-4o Mini — Fast & cheap ~$0.001/generation',
				'gpt-4o'        => '🏆 GPT-4o — Best quality ~$0.01/generation',
				'gpt-3.5-turbo' => '💰 GPT-3.5 Turbo — Budget ~$0.0005/generation',
			),
			'openrouter' => array(
				'openai/gpt-4o-mini'                  => '⚡ GPT-4o Mini (OpenRouter) ~$0.0006/generation',
				'openai/gpt-4o'                       => '🏆 GPT-4o (OpenRouter) ~$0.008/generation',
				'anthropic/claude-3-haiku'            => '🤖 Claude 3 Haiku ~$0.0003/generation',
				'anthropic/claude-3.5-sonnet'         => '✨ Claude 3.5 Sonnet ~$0.005/generation',
				'meta-llama/llama-3.1-8b-instruct:free' => '🆓 Llama 3.1 8B — FREE',
			),
			'gemini'     => array(
				'gemini-2.0-flash'   => '🆓 Gemini 2.0 Flash — FREE tier available',
				'gemini-1.5-flash'   => '🆓 Gemini 1.5 Flash — FREE tier available',
				'gemini-1.5-pro'     => '🏆 Gemini 1.5 Pro — Best quality',
			),
			'groq'       => array(
				'llama-3.1-8b-instant'     => '🆓 Llama 3.1 8B Instant — FREE & ultra fast',
				'llama-3.3-70b-versatile'  => '🏆 Llama 3.3 70B — Best quality FREE',
				'mixtral-8x7b-32768'       => '⚡ Mixtral 8x7B — Fast & FREE',
			),
		);

		$all_models = $models_by_provider[ $provider ];

		$provider_labels = array(
			'openai'     => '🔵 OpenAI',
			'openrouter' => '🟢 OpenRouter',
			'gemini'     => '🔴 Google Gemini (FREE)',
			'groq'       => '🟡 Groq (FREE)',
		);

		$provider_links = array(
			'openai'     => 'platform.openai.com → API Keys',
			'openrouter' => 'openrouter.ai/keys — cheaper rates, 100+ models',
			'gemini'     => 'aistudio.google.com/apikey — FREE to use',
			'groq'       => 'console.groq.com/keys — completely FREE',
		);

		$tones = array(
			'neutral'   => 'Neutral (Default)',
			'formal'    => 'Formal & Professional',
			'simple'    => 'Simple & Easy to Understand',
			'friendly'  => 'Friendly & Conversational',
			'technical' => 'Technical & Detailed',
		);
		?>
		<div class="wrap aifaq-settings-wrap">
			<h1><?php esc_html_e( 'AI FAQ Generator — Settings', 'ai-faq-generator' ); ?></h1>

			<?php settings_errors( 'aifaq_settings_group' ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'aifaq_settings_group' ); ?>

				<div class="aifaq-settings-grid">

					<!-- API Settings -->
					<div class="aifaq-card">
						<h2><?php esc_html_e( 'API Configuration', 'ai-faq-generator' ); ?></h2>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="aifaq_api_key"><?php esc_html_e( 'API Key', 'ai-faq-generator' ); ?></label></th>
								<td>
									<input type="password" id="aifaq_api_key" name="aifaq_api_key"
										value="<?php echo esc_attr( $api_key ); ?>"
										class="regular-text" autocomplete="new-password" />
									<?php if ( ! empty( $api_key ) ) : ?>
										<span style="margin-left:8px;color:#2e7d32;font-weight:600;">
											✅ <?php echo esc_html( $provider_labels[ $provider ] ); ?> <?php esc_html_e( 'key detected', 'ai-faq-generator' ); ?>
										</span>
									<?php endif; ?>
									<p class="description">
										<?php esc_html_e( 'Supports 4 providers — paste any key and it auto-detects:', 'ai-faq-generator' ); ?><br>
										🔵 <strong>OpenAI</strong> — platform.openai.com (sk-...)<br>
										🟢 <strong>OpenRouter</strong> — openrouter.ai/keys (sk-or-...) cheaper rates<br>
										🔴 <strong>Gemini</strong> — aistudio.google.com/apikey (AIza...) <strong>FREE</strong><br>
										🟡 <strong>Groq</strong> — console.groq.com/keys (gsk_...) <strong>FREE</strong>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="aifaq_model"><?php esc_html_e( 'AI Model', 'ai-faq-generator' ); ?></label></th>
								<td>
									<select id="aifaq_model" name="aifaq_model">
										<?php foreach ( $all_models as $value => $label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $model, $value ); ?>>
												<?php echo esc_html( $label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description">
										<?php esc_html_e( 'Models shown for detected provider:', 'ai-faq-generator' ); ?>
										<strong><?php echo esc_html( $provider_labels[ $provider ] ); ?></strong>
										— <?php echo esc_html( $provider_links[ $provider ] ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>

					<!-- Generation Settings -->
					<div class="aifaq-card">
						<h2><?php esc_html_e( 'Generation Settings', 'ai-faq-generator' ); ?></h2>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="aifaq_faq_count"><?php esc_html_e( 'Number of FAQs', 'ai-faq-generator' ); ?></label></th>
								<td>
									<input type="number" id="aifaq_faq_count" name="aifaq_faq_count"
										value="<?php echo esc_attr( $faq_count ); ?>"
										min="1" max="20" class="small-text" />
									<p class="description"><?php esc_html_e( 'How many Q&A pairs to generate per post (1–20).', 'ai-faq-generator' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="aifaq_tone"><?php esc_html_e( 'Default Tone', 'ai-faq-generator' ); ?></label></th>
								<td>
									<select id="aifaq_tone" name="aifaq_tone">
										<?php foreach ( $tones as $value => $label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $tone, $value ); ?>>
												<?php echo esc_html( $label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Default writing tone for generated FAQs. Can be changed per post inside the editor.', 'ai-faq-generator' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable on Post Types', 'ai-faq-generator' ); ?></th>
								<td>
									<?php foreach ( $all_post_types as $pt ) : ?>
										<label style="display:block;margin-bottom:4px;">
											<input type="checkbox" name="aifaq_post_types[]"
												value="<?php echo esc_attr( $pt->name ); ?>"
												<?php checked( in_array( $pt->name, $post_types, true ) ); ?> />
											<?php echo esc_html( $pt->label ); ?>
											<code style="font-size:11px;color:#888;">(<?php echo esc_html( $pt->name ); ?>)</code>
										</label>
									<?php endforeach; ?>
								</td>
							</tr>
						</table>
					</div>

					<!-- Display Settings -->
					<div class="aifaq-card">
						<h2><?php esc_html_e( 'Display Settings', 'ai-faq-generator' ); ?></h2>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="aifaq_display_style"><?php esc_html_e( 'Display Style', 'ai-faq-generator' ); ?></label></th>
								<td>
									<select id="aifaq_display_style" name="aifaq_display_style">
										<option value="accordion" <?php selected( $display_style, 'accordion' ); ?>><?php esc_html_e( 'Accordion (expandable)', 'ai-faq-generator' ); ?></option>
										<option value="list" <?php selected( $display_style, 'list' ); ?>><?php esc_html_e( 'Plain List (always visible)', 'ai-faq-generator' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="aifaq_heading_tag"><?php esc_html_e( 'Question Heading Tag', 'ai-faq-generator' ); ?></label></th>
								<td>
									<select id="aifaq_heading_tag" name="aifaq_heading_tag">
										<?php foreach ( array( 'h2', 'h3', 'h4', 'strong' ) as $tag ) : ?>
											<option value="<?php echo esc_attr( $tag ); ?>" <?php selected( $heading_tag, $tag ); ?>><?php echo esc_html( strtoupper( $tag ) ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Auto-Append to Content', 'ai-faq-generator' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="aifaq_auto_display" value="1" <?php checked( $auto_display, '1' ); ?> />
										<?php esc_html_e( 'Automatically append FAQ section below post content', 'ai-faq-generator' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Uncheck to use [ai_faq] shortcode manually.', 'ai-faq-generator' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'JSON-LD Schema', 'ai-faq-generator' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="aifaq_schema_enabled" value="1" <?php checked( $schema_enabled, '1' ); ?> />
										<?php esc_html_e( 'Inject FAQ schema markup (boosts Google rich results)', 'ai-faq-generator' ); ?>
									</label>
								</td>
							</tr>
						</table>
					</div>

				</div><!-- .aifaq-settings-grid -->

				<?php submit_button( __( 'Save Settings', 'ai-faq-generator' ) ); ?>
			</form>

			<!-- Bulk Generate -->
			<div class="aifaq-card aifaq-bulk-wrap" style="margin-top:20px;">
				<h2>⚡ <?php esc_html_e( 'Bulk Generate FAQs', 'ai-faq-generator' ); ?></h2>
				<p><?php esc_html_e( 'Generate FAQs for all posts/pages that do not have FAQs yet — in one click.', 'ai-faq-generator' ); ?></p>

				<?php
				$post_types_enabled = (array) get_option( 'aifaq_post_types', array( 'post', 'page' ) );
				$posts_without_faqs = get_posts( array(
					'post_type'      => $post_types_enabled,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'meta_query'     => array(
						array(
							'key'     => '_aifaq_data',
							'compare' => 'NOT EXISTS',
						),
					),
				) );
				?>

				<?php if ( empty( $posts_without_faqs ) ) : ?>
					<p style="color:#2e7d32;font-weight:600;">✅ <?php esc_html_e( 'All published posts already have FAQs!', 'ai-faq-generator' ); ?></p>
				<?php else : ?>
					<p>
						<strong><?php echo esc_html( count( $posts_without_faqs ) ); ?></strong>
						<?php esc_html_e( 'posts found without FAQs.', 'ai-faq-generator' ); ?>
					</p>
					<button type="button" id="aifaq-bulk-start" class="button button-primary">
						⚡ <?php esc_html_e( 'Generate FAQs for All Posts', 'ai-faq-generator' ); ?>
					</button>
					<div id="aifaq-bulk-progress" style="display:none;margin-top:16px;">
						<div style="background:#e0e0e0;border-radius:4px;height:12px;width:100%;max-width:500px;">
							<div id="aifaq-bulk-bar" style="background:#2271b1;height:12px;border-radius:4px;width:0%;transition:width 0.3s;"></div>
						</div>
						<p id="aifaq-bulk-status" style="margin-top:8px;font-size:13px;color:#555;"></p>
					</div>
					<div id="aifaq-bulk-log" style="display:none;margin-top:12px;max-height:200px;overflow-y:auto;border:1px solid #ddd;padding:10px;border-radius:4px;font-size:12px;background:#f9f9f9;"></div>
					<script type="application/json" id="aifaq-bulk-posts"><?php echo wp_json_encode( wp_list_pluck( $posts_without_faqs, 'ID' ) ); ?></script>
				<?php endif; ?>
			</div>

			<!-- Shortcode reference -->
			<div class="aifaq-card aifaq-shortcode-info" style="margin-top:20px;">
				<h2><?php esc_html_e( 'Shortcode', 'ai-faq-generator' ); ?></h2>
				<p><?php esc_html_e( 'Place FAQs anywhere in your content:', 'ai-faq-generator' ); ?></p>
				<code>[ai_faq]</code>
				<p><?php esc_html_e( 'Or with a custom title:', 'ai-faq-generator' ); ?></p>
				<code>[ai_faq title="Frequently Asked Questions"]</code>
			</div>
		</div>

		<script>
		(function($){
			$('#aifaq-bulk-start').on('click', function(){
				var posts = JSON.parse($('#aifaq-bulk-posts').text() || '[]');
				if(!posts.length) return;
				var total = posts.length, done = 0;
				$('#aifaq-bulk-progress').show();
				$('#aifaq-bulk-log').show();
				$('#aifaq-bulk-start').prop('disabled', true);

				function processNext(){
					if(!posts.length){
						$('#aifaq-bulk-status').text('✅ Done! Generated FAQs for ' + done + ' posts.');
						$('#aifaq-bulk-bar').css('width','100%');
						return;
					}
					var postId = posts.shift();
					$('#aifaq-bulk-status').text('Processing post ' + (done+1) + ' of ' + total + '...');
					$.post(ajaxurl, {
						action: 'aifaq_bulk_generate',
						nonce:  '<?php echo esc_js( wp_create_nonce( 'aifaq_generate' ) ); ?>',
						post_id: postId,
					}).done(function(res){
						done++;
						var pct = Math.round((done/total)*100);
						$('#aifaq-bulk-bar').css('width', pct + '%');
						if(res.success){
							$('#aifaq-bulk-log').append('<div style="color:#2e7d32;">✓ ' + res.data.title + ' — ' + res.data.count + ' FAQs</div>');
						} else {
							$('#aifaq-bulk-log').append('<div style="color:#c62828;">✗ Post #' + postId + ' — ' + (res.data && res.data.message ? res.data.message : 'Error') + '</div>');
						}
					}).fail(function(){
						done++;
						$('#aifaq-bulk-log').append('<div style="color:#c62828;">✗ Post #' + postId + ' — Network error</div>');
					}).always(function(){
						setTimeout(processNext, 500);
					});
				}
				processNext();
			});
		}(jQuery));
		</script>

		<style>
		.aifaq-settings-wrap { max-width: 900px; }
		.aifaq-settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
		.aifaq-card { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 20px 24px; }
		.aifaq-card h2 { font-size: 15px; margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
		.aifaq-shortcode-info { grid-column: 1 / -1; }
		.aifaq-shortcode-info code { display: inline-block; margin: 4px 0; padding: 6px 12px; background: #f0f0f1; border-radius: 4px; font-size: 13px; }
		@media (max-width: 782px) { .aifaq-settings-grid { grid-template-columns: 1fr; } }
		</style>
		<?php
	}
}
