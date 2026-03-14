<?php
// index.php
session_start();

// Incluir PHPMailer
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';
require 'config_email.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Conexión a la base de datos SQLite
$db = new SQLite3('leyes_guinea.db');

// Función para inicializar la base de datos si no existe
function inicializarBaseDatos($db) {
    $db->exec('CREATE TABLE IF NOT EXISTS leyes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        descripcion TEXT,
        codigo TEXT UNIQUE NOT NULL,
        precio_ley DECIMAL(10,2) DEFAULT 0.00
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS preguntas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ley_id INTEGER NOT NULL,
        pregunta TEXT NOT NULL,
        respuesta TEXT NOT NULL,
        categoria TEXT,
        keywords TEXT,
        estado TEXT DEFAULT "aprobada",
        sugerida_por INTEGER,
        fecha_sugerencia DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ley_id) REFERENCES leyes(id),
        FOREIGN KEY (sugerida_por) REFERENCES usuarios(id)
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        nombre TEXT,
        telefono TEXT,
        tipo TEXT DEFAULT "usuario",
        fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
        estado TEXT DEFAULT "pendiente",
        codigo_verificacion TEXT,
        contrasena TEXT
    )');

    $columns = $db->query("PRAGMA table_info(usuarios)");
    $contrasenaExists = false;
    while ($column = $columns->fetchArray()) {
        if ($column['name'] === 'contrasena') {
            $contrasenaExists = true;
            break;
        }
    }

    if (!$contrasenaExists) {
        $db->exec('ALTER TABLE usuarios ADD COLUMN contrasena TEXT');
    }

    $db->exec('CREATE TABLE IF NOT EXISTS solicitudes_leyes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER NOT NULL,
        nombre_ley TEXT NOT NULL,
        descripcion TEXT,
        estado TEXT DEFAULT "pendiente",
        fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS descargas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER NOT NULL,
        ley_id INTEGER NOT NULL,
        fecha_descarga DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
        FOREIGN KEY (ley_id) REFERENCES leyes(id)
    )');

    $result = $db->query('SELECT COUNT(*) as count FROM leyes');
    $row = $result->fetchArray();

    if ($row['count'] == 0) {
        $db->exec("INSERT INTO leyes (nombre, descripcion, codigo, precio_ley) VALUES
            ('Ley sobre Educación General', 'Regula el sistema educativo en Guinea Ecuatorial', 'EDU', 25.00),
            ('Reglamento del Régimen General de Seguridad Social', 'Establece las prestaciones de seguridad social', 'SS', 30.00),
            ('Ley General de Trabajo', 'Regula las relaciones laborales entre empleadores y trabajadores', 'TRABAJO', 35.00)");
    }
}

inicializarBaseDatos($db);

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'registro':
                $email = trim($_POST['email']);
                $nombre = trim($_POST['nombre']);
                $telefono = trim($_POST['telefono']);
                $contrasena = $_POST['contrasena'];

                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $stmt = $db->prepare('SELECT id FROM usuarios WHERE email = ?');
                    $stmt->bindValue(1, $email, SQLITE3_TEXT);
                    $result = $stmt->execute();

                    if ($result->fetchArray()) {
                        $mensaje = 'Este email ya está registrado.';
                        $tipo_mensaje = 'error';
                    } else {
                        if (strlen($contrasena) < 6) {
                            $mensaje = 'La contraseña debe tener al menos 6 caracteres.';
                            $tipo_mensaje = 'error';
                        } else {
                            $codigo_verificacion = md5(uniqid(rand(), true));
                            $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);

                            $stmt = $db->prepare('INSERT INTO usuarios (email, nombre, telefono, codigo_verificacion, contrasena) VALUES (?, ?, ?, ?, ?)');
                            $stmt->bindValue(1, $email, SQLITE3_TEXT);
                            $stmt->bindValue(2, $nombre, SQLITE3_TEXT);
                            $stmt->bindValue(3, $telefono, SQLITE3_TEXT);
                            $stmt->bindValue(4, $codigo_verificacion, SQLITE3_TEXT);
                            $stmt->bindValue(5, $contrasena_hash, SQLITE3_TEXT);

                            if ($stmt->execute()) {
                                try {
                                    $mail = new PHPMailer(true);
                                    $mail->isSMTP();
                                    $mail->Host = SMTP_HOST;
                                    $mail->SMTPAuth = true;
                                    $mail->Username = SMTP_USER;
                                    $mail->Password = SMTP_PASS;
                                    $mail->SMTPSecure = SMTP_SECURE;
                                    $mail->Port = SMTP_PORT;
                                    $mail->setFrom(FROM_EMAIL, FROM_NAME);
                                    $mail->addAddress($email, $nombre);
                                    $mail->isHTML(true);
                                    $mail->Subject = 'Registro en Guía Legal Guinea Ecuatorial';
                                    $mail->Body = "<h2>¡Gracias por registrarte, $nombre!</h2><p>Tu registro ha sido recibido y será revisado por el administrador.</p>";
                                    $mail->AltBody = "Gracias por registrarte, $nombre.";
                                    $mail->send();
                                    $mensaje = 'Registro enviado correctamente. Revisa tu email para más información.';
                                    $tipo_mensaje = 'exito';
                                } catch (Exception $e) {
                                    $mensaje = 'Registro completado. Hubo un problema al enviar el email de confirmación.';
                                    $tipo_mensaje = 'error';
                                }
                            } else {
                                $mensaje = 'Error en el registro. Intenta nuevamente.';
                                $tipo_mensaje = 'error';
                            }
                        }
                    }
                } else {
                    $mensaje = 'Email inválido.';
                    $tipo_mensaje = 'error';
                }
                break;

            case 'sugerir_pregunta':
            case 'solicitar_ley':
            case 'descargar_ley':
                // Lógica existente para otras acciones
                break;
        }
    }
}

