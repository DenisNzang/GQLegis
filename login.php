<?php
// login.php
session_start();

$db = new SQLite3('leyes_guinea.db');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Buscar usuario
    $stmt = $db->prepare('SELECT * FROM usuarios WHERE email = ?');
    $stmt->bindValue(1, $email, SQLITE3_TEXT);
    $result = $stmt->execute();
    $usuario = $result->fetchArray(SQLITE3_ASSOC);

    if ($usuario) {
        // Verificar estado
        if ($usuario['estado'] === 'aprobado') {
            // Verificar si es el usuario admin con contraseña 'demo123'
            if ($usuario['tipo'] === 'admin' && $password === 'demo123') {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_aprobado'] = true;
                $_SESSION['usuario_tipo'] = $usuario['tipo'];

                header('Location: admin.php');
                exit;
            }
            // Verificar contraseña para usuarios normales con password_verify
            elseif ($usuario['contrasena'] && password_verify($password, $usuario['contrasena'])) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_aprobado'] = true;
                $_SESSION['usuario_tipo'] = $usuario['tipo'];

                header('Location: index.php');
                exit;
            }
            // Verificación para usuarios antiguos que aún tienen la contraseña simple y no tienen contraseña hasheada
            elseif (!$usuario['contrasena'] && $password === 'demo123') {
                // Actualizar la contraseña del usuario antiguo con un hash seguro
                $nueva_contrasena_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt_update = $db->prepare('UPDATE usuarios SET contrasena = ? WHERE id = ?');
                $stmt_update->bindValue(1, $nueva_contrasena_hash, SQLITE3_TEXT);
                $stmt_update->bindValue(2, $usuario['id'], SQLITE3_INTEGER);
                $stmt_update->execute();

                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_aprobado'] = true;
                $_SESSION['usuario_tipo'] = $usuario['tipo'];

                header('Location: index.php');
                exit;
            } else {
                $error = 'Contraseña incorrecta.';
            }
        } elseif ($usuario['estado'] === 'pendiente') {
            $error = 'Tu cuenta está pendiente de aprobación por el administrador.';
        } else {
            $error = 'Tu cuenta ha sido rechazada.';
        }
    } else {
        $error = 'Usuario no encontrado.';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Guía Legal</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 10px;
            padding: 2.5rem;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1f639b 100%);
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .links {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .links a {
            color: #3498db;
            text-decoration: none;
        }
        
        .info {
            background: #e8f4fc;
            padding: 10px;
            border-radius: 6px;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Iniciar Sesión</h2>
        
        <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">Iniciar Sesión</button>
        </form>
        
        <div class="links">
            <a href="index.php">Volver al sitio</a>
        </div>
        
        <div class="info">
            <p><strong>Para usuarios registrados:</strong> Usa el email con el que te registraste y la contraseña "demo123"</p>
            <p><strong>Para administrador:</strong> Email: admin@guinealegal.com</p>
        </div>
    </div>
</body>
</html>
<?php
$db->close();
?>