# Publicador PHP Sencillo

Este proyecto permite generar y subir artículos HTML a un servidor remoto mediante SFTP.

## Preparación
1. Coloca `template.html`, `posts.json` y `sitemap.xml` en la raíz del repositorio. Pueden estar vacíos si es la primera vez.
2. Define las variables de entorno requeridas:
   - `SITE_URL`
   - `SFTP_HOST`
   - `SFTP_USER`
   - `SFTP_PASS`
   - `SFTP_POSTS_PATH`
   - `SFTP_IMAGES_PATH`
   - `BASIC_USER`
   - `BASIC_PASS`
3. El fichero `.htaccess` permite que la cabecera `Authorization` llegue a PHP para validar las credenciales.
4. Asegúrate de tener instalado el módulo **php-ssh2**. En Ubuntu se puede instalar con:
   ```bash
   sudo apt-get install php-ssh2
   ```
   También puedes usar phpseclib mediante Composer en caso necesario.

## Uso

Para probar localmente ejecuta:
```bash
php -S localhost:8000
```
Accede a `http://localhost:8000/index.php` e introduce las credenciales definidas en `BASIC_USER` y `BASIC_PASS`.

El formulario permite subir imágenes desde el editor y generar el HTML final. Al enviarlo se actualizan `posts.json` y `sitemap.xml` y se suben junto con el artículo y las imágenes vía SFTP.
