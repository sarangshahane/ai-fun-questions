<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counters behind the settings-screen dashboard.
 *
 * Counts only. No question text, no answer text, no visitor identifier ever
 * reaches this table — that is what keeps the plugin's first principle intact
 * while still being able to say how many questions were generated yesterday.
 */
class AI_FQ_Stats {

	/** A question was generated and served. */
	const GENERATED = 'generated';

	/** A request was turned away by the rate limiter. */
	const REFUSED_LIMIT = 'refused_limit';

	/** A request reached the provider and the provider failed. */
	const REFUSED_ERROR = 'refused_error';

	/** Prompt tokens reported by the provider. */
	const TOKENS_IN = 'tokens_in';

	/** Completion tokens reported by the provider. */
	const TOKENS_OUT = 'tokens_out';

	/**
	 * How long rows are kept.
	 *
	 * The dashboard's longest window is the current calendar month, which can
	 * be 31 days, and its sparkline needs 14. Sixty-two days covers both with
	 * room for a site whose cron has not run for a while.
	 */
	const RETENTION_DAYS = 62;

	/** Bumped whenever the table definition changes. */
	const DB_VERSION = '1';

	/** Option holding the installed table version. */
	const DB_OPTION = 'ai_fq_stats_db_version';

	public static function init() {
		add_action( 'ai_fq_cleanup_rate_limits', array( __CLASS__, 'cleanup' ) );

		/*
		 * The activation hook only fires on a fresh activation, so a site that
		 * updates the plugin in place would never get this table. Guarded by an
		 * autoloaded option, so the steady-state cost is nothing.
		 */
		if ( self::DB_VERSION !== get_option( self::DB_OPTION ) ) {
			self::create_table();
			update_option( self::DB_OPTION, self::DB_VERSION, false );
		}
	}

	/**
	 * Adds to one counter for the current hour.
	 *
	 * @param string $metric One of the metric constants.
	 * @param int    $count  Amount to add.
	 */
	public static function record( $metric, $count = 1 ) {
		self::record_many( array( $metric => $count ) );
	}

	/**
	 * Adds to several counters.
	 *
	 * One statement per metric, each fully static. Building a multi-row insert
	 * would halve the query count but means composing SQL at runtime, which
	 * both Plugin Check and a human reviewer are right to flag on a plugin
	 * whose other queries are all literal. Three indexed upserts alongside a
	 * thirty-second call to an AI provider is not the cost worth optimising.
	 *
	 * @param array $counts Metric name => amount to add.
	 */
	public static function record_many( array $counts ) {
		global $wpdb;

		$hour  = self::current_hour();
		$table = self::table_name();

		foreach ( $counts as $metric => $count ) {
			$count = (int) $count;

			if ( ! in_array( $metric, self::metrics(), true ) || $count <= 0 ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Counters are written on the public request path; a cached value cannot be incremented.
			$wpdb->query(
				$wpdb->prepare(
					'INSERT INTO %i (stat_hour, metric, stat_count) VALUES (%d, %s, %d)'
					. ' ON DUPLICATE KEY UPDATE stat_count = stat_count + %d',
					$table,
					$hour,
					$metric,
					$count,
					$count
				)
			);
		}
	}

	/**
	 * Total for a metric over a rolling window ending now.
	 *
	 * @param string $metric  Metric name.
	 * @param int    $seconds Length of the window.
	 * @return int
	 */
	public static function rolling( $metric, $seconds ) {
		return self::sum_since( $metric, self::current_hour() - (int) $seconds );
	}

	/**
	 * Total for a metric since local midnight today.
	 *
	 * @param string $metric Metric name.
	 * @return int
	 */
	public static function today( $metric ) {
		return self::sum_since( $metric, self::local_day_start( 0 ) );
	}

	/**
	 * Total for a metric since the first of the current local month.
	 *
	 * @param string $metric Metric name.
	 * @return int
	 */
	public static function this_month( $metric ) {
		$local = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- A local wall-clock stamp is exactly what the month boundary needs.
		$first = (int) strtotime( gmdate( 'Y-m-01 00:00:00', $local ) );

		return self::sum_since( $metric, $first - self::offset() );
	}

	/**
	 * One count per local day, oldest first.
	 *
	 * Returned as a plain list rather than keyed by date: the only consumer is
	 * a sparkline, which wants the shape of the series and nothing else.
	 *
	 * @param string $metric Metric name.
	 * @param int    $days   Number of days including today.
	 * @return int[]
	 */
	public static function daily_series( $metric, $days ) {
		global $wpdb;

		$days  = max( 1, (int) $days );
		$start = self::local_day_start( $days - 1 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate over the plugin's own counter table; the caller caches the result.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT stat_hour, stat_count FROM %i WHERE metric = %s AND stat_hour >= %d',
				self::table_name(),
				(string) $metric,
				$start
			),
			ARRAY_A
		);

		$series = array_fill( 0, $days, 0 );

		foreach ( (array) $rows as $row ) {
			/*
			 * Bucket by local day rather than by UTC day: a site in UTC+5:30
			 * would otherwise see yesterday evening's questions counted as
			 * today's, which is visibly wrong on the sparkline.
			 */
			$index = (int) floor( ( (int) $row['stat_hour'] - $start ) / DAY_IN_SECONDS );

			if ( $index >= 0 && $index < $days ) {
				$series[ $index ] += (int) $row['stat_count'];
			}
		}

		return $series;
	}

	/**
	 * @param string $metric Metric name.
	 * @param int    $since  UTC timestamp to count from, inclusive.
	 * @return int
	 */
	private static function sum_since( $metric, $since ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate over the plugin's own counter table; the caller caches the result.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(stat_count) FROM %i WHERE metric = %s AND stat_hour >= %d',
				self::table_name(),
				(string) $metric,
				(int) $since
			)
		);
	}

	/**
	 * Start of a local day, expressed as a UTC timestamp.
	 *
	 * @param int $days_ago 0 for today.
	 * @return int
	 */
	private static function local_day_start( $days_ago ) {
		$local = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- A local wall-clock stamp is exactly what the day boundary needs.
		$local = $local - ( (int) $days_ago * DAY_IN_SECONDS );
		$midnight = (int) strtotime( gmdate( 'Y-m-d 00:00:00', $local ) );

		return $midnight - self::offset();
	}

	/**
	 * Site timezone offset in seconds. Fractional zones are real — UTC+5:30.
	 *
	 * @return int
	 */
	private static function offset() {
		return (int) round( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );
	}

	/**
	 * @return int
	 */
	private static function current_hour() {
		$now = time();

		return $now - ( $now % HOUR_IN_SECONDS );
	}

	/**
	 * @return string[]
	 */
	public static function metrics() {
		return array(
			self::GENERATED,
			self::REFUSED_LIMIT,
			self::REFUSED_ERROR,
			self::TOKENS_IN,
			self::TOKENS_OUT,
		);
	}

	public static function cleanup() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled pruning of the plugin's own counter table.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE stat_hour < %d',
				self::table_name(),
				time() - ( self::RETENTION_DAYS * DAY_IN_SECONDS )
			)
		);
	}

	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			stat_hour bigint(20) unsigned NOT NULL,
			metric varchar(20) NOT NULL,
			stat_count bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (stat_hour, metric),
			KEY metric_hour (metric, stat_hour)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'ai_fq_stats';
	}
}
