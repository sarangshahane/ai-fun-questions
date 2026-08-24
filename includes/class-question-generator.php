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

	public static function request_body( $model ) {
		return array(
			'model'    => $model,
			'messages' => array(
				array(
					'role'    => 'system',
					'content' => self::PROMPT,
				),
				array(
					'role'    => 'user',
					'content' => 'Generate one fresh question now.',
				),
			),
			'temperature' => 0.9,
			'stream'      => false,
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
