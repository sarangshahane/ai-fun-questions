<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_FQ_Ollama_Provider implements AI_FQ_Provider_Interface {

	public function generate_question() {
		$url   = trim( get_option( 'ai_fq_ollama_url', 'http://localhost:11434/api/chat' ) );
		$model = trim( get_option( 'ai_fq_ollama_model', 'gemma3' ) );

		if ( empty( $model ) || ! self::allowed_url( $url ) ) {
			return new WP_Error(
				'ai_fq_ollama_config',
				__( 'The Ollama configuration is invalid.', 'ai-fun-questions' )
			);
		}

		$body = AI_FQ_Question_Generator::request_body( $model );
		$body['format'] = 'json';

		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => 30,
				'redirection' => 2,
				'headers'     => array(
					'Content-Type' => 'application/json',
				),
				'body'        => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::log_error( $response->get_error_message() );
			return self::public_error();
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 || empty( $body['message']['content'] ) ) {
			self::log_error( 'Ollama returned an unusable response. HTTP status: ' . $status );
			return self::public_error();
		}

		return AI_FQ_Question_Generator::normalize_response( $body['message']['content'] );
	}

	private static function allowed_url( $url ) {
		$parts = wp_parse_url( $url );

		if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
			return false;
		}

		if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}

		/*
		 * wp_http_validate_url() rejects loopback and private addresses, which
		 * are exactly what a self-hosted Ollama uses, so the host allowlist
		 * below is the check instead of that helper.
		 */
		$host = strtolower( trim( $parts['host'], '[]' ) );

		/*
		 * Ollama is commonly self-hosted, so private/local addresses are
		 * allowed intentionally. The admin controls the URL; this is not
		 * an arbitrary user-supplied URL.
		 */
		$allowed_hosts = apply_filters(
			'ai_fq_allowed_ollama_hosts',
			array(
				'localhost',
				'127.0.0.1',
				'::1',
			)
		);

		if ( in_array( $host, $allowed_hosts, true ) ) {
			return true;
		}

		return (bool) apply_filters( 'ai_fq_allow_remote_ollama', false, $host );
	}

	private static function public_error() {
		return new WP_Error(
			'ai_fq_provider_error',
			__( 'The AI service is temporarily unavailable. Please try again.', 'ai-fun-questions' )
		);
	}

	private static function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[AI Fun Questions] ' . wp_strip_all_tags( $message ) );
		}
	}
}
