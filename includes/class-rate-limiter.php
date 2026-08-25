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

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE bucket_key = %s AND window_start < %d",
				$key,
				$start
			)
		);

		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (bucket_key, window_start, request_count) VALUES (%s, %d, 1)
				ON DUPLICATE KEY UPDATE request_count = request_count + 1",
				$key,
				$start
			)
		);

		if ( false === $inserted ) {
			// Fail closed when the limiter cannot be updated.
			return false;
		}

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT request_count FROM {$table} WHERE bucket_key = %s AND window_start = %d",
				$key,
				$start
			)
		);

		return $count <= self::limit( $bucket, $default_limit );
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

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE window_start < %d",
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
