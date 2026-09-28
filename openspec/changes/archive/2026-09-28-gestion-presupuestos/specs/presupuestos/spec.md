## Purpose

Permite a cada usuario de MyNAB gestionar múltiples presupuestos independientes (personal, freelance, negocio, etc.), elegir cuál está viendo activamente, y personalizarlos visualmente con color e icono. Es la base sobre la que descansan cuentas, categorías, beneficiarios y transacciones.

## ADDED Requirements

### Requirement: Crear presupuesto

El sistema SHALL permitir a un usuario autenticado crear un presupuesto proporcionando nombre, color e icono; la descripción es opcional.

#### Scenario: Creación exitosa

- **WHEN** un usuario autenticado envía POST `/presupuestos` con nombre "Personal", color "#22c55e", icono "wallet"
- **THEN** el sistema crea el registro en la tabla `presupuestos` con `user_id` del usuario autenticado
- **AND** redirige a `/presupuestos` con flash success

#### Scenario: Primer presupuesto se marca como activo

- **WHEN** un usuario sin presupuestos crea su primer presupuesto
- **THEN** el sistema actualiza `users.presupuesto_activo_id` con el ID del nuevo presupuesto

#### Scenario: Nombre duplicado para el mismo usuario

- **WHEN** un usuario con presupuesto "Personal" intenta crear otro con nombre "PERSONAL" (case-insensitive)
- **THEN** el sistema muestra error de validación "Ya tienes un presupuesto con ese nombre"
- **AND** no crea el registro

#### Scenario: Nombre igual para usuarios distintos

- **WHEN** dos usuarios distintos cada uno crea un presupuesto llamado "Personal"
- **THEN** ambos registros se crean exitosamente

#### Scenario: Color con formato inválido

- **WHEN** un usuario envía color "verde" (no hex)
- **THEN** el sistema muestra error "El color debe ser un hexadecimal válido (#RRGGBB)"
- **AND** no crea el registro

### Requirement: Listar presupuestos propios

El sistema SHALL mostrar al usuario autenticado únicamente sus propios presupuestos no eliminados.

#### Scenario: Usuario ve solo sus presupuestos

- **WHEN** un usuario con 2 presupuestos activos visita `/presupuestos`
- **THEN** el sistema muestra esos 2 presupuestos
- **AND** no muestra presupuestos de otros usuarios ni los eliminados (soft-deleted)

#### Scenario: Usuario sin presupuestos

- **WHEN** un usuario sin presupuestos visita `/presupuestos`
- **THEN** el sistema muestra un empty state con mensaje "Aún no tienes presupuestos" y un botón "Crear presupuesto"

### Requirement: Editar presupuesto propio

El sistema SHALL permitir a un usuario autenticado editar los datos de sus propios presupuestos.

#### Scenario: Edición exitosa

- **WHEN** el dueño de un presupuesto envía PUT `/presupuestos/{id}` con nombre modificado
- **THEN** el sistema actualiza el registro
- **AND** redirige a `/presupuestos` con flash success

#### Scenario: Intento de editar presupuesto ajeno

- **WHEN** un usuario intenta editar un presupuesto cuyo `user_id` no coincide con el suyo
- **THEN** el sistema retorna 403 Forbidden
- **AND** no modifica el registro

### Requirement: Eliminar presupuesto (soft delete)

El sistema SHALL permitir eliminar presupuestos vía soft delete, preservando el historial en base de datos.

#### Scenario: Eliminación básica

- **WHEN** el dueño envía DELETE `/presupuestos/{id}`
- **THEN** el sistema marca `deleted_at` en el registro
- **AND** el presupuesto no aparece más en la lista ni en el selector
- **AND** las transacciones/cuentas asociadas quedan en base de datos

#### Scenario: Eliminación del presupuesto activo

- **WHEN** el dueño elimina el presupuesto que estaba marcado como activo
- **AND** el usuario tiene otros presupuestos disponibles
- **THEN** el sistema reasigna `presupuesto_activo_id` al siguiente presupuesto disponible del usuario

#### Scenario: Eliminación del último presupuesto

- **WHEN** el dueño elimina su único presupuesto restante
- **THEN** `presupuesto_activo_id` queda en null
- **AND** el sistema redirige al dashboard con CTA para crear uno nuevo

#### Scenario: Intento de eliminar presupuesto ajeno

- **WHEN** un usuario intenta eliminar un presupuesto que no le pertenece
- **THEN** el sistema retorna 403 Forbidden

### Requirement: Selección de presupuesto activo

El sistema SHALL permitir al usuario cambiar cuál de sus presupuestos está viendo activamente.

#### Scenario: Cambio exitoso desde el navbar

- **WHEN** un usuario con múltiples presupuestos selecciona uno diferente en el selector del navbar (POST `/presupuestos/{id}/seleccionar`)
- **THEN** el sistema actualiza `users.presupuesto_activo_id`
- **AND** al recargar la app, el selector muestra el nuevo presupuesto activo

#### Scenario: Intento de seleccionar presupuesto ajeno

- **WHEN** un usuario intenta seleccionar como activo un presupuesto cuyo `user_id` no es el suyo
- **THEN** el sistema retorna 403 Forbidden
- **AND** no modifica `presupuesto_activo_id`

### Requirement: Selector visible en navbar

El sistema SHALL mostrar en el navbar de todas las páginas autenticadas un selector con el presupuesto activo y la posibilidad de cambiar a otros.

#### Scenario: Usuario con presupuesto activo

- **WHEN** un usuario autenticado con al menos un presupuesto activo carga cualquier página
- **THEN** el navbar muestra un dropdown con nombre + color + moneda base del presupuesto activo (ej: "Personal · BOB")

#### Scenario: Usuario sin presupuestos

- **WHEN** un usuario autenticado sin presupuestos carga cualquier página
- **THEN** el navbar muestra un CTA "Crear presupuesto" que redirige a `/presupuestos/create`

### Requirement: Redirección inicial sin presupuestos

El sistema SHALL guiar al usuario recién registrado a crear su primer presupuesto.

#### Scenario: Usuario nuevo al dashboard

- **WHEN** un usuario recién registrado (sin presupuestos) llega a `/dashboard`
- **THEN** el sistema muestra un mensaje "Crea tu primer presupuesto para empezar" con un botón que redirige a `/presupuestos/create`