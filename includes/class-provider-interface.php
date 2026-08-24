<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface AI_FQ_Provider_Interface {

	/**
	 * Generate one question.
	 *
	 * @return array|\WP_Error
	 */
	public function generate_question();
}
