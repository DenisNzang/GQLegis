# Guía Legal Guinea Ecuatorial - Sistema de Q&A Legislativo

## Descripción del Proyecto

**Guía Legal Guinea Ecuatorial** es una aplicación web informativa que proporciona preguntas y respuestas sobre la legislación de Guinea Ecuatorial, enfocándose en tres áreas principales:
- **Educación General**
- **Régimen General de Seguridad Social**
- **Ley General de Trabajo**

El sistema permite a los usuarios buscar información legal, registrarse como usuarios aprobados, sugerir nuevas preguntas, solicitar información sobre leyes adicionales y adquirir el texto completo de las leyes disponibles.

## Características Principales

### Para Usuarios Públicos:
- **Búsqueda avanzada**: Filtrar por ley específica, categoría o palabras clave
- **Acceso a Q&A**: Ver preguntas y respuestas aprobadas sobre legislación
- **Sistema de registro**: Solicitar aprobación para funciones avanzadas

### Para Usuarios Registrados y Aprobados:
- **Sugerir preguntas**: Proponer nuevas preguntas para ser respondidas oficialmente
- **Solicitar leyes**: Pedir la inclusión de nuevas leyes en el sistema
- **Adquirir leyes**: Solicitar el texto completo de leyes (con precio)
- **Notificaciones por email**: Confirmación de registros y acciones

### Para Administradores:
- **Panel de administración**: Aprobar/rechazar usuarios y contenido
- **Edición de preguntas**: Redactar respuestas oficiales a preguntas sugeridas
- **Gestión de solicitudes**: Administrar pedidos de leyes nuevas
- **Estadísticas**: Seguimiento de descargas y actividad

## Arquitectura Técnica

### Tecnologías Utilizadas:
- **Backend**: PHP 7.4+ con SQLite3
- **Frontend**: HTML5, CSS3, JavaScript vanilla
- **Email**: PHPMailer con SMTP de Gmail
- **Base de datos**: SQLite (archivo `leyes_guinea.db`)
- **Sesiones**: PHP Sessions para autenticación

### Estructura de Archivos:
```
├── index.php              # Página principal con sistema de búsqueda
├── admin.php             # Panel de administración
├── login.php             # Sistema de autenticación
├── logout.php            # Cierre de sesión
├── config_email.php      # Configuración de PHPMailer
├── leyes_guinea.db       # Base de datos SQLite (se genera automáticamente)
├── leyes_guinea.db.sql   # Esquema SQL inicial
└── (logo.png)           # Logo opcional del sitio
```

## Requisitos del Sistema

### Servidor Web:
- **PHP 7.4 o superior** (con extensión SQLite3 habilitada)
- **Servidor web** (Apache, Nginx, o servidor PHP integrado)
- **Permisos de escritura** en el directorio para la base de datos

### Configuración de Email (Opcional pero recomendado):
- **Cuenta de Gmail** para enviar notificaciones
- **Contraseña de aplicación** de Google (no la contraseña normal)

## Instalación Rápida

### Paso 1: Descargar y Preparar
1. Descargar todos los archivos del proyecto
2. Colocarlos en el directorio público de tu servidor web
3. Asegurar permisos de escritura: `chmod 755 .` (Linux)

### Paso 2: Configurar Email (Opcional)
1. Editar `config_email.php`:
```php
define('SMTP_USER', 'tu-email@gmail.com');
define('SMTP_PASS', 'contraseña-aplicación-google');
```

2. Para obtener contraseña de aplicación de Google:
   - Ir a https://myaccount.google.com/security
   - Activar "Verificación en dos pasos"
   - Generar "Contraseña de aplicación" para correo

### Paso 3: Acceder por Primera Vez
1. Abrir `index.php` en tu navegador
2. La base de datos se creará automáticamente con:
   - 3 leyes predefinidas
   - Esquema completo de tablas

### Paso 4: Acceso Administrativo
1. Registrar un usuario desde la página principal
2. En la base de datos, manualmente:
```sql
UPDATE usuarios SET 
  estado = 'aprobado', 
  tipo = 'admin' 
WHERE email = 'tu-email@ejemplo.com';
```

O usar el usuario demo:
- **Email**: `admin@guinealegal.com`
- **Contraseña**: `demo123`

## Estructura de la Base de Datos

### Tablas Principales:

1. **`leyes`** - Catálogo de leyes disponibles
   ```sql
   id, nombre, descripcion, codigo, precio_ley
   ```

2. **`preguntas`** - Preguntas y respuestas aprobadas
   ```sql
   id, ley_id, pregunta, respuesta, categoria, estado, sugerida_por
   ```

3. **`usuarios`** - Usuarios registrados
   ```sql
   id, email, nombre, tipo, estado, fecha_registro
   ```

4. **`solicitudes_leyes`** - Peticiones de nuevas leyes
5. **`descargas`** - Historial de solicitudes de leyes

## Configuración Avanzada

### Personalizar Leyes Iniciales
Editar `index.php` (función `inicializarBaseDatos()`) para modificar las leyes predefinidas.

### Cambiar Precios de Leyes
Desde el panel administrativo o directamente en la base de datos:
```sql
UPDATE leyes SET precio_ley = 50.00 WHERE codigo = 'EDU';
```

