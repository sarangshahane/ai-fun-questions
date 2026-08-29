<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Deactivation leaves everything in place — only deletion clears up, so a
 * site owner can deactivate and reactivate without losing their provider
 * credentials or topic selection.
 *
 * @package AI_Fun_Questions
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Removes this plugin's data from whichever site is currently active.
 */
function ai_fq_uninstall_site() {
	global $wpdb;

	$options = array(
		'ai_fq_provider',
		'ai_fq_topics',
		'ai_fq_ollama_url',
		'ai_fq_ollama_model',
		'ai_fq_hf_token',
		'ai_fq_hf_model',
		'ai_fq_openai_endpoint',
		'ai_fq_openai_key',
		'ai_fq_openai_model',
		'ai_fq_openai_price_in',
		'ai_fq_openai_price_out',
		'ai_fq_hf_price_in',
		'ai_fq_hf_price_out',
		'ai_fq_stats_db_version',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	/*
	 * Question transients hold punchlines and expire within ten minutes, but
	 * deleting the plugin should not leave rows behind on sites without a
	 * persistent object cache.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Removing the plugin's own transients on uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_ai_fq_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_ai_fq_' ) . '%'
		)
	);

	// Both tables hold nothing but counters — no question or visitor data.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing the plugin's own table on uninstall.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'ai_fq_rate_limits' ) );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing the plugin's own table on uninstall.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'ai_fq_stats' ) );

	delete_transient( 'ai_fq_shortcode_locations' );

	wp_clear_scheduled_hook( 'ai_fq_cleanup_rate_limits' );
}

/*
 * On multisite the options and the table are per-site, so cleaning only the
 * current one orphans data on every other site in the network.
 */
if ( is_multisite() ) {
	$ai_fq_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $ai_fq_sites as $ai_fq_site_id ) {
		switch_to_blog( $ai_fq_site_id );
		ai_fq_uninstall_site();
		restore_current_blog();
	}
} else {
	ai_fq_uninstall_site();
}
