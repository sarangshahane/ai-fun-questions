<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_FQ_Rate_Limiter {

	const WINDOW = 60;
	const LIMIT  = 5;

	/**
	 * Ceiling applied per client IP, above the finer per-client limit.
	 *
	 * The per-client bucket mixes in the User-Agent, which the caller controls,
	 * so rotating it alone would mint a fresh quota. This ceiling is keyed on
	 * the IP only and cannot be rotated away from the same address.
	 */
	const IP_LIMIT = 15;

	/**
	 * Ceiling applied across the whole site, keyed on nothing.
	 *
	 * The per-IP ceiling only bounds one address. It does nothing about an
	 * attacker who spends other people's addresses — a fetch() in any page
	 * they control makes every visitor's browser call the endpoint, each with
	 * a fresh per-IP allowance, and every call costs the site owner money.
	 * This bucket is the only control that bounds that bill.
	 */
	const GLOBAL_LIMIT = 120;

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cleanup_schedule' ) );

		if ( ! wp_next_scheduled( 'ai_fq_cleanup_rate_limits' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'ai_fq_cleanup_rate_limits' );
		}

		add_action( 'ai_fq_cleanup_rate_limits', array( __CLASS__, 'cleanup' ) );
	}

	/**
	 * @param string   $bucket        Bucket key to count against.
	 * @param int|null $default_limit Per-window allowance, defaulting to self::LIMIT.
	 * @return bool
	 */
	public static function allow( $bucket, $default_limit = null ) {
		global $wpdb;

		$table = self::table_name();
		$now   = time();

		/*
		 * Snap to a fixed window so every request inside the same window shares
		 * one row. Deriving the window from the current second would give each
		 * request its own primary key and the counter would never grow.
		 */
		$start = $now - ( $now % self::WINDOW );
		$key   = hash_hmac( 'sha256', (string) $bucket, wp_salt( 'auth' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Rate limiting must read and write on every request; a cached count cannot enforce a limit.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE bucket_key = %s AND window_start < %d',
				$table,
				$key,
				$start - self::WINDOW
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Rate limiting must read and write on every request; a cached count cannot enforce a limit.
		$current = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT request_count FROM %i WHERE bucket_key = %s AND window_start = %d',
				$table,
				$key,
				$start
			)
		);

		/*
		 * Fixed windows let a caller spend a full allowance either side of the
		 * boundary — five at t=59 and five at t=61 is ten calls in two seconds.
		 * Weighting the previous window by how much of it is still in view
		 * gives the standard sliding estimate and closes that burst.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Rate limiting must read and write on every request; a cached count cannot enforce a limit.
		$previous = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT request_count FROM %i WHERE bucket_key = %s AND window_start = %d',
				$table,
				$key,
				$start - self::WINDOW
			)
		);

		$carry    = ( self::WINDOW - ( $now - $start ) ) / self::WINDOW;
		$estimate = $current + 1 + ( $previous * $carry );

		/*
		 * Decide before charging. Charging first and deciding after looks
		 * equivalent, but it means a rejected request still raises the count —
		 * and on a bucket keyed on nothing, such as the site-wide ceiling, one
		 * caller could then hold the count above the limit indefinitely and
		 * deny the feature to everyone. Refused requests must cost nothing.
		 */
		if ( $estimate > self::limit( $bucket, $default_limit ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Rate limiting must read and write on every request; a cached count cannot enforce a limit.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (bucket_key, window_start, request_count) VALUES (%s, %d, 1)'
				. ' ON DUPLICATE KEY UPDATE request_count = request_count + 1',
				$table,
				$key,
				$start
			)
		);

		/*
		 * Read-then-write is not atomic, so a burst of simultaneous requests
		 * can overshoot the limit by a little. That is the right trade against
		 * the alternative above: a small overshoot costs a few extra calls, a
		 * self-inflicted lockout costs the whole feature.
		 */
		// Fail closed when the limiter cannot be updated.
		return false !== $inserted;
	}

	/**
	 * Requests allowed per bucket per window.
	 *
	 * A page may hold several widgets, each firing its own generation request,
	 * so sites that need more headroom can raise this without editing the
	 * plugin. The bucket is passed so a site can limit generation and answer
	 * submissions differently.
	 *
	 * @param string   $bucket  Bucket key the limit is being applied to.
	 * @param int|null $default Allowance before filtering, defaulting to self::LIMIT.
	 * @return int
	 */
	public static function limit( $bucket = '', $default = null ) {
		$default = ( null === $default || (int) $default <= 0 ) ? self::LIMIT : (int) $default;

		$limit = (int) apply_filters( 'ai_fq_rate_limit', $default, $bucket );

		return $limit > 0 ? $limit : $default;
	}

	public static function cleanup() {
		global $wpdb;

		$table  = self::table_name();
		$cutoff = time() - DAY_IN_SECONDS;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled cleanup of the limiter table; nothing to cache.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE window_start < %d',
				$table,
				$cutoff
			)
		);
	}

	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			bucket_key varchar(64) NOT NULL,
			window_start bigint(20) unsigned NOT NULL,
			request_count smallint(5) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (bucket_key, window_start)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'ai_fq_rate_limits';
	}

	public static function add_cleanup_schedule( $schedules ) {
		if ( ! isset( $schedules['hourly'] ) ) {
			$schedules['hourly'] = array(
				'interval' => HOUR_IN_SECONDS,
				'display'  => __( 'Hourly', 'ai-fun-questions' ),
			);
		}

		return $schedules;
	}
}
