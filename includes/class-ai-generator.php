<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIFAQ_AI_Generator {

	private $api_key;
	private $model;
	private $api_url;
	private $provider;

	public function __construct() {
		$this->api_key  = get_option( 'aifaq_api_key', '' );
		$this->model    = get_option( 'aifaq_model', 'gpt-4o-mini' );
		$this->provider = $this->detect_provider();

		$urls = array(
			'openai'     => 'https://api.openai.com/v1/chat/completions',
			'openrouter' => 'https://openrouter.ai/api/v1/chat/completions',
			'groq'       => 'https://api.groq.com/openai/v1/chat/completions',
			'gemini'     => 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . $this->api_key,
		);

		$this->api_url = $urls[ $this->provider ];
	}

	/**
	 * Auto-detect provider from API key prefix.
	 */
	private function detect_provider() {
		if ( strpos( $this->api_key, 'sk-or-' ) === 0 ) {
			return 'openrouter';
		}
		if ( strpos( $this->api_key, 'AIza' ) === 0 ) {
			return 'gemini';
		}
		if ( strpos( $this->api_key, 'gsk_' ) === 0 ) {
			return 'groq';
		}
		return 'openai';
	}

	/**
	 * Generate FAQs for a given post.
	 *
	 * @param int $post_id
	 * @param int $count  Number of Q&A pairs to generate.
	 * @return array|WP_Error  Array of ['question'=>'...','answer'=>'...'] or WP_Error.
	 */
	public function generate( $post_id, $count = null, $tone = null ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'API key is not configured. Please add it in Settings → AI FAQ Generator.', 'ai-faq-generator' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'invalid_post', __( 'Post not found.', 'ai-faq-generator' ) );
		}

		if ( null === $count ) {
			$count = (int) get_option( 'aifaq_faq_count', 5 );
		}
		$count = max( 1, min( 20, (int) $count ) );

		if ( null === $tone ) {
			$tone = get_option( 'aifaq_tone', 'neutral' );
		}

		$content = $this->prepare_content( $post );
		if ( empty( trim( $content ) ) ) {
			return new WP_Error( 'empty_content', __( 'Post has no readable content to generate FAQs from.', 'ai-faq-generator' ) );
		}

		$prompt = $this->build_prompt( $content, $count, $post->post_title, $tone );

		if ( 'gemini' === $this->provider ) {
			$response = $this->call_gemini( $prompt );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			return $this->parse_gemini_response( $response );
		}

		$response = $this->call_openai( $prompt );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->parse_response( $response );
	}

	/**
	 * Strip shortcodes, HTML, and trim content to a safe token length.
	 */
	private function prepare_content( $post ) {
		$content = $post->post_content;
		$content = do_shortcode( $content );
		$content = wp_strip_all_tags( $content );
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = trim( $content );

		// Limit to ~3000 words to stay within token budget.
		$words = explode( ' ', $content );
		if ( count( $words ) > 3000 ) {
			$content = implode( ' ', array_slice( $words, 0, 3000 ) ) . '…';
		}

		return $content;
	}

	/**
	 * Build the structured system + user prompt.
	 */
	private function build_prompt( $content, $count, $title, $tone = 'neutral' ) {
		$tone_map = array(
			'neutral'   => 'Use a neutral, informative tone.',
			'formal'    => 'Use a formal and professional tone suitable for business websites.',
			'simple'    => 'Use simple, easy-to-understand language suitable for beginners. Avoid technical jargon.',
			'friendly'  => 'Use a friendly, conversational tone as if speaking directly to the reader.',
			'technical' => 'Use a technical, detailed tone suitable for developers and experts.',
		);
		$tone_instruction = isset( $tone_map[ $tone ] ) ? $tone_map[ $tone ] : $tone_map['neutral'];

		$system = 'You are an SEO expert who writes clear, helpful FAQ content for websites. Always respond in valid JSON only — no markdown fences, no extra text.';

		$user = 'Read the following content from a page titled "' . $title . '" and generate exactly ' . $count . ' frequently asked questions with detailed answers. ' .
			$tone_instruction . ' ' .
			'Return ONLY a JSON array in this format: [{"question": "...", "answer": "..."}, ...]. ' .
			'Rules: (1) Questions must be things real users would search for. (2) Answers must be 1–3 sentences, factual, and based on the content. (3) No fluff. (4) Do not number the questions.' .
			"\n\nContent:\n" . $content;

		return array(
			'system' => $system,
			'user'   => $user,
		);
	}

	/**
	 * Send a request to the OpenAI Chat Completions API.
	 */
	private function call_openai( $prompt ) {
		$body = array(
			'model'       => $this->model,
			'messages'    => array(
				array( 'role' => 'system', 'content' => $prompt['system'] ),
				array( 'role' => 'user',   'content' => $prompt['user'] ),
			),
			'temperature' => 0.4,
			'max_tokens'  => 2000,
		);

		$response = wp_remote_post(
			$this->api_url,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'http_error', $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown API error.', 'ai-faq-generator' );
			return new WP_Error( 'api_error', $message );
		}

		return $data;
	}

	/**
	 * Send a request to the Gemini API.
	 */
	private function call_gemini( $prompt ) {
		$body = array(
			'contents'         => array(
				array(
					'parts' => array(
						array( 'text' => $prompt['system'] . "\n\n" . $prompt['user'] ),
					),
				),
			),
			'generationConfig' => array(
				'temperature'     => 0.4,
				'maxOutputTokens' => 2000,
			),
		);

		$response = wp_remote_post(
			$this->api_url,
			array(
				'timeout' => 60,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'http_error', $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown Gemini API error.', 'ai-faq-generator' );
			return new WP_Error( 'api_error', $message );
		}

		return $data;
	}

	/**
	 * Parse Gemini API response format.
	 */
	private function parse_gemini_response( $data ) {
		$text = isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ? trim( $data['candidates'][0]['content']['parts'][0]['text'] ) : '';

		if ( empty( $text ) ) {
			return new WP_Error( 'empty_response', __( 'Gemini returned an empty response. Please try again.', 'ai-faq-generator' ) );
		}

		$text  = preg_replace( '/^```(?:json)?\s*/i', '', $text );
		$text  = preg_replace( '/\s*```$/', '', $text );
		$faqs  = json_decode( $text, true );

		if ( ! is_array( $faqs ) || empty( $faqs ) ) {
			return new WP_Error( 'parse_error', __( 'Could not parse Gemini response. Please try again.', 'ai-faq-generator' ) );
		}

		$clean = array();
		foreach ( $faqs as $item ) {
			if ( ! empty( $item['question'] ) && ! empty( $item['answer'] ) ) {
				$clean[] = array(
					'question' => sanitize_text_field( $item['question'] ),
					'answer'   => wp_kses_post( $item['answer'] ),
				);
			}
		}

		if ( empty( $clean ) ) {
			return new WP_Error( 'no_faqs', __( 'No valid FAQs returned by Gemini. Please try again.', 'ai-faq-generator' ) );
		}

		return $clean;
	}

	/**
	 * Extract and validate the Q&A array from the API response.
	 */
	private function parse_response( $data ) {
		$text = isset( $data['choices'][0]['message']['content'] ) ? trim( $data['choices'][0]['message']['content'] ) : '';

		if ( empty( $text ) ) {
			return new WP_Error( 'empty_response', __( 'AI returned an empty response. Please try again.', 'ai-faq-generator' ) );
		}

		// Strip any accidental markdown code fences.
		$text = preg_replace( '/^```(?:json)?\s*/i', '', $text );
		$text = preg_replace( '/\s*```$/', '', $text );

		$faqs = json_decode( $text, true );

		if ( ! is_array( $faqs ) || empty( $faqs ) ) {
			return new WP_Error( 'parse_error', __( 'Could not parse FAQ response. Please try again.', 'ai-faq-generator' ) );
		}

		$clean = array();
		foreach ( $faqs as $item ) {
			if ( ! empty( $item['question'] ) && ! empty( $item['answer'] ) ) {
				$clean[] = array(
					'question' => sanitize_text_field( $item['question'] ),
					'answer'   => wp_kses_post( $item['answer'] ),
				);
			}
		}

		if ( empty( $clean ) ) {
			return new WP_Error( 'no_faqs', __( 'No valid FAQs were returned. Please try again.', 'ai-faq-generator' ) );
		}

		return $clean;
	}
}
