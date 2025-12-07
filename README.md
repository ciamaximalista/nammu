![Nammu Logo](nammu-banner.png)

# Nammu — CMS ligero para periodistas, editoriales y escuelas

Nammu condensa todo lo que esperas de un gestor contemporáneo —edición Markdown, plantillas vivas, itinerarios interactivos y automatización social— en un paquete PHP 8 sin dependencias caprichosas. Es ligero, portable y lo bastante flexible como para lanzar un blog personal, un diccionario multimedia o un curso con estadísticas por tema.

La plataforma se distribuye bajo licencia **EUPL** y corre en cualquier hosting que ofrezca PHP 8+ y escritura en disco.

## Qué puedes construir con Nammu

### Publicaciones en Markdown con control editorial

- Panel `admin.php` con sesión propia y creación del primer usuario en el onboarding.
- Entradas y páginas Markdown con front matter YAML, soporte para `[toc]`, tablas, superíndices, bloques de código y TOC automático configurable por plantilla.
- Sistema de borradores con filtros “Entradas / Páginas / Borradores”, publicación directa desde el formulario y sello rojo en la vista pública cuando está sin publicar.
- Buscador interno dentro de la pestaña “Editar” que filtra por título, descripción, categoría o archivo para localizar contenidos en segundos.

### Biblioteca multimedia integrada

- Subida múltiple de imágenes, vídeos (MP4, WebM, MOV), GIF y PDF con renombrado seguro (`nammu_unique_asset_filename`) y validación de extensión.
- Editor web con recorte, brillo, contraste, saturación y pixelado antes de sobrescribir el archivo en `assets/`.
- Selector de medios reutilizable que inserta imágenes, vídeos locales (`<video>`), PDF (`<iframe>`) o enlaces de YouTube convertidos a iframes responsive.

### Plantillas vivas y SEO al día

- Página “Plantilla” para elegir tipografías Google Fonts, colores, estilo de tarjetas, cabeceras de portada (texto, gráfico, mixtas) y comportamiento del TOC.
- Footer editable con HTML libre, logotipos posicionables (arriba, abajo o ninguno) y radio global de esquinas.
- `rss.xml` y `sitemap.xml` regenerados automáticamente desde `index.php`, con limpieza de `[toc]`, conversión de embeds a enlaces y `<enclosure>` para imágenes.
- Botones contextuales en todas las cajas de búsqueda: índice de categorías, índice alfabético (modo diccionario) y, si hay cursos, acceso directo a `/itinerarios` con un icono de libro.

### Buscador avanzado e índices

- Buscador público (`buscar.php`) con `"-frase exacta"`, exclusiones `-palabra`, filtros `title:`, `category:`, `content:` y selector Entradas/Páginas.
- Índice de categorías (`/categorias`) y vistas por categoría o letra (`/categoria/{slug}`, `/letra/{slug}`) que heredan la maquetación elegida.
- Modo diccionario: agrupa la portada por letras, muestra `/letras`, añade accesos directos por letra y mantiene la navegación cuando vuelves a modo blog.

### Itinerarios, cursos y libros interactivos

- Cada itinerario vive en `/itinerarios/{slug}` con portada Markdown, imagen, descripción y autoevaluación opcional.
- Temas independientes con imagen, descripción, contenido y quiz propio. Tres lógicas de uso: libre, secuencial y assessment (exige aprobar).
- Progreso almacenado en cookies: desbloquea automáticamente los temas ya superados y el siguiente en línea, mostrando un aviso cuando todavía no puedes avanzar.
- Estadísticas por itinerario: lectores de la presentación, usuarios que completaron el tema 1 (iniciados) y porcentaje por tema. Incluye botón **Poner estadísticas a cero** para reiniciar los contadores.
- Feed dedicado `itinerarios.xml` y botones en los buscadores que llevan directo al índice de cursos.

### Automatización y redes sociales

- Integración opcional con Telegram, WhatsApp Cloud API, Facebook Pages y Twitter/X: auto-posting al publicar o envío manual desde la tabla de “Editar”.
- Plantillas de mensaje consistentes (título, descripción y URL pública) con escape apropiado para HTML o texto plano.
- Feedback inmediato en la UI usando `$_SESSION['social_feedback']`.

### Recursos técnicos clave

- Compatible con la estructura `content/` y `assets/` de PicoCMS, lo que simplifica migraciones.
- Si está disponible Symfony Yaml, se usa para parsear `config/config.yml`; si no, existe un parser propio robusto.
- Markdown enriquecido en el panel gracias a una barra de botones que aplica negrita, listas, enlaces, código, citas y atajos para imágenes/vídeos. Incluye atajos de teclado (Ctrl/Cmd+B, Ctrl/Cmd+I, Ctrl/Cmd+K) en el editor de contenidos.

