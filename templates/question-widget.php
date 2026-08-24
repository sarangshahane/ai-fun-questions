<?php
/**
 * Optional frontend template for future extraction from the shortcode renderer.
 *
 * Variables expected by a future implementation:
 * - $widget_token
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ai-fq" data-ai-fq data-widget-token="<?php echo esc_attr( $widget_token ); ?>">
	<div class="ai-fq__card">
		<div class="ai-fq__eyebrow"><?php esc_html_e( 'AI FUN QUESTION', 'ai-fun-questions' ); ?></div>
		<div class="ai-fq__loading" data-ai-fq-loading>
			<?php esc_html_e( 'Thinking of something funny…', 'ai-fun-questions' ); ?>
		</div>
	</div>
</div>
