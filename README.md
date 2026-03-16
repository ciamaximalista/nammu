![Nammu Logo](nammu-banner.png)

# Nammu — CMS y CRM ligero para proyectos

Nammu condensa todo lo que esperas de un gestor contemporáneo —edición Markdown, plantillas vivas, itinerarios interactivos y automatización social— en un paquete PHP 8 sin dependencias caprichosas. Es ligero, portable y lo bastante flexible como para lanzar un blog personal, un diccionario multimedia, un podcast, una newsletter, sistemas de avisos o un curso y generar tus propias estadísticas sin compartirlas con ninguna empresa.

La plataforma se distribuye bajo licencia **EUPL** y corre en cualquier hosting que ofrezca PHP 8+.

## Qué incluye Nammu

Nammu combina en una sola instalación:

- Blog y páginas en Markdown.
- Podcast con `podcast.xml` y páginas HTML por episodio.
- Newsletter y lista de avisos.
- Itinerarios, cursos y colecciones de temas.
- Portal de Actualidad con notas manuales y agregación de RSS externas.
- Nodo propio en el Fediverso mediante ActivityPub.
- Biblioteca multimedia, buscador, SEO técnico, estadísticas propias y automatización social.

## Requisitos mínimos

- PHP 8.0 o superior.
- Extensiones habituales: `json`, `mbstring`, `iconv`.
- `curl` muy recomendable para integraciones externas y redes sociales.
- `gd` recomendable para edición de imágenes, variantes WebP e imágenes de podcast.
- `openssl` para notificaciones push.
- Permisos de escritura sobre `config/`, `content/`, `assets/`, `itinerarios/` y `backups/`.
- Composer es opcional. El núcleo funciona sin él, pero algunas integraciones mejoran si está disponible.

## Instalación

### 1. Clona o copia el proyecto

Si el directorio del sitio ya existe y está vacío:

```bash
cd /var/www/html/<carpeta-publica>
git clone https://github.com/ciamaximalista/nammu.git .
```

Si prefieres descargar un ZIP, descomprímelo en la carpeta pública del dominio.

### 2. Asegura la estructura básica

Nammu trabaja sobre estas carpetas:

- `config/`
- `content/`
- `assets/`
- `itinerarios/`
- `backups/`

Si alguna no existe, créala:

```bash
mkdir -p config content assets itinerarios backups
```

### 3. Ajusta permisos

Configuración recomendada en un servidor típico con `www-data`:

```bash
sudo chown -R <tu-usuario>:www-data /var/www/html/<carpeta-publica>
sudo find /var/www/html/<carpeta-publica> -type d -exec chmod 2775 {} \;
sudo find /var/www/html/<carpeta-publica> -type f -exec chmod 664 {} \;
```

Sustituye:

- `<tu-usuario>` por tu usuario del sistema.
- `<carpeta-publica>` por la carpeta real del sitio.

Si tu servidor usa otro grupo para PHP o Apache (`apache`, `nginx`, etc.), cambia `www-data` por el correspondiente.

### 4. Configura el dominio o virtual host

Tu dominio debe apuntar a la carpeta donde está Nammu y ejecutar `index.php` con PHP 8+.

Ejemplos habituales:

- Apache con `DocumentRoot /var/www/html/<carpeta-publica>`
- Nginx con `root /var/www/html/<carpeta-publica>`

### 5. Entra al panel y crea el usuario inicial

Abre:

```text
https://tu-dominio/admin.php
```

En el primer acceso Nammu crea el usuario inicial y guarda la configuración básica.

### 6. Configura lo esencial desde el admin

Nada más entrar, revisa estas pantallas:

1. **Configuración**: nombre del sitio, autor, idioma, modo blog/diccionario, URL del sitio.
2. **Plantilla**: tipografías, colores, portada, footer y botones de cabecera.
3. **Difusión**: redes sociales, push, podcast y metadatos sociales.
4. **Lista**: correo saliente y suscripciones si vas a usar avisos o newsletters.

### 7. Activa las dependencias opcionales

