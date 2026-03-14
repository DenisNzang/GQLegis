<?php
// admin.php
session_start();

// Verificar que sea administrador
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = new SQLite3('leyes_guinea.db');

// Procesar acciones
$action = $_POST['action'] ?? '';
$message = '';

switch ($action) {
    case 'aprobar_usuario':
        $id = $_POST['id'];
        $stmt = $db->prepare('UPDATE usuarios SET estado = "aprobado" WHERE id = ?');
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $stmt->execute();
        $message = 'Usuario aprobado.';
        break;
        
    case 'rechazar_usuario':
        $id = $_POST['id'];
        $stmt = $db->prepare('UPDATE usuarios SET estado = "rechazado" WHERE id = ?');
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $stmt->execute();
        $message = 'Usuario rechazado.';
        break;
        
    case 'aprobar_pregunta':
        $id = $_POST['id'];
        // Ahora el administrador puede editar la pregunta antes de aprobarla
        if (isset($_POST['pregunta_editada'])) {
            $pregunta_editada = trim($_POST['pregunta_editada']);
            $stmt = $db->prepare('UPDATE preguntas SET pregunta = ?, estado = "aprobada" WHERE id = ?');
            $stmt->bindValue(1, $pregunta_editada, SQLITE3_TEXT);
            $stmt->bindValue(2, $id, SQLITE3_INTEGER);
        } else {
            $stmt = $db->prepare('UPDATE preguntas SET estado = "aprobada" WHERE id = ?');
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        }
        $stmt->execute();
        $message = 'Pregunta aprobada.';
        break;
        
    case 'editar_pregunta':
        $id = $_POST['id'];
        $pregunta = trim($_POST['pregunta']);
        $respuesta = trim($_POST['respuesta']);
        $categoria = trim($_POST['categoria']);
        
        $stmt = $db->prepare('UPDATE preguntas SET pregunta = ?, respuesta = ?, categoria = ?, estado = "aprobada" WHERE id = ?');
        $stmt->bindValue(1, $pregunta, SQLITE3_TEXT);
        $stmt->bindValue(2, $respuesta, SQLITE3_TEXT);
        $stmt->bindValue(3, $categoria, SQLITE3_TEXT);
        $stmt->bindValue(4, $id, SQLITE3_INTEGER);
        $stmt->execute();
        $message = 'Pregunta editada y aprobada.';
        break;
        
    case 'rechazar_pregunta':
        $id = $_POST['id'];
        $stmt = $db->prepare('DELETE FROM preguntas WHERE id = ?');
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $stmt->execute();
        $message = 'Pregunta rechazada.';
        break;
        
    case 'aprobar_solicitud_ley':
        $id = $_POST['id'];
        if (isset($_POST['descripcion_ley'])) {
            $descripcion_ley = trim($_POST['descripcion_ley']);
            $stmt = $db->prepare('UPDATE solicitudes_leyes SET descripcion = ?, estado = "aprobada" WHERE id = ?');
            $stmt->bindValue(1, $descripcion_ley, SQLITE3_TEXT);
            $stmt->bindValue(2, $id, SQLITE3_INTEGER);
        } else {
            $stmt = $db->prepare('UPDATE solicitudes_leyes SET estado = "aprobada" WHERE id = ?');
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        }
        $stmt->execute();
        $message = 'Solicitud de ley aprobada.';
        break;
        
    case 'rechazar_solicitud_ley':
        $id = $_POST['id'];
        $stmt = $db->prepare('UPDATE solicitudes_leyes SET estado = "rechazada" WHERE id = ?');
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $stmt->execute();
        $message = 'Solicitud de ley rechazada.';
        break;
        
    case 'crear_ley_desde_solicitud':
        $solicitud_id = $_POST['solicitud_id'];
        $nombre_ley = trim($_POST['nombre_ley']);
        $descripcion = trim($_POST['descripcion']);
        $codigo = trim($_POST['codigo']);
        $precio = floatval($_POST['precio']);
        
        // Crear la nueva ley
        $stmt = $db->prepare('INSERT INTO leyes (nombre, descripcion, codigo, precio_ley) VALUES (?, ?, ?, ?)');
        $stmt->bindValue(1, $nombre_ley, SQLITE3_TEXT);
        $stmt->bindValue(2, $descripcion, SQLITE3_TEXT);
        $stmt->bindValue(3, $codigo, SQLITE3_TEXT);
        $stmt->bindValue(4, $precio, SQLITE3_FLOAT);
        $stmt->execute();
        
        // Marcar la solicitud como completada
        $stmt = $db->prepare('UPDATE solicitudes_leyes SET estado = "completada" WHERE id = ?');
        $stmt->bindValue(1, $solicitud_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        $message = 'Ley creada exitosamente desde la solicitud.';
        break;
}

// Obtener datos para mostrar
// Usuarios pendientes
$usuarios_pendientes = [];
$result = $db->query('SELECT * FROM usuarios WHERE estado = "pendiente" ORDER BY fecha_registro');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $usuarios_pendientes[] = $row;
}

// Usuarios aprobados
$usuarios_aprobados = [];
$result = $db->query('SELECT * FROM usuarios WHERE estado = "aprobado" ORDER BY fecha_registro');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $usuarios_aprobados[] = $row;
}

// Preguntas pendientes
$preguntas_pendientes = [];
$result = $db->query('SELECT p.*, l.nombre as ley_nombre, u.email as usuario_email 
                      FROM preguntas p 
                      JOIN leyes l ON p.ley_id = l.id 
                      LEFT JOIN usuarios u ON p.sugerida_por = u.id 
                      WHERE p.estado = "pendiente" 
                      ORDER BY p.fecha_sugerencia');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $preguntas_pendientes[] = $row;
}

