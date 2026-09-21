<?php if ($page === 'fediverso'): ?>
    <?php include __DIR__ . '/admin-view-fediverso-data.php'; if ($isFediverseHomeTab && $fediverseNeedsLivePanel) { include __DIR__ . '/admin-view-fediverso-home-data.php'; } ?>
    <?php $fediverseInitialVersion = function_exists('nammu_fediverse_tab_version') ? nammu_fediverse_tab_version($fediverseTab) : ''; ?>
    <div class="tab-pane active" id="fediverse-admin-root" data-fediverse-admin data-active-tab="<?= htmlspecialchars($fediverseTab, ENT_QUOTES, 'UTF-8') ?>" data-active-version="<?= htmlspecialchars($fediverseInitialVersion, ENT_QUOTES, 'UTF-8') ?>">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <div>
                <h2 class="mb-1">Fediverso</h2>
                <p class="text-muted mb-0">Panel de ActivityPub para seguir actores, revisar el inbox federado del blog y preparar mensajería privada.</p>
            </div>
        </div>

        <?php if (!empty($fediverseFeedback)): ?>
            <div class="alert alert-<?= htmlspecialchars($fediverseFeedback['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($fediverseFeedback['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-4">
            <?php foreach ($fediverseTabs as $tabKey => $tabLabel): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $fediverseTab === $tabKey ? 'active' : '' ?>" href="<?= htmlspecialchars($buildTabUrl($tabKey), ENT_QUOTES, 'UTF-8') ?>" data-fediverse-tab-link="<?= htmlspecialchars($tabKey, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <div id="fediverse-tab-panel" data-fediverse-tab-panel data-fediverse-tab="<?= htmlspecialchars($fediverseTab, ENT_QUOTES, 'UTF-8') ?>">
        <!-- FEDIVERSE_TAB_PANEL_START -->
        <?php if ($fediverseCachedPanelHtml !== ''): ?>
            <?= $fediverseCachedPanelHtml ?>
        <?php elseif ($fediverseTab === 'home'): ?>
            <?php include __DIR__ . '/admin-view-fediverso-home.php'; ?>
        <?php elseif ($fediverseTab === 'notifications'): ?>
            <?php include __DIR__ . '/admin-view-fediverso-notifications.php'; ?>
        <?php elseif ($fediverseTab === 'messages'): ?>
            <?php include __DIR__ . '/admin-view-fediverso-messages.php'; ?>
        <?php elseif ($fediverseTab === 'mentions'): ?>
            <?php include __DIR__ . '/admin-view-fediverso-mentions.php'; ?>
        <?php elseif ($fediverseTab === 'network'): ?>
            <?php include __DIR__ . '/admin-view-fediverso-network.php'; ?>
        <?php elseif ($fediverseTab === 'settings'): ?>
            <?php include __DIR__ . '/admin-view-fediverso-settings.php'; ?>
        <?php endif; ?>
        <!-- FEDIVERSE_TAB_PANEL_END -->
        </div>
    </div>
    <style>
<?php admin_inline_asset('fediverso.css'); ?>
    </style>
    <script>
<?php admin_inline_asset('fediverso.js'); ?>
    </script>
<?php endif; ?>