Si quieres notificaciones push:

```bash
cd /var/www/html/<carpeta-publica>
composer require minishlink/web-push
```

Si luego activas Push en **Difusión**, Nammu generará las claves VAPID.

## Cron, automatización y backups

### Qué tareas automáticas necesita Nammu

La tarea principal es:

```bash
php /var/www/html/<carpeta-publica>/admin.php --run-scheduled
```

Esa tarea se encarga de:

- publicar contenidos programados,
- procesar colas pendientes,
- revisar RSS externas configuradas en **Redes**,
- refrescar actores seguidos en **Fediverso**,
- regenerar snapshots públicos dependientes del cron.

Además, Nammu incluye:

- backup diario de estadísticas: `core/backup-daily.php`
- backup completo semanal: `core/backup-weekly.php`

### Cómo editar el cron correctamente

La forma recomendada es editar el cron del usuario del servidor web:

```bash
sudo crontab -u www-data -e
```

Si usas ese comando, las líneas van **sin** la columna `www-data`.

### Bloque de cron recomendado

```bash
*/5 * * * * php /var/www/html/<carpeta-publica>/admin.php --run-scheduled >> /var/www/html/<carpeta-publica>/backups/cron.log 2>&1
15 3 * * * php /var/www/html/<carpeta-publica>/core/backup-daily.php --retention=7 >> /var/www/html/<carpeta-publica>/backups/backup.log 2>&1
30 3 * * 0 php /var/www/html/<carpeta-publica>/core/backup-daily.php --cleanup-only --retention=7 >> /var/www/html/<carpeta-publica>/backups/backup.log 2>&1
45 3 * * 0 php /var/www/html/<carpeta-publica>/core/backup-weekly.php --retention-weeks=8 >> /var/www/html/<carpeta-publica>/backups/backup-full.log 2>&1
```

Si en vez de eso editas `/etc/crontab` o usas `sudo crontab -e`, entonces sí debes añadir `www-data` delante del comando.

### Qué guarda cada backup

`core/backup-daily.php` guarda solo estadísticas:

- `config/analytics.json`
- `config/analytics.last-good.json`
- `config/gsc-cache.json`
- `config/bing-cache.json`
- `itinerarios/*/stats.json`

`core/backup-weekly.php` guarda una copia comprimida de:

- `content/`
- `assets/`
- `config/`
- `itinerarios/`

### Restauración rápida del backup diario de estadísticas

```bash
tar -xzf /var/www/html/<carpeta-publica>/backups/nammu-stats-backup-AAAA-MM-DD_HHMMSS.tar.gz -C /var/www/html/<carpeta-publica>
```

## Funcionalidades principales

### 1. Edición y publicación

- Panel `admin.php` con sesión propia y creación del primer usuario en el onboarding.
- Entradas, páginas, newsletters y podcasts en Markdown con front matter YAML.
- Soporte para `[toc]`, tablas, superíndices, bloques de código, callouts y TOC automático configurable.
- Incrustación automática de vídeos de YouTube y PeerTube pegando su URL, incluso en instancias PeerTube con dominio propio.
- Borradores, publicación directa, paso a borrador y previsualización.
- Publicaciones programadas con fecha y hora.
- Entradas relacionadas en posts y podcasts, incluyendo páginas de itinerario.
- Páginas privadas accesibles solo para el administrador logueado.

### 2. Podcast, newsletter y avisos

- Podcast con episodios en HTML, audio mp3, slug editable y feed `podcast.xml`.
- Páginas HTML por episodio y enlaces desde `/podcast`.
- Newsletter con archivo privado, vista web propia y control de envío.
- Sistema de avisos por email separado de la newsletter.
- Lista de suscriptores y libreta de direcciones postales en `config/`.

### 3. Biblioteca de recursos

- Subida múltiple de imágenes, vídeos y documentos.
- Renombrado seguro y validación de extensión.
- Editor web de imágenes con recorte y ajustes básicos.
- Variantes WebP automáticas para imágenes compatibles.
- Selector de recursos reutilizable en Publicar, Editar, Itinerarios y Redes.

