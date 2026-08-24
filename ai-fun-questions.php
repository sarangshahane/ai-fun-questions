<?php
/**
 * Plugin Name: AI Fun Questions
 * Plugin URI:  https://github.com/sarangshahane/ai-fun-questions
 * Description: AI-powered fun tech questions that are generated on demand.
 * Version:     0.3.1
 * Author:      Sarang Shahane
 * Author URI:  https://github.com/sarangshahane
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: ai-fun-questions
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * AI Fun Questions is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * AI Fun Questions is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this plugin. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AI_FQ_VERSION', '0.3.1' );
define( 'AI_FQ_FILE', __FILE__ );
define( 'AI_FQ_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_FQ_URL', plugin_dir_url( __FILE__ ) );

require_once AI_FQ_DIR . 'includes/class-provider-interface.php';
require_once AI_FQ_DIR . 'includes/class-question-generator.php';
require_once AI_FQ_DIR . 'includes/providers/class-ollama.php';
require_once AI_FQ_DIR . 'includes/providers/class-huggingface.php';
require_once AI_FQ_DIR . 'includes/providers/class-openai-compatible.php';
require_once AI_FQ_DIR . 'includes/class-rate-limiter.php';
require_once AI_FQ_DIR . 'includes/class-rest-api.php';
require_once AI_FQ_DIR . 'includes/class-admin.php';
require_once AI_FQ_DIR . 'includes/class-frontend.php';
require_once AI_FQ_DIR . 'includes/class-plugin.php';

add_action(
	'plugins_loaded',
	function () {
		AI_FQ_Plugin::init();
	}
);


register_activation_hook(
	AI_FQ_FILE,
	array( 'AI_FQ_Plugin', 'activate' )
);

register_deactivation_hook(
	AI_FQ_FILE,
	array( 'AI_FQ_Plugin', 'deactivate' )
);
