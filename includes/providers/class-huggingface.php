<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_FQ_HuggingFace_Provider implements AI_FQ_Provider_Interface {

	public function generate_question() {
		$token = defined( 'AI_FQ_HF_TOKEN' ) ? trim( AI_FQ_HF_TOKEN ) : trim( get_option( 'ai_fq_hf_token', '' ) );
		$model = trim( get_option( 'ai_fq_hf_model', 'Qwen/Qwen3-4B-Instruct-2507' ) );

		if ( empty( $token ) || empty( $model ) ) {
			return new WP_Error(
				'ai_fq_hf_config',
				__( 'The Hugging Face configuration is invalid.', 'ai-fun-questions' )
			);
		}

		$response = wp_remote_post(
			'https://router.huggingface.co/v1/chat/completions',
			array(
				'timeout'     => 30,
				'redirection' => 2,
				'headers'     => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
				'body'        => wp_json_encode(
					AI_FQ_Question_Generator::request_body( $model )
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::log_error( $response->get_error_message() );
			return self::public_error();
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 || empty( $data['choices'][0]['message']['content'] ) ) {
			self::log_error( 'Hugging Face returned an unusable response. HTTP status: ' . $status );
			return self::public_error();
		}

		return AI_FQ_Question_Generator::normalize_response(
			$data['choices'][0]['message']['content']
		);
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