// Solicitudes de leyes pendientes
$solicitudes_pendientes = [];
$result = $db->query('SELECT s.*, u.email as usuario_email 
                      FROM solicitudes_leyes s 
                      JOIN usuarios u ON s.usuario_id = u.id 
                      WHERE s.estado = "pendiente" 
                      ORDER BY s.fecha_solicitud');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $solicitudes_pendientes[] = $row;
}

// Descargas solicitadas
$descargas = [];
$result = $db->query('SELECT d.*, l.nombre as ley_nombre, u.email as usuario_email 
                      FROM descargas d 
                      JOIN leyes l ON d.ley_id = l.id 
                      JOIN usuarios u ON d.usuario_id = u.id 
                      ORDER BY d.fecha_descarga DESC LIMIT 20');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $descargas[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrador</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        header { background: #2c3e50; color: white; padding: 1rem 0; margin-bottom: 2rem; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        h1 { margin: 0; }
        .nav-links a { color: white; margin-left: 20px; text-decoration: none; }
        .section { background: white; border-radius: 8px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { margin-top: 0; color: #2c3e50; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .btn { background: #3498db; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #219653; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; font-weight: 600; color: #2c3e50; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .message.success { background: #d4edda; color: #155724; }
        .message.error { background: #f8d7da; color: #721c24; }
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; }
        .badge-pendiente { background: #fff3cd; color: #856404; }
        .badge-aprobado { background: #d4edda; color: #155724; }
        .badge-rechazado { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <h1>Panel de Administración</h1>
                <div class="nav-links">
                    <span>Administrador: <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
                    <a href="index.php">Ver sitio público</a>
                    <a href="logout.php">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if ($message): ?>
        <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Usuarios Pendientes -->
        <div class="section">
            <h2>Usuarios Pendientes de Aprobación (<?php echo count($usuarios_pendientes); ?>)</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Fecha Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios_pendientes as $usuario): ?>
                    <tr>
                        <td><?php echo $usuario['id']; ?></td>
                        <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['telefono']); ?></td>
                        <td><?php echo $usuario['fecha_registro']; ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="aprobar_usuario">
                                <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                                <button type="submit" class="btn btn-success">Aprobar</button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="rechazar_usuario">
                                <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                                <button type="submit" class="btn btn-danger">Rechazar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Preguntas Pendientes -->
        <div class="section">
            <h2>Preguntas Sugeridas Pendientes (<?php echo count($preguntas_pendientes); ?>)</h2>
            <!-- Modificar la tabla de preguntas pendientes para incluir edición: -->
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Pregunta</th>
                        <th>Ley</th>
                        <th>Sugerida por</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preguntas_pendientes as $pregunta): ?>
                    <tr>
                        <td><?php echo $pregunta['id']; ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="editar_pregunta">
                                <input type="hidden" name="id" value="<?php echo $pregunta['id']; ?>">
                                <textarea name="pregunta" rows="2" style="width: 100%; margin-bottom: 5px;"><?php echo htmlspecialchars($pregunta['pregunta']); ?></textarea><br>
                                <textarea name="respuesta" rows="4" style="width: 100%; margin-bottom: 5px;" placeholder="Escribe la respuesta oficial..."></textarea><br>
                                <input type="text" name="categoria" placeholder="Categoría" style="width: 100%; margin-bottom: 5px;">
                                <button type="submit" class="btn btn-success">Aprobar con respuesta</button>
                            </form>
                        </td>
                        <td><?php echo htmlspecialchars($pregunta['ley_nombre']); ?></td>
                        <td><?php echo htmlspecialchars($pregunta['usuario_email'] ?? 'Anónimo'); ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="aprobar_pregunta">
                                <input type="hidden" name="id" value="<?php echo $pregunta['id']; ?>">
                                <button type="submit" class="btn btn-success">Aprobar tal cual</button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="rechazar_pregunta">
                                <input type="hidden" name="id" value="<?php echo $pregunta['id']; ?>">
                                <button type="submit" class="btn btn-danger">Rechazar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Solicitudes de Leyes -->
        <div class="section">
            <h2>Solicitudes de Nuevas Leyes (<?php echo count($solicitudes_pendientes); ?>)</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Ley</th>
                        <th>Descripción</th>
                        <th>Solicitante</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes_pendientes as $solicitud): ?>
                    <tr>
                        <td><?php echo $solicitud['id']; ?></td>
                        <td><?php echo htmlspecialchars($solicitud['nombre_ley']); ?></td>
                        <td style="max-width: 300px;"><?php echo htmlspecialchars(substr($solicitud['descripcion'], 0, 100)); ?>...</td>
                        <td><?php echo htmlspecialchars($solicitud['usuario_email']); ?></td>
                        <td><?php echo $solicitud['fecha_solicitud']; ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="aprobar_solicitud_ley">
                                <input type="hidden" name="id" value="<?php echo $solicitud['id']; ?>">
                                <button type="submit" class="btn btn-success">Aprobar</button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="rechazar_solicitud_ley">
                                <input type="hidden" name="id" value="<?php echo $solicitud['id']; ?>">
                                <button type="submit" class="btn btn-danger">Rechazar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Descargas Solicitadas -->
        <div class="section">
            <h2>Últimas Descargas Solicitadas</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Ley</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($descargas as $descarga): ?>
                    <tr>
                        <td><?php echo $descarga['id']; ?></td>
                        <td><?php echo htmlspecialchars($descarga['usuario_email']); ?></td>
                        <td><?php echo htmlspecialchars($descarga['ley_nombre']); ?></td>
                        <td><?php echo $descarga['fecha_descarga']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php
$db->close();
?>