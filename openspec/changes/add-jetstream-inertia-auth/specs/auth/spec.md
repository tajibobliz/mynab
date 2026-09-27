## Purpose

Provee registro, inicio de sesión, cierre de sesión y protección de rutas para los usuarios del sistema MyNAB, de modo que cada usuario acceda únicamente a sus propios presupuestos y datos financieros.

## ADDED Requirements

### Requirement: Registro de usuario

El sistema SHALL permitir a un visitante crear una cuenta proporcionando nombre, email único y contraseña de al menos 8 caracteres.

#### Scenario: Registro exitoso

- **WHEN** un visitante en `/register` envía nombre "María Rojas", email "maria@example.com" y contraseña válida
- **THEN** el sistema crea un registro en la tabla `users`, autentica al visitante y lo redirige a `/dashboard`

#### Scenario: Email duplicado

- **WHEN** un visitante intenta registrarse con un email ya existente
- **THEN** el sistema muestra error de validación y no crea el registro

#### Scenario: Contraseña débil

- **WHEN** un visitante envía una contraseña de menos de 8 caracteres
- **THEN** el sistema muestra error de validación y no crea el registro

### Requirement: Inicio de sesión

El sistema SHALL permitir a un usuario registrado autenticarse con email y contraseña.

#### Scenario: Login exitoso

- **WHEN** un usuario registrado ingresa credenciales correctas en `/login`
- **THEN** el sistema autentica al usuario y lo redirige a `/dashboard`

#### Scenario: Credenciales inválidas

- **WHEN** un usuario ingresa una contraseña incorrecta
- **THEN** el sistema muestra error de autenticación y mantiene al usuario en `/login`

### Requirement: Cierre de sesión

El sistema SHALL permitir a un usuario autenticado cerrar su sesión.

#### Scenario: Logout exitoso

- **WHEN** un usuario autenticado activa la acción "Cerrar sesión" desde el navbar
- **THEN** el sistema destruye la sesión y redirige al usuario a `/`

### Requirement: Protección de rutas autenticadas

El sistema SHALL restringir el acceso a rutas internas a usuarios autenticados.

#### Scenario: Acceso no autenticado a /dashboard

- **WHEN** un visitante no autenticado intenta acceder a `/dashboard`
- **THEN** el sistema lo redirige a `/login`