# cuentas Specification

## Purpose
Permite al usuario registrar cuentas dentro de cada presupuesto para vincular transacciones y calcular saldos. Cada cuenta tiene su propia moneda (que puede diferir de la moneda base del presupuesto), tipo limitado (banco, efectivo o wallet), saldo inicial en centavos, y fecha de apertura. Las cuentas son la base del flujo transaccional del Change 7 y de la consolidación multi-moneda del Change 8.

## Requirements

### Requirement: Cuenta pertenece a un presupuesto

El sistema SHALL asociar cada cuenta a exactamente un presupuesto, y filtrar la vista por el presupuesto activo del usuario.

#### Scenario: Aislamiento por presupuesto activo

- **WHEN** un usuario con presupuesto activo "Personal" navega a `/cuentas`
- **THEN** ve únicamente las cuentas asociadas a "Personal"
- **AND** no ve cuentas de otros presupuestos del mismo usuario

#### Scenario: Sin presupuesto activo

- **WHEN** un usuario sin presupuesto activo intenta acceder a `/cuentas`
- **THEN** el sistema redirige a `/dashboard` con mensaje "Necesitas un presupuesto activo para gestionar cuentas"

#### Scenario: Auto-asignación al presupuesto activo

- **WHEN** un usuario crea una cuenta desde `/cuentas/create`
- **THEN** el sistema asigna automáticamente `presupuesto_id` al presupuesto activo del usuario
- **AND** el formulario no muestra selector de presupuesto

### Requirement: Moneda de la cuenta independiente y fija

El sistema SHALL permitir que la moneda de una cuenta sea distinta a la del presupuesto, pero fija una vez creada.

#### Scenario: Cuenta con moneda distinta al presupuesto

- **WHEN** un usuario crea una cuenta "Binance USDT" con moneda USDT dentro del presupuesto "Personal" (moneda base BOB)
- **THEN** el sistema acepta la operación
- **AND** la cuenta queda registrada con moneda USDT

#### Scenario: Moneda inactiva rechazada al crear

- **WHEN** un usuario intenta crear una cuenta con una moneda desactivada
- **THEN** el sistema rechaza con error "La moneda seleccionada no está activa"

#### Scenario: Intento de cambiar moneda en update

- **WHEN** el dueño envía PUT `/cuentas/{id}` con moneda_codigo distinta a la actual
- **THEN** el sistema ignora silenciosamente el cambio de moneda
- **AND** actualiza los demás campos válidos
- **AND** la cuenta mantiene su moneda original

#### Scenario: UI de edición muestra moneda como readonly

- **WHEN** un usuario abre `/cuentas/{id}/edit`
- **THEN** el campo moneda se muestra como badge readonly con tooltip "No se puede cambiar la moneda después de crear la cuenta"

### Requirement: Tipos de cuenta limitados

El sistema SHALL restringir el tipo de cuenta a exactamente 3 valores: banco, efectivo, wallet.

#### Scenario: Tipo válido aceptado

- **WHEN** un usuario crea una cuenta con tipo "banco"
- **THEN** el sistema la registra correctamente

#### Scenario: Tipo inválido rechazado

- **WHEN** un usuario envía un tipo distinto a los 3 permitidos (ej: "credito", "prestamo")
- **THEN** el sistema rechaza con error de validación

### Requirement: Saldo inicial en centavos

El sistema SHALL almacenar el saldo inicial de cada cuenta en centavos como bigInteger.

#### Scenario: Creación con saldo positivo

- **WHEN** un usuario crea una cuenta con saldo inicial Bs 2.000
- **THEN** el sistema almacena `saldo_inicial_centavos = 200000`

#### Scenario: Creación con saldo cero

- **WHEN** un usuario crea una cuenta con saldo inicial 0
- **THEN** el sistema lo acepta (cuenta nueva sin fondos)

#### Scenario: Saldo negativo rechazado