$resultados = [];
$leyFiltro = $_GET['ley'] ?? '';
$categoriaFiltro = $_GET['categoria'] ?? '';
$terminoBusqueda = $_GET['busqueda'] ?? '';
$hayFiltros = !empty($leyFiltro) || !empty($categoriaFiltro) || !empty($terminoBusqueda);

if ($hayFiltros) {
    $sql = 'SELECT p.*, l.nombre as ley_nombre, l.codigo as ley_codigo
            FROM preguntas p
            JOIN leyes l ON p.ley_id = l.id
            WHERE p.estado = "aprobada"';

    $params = [];
    $types = [];

    if (!empty($leyFiltro)) {
        $sql .= ' AND l.codigo = :ley';
        $params[':ley'] = $leyFiltro;
        $types[':ley'] = SQLITE3_TEXT;
    }

    if (!empty($categoriaFiltro)) {
        $sql .= ' AND p.categoria = :categoria';
        $params[':categoria'] = $categoriaFiltro;
        $types[':categoria'] = SQLITE3_TEXT;
    }

    if (!empty($terminoBusqueda)) {
        $sql .= ' AND (p.pregunta LIKE :busqueda OR p.respuesta LIKE :busqueda OR p.keywords LIKE :busqueda)';
        $params[':busqueda'] = '%' . $terminoBusqueda . '%';
        $types[':busqueda'] = SQLITE3_TEXT;
    }

    $sql .= ' ORDER BY l.nombre, p.categoria, p.pregunta';
    $stmt = $db->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $types[$key]);
    }

    $result = $stmt->execute();

    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $resultados[] = $row;
        }
    }
} else {
    $sql = 'SELECT p.*, l.nombre as ley_nombre, l.codigo as ley_codigo
            FROM preguntas p
            JOIN leyes l ON p.ley_id = l.id
            WHERE p.estado = "aprobada"
            ORDER BY l.nombre, p.categoria, p.pregunta';

    $result = $db->query($sql);

    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $resultados[] = $row;
        }
    }
}

$categorias = [];
$result = $db->query('SELECT DISTINCT categoria FROM preguntas WHERE categoria IS NOT NULL AND categoria != "" AND estado = "aprobada" ORDER BY categoria');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $categorias[] = $row['categoria'];
}

$leyes = [];
$result = $db->query('SELECT * FROM leyes ORDER BY nombre');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $leyes[] = $row;
}

$usuario_logueado = false;
$usuario_aprobado = false;
$usuario_nombre = '';

