![Nammu Logo](nammu-banner.png)

# Nammu — Motor de Blog compatible con PicoCMS

Nammu es un motor ligero para blogs que reutiliza la estructura de contenidos de **PicoCMS** y añade herramientas de edición, plantillas personalizables, generación de RSS y administración de recursos. El proyecto se distribuye bajo licencia **EUPL** y puede ejecutarse en cualquier alojamiento que soporte PHP 8+.

## Características principales

- Panel de administración (`admin.php`) para crear y editar entradas/páginas en Markdown.
- Gestión directa de recursos multimedia (carpeta `assets/`), con recorte básico, control de intensidad de color, brillo y contraste y filtro de pixelado.
- Plantillas configurables desde la administración: tipografías Google Fonts, colores, cabeceras, maquetación de portada, footer, meta etiquetas sociales y logotipo flotante.
- Generación automática de `rss.xml`  (RSS 2.0) con imágenes destacadas y compatibilidad con URLs amigables.
- Parseador Markdown extensible con soporte para listas, encabezados, enlaces, imágenes, código en línea, bloques de cita y bloques de código.
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

    ```bash
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

## Actualizaciones

Cuando apliques nuevas versiones desde GitHub, asegúrate de que los permisos no impidan la escritura en el repositorio. Si delegaste la propiedad en el usuario del servidor web, puedes recuperar el control con tu usuario SSH:

```bash
sudo chown -R TU_USUARIO:www-data /var/www/html/blogs/memoria
find /var/www/html/blogs/memoria -type d -exec chmod 775 {} \;
find /var/www/html/blogs/memoria -type f -exec chmod 664 {} \;

sudo chown -R www-data:www-data /var/www/html/blogs/memoria/{content,assets,config}
sudo chmod 775 /var/www/html/blogs/memoria/{content,assets,config}
```

Reemplaza `TU_USUARIO` por tu usuario SSH. Con esta secuencia podrás ejecutar `git pull` normalmente y mantener el servidor con permisos de escritura en las rutas necesarias.

## Licencia

Este proyecto se distribuye bajo **European Union Public Licence (EUPL)**. Consulta el texto completo de la licencia para conocer los términos de uso, distribución y modificación.

---

¿Preguntas o incidencias? Abre un _issue_ en GitHub o contacta con el equipo de desarrollo. ¡Bienvenido a Nammu! 

