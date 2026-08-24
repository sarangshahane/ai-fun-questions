<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_FQ_Plugin {

	public static function init() {
		AI_FQ_Rate_Limiter::init();
		AI_FQ_Admin::init();
		AI_FQ_REST_API::init();
		AI_FQ_Frontend::init();
	}

	public static function activate() {
		AI_FQ_Rate_Limiter::create_table();
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'ai_fq_cleanup_rate_limits' );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ai_fq_cleanup_rate_limits' );
		}
	}
}