### 4. Diseño, portada y navegación

- Configuración visual completa desde **Plantilla**.
- Tipografías Google Fonts, paleta de color, cabeceras, tipos de tarjeta y footer.
- Buscador configurable por posición y modo.
- Footer editable con HTML libre, logotipos y enlaces sociales.
- Botonera común en portada, posts y páginas sistémicas.

### 5. SEO, feeds y previsualización social

- `rss.xml`, `sitemap.xml`, `itinerarios.xml`, `podcast.xml`, `noticias.xml`.
- Tarjetas Open Graph y Twitter completas con imagen, título, descripción y `alt`.
- Canonical, datos estructurados e integración IndexNow.

### 6. Fediverso, ActivityPub y agregación

Nammu no se limita a generar feeds RSS: también convierte cada blog en un nodo propio del fediverso.

- El blog expone identidad ActivityPub completa con `WebFinger`, actor, clave pública, `outbox`, `followers`, `following` e `inbox`.
- Otros servidores compatibles, como Mastodon o Akkoma, pueden seguir el blog como una cuenta más usando un identificador tipo `@nombre@dominio`.
- Nammu firma las entregas salientes y puede repartir nuevas publicaciones a seguidores federados.
- El inbox recibe actividades remotas y procesa acciones básicas como `Follow`, `Undo`, respuestas y otras notificaciones.

#### Qué contenidos federa Nammu

- Entradas del blog.
- Itinerarios.
- Podcasts.
- Noticias agregadas desde RSS externas.
- Notas manuales creadas desde la pestaña **Redes** y publicadas en **Actualidad**.

#### Qué ves en el admin

- Módulo **Fediverso** para seguir actores públicos y leer su timeline.
- Pestañas de inicio, notificaciones, mensajes y configuración.
- Conversaciones públicas y privadas con actores remotos.
- Historial de favoritos, respuestas y reenvíos hechos desde Nammu.

#### Relación con Actualidad

- La página pública `actualidad.php` se construye a partir de RSS externas configuradas y de notas manuales.
- El feed `noticias.xml` publica esa misma selección para reutilizarla fuera del blog.
- Las notas y noticias de `Actualidad` también pueden entrar en la salida ActivityPub del sitio como contenido federable.

#### Automatización

- El cron integrado refresca periódicamente los actores seguidos desde **Fediverso**.
- Ese mismo cron reparte a seguidores federados las nuevas publicaciones locales que aún no se hayan entregado.
- `llms.txt` sigue disponible como archivo adicional para consumo por modelos de lenguaje.

### 7. Itinerarios y formación

- Itinerarios con portada propia, temas, imágenes y quizzes.
- Lógicas `free`, `sequential` y `assessment`.
- Seguimiento del progreso por cookies.
- Estadísticas por itinerario y reseteo desde el admin.

### 8. Estadísticas y RGPD

- Dashboard con usuarios únicos, páginas más vistas, búsquedas internas, orígenes y bots.
- Integración opcional con Google Search Console y Bing Webmaster Tools.
- Consentimiento de cookies obligatorio para usuarios humanos.
- Sin envío de datos de analítica a terceros.

### 9. Redes sociales y automatización

- Integración opcional con Telegram, Facebook Pages, Twitter/X, Bluesky, Instagram y LinkedIn.
- Auto-posting al publicar y envío manual desde la pestaña **Redes**.
- Twitter/X usa OAuth 1.0a de usuario: `Consumer Key`, `Consumer Secret`, `Access Token`, `Access Token Secret`.
- Envíos manuales con contador de caracteres y negritas básicas.
- Telegram, Facebook, Bluesky, Instagram y X aceptan imágenes en los envíos manuales.
- Telegram y X suben el archivo local de `assets/` cuando procede.
- Configuración de RSS externas para reenvío automático a redes.
- Generación automática de `actualidad.php` y `noticias.xml` desde esas fuentes.

### 10. Actualidad agregada

