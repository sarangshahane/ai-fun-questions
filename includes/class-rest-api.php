<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_FQ_REST_API {

	const NAMESPACE = 'ai-fun-questions/v1';
	const TOKEN_TTL = 10 * MINUTE_IN_SECONDS;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/question',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'generate_question' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/answer',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'submit_answer' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'answer' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);
	}

	public static function generate_question( WP_REST_Request $request ) {
		$widget_token = $request->get_header( 'X-AI-FQ-Widget' );

		if ( ! self::valid_token( $widget_token ) ) {
			return new WP_Error(
				'ai_fq_invalid_widget',
				__( 'Invalid question widget token.', 'ai-fun-questions' ),
				array( 'status' => 400 )
			);
		}

		/*
		 * The widget token is supplied by the client, so it must not take part
		 * in the bucket key: rotating it would hand the caller a fresh quota.
		 */
		$bucket = 'generate|' . self::client_hash();

		if ( ! AI_FQ_Rate_Limiter::allow( $bucket ) ) {
			return new WP_Error(
				'ai_fq_rate_limited',
				__( 'Please wait before requesting another question.', 'ai-fun-questions' ),
				array(
					'status'      => 429,
					'retry_after' => AI_FQ_Rate_Limiter::WINDOW,
				)
			);
		}

		$question = AI_FQ_Question_Generator::generate();

		if ( is_wp_error( $question ) ) {
			return $question;
		}

		$question_token = wp_generate_password( 48, false, false );

		set_transient(
			self::question_key( $question_token ),
			array(
				'question'     => $question,
				'widget_hash'  => hash_hmac( 'sha256', $widget_token, wp_salt( 'auth' ) ),
				'client_hash'  => self::client_hash(),
				'created'      => time(),
				'revealed'     => false,
			),
			self::TOKEN_TTL
		);

		return rest_ensure_response(
			array(
				'token'    => $question_token,
				'question' => $question['question'],
				'category' => $question['category'],
				'hint'     => $question['hint'],
			)
		);
	}

	public static function submit_answer( WP_REST_Request $request ) {
		$token        = $request->get_param( 'token' );
		$answer       = trim( (string) $request->get_param( 'answer' ) );
		$widget_token = $request->get_header( 'X-AI-FQ-Widget' );

		if ( ! self::valid_token( $token ) || ! self::valid_token( $widget_token ) ) {
			return new WP_Error(
				'ai_fq_invalid_token',
				__( 'Invalid question token.', 'ai-fun-questions' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $answer ) {
			return new WP_Error(
				'ai_fq_empty_answer',
				__( 'Please enter an answer first.', 'ai-fun-questions' ),
				array( 'status' => 400 )
			);
		}

		if ( mb_strlen( $answer ) > 1000 ) {
			return new WP_Error(
				'ai_fq_answer_too_long',
				__( 'Your answer is too long.', 'ai-fun-questions' ),
				array( 'status' => 400 )
			);
		}

		if ( ! AI_FQ_Rate_Limiter::allow( 'answer|' . self::client_hash() ) ) {
			return new WP_Error(
				'ai_fq_rate_limited',
				__( 'Please wait before submitting another answer.', 'ai-fun-questions' ),
				array(
					'status'      => 429,
					'retry_after' => AI_FQ_Rate_Limiter::WINDOW,
				)
			);
		}

		$data = get_transient( self::question_key( $token ) );

		if ( empty( $data ) || ! is_array( $data ) ) {
			return new WP_Error(
				'ai_fq_question_expired',
				__( 'This question has expired. Please request a new one.', 'ai-fun-questions' ),
				array( 'status' => 404 )
			);
		}

		$expected_widget_hash = hash_hmac( 'sha256', $widget_token, wp_salt( 'auth' ) );

		if (
			empty( $data['widget_hash'] ) ||
			empty( $data['client_hash'] ) ||
			! is_string( $data['widget_hash'] ) ||
			! is_string( $data['client_hash'] ) ||
			! hash_equals( $data['widget_hash'], $expected_widget_hash ) ||
			! hash_equals( $data['client_hash'], self::client_hash() )
		) {
			return new WP_Error(
				'ai_fq_invalid_client',
				__( 'This question belongs to another visitor.', 'ai-fun-questions' ),
				array( 'status' => 403 )
			);
		}

		if ( ! isset( $data['question']['answer'] ) || ! is_string( $data['question']['answer'] ) ) {
			return new WP_Error(
				'ai_fq_question_expired',
				__( 'This question has expired. Please request a new one.', 'ai-fun-questions' ),
				array( 'status' => 404 )
			);
		}

		if ( ! empty( $data['revealed'] ) ) {
			return new WP_Error(
				'ai_fq_already_revealed',
				__( 'This question has already been answered.', 'ai-fun-questions' ),
				array( 'status' => 409 )
			);
		}

		$data['revealed'] = true;

		/*
		 * Keep the answer available briefly so a double-click can return a
		 * deterministic conflict instead of creating a second reveal.
		 */
		set_transient(
			self::question_key( $token ),
			$data,
			2 * MINUTE_IN_SECONDS
		);

		return rest_ensure_response(
			array(
				'your_answer' => $answer,
				'answer'      => $data['question']['answer'],
			)
		);
	}

	private static function question_key( $token ) {
		return 'ai_fq_question_' . hash( 'sha256', $token );
	}

	private static function client_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown';

		return hash_hmac( 'sha256', $ip . '|' . $ua, wp_salt( 'auth' ) );
	}

	private static function valid_token( $token ) {
		return is_string( $token ) && preg_match( '/^[A-Za-z0-9]{32,64}$/', $token );
	}
}
