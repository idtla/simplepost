<?php
// Verificar autenticación HTTP básica
$user = getenv('BASIC_USER');
$pass = getenv('BASIC_PASS');
if (!isset($_SERVER['PHP_AUTH_USER']) ||
    $_SERVER['PHP_AUTH_USER'] !== $user ||
    $_SERVER['PHP_AUTH_PW'] !== $pass) {
    header('WWW-Authenticate: Basic realm="CMS"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Autenticación requerida';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Publicar artículo</title>
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
    tinymce.init({
        selector: '#content',
        plugins: 'image link code',
        toolbar: 'undo redo | bold italic | alignleft aligncenter alignright | code | image',
        images_upload_url: 'publish.php?image=1',
        automatic_uploads: true
    });

    function addCategory() {
        const container = document.getElementById('categories');
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'categories[]';
        container.appendChild(document.createElement('br'));
        container.appendChild(input);
    }
    </script>
</head>
<body>
<h1>Nuevo artículo</h1>
<form action="publish.php" method="post" enctype="multipart/form-data">
    <label>Título: <input type="text" name="title" required></label><br>
    <label>Meta descripción:<br><textarea name="meta_description" required></textarea></label><br>
    <label>Fecha (dd/mm/aaaa): <input type="text" name="date" required></label><br>
    <label>Autor: <input type="text" name="author" required></label><br>
    <div id="categories">
        Categorías:<br>
        <input type="text" name="categories[]">
    </div>
    <button type="button" onclick="addCategory()">Añadir categoría</button><br>
    Etiquetas:<br>
    <input type="text" name="tags[]"><br>
    <input type="text" name="tags[]"><br>
    <input type="text" name="tags[]"><br>
    <label>Imagen destacada: <input type="file" name="featured_image"></label><br>
    <textarea id="content" name="content"></textarea><br>
    <button type="submit">Publicar</button>
</form>
</body>
</html>
