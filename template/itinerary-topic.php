<?php
/** @var \Nammu\Core\Itinerary $itinerary */
/** @var \Nammu\Core\ItineraryTopic $topic */
/** @var bool $hasTest */
/** @var array|null $nextStep */
/** @var callable $itineraryUrl */
/** @var string $itinerariesIndexUrl */
?>
<section class="itinerary-topic-cta">
    <div class="itinerary-topic-cta__wrapper">
        <div class="itinerary-topic-cta__info">
            <p class="itinerary-topic-cta__breadcrumbs">
                <a href="<?= htmlspecialchars($itineraryUrl($itinerary), ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($itinerary->getTitle(), ENT_QUOTES, 'UTF-8') ?>
                </a>
                &rsaquo; Tema <?= (int) $topic->getNumber() ?>
            </p>
            <h2>¿Listo para continuar?</h2>
            <?php if ($nextStep !== null): ?>
                <p>Has completado <strong><?= htmlspecialchars($topic->getTitle(), ENT_QUOTES, 'UTF-8') ?></strong>. Pulsa el botón para avanzar al siguiente tema del itinerario.</p>
            <?php else: ?>
                <p>Has llegado al final del itinerario. Puedes volver al listado de itinerarios o repasar los temas anteriores.</p>
            <?php endif; ?>
        </div>
        <div class="itinerary-topic-cta__actions">
            <?php if ($hasTest): ?>
                <div class="itinerary-topic-cta__alert">
                    <strong>Próximamente:</strong> Este tema tendrá un test que deberás superar antes de pasar al siguiente.
                </div>
            <?php elseif ($nextStep !== null): ?>
                <a class="button button-primary" href="<?= htmlspecialchars($nextStep['url'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($nextStep['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php else: ?>
                <a class="button button-secondary" href="<?= htmlspecialchars($itinerariesIndexUrl, ENT_QUOTES, 'UTF-8') ?>">
                    Volver a los itinerarios
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>
