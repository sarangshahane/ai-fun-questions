<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_FQ_Question_Generator {

	const MAX_QUESTION_LENGTH = 300;
	const MAX_ANSWER_LENGTH   = 160;
	const MAX_CATEGORY_LENGTH = 40;
	const MAX_HINT_LENGTH     = 200;

	const PROMPT = <<<'PROMPT'
You are a witty tech joke generator.

Generate exactly ONE original funny question based on computers, programming, WordPress, internet, AI, or technology.

Requirements:
- The joke must be family-friendly.
- It should sound like a short conversational riddle.
- The punchline should contain clever wordplay.
- Do not reuse famous jokes.
- Keep the question under 35 words.
- Keep the answer under 12 words.
- Keep the hint under 20 words.
- Return ONLY valid JSON.

JSON format:

{
  "question": "",
  "answer": "",
  "category": "",
  "hint": ""
}
PROMPT;

	public static function generate() {
		$provider_name = get_option( 'ai_fq_provider', 'ollama' );
		$provider      = self::get_provider( $provider_name );

		if ( ! $provider ) {
			return new WP_Error(
				'ai_fq_invalid_provider',
				__( 'The configured AI provider is not available.', 'ai-fun-questions' )
			);
		}

		return $provider->generate_question();
	}

	public static function get_provider( $provider_name ) {
		switch ( $provider_name ) {
			case 'huggingface':
				return new AI_FQ_HuggingFace_Provider();
			case 'openai':
				return new AI_FQ_OpenAI_Compatible_Provider();
			case 'ollama':
			default:
				return new AI_FQ_Ollama_Provider();
		}
	}

	/**
	 * Subjects the user turn rotates through.
	 *
	 * Not a question bank: these steer the model's topic, they are not jokes
	 * and no generated content is stored.
	 */
	const TOPICS = array(
		'programming languages',
		'debugging',
		'WordPress',
		'databases',
		'version control',
		'the command line',
		'artificial intelligence',
		'networking',
		'web browsers',
		'cloud hosting',
		'passwords and security',
		'hardware',
	);

	/**
	 * Second variation axis, crossed with TOPICS.
	 *
	 * One axis was not enough: two requests that drew the same topic still came
	 * back with the same joke, because the model has a favourite one per
	 * subject. Crossing topic with an angle makes that collision far rarer.
	 */
	const ANGLES = array(
		'a pun on a technical term',
		'a misunderstanding between two pieces of software',
		'a workplace situation',
		'an everyday object compared to technology',
		'a play on an error message',
		'a relationship or breakup framing',
		'an exaggerated boast',
		'a double meaning in ordinary English',
	);

	public static function request_body( $model ) {
		$body = array(
			'model'    => $model,
			'messages' => array(
				array(
					'role'    => 'system',
					'content' => self::PROMPT,
				),
				array(
					'role'    => 'user',
					'content' => self::user_prompt(),
				),
			),
			'stream'   => false,
		);

		if ( self::accepts_temperature( $model ) ) {
			$body['temperature'] = 0.9;
		}

		return $body;
	}

	/**
	 * OpenAI's gpt-5 and o-series reject any temperature but their default and
	 * fail the whole request with HTTP 400, so the parameter is left off for
	 * them. Their default sampling is varied enough, and user_prompt() is what
	 * actually keeps concurrent widgets from repeating each other.
	 *
	 * Matched narrowly on purpose: openai/gpt-oss-* on Hugging Face and
	 * gpt-oss on Ollama are ordinary chat models that do accept temperature,
	 * and neither starts with a bare "gpt-5" or "o<digit>".
	 */
	private static function accepts_temperature( $model ) {
		$model = strtolower( trim( (string) $model ) );

		if ( preg_match( '/^gpt-5([.\-]|$)/', $model ) ) {
			return false;
		}

		return ! preg_match( '/^o[0-9]+(-|$)/', $model );
	}

	/**
	 * User turn for one generation.
	 *
	 * Two widgets rendering at the same second send the same system prompt to
	 * the same model, and an identical prompt invites an identical completion —
	 * several widgets on one page were returning the same joke. Rotating the
	 * subject and carrying a throwaway variation key makes each request its own
	 * sample without touching the rules in PROMPT.
	 *
	 * @return string
	 */
	private static function user_prompt() {
		$topic = self::TOPICS[ array_rand( self::TOPICS ) ];
		$angle = self::ANGLES[ array_rand( self::ANGLES ) ];

		return sprintf(
			'Generate one fresh question now. Make it about %1$s, built on %2$s. Variation key %3$s — never mention this key, and do not repeat a joke you would usually tell about this subject.',
			$topic,
			$angle,
			wp_generate_password( 10, false, false )
		);
	}

	public static function normalize_response( $content ) {
		$content = trim( (string) $content );
		$content = preg_replace( '/^```(?:json)?\s*/i', '', $content );
		$content = preg_replace( '/\s*```$/', '', $content );
		$content = trim( $content );

		$data = json_decode( $content, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			$json_start = strpos( $content, '{' );
			$json_end   = strrpos( $content, '}' );

			if ( false !== $json_start && false !== $json_end && $json_end > $json_start ) {
				$data = json_decode(
					substr( $content, $json_start, $json_end - $json_start + 1 ),
					true
				);
			}
		}

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error(
				'ai_fq_invalid_json',
				__( 'The AI provider returned an invalid response.', 'ai-fun-questions' )
			);
		}

		$required = array( 'question', 'answer', 'category', 'hint' );

		foreach ( $required as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_string( $data[ $key ] ) || '' === trim( $data[ $key ] ) ) {
				return new WP_Error(
					'ai_fq_missing_field',
					sprintf(
						/* translators: %s: field name */
						__( 'The AI response is missing the "%s" field.', 'ai-fun-questions' ),
						$key
					)
				);
			}
		}

		$question = sanitize_text_field( $data['question'] );
		$answer   = sanitize_text_field( $data['answer'] );
		$category = sanitize_key( $data['category'] );
		$hint     = sanitize_text_field( $data['hint'] );

		if (
			'' === $question ||
			strlen( $question ) > self::MAX_QUESTION_LENGTH ||
			'' === $answer ||
			strlen( $answer ) > self::MAX_ANSWER_LENGTH ||
			'' === $category ||
			strlen( $category ) > self::MAX_CATEGORY_LENGTH ||
			'' === $hint ||
			strlen( $hint ) > self::MAX_HINT_LENGTH
		) {
			return new WP_Error(
				'ai_fq_invalid_content',
				__( 'The AI response did not meet the required content limits.', 'ai-fun-questions' )
			);
		}

		$allowed_categories = array(
			'computer',
			'programming',
			'wordpress',
			'internet',
			'ai',
			'technology',
			'tech',
		);

		if ( ! in_array( $category, $allowed_categories, true ) ) {
			$category = 'technology';
		}

		return array(
			'question' => $question,
			'answer'   => $answer,
			'category' => $category,
			'hint'     => $hint,
		);
	}
}
