Nammu - Instrucciones rápidas
============================

Instalación
-----------
1. Clona el repositorio en la raíz pública de tu dominio.
2. Ajusta permisos de escritura para `content/`, `config/`, `assets/`, `itinerarios/` y `backups/`.
3. Configura el virtualhost para que `index.php` sea el front controller.
4. Entra en `/admin.php` para completar configuración inicial.

Instalación - Cron obligatorio
------------------------------
Para evitar carga extra en `index.php`, las tareas programadas deben ejecutarse por cron.

Tareas recomendadas:
- Publicación programada y cola de notificaciones: cada minuto.
- Backup de estadísticas: diario.
- Limpieza de backups de estadísticas: semanal (retención 7 días).

Comandos (ejemplo por instalación):
- Programadas: `php /var/www/html/blogs/<instalacion>/admin.php --run-scheduled`
- Backup diario: `php /var/www/html/blogs/<instalacion>/core/backup-daily.php --retention=7`
- Limpieza semanal: `php /var/www/html/blogs/<instalacion>/core/backup-daily.php --cleanup-only --retention=7`

Ejemplo de crontab:
* * * * * www-data php /var/www/html/blogs/<instalacion>/admin.php --run-scheduled >> /var/www/html/blogs/<instalacion>/backups/cron.log 2>&1
15 3 * * * www-data php /var/www/html/blogs/<instalacion>/core/backup-daily.php --retention=7 >> /var/www/html/blogs/<instalacion>/backups/backup.log 2>&1
30 3 * * 0 www-data php /var/www/html/blogs/<instalacion>/core/backup-daily.php --cleanup-only --retention=7 >> /var/www/html/blogs/<instalacion>/backups/backup.log 2>&1
