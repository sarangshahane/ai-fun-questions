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

		/*
		 * A cross-origin caller is not this site's widget. Browsers always send
		 * Origin on a cross-origin POST, so this rejects a fetch() planted in
		 * someone else's page — the variant that spends other people's IP
		 * allowances — while same-origin widget traffic is unaffected.
		 */
		if ( ! self::same_origin( $request ) ) {
			return new WP_Error(
				'ai_fq_bad_origin',
				__( 'Question requests must come from this site.', 'ai-fun-questions' ),
				array( 'status' => 403 )
			);
		}

		/*
		 * Charge the site-wide ceiling before anything keyed on the caller.
		 * Per-IP limits bound one address; only this bounds the total spend
		 * against the owner's paid AI account.
		 */
		if ( ! AI_FQ_Rate_Limiter::allow( 'generate-global', AI_FQ_Rate_Limiter::GLOBAL_LIMIT ) ) {
			/*
			 * A refused request must never raise a *limit* counter — that was
			 * the lockout bug the sliding-window fix closed. This is a metrics
			 * counter on a table whose row count is bounded by hour and metric,
			 * so it cannot deny anyone service; it costs one indexed upsert.
			 */
			AI_FQ_Stats::record( AI_FQ_Stats::REFUSED_LIMIT );

			return new WP_Error(
				'ai_fq_rate_limited',
				__( 'Please wait before requesting another question.', 'ai-fun-questions' ),
				array(
					'status'      => 429,
					'retry_after' => AI_FQ_Rate_Limiter::WINDOW,
				)
			);
		}

		/*
		 * Charge the per-IP ceiling next. The bucket above mixes in the
		 * User-Agent, so a caller rotating that header alone would otherwise
		 * mint a fresh quota and an unbounded provider bill from one address.
		 */
		if ( ! AI_FQ_Rate_Limiter::allow( 'generate-ip|' . self::ip_hash(), AI_FQ_Rate_Limiter::IP_LIMIT ) ) {
			AI_FQ_Stats::record( AI_FQ_Stats::REFUSED_LIMIT );

			return new WP_Error(
				'ai_fq_rate_limited',
				__( 'Please wait before requesting another question.', 'ai-fun-questions' ),
				array(
					'status'      => 429,
					'retry_after' => AI_FQ_Rate_Limiter::WINDOW,
				)
			);
		}

		if ( ! AI_FQ_Rate_Limiter::allow( $bucket ) ) {
			AI_FQ_Stats::record( AI_FQ_Stats::REFUSED_LIMIT );

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
			/*
			 * The provider-specific message names which service the site uses
			 * and that it is currently broken. Keep that for the log and hand
			 * the visitor something generic.
			 */
			self::log_error( $question->get_error_message() );

			return new WP_Error(
				'ai_fq_unavailable',
				__( 'The AI service is temporarily unavailable. Please try again.', 'ai-fun-questions' ),
				array( 'status' => 503 )
			);
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

		/*
		 * As on generation: the client bucket mixes in the User-Agent, which
		 * the caller controls, so an IP-only ceiling has to be charged too.
		 */
		if ( ! AI_FQ_Rate_Limiter::allow( 'answer-ip|' . self::ip_hash(), AI_FQ_Rate_Limiter::IP_LIMIT ) ) {
			AI_FQ_Stats::record( AI_FQ_Stats::REFUSED_LIMIT );

			return new WP_Error(
				'ai_fq_rate_limited',
				__( 'Please wait before submitting another answer.', 'ai-fun-questions' ),
				array(
					'status'      => 429,
					'retry_after' => AI_FQ_Rate_Limiter::WINDOW,
				)
			);
		}

		if ( ! AI_FQ_Rate_Limiter::allow( 'answer|' . self::client_hash() ) ) {
			AI_FQ_Stats::record( AI_FQ_Stats::REFUSED_LIMIT );

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

	/**
	 * Address the request is attributed to.
	 *
	 * REMOTE_ADDR only. Behind a reverse proxy or CDN that is the proxy, which
	 * collapses every visitor into one bucket, but forwarded headers are
	 * caller-supplied and trusting them by default would be worse than the
	 * collapse. A site that terminates its own proxy can supply the real
	 * address through the filter.
	 *
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';

		$ip = (string) apply_filters( 'ai_fq_client_ip', $ip );

		return '' !== trim( $ip ) ? $ip : 'unknown';
	}

	/**
	 * Per-IP identity, used for the ceiling the User-Agent cannot rotate away.
	 *
	 * @return string
	 */
	private static function ip_hash() {
		return hash_hmac( 'sha256', self::ip_bucket(), wp_salt( 'auth' ) );
	}

	/**
	 * The address range a per-IP ceiling should apply to.
	 *
	 * A single cheap host is routinely handed a routed IPv6 /64, which is
	 * 2^64 source addresses and, without this, 2^64 separate allowances.
	 * Truncating to the /64 makes the whole allocation share one ceiling.
	 *
	 * @return string
	 */
	private static function ip_bucket() {
		$ip = self::client_ip();

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return $ip;
		}

		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Already validated as IPv6.

		if ( false === $packed || 16 !== strlen( $packed ) ) {
			return $ip;
		}

		/*
		 * An IPv4-mapped address (::ffff:192.0.2.1) has an all-zero first half,
		 * so truncating to the /64 would put every IPv4 visitor in one bucket —
		 * which happens routinely when PHP sees REMOTE_ADDR in mapped form.
		 * Unwrap to the embedded IPv4 and bucket on that instead.
		 */
		if ( "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff" === substr( $packed, 0, 12 ) ) {
			return inet_ntop( substr( $packed, 12, 4 ) );
		}

		// First 8 bytes = the /64 prefix.
		return bin2hex( substr( $packed, 0, 8 ) ) . '::/64';
	}

	/**
	 * Same-origin check for state-changing public routes.
	 *
	 * A missing Origin is allowed: non-browser callers do not send one, and
	 * the widget's own same-origin request may omit it in older browsers.
	 *
	 * Compared on host only. An Origin header is always scheme, host and port
	 * with no path, so matching it against home_url() in full would never
	 * succeed on a subdirectory install — and would reject every legitimate
	 * request on those sites. Both home and site hosts are accepted, which
	 * covers WordPress living in a different directory from the front end.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return bool
	 */
	private static function same_origin( WP_REST_Request $request ) {
		$origin = $request->get_header( 'Origin' );

		if ( empty( $origin ) ) {
			return true;
		}

		$origin_host = wp_parse_url( $origin, PHP_URL_HOST );

		if ( empty( $origin_host ) ) {
			return false;
		}

		$allowed = array(
			wp_parse_url( home_url(), PHP_URL_HOST ),
			wp_parse_url( site_url(), PHP_URL_HOST ),
		);

		/**
		 * Filters the hosts allowed to call the public question endpoint.
		 *
		 * Useful where the public host differs from what WordPress knows about,
		 * such as a reverse proxy or a mapped multisite domain.
		 *
		 * @param string[] $allowed Allowed host names.
		 */
		$allowed = (array) apply_filters( 'ai_fq_allowed_origin_hosts', array_filter( $allowed ) );

		return in_array( strtolower( $origin_host ), array_map( 'strtolower', $allowed ), true );
	}

	/**
	 * Debug-only diagnostics; provider errors are never shown to visitors.
	 *
	 * @param string $message Message to record.
	 */
	private static function log_error( $message ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Diagnostics behind WP_DEBUG_LOG.
		error_log( '[AI Fun Questions] ' . wp_strip_all_tags( $message ) );
	}

	private static function client_hash() {
		$ip = self::client_ip();
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown';

		return hash_hmac( 'sha256', $ip . '|' . $ua, wp_salt( 'auth' ) );
	}

	private static function valid_token( $token ) {
		return is_string( $token ) && preg_match( '/^[A-Za-z0-9]{32,64}$/', $token );
	}
}
