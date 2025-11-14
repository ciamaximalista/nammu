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
?>
<div class="itinerary-single-content">
    <?= $itineraryBody ?>
</div>

<?php
    $usageLogic = method_exists($itinerary, 'getUsageLogic') ? $itinerary->getUsageLogic() : 'free';
    $usageNotice = '';
    if ($usageLogic === \Nammu\Core\Itinerary::USAGE_LOGIC_SEQUENTIAL) {
        $usageNotice = 'Este itinerario usa cookies para asegurar que sigues el orden de temas creado por su autor. La información guardada en esas cookies se usa exclusivamenente para ese fin. Al iniciar el itinerario aceptas su uso.';
    } elseif ($usageLogic === \Nammu\Core\Itinerary::USAGE_LOGIC_ASSESSMENT) {
        $usageNotice = 'Este itinerario usa cookies para asegurar que sigues el orden de temas creado por su autor y que  pasas las autoevaluaciones entre temas. La información guardada en esas cookies se usa exclusivamenente para esos fines. Al iniciar el itinerario aceptas su uso.';
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
        <?php if ($usageNotice !== ''): ?>
            <div class="itinerary-usage-alert" role="note">
                <?= htmlspecialchars($usageNotice, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
