<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The insight row above the settings form.
 *
 * Everything here is derived, never authored: counters from AI_FQ_Stats, the
 * live limiter state, the saved settings, and one cached query for shortcode
 * placements. Nothing on this screen is a new source of truth, so nothing here
 * can disagree with the rest of the plugin.
 */
class AI_FQ_Dashboard {

	/** Days in the sparkline. */
	const SPARK_DAYS = 14;

	/** How long the shortcode-placement query is cached. */
	const PLACEMENT_TTL = HOUR_IN_SECONDS;

	/** Placements listed by name before falling back to the count alone. */
	const PLACEMENT_LIMIT = 5;

	/** Shortest gap between two connection tests. */
	const TEST_COOLDOWN = 10;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'save_post', array( __CLASS__, 'flush_placements' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush_placements' ) );
	}

	/**
	 * Admin-only route behind the connection test.
	 *
	 * Deliberately not in class-rest-api.php: that module owns the two public
	 * visitor routes and its permission model is "anyone, rate-limited". This
	 * one is the opposite and should not sit next to them.
	 */
	public static function register_routes() {
		register_rest_route(
			'ai-fun-questions/v1',
			'/test-connection',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'test_connection' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	/**
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Runs one real generation against the configured provider.
	 *
	 * A probe that only checks the host answers would report success on an
	 * expired API key, which is the failure people actually have. So this is
	 * the real call — which is why it costs one generation, is never scheduled,
	 * and never runs without a click. The question itself is discarded: no
	 * transient is written and nothing is returned to the browser but the
	 * verdict and the elapsed time.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function test_connection() {
		if ( get_transient( 'ai_fq_test_cooldown' ) ) {
			return new WP_Error(
				'ai_fq_test_cooldown',
				__( 'Please wait a moment before testing again.', 'ai-fun-questions' ),
				array( 'status' => 429 )
			);
		}

		set_transient( 'ai_fq_test_cooldown', 1, self::TEST_COOLDOWN );

		$started  = microtime( true );
		$question = AI_FQ_Question_Generator::generate();
		$elapsed  = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $question ) ) {
			return rest_ensure_response(
				array(
					'ok'      => false,
					/*
					 * An administrator is the only caller here, so the reason
					 * is useful rather than a disclosure — this is the one
					 * place the provider message is allowed out.
					 */
					'message' => wp_strip_all_tags( $question->get_error_message() ),
				)
			);
		}

		return rest_ensure_response(
			array(
				'ok' => true,
				/* translators: %s: round-trip time in milliseconds. */
				'message' => sprintf( __( 'Reachable · %s ms', 'ai-fun-questions' ), number_format_i18n( $elapsed ) ),
			)
		);
	}

	/**
	 * What this site pays per million tokens, as an input/output pair.
	 *
	 * Read from the settings, not from a table shipped in this file. There is
	 * no pricing API to call, and a hardcoded list is stale the day a provider
	 * changes it — so the only number the plugin can honestly show is the one
	 * the site owner entered. Zero means "not told", and the spend tile then
	 * reports token counts rather than a currency figure.
	 *
	 * @return float[] array( input, output )
	 */
	public static function prices() {
		$provider = self::provider_summary();

		/*
		 * Keyed per provider, like the credentials: every provider panel stays
		 * in the DOM so switching does not drop saved values, which means one
		 * shared option name would be submitted twice and the hidden panel's
		 * empty box would win. Ollama runs on the owner's own hardware and has
		 * no per-token price to state.
		 */
		switch ( $provider['key'] ) {
			case 'openai':
				$prefix = 'ai_fq_openai_price';
				break;
			case 'huggingface':
				$prefix = 'ai_fq_hf_price';
				break;
			default:
				return apply_filters( 'ai_fq_token_prices', array( 0.0, 0.0 ) );
		}

		$prices = array(
			(float) get_option( $prefix . '_in', 0 ),
			(float) get_option( $prefix . '_out', 0 ),
		);

		/**
		 * Filters the per-million-token prices used for the spend estimate.
		 *
		 * @param float[] $prices array( input, output ).
		 */
		return apply_filters( 'ai_fq_token_prices', $prices );
	}

	/**
	 * The active provider, its model, and where its credential comes from.
	 *
	 * @return array
	 */
	public static function provider_summary() {
		$providers = AI_FQ_Admin::providers();
		$active    = get_option( 'ai_fq_provider', 'ollama' );
		$active    = isset( $providers[ $active ] ) ? $active : 'ollama';

		switch ( $active ) {
			case 'openai':
				$model  = trim( (string) get_option( 'ai_fq_openai_model', 'gpt-4o-mini' ) );
				$source = defined( 'AI_FQ_OPENAI_KEY' )
					? __( 'key from wp-config.php', 'ai-fun-questions' )
					: __( 'key stored in the database', 'ai-fun-questions' );
				$secure = defined( 'AI_FQ_OPENAI_KEY' );
				break;

			case 'huggingface':
				$model  = trim( (string) get_option( 'ai_fq_hf_model', '' ) );
				$source = defined( 'AI_FQ_HF_TOKEN' )
					? __( 'token from wp-config.php', 'ai-fun-questions' )
					: __( 'token stored in the database', 'ai-fun-questions' );
				$secure = defined( 'AI_FQ_HF_TOKEN' );
				break;

			default:
				$model  = trim( (string) get_option( 'ai_fq_ollama_model', '' ) );
				$source = __( 'no credential needed', 'ai-fun-questions' );
				$secure = true;
				break;
		}

		return array(
			'key'    => $active,
			'label'  => isset( $providers[ $active ]['label'] ) ? $providers[ $active ]['label'] : $active,
			'model'  => $model,
			'source' => $source,
			'secure' => $secure,
		);
	}

	/**
	 * Posts and pages whose content contains the shortcode.
	 *
	 * One LIKE query, cached for an hour and dropped whenever a post is saved
	 * or deleted. Only post content is searched: shortcodes placed in widgets
	 * or templates are real but not discoverable this cheaply, so the widget
	 * says "posts and pages" rather than claiming to know every placement.
	 *
	 * @return array
	 */
	public static function placements() {
		$cached = get_transient( 'ai_fq_shortcode_locations' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$like = '%' . $wpdb->esc_like( '[ai_fun_question' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP_Query cannot search post_content for a literal; the result is cached in a transient immediately below.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_type FROM {$wpdb->posts}
				 WHERE post_status = 'publish' AND post_content LIKE %s
				 ORDER BY post_modified DESC
				 LIMIT %d",
				$like,
				self::PLACEMENT_LIMIT + 1
			),
			ARRAY_A
		);

		$items = array();

		foreach ( (array) $rows as $row ) {
			$object = get_post_type_object( $row['post_type'] );

			$items[] = array(
				'id'    => (int) $row['ID'],
				'title' => '' !== trim( (string) $row['post_title'] )
					? $row['post_title']
					: __( '(no title)', 'ai-fun-questions' ),
				'type'  => $object ? $object->labels->singular_name : $row['post_type'],
				'link'  => (string) get_edit_post_link( (int) $row['ID'] ),
			);
		}

		/*
		 * Counted, not inferred from the capped list. Listing five and saying
		 * "the five most recent" told the reader nothing about how many there
		 * actually are, which is the one number this tile exists to give.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Counts the same LIKE as above; cached in the transient below.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s",
				$like
			)
		);

		$result = array(
			'items' => array_slice( $items, 0, self::PLACEMENT_LIMIT ),
			'total' => $total,
		);

		set_transient( 'ai_fq_shortcode_locations', $result, self::PLACEMENT_TTL );

		return $result;
	}

	public static function flush_placements() {
		delete_transient( 'ai_fq_shortcode_locations' );
	}

	/**
	 * Configuration health, worst finding first.
	 *
	 * @return array
	 */
	public static function health() {
		$provider = self::provider_summary();
		$checks   = array();

		$checks[] = array(
			'ok'   => $provider['secure'],
			'text' => $provider['secure']
				? __( 'Credential from <b>wp-config.php</b>', 'ai-fun-questions' )
				: __( 'Credential stored in the <b>database</b>', 'ai-fun-questions' ),
		);

		$endpoint = self::endpoint( $provider['key'] );

		$https = 0 === stripos( trim( (string) $endpoint ), 'https://' );

		$checks[] = array(
			'ok'   => $https,
			'text' => $https
				? __( 'Endpoint uses <b>https</b>', 'ai-fun-questions' )
				: __( 'Endpoint is not <b>https</b>', 'ai-fun-questions' ),
		);

		/*
		 * Without this filter the limiter sees the proxy's address on every
		 * request behind a CDN, which collapses every visitor into one bucket.
		 * Worth surfacing: the plugin cannot detect the proxy itself.
		 */
		$has_ip_filter = has_filter( 'ai_fq_client_ip' );

		$checks[] = array(
			'ok'   => (bool) $has_ip_filter,
			'text' => $has_ip_filter
				? __( '<b>ai_fq_client_ip</b> filter in place', 'ai-fun-questions' )
				: __( 'No <b>ai_fq_client_ip</b> filter &mdash; limits collapse behind a proxy', 'ai-fun-questions' ),
		);

		/*
		 * Left in the order they are declared — credential, transport, then
		 * proxy — because that reads as a checklist of the same three things
		 * every time. Sorting failures to the top would move rows around
		 * between visits for no gain on a list of three.
		 */
		return $checks;
	}

	/**
	 * The URL the active provider will actually post to.
	 *
	 * Read from the same places the provider reads, including the wp-config
	 * pairing rule, so the transport check reports on the real request rather
	 * than on a second copy of the string that could drift away from it.
	 *
	 * @param string $key Provider key.
	 * @return string
	 */
	public static function endpoint( $key ) {
		switch ( $key ) {
			case 'openai':
				/*
				 * A key from wp-config.php forces the endpoint to come from
				 * wp-config.php too; the provider ignores the stored option in
				 * that case, so reading it here would report the wrong host.
				 */
				if ( defined( 'AI_FQ_OPENAI_KEY' ) ) {
					return defined( 'AI_FQ_OPENAI_ENDPOINT' )
						? (string) AI_FQ_OPENAI_ENDPOINT
						: 'https://api.openai.com/v1/chat/completions';
				}

				return (string) get_option( 'ai_fq_openai_endpoint', 'https://api.openai.com/v1/chat/completions' );

			case 'huggingface':
				return AI_FQ_HuggingFace_Provider::ENDPOINT;

			default:
				return (string) get_option( 'ai_fq_ollama_url', 'http://localhost:11434/api/chat' );
		}
	}

	/**
	 * Spend for the current month, or token counts when no price is known.
	 *
	 * @return array
	 */
	public static function spend() {
		$provider = self::provider_summary();
		$in       = AI_FQ_Stats::this_month( AI_FQ_Stats::TOKENS_IN );
		$out      = AI_FQ_Stats::this_month( AI_FQ_Stats::TOKENS_OUT );

		list( $price_in, $price_out ) = self::prices();

		if ( $price_in <= 0 && $price_out <= 0 ) {
			return array(
				'priced' => false,
				'tokens' => $in + $out,
				'model'  => $provider['model'],
			);
		}

		return array(
			'priced'    => true,
			'amount'    => ( ( $in / 1000000 ) * $price_in ) + ( ( $out / 1000000 ) * $price_out ),
			'price_in'  => $price_in,
			'price_out' => $price_out,
			'model'     => $provider['model'],
		);
	}

	/**
	 * A polyline for the sparkline, scaled to the series' own maximum.
	 *
	 * @param int[] $series Counts, oldest first.
	 * @param int   $width  viewBox width.
	 * @param int   $height viewBox height.
	 * @return array Points string and the last point's coordinates.
	 */
	public static function spark_points( array $series, $width = 240, $height = 34 ) {
		$count = count( $series );

		if ( $count < 2 ) {
			$series = array( 0, 0 );
			$count  = 2;
		}

		$max = max( $series );
		/*
		 * A flat series has no range to scale against. Pinning it to the floor
		 * rather than dividing by zero draws the honest picture: nothing yet.
		 */
		$max = $max > 0 ? $max : 1;

		$top    = 3;
		$bottom = $height - 3;
		$points = array();

		foreach ( $series as $index => $value ) {
			$x = ( $index / ( $count - 1 ) ) * $width;
			$y = $bottom - ( ( $value / $max ) * ( $bottom - $top ) );

			$points[] = round( $x, 1 ) . ',' . round( $y, 1 );
		}

		$last = explode( ',', end( $points ) );

		return array(
			'points' => implode( ' ', $points ),
			'last_x' => (float) $last[0],
			'last_y' => (float) $last[1],
		);
	}

	/**
	 * The whole insight row: four compact tiles, then three wider ones.
	 *
	 * Printed straight rather than assembled from a component helper — there is
	 * one caller and seven tiles, and each tile's body is a different shape.
	 */
	public static function render() {
		$provider = self::provider_summary();
		$today    = AI_FQ_Stats::today( AI_FQ_Stats::GENERATED );
		$series   = AI_FQ_Stats::daily_series( AI_FQ_Stats::GENERATED, self::SPARK_DAYS );
		$spark    = self::spark_points( $series );
		$fortnight = array_sum( $series );

		$used  = AI_FQ_Rate_Limiter::used( 'generate-global' );
		$limit = AI_FQ_Rate_Limiter::limit( 'generate-global', AI_FQ_Rate_Limiter::GLOBAL_LIMIT );
		$share = $limit > 0 ? min( 100, ( $used / $limit ) * 100 ) : 0;

		$spend       = self::spend();
		$by_limit    = AI_FQ_Stats::rolling( AI_FQ_Stats::REFUSED_LIMIT, DAY_IN_SECONDS );
		$by_error    = AI_FQ_Stats::rolling( AI_FQ_Stats::REFUSED_ERROR, DAY_IN_SECONDS );
		$placements  = self::placements();
		?>
		<div class="ai-fq-widgets">
			<div class="ai-fq-wgrid">

				<div class="ai-fq-w">
					<span class="ai-fq-w__label"><?php esc_html_e( 'Questions today', 'ai-fun-questions' ); ?></span>
					<span class="ai-fq-w__value"><?php echo esc_html( number_format_i18n( $today ) ); ?></span>
					<svg class="ai-fq-spark" viewBox="0 0 240 34" preserveAspectRatio="none" role="img" aria-label="<?php esc_attr_e( 'Questions generated per day over the last 14 days', 'ai-fun-questions' ); ?>">
						<polyline points="<?php echo esc_attr( $spark['points'] ); ?>" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"></polyline>
						<circle cx="<?php echo esc_attr( min( 237.4, $spark['last_x'] ) ); ?>" cy="<?php echo esc_attr( $spark['last_y'] ); ?>" r="2.6" fill="currentColor"></circle>
					</svg>
					<span class="ai-fq-w__meta">
						<?php
						printf(
							/* translators: %s: number of questions. */
							esc_html__( 'Last 14 days · %s total', 'ai-fun-questions' ),
							esc_html( number_format_i18n( $fortnight ) )
						);
						?>
					</span>
				</div>

				<div class="ai-fq-w">
					<span class="ai-fq-w__label"><?php esc_html_e( 'This minute', 'ai-fun-questions' ); ?></span>
					<span class="ai-fq-w__value">
						<?php echo esc_html( number_format_i18n( $used ) ); ?>
						<small>
							<?php
							printf(
								/* translators: %s: site-wide per-minute ceiling. */
								esc_html__( 'of %s', 'ai-fun-questions' ),
								esc_html( number_format_i18n( $limit ) )
							);
							?>
						</small>
					</span>
					<div class="ai-fq-meter" role="img" aria-label="<?php esc_attr_e( 'Share of the site-wide per-minute ceiling in use', 'ai-fun-questions' ); ?>">
						<i style="width:<?php echo esc_attr( round( $share, 1 ) ); ?>%"></i>
					</div>
					<span class="ai-fq-w__meta"><?php esc_html_e( 'Site-wide ceiling, resets each minute', 'ai-fun-questions' ); ?></span>
				</div>

				<div class="ai-fq-w">
					<span class="ai-fq-w__label"><?php esc_html_e( 'Estimated spend', 'ai-fun-questions' ); ?></span>
					<?php if ( $spend['priced'] ) : ?>
						<?php /* No currency symbol: the rate came from a settings field, and the plugin is not told which currency it is in. */ ?>
						<span class="ai-fq-w__value"><?php echo esc_html( number_format_i18n( $spend['amount'], 2 ) ); ?></span>
						<span class="ai-fq-w__meta">
							<?php
							printf(
								/* translators: 1: model name, 2: input price, 3: output price. */
								esc_html__( 'This month · %1$s at %2$s / %3$s per 1M', 'ai-fun-questions' ),
								esc_html( $spend['model'] ),
								esc_html( number_format_i18n( $spend['price_in'], 2 ) ),
								esc_html( number_format_i18n( $spend['price_out'], 2 ) )
							);
							?>
						</span>
					<?php else : ?>
						<span class="ai-fq-w__value"><?php echo esc_html( number_format_i18n( $spend['tokens'] ) ); ?> <small><?php esc_html_e( 'tokens', 'ai-fun-questions' ); ?></small></span>
						<span class="ai-fq-w__meta">
							<?php
							esc_html_e( 'This month · set a token price below to see an estimate', 'ai-fun-questions' );
							?>
						</span>
					<?php endif; ?>
				</div>

				<div class="ai-fq-w">
					<span class="ai-fq-w__label"><?php esc_html_e( 'Refused, 24 h', 'ai-fun-questions' ); ?></span>
					<span class="ai-fq-w__value"><?php echo esc_html( number_format_i18n( $by_limit + $by_error ) ); ?></span>
					<span class="ai-fq-w__meta">
						<?php
						printf(
							/* translators: 1: rate-limited count, 2: provider-error count. */
							esc_html__( '%1$s rate-limited · %2$s provider errors', 'ai-fun-questions' ),
							esc_html( number_format_i18n( $by_limit ) ),
							esc_html( number_format_i18n( $by_error ) )
						);
						?>
					</span>
				</div>
			</div>

			<div class="ai-fq-wgrid ai-fq-wgrid--wide">

				<div class="ai-fq-w">
					<span class="ai-fq-w__label"><?php esc_html_e( 'Provider', 'ai-fun-questions' ); ?></span>
					<span class="ai-fq-w__value ai-fq-w__value--text"><?php echo esc_html( $provider['label'] ); ?></span>
					<span class="ai-fq-w__meta">
						<?php
						echo esc_html(
							'' !== $provider['model']
								? $provider['model'] . ' · ' . $provider['source']
								: $provider['source']
						);
						?>
					</span>
					<div class="ai-fq-w__action">
						<button type="button" class="ai-fq-button ai-fq-button--ghost" data-ai-fq-test>
							<?php esc_html_e( 'Test connection', 'ai-fun-questions' ); ?>
						</button>
						<span class="ai-fq-w__meta" data-ai-fq-test-result aria-live="polite"></span>
					</div>
				</div>

				<div class="ai-fq-w">
					<span class="ai-fq-w__label"><?php esc_html_e( 'Where it appears', 'ai-fun-questions' ); ?></span>
					<?php if ( empty( $placements['items'] ) ) : ?>
						<span class="ai-fq-w__meta"><?php esc_html_e( 'No published post or page contains the shortcode yet.', 'ai-fun-questions' ); ?></span>
					<?php else : ?>
						<ul class="ai-fq-where">
							<?php foreach ( $placements['items'] as $item ) : ?>
								<li>
									<?php if ( '' !== $item['link'] ) : ?>
										<a href="<?php echo esc_url( $item['link'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
									<?php else : ?>
										<span><?php echo esc_html( $item['title'] ); ?></span>
									<?php endif; ?>
									<span><?php echo esc_html( $item['type'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
						<span class="ai-fq-w__meta">
							<?php
							$total = (int) $placements['total'];

							/* translators: %s: number of posts and pages. */
							$label = _n(
								'%s post or page contains the shortcode',
								'%s posts and pages contain the shortcode',
								$total,
								'ai-fun-questions'
							);

							printf(
								esc_html( $label ),
								esc_html( number_format_i18n( $total ) )
							);

							$hidden = $total - count( $placements['items'] );

							if ( $hidden > 0 ) {
								echo ' ';
								printf(
									/* translators: %s: number of placements not listed. */
									esc_html__( '(%s not listed)', 'ai-fun-questions' ),
									esc_html( number_format_i18n( $hidden ) )
								);
							}
							?>
						</span>
					<?php endif; ?>
				</div>

				<div class="ai-fq-w">
					<span class="ai-fq-w__label"><?php esc_html_e( 'Configuration', 'ai-fun-questions' ); ?></span>
					<ul class="ai-fq-health">
						<?php foreach ( self::health() as $check ) : ?>
							<li>
								<?php if ( $check['ok'] ) : ?>
									<svg class="ai-fq-health__ok" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 5 5 9-11"></path></svg>
								<?php else : ?>
									<svg class="ai-fq-health__warn" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 7v7"></path><circle cx="12" cy="17.5" r="1.1" fill="currentColor" stroke="none"></circle><path d="M12 2.8 22 20H2z" stroke-linejoin="round"></path></svg>
								<?php endif; ?>
								<span><?php echo wp_kses( $check['text'], array( 'b' => array() ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}
}