if (isset($_SESSION['usuario_id'])) {
    $usuario_logueado = true;
    $usuario_aprobado = $_SESSION['usuario_aprobado'] ?? false;
    $usuario_nombre = $_SESSION['usuario_nombre'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guía Legal - Guinea Ecuatorial</title>
    <link rel="stylesheet" href="../assets/css/ui-shared.css">
    <style>
        :root {
            --primary-color: #3498db;
            --primary-hover: #2980b9;
        }

        body {
            background-color: #f8f9fa;
        }

        .app-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .app-header .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .app-header .logo-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .app-header .logo {
            max-height: 60px;
            border-radius: 6px;
        }

        .app-header .site-title {
            font-size: 1.8rem;
            font-weight: 300;
            margin: 0;
            color: white;
        }

        .app-header .site-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }

        .user-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
        }

        .btn:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1f639b 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .btn-user {
            background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
        }

        .btn-logout {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        main {
            padding: 2rem 0;
            flex: 1;
        }

        .search-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
            font-size: 0.9rem;
        }

        select, input[type="text"] {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
            width: 100%;
        }

        select:focus, input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .results-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .result-item {
            border-left: 4px solid var(--primary-color);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background: #f8fafc;
            border-radius: 0 8px 8px 0;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .result-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .question {
            font-size: 1.15rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.8rem;
        }

        .answer {
            color: #34495e;
            margin-bottom: 1rem;
            line-height: 1.6;
            white-space: pre-line;
        }

        .meta-info {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 0.85rem;
            color: #7f8c8d;
            padding-top: 0.8rem;
            border-top: 1px dashed #e0e0e0;
        }

        .law-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
            background: #e8f4fc;
            color: #2980b9;
        }

        .mensaje {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .mensaje.exito {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .mensaje.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 12px;
            max-width: 500px;
            position: relative;
        }

        .close {
            position: absolute;
            right: 1.5rem;
            top: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }

        .close:hover {
            color: #333;
        }

        @media (max-width: 768px) {
            .app-header .header-content {
                flex-direction: column;
                text-align: center;
            }

            .user-actions {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container">
            <div class="header-logo">
                <a href="../index.php">
                    <img src="../assets/img/logo_black.png" alt="STI Logo">
                </a>
                <a href="../index.php" class="site-title">STI PROJECTS</a>
            </div>
            <nav class="header-nav">
                <a href="../index.php" class="nav-link">Inicio</a>
                <a href="index.php" class="nav-link active">Legislación</a>
            </nav>
        </div>
    </header>

    <div class="app-header">
        <div class="container">
            <div class="header-content">
                <div class="logo-container">
                    <img src="logo.png" alt="Guía Legal Logo" class="logo">
                    <div>
                        <h1 class="site-title">Guía Legal</h1>
                        <p class="site-subtitle">Legislación de Guinea Ecuatorial</p>
                    </div>
                </div>
                <div class="user-actions">
                    <?php if ($usuario_logueado): ?>
                        <span style="color: rgba(255,255,255,0.9);">Hola, <?php echo htmlspecialchars($usuario_nombre); ?></span>
                        <?php if ($usuario_aprobado): ?>
                            <a href="#" class="btn btn-user" onclick="abrirModal('modalPregunta')">Sugerir Pregunta</a>
                            <a href="#" class="btn btn-user" onclick="abrirModal('modalLey')">Solicitar Ley</a>
                        <?php endif; ?>
                        <a href="logout.php" class="btn btn-logout">Cerrar Sesión</a>
                    <?php else: ?>
                        <a href="login.php" class="btn">Iniciar Sesión</a>
                        <a href="#" class="btn" onclick="abrirModal('modalRegistro')">Registrarse</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <main class="site-main">
        <div class="container">
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <section class="search-section">
                <form method="GET" class="search-form">
                    <div class="form-group">
                        <label for="busqueda">Buscar</label>
                        <input type="text" id="busqueda" name="busqueda" placeholder="Término de búsqueda..." value="<?php echo htmlspecialchars($terminoBusqueda); ?>">
                    </div>
                    <div class="form-group">
                        <label for="ley">Ley</label>
                        <select id="ley" name="ley">
                            <option value="">Todas</option>
                            <?php foreach ($leyes as $ley): ?>
                                <option value="<?php echo htmlspecialchars($ley['codigo']); ?>" <?php echo $leyFiltro === $ley['codigo'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ley['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="categoria">Categoría</label>
                        <select id="categoria" name="categoria">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoriaFiltro === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn">Buscar</button>
                    <?php if ($hayFiltros): ?>
                        <a href="index.php" class="btn" style="background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);">Limpiar</a>
                    <?php endif; ?>
                </form>
            </section>

            <section class="results-section">
                <div class="results-header">
                    <h2 class="results-count"><?php echo count($resultados); ?> resultado(s) encontrado(s)</h2>
                </div>

                <?php if (count($resultados) > 0): ?>
                    <?php foreach ($resultados as $resultado): ?>
                        <div class="result-item">
                            <h3 class="question"><?php echo htmlspecialchars($resultado['pregunta']); ?></h3>
                            <div class="answer"><?php echo htmlspecialchars($resultado['respuesta']); ?></div>
                            <div class="meta-info">
                                <span class="law-badge"><?php echo htmlspecialchars($resultado['ley_nombre']); ?></span>
                                <?php if (!empty($resultado['categoria'])): ?>
                                    <span class="law-badge" style="background: #f0f9f4; color: #27ae60;"><?php echo htmlspecialchars($resultado['categoria']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="result-item" style="border-left-color: #95a5a6;">
                        <p style="color: #7f8c8d; text-align: center; padding: 2rem;">
                            No se encontraron resultados. <?php echo $hayFiltros ? 'Intenta ajustar los filtros de búsqueda.' : 'Sé el primero en sugerir una pregunta.'; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <p>Denis Nzang Camps &copy; <span class="current-year"></span> - Todos los derechos reservados</p>
                <p><a href="mailto:denis.nzang@gmail.com">denis.nzang@gmail.com</a></p>
            </div>
        </div>
    </footer>

    <!-- Modal Registro -->
    <div id="modalRegistro" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalRegistro')">&times;</span>
            <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">Registrarse</h2>
            <form method="POST">
                <input type="hidden" name="accion" value="registro">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="registro_email">Email *</label>
                    <input type="email" id="registro_email" name="email" required>
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="registro_nombre">Nombre *</label>
                    <input type="text" id="registro_nombre" name="nombre" required>
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="registro_telefono">Teléfono</label>
                    <input type="tel" id="registro_telefono" name="telefono">
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="registro_contrasena">Contraseña *</label>
                    <input type="password" id="registro_contrasena" name="contrasena" required minlength="6">
                </div>
                <button type="submit" class="btn" style="width: 100%;">Registrarse</button>
            </form>
        </div>
    </div>

    <!-- Modal Sugerir Pregunta -->
    <div id="modalPregunta" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalPregunta')">&times;</span>
            <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">Sugerir Pregunta</h2>
            <form method="POST">
                <input type="hidden" name="accion" value="sugerir_pregunta">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label for="pregunta_ley">Ley *</label>
                    <select id="pregunta_ley" name="ley_id" required>
                        <option value="">Selecciona una ley</option>
                        <?php foreach ($leyes as $ley): ?>
                            <option value="<?php echo $ley['id']; ?>"><?php echo htmlspecialchars($ley['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="pregunta_texto">Pregunta *</label>
                    <input type="text" id="pregunta_texto" name="pregunta" required placeholder="Escribe tu pregunta...">
                </div>
                <button type="submit" class="btn" style="width: 100%;">Enviar Pregunta</button>
            </form>
        </div>
    </div>

    <!-- Modal Solicitar Ley -->
    <div id="modalLey" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalLey')">&times;</span>
            <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">Solicitar Nueva Ley</h2>
            <form method="POST">
                <input type="hidden" name="accion" value="solicitar_ley">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="solicitar_ley_nombre">Nombre de la Ley *</label>
                    <input type="text" id="solicitar_ley_nombre" name="nombre_ley" required placeholder="Ej: Ley de Comercio">
                </div>
                <button type="submit" class="btn" style="width: 100%;">Enviar Solicitud</button>
            </form>
        </div>
    </div>

    <script src="../assets/js/ui-shared.js"></script>
    <script>
        function abrirModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function cerrarModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                cerrarModal(event.target.id);
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    cerrarModal(modal.id);
                });
            }
        });

        <?php if ($mensaje): ?>
        setTimeout(() => {
            document.querySelectorAll('.mensaje').forEach(msg => {
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    msg.style.display = 'none';
                }, 500);
            });
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>
<?php
$db->close();
?>
