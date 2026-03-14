<?php
// config_email.php
// Configuración para PHPMailer

// Definir constantes para SMTP de Gmail
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'sti.bata@gmail.com');
define('SMTP_PASS', 'nyqj xqpc gtnf axbl'); // Esta debe ser una contraseña de aplicación de Gmail
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // 'tls' o 'ssl'
define('FROM_EMAIL', 'sti.bata@gmail.com');
define('FROM_NAME', 'Guía Legal Guinea Ecuatorial');
define('ADMIN_EMAIL', 'sti.bata@gmail.com');

// En index.php, incluir este archivo y usar las constantes
?>