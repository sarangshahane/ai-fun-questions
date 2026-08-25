<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_FQ_Admin {

	const PAGE_SLUG = 'ai-fun-questions';

	/** Sentinel submitted by the model picker when the free-text box is in use. */
	const CUSTOM_MODEL = '__custom';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function add_menu() {
		add_options_page(
			__( 'AI Fun Questions', 'ai-fun-questions' ),
			__( 'AI Fun Questions', 'ai-fun-questions' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Settings-page assets only. Nothing is loaded on other admin screens.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'ai-fun-questions-admin',
			AI_FQ_Plugin::style_url( 'admin' ),
			array(),
			AI_FQ_VERSION
		);

		AI_FQ_Plugin::add_rtl( 'ai-fun-questions-admin' );

		wp_enqueue_script(
			'ai-fun-questions-admin',
			AI_FQ_Plugin::script_url( 'admin' ),
			array(),
			AI_FQ_VERSION,
			true
		);

		wp_localize_script(
			'ai-fun-questions-admin',
			'AI_FQ_ADMIN',
			array(
				'i18n' => array(
					'saved'    => __( 'All changes saved', 'ai-fun-questions' ),
					'unsaved'  => __( 'Unsaved changes', 'ai-fun-questions' ),
					'selected' => __( 'Selected', 'ai-fun-questions' ),
					'inUse'    => __( 'Saved, not in use', 'ai-fun-questions' ),
				),
			)
		);
	}

	public static function register_settings() {
		$fields = array(
			'ai_fq_provider'        => array( __CLASS__, 'sanitize_provider' ),
			'ai_fq_ollama_url'      => array( __CLASS__, 'sanitize_url_field' ),
			'ai_fq_ollama_model'    => array( __CLASS__, 'sanitize_ollama_model' ),
			'ai_fq_hf_token'        => array( __CLASS__, 'sanitize_hf_token' ),
			'ai_fq_hf_model'        => array( __CLASS__, 'sanitize_hf_model' ),
			'ai_fq_openai_endpoint' => array( __CLASS__, 'sanitize_url_field' ),
			'ai_fq_openai_key'      => array( __CLASS__, 'sanitize_openai_key' ),
			'ai_fq_openai_model'    => array( __CLASS__, 'sanitize_openai_model' ),
		);

		foreach ( $fields as $field => $callback ) {
			register_setting(
				'ai_fq_settings',
				$field,
				array(
					'sanitize_callback' => $callback,
				)
			);
		}
	}

	/**
	 * Provider metadata used to build the picker and the per-provider panels.
	 */
	public static function providers() {
		return array(
			'openai'      => array(
				'label'    => __( 'OpenAI-compatible', 'ai-fun-questions' ),
				'subtitle' => __( 'Any OpenAI-shaped API', 'ai-fun-questions' ),
			),
			'huggingface' => array(
				'label'    => __( 'Hugging Face', 'ai-fun-questions' ),
				'subtitle' => __( 'Inference Providers', 'ai-fun-questions' ),
			),
			'ollama'      => array(
				'label'    => __( 'Ollama', 'ai-fun-questions' ),
				'subtitle' => __( 'Local, self-hosted', 'ai-fun-questions' ),
			),
		);
	}

	/**
	 * Suggested models per provider, grouped by what they cost to run.
	 *
	 * Checked against the live catalogues, not from memory: OpenAI's pricing
	 * page, the Hugging Face router (router.huggingface.co/v1/models) and the
	 * Ollama library. Three filters were applied on top of "does it exist":
	 *
	 * - Gated Hugging Face repos (meta-llama, google/gemma) need a licence
	 *   accepted on the Hub first, so they are left out of the list.
	 * - Reasoning models that emit their thinking into the message body break
	 *   the strict-JSON reply this plugin parses. OpenAI's gpt-5 tiers keep
	 *   theirs out of the message, so they are listed; request_body() drops
	 *   temperature for them, which they reject.
	 *
	 * Catalogues move, so this is a convenience list and never a whitelist:
	 * the picker always offers a custom value, and the filter lets a site
	 * replace the list outright.
	 */
	public static function models( $provider ) {
		$models = array(
			'openai'      => array(
				/* translators: Group label in the model dropdown. */
				__( 'Paid — low cost', 'ai-fun-questions' )          => array(
					'gpt-5-nano',
					'gpt-4.1-nano',
					'gpt-4o-mini',
					'gpt-5.6-luna',
					'gpt-5-mini',
					'gpt-4.1-mini',
				),
				/* translators: Group label in the model dropdown. */
				__( 'Paid — higher capability', 'ai-fun-questions' ) => array(
					'gpt-4.1',
					'gpt-5.6-terra',
					'gpt-4o',
					'gpt-5.6-sol',
				),
			),
			'huggingface' => array(
				/*
				 * Every model on the router is metered; the free allowance is
				 * account credit, not a free model, so nothing here is free.
				 */
				/* translators: Group label in the model dropdown. */
				__( 'Metered — lowest cost per token', 'ai-fun-questions' ) => array(
					'Qwen/Qwen3-4B-Instruct-2507',
					'openai/gpt-oss-20b',
					'microsoft/phi-4',
				),
				/* translators: Group label in the model dropdown. */
				__( 'Metered — larger models', 'ai-fun-questions' )         => array(
					'openai/gpt-oss-120b',
					'Qwen/Qwen3-Next-80B-A3B-Instruct',
					'Qwen/Qwen3-235B-A22B-Instruct-2507',
				),
			),
			'ollama'      => array(
				/* translators: Group label in the model dropdown. */
				__( 'Free — runs locally on your own hardware', 'ai-fun-questions' ) => array(
					'gemma3',
					'llama3.2',
					'llama3.1',
					'qwen2.5',
					'mistral',
					'phi4',
					'smollm2',
					'tinyllama',
				),
			),
		);

		$groups = isset( $models[ $provider ] ) ? $models[ $provider ] : array();

		/**
		 * Filters the suggested models offered for a provider.
		 *
		 * @param array  $groups   Group label => list of model identifiers.
		 * @param string $provider Provider key.
		 */
		return apply_filters( 'ai_fq_provider_models', $groups, $provider );
	}

	public static function sanitize_provider( $value ) {
		$value = sanitize_text_field( (string) $value );

		return array_key_exists( $value, self::providers() ) ? $value : 'ollama';
	}

	public static function sanitize_url_field( $value ) {
		return esc_url_raw( trim( (string) $value ) );
	}

	public static function sanitize_hf_token( $value ) {
		return self::sanitize_secret( $value, 'ai_fq_hf_token' );
	}

	public static function sanitize_openai_key( $value ) {
		return self::sanitize_secret( $value, 'ai_fq_openai_key' );
	}

	public static function sanitize_ollama_model( $value ) {
		return self::sanitize_model( $value, 'ai_fq_ollama_model' );
	}

	public static function sanitize_hf_model( $value ) {
		return self::sanitize_model( $value, 'ai_fq_hf_model' );
	}

	public static function sanitize_openai_model( $value ) {
		return self::sanitize_model( $value, 'ai_fq_openai_model' );
	}

	/**
	 * The picker submits either a listed model or the CUSTOM_MODEL marker, in
	 * which case the free-text companion field carries the real value.
	 *
	 * A blank custom box would otherwise wipe a working model name, so it
	 * falls back to what is already stored.
	 */
	private static function sanitize_model( $value, $option ) {
		$value = sanitize_text_field( (string) $value );

		if ( self::CUSTOM_MODEL !== $value ) {
			return $value;
		}

		/*
		 * options.php has already run check_admin_referer() for this settings
		 * group before any sanitize callback fires, so the request is verified
		 * by the time we read the companion field.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$custom = isset( $_POST[ $option . '_custom' ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_text_field( wp_unslash( $_POST[ $option . '_custom' ] ) )
			: '';

		return '' !== trim( $custom ) ? $custom : (string) get_option( $option, '' );
	}

	/**
	 * Keep the stored secret when the (always blank) input is submitted empty.
	 *
	 * A blank field means "leave it alone", so removing a credential needs an
	 * explicit opt-in: the matching _clear checkbox.
	 */
	private static function sanitize_secret( $value, $option ) {
		$value = sanitize_text_field( (string) $value );

		if ( '' !== trim( $value ) ) {
			return $value;
		}

		/*
		 * options.php has already run check_admin_referer() for this settings
		 * group before any sanitize callback fires, so the request is verified
		 * by the time we read the companion checkbox.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST[ $option . '_clear' ] ) ) {
			return '';
		}

		return (string) get_option( $option, '' );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$providers = self::providers();
		$active    = get_option( 'ai_fq_provider', 'ollama' );

		if ( ! isset( $providers[ $active ] ) ) {
			$active = 'ollama';
		}
		?>
		<div class="wrap ai-fq-admin">
			<h1 class="ai-fq-admin__title">
				<span><?php esc_html_e( 'AI Fun Questions', 'ai-fun-questions' ); ?></span>
			</h1>

			<div class="ai-fq-note">
				<?php self::icon( 'info' ); ?>
				<p><?php esc_html_e( 'No question bank is stored. Each question is generated on demand and only the current question is temporarily cached for the visitor session.', 'ai-fun-questions' ); ?></p>
			</div>

			<form method="post" action="options.php" class="ai-fq-form" data-ai-fq-form>
				<?php settings_fields( 'ai_fq_settings' ); ?>

				<div class="ai-fq-form__main">
					<fieldset class="ai-fq-providers">
						<legend class="ai-fq-providers__legend"><?php esc_html_e( 'AI Provider', 'ai-fun-questions' ); ?></legend>

						<div class="ai-fq-providers__grid">
							<?php foreach ( $providers as $key => $provider ) : ?>
								<label class="ai-fq-provider" for="ai-fq-provider-<?php echo esc_attr( $key ); ?>">
									<input
										type="radio"
										class="ai-fq-provider__input"
										id="ai-fq-provider-<?php echo esc_attr( $key ); ?>"
										name="ai_fq_provider"
										value="<?php echo esc_attr( $key ); ?>"
										data-ai-fq-provider-input
										<?php checked( $active, $key ); ?>
									>
									<span class="ai-fq-provider__card">
										<span class="ai-fq-provider__top">
											<span class="ai-fq-provider__icon"><?php self::icon( $key ); ?></span>
											<span class="ai-fq-provider__mark" aria-hidden="true"></span>
										</span>
										<span class="ai-fq-provider__name"><?php echo esc_html( $provider['label'] ); ?></span>
										<span class="ai-fq-provider__subtitle"><?php echo esc_html( $provider['subtitle'] ); ?></span>
									</span>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<?php
					foreach ( $providers as $key => $provider ) {
						self::render_panel( $key, $provider, $active );
					}
					?>
				</div>

				<aside class="ai-fq-form__aside">
					<div class="ai-fq-card ai-fq-card--notes">
						<h2 class="ai-fq-card__title"><?php esc_html_e( 'Production notes', 'ai-fun-questions' ); ?></h2>
						<ul class="ai-fq-notes-list">
							<li><?php esc_html_e( 'The public widget is intentionally unauthenticated. Rate limiting and short-lived tokens protect the generation/answer flow.', 'ai-fun-questions' ); ?></li>
							<li><?php esc_html_e( 'AI provider credentials should preferably be defined in wp-config.php for production sites.', 'ai-fun-questions' ); ?></li>
							<li><?php esc_html_e( 'AI output is validated as plain text and constrained to predefined categories and maximum lengths.', 'ai-fun-questions' ); ?></li>
						</ul>

						<h2 class="ai-fq-card__title"><?php esc_html_e( 'Frontend usage', 'ai-fun-questions' ); ?></h2>
						<p class="ai-fq-card__text"><?php esc_html_e( 'Add this shortcode to any page, post, template, or shortcode-enabled area:', 'ai-fun-questions' ); ?></p>
						<code class="ai-fq-code">[ai_fun_question]</code>
					</div>
				</aside>

				<div class="ai-fq-savebar">
					<span class="ai-fq-savebar__status" data-ai-fq-status>
						<?php esc_html_e( 'All changes saved', 'ai-fun-questions' ); ?>
					</span>
					<button type="submit" class="ai-fq-button">
						<?php esc_html_e( 'Save Changes', 'ai-fun-questions' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * One configuration panel per provider. Only the panel matching the
	 * selected provider is shown; the rest are hidden by CSS but stay in the
	 * form, so switching providers never drops saved credentials on submit.
	 */
	private static function render_panel( $key, $provider, $active ) {
		$is_active = ( $key === $active );
		$classes   = 'ai-fq-panel' . ( $is_active ? ' is-active' : '' );
		?>
		<section class="<?php echo esc_attr( $classes ); ?>" data-ai-fq-panel="<?php echo esc_attr( $key ); ?>">
			<header class="ai-fq-panel__header">
				<h2 class="ai-fq-panel__title"><?php echo esc_html( $provider['label'] ); ?></h2>
				<span class="ai-fq-pill ai-fq-pill--state" data-ai-fq-panel-state>
					<?php
					echo $is_active
						? esc_html__( 'Selected', 'ai-fun-questions' )
						: esc_html__( 'Saved, not in use', 'ai-fun-questions' );
					?>
				</span>
			</header>

			<div class="ai-fq-panel__grid">
				<?php
				switch ( $key ) {
					case 'ollama':
						self::render_field(
							array(
								'name'        => 'ai_fq_ollama_url',
								'label'       => __( 'Ollama URL', 'ai-fun-questions' ),
								'type'        => 'url',
								'value'       => get_option( 'ai_fq_ollama_url', 'http://localhost:11434/api/chat' ),
								/* translators: %s: default Ollama endpoint URL. */
								'description' => sprintf( __( 'Default: %s', 'ai-fun-questions' ), 'http://localhost:11434/api/chat' ),
							)
						);
						self::render_field(
							array(
								'name'        => 'ai_fq_ollama_model',
								'label'       => __( 'Ollama Model', 'ai-fun-questions' ),
								'type'        => 'select',
								'choices'     => self::models( 'ollama' ),
								'value'       => get_option( 'ai_fq_ollama_model', 'gemma3' ),
								'description' => __( 'The model must already be pulled on the machine running Ollama.', 'ai-fun-questions' ),
							)
						);
						break;

					case 'huggingface':
						self::render_field(
							array(
								'name'        => 'ai_fq_hf_token',
								'label'       => __( 'Hugging Face Token', 'ai-fun-questions' ),
								'type'        => 'password',
								'secret'      => true,
								'constant'    => 'AI_FQ_HF_TOKEN',
								'stored'      => '' !== (string) get_option( 'ai_fq_hf_token', '' ),
								'description' => __( 'Leave blank to keep the existing saved token. For production, prefer AI_FQ_HF_TOKEN in wp-config.php.', 'ai-fun-questions' ),
							)
						);
						self::render_field(
							array(
								'name'        => 'ai_fq_hf_model',
								'label'       => __( 'Hugging Face Model', 'ai-fun-questions' ),
								'type'        => 'select',
								'choices'     => self::models( 'huggingface' ),
								'value'       => get_option( 'ai_fq_hf_model', 'Qwen/Qwen3-4B-Instruct-2507' ),
								'description' => __( 'Use a chat-completion model available through Hugging Face Inference Providers.', 'ai-fun-questions' ),
							)
						);
						break;

					case 'openai':
						self::render_field(
							array(
								'name'  => 'ai_fq_openai_endpoint',
								'label' => __( 'OpenAI-compatible Endpoint', 'ai-fun-questions' ),
								'type'  => 'url',
								'value' => get_option( 'ai_fq_openai_endpoint', 'https://api.openai.com/v1/chat/completions' ),
								'full'  => true,
							)
						);
						self::render_field(
							array(
								'name'        => 'ai_fq_openai_key',
								'label'       => __( 'OpenAI-compatible API Key', 'ai-fun-questions' ),
								'type'        => 'password',
								'secret'      => true,
								'constant'    => 'AI_FQ_OPENAI_KEY',
								'stored'      => '' !== (string) get_option( 'ai_fq_openai_key', '' ),
								'description' => __( 'Leave blank to keep the existing saved key. For production, prefer AI_FQ_OPENAI_KEY in wp-config.php.', 'ai-fun-questions' ),
							)
						);
						self::render_field(
							array(
								'name'        => 'ai_fq_openai_model',
								'label'       => __( 'OpenAI-compatible Model', 'ai-fun-questions' ),
								'type'        => 'select',
								'choices'     => self::models( 'openai' ),
								'value'       => get_option( 'ai_fq_openai_model', 'gpt-4o-mini' ),
								'description' => __( 'Listed models assume the default OpenAI endpoint. Pointing the endpoint elsewhere means using that service\'s own model names.', 'ai-fun-questions' ),
							)
						);
						break;
				}
				?>
			</div>
		</section>
		<?php
	}

	/**
	 * Secret fields never render their stored value; a placeholder only signals
	 * that something is saved.
	 */
	private static function render_field( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'name'        => '',
				'label'       => '',
				'type'        => 'text',
				'value'       => '',
				'description' => '',
				'secret'      => false,
				'constant'    => '',
				'stored'      => false,
				'full'        => false,
				'choices'     => array(),
			)
		);

		$id            = 'ai-fq-field-' . $args['name'];
		$from_constant = '' !== $args['constant'] && defined( $args['constant'] );
		$classes       = 'ai-fq-field' . ( $args['full'] ? ' ai-fq-field--full' : '' );

		$description = $args['description'];

		if ( $args['secret'] && $from_constant ) {
			$description = sprintf(
				/* translators: %s: PHP constant name. */
				__( 'Defined via %s in wp-config.php. The field below is ignored.', 'ai-fun-questions' ),
				$args['constant']
			);
		}
		?>
		<div class="<?php echo esc_attr( $classes ); ?>">
			<label class="ai-fq-field__label" for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $args['label'] ); ?>
				<?php if ( $from_constant ) : ?>
					<span class="ai-fq-pill ai-fq-pill--tiny"><?php esc_html_e( 'wp-config', 'ai-fun-questions' ); ?></span>
				<?php endif; ?>
			</label>

			<?php if ( 'select' === $args['type'] ) : ?>
				<?php self::render_model_picker( $id, $args ); ?>
			<?php else : ?>
				<input
					class="ai-fq-field__input"
					type="<?php echo esc_attr( $args['type'] ); ?>"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $args['name'] ); ?>"
					value="<?php echo $args['secret'] ? '' : esc_attr( $args['value'] ); ?>"
					<?php if ( $args['secret'] ) : ?>
						autocomplete="new-password"
						placeholder="<?php echo esc_attr( $args['stored'] ? str_repeat( "\xe2\x80\xa2", 12 ) : '' ); ?>"
					<?php endif; ?>
				>
			<?php endif; ?>

			<?php if ( $args['secret'] && $args['stored'] ) : ?>
				<label class="ai-fq-field__clear">
					<input
						type="checkbox"
						name="<?php echo esc_attr( $args['name'] ); ?>_clear"
						value="1"
						data-ai-fq-clear="<?php echo esc_attr( $id ); ?>"
					>
					<span><?php esc_html_e( 'Clear the saved value', 'ai-fun-questions' ); ?></span>
				</label>
			<?php endif; ?>

			<?php if ( '' !== $description ) : ?>
				<p class="ai-fq-field__description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * A grouped model dropdown plus a free-text box for anything not listed.
	 *
	 * The dropdown carries the option name so the page still works with
	 * JavaScript off; the companion box is only read when CUSTOM_MODEL is
	 * submitted. JavaScript just hides the box while a listed model is chosen.
	 */
	private static function render_model_picker( $id, $args ) {
		$value    = (string) $args['value'];
		$is_known = false;

		foreach ( $args['choices'] as $models ) {
			if ( in_array( $value, $models, true ) ) {
				$is_known = true;
				break;
			}
		}

		$custom_id = $id . '-custom';
		?>
		<select
			class="ai-fq-field__select"
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $args['name'] ); ?>"
			data-ai-fq-model="<?php echo esc_attr( $custom_id ); ?>"
		>
			<?php foreach ( $args['choices'] as $group => $models ) : ?>
				<optgroup label="<?php echo esc_attr( $group ); ?>">
					<?php foreach ( $models as $model ) : ?>
						<option value="<?php echo esc_attr( $model ); ?>" <?php selected( $value, $model ); ?>>
							<?php echo esc_html( $model ); ?>
						</option>
					<?php endforeach; ?>
				</optgroup>
			<?php endforeach; ?>

			<option value="<?php echo esc_attr( self::CUSTOM_MODEL ); ?>" <?php selected( $is_known, false ); ?>>
				<?php esc_html_e( 'Custom model…', 'ai-fun-questions' ); ?>
			</option>
		</select>

		<input
			class="ai-fq-field__input ai-fq-field__custom<?php echo $is_known ? ' is-hidden' : ''; ?>"
			type="text"
			id="<?php echo esc_attr( $custom_id ); ?>"
			name="<?php echo esc_attr( $args['name'] ); ?>_custom"
			value="<?php echo $is_known ? '' : esc_attr( $value ); ?>"
			placeholder="<?php esc_attr_e( 'Exact model name', 'ai-fun-questions' ); ?>"
		>
		<?php
	}

	/**
	 * Inline SVG so the page needs no icon font or image requests.
	 */
	private static function icon( $name ) {
		$icons = array(
			'info'        => '<circle cx="12" cy="12" r="9"/><path d="M12 11.2v4.6"/><circle cx="12" cy="8.1" r="1" fill="currentColor" stroke="none"/>',
			'ollama'      => '<rect x="3" y="7" width="18" height="11" rx="4.5"/><circle cx="9" cy="12.5" r="1.5" fill="currentColor" stroke="none"/><circle cx="15" cy="12.5" r="1.5" fill="currentColor" stroke="none"/>',
			'huggingface' => '<circle cx="12" cy="12" r="9"/><path d="M8.4 14.2c.9 1.3 2.1 1.9 3.6 1.9s2.7-.6 3.6-1.9"/><circle cx="9.3" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="14.7" cy="10" r="1" fill="currentColor" stroke="none"/>',
			'openai'      => '<path d="M12 2.9 20 7.45v9.1L12 21.1 4 16.55v-9.1z"/><circle cx="12" cy="12" r="3.1"/>',
		);

		if ( ! isset( $icons[ $name ] ) ) {
			return;
		}

		printf(
			'<svg class="ai-fq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
			$icons[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hard-coded SVG paths.
		);
	}
}
