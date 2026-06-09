# Documentación del Proyecto — Lab2FA

Sistema de autenticación de dos factores (2FA) basado en TOTP, construido en PHP con PDO y MySQL.

---

## Estructura General

```
Lab2FA_DS7/
├── index.php                   ← Punto de entrada raíz
└── Lab2FA/
    ├── index.php               ← Punto de entrada del módulo
    ├── config/
    │   └── database.php        ← Credenciales de conexión
    ├── classes/
    │   ├── Database.php        ← Conexión PDO
    │   ├── Auth.php            ← Login y verificación 2FA
    │   ├── Registro.php        ← Registro de nuevos usuarios
    │   ├── TOTP.php            ← Algoritmo TOTP (RFC 6238)
    │   ├── CSRF.php            ← Protección contra CSRF
    │   └── Sanitizador.php     ← Limpieza y validación de entradas
    ├── public/
    │   ├── login.php           ← Formulario de inicio de sesión
    │   ├── registro.php        ← Formulario de registro
    │   ├── verificar_2fa.php   ← Ingreso del código TOTP
    │   ├── dashboard.php       ← Panel principal (zona protegida)
    │   ├── logout.php          ← Cierre de sesión
    │   ├── hash.php            ← UI para generar/verificar hashes
    │   ├── ajax_check.php      ← Validación en tiempo real (usuario/correo)
    │   ├── ajax_hash.php       ← Endpoint AJAX para hashing bcrypt
    │   └── info.php            ← phpinfo() (diagnóstico)
    └── assets/
        ├── styles.css          ← Estilos globales
        └── scene.js            ← Escena animada de fondo
```

---

## Archivos de Entrada

### `index.php` (raíz)
Redirige inmediatamente a `Lab2FA/public/login.php`. Es el punto de entrada del dominio.

### `Lab2FA/index.php`
Redirige a `public/login.php`. Evita que se navegue directamente al directorio `Lab2FA/`.

---

## Configuración

### `config/database.php`
Retorna un array PHP con los parámetros de conexión: `host`, `database`, `user`, `password` y `charset`. Este archivo es requerido por `Database.php` al instanciar la conexión.

```php
return [
    'host'     => '127.0.0.1',
    'database' => 'Lab2FA',
    'user'     => 'user123',
    'password' => 'user123!',
    'charset'  => 'utf8mb4',
];
```

> **Importante:** Este archivo contiene credenciales sensibles y no debe subirse a repositorios públicos.

---

## Clases (Lógica de Negocio)

### `classes/Database.php`
**Lo más importante:** El método `conectar()` crea y retorna una instancia `PDO` con modo de error por excepciones (`ERRMODE_EXCEPTION`) y preparaciones reales (`EMULATE_PREPARES = false`), lo que previene inyecciones SQL por omisión.

### `classes/Auth.php`
**Lo más importante:** Implementa el flujo de autenticación en dos fases:

1. `login()` — Verifica usuario y contraseña con `password_verify()`. Si son correctas, **no** establece sesión completa sino una sesión intermedia (`pre_2fa`) y redirige al paso 2FA.
2. `verificar2FA()` — Valida el código TOTP. Solo si el código es correcto llama a `establecerSesion()`, que regenera el ID de sesión (`session_regenerate_id(true)`) y marca `$_SESSION['autenticado'] = 'SI'`.
3. `detectarAnomalia()` — Bloquea el intento si el mismo usuario o IP tiene 5 o más fallos en los últimos 5 minutos, registrando la anomalía en la tabla `intentos_login`.
4. `trazabilidad()` — Registra cada login exitoso en la tabla `trazabilidad_acciones`.

### `classes/Registro.php`
**Lo más importante:** El método público `registrar()` orquesta el proceso completo:
- Sanitiza todos los campos con `Sanitizador`.
- Valida reglas de negocio (campos requeridos, formato de correo, contraseñas coincidentes, mínimo 6 caracteres).
- Verifica unicidad de usuario y correo en la BD.
- Genera el hash bcrypt con `cost: 13` y un secreto TOTP aleatorio.
- Inserta el usuario y registra la acción en `trazabilidad_acciones`.
- Devuelve el secreto TOTP para que la vista genere el QR.

### `classes/TOTP.php`
**Lo más importante:** Implementación manual del algoritmo TOTP (RFC 6238) sin dependencias externas:

- `generarSecreto()` — Produce una clave Base32 aleatoria de 16 caracteres.
- `base32Decode()` — Decodifica el secreto de Base32 a bytes binarios.
- `generarCodigo()` — Aplica HMAC-SHA1 sobre el intervalo de tiempo actual (`floor(time() / 30)`) y extrae un OTP de 6 dígitos mediante truncado dinámico.
- `validarCodigo()` — Acepta códigos del intervalo anterior, actual y siguiente (`-1, 0, +1`) para tolerar desfases de reloj de hasta 30 segundos.
- `generarURLQR()` — Construye una URL `otpauth://totp/...` y llama a la API de `qrserver.com` para generar la imagen del código QR.

