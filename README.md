![Nammu Logo](nammu-banner.png)

# Nammu — Motor de Blog y Diccionario compatible con PicoCMS

Nammu es un motor ligero para blogs y diccionarios online que reutiliza la estructura de contenidos de **PicoCMS** y añade herramientas de edición, plantillas personalizables, generación de RSS y administración de recursos. El proyecto se distribuye bajo licencia **EUPL** y puede ejecutarse en cualquier alojamiento que soporte PHP 8+.

## Características principales

- Panel de administración (`admin.php`) para crear y editar entradas/páginas en Markdown.
- Gestión directa de recursos multimedia (carpeta `assets/`), con recorte básico, control de intensidad de color, brillo y contraste y filtro de pixelado.
- Plantillas configurables desde la administración: tipografías Google Fonts, colores, cabeceras, maquetación de portada, footer, meta etiquetas sociales y logotipo flotante.
- Generación automática de `rss.xml`  (RSS 2.0) con imágenes destacadas, limpieza de etiquetas `[toc]/[TOC]` y reemplazo de vídeos/PDF incrustados por enlaces absolutos para máxima compatibilidad con lectores.
- Generación automática de `sitemap.xml` para facilitar la indexación por Google y otros buscadores.
- Parseador Markdown extensible con soporte para listas, encabezados, enlaces, imágenes, código en línea, bloques de cita y bloques de código.
- Buscador avanzado (`buscar.php`) con soporte para frases exactas, exclusiones con `-palabra`, filtros por campos (`title:`, `category:`, `content:`), selector de tipo (entradas/páginas) e integración visual configurable desde la plantilla.
- Botón extra en las cajas de búsqueda (portada, categorías, post individual, etc.) que enlaza al índice alfabético cuando se usa la ordenación por título (modo diccionario).
- Índice de categorías (`/categorias`) y páginas por categoría (`/categoria/{slug}`) que reutilizan la maquetación elegida para la portada e incluyen miniaturas automáticas basadas en la última entrada de cada categoría.
- Selector de recursos multimedia compatible con imágenes, vídeos (MP4, WebM, MOV…) y documentos PDF. Al insertar desde el modal, los vídeos se convierten en bloques `<video>` centrados, los PDF se incrustan como `<iframe>` a ancho completo y las URLs directas de YouTube se transforman automáticamente en iframes responsivos.
- Integración opcional con Telegram, WhatsApp Cloud API, Facebook Pages y Twitter/X para enviarlas automáticamente al publicar (también se puede reenviar manualmente desde la tabla de “Editar”).
- Índice alfabético: al elegir la ordenación alfabética en Configuración, la portada agrupa las entradas por letra (A, B, C…, “Otros”), se activa el índice de letras (`/letras`) y las vistas por letra (`/letra/{slug}`) que reutilizan la maquetación elegida.
- Parseador Markdown con extras: soporta código en bloque (```` ``` ````), blockquotes, superíndices con `^texto`, tachado con `~~`, negrita/cursiva combinadas y genera automáticamente una **tabla de contenidos** cuando encuentra `[toc]` o `[TOC]`, enlazando con los títulos `h1–h4`.
- Bloque “Entrada” dentro de la pestaña Plantilla para decidir si las entradas muestran un índice de contenidos por defecto (a partir de 2, 3 o 4 encabezados). El sistema añade el TOC en entradas publicadas y borradores, pero respeta las etiquetas `[toc]/[TOC]` si ya las incluiste manualmente.
- TOC automático opcional: si activas el bloque anterior, cada post mostrará el índice al inicio del contenido siempre que alcance el mínimo de encabezados definido; las páginas quedan excluidas por diseño y puedes desactivar la función post a post insertando tu propio `[toc]`.
- Tratamiento diferenciado de entradas y páginas: las páginas usan la misma ruta amigable `/slug`, pero muestran cintillas de actualización específicas (con fecha calculada a partir del YAML o, si falta, de los metadatos del archivo) y pueden compartir maquetación con las entradas según la plantilla elegida.
- Modo borradores: desde “Publicar” puedes guardar contenidos como borrador; la pestaña “Editar” incorpora un filtro “Entradas | Páginas | Borradores” y las entradas en borrador quedan fuera de RSS, sitemap, índices, búsqueda y portada. Si accedes por URL directa, el post muestra un sello rojo “Borrador” en la vista individual.
- **Itinerarios temáticos**: agrupa múltiples archivos Markdown bajo `/itinerarios/{slug}` con portada, índice visual, contenidos renderizados como entradas y compatibilidad con recursos (vídeos, PDF, etc.). Incluye formulario completo en la administración, botón “Comenzar itinerario”, pases por temas, y genera un feed específico `itinerarios.xml` además de añadir un acceso directo en todas las cajas de búsqueda del sitio.
- Compatible con la estructura de directorios de PicoCMS (`content/` y `assets/`), lo que simplifica migraciones.

## Itinerarios: guía rápida

Los itinerarios añaden una capa narrativa encima de los posts tradicionales para organizar contenidos extensos o cursos modulares. Funcionan como “colecciones” dentro del motor y heredan el look & feel definido en la plantilla.

### Estructura y almacenamiento

- Cada itinerario vive en `itinerarios/{slug}/index.md`. El front matter del `index.md` admite al menos `Title`, `Description`, `Image`, `Date/Updated` y cualquier campo personalizado que necesites.
- Los temas se guardan como ficheros Markdown hermanos (`itinerarios/{slug}/{tema}.md`). El YAML de cada tema debe incluir `Title`, `Description`, `Number`, `Image` opcional y se completará con el contenido Markdown de la presentación del tema.
- Los slugs se normalizan automáticamente (permitiendo `-`) tanto para itinerarios como para temas. Internamente se usa `ItineraryRepository` para cargar/guardar las entradas y se reordenan los números de tema al insertar en posiciones intermedias.

### Creación y edición en el panel

- La pestaña **Itinerarios** del admin ofrece:
  - Tabla con todos los itinerarios (título, descripción, nº de temas, slug público) con acciones **Editar** y **Borrar**.
  - Formulario “Nuevo/Editar itinerario” en una sola columna: título, descripción corta, imagen de portada, slug editable, área Markdown con los mismos atajos que los posts y botón para insertar recursos.
  - Bloque “Temas del itinerario” con botón “Nuevo tema” y tarjetas individuales que incluyen los botones **Editar**/**Borrar** más un enlace “Ver tema”.
  - Formulario de tema con título, descripción, número (permite reorganizar arrastrando posiciones al guardar), slug, imagen y editor Markdown. También dispone de botón “Nuevo recurso” y un placeholder “Añadir test” (la lógica de tests se activará más adelante).
- Los itinerarios se almacenan en disco inmediatamente; al borrar un itinerario se elimina la carpeta completa y todos sus temas. Al borrar un tema sólo se borra su fichero `.md`.

### Experiencia de navegación

- `/itinerarios`: listado maquetado como una página de categoría (respeta columnas, estilo de tarjetas y colores definidos en Plantilla). Muestra hero con el nombre del propietario del blog y tarjetas con imagen, título y descripción.
- `/itinerarios/{slug}`: renderiza el `index.md` como si fuese un post (sin etiqueta de categoría) y añade un índice visual de temas (tarjetas en rejilla con cintilla “Tema N”, imagen recortada, descripción y enlace). Al final aparece el botón **Comenzar Itinerario** que lleva al primer tema.
- `/itinerarios/{slug}/{tema}`: cada tema se muestra con la maquetación de post individual (imagen, descripción, recursos incrustados, etc.) seguida de una caja de llamada a la acción que permite avanzar al siguiente tema, volver al índice o (próximamente) superar un test. Cuando no hay test se ofrece un botón “Pasar al siguiente tema”.
- Los posts virtuales creados para cada itinerario/tema actualizan los metadatos sociales (Open Graph / Twitter), añaden cintillas informativas (“Itinerario”, “Tema N del itinerario «…»”) y eliminan el buscador incrustado para evitar duplicados en esta vista.

### Búsqueda, RSS y otros extras

- Todas las cajas de búsqueda (portada, posts, categorías, índice alfabético y buscador avanzado) muestran un tercer botón con icono educativo que enlaza a `/itinerarios` siempre que exista al menos un itinerario.
- Además del `rss.xml` general, Nammu genera `itinerarios.xml` con los itinerarios ordenados por fecha (metadatos `Date`/`Updated` o `filemtime`). El feed incluye portada, descripción y el contenido completo del `index.md` en `content:encoded`.
- Los PDFs, vídeos y recursos insertados desde el editor se procesan igual que en los posts estándar, garantizando rutas absolutas (`/assets/...`) y estilos adaptados (iframes a ancho completo, vídeo responsivo, etc.).

## Requisitos

- PHP 8.0 o superior con extensiones estándar (no requiere Composer en producción).
- Servidor web (Apache, Nginx, Caddy…) con permisos de escritura en `content/`, `assets/` y `config/`.
- Acceso SSH/SFTP para aplicar permisos y desplegar actualizaciones.

## Instalación limpia

1. Clona o descarga el repositorio:
   ```bash
   git clone https://github.com/<tu-organizacion>/nammu.git memoria
   ```
   Alternativamente si quieres instalarlo directamente en en una carpeta pública determinada:
   
    ```bash
    git init
    git config --global --add safe.directory /var/www/html/<carpeta-publica-de-tu-sitio>
    git remote add origin https://github.com/ciamaximalista/nammu.git
    git pull origin main
    ```
2. Coloca los archivos en el directorio público en la carpeta que tengas asociada a tu dominio (`/var/www/html/<carpeta-publica-de-tu-sitio>`).
3. Crea las carpetas que Nammu necesita escribir y ajusta permisos (repite para cada instancia que tengas en `blogs/`):
   ```bash
   sudo mkdir -p /var/www/html/<carpeta-publica-de-tu-sitio>/{config,content,assets,itinerarios}
   sudo chown -R www-data:www-data /var/www/html/<carpeta-publica-de-tu-sitio>
   sudo find /var/www/html/<carpeta-publica-de-tu-sitio> -type d -exec chmod 755 {} \;
   sudo find /var/www/html/<carpeta-publica-de-tu-sitio> -type f -exec chmod 644 {} \;
   sudo find /var/www/html/<carpeta-publica-de-tu-sitio>/{config,content,assets,itinerarios} -type d -exec chmod 775 {} \;
   sudo find /var/www/html/<carpeta-publica-de-tu-sitio>/{config,content,assets,itinerarios} -type f -exec chmod 664 {} \;
   sudo chmod 664 /var/www/html/<carpeta-publica-de-tu-sitio>/config/config.yml
   ```
   Ajusta el usuario/grupo (`www-data`) según tu servidor.
4. Configura el host virtual y asegúrate de que `AllowOverride All` esté habilitado si deseas utilizar el `.htaccess`.
5. Visita `https://tu-dominio/admin.php`, crea el usuario inicial y empieza a publicar.
6. Si vas a subir vídeos medianos o grandes, recuerda aumentar `upload_max_filesize` y `post_max_size` en tu `php.ini` (o `.user.ini`) a un valor superior al del archivo más pesado que quieras aceptar y reinicia PHP/FPM.

## Migración desde PicoCMS

Nammu está pensado para reemplazar una instalación de PicoCMS reutilizando los mismos contenidos:

1. **Haz copia de seguridad** de tu sitio actual.
2. Dentro del directorio de PicoCMS, **elimina todo** excepto las carpetas `content/` y `assets/`.

Ahora ya está todo listo para descargar y poner permisos:

    git init
    git config --global --add safe.directory /var/www/html/<carpeta-publica-de-tu-sitio>
    git remote add origin https://github.com/ciamaximalista/nammu.git
    git pull origin main
    
    sudo chown -R <tu-usuario>:www-data /var/www/html/<carpeta-publica-de-tu-sitio>
    sudo find /var/www/html/<carpeta-publica-de-tu-sitio> -type d -exec chmod 755 {} \;
    sudo find /var/www/html/<carpeta-publica-de-tu-sitio> -type f -exec chmod 644 {} \;
    sudo chmod 775 /var/www/html/<carpeta-publica-de-tu-sitio>/config
    sudo chmod 775 /var/www/html/<carpeta-publica-de-tu-sitio>/content /var/www/html/<carpeta-publica-de-tu-sitio>/assets
    ```

> Consejo: si usabas plugins de PicoCMS, evalúa si aún los necesitas. El núcleo de Nammu ya integra RSS, cabecera personalizable y otras funciones habituales. Pronto añadiremos más.

## Administración y permisos

- `admin.php` exige autenticación. El primer acceso genera un usuario administrador.
- `content/` y `assets/` deben ser **escribibles** por el proceso web para guardar posts e imágenes.
- `config/config.yml` almacena la configuración general y se actualiza desde el panel (plantilla, redes sociales, etc.). Si la edición falla, revisa que el archivo tenga permisos 664 y pertenezca al usuario/grupo del servidor.
- En la pestaña **Editar** ahora tienes un buscador interno minimalista que filtra por título, descripción, categoría o nombre de archivo. El filtro se mantiene al cambiar entre “Entradas” y “Páginas”, lo que facilita localizar rápidamente el contenido que quieres modificar o borrar.
- Los borradores se gestionan desde la misma pestaña: el nuevo botón “Guardar como borrador” en **Publicar** crea contenidos que no se publican hasta que los promociones con “Publicar como entrada” o “Publicar como página” dentro del formulario de edición.
- El modal de recursos permite insertar vídeos (locales o de YouTube) y documentos PDF en el cuerpo del post; los vídeos locales se incrustan como `<video>` HTML5, las URLs de YouTube pegadas en una línea se transforman automáticamente en un iframe responsive y los PDF se muestran como un `<iframe>` a ancho completo.
- Cada entrada puede anunciarse en Telegram, WhatsApp Cloud, Facebook Pages o Twitter/X si configuras los tokens desde **Configuración → Redes sociales**. Puedes activar el envío automático o dispararlo manualmente con el botón “Enviar” de la tabla de “Editar”.

## Orden alfabético, índices y buscador

- En **Configuración → Ordenar posts por** puedes elegir *“Orden alfabético (A-Z)”*. Cuando está activo:
  - La portada agrupa automáticamente las entradas por letra inicial mostrando bloques `A`, `B`, `C`, … (también un bloque “Otros” para títulos que no empiezan por letra).
  - Se habilita un botón adicional en todas las cajas de búsqueda que enlaza al nuevo índice alfabético.
  - Puedes acceder al listado completo de letras en `/letras` y a las páginas individuales `/letra/{slug}` (por ejemplo `/letra/a` o `/letra/otros`). Ambas reutilizan la maquetación seleccionada en Plantilla (columnas, tarjetas, etc.).
- Si vuelves a la ordenación cronológica, la portada y las rutas alfabéticas regresan a su comportamiento habitual y el botón del buscador desaparece automáticamente.

## Actualizaciones

Cuando apliques nuevas versiones desde GitHub, asegúrate de que los permisos no impidan la escritura en el repositorio. Si delegaste la propiedad en el usuario del servidor web, puedes recuperar el control con tu usuario SSH:

```bash
sudo chown -R <tu-usuario>:www-data /var/www/html/blogs/memoria
find /var/www/html/<carpeta-publica-de-tu-sitio> -type d -exec chmod 775 {} \;
find /var/www/html/<carpeta-publica-de-tu-sitio> -type f -exec chmod 664 {} \;

sudo chown -R www-data:www-data /var/www/html/<carpeta-publica-de-tu-sitio>/{content,assets,config}
sudo chmod 775 /var/www/html/<carpeta-publica-de-tu-sitio>{content,assets,config}
```

Reemplaza <tu-usuario> por tu usuario SSH. Con esta secuencia podrás ejecutar `git pull` normalmente y mantener el servidor con permisos de escritura en las rutas necesarias.

## Licencia

Este proyecto se distribuye bajo **European Union Public Licence (EUPL)**. Consulta el texto completo de la licencia para conocer los términos de uso, distribución y modificación.

---

¿Preguntas o incidencias? Abre un _issue_ en GitHub o contacta con el equipo de desarrollo. ¡Bienvenido a Nammu! 
