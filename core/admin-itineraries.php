<?php
/**
 * Nammu — panel de administración.
 * Itinerarios formativos: repositorio, clases, lógica de uso, cuestionarios y utilidades.
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */

use Nammu\Core\Itinerary;
use Nammu\Core\ItineraryRepository;
use Nammu\Core\ItineraryTopic;

function admin_itinerary_repository(): ItineraryRepository {
    static $repository = null;
    if ($repository === null) {
        if (!is_dir(ITINERARIES_DIR)) {
            nammu_ensure_directory(ITINERARIES_DIR);
        }
        $repository = new ItineraryRepository(ITINERARIES_DIR);
    }
    return $repository;
}

/**
 * @return Itinerary[]
 */
function admin_list_itineraries(): array {
    try {
        return admin_itinerary_repository()->all();
    } catch (Throwable $e) {
        return [];
    }
}

function admin_load_itinerary(string $slug): ?Itinerary {
    $normalized = ItineraryRepository::normalizeSlug($slug);
    if ($normalized === '') {
        return null;
    }
    try {
        return admin_itinerary_repository()->find($normalized);
    } catch (Throwable $e) {
        return null;
    }
}

function admin_load_itinerary_topic(string $itinerarySlug, string $topicSlug): ?ItineraryTopic {
    $itineraryNormalized = ItineraryRepository::normalizeSlug($itinerarySlug);
    $topicNormalized = ItineraryRepository::normalizeSlug($topicSlug);
    if ($itineraryNormalized === '' || $topicNormalized === '') {
        return null;
    }
    try {
        return admin_itinerary_repository()->findTopic($itineraryNormalized, $topicNormalized);
    } catch (Throwable $e) {
        return null;
    }
}

function admin_get_itinerary_stats(Itinerary $itinerary): array {
    try {
        return admin_itinerary_repository()->getItineraryStats($itinerary->getSlug());
    } catch (Throwable $e) {
        return ['started' => 0, 'topics' => [], 'updated_at' => 0];
    }
}

function admin_itinerary_class_options(): array {
    return [
        'Libro' => 'Libro',
        'Curso' => 'Curso',
        'Colección de materiales' => 'Colección de materiales',
        'Otros' => 'Otros',
    ];
}

function admin_next_itinerary_order(): int {
    $list = admin_list_itineraries();
    $max = 0;
    foreach ($list as $index => $item) {
        if ($item instanceof Itinerary) {
            $meta = $item->getMetadata();
            $value = (int) ($meta['Order'] ?? 0);
            if ($value <= 0) {
                $value = $index + 1;
            }
            if ($value > $max) {
                $max = $value;
            }
        }
    }
    return $max + 1;
}

function admin_normalize_itinerary_class_label(?string $choice, ?string $custom): string {
    $choice = trim((string) $choice);
    $custom = trim((string) $custom);
    $options = admin_itinerary_class_options();
    if ($choice !== '' && isset($options[$choice])) {
        if ($choice === 'Otros') {
            return $custom !== '' ? $custom : 'Itinerario';
        }
        return $options[$choice];
    }
    if ($custom !== '') {
        return $custom;
    }
    return 'Itinerario';
}

function admin_itinerary_class_form_state(string $label): array {
    $label = trim($label);
    $options = admin_itinerary_class_options();
    if ($label === '' || $label === 'Itinerario') {
        return ['choice' => '', 'custom' => ''];
    }
    foreach ($options as $value => $text) {
        if ($value === 'Otros') {
            continue;
        }
        if ($label === $text) {
            return ['choice' => $value, 'custom' => ''];
        }
    }
    return ['choice' => 'Otros', 'custom' => $label];
}

function admin_itinerary_usage_logic_options(): array {
    return [
        'free' => 'El lector puede ir libremente al tema que quiera',
        'sequential' => 'Es necesario haber accedido a un tema para pasar al siguiente',
        'assessment' => 'Es necesario haber respondido correctamente a las autoevaluaciones para pasar al tema siguiente',
    ];
}

function admin_normalize_itinerary_usage_logic(?string $value): string {
    $value = strtolower(trim((string) $value));
    $options = admin_itinerary_usage_logic_options();
    if ($value !== '' && isset($options[$value])) {
        return $value;
    }
    return 'free';
}

function admin_parse_quiz_payload(string $payload): array {
    $payload = trim($payload);
    if ($payload === '') {
        return ['data' => [], 'error' => null];
    }
    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        return ['data' => [], 'error' => 'La autoevaluación enviada no es válida.'];
    }
    $sanitized = admin_sanitize_quiz_array($decoded);
    if (!empty($decoded['questions']) && empty($sanitized)) {
        return ['data' => [], 'error' => 'Revisa la autoevaluación: cada pregunta necesita texto y al menos una respuesta correcta.'];
    }
    return ['data' => $sanitized, 'error' => null];
}

function admin_sanitize_quiz_array(?array $quiz): array {
    if (!is_array($quiz)) {
        return [];
    }
    $questions = [];
    foreach ($quiz['questions'] ?? [] as $question) {
        if (!is_array($question)) {
            continue;
        }
        $text = trim((string) ($question['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $answers = [];
        foreach ($question['answers'] ?? [] as $answer) {
            if (!is_array($answer)) {
                continue;
            }
            $answerText = trim((string) ($answer['text'] ?? ''));
            if ($answerText === '') {
                continue;
            }
            $answers[] = [
                'text' => $answerText,
                'correct' => !empty($answer['correct']),
            ];
        }
        if (empty($answers)) {
            continue;
        }
        $hasCorrect = false;
        foreach ($answers as $answer) {
            if ($answer['correct']) {
                $hasCorrect = true;
                break;
            }
        }
        if (!$hasCorrect) {
            continue;
        }
        $questions[] = [
            'text' => $text,
            'answers' => $answers,
        ];
    }
    if (empty($questions)) {
        return [];
    }
    $minimum = (int) ($quiz['minimum_correct'] ?? count($questions));
    if ($minimum < 1) {
        $minimum = 1;
    }
    if ($minimum > count($questions)) {
        $minimum = count($questions);
    }
    return [
        'minimum_correct' => $minimum,
        'questions' => array_values($questions),
    ];
}

function admin_quiz_summary(array $quiz): string {
    if (empty($quiz['questions'])) {
        return '';
    }
    $questionCount = count($quiz['questions']);
    $minimum = (int) ($quiz['minimum_correct'] ?? $questionCount);
    if ($minimum < 1) {
        $minimum = 1;
    }
    if ($minimum > $questionCount) {
        $minimum = $questionCount;
    }
    $questionLabel = $questionCount === 1 ? 'pregunta' : 'preguntas';
    return $questionCount . ' ' . $questionLabel . ' · mínimo ' . $minimum . ' correctas';
}

function admin_quiz_json(array $quiz): string {
    if (empty($quiz['questions'])) {
        return '';
    }
    $payload = json_encode($quiz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $payload === false ? '' : $payload;
}
