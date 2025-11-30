<?php
/** @var \Nammu\Core\Itinerary $itinerary */
/** @var string $itineraryHtml */
/** @var array $topicSummaries */
/** @var string|null $firstTopicUrl */
/** @var string $itineraryCover */
/** @var string $itineraryDescription */
/** @var string $itineraryTitle */
/** @var string $itineraryBody */
/** @var string $itineraryMeta */
/** @var string $itinerariesIndexUrl */
?>
<?php
    $itinerariesIndexUrl = $itinerariesIndexUrl ?? (($baseUrl ?? '/') !== '' ? rtrim($baseUrl ?? '/', '/') . '/itinerarios' : '/itinerarios');
?>

<div class="itinerary-single-content">
    <?= $itineraryBody ?>
</div>

<?php
    $usageLogic = method_exists($itinerary, 'getUsageLogic') ? $itinerary->getUsageLogic() : 'free';
    $usageReasons = [
        'Hacer estadísticas para mejorar los itinerarios y reforzar o ajustar los temas con más abandonos.',
    ];
    if ($usageLogic === \Nammu\Core\Itinerary::USAGE_LOGIC_SEQUENTIAL || $usageLogic === \Nammu\Core\Itinerary::USAGE_LOGIC_ASSESSMENT) {
        $usageReasons[] = 'Asegurarnos de que no se lea un tema sin haber leído el anterior cuando el itinerario requiere avanzar en orden.';
    }
    if ($usageLogic === \Nammu\Core\Itinerary::USAGE_LOGIC_ASSESSMENT) {
        $usageReasons[] = 'Comprobar que se han superado las autoevaluaciones cuando el autor lo ha configurado así.';
    }
    $usageNotice = '';
    if (!empty($usageReasons)) {
        $usageNotice = '<strong>Uso de cookies en este itinerario:</strong><ul class="mb-0">';
        foreach ($usageReasons as $reason) {
            $usageNotice .= '<li>' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $usageNotice .= '</ul>';
    }
?>
<?php if (!empty($topicSummaries)): ?>
    <section class="itinerary-topics">
        <h2>Temas del itinerario</h2>
        <div class="itinerary-topics__list">
            <?php foreach ($topicSummaries as $topic): ?>
                <?php
                    $topicImage = $topic['image'] ?? null;
                    if (!$topicImage && method_exists($itinerary, 'getImage')) {
                        $topicImage = $itinerary->getImage();
                    }
                    $topicImageUrl = $topicImage ? $resolveImage($topicImage) : null;
                ?>
                <article class="itinerary-topic-card">
                    <?php if ($topicImageUrl): ?>
                        <figure class="itinerary-topic-card__media">
                            <img src="<?= htmlspecialchars($topicImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($topic['title'], ENT_QUOTES, 'UTF-8') ?>">
                        </figure>
                    <?php endif; ?>
                    <div class="itinerary-topic-card__number">
                        Tema <?= (int) $topic['number'] ?>
                    </div>
                    <div class="itinerary-topic-card__body">
                        <?php if (!empty($topic['meta'])): ?>
                            <div class="post-meta-band"><?= htmlspecialchars($topic['meta'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <h3>
                            <a href="<?= htmlspecialchars($topic['url'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($topic['title'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </h3>
                        <?php if ($topic['description'] !== ''): ?>
                            <p class="itinerary-topic-card__description"><?= htmlspecialchars($topic['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($firstTopicUrl): ?>
            <div class="itinerary-topics__cta">
                <a class="button button-primary" href="<?= htmlspecialchars($firstTopicUrl, ENT_QUOTES, 'UTF-8') ?>">Comenzar itinerario</a>
            </div>
        <?php endif; ?>
        <?php if (!empty($itinerariesIndexUrl)): ?>
            <div class="itinerary-topics__back mt-3" style="text-align: center;">
                <a class="button button-secondary" href="<?= htmlspecialchars($itinerariesIndexUrl, ENT_QUOTES, 'UTF-8') ?>">Todos los itinerarios</a>
            </div>
        <?php endif; ?>
        <?php if ($usageNotice !== ''): ?>
            <div class="itinerary-usage-alert" role="note">
                <?= htmlspecialchars($usageNotice, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
