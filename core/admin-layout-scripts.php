<?php
/**
 * Nammu — panel de administración. Scripts del panel: JS de la pestaña Plantilla, barra Markdown, jQuery/Bootstrap y los
 * módulos de core/admin-assets/ (recursos, editor de imágenes, itinerarios, formularios, autoguardado, tema, borrado).
 */
?>
<?php if ($page === 'template'): ?>
        <style>
        body { margin-top: 8px; margin-bottom: 8px; }
        </style>

        <script>
<?php admin_inline_asset('template-settings.js'); ?>
        </script>
<?php endif; ?>

        <script>
<?php admin_inline_asset('markdown-toolbar.js'); ?>
        </script>

        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>

        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>

        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

        <script>
        window.nammuAssetApply = <?= json_encode($assetApply, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
<?php admin_inline_asset('media-modal.js'); ?>
        </script>

        <script>
<?php admin_inline_asset('media-gallery.js'); ?>
        </script>

        <script>
<?php admin_inline_asset('image-editor.js'); ?>
        </script>

        <script>
<?php admin_inline_asset('itineraries.js'); ?>
        </script>

        <script>
<?php admin_inline_asset('editor-forms.js'); ?>
        </script>



        <script>
<?php admin_inline_asset('autosave.js'); ?>
        </script>
        <script>
<?php admin_inline_asset('admin-theme.js'); ?>
        </script>
        <script>
<?php admin_inline_asset('delete-post-modal.js'); ?>
        </script>
