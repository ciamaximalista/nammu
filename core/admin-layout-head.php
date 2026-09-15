<?php
/**
 * Nammu — panel de administración. Cabecera HTML del panel (<head>): fuentes, Bootstrap y la hoja de estilos propia.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nammu</title>
    <link rel="icon" href="nammu.png" type="image/png">
    <script>
        (function() {
            try {
                if (localStorage.getItem('nammuAdminTheme') === 'dark') {
                    document.documentElement.setAttribute('data-admin-theme', 'dark');
                }
            } catch (error) {
                // Si el navegador bloquea localStorage, el admin permanece en modo claro.
            }
        })();
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Gabarito:wght@700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
<?php admin_inline_asset('admin.css'); ?>
            </style>

        </head>