## Requisitos mínimos

- PHP 8.0 o superior con extensiones estándar (`json`, `mbstring`, `iconv`, `curl` recomendado).
- Servidor web capaz de ejecutar PHP y escribir en `content/`, `assets/` e `itinerarios/`.
- Composer opcional para aprovechar Symfony Yaml (el núcleo funciona sin él).

## Instalación

### Paso a paso

1. Clona el repositorio o descarga el ZIP y descomprímelo en tu raíz web (ej. `/var/www/html/blogs/memoria`).
2. Crea los directorios `config/`, `content/`, `assets/` e `itinerarios/` si no existen.
3. Ajusta los permisos (ver siguiente bloque).
4. Configura tu host virtual apuntando a la carpeta pública y habilita PHP 8.
5. Accede a `https://tusitio/admin.php`, crea el usuario inicial y empieza a publicar.

### Permisos recomendados

```bash
sudo chown -R <tu-usuario>:www-data /var/www/html/<carpeta-publica>
sudo find /var/www/html/<carpeta-publica> -type d -exec chmod 2775 {} \;
sudo find /var/www/html/<carpeta-publica> -type f -exec chmod 664 {} \;
```

Sustituye `<tu-usuario>` y `<carpeta-publica>` según tu entorno. Usa el grupo del proceso web (www-data, apache, nginx…).

Si quieres fijar la URL pública (por ejemplo, sin `www`), añade en `config/config.yml`:
```yaml
site_url: "https://tusitio.com"
```
De lo contrario, Nammu usará automáticamente el host de la petición para generar `rss.xml`, `itinerarios.xml` y enlaces absolutos.

## Actualización

### Desde Git

```bash
cd /var/www/html/<carpeta-publica>
git pull origin main
sudo chown -R <tu-usuario>:www-data .
sudo find . -type d -exec chmod 2775 {} \;
sudo find . -type f -exec chmod 664 {} \;
```

Si cediste propiedad al usuario del servidor para que pueda escribir, ejecuta `sudo chown -R <tu-usuario>:www-data .` antes de `git pull` y deshaz el cambio después.

### Migración desde PicoCMS

1. Copia tus carpetas `content/` y `assets/` dentro de la instalación de Nammu.
2. Revisa el front matter y asegúrate de usar las claves que Nammu reconoce (`Title`, `Template`, `Date`, `Category`, `Image`, `Description`, `Status`, `Ordo`).
3. Ve a **Configuración** y define el modo (blog/diccionario), nombre del sitio, autor y redes sociales.
4. Replica tus estilos en **Plantilla** para ajustar fuentes, colores y portadas.
5. Regenera `rss.xml`, `sitemap.xml` e `itinerarios.xml` cargando la portada; el sistema los crea automáticamente.

## Administración diaria

### Panel de control

- **Publicar**: formularios para entradas/páginas con fecha amigable, selección de tipo, slug, imagen destacada y modales para insertar medios.
- **Editar**: tabla con paginación, buscador, estados, acciones rápidas (editar, borrar) y botones para disparar envíos sociales.
- **Recursos**: biblioteca con tarjetas, filtros por imagen/vídeo/documento, botón “Editar” para abrir el mini editor, subida múltiple con feedback, etiquetado de imágenes, audio, vídeo y documentos (pdf, docx, xlsx, pptx, odt, md, etc., con nube de tags) y buscador instantáneo por nombre o etiquetas.
- **Plantilla**: controla tipografías, colores, cabeceras, comportamiento del buscador (posición y modo flotante), TOC por defecto y número de entradas por home.
- **Itinerarios**: crea portadas, define clase (Libro, Curso, Colección, Otros), lógica de uso, quizzes y estadísticas. Cada tema puede añadirse, duplicarse o borrarse desde la misma pestaña.
- **Configuración**: modo blog/diccionario, búsqueda avanzada, nombre del sitio, autor, redes sociales, API de Google Fonts y cambio de contraseña.

El modal “Insertar recurso” que aparece en Publicar, Editar e Itinerarios comparte el mismo buscador, así que puedes localizar imágenes etiquetadas sin salir del formulario.

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

## Licencia y soporte

Nammu está licenciado bajo **EUPL**. Puedes usarlo en proyectos comerciales u open source siempre que mantengas la atribución y compartas las modificaciones bajo la misma licencia cuando corresponda.

¿Dudas, bugs o ideas? Abre un issue en el repositorio o contacta con quienes mantienen el proyecto.

---

**Nammu** convierte la edición diaria en un sprint veloz y medible. Publica, enseña, analiza y comparte desde un mismo panel, sin sacrificar velocidad ni control.
