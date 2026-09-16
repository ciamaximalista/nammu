<?php
/**
 * Nammu — panel de administración. Datos del Escritorio. Se incluye desde core/admin-page-dashboard.php en el ámbito
 * global de admin.php y carga, en este orden, las piezas admin-view-dashboard-*.php, que comparten variables entre sí.
 */
include __DIR__ . '/admin-view-dashboard-queues.php';
include __DIR__ . '/admin-view-dashboard-search.php';
include __DIR__ . '/admin-view-dashboard-analytics.php';
include __DIR__ . '/admin-view-dashboard-top.php';
include __DIR__ . '/admin-view-dashboard-counts.php';