- `actualidad.php` compone una página pública a partir de las RSS configuradas en **Redes**.
- `noticias.xml` publica esa misma selección como feed agregada.
- Permite añadir notas manuales desde **Redes**, que se muestran como post-it y también pueden difundirse por ActivityPub.
- Se cachean imágenes sociales para acelerar la carga pública.
- Si una fuente no trae imagen, Nammu intenta recuperar la imagen social o la primera imagen útil del artículo.

### 11. Compatibilidad y migración

- Compatible con la estructura `content/` y `assets/` de PicoCMS.
- Parser propio robusto y uso opcional de Symfony Yaml si está disponible.
- Integración con Nisaba y sugerencias editoriales desde el botón **Ideas**.

## Actualización

### Desde Git

```bash
cd /var/www/html/<carpeta-publica>
git pull origin main
sudo chown -R <tu-usuario>:www-data .
sudo find . -type d -exec chmod 2775 {} \;
sudo find . -type f -exec chmod 664 {} \;
```

## Migración desde PicoCMS

1. Copia `content/` y `assets/` dentro de Nammu.
2. Revisa el front matter y usa claves como `Title`, `Template`, `Date`, `Category`, `Image`, `Description`, `Status`, `Ordo`.
3. Ajusta **Configuración** y **Plantilla**.
4. Carga la portada una vez para regenerar feeds y sitemap.

## Administración diaria

### Panel de control

- **Publicar**: formularios para entradas/páginas con fecha amigable, selección de tipo, slug, imagen destacada y modales para insertar medios.
- **Editar**: tabla con paginación, buscador, estados, acciones rápidas (editar, borrar) y botones para disparar envíos sociales.
- **Recursos**: biblioteca con tarjetas, filtros por imagen/vídeo/documento, botón “Editar” para abrir el mini editor, subida múltiple con feedback, etiquetado de imágenes, audio, vídeo y documentos (pdf, docx, xlsx, pptx, odt, md, etc., con nube de tags) y buscador instantáneo por nombre o etiquetas.
- **Plantilla**: controla tipografías, colores, cabeceras, comportamiento del buscador (posición y modo flotante), TOC por defecto y número de entradas por home.
- **Itinerarios**: crea portadas, define clase (Libro, Curso, Colección, Otros), lógica de uso, quizzes y estadísticas. Cada tema puede añadirse, duplicarse o borrarse desde la misma pestaña.
- **Configuración**: modo blog/diccionario, búsqueda avanzada, nombre del sitio, autor, redes sociales, API de Google Fonts, correo de lista (Gmail + OAuth) y cambio de contraseña.
- **Difusión**: credenciales y guías rápidas por red, usuario público de X para footer y `twitter:site`, App ID de Facebook, tokens de Instagram y LinkedIn y opciones de autoenvío.
- **Redes**: envío manual de mensajes a varias redes a la vez y configuración de RSS externas para reenvío automático de novedades.
- **Actualidad**: página pública agregada desde las fuentes RSS configuradas en **Redes**, con notas manuales, versión RSS propia en `noticias.xml` e integración con el fediverso.
- **Fediverso**: timeline remoto, notificaciones, mensajes y gestión de seguidores/seguidos para la cuenta ActivityPub del blog.

El modal “Insertar recurso” que aparece en Publicar, Editar e Itinerarios comparte el mismo buscador, así que puedes localizar imágenes etiquetadas sin salir del formulario.

Ejemplo rápido:
- **Avisos**: publica una entrada/itinerario y, si están activados, se envía un aviso con título + descripción + enlace.
- **Newsletter**: usa “Enviar como newsletter” desde Publicar o Editar; se envía el contenido completo y no se publica en el blog.

### Lista de correo con Gmail (OAuth2)



