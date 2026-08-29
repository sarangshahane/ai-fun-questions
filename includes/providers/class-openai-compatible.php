<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_FQ_OpenAI_Compatible_Provider implements AI_FQ_Provider_Interface {

	public function generate_question() {
		$api_key = defined( 'AI_FQ_OPENAI_KEY' ) ? trim( AI_FQ_OPENAI_KEY ) : trim( get_option( 'ai_fq_openai_key', '' ) );

		/*
		 * A key defined in wp-config.php stays out of the database, but the
		 * endpoint it is sent to is still a database row — anyone able to write
		 * that option would receive the key. When the key comes from
		 * wp-config.php the destination must come from there too.
		 */
		if ( defined( 'AI_FQ_OPENAI_KEY' ) ) {
			$endpoint = defined( 'AI_FQ_OPENAI_ENDPOINT' )
				? trim( AI_FQ_OPENAI_ENDPOINT )
				: 'https://api.openai.com/v1/chat/completions';
		} else {
			$endpoint = trim( get_option( 'ai_fq_openai_endpoint', 'https://api.openai.com/v1/chat/completions' ) );
		}
		$model    = trim( get_option( 'ai_fq_openai_model', 'gpt-4o-mini' ) );

		if ( ! wp_http_validate_url( $endpoint ) || empty( $api_key ) || empty( $model ) ) {
			return new WP_Error(
				'ai_fq_openai_config',
				__( 'The OpenAI-compatible configuration is invalid.', 'ai-fun-questions' )
			);
		}

		$body = AI_FQ_Question_Generator::request_body( $model );
		$body['response_format'] = array( 'type' => 'json_object' );

		/*
		 * wp_safe_remote_post() sets reject_unsafe_urls, which validates the
		 * target on every redirect hop. wp_remote_post() validates nothing, so
		 * a 302 from the configured endpoint would follow into private address
		 * space — cloud metadata included.
		 */
		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'timeout'     => 30,
				'redirection' => 2,
				'headers'     => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'        => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::log_error( $response->get_error_message() );
			return self::public_error();
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 || empty( $data['choices'][0]['message']['content'] ) ) {
			self::log_error( 'OpenAI-compatible provider returned an unusable response. HTTP status: ' . $status );
			return self::public_error();
		}

		return AI_FQ_Question_Generator::normalize_response(
			$data['choices'][0]['message']['content'],
			array(
				'in'  => isset( $data['usage']['prompt_tokens'] ) ? (int) $data['usage']['prompt_tokens'] : 0,
				'out' => isset( $data['usage']['completion_tokens'] ) ? (int) $data['usage']['completion_tokens'] : 0,
			)
		);
	}

	private static function public_error() {
		return new WP_Error(
			'ai_fq_provider_error',
			__( 'The AI service is temporarily unavailable. Please try again.', 'ai-fun-questions' )
		);
	}

	private static function log_error( $message ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		/*
		 * Provider failures are never shown to visitors, so the reason has to
		 * go somewhere a site owner can find it. Debug builds only.
		 */
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Diagnostics behind WP_DEBUG_LOG.
		error_log( '[AI Fun Questions] ' . wp_strip_all_tags( $message ) );
	}
}