### Añadir Preguntas Iniciales
Ejecutar SQL en la base de datos:
```sql
INSERT INTO preguntas (ley_id, pregunta, respuesta, categoria, estado) 
VALUES (1, '¿Cuál es la edad mínima para trabajar?', 'Respuesta detallada...', 'Edad laboral', 'aprobada');
```

## Flujo de Trabajo Típico

### Para Usuarios:
1. **Registro** → Aprobación por administrador → Acceso a funciones completas
2. **Búsqueda** → Encuentra información relevante
3. **Sugerencia** → Propone nueva pregunta → Admin investiga y responde
4. **Adquisición** → Solicita ley completa → Admin contacta para pago/entrega

### Para Administradores:
1. **Revisión diaria** → Aprobar usuarios/preguntas pendientes
2. **Investigación** → Buscar respuestas oficiales para preguntas sugeridas
3. **Gestión** → Crear nuevas leyes desde solicitudes aprobadas
4. **Contacto** → Coordinar entrega de leyes completas a usuarios

## Sistema de Notificaciones

### Emails Automáticos Enviados:
1. **Registro exitoso** → Confirmación al usuario
2. **Nuevo registro** → Notificación al administrador
3. **Pregunta sugerida** → Alerta al administrador
4. **Solicitud de ley** → Notificación al administrador
5. **Descarga solicitada** → Informe al administrador

### Configuración SMTP:
El sistema usa **PHPMailer** con estas constantes en `config_email.php`:
```php
SMTP_HOST, SMTP_USER, SMTP_PASS, SMTP_PORT, SMTP_SECURE
FROM_EMAIL, FROM_NAME, ADMIN_EMAIL
```

## Consideraciones de Seguridad

### Implementadas:
- **Validación de email** en registros
- **Sistema de aprobación** para usuarios
- **Protección XSS** con `htmlspecialchars()`
- **Prepared statements** para consultas SQL
- **Sesiones PHP** para autenticación

### Recomendadas para Producción:
1. Usar HTTPS
2. Cambiar contraseña de administrador demo
3. Implementar hash de contraseñas real
4. Limitar intentos de login
5. Realizar backups periódicos de la base de datos

## Mantenimiento y Backups

### Backups de Base de Datos:
```bash
# Copia manual
cp leyes_guinea.db backup/leyes_guinea_$(date +%Y%m%d).db

# Restaurar
cp backup/leyes_guinea_20240101.db leyes_guinea.db
```

### Limpieza de Datos:
- Las preguntas rechazadas se eliminan automáticamente
- Mantener historial de descargas según necesidades
- Revisar usuarios inactivos periódicamente

## Solución de Problemas Comunes

### Problema: "No se pueden enviar emails"
**Solución:**
1. Verificar credenciales SMTP en `config_email.php`
2. Asegurar que Google permite "Apps menos seguras" o usar contraseña de aplicación
3. Revisar logs de error de PHP

### Problema: "Base de datos no se crea"
**Solución:**
1. Verificar permisos de escritura en el directorio
2. Confirmar que extensión SQLite3 está habilitada en PHP
3. Revisar errores en consola del navegador

### Problema: "Usuario admin no funciona"
**Solución:**
1. Verificar que el usuario tiene `tipo = 'admin'` y `estado = 'aprobado'`
2. Probar con credenciales demo: admin@guinealegal.com / demo123
3. Crear nuevo usuario y actualizar manualmente en la base de datos

## Personalización y Extensión

### Añadir Nuevas Leyes:
1. Usuario sugiere nueva ley desde su panel
2. Administrador aprueba la solicitud
3. Administrador añade descripción y precio desde panel admin

### Crear Preguntas Iniciales:
Usar script SQL o añadir manualmente desde panel administrativo.

### Modificar Diseño:
Editar los estilos CSS embebidos en cada archivo PHP.

## Roles y Permisos

### Usuario Público:
- Ver preguntas/respuestas aprobadas
- Buscar información
- Registrarse

### Usuario Aprobado:
- Todo lo anterior +
- Sugerir preguntas
- Solicitar nuevas leyes
- Adquirir leyes completas

### Administrador:
- Todo lo anterior +
- Aprobar/rechazar usuarios
- Editar y responder preguntas
- Gestionar solicitudes de leyes
- Ver estadísticas de uso

## Licencia y Créditos

Este sistema está diseñado para proveer información legal accesible sobre Guinea Ecuatorial. 

**Nota Legal**: Esta herramienta tiene fines informativos únicamente y no sustituye asesoramiento legal profesional. Los usuarios deben consultar con abogados calificados para asuntos legales específicos.

---

## Primeros Pasos Rápidos

1. **Para evaluar el sistema**: 
   - Abrir `index.php` 
   - Usar credenciales demo: admin@guinealegal.com / demo123

2. **Para implementar en producción**:
   - Configurar email en `config_email.php`
   - Cambiar credenciales de administrador
   - Personalizar leyes iniciales según necesidades

3. **Para desarrollo**:
   - Clonar/repositorio
   - Modificar según requerimientos específicos
   - Añadir preguntas iniciales relevantes