1. Ve a **Google Cloud Console** con la cuenta de gmail que quieras usar  `https://console.cloud.google.com/ `
   1. En la barra superior, haz clic en el selector de proyectos y selecciona **Proyecto nuevo**.
   2. Ponle un nombre (ej. `Lista de Correo Nanmmu`) y haz clic en **Crear**.
   3. Asegúrate de que el nuevo proyecto esté seleccionado en la barra superior.
   4. En el menú lateral (hamburguesa ☰), ve a **APIs y servicios > Biblioteca**.
   5. Busca **"Gmail API"**, selecciónala y haz clic en **Habilitar**.
   6. Luego, en  el proyecto, nos saldrá un mensaje para configurar OAUTH. Pinchamos. Rellenamos campos eligiendo Público> Interno. 
   7. En **Credenciales**  > **Crear Credenciales** > **ID de cliente de OAuth**, rellena campos, añade como URI autorizada la de tu blog y añade como URI de redirección autorizada: `https://tu-dominio/admin.php?page=lista-correo&gmail_callback=1` (ajusta protocolo/host). 
   8. **Consejo**: Google Cloud Console es muy poco usable y tiene una gran variabilidad, utiliza alguna IA si te atascas en el proceso descrito.
2. En **Configuración de Nammu**, introduce:
   - Dirección Gmail que se usará para enviar.
   - Client ID y Client Secret de una credencial OAuth 2.0 (tipo “Aplicación web”) creada en Google Cloud Console.
3. Guarda y ve a la pestaña **Lista**. Pulsa “Conectar con Google” para abrir el consentimiento y obtener el `refresh_token`. El estado cambiará a “Conectado”.
4. Desde **Lista** podrás añadir/eliminar correos y activar avisos y newsletters; los lectores pueden elegir sus preferencias de envío.
5. La lista vive en `config/mailing-subscribers.json` y los tokens en `config/mailing-tokens.json`.
6. Si cambias de cuenta o credenciales, desconecta y vuelve a conectar para regenerar tokens.

### Recursos avanzados

- Botón “Recalcular Ordo” para reordenar automáticamente el campo `Ordo` según la fecha de publicación.
- Restablecimiento de estadísticas de itinerario desde el modal correspondiente.
- Alertas discretas tras cada acción (`$_SESSION['asset_feedback']`, `$_SESSION['itinerary_feedback']`, etc.).

## Itinerarios en profundidad

### Crear y editar

1. En **Itinerarios**, pulsa “Nuevo itinerario”.
2. Completa título, descripción, imagen, contenido y, si quieres, autoevaluación de presentación (JSON generado desde el modal).
3. Elige la lógica (`free`, `sequential`, `assessment`) y guarda.
4. Añade temas: cada uno tiene número, título, descripción, imagen, contenido Markdown y quiz opcional.

### Progreso y estadísticas

- El navegador marca los temas visitados/aprobados y desbloquea el siguiente automáticamente.
- El botón “Comenzar itinerario” respeta el último progreso, mostrando sólo los temas permitidos.
- El modal de estadísticas muestra lectores de la presentación, iniciados (quienes superaron el tema 1) y porcentaje por tema respecto a esos iniciados.
- Puedes pulsar **Poner estadísticas a cero** para limpiar `stats.json` del itinerario.

## SEO, feeds y archivos auxiliares

- `rss.xml`: feed RSS 2.0 con títulos, descripciones higienizadas, enlaces absolutos e imágenes destacadas.
- `sitemap.xml`: inventario de entradas, páginas e itinerarios para buscadores.
- `itinerarios.xml`: feed específico de cursos/libros para reutilizar el contenido en otras plataformas.
- `podcast.xml`: feed RSS de podcast con metadatos iTunes, duración y archivo mp3.
- `llms.txt`: resumen y enlaces clave para facilitar el consumo por modelos de lenguaje.

## Licencia y soporte

Nammu está licenciado bajo **EUPL**. Puedes usarlo en proyectos comerciales u open source siempre que mantengas la atribución y compartas las modificaciones bajo la misma licencia cuando corresponda.

¿Dudas, bugs o ideas? Abre un issue en el repositorio o contacta con quienes mantienen el proyecto.

---

**Nammu** convierte la edición diaria en un sprint veloz y medible. Publica, enseña, analiza y comparte desde un mismo panel, sin sacrificar velocidad ni control.
