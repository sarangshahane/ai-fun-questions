<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_FQ_Frontend {

	public static function init() {
		add_shortcode( 'ai_fun_question', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style(
			'ai-fun-questions',
			AI_FQ_Plugin::style_url( 'frontend' ),
			array(),
			AI_FQ_VERSION
		);

		AI_FQ_Plugin::add_rtl( 'ai-fun-questions' );

		wp_register_script(
			'ai-fun-questions',
			AI_FQ_Plugin::script_url( 'frontend' ),
			array(),
			AI_FQ_VERSION,
			true
		);

		wp_localize_script(
			'ai-fun-questions',
			'AI_FQ',
			array(
				'restUrl' => esc_url_raw( rest_url( AI_FQ_REST_API::NAMESPACE ) ),
				'i18n'    => array(
					'loading'     => __( 'Thinking of something funny…', 'ai-fun-questions' ),
					'error'       => __( 'Could not generate a question. Please try again.', 'ai-fun-questions' ),
					'submit'      => __( 'Submit Answer', 'ai-fun-questions' ),
					'next'        => __( 'Next Question', 'ai-fun-questions' ),
					'emptyAnswer' => __( 'Type your answer first.', 'ai-fun-questions' ),
					'retry'       => __( 'Try Again', 'ai-fun-questions' ),
				),
			)
		);
	}

	public static function shortcode() {
		wp_enqueue_style( 'ai-fun-questions' );
		wp_enqueue_script( 'ai-fun-questions' );

		$widget_token = wp_generate_password( 48, false, false );

		ob_start();
		?>
		<div class="ai-fq" data-ai-fq data-widget-token="<?php echo esc_attr( $widget_token ); ?>">
			<div class="ai-fq__card">
				<div class="ai-fq__eyebrow"><?php esc_html_e( 'AI FUN QUESTION', 'ai-fun-questions' ); ?></div>

				<div class="ai-fq__loading" data-ai-fq-loading>
					<?php esc_html_e( 'Thinking of something funny…', 'ai-fun-questions' ); ?>
				</div>

				<div class="ai-fq__content" data-ai-fq-content hidden>
					<div class="ai-fq__question" data-ai-fq-question></div>

					<input type="hidden" data-ai-fq-token value="">

					<label class="ai-fq__label" for="ai-fq-answer-<?php echo esc_attr( substr( $widget_token, 0, 12 ) ); ?>">
						<?php esc_html_e( 'Your answer', 'ai-fun-questions' ); ?>
					</label>

					<textarea
						class="ai-fq__input"
						id="ai-fq-answer-<?php echo esc_attr( substr( $widget_token, 0, 12 ) ); ?>"
						data-ai-fq-answer
						rows="3"
						maxlength="1000"
						placeholder="<?php esc_attr_e( 'What do you think?', 'ai-fun-questions' ); ?>"
					></textarea>

					<div class="ai-fq__actions">
						<button type="button" class="ai-fq__button" data-ai-fq-submit>
							<?php esc_html_e( 'Submit Answer', 'ai-fun-questions' ); ?>
						</button>
						<button type="button" class="ai-fq__button ai-fq__button--secondary" data-ai-fq-next hidden>
							<?php esc_html_e( 'Next Question', 'ai-fun-questions' ); ?>
						</button>
					</div>

					<div class="ai-fq__result" data-ai-fq-result hidden>
						<div class="ai-fq__result-label"><?php esc_html_e( 'Your answer:', 'ai-fun-questions' ); ?></div>
						<div data-ai-fq-your-answer></div>

						<div class="ai-fq__result-label"><?php esc_html_e( 'The AI punchline:', 'ai-fun-questions' ); ?></div>
						<div class="ai-fq__punchline" data-ai-fq-punchline></div>
					</div>

					<div class="ai-fq__hint" data-ai-fq-hint></div>
				</div>

				<div class="ai-fq__error" data-ai-fq-error hidden>
					<div data-ai-fq-error-text></div>
					<button type="button" class="ai-fq__button ai-fq__button--secondary ai-fq__button--retry" data-ai-fq-retry hidden>
						<?php esc_html_e( 'Try Again', 'ai-fun-questions' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
