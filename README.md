![Nammu Logo](nammu-banner.png)

# Nammu — Motor de Blog y Diccionario compatible con PicoCMS

Nammu es un motor ligero para blogs y diccionarios online que reutiliza la estructura de contenidos de **PicoCMS** y añade herramientas de edición, plantillas personalizables, generación de RSS y administración de recursos. El proyecto se distribuye bajo licencia **EUPL** y puede ejecutarse en cualquier alojamiento que soporte PHP 8+.

## Características principales

- Panel de administración (`admin.php`) para crear y editar entradas/páginas en Markdown.
- Gestión directa de recursos multimedia (carpeta `assets/`), con recorte básico, control de intensidad de color, brillo y contraste y filtro de pixelado.
- Plantillas configurables desde la administración: tipografías Google Fonts, colores, cabeceras, maquetación de portada, footer, meta etiquetas sociales y logotipo flotante.
- Generación automática de `rss.xml`  (RSS 2.0) con imágenes destacadas y compatibilidad con URLs amigables.
- Generación automática de `sitemap.xml` para facilitar la indexación por Google y otros buscadores.
- Parseador Markdown extensible con soporte para listas, encabezados, enlaces, imágenes, código en línea, bloques de cita y bloques de código.
- Buscador avanzado (`buscar.php`) con soporte para frases exactas, exclusiones con `-palabra`, filtros por campos (`title:`, `category:`, `content:`), selector de tipo (entradas/páginas) e integración visual configurable desde la plantilla.
- Botón extra en las cajas de búsqueda (portada, categorías, post individual, etc.) que enlaza al índice alfabético cuando se usa la ordenación por título (modo diccionario).
- Índice de categorías (`/categorias`) y páginas por categoría (`/categoria/{slug}`) que reutilizan la maquetación elegida para la portada e incluyen miniaturas automáticas basadas en la última entrada de cada categoría.
- Índice alfabético: al elegir la ordenación alfabética en Configuración, la portada agrupa las entradas por letra (A, B, C…, “Otros”), se activa el índice de letras (`/letras`) y las vistas por letra (`/letra/{slug}`) que reutilizan la maquetación elegida.
- Parseador Markdown con extras: soporta código en bloque (```` ``` ````), blockquotes, superíndices con `^texto`, tachado con `~~`, negrita/cursiva combinadas y genera automáticamente una **tabla de contenidos** cuando encuentra `[toc]` o `[TOC]`, enlazando con los títulos `h1–h4`.
- Tratamiento diferenciado de entradas y páginas: las páginas usan la misma ruta amigable `/slug`, pero muestran cintillas de actualización específicas (con fecha calculada a partir del YAML o, si falta, de los metadatos del archivo) y pueden compartir maquetación con las entradas según la plantilla elegida.
- Modo borradores: desde “Publicar” puedes guardar contenidos como borrador; la pestaña “Editar” incorpora un filtro “Entradas | Páginas | Borradores” y las entradas en borrador quedan fuera de RSS, sitemap, índices, búsqueda y portada. Si accedes por URL directa, el post muestra un sello rojo “Borrador” en la vista individual.
- Compatible con la estructura de directorios de PicoCMS (`content/` y `assets/`), lo que simplifica migraciones.

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
3. Asegura los permisos:
   ```bash
   sudo chown -R www-data:www-data /var/www/html/<carpeta-publica-de-tu-sitio>
   sudo find /var/www/html/<carpeta-publica-de-tu-sitio> -type d -exec chmod 755 {} \;
   sudo find /var/www/html/<carpeta-publica-de-tu-sitio> -type f -exec chmod 644 {} \;
   sudo chmod 775 /var/www/html/<carpeta-publica-de-tu-sitio>/config
   sudo chmod 775 /var/www/html/<carpeta-publica-de-tu-sitio>/content /var/www/html/<carpeta-publica-de-tu-sitio>/assets
   ```
   Ajusta el usuario/grupo (`www-data`) según tu servidor.
4. Configura el host virtual y asegúrate de que `AllowOverride All` esté habilitado si deseas utilizar el `.htaccess`.
5. Visita `https://tu-dominio/admin.php`, crea el usuario inicial y empieza a publicar.

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