### `classes/CSRF.php`
**Lo más importante:** Protección contra ataques Cross-Site Request Forgery:

- `generarToken()` — Crea un token aleatorio de 32 bytes en hexadecimal y lo guarda en sesión. Si ya existe, reutiliza el mismo.
- `validarToken()` — Compara el token del formulario con el de la sesión usando `hash_equals()` (comparación en tiempo constante, resistente a ataques de temporización).

### `classes/Sanitizador.php`
**Lo más importante:** Centraliza la limpieza de entradas del usuario antes de usarlas:

| Método | Qué hace |
|---|---|
| `texto()` | `strip_tags` + `htmlspecialchars` con UTF-8 |
| `email()` | `FILTER_SANITIZE_EMAIL` |
| `usuario()` | Solo permite `[a-zA-Z0-9_]`, elimina todo lo demás |
| `entero()` | Extrae solo dígitos e signo |
| `booleano()` | `FILTER_VALIDATE_BOOLEAN` |
| `sexo()` | Solo acepta `'M'` o `'F'`, devuelve `'M'` por defecto |

---

## Páginas Públicas

### `public/login.php`
**Lo más importante:** Valida el token CSRF en cada POST antes de procesar credenciales. Si la contraseña es correcta, redirige a `verificar_2fa.php` sin establecer sesión completa aún. Muestra errores genéricos para no revelar si el usuario existe.

### `public/registro.php`
**Lo más importante:** Tras el registro exitoso, muestra el código QR generado con `TOTP::generarURLQR()` y la clave secreta en texto plano (solo en este momento, para que el usuario la guarde). Incluye validación en tiempo real con jQuery Validate + llamadas AJAX a `ajax_check.php` para usuario y correo.

### `public/verificar_2fa.php`
**Lo más importante:** Solo es accesible si `$_SESSION['pre_2fa']` está definido; de lo contrario redirige al login. Recibe el código TOTP de 6 dígitos y llama a `Auth::verificar2FA()`. Si es correcto, redirige al dashboard con sesión completa establecida.

### `public/dashboard.php`
**Lo más importante:** Verifica `$_SESSION['autenticado'] === 'SI'` al inicio; cualquier acceso sin esta sesión redirige al login. Muestra la insignia de "2FA verificado" si `$_SESSION['fase_qr_ok']` está activa.

### `public/logout.php`
**Lo más importante:** Llama a `session_destroy()` para invalidar completamente la sesión y redirige al login.

### `public/hash.php`
**Lo más importante:** Requiere sesión autenticada. Sirve la interfaz visual para generar hashes bcrypt y verificar si una contraseña coincide con un hash. Las operaciones reales se delegan a `ajax_hash.php` vía `fetch()`.

### `public/ajax_check.php`
**Lo más importante:** Endpoint JSON para la validación en tiempo real del formulario de registro. Recibe `campo` (`usuario` o `correo`) y `valor`, consulta la BD y devuelve `true` (disponible) o un string de error (ya en uso), que jQuery Validate interpreta directamente.

### `public/ajax_hash.php`
**Lo más importante:** Endpoint JSON protegido por sesión y CSRF. Acepta dos acciones:
- `generar` — Devuelve `password_hash($password, PASSWORD_BCRYPT, ['cost' => 13])`.
- `validar` — Devuelve si `password_verify($password, $hash)` es verdadero o falso.

### `public/info.php`
**Lo más importante:** Ejecuta `phpinfo()`. Útil para diagnóstico del entorno PHP. **No debe estar accesible en producción** ya que expone información sensible del servidor.

---

## Assets

### `assets/styles.css`
**Lo más importante:** Define variables CSS globales (`:root`) para colores, sombras y fondos, y aplica un sistema de diseño oscuro consistente en todas las vistas. Incluye los estilos de la escena animada (`#scene`, `.star`, `@keyframes twinkle`), los formularios, botones, alertas, el QR box y el dashboard.

### `assets/scene.js`
**Lo más importante:** Inyecta dinámicamente el elemento `#scene` con cielo y suelo al inicio del `<body>`, y crea 95 estrellas con posición, tamaño y duración de parpadeo aleatorios usando CSS custom properties (`--dur`). Se ejecuta como IIFE y no depende de ningún framework.

---

## Flujo de Autenticación (Resumen)

```
Usuario → login.php
         ↓ (credenciales correctas)
         $_SESSION['pre_2fa'] = true
         ↓
         verificar_2fa.php
         ↓ (código TOTP válido)
         $_SESSION['autenticado'] = 'SI'
         ↓
         dashboard.php  (zona protegida)
         ↓
         logout.php → session_destroy()
```
