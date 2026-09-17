![Nammu](nammu-banner.png)

# Nammu

**Un CMS en PHP, sin base de datos, que convierte tu web en un blog, un podcast, una newsletter, una escuela y una cuenta del Fediverso a la vez.**

Nammu cabe en una carpeta. Se instala clonando un repositorio en cualquier hosting con PHP 8, guarda todo en ficheros Markdown, YAML y JSON, y desde un único panel te deja publicar, enseñar, medir y difundir sin regalar datos a ninguna empresa. Licencia **EUPL**.

- [Por qué Nammu](#por-qué-nammu)
- [Qué puedes montar](#qué-puedes-montar-con-nammu)
- [Lo que trae de serie](#lo-que-trae-de-serie)
- [Sitios que ya funcionan con Nammu](#sitios-que-ya-funcionan-con-nammu)
- [Instalación rápida](#instalación-rápida)
- [Instalación paso a paso](#instalación-paso-a-paso)
- [Cron y backups](#cron-y-backups)
- [Actualización](#actualización)
- [Varias instalaciones en el mismo servidor](#varias-instalaciones-en-el-mismo-servidor)
- [Guía del panel](#guía-del-panel)
- [Feeds y archivos auxiliares](#feeds-y-archivos-auxiliares)
- [Estructura del código](#estructura-del-código)
- [Licencia y soporte](#licencia-y-soporte)

## Por qué Nammu

**Sin base de datos, sin framework, sin build.** Todo el contenido vive en `content/` como Markdown con front matter; la configuración, en `config/`. Copias la carpeta y tienes el sitio entero, con su historial en Git si quieres. Es compatible con la estructura `content/` y `assets/` de PicoCMS, así que migrar desde allí es copiar dos carpetas.

**Tu blog es una cuenta del Fediverso.** Nammu expone WebFinger, actor, `inbox`, `outbox`, seguidores y seguidos. Desde Mastodon, Akkoma, Pixelfed o GoToSocial se puede seguir el blog como `@nombre@tu-dominio`; cada entrada, episodio o itinerario llega a los seguidores, y desde el panel respondes, impulsas, marcas favoritos, mandas mensajes privados y gestionas quién te sigue. También envía y recibe Webmentions.

**Estadísticas propias y sin terceros.** Usuarios únicos, páginas vistas, búsquedas internas, orígenes, bots y progreso de itinerarios se calculan en tu servidor. Nada sale hacia fuera. Si quieres, se conecta a Google Search Console y Bing Webmaster Tools para completar el cuadro.

**Un solo panel para todo.** Publicar, editar, biblioteca de medios con editor de imágenes, plantilla visual, itinerarios formativos, lista de correo, redes sociales, Fediverso y estadísticas. Todo dentro de `admin.php`, sin extensiones que instalar.

**Difusión automática.** Al publicar, Nammu puede avisar por correo a tu lista, enviar notificaciones push, publicar en Telegram, Facebook, X, Bluesky, Instagram y LinkedIn, notificar a IndexNow y repartir la publicación por ActivityPub. Todo opcional, todo desde el cron.

**Pensado para durar.** Feeds RSS específicos por tipo de contenido, `sitemap.xml`, Open Graph completo, `llms.txt` e `identity.txt` editables, versión limpia para lectores diferidos (Instapaper, Kobo, Pocket, Wallabag) y backups diarios y semanales de serie.

## Qué puedes montar con Nammu

| Quieres… | Nammu te da… |
| --- | --- |
| Un blog personal o de proyecto | Entradas y páginas en Markdown, categorías, buscador, portada configurable, páginas privadas para el administrador |
| Un diccionario o glosario multimedia | Modo diccionario con índice por letras y búsqueda avanzada |
| Un podcast | Episodios con audio mp3, página HTML por episodio y `podcast.xml` con metadatos iTunes |
| Una newsletter | Envío por Gmail (OAuth2), archivo privado, vista web y control de envíos |
| Un sistema de avisos | Lista de suscriptores con preferencias, avisos por email al publicar y notificaciones push |
| Un curso, un libro o una colección | Itinerarios con temas, quizzes, lógica libre/secuencial/evaluación, progreso por lector y estadísticas |
| Presencia en el Fediverso | Cuenta ActivityPub propia del blog, perfil público `@usuario@dominio`, hilos, notificaciones, mensajes y Webmentions |
| Un agregador de fuentes | Perfil de actualidad con RSS externas y notas manuales, reenvío automático a redes y `noticias.xml` |

## Lo que trae de serie

**Edición y publicación**

- Markdown con front matter YAML, `[toc]`, tablas, superíndices, bloques de código y callouts.
- Vídeos de YouTube y PeerTube incrustados pegando la URL, incluso en instancias PeerTube con dominio propio.
- Borradores, previsualización, publicación programada con fecha y hora, entradas relacionadas.
- Podcast, newsletter y avisos por email separados entre sí.

**Biblioteca de recursos**

- Subida múltiple de imágenes, vídeos, audio y documentos (pdf, docx, xlsx, pptx, odt, md…), con etiquetas y buscador instantáneo.
- Editor web de imágenes con recorte y ajustes básicos; variantes WebP automáticas servidas por `.htaccess`.
- Selector de recursos compartido en Publicar, Editar, Itinerarios y Redes.

**Diseño**

- Tipografías de Google Fonts, paleta de color, cabeceras, tipos de tarjeta, buscador flotante o fijo, footer con HTML libre y botonera común.

**SEO, feeds y máquinas**

- `rss.xml`, `blog.xml`, `podcast.xml`, `itinerarios.xml`, `noticias.xml`, `fediverso.xml`, `sitemap.xml`.
- Open Graph y Twitter Cards completas, canonical, datos estructurados, IndexNow.
- `llms.txt` e `identity.txt` editables desde el panel.

**Fediverso y ActivityPub**

- Actor federado con firma HTTP; se federan entradas, episodios, portadas de itinerarios, noticias agregadas y notas manuales.
- Seguir, ser seguido, bloquear, favoritos, impulsos, respuestas, mensajes privados, reenvíos como nota, borrados.
- Páginas públicas de hilo por cada objeto, con respuestas e impulsos recibidos.
- Módulo **Fediverso** en el panel: `Inicio`, `Notificaciones`, `Mensajes`, `Menciones`, `Red` y `Configuración`.
- Endpoint `POST /webmention` con verificación, y bloque `Menciones en otros blogs` bajo las entradas.

**Itinerarios y formación**

- Portada propia, temas numerados con quiz opcional, clases Libro/Curso/Colección/Otros.
- Progreso guardado en el navegador del lector; estadísticas por tema y reseteo desde el panel.

**Estadísticas y RGPD**

- Dashboard propio; consentimiento de cookies obligatorio para humanos; sin analítica de terceros.
- Integración opcional con Google Search Console y Bing Webmaster Tools.

**Redes sociales**

- Telegram, Facebook Pages, X (OAuth 1.0a), Bluesky, Instagram y LinkedIn.
- Autoenvío al publicar y envíos manuales con imagen y contador de caracteres.
- RSS externas reenviadas automáticamente a redes y al perfil del Fediverso.

**Operación**

- Cron en tres fases (ligera, mantenimiento, pesada) con locks para no solaparse.
- Backup diario de estadísticas y semanal completo.
- Smoke test propio: `composer run check`.
- Planificador central opcional para servidores con varias instalaciones.

## Sitios que ya funcionan con Nammu

- [Memoria](https://memoria.repoblacion.ong)
- [Terceros lugares](https://terceroslugares.repoblacion.ong)
- [Maximalismo](https://maximalismo.blog)
- [Maximalism](https://maximalism.blog)
- [La Candela](https://lacandela.org)
- [Graneles](https://graneles.urrutiaelejalde.org)

Todos se actualizan desde este mismo repositorio y conviven en un mismo servidor, coordinados con el planificador central que se describe más abajo.

## Instalación rápida

Para quien ya sabe lo que hace. Cada paso se explica con detalle en la sección siguiente.

**Requisitos:** PHP 8.0+, extensiones `json`, `mbstring`, `iconv`, `curl` y `gd`; Apache con `mod_rewrite` (o Nginx con reglas equivalentes); un cron.

```bash
# 1. Código
cd /var/www/html/<carpeta-publica>
git clone https://github.com/ciamaximalista/nammu.git .
mkdir -p config content assets itinerarios backups

# 2. Permisos: grupo compartido entre tu usuario y el del servidor web
SITE=$PWD; DEPLOY_USER=$USER; SHARED_GROUP=www-data
sudo usermod -aG "$SHARED_GROUP" "$DEPLOY_USER"
sudo chown -R "$DEPLOY_USER:$SHARED_GROUP" "$SITE"
sudo find "$SITE" -type d -exec chmod 2775 {} \;
sudo find "$SITE" -type f -exec chmod 664 {} \;
git config core.sharedRepository group
git config core.fileMode false

# 3. Comprobación
composer run check        # debe terminar en "Smoke OK"
```

Apunta el dominio a la carpeta, abre `https://tu-dominio/admin.php`, crea el usuario inicial y añade el [bloque de cron recomendado](#bloque-de-cron-recomendado).

## Instalación paso a paso

### 1. Requisitos

- **PHP 8.0 o superior.**
- Extensiones: `json`, `mbstring`, `iconv` (imprescindibles); `curl` (integraciones externas, redes y Fediverso); `gd` (editor de imágenes, WebP, portadas de podcast); `openssl` (notificaciones push).
- **Apache con `mod_rewrite`.** El repositorio incluye el `.htaccess` que sirve las URL amigables, los feeds y bloquea el acceso HTTP a `core/`, `config/`, `content/`, `template/` y `vendor/`. Si usas Nginx, replica esas reglas (más abajo hay un ejemplo).
- Un **cron** para las tareas programadas. Sin cron, Nammu funciona, pero no publica contenidos programados, no reparte al Fediverso ni envía avisos y colas.
- **Composer es opcional.** El núcleo no lo necesita; solo hace falta para las notificaciones push (`minishlink/web-push`) y para ejecutar `composer run check`.

### 2. Obtén el código

Con el directorio del sitio vacío:

```bash
cd /var/www/html/<carpeta-publica>
git clone https://github.com/ciamaximalista/nammu.git .
```

También puedes descargar un ZIP y descomprimirlo en la carpeta pública del dominio, pero con Git actualizarás con un `git pull`.

Las carpetas con datos del sitio no vienen en el repositorio (están en `.gitignore`), así que créalas:

```bash
mkdir -p config content assets itinerarios backups
```

- `config/`: configuración, usuario, claves, colas y stores JSON.
- `content/`: entradas, páginas, podcasts y newsletters en Markdown.
- `assets/`: imágenes, audio, vídeo y documentos subidos.
- `itinerarios/`: cursos, libros y colecciones.
- `backups/`: copias de seguridad y logs del cron.

### 3. Permisos

Este es el paso en el que más instalaciones fallan, así que merece atención. Nammu escribe ficheros desde varios sitios distintos: el panel web (usuario de PHP), el cron, `git pull` (tu usuario) y a veces comandos manuales. Para que ninguno bloquee al otro, la solución es un **grupo compartido** entre el usuario del servidor web y el usuario con el que administras el repositorio.

Identifica los usuarios reales:

```bash
ps -eo user,group,comm,args | grep -E 'apache|www-data|php-fpm|nginx' | grep -v grep
id <tu-usuario>
```

En Debian/Ubuntu lo normal es `www-data:www-data`. En otros servidores puede ser `apache:apache`, `nginx:nginx` o `nobody:nogroup`. Define las variables con los valores de tu servidor:

```bash
SITE=/var/www/html/<carpeta-publica>
DEPLOY_USER=<tu-usuario>
WEB_USER=www-data
SHARED_GROUP=www-data
```

`SHARED_GROUP` debe ser un grupo al que pertenezcan tanto `DEPLOY_USER` como `WEB_USER`. No mezcles `www-data` con `nogroup`: si el proceso web corre como `nobody:nogroup`, usa `WEB_USER=nobody` y `SHARED_GROUP=nogroup`; si no, el cron y Apache no podrán reescribir stores como `config/fediverso-deliveries.json`.

Añade tu usuario al grupo (vuelve a iniciar sesión para que tenga efecto) y aplica propietario, escritura de grupo y bit `setgid` en directorios, que hace que los ficheros nuevos hereden el grupo aunque los cree PHP, cron o Git:

```bash
sudo usermod -aG "$SHARED_GROUP" "$DEPLOY_USER"
sudo chown -R "$DEPLOY_USER:$SHARED_GROUP" "$SITE"
sudo find "$SITE" -type d -exec chmod 2775 {} \;
sudo find "$SITE" -type f -exec chmod 664 {} \;
```

Nammu aplica `umask(0002)` al arrancar y escribe directorios con `setgid` y ficheros `0664`, así que a partir de aquí los nuevos JSON, feeds, colas, assets y cachés quedan accesibles para ambos usuarios. Pero solo si el árbol inicial ya tiene el grupo correcto.

Los scripts se ejecutan como `php archivo.php`, así que no necesitan bit ejecutable. Configura Git para que no toque los permisos en cada `pull`:

```bash
cd "$SITE"
git config core.sharedRepository group
git config core.fileMode false
git config --global --add safe.directory "$SITE"
```

Usa también `umask 0002` en tus sesiones manuales, scripts de despliegue y líneas de cron para que Git, Composer y las redirecciones de log no creen ficheros sin escritura de grupo.

Directorios que deben ser escribibles por el usuario web y por el de despliegue: `config/`, `content/`, `content/uploads/`, `assets/`, `assets/actualidad-cache/`, `itinerarios/`, `backups/`, `vendor/` (si usas Composer) y `.git/` (si actualizas con Git desde el mismo directorio).

**Comprobación:**

```bash
id "$DEPLOY_USER"; id "$WEB_USER"
ls -ld "$SITE" "$SITE/.git" "$SITE/config" "$SITE/assets" "$SITE/backups"
cd "$SITE"
touch .git/test-write config/test-write assets/test-write backups/test-write
rm .git/test-write config/test-write assets/test-write backups/test-write
git config --get core.sharedRepository   # group
git config --get core.fileMode           # false
composer run check                       # Smoke OK
```

Los directorios deben verse como `drwxrwsr-x` con el grupo compartido. Si aparecen ficheros de otro propietario sin escritura de grupo, o Git falla con `index.lock`, `FETCH_HEAD` o `dubious ownership`, corrige permisos antes de activar el cron.

Si corriges una instalación que ya existía, empieza por las rutas mutables:

```bash
sudo chown -R "$DEPLOY_USER:$SHARED_GROUP" "$SITE/config" "$SITE/content" "$SITE/assets" "$SITE/itinerarios" "$SITE/backups" "$SITE/.git"
sudo find "$SITE/config" "$SITE/content" "$SITE/assets" "$SITE/itinerarios" "$SITE/backups" "$SITE/.git" -type d -exec chmod 2775 {} \;
sudo find "$SITE/config" "$SITE/content" "$SITE/assets" "$SITE/itinerarios" "$SITE/backups" "$SITE/.git" -type f -exec chmod 664 {} \;
```

### 4. Servidor web

El dominio debe apuntar a la carpeta de Nammu y servir `index.php` con PHP 8+.

**Apache:** `DocumentRoot /var/www/html/<carpeta-publica>` con `AllowOverride All` para que se lea el `.htaccess` del repositorio.

**Nginx** no lee `.htaccess`; un bloque orientativo equivalente sería:

```nginx
server {
    server_name tu-dominio;
    root /var/www/html/<carpeta-publica>;
    index index.php;

    location ~ ^/(config|content|core|template|vendor)(/|$) { return 404; }
    location ~ /\.(?!well-known) { return 404; }

    location ~ ^/(rss|blog|sitemap|podcast)\.xml$ { rewrite ^ /index.php last; }
    location ~ ^/(llms|llms-posts|identity)\.txt$ { rewrite ^ /index.php last; }
    location ~ ^/itinerarios(/|$) { rewrite ^ /index.php last; }
    location = /avisos { rewrite ^ /avisos.php last; }
    location = /correos { rewrite ^ /correos.php last; }

    location / { try_files $uri $uri/ /index.php?$args; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
```

Ajusta el socket de PHP-FPM y compara con el `.htaccess` del repositorio si añades funciones (por ejemplo, el servido automático de variantes WebP).

### 5. Primer acceso

Abre `https://tu-dominio/admin.php`. En la primera visita Nammu crea el usuario administrador y guarda la configuración básica.

Después, revisa estas pestañas:

1. **Configuración**: nombre del sitio, autor, idioma, modo blog/diccionario, URL del sitio. Al final de la pestaña se editan `llms.txt` e `identity.txt` y el bloque de formas de contacto para los lectores.
2. **Plantilla**: tipografías, colores, portada, footer y botones de cabecera.
3. **Difusión**: redes sociales, push, podcast y metadatos sociales.
4. **Lista**: correo saliente y suscripciones si vas a usar avisos o newsletters.

### 6. Notificaciones push (opcional)

```bash
cd /var/www/html/<carpeta-publica>
composer require minishlink/web-push
```

Al activar Push en **Difusión**, Nammu genera las claves VAPID.

## Cron y backups

### Las tres fases

Nammu reparte la automatización en tres comandos para que el panel de Fediverso cargue rápido sin picos de CPU:

| Comando | Cada | Hace |
| --- | --- | --- |
| `admin.php --run-scheduled` | 5 min | Fase **ligera**: refresca actores seguidos, recoge respuestas y reacciones nuevas, precalienta unos pocos hilos recientes y regenera los snapshots de `Inicio` y `Notificaciones` |
| `admin.php --run-scheduled-maintenance` | 15 min | Fase de **mantenimiento**: publica contenidos programados, procesa avisos y colas, reconstruye `Actualidad`, revisa Nisaba, Telex y otras RSS, descubre y envía Webmentions, envía a redes sociales, reparte publicaciones federadas y procesa borrados/accepts |
| `admin.php --run-scheduled-heavy` | 1 h | Fase **pesada**: precalienta cachés e hilos del Fediverso y regenera los snapshots completos del panel |

Además:

- `core/backup-daily.php`: backup diario de estadísticas (`config/analytics.json`, `config/analytics.last-good.json`, `config/gsc-cache.json`, `config/bing-cache.json`, `itinerarios/*/stats.json`).
- `core/backup-weekly.php`: copia comprimida semanal de `content/`, `assets/`, `config/` e `itinerarios/`.
- `admin.php --run-fediverse-link-card-refresh` (opcional): resuelve `og:title`, `og:description` y `og:image` de los enlaces del Fediverso en segundo plano, con lock propio (`config/.fediverse-link-cards.lock`) para no bloquear a las otras fases.

Las fases usan un lock interno común, así que `light`, `maintenance` y `heavy` no se solapan aunque el cron las dispare demasiado cerca. Aun así, protege cada línea con `flock`.

### Cómo editar el cron

Edita el cron del usuario del servidor web para que los ficheros que se reescriben en caliente sigan siendo suyos:

```bash
sudo crontab -u "$WEB_USER" -e
```

Con ese comando las líneas van **sin** la columna del usuario. Si en vez de eso editas `/etc/crontab` o usas `sudo crontab -e`, añade el usuario web real (`www-data`, `apache`, `nginx`, `nobody`) delante del comando.

Comprueba que quedó activo:

```bash
sudo crontab -u "$WEB_USER" -l
sudo systemctl status cron --no-pager
```

### Bloque de cron recomendado

En **Configuración** puedes definir un directorio común de backups. Si no lo haces, se usa `/var/www/html/<carpeta-publica>/backups`. Si eliges una ruta compartida como `/media/backups`, Nammu usa una subcarpeta por sitio (`/media/backups/<carpeta-publica>`); `backup-daily.php` y `backup-weekly.php` leen esa configuración sin necesitar `--dest`. Ajusta las redirecciones `>>` para que los logs vayan al mismo sitio.

```bash
*/5 * * * * umask 0002; flock -n /tmp/<carpeta-publica>-run-scheduled.lock php /var/www/html/<carpeta-publica>/admin.php --run-scheduled >> <directorio-backups>/cron.log 2>&1
12,27,42,57 * * * * umask 0002; flock -n /tmp/<carpeta-publica>-run-scheduled-maintenance.lock php /var/www/html/<carpeta-publica>/admin.php --run-scheduled-maintenance >> <directorio-backups>/cron.log 2>&1
7 * * * * umask 0002; flock -n /tmp/<carpeta-publica>-run-scheduled-heavy.lock php /var/www/html/<carpeta-publica>/admin.php --run-scheduled-heavy >> <directorio-backups>/cron.log 2>&1
5-55/10 * * * * umask 0002; flock -n /tmp/<carpeta-publica>-fediverse-link-cards.lock php /var/www/html/<carpeta-publica>/admin.php --run-fediverse-link-card-refresh >> <directorio-backups>/fediverse-link-cards.log 2>&1
15 3 * * * umask 0002; flock -n /tmp/<carpeta-publica>-backup-daily.lock php /var/www/html/<carpeta-publica>/core/backup-daily.php --retention=7 >> <directorio-backups>/backup.log 2>&1
30 3 * * 0 umask 0002; flock -n /tmp/<carpeta-publica>-backup-cleanup.lock php /var/www/html/<carpeta-publica>/core/backup-daily.php --cleanup-only --retention=7 >> <directorio-backups>/backup.log 2>&1
45 3 * * 0 umask 0002; flock -n /tmp/<carpeta-publica>-backup-weekly.lock php /var/www/html/<carpeta-publica>/core/backup-weekly.php --retention-weeks=8 >> <directorio-backups>/backup-full.log 2>&1
```

### Diagnóstico

Si una nota aparece en el perfil público pero `maintenance` no la entrega a los seguidores, casi siempre es un problema de permisos en los stores del Fediverso:

```bash
stat -c '%a %U:%G %n' "$SITE/config" "$SITE/config/fediverso-deliveries.json" "$SITE/config/actualidad-items.json" "$SITE/config/social-rss-state.json"
sudo -u "$WEB_USER" test -w "$SITE/config/fediverso-deliveries.json" && echo "fediverso-deliveries escribible"
sudo -u "$WEB_USER" test -w "$SITE/config" && echo "config escribible"
```

`config/fediverso-deliveries.json` debe ser escribible por `WEB_USER`; si no, Nammu detiene las entregas con `fediverse_delivery_error=deliveries_store_not_writable`.

### Restaurar el backup diario de estadísticas

```bash
tar -xzf /var/www/html/<carpeta-publica>/backups/nammu-stats-backup-AAAA-MM-DD_HHMMSS.tar.gz -C /var/www/html/<carpeta-publica>
```

## Actualización

Con las mismas variables de la instalación:

```bash
SITE=/var/www/html/<carpeta-publica>
DEPLOY_USER=<tu-usuario>
SHARED_GROUP=www-data

cd "$SITE"
git pull origin main
sudo chown -R "$DEPLOY_USER:$SHARED_GROUP" config content assets itinerarios backups .git
sudo find config content assets itinerarios backups .git -type d -exec chmod 2775 {} \;
sudo find config content assets itinerarios backups .git -type f -exec chmod 664 {} \;
git config core.sharedRepository group
git config core.fileMode false
git config --global --add safe.directory "$SITE"
composer run check
```

Si mantienes dependencias con Composer dentro del sitio, incluye también `vendor` en los tres comandos de permisos.

## Varias instalaciones en el mismo servidor

Todo lo de esta sección es opcional. Una instalación única no necesita tocar nada.

### Escalonar los cron

Si tienes varios sitios, no los lances en el mismo minuto. Desplaza cada fase un minuto por sitio:

```bash
*/5 * * * * umask 0002; flock -n /tmp/sitio-a-run-scheduled.lock php /var/www/html/sitio-a/admin.php --run-scheduled >> /var/www/html/sitio-a/backups/cron.log 2>&1
1-56/5 * * * * umask 0002; flock -n /tmp/sitio-b-run-scheduled.lock php /var/www/html/sitio-b/admin.php --run-scheduled >> /var/www/html/sitio-b/backups/cron.log 2>&1
2-57/5 * * * * umask 0002; flock -n /tmp/sitio-c-run-scheduled.lock php /var/www/html/sitio-c/admin.php --run-scheduled >> /var/www/html/sitio-c/backups/cron.log 2>&1

12,27,42,57 * * * * umask 0002; flock -n /tmp/sitio-a-run-scheduled-maintenance.lock php /var/www/html/sitio-a/admin.php --run-scheduled-maintenance >> /var/www/html/sitio-a/backups/cron.log 2>&1
13,28,43,58 * * * * umask 0002; flock -n /tmp/sitio-b-run-scheduled-maintenance.lock php /var/www/html/sitio-b/admin.php --run-scheduled-maintenance >> /var/www/html/sitio-b/backups/cron.log 2>&1
14,29,44,59 * * * * umask 0002; flock -n /tmp/sitio-c-run-scheduled-maintenance.lock php /var/www/html/sitio-c/admin.php --run-scheduled-maintenance >> /var/www/html/sitio-c/backups/cron.log 2>&1

7 * * * * umask 0002; flock -n /tmp/sitio-a-run-scheduled-heavy.lock php /var/www/html/sitio-a/admin.php --run-scheduled-heavy >> /var/www/html/sitio-a/backups/cron.log 2>&1
17 * * * * umask 0002; flock -n /tmp/sitio-b-run-scheduled-heavy.lock php /var/www/html/sitio-b/admin.php --run-scheduled-heavy >> /var/www/html/sitio-b/backups/cron.log 2>&1
27 * * * * umask 0002; flock -n /tmp/sitio-c-run-scheduled-heavy.lock php /var/www/html/sitio-c/admin.php --run-scheduled-heavy >> /var/www/html/sitio-c/backups/cron.log 2>&1

5-55/10 * * * * umask 0002; flock -n /tmp/sitio-a-fediverse-link-cards.lock php /var/www/html/sitio-a/admin.php --run-fediverse-link-card-refresh >> /var/www/html/sitio-a/backups/fediverse-link-cards.log 2>&1
6-56/10 * * * * umask 0002; flock -n /tmp/sitio-b-fediverse-link-cards.lock php /var/www/html/sitio-b/admin.php --run-fediverse-link-card-refresh >> /var/www/html/sitio-b/backups/fediverse-link-cards.log 2>&1
7-57/10 * * * * umask 0002; flock -n /tmp/sitio-c-fediverse-link-cards.lock php /var/www/html/sitio-c/admin.php --run-fediverse-link-card-refresh >> /var/www/html/sitio-c/backups/fediverse-link-cards.log 2>&1
```

### Declaración de multiinstancia

En **Configuración** hay una sección opcional de multiinstancia con estos campos:

- `enabled`: activa la declaración para ese sitio.
- `cluster`: nombre lógico del grupo de sitios.
- `shared_cache_dir`: caché remota compartida.
- `shared_queue_dir`: colas compartidas.
- `instances_root_dir`: directorio donde viven las instalaciones del clúster.
- `scheduler_mode`: `standalone` o `central`.
- `scheduler_strategy`: `fixed` o `activity`.

Con `enabled = on` y `shared_cache_dir` escribible, las instancias comparten documentos WebFinger, documentos ActivityPub remotos, estado de actores remotos y metadatos de social cards y link cards. Si el directorio no existe o no es escribible, cada sitio sigue con sus cachés locales.

Rutas compartidas típicas, con las mismas variables de permisos:

```bash
sudo mkdir -p /var/www/html/blogs/_shared-cache /var/www/html/blogs/_shared-queue
sudo chown -R "$DEPLOY_USER:$SHARED_GROUP" /var/www/html/blogs/_shared-cache /var/www/html/blogs/_shared-queue
sudo find /var/www/html/blogs/_shared-cache /var/www/html/blogs/_shared-queue -type d -exec chmod 2775 {} \;
sudo find /var/www/html/blogs/_shared-cache /var/www/html/blogs/_shared-queue -type f -exec chmod 664 {} \;
```

### Planificador central

Si varias instalaciones comparten `cluster`, `shared_queue_dir` y `scheduler_mode = central`, un único runner sustituye a los cron individuales de las tres fases:

```bash
* * * * * umask 0002; /usr/bin/timeout -k 10s 120s /usr/bin/flock -n /tmp/<cluster>-run-cluster.lock /usr/bin/php /var/www/html/<carpeta-publica>/admin.php --run-cluster-scheduled >> <directorio-backups>/cluster-cron.log 2>&1
```

El runner descubre los sitios hermanos, reparte sus fases `light`, `maintenance` y `heavy`, ejecuta internamente los comandos normales de cada uno y evita duplicados con un lock por clúster y un estado compartido de slots ejecutados. Un sitio en `standalone` conserva su cron propio y queda fuera.

El `timeout` debe superar con holgura lo que tarda una fase (una `light` con actores lentos puede pasar de 45 s); si se queda corto, mata al runner antes de que escriba en el log y la fase se repite al minuto siguiente.

Para que funcione, `config/*.json` de cada sitio, `_shared-cache/`, `_shared-queue/` y `backups/cluster-cron.log` deben ser escribibles por el usuario que ejecuta el cron.

Estrategias:

- `fixed` (por defecto): reparto por slots fijos según el índice de cada sitio.
- `activity`: prioriza los sitios con contenido local reciente (publicaciones nuevas en `content/` y notas en `config/actualidad-manual.json`) con esta cadencia orientativa:
  - frescos: `light` cada 10 min, `maintenance` cada 30, `heavy` cada 60;
  - templados: `light` cada 20 min, `maintenance` cada 60, `heavy` cada 180;
  - inactivos: `light` cada 60 min, `maintenance` cada 180, `heavy` cada 720.

### Colas y pacing compartidos

Con `shared_queue_dir` activo, Nammu mueve a ese directorio, separadas por sitio mediante un sufijo derivado de la URL base, las colas `fediverso-announce-queue`, `fediverso-undo-announce-queue`, `fediverso-delete-queue`, `webmention-queue` y `webmention-state`.

También mantiene un estado compartido por host remoto para fetches y entregas ActivityPub, Webmentions y fetches de social cards: espacia suavemente las peticiones concurrentes al mismo host y propaga un backoff común cuando el servidor remoto devuelve `429`, `503`, `504`, `5xx` o no responde.

### Dashboard en modo central

Con `scheduler_mode = central`, el dashboard añade un bloque `Clúster central` con los sitios detectados, la estrategia activa, el reparto por perfil (`fresh`, `warm`, `idle`), la instancia que actuó por última vez como runner, los slots recientes, los hosts remotos seguidos y en backoff, y el estado de caché y cola compartidas.

## Guía del panel

### Pestañas

- **Publicar**: entradas, páginas, podcasts y newsletters con fecha amigable, slug, imagen destacada y modal para insertar recursos.
- **Editar**: tabla con paginación, buscador, estados, acciones rápidas y botones para disparar envíos sociales.
- **Recursos**: biblioteca con filtros por tipo, subida múltiple, etiquetado, buscador instantáneo y mini editor de imágenes.
- **Plantilla**: tipografías, colores, cabeceras, buscador (posición y modo flotante), TOC por defecto y entradas por página.
- **Itinerarios**: portadas, clase (Libro, Curso, Colección, Otros), lógica, temas, quizzes y estadísticas.
- **Configuración**: modo blog/diccionario, búsqueda avanzada, datos del sitio, API de Google Fonts, correo de lista (Gmail + OAuth), `llms.txt`, `identity.txt`, multiinstancia y cambio de contraseña.
- **Difusión**: credenciales por red con guías rápidas, usuario público de X para el footer y `twitter:site`, App ID de Facebook, tokens de Instagram y LinkedIn, autoenvío.
- **Redes**: envío manual a varias redes a la vez, notas manuales para el perfil y RSS externas para reenvío automático.
- **Lista**: suscriptores, avisos y newsletters.
- **Fediverso**: timeline, notificaciones, mensajes, menciones y gestión de seguidores, seguidos y bloqueados.

Los modales de recursos de Publicar, Editar e Itinerarios comparten el mismo buscador. Tras cada acción, el panel muestra alertas discretas (`$_SESSION['asset_feedback']`, `$_SESSION['itinerary_feedback']`, etc.).

Tres atajos útiles:

- **Avisos**: publica una entrada o itinerario y, si están activados, se envía un aviso con título, descripción y enlace.
- **Newsletter**: “Enviar como newsletter” desde Publicar o Editar envía el contenido completo sin publicarlo en el blog.
- **Recalcular Ordo**: reordena automáticamente el campo `Ordo` según la fecha de publicación.

### Lista de correo con Gmail (OAuth2)

1. En [Google Cloud Console](https://console.cloud.google.com/), con la cuenta de Gmail que quieras usar:
   1. Selector de proyectos → **Proyecto nuevo**; ponle nombre y créalo. Asegúrate de que queda seleccionado.
   2. Menú ☰ → **APIs y servicios → Biblioteca**; busca **Gmail API** y pulsa **Habilitar**.
   3. Acepta configurar la pantalla de consentimiento OAuth; elige Público → Interno.
   4. **Credenciales → Crear credenciales → ID de cliente de OAuth** (tipo “Aplicación web”). Añade como URI autorizada la de tu blog y como URI de redirección `https://tu-dominio/admin.php?page=lista-correo&gmail_callback=1`.
   5. Google Cloud Console cambia a menudo; si te atascas, pregunta a una IA con estos pasos a mano.
2. En **Configuración** de Nammu introduce la dirección Gmail, el Client ID y el Client Secret.
3. Guarda y ve a **Lista**. Pulsa “Conectar con Google” para obtener el `refresh_token`; el estado pasará a “Conectado”.
4. Desde **Lista** añades o eliminas correos y activas avisos y newsletters; los lectores eligen sus preferencias.
5. La lista vive en `config/mailing-subscribers.json` y los tokens en `config/mailing-tokens.json`. Si cambias de cuenta, desconecta y vuelve a conectar.

### Itinerarios

1. En **Itinerarios**, pulsa “Nuevo itinerario”: título, descripción, imagen, contenido y, si quieres, autoevaluación de presentación (JSON generado desde el modal).
2. Elige la lógica: `free` (temas en cualquier orden), `sequential` (cada tema desbloquea el siguiente) o `assessment` (con evaluación).
3. Añade temas: número, título, descripción, imagen, contenido Markdown y quiz opcional. Cada tema se puede duplicar o borrar desde la misma pestaña.

El navegador del lector guarda por cookies los temas visitados y aprobados; “Comenzar itinerario” retoma el último progreso. El modal de estadísticas muestra lectores de la presentación, iniciados (quienes superaron el tema 1) y porcentaje por tema; **Poner estadísticas a cero** limpia `stats.json`.

### Fediverso en el día a día

- El perfil público del blog vive en `https://tu-dominio/@usuario@tu-dominio` y reúne entradas, podcasts, itinerarios, noticias de RSS externas y notas manuales; sustituye a la antigua vista `actualidad.php`, que puede seguir existiendo por compatibilidad.
- Cada contenido federado tiene su página pública de hilo con respuestas e impulsos recibidos, enlazable desde el panel, la web y los feeds.
- Desde el panel puedes seguir y dejar de seguir, bloquear seguidores, marcar favoritos, impulsar, responder, mandar mensajes privados, reenviar una publicación remota como nota local, borrar publicaciones y respuestas propias, y ocultar localmente respuestas de terceros.
- Las Webmentions salientes usan como `source` la URL propia de la pieza (entradas, podcast, itinerarios) o la página pública del objeto Fediverso (noticias y notas). Las entrantes se verifican, se guardan en `config/webmentions.json` y pueden mostrarse bajo las entradas.
- El cron `light` refresca actores y actividades; `maintenance` reparte publicaciones nuevas a los seguidores, descubre enlaces salientes y procesa Webmentions.

### Migración desde PicoCMS

1. Copia `content/` y `assets/` dentro de Nammu.
2. Revisa el front matter: `Title`, `Template`, `Date`, `Category`, `Image`, `Description`, `Status`, `Ordo`.
3. Ajusta **Configuración** y **Plantilla**.
4. Carga la portada una vez para regenerar feeds y sitemap.

Nammu usa un parser propio y, si está disponible, Symfony Yaml.

## Feeds y archivos auxiliares

- `rss.xml`: feed principal; publica la RSS asociada al contenido elegido como portada.
- `blog.xml`: entradas del blog con descripciones higienizadas, enlaces absolutos e imágenes destacadas.
- `podcast.xml`: episodios con metadatos iTunes, duración y mp3.
- `itinerarios.xml`: cursos, libros y colecciones.
- `noticias.xml`: perfil de actualidad con noticias externas y notas manuales.
- `fediverso.xml`: páginas públicas de hilo asociadas a lo visible en el perfil.
- `sitemap.xml`: entradas, páginas e itinerarios.
- `llms.txt`: resumen del sitio y rutas relevantes para agentes automáticos.
- `identity.txt`: ficha pública sobre quién habla en el sitio y desde qué contexto.

`llms.txt` e `identity.txt` se editan desde **Configuración**; si vacías uno y guardas, Nammu regenera una versión básica. Se sirven como texto plano en `/llms.txt` y `/identity.txt`.

Los lectores diferidos (`Instapaper`, `Kobo`, `Pocket`, `Wallabag`, `Readability`) reciben una versión del artículo sin avisos de consentimiento ni bloques accesorios; la vista normal no cambia.

## Estructura del código

Nammu no usa framework: son funciones PHP con prefijo `nammu_*` (núcleo compartido por la web pública y el panel) y `admin_*` (panel).

- `index.php` y `template/*.php`: web pública (portada, entradas, categorías, podcast, itinerarios, perfil Fediverso, buscador).
- `core/helpers.php`: núcleo compartido (configuración, analítica, feeds, push, mailing público).
- `core/fediverso.php`, `core/actualidad.php`, `core/webmention.php`, `core/admin-redes.php`, `core/postal.php`: módulos que el panel y el cron cargan bajo demanda.
- `core/Itinerary*.php`, `core/MarkdownConverter.php`, `core/RssGenerator.php`, `core/SitemapGenerator.php`: clases del espacio de nombres `Nammu\Core`.

`admin.php` es la entrada del panel y del cron (`--run-scheduled`, `--run-scheduled-maintenance`, `--run-scheduled-heavy`, `--run-cluster-scheduled`, `--run-fediverse-link-card-refresh`, `--replay-fediverse-deletes`). Es un fichero corto que arranca, despacha y monta la página; el resto vive en `core/`:

- `core/admin-<dominio>.php`: funciones del panel por dominio (`admin-scheduler`, `admin-content`, `admin-media`, `admin-itineraries`, `admin-artifacts`, `admin-settings`, `admin-search-console`, `admin-urls`, `admin-indexnow`, `admin-social`, `admin-social-senders`, `admin-mailing`, `admin-mailing-campaigns`, `admin-backups`, `admin-csrf`, `admin-cli`, `admin-view`). Solo definen funciones.
- `core/admin-actions.php` y `core/admin-actions-<grupo>.php`: acciones POST. El despachador elige el fichero por la primera clave de formulario presente y cada fichero contiene la cadena `if/elseif` de su grupo (`auth`, `content`, `social`, `itineraries`, `actuality`, `media`, `settings`, `mailing`, `postal`). Las acciones del Fediverso están en `admin-actions-fediverso.php` y los retornos OAuth en `admin-actions-oauth.php`. Todas se ejecutan tras validar el token CSRF.
- `core/admin-request-state.php`, `core/admin-endpoints.php`, `core/admin-view-*.php`: estado recuperado de la sesión, respuestas AJAX/descargas que terminan la petición y datos que necesitan las vistas. Los del Escritorio (`admin-view-dashboard.php`) se reparten en `admin-view-dashboard-{queues,search,analytics,top,counts}.php`, que se cargan en ese orden y comparten variables. Los del Fediverso están en `admin-view-fediverso-data.php` (datos y cierres comunes) y una vista por pestaña, `admin-view-fediverso-{home,notifications,messages,mentions,network,settings}.php`, que `admin-page-fediverso.php` incluye según la pestaña activa.
- `core/admin-layout-*.php`: esqueleto HTML (`head`, `auth`, `nav`, `modals`, `scripts`).
- `core/admin-page-<pestaña>.php`: plantilla de cada pestaña.
- `core/admin-assets/`: CSS y JS del panel (`admin.css` común y un `<pestaña>.css`/`<pestaña>.js` por pestaña con estilos o scripts propios). `core/` no se sirve por HTTP, así que `admin_inline_asset()` los vuelca inline.

Todas esas piezas se incluyen en el ámbito global de `admin.php` y comparten variables como `$page`, `$settings` o `$error`. `NAMMU_ROOT` (definida en `core/bootstrap.php`) es la raíz pública de la instalación y sustituye a `__DIR__` en el código del panel.

Antes de enviar cambios, `composer run check` pasa `php -l` sobre todos los ficheros, ejecuta `tests/smoke.php` y valida `composer.json`. El smoke test, además de las comprobaciones unitarias, renderiza cada pestaña del panel desde la CLI como usuario con sesión iniciada (`php -d auto_prepend_file=tests/admin-render-prepend.php admin.php`, con la pestaña en `NAMMU_RENDER_PAGE`) y falla ante cualquier aviso de PHP, así que detecta variables perdidas al mover código entre piezas.

## Licencia y soporte

Nammu se distribuye bajo la **[EUPL 1.2](LICENSE.txt)**. Puedes usarlo en proyectos comerciales u open source siempre que mantengas la atribución y compartas las modificaciones bajo la misma licencia cuando corresponda.

¿Dudas, bugs o ideas? Abre un issue en [github.com/ciamaximalista/nammu](https://github.com/ciamaximalista/nammu) o contacta con quienes mantienen el proyecto.