- **WHEN** un usuario envía un saldo inicial negativo
- **THEN** el sistema rechaza con error "El saldo inicial no puede ser negativo"

### Requirement: Saldo actual computed (no persistido)

El sistema SHALL exponer un accessor `saldo_actual_centavos` en el modelo Cuenta que calcule el saldo real sumando el saldo inicial con las transacciones vinculadas.

#### Scenario: Sin transacciones (Change 4)

- **WHEN** se consulta el saldo actual de una cuenta antes de la existencia de la tabla transacciones
- **THEN** el accessor retorna `saldo_inicial_centavos`

#### Scenario: Extensión futura en Change 7

- **WHEN** la tabla transacciones exista (Change 7)
- **THEN** el accessor extenderá su cálculo con las transacciones asociadas
- **AND** este spec se actualizará en Change 7 con los nuevos casos

### Requirement: Fecha de apertura obligatoria y no futura

El sistema SHALL requerir una fecha de apertura para cada cuenta, y validar que no sea futura.

#### Scenario: Fecha pasada válida

- **WHEN** un usuario crea una cuenta con fecha_apertura de hace 6 meses
- **THEN** el sistema la acepta

#### Scenario: Fecha futura rechazada

- **WHEN** un usuario envía fecha_apertura posterior a hoy
- **THEN** el sistema rechaza con error "La fecha de apertura no puede ser futura"

### Requirement: Nombre único dentro del presupuesto (case-insensitive)

El sistema SHALL rechazar nombres de cuenta duplicados dentro del mismo presupuesto, ignorando diferencias de mayúsculas/minúsculas.

#### Scenario: Duplicado en mismo presupuesto rechazado

- **WHEN** un usuario intenta crear "BNB Checking" cuando ya existe "bnb checking" en el mismo presupuesto
- **THEN** el sistema rechaza con error "Ya existe una cuenta con ese nombre en este presupuesto"

#### Scenario: Mismo nombre en distinto presupuesto permitido

- **WHEN** un usuario crea "Efectivo" en presupuesto "Personal" y luego "Efectivo" en presupuesto "Freelance USD"
- **THEN** el sistema acepta ambas

### Requirement: Soft delete

El sistema SHALL usar soft delete para las cuentas, preservando el histórico para reportes.

#### Scenario: Eliminación exitosa

- **WHEN** el dueño elimina una cuenta desde `/cuentas`
- **THEN** el sistema setea `deleted_at` sin borrar el registro
- **AND** la cuenta desaparece de la UI y de dropdowns
- **AND** las transacciones históricas asociadas (Change 7) permanecen consultables

#### Scenario: Cuenta soft-deleted no aparece en listados

- **WHEN** un usuario navega a `/cuentas` después de eliminar una cuenta
- **THEN** la cuenta eliminada no aparece en la lista

### Requirement: Autorización por dueño del presupuesto

El sistema SHALL restringir edición y eliminación de cuentas al dueño del presupuesto al que pertenecen.

#### Scenario: Intento de editar cuenta ajena

- **WHEN** un usuario intenta PUT `/cuentas/{id}` sobre una cuenta cuyo presupuesto pertenece a otro usuario
- **THEN** el sistema retorna 403 Forbidden

#### Scenario: Intento de eliminar cuenta ajena

- **WHEN** un usuario intenta DELETE `/cuentas/{id}` sobre una cuenta ajena
- **THEN** el sistema retorna 403 Forbidden

### Requirement: Número de referencia opcional

El sistema SHALL permitir un identificador libre opcional (últimos 4 dígitos, alias de wallet, nota) para distinguir cuentas similares.

#### Scenario: Cuenta con referencia

- **WHEN** un usuario crea "BNB Checking" con referencia "**4417"
- **THEN** la referencia se almacena y se muestra debajo del nombre en la UI

#### Scenario: Cuenta sin referencia

- **WHEN** un usuario crea una cuenta sin ingresar referencia
- **THEN** el sistema acepta el registro (campo nullable)
