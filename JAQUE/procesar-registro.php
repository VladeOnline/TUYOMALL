
<?php
require_once __DIR__ . '/../Conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sql = "INSERT INTO IniciarSesionCliente 
                (Nombre, Apellido, Email, password_hash) 
                VALUES (:nombre, :apellido, :email, :password)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':nombre'   => $_POST['r-nombre'],
            ':apellido' => $_POST['r-apellido'],
            ':email'    => $_POST['r-email'],
            ':password' => password_hash($_POST['r-password'], PASSWORD_DEFAULT)
        ]);

       
        // Redirigir inmediatamente a la página principal
        header("Location: /principal.html"); // o /acceso-cliente.php si esa es tu pantalla principal
        exit;

    } catch (PDOException $e) {
        // Si hay error, puedes regresar al formulario con un mensaje
        echo "❌ Error al registrar: " . htmlspecialchars($e->getMessage());
        // Opcional: redirigir de nuevo al formulario
        // header("Location: /acceso-cliente.php");
        // exit;
    }
}
?>
