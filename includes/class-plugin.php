<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_FQ_Plugin {

	public static function init() {
		AI_FQ_Rate_Limiter::init();
		AI_FQ_Stats::init();
		AI_FQ_Admin::init();
		AI_FQ_Dashboard::init();
		AI_FQ_REST_API::init();
		AI_FQ_Frontend::init();
	}

	public static function activate() {
		AI_FQ_Rate_Limiter::create_table();
		AI_FQ_Stats::create_table();
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'ai_fq_cleanup_rate_limits' );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ai_fq_cleanup_rate_limits' );
		}
	}

	/**
	 * '.min' normally, '' when SCRIPT_DEBUG is on.
	 *
	 * WordPress also uses this value to locate the RTL stylesheet, so it has to
	 * be handed to wp_style_add_data() as the 'suffix' extra.
	 *
	 * @return string
	 */
	public static function asset_suffix() {
		return ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
	}

	/**
	 * Full URL of a stylesheet, pointing at the minified build unless
	 * SCRIPT_DEBUG asks for the readable source.
	 *
	 * @param string $handle_file File name without extension or suffix.
	 * @return string
	 */
	public static function style_url( $handle_file ) {
		$suffix = self::asset_suffix();
		$dir    = '' === $suffix ? 'assets/css/' : 'assets/min-css/';

		return AI_FQ_URL . $dir . $handle_file . $suffix . '.css';
	}

	/**
	 * Full URL of a script, minified unless SCRIPT_DEBUG is on.
	 *
	 * @param string $handle_file File name without extension or suffix.
	 * @return string
	 */
	public static function script_url( $handle_file ) {
		$suffix = self::asset_suffix();
		$dir    = '' === $suffix ? 'assets/js/' : 'assets/min-js/';

		return AI_FQ_URL . $dir . $handle_file . $suffix . '.js';
	}

	/**
	 * Point a registered style at its -rtl counterpart on RTL locales.
	 *
	 * @param string $handle Registered style handle.
	 */
	public static function add_rtl( $handle ) {
		wp_style_add_data( $handle, 'rtl', 'replace' );
		wp_style_add_data( $handle, 'suffix', self::asset_suffix() );
	}
}
