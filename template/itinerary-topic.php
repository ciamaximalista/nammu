<?php
/** @var \Nammu\Core\Itinerary $itinerary */
/** @var \Nammu\Core\ItineraryTopic $topic */
/** @var array|null $nextStep */
/** @var array|null $previousStep */
/** @var callable $itineraryUrl */
/** @var string $itinerariesIndexUrl */
/** @var array $quiz */
/** @var string $usageLogic */
/** @var array $progress */

use Nammu\Core\Itinerary;

$quizData = is_array($quiz ?? null) ? $quiz : [];
$quizQuestions = $quizData['questions'] ?? [];
$quizAvailable = !empty($quizQuestions);
$minimumCorrect = $quizData['minimum_correct'] ?? count($quizQuestions);
$questionCount = count($quizQuestions);
if ($questionCount > 0) {
    $minimumCorrect = (int) $minimumCorrect;
    if ($minimumCorrect < 1) {
        $minimumCorrect = 1;
    }
    if ($minimumCorrect > $questionCount) {
        $minimumCorrect = $questionCount;
    }
} else {
    $minimumCorrect = 0;
}
$progressVisited = $progress['visited'] ?? [];
$progressPassed = $progress['passed'] ?? [];
$topicPassed = in_array($topic->getSlug(), $progressPassed, true);
$usageLogic = $usageLogic ?? Itinerary::USAGE_LOGIC_FREE;
$quizRequired = $usageLogic === Itinerary::USAGE_LOGIC_ASSESSMENT && $quizAvailable;
$ctaLocked = $quizRequired && !$topicPassed && $nextStep !== null;
$shuffledQuestions = $quizQuestions;
if ($quizAvailable) {
    shuffle($shuffledQuestions);
}
?>

<?php if ($quizAvailable): ?>
    <section
        class="itinerary-quiz"
        data-itinerary-quiz
        data-itinerary-slug="<?= htmlspecialchars($itinerary->getSlug(), ENT_QUOTES, 'UTF-8') ?>"
        data-topic-slug="<?= htmlspecialchars($topic->getSlug(), ENT_QUOTES, 'UTF-8') ?>"
        data-min-correct="<?= (int) $minimumCorrect ?>"
        data-usage-logic="<?= htmlspecialchars($usageLogic, ENT_QUOTES, 'UTF-8') ?>"
    >
        <div class="itinerary-quiz__header">
            <h2>Autoevaluación del tema</h2>
            <p>Debes acertar al menos <?= (int) $minimumCorrect ?> de <?= (int) $questionCount ?> preguntas para continuar.</p>
        </div>
        <div class="itinerary-quiz__body">
            <?php foreach ($shuffledQuestions as $index => $question): ?>
                <?php
                $answers = $question['answers'] ?? [];
                shuffle($answers);
                ?>
                <article class="itinerary-quiz__question" data-quiz-question>
                    <h3>Pregunta <?= $index + 1 ?></h3>
                    <p><?= htmlspecialchars($question['text'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                    <ul class="itinerary-quiz__answers">
                        <?php foreach ($answers as $answerIndex => $answer): ?>
                            <li>
                                <label>
                                    <input
                                        type="checkbox"
                                        data-quiz-answer
                                        data-correct="<?= !empty($answer['correct']) ? '1' : '0' ?>"
                                        value="1"
                                    >
                                    <span><?= htmlspecialchars($answer['text'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="itinerary-quiz__actions">
            <button type="button" class="button button-primary" data-quiz-submit>Comprobar respuestas</button>
            <div class="itinerary-quiz__result" data-quiz-result aria-live="polite"></div>
        </div>
    </section>
<?php endif; ?>

<section
    class="itinerary-topic-cta"
    data-itinerary-topic-cta
    data-itinerary-slug="<?= htmlspecialchars($itinerary->getSlug(), ENT_QUOTES, 'UTF-8') ?>"
    data-topic-slug="<?= htmlspecialchars($topic->getSlug(), ENT_QUOTES, 'UTF-8') ?>"
    data-usage-logic="<?= htmlspecialchars($usageLogic, ENT_QUOTES, 'UTF-8') ?>"
    data-requires-quiz="<?= $quizRequired ? '1' : '0' ?>"
    data-initial-passed="<?= $topicPassed ? '1' : '0' ?>"
>
    <div class="itinerary-topic-cta__wrapper">
        <div class="itinerary-topic-cta__info">
            <p class="itinerary-topic-cta__breadcrumbs">
                <a href="<?= htmlspecialchars($itineraryUrl($itinerary), ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($itinerary->getTitle(), ENT_QUOTES, 'UTF-8') ?>
                </a>
                &rsaquo; Tema <?= (int) $topic->getNumber() ?>
            </p>
            <h2>¿Listo para continuar?</h2>
            <?php if ($nextStep !== null && $ctaLocked): ?>
                <p>Cuando completes <strong><?= htmlspecialchars($topic->getTitle(), ENT_QUOTES, 'UTF-8') ?></strong> podrás avanzar al siguiente tema.</p>
            <?php elseif ($nextStep === null): ?>
                <p>Has llegado al final del itinerario. Puedes volver al listado de itinerarios o repasar los temas anteriores.</p>
            <?php endif; ?>
        </div>
        <div class="itinerary-topic-cta__actions">
            <?php if ($nextStep !== null): ?>
                <a
                    class="button button-primary <?= $ctaLocked ? 'button-disabled' : '' ?>"
                    href="<?= htmlspecialchars($nextStep['url'], ENT_QUOTES, 'UTF-8') ?>"
                    data-next-link
                    <?= $ctaLocked ? 'tabindex="-1" aria-disabled="true"' : '' ?>
                >
                    <?= htmlspecialchars($nextStep['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
                <?php if ($quizRequired): ?>
                    <p class="itinerary-topic-cta__alert" data-next-locked style="<?= $ctaLocked ? '' : 'display:none;' ?>">
                        Completa y aprueba la autoevaluación para desbloquear el siguiente tema.
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($previousStep)): ?>
                <a
                    class="button button-secondary"
                    href="<?= htmlspecialchars($previousStep['url'], ENT_QUOTES, 'UTF-8') ?>"
                >
                    <?= htmlspecialchars($previousStep['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endif; ?>

            <?php if (!empty($itinerariesIndexUrl)): ?>
                <a class="button button-secondary" href="<?= htmlspecialchars($itinerariesIndexUrl, ENT_QUOTES, 'UTF-8') ?>">
                    Todos los itinerarios
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>
