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

$colors = $theme['colors'] ?? [];
$highlight = htmlspecialchars($colors['highlight'] ?? '#f3f6f9', ENT_QUOTES, 'UTF-8');
$textColor = htmlspecialchars($colors['text'] ?? '#222222', ENT_QUOTES, 'UTF-8');
$accentColor = htmlspecialchars($colors['accent'] ?? '#0a4c8a', ENT_QUOTES, 'UTF-8');
$searchSettings = $theme['search'] ?? [];
$searchMode = in_array($searchSettings['mode'] ?? 'none', ['none', 'home', 'single', 'both'], true) ? $searchSettings['mode'] : 'none';
$searchPositionSetting = in_array($searchSettings['position'] ?? 'title', ['title', 'footer'], true) ? $searchSettings['position'] : 'title';
$shouldShowSearch = in_array($searchMode, ['home', 'single', 'both'], true);
$searchTop = $shouldShowSearch && $searchPositionSetting === 'title';
$searchBottom = $shouldShowSearch && $searchPositionSetting === 'footer';
$subscriptionSettings = is_array($theme['subscription'] ?? null) ? $theme['subscription'] : [];
$subscriptionModeValue = $subscriptionSettings['mode'] ?? 'none';
$subscriptionPositionValue = $subscriptionSettings['position'] ?? 'footer';
$subscriptionMode = in_array($subscriptionModeValue, ['none', 'home', 'single', 'both'], true) ? $subscriptionModeValue : 'none';
$subscriptionPositionSetting = in_array($subscriptionPositionValue, ['title', 'footer'], true) ? $subscriptionPositionValue : 'footer';
$shouldShowSubscription = in_array($subscriptionMode, ['home', 'single', 'both'], true);
$subscriptionTop = $shouldShowSubscription && $subscriptionPositionSetting === 'title';
$subscriptionBottom = $shouldShowSubscription && $subscriptionPositionSetting === 'footer';
$searchActionBase = $baseUrl ?? '/';
$searchAction = rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/buscar.php';
$subscriptionAction = rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/subscribe.php';
$letterIndexUrlValue = $lettersIndexUrl ?? null;
$itinerariesIndexUrl = $itinerariesIndexUrl ?? (($baseUrl ?? '/') !== '' ? rtrim($baseUrl ?? '/', '/') . '/itinerarios' : '/itinerarios');
$podcastIndexUrl = $podcastIndexUrl ?? (($baseUrl ?? '/') !== '' ? rtrim($baseUrl ?? '/', '/') . '/podcast' : '/podcast');
$hasItineraries = !empty($hasItineraries);
$hasPodcast = !empty($hasPodcast);
$hasCategories = !empty($hasCategories);
$showLetterButton = !empty($showLetterIndexButton) && !empty($letterIndexUrlValue);
$currentUrl = ($baseUrl ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
$subscriptionSuccess = isset($_GET['subscribed']) && $_GET['subscribed'] === '1';
$subscriptionSent = isset($_GET['sub_sent']) && $_GET['sub_sent'] === '1';
$subscriptionError = isset($_GET['sub_error']) && $_GET['sub_error'] === '1';
$subscriptionMessage = '';
if ($subscriptionSuccess) {
    $subscriptionMessage = 'Suscripción confirmada. ¡Gracias!';
} elseif ($subscriptionSent) {
    $subscriptionMessage = 'Hemos enviado un email de confirmación. Revisa tu correo.';
} elseif ($subscriptionError) {
    $subscriptionMessage = 'No pudimos procesar ese correo. Revisa la dirección e inténtalo de nuevo.';
}
$postalEnabled = $postalEnabled ?? false;
$postalUrl = $postalUrl ?? '/correos.php';
$postalLogoSvg = $postalLogoSvg ?? '';
$renderSearchBox = static function (string $variant) use ($searchAction, $searchActionBase, $accentColor, $letterIndexUrlValue, $showLetterButton, $hasItineraries, $itinerariesIndexUrl, $hasCategories, $hasPodcast, $podcastIndexUrl): string {
    ob_start(); ?>
    <div class="site-search-box <?= htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') ?>">
        <form class="site-search-form" method="get" action="<?= htmlspecialchars($searchAction, ENT_QUOTES, 'UTF-8') ?>">
            <span class="search-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="8" r="6" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                    <line x1="12.5" y1="12.5" x2="17" y2="17" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </span>
            <input type="text" name="q" placeholder="Busca en este sitio..." required>
            <button type="submit" aria-label="Buscar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                    <path d="M21 4L9 16L4 11" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <?php if ($hasCategories): ?>
                <a class="search-categories-link" href="<?= htmlspecialchars(rtrim($searchActionBase === '' ? '/' : $searchActionBase, '/') . '/categorias', ENT_QUOTES, 'UTF-8') ?>" aria-label="Índice de categorías">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                        <rect x="4" y="5" width="16" height="14" rx="2" fill="none" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                        <line x1="8" y1="9" x2="16" y2="9" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                        <line x1="8" y1="13" x2="16" y2="13" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                    </svg>
                </a>
            <?php endif; ?>
            <?php if ($showLetterButton && $letterIndexUrlValue): ?>
                <a class="search-letters-link" href="<?= htmlspecialchars($letterIndexUrlValue, ENT_QUOTES, 'UTF-8') ?>" aria-label="Índice alfabético">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 18L9 6L13 18" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="6.5" y1="13" x2="11.5" y2="13" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                        <path d="M15 6H20L15 18H20" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (!empty($hasItineraries) && !empty($itinerariesIndexUrl)): ?>
                <a class="search-itineraries-link" href="<?= htmlspecialchars($itinerariesIndexUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Itinerarios">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 5H10C11.1046 5 12 5.89543 12 7V19H4C2.89543 19 2 18.1046 2 17V7C2 5.89543 2.89543 5 4 5Z" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M20 5H14C12.8954 5 12 5.89543 12 7V19H20C21.1046 19 22 18.1046 22 17V7C22 5.89543 21.1046 5 20 5Z" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linejoin="round"/>
                        <line x1="12" y1="7" x2="12" y2="19" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (!empty($hasPodcast) && !empty($podcastIndexUrl)): ?>
                <a class="search-podcast-link" href="<?= htmlspecialchars($podcastIndexUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Podcast">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="9" y="3" width="6" height="10" rx="3" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                        <path d="M5 11C5 14.866 8.134 18 12 18C15.866 18 19 14.866 19 11" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                        <line x1="12" y1="18" x2="12" y2="22" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                        <line x1="8" y1="22" x2="16" y2="22" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </a>
            <?php endif; ?>
        </form>
    </div>
    <?php
    return (string) ob_get_clean();
};
$renderSubscriptionBox = static function (string $variant) use ($subscriptionAction, $accentColor, $highlight, $textColor, $subscriptionMessage, $currentUrl, $postalEnabled, $postalUrl, $postalLogoSvg): string {
    $avisosUrl = $subscriptionAction !== '' ? str_replace('/subscribe.php', '/avisos.php', $subscriptionAction) : '/avisos.php';
    ob_start(); ?>
    <div class="site-search-box <?= htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') ?> site-subscription-box">
        <form class="site-search-form subscription-form" method="post" action="<?= htmlspecialchars($subscriptionAction, ENT_QUOTES, 'UTF-8') ?>">
            <span class="search-icon subscription-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="5" width="18" height="14" rx="2" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"/>
                    <polyline points="3,7 12,13 21,7" fill="none" stroke="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <input type="hidden" name="back" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="force_all" value="1">
            <input type="email" name="subscriber_email" placeholder="Suscríbete" required>
            <button type="submit" aria-label="Enviar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                    <path d="M21 4L9 16L4 11" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <a class="subscription-avisos-link" href="<?= htmlspecialchars($avisosUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Avisos por email">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8') ?>" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="6" width="16" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
                    <polyline points="4,8 12,14 20,8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            <?php if ($postalEnabled && $postalLogoSvg !== ''): ?>
                <a class="subscription-postal-link" href="<?= htmlspecialchars($postalUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Suscripción postal">
                    <?= $postalLogoSvg ?>
                </a>
            <?php endif; ?>
        </form>
        <?php if ($subscriptionMessage !== ''): ?>
            <div class="subscription-feedback" style="color: <?= htmlspecialchars($textColor, ENT_QUOTES, 'UTF-8') ?>; background: <?= htmlspecialchars($highlight, ENT_QUOTES, 'UTF-8') ?>; border-radius: 10px; padding:10px; margin-top:10px; font-size:14px;">
                <?= htmlspecialchars($subscriptionMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
};
$renderPostalBox = static function (string $variant) use ($postalEnabled, $postalUrl, $postalLogoSvg): string {
    if (!$postalEnabled || $postalLogoSvg === '') {
        return '';
    }
    ob_start(); ?>
    <div class="site-search-box <?= htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') ?> site-subscription-box">
        <div class="postal-only-box">
            <a class="subscription-postal-link" href="<?= htmlspecialchars($postalUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Suscripción postal">
                <?= $postalLogoSvg ?>
            </a>
            <div class="postal-only-text">
                <strong>Correo postal</strong>
                <span>Suscríbete para recibir envíos físicos.</span>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
};

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

<?php if ($subscriptionTop): ?>
    <section class="site-search-block placement-top site-subscription-block">
        <?= $renderSubscriptionBox('variant-inline minimal') ?>
    </section>
<?php endif; ?>
<?php if ($searchTop): ?>
    <section class="site-search-block placement-top">
        <?= $renderSearchBox('variant-inline minimal') ?>
    </section>
<?php endif; ?>

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

<?php if ($subscriptionBottom): ?>
    <section class="site-search-block placement-bottom site-subscription-block">
        <?= $renderSubscriptionBox('variant-panel') ?>
    </section>
<?php endif; ?>
<?php if ($searchBottom): ?>
    <section class="site-search-block placement-bottom">
        <?= $renderSearchBox('variant-panel') ?>
    </section>
<?php endif; ?>
