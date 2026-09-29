## Purpose

Permite a cada usuario mantener sus propios tipos de cambio manuales entre pares de monedas, bidireccionales (compra y venta como registros separados), versionados por fecha de vigencia, y consultarlos de forma histórica para consolidar reportes en la moneda base de cualquier presupuesto.

## ADDED Requirements

### Requirement: Tipos de cambio son por usuario

El sistema SHALL asociar cada tipo de cambio al usuario que lo creó y no exponerlo a otros usuarios.

#### Scenario: Aislamiento entre usuarios

- **WHEN** el usuario María consulta `/tipos-cambio`
- **THEN** ve únicamente sus propios tipos de cambio
- **AND** no ve los tipos de cambio de otros usuarios

### Requirement: Direccionalidad explícita

El sistema SHALL tratar los tipos de cambio como direccionales: cada par (origen, destino) es un registro independiente.

#### Scenario: Par bidireccional almacenado como 2 registros

- **WHEN** un usuario tiene registrado BOB→USDT con tasa 0.14 y USDT→BOB con tasa 7.10 en la misma fecha
- **THEN** ambas tasas coexisten como registros distintos en la tabla
- **AND** el sistema no calcula una a partir de la otra

#### Scenario: Origen y destino no pueden ser iguales

- **WHEN** un usuario intenta crear un tipo de cambio con moneda_origen y moneda_destino iguales
- **THEN** el sistema rechaza con error de validación "La moneda de origen y destino deben ser diferentes"

### Requirement: Fecha de vigencia obligatoria

El sistema SHALL requerir una fecha de vigencia para cada tipo de cambio y usarla para resolver tasas históricas.

#### Scenario: Creación con fecha pasada válida

- **WHEN** un usuario crea un tipo de cambio con fecha del mes pasado
- **THEN** el sistema lo acepta

#### Scenario: Rechazo de fecha futura

- **WHEN** un usuario envía fecha posterior a hoy
- **THEN** el sistema rechaza con error "La fecha no puede ser futura"

#### Scenario: Unicidad por par y fecha

- **WHEN** un usuario intenta crear un segundo tipo de cambio BOB→USDT con la misma fecha que uno existente
- **THEN** el sistema rechaza con error "Ya existe un tipo de cambio para este par en esta fecha"

### Requirement: Resolución de tasa vigente en una fecha dada

El sistema SHALL proveer un mecanismo que, dado un usuario, un par de monedas y una fecha, retorna la tasa aplicable en esa fecha.

#### Scenario: Tasa exacta en la fecha

- **WHEN** se consulta la tasa de BOB→USDT del usuario María en fecha 15/09/2026
- **AND** existe un tipo de cambio con exactamente esa fecha
- **THEN** el sistema retorna esa tasa con `esExtrapolada=false`

#### Scenario: Tasa histórica más reciente

- **WHEN** se consulta la tasa en fecha 20/09/2026
- **AND** el registro más reciente para ese par tiene fecha 15/09/2026
- **THEN** el sistema retorna la tasa del 15/09/2026 con `esExtrapolada=false`

#### Scenario: Fecha anterior a cualquier registro

- **WHEN** se consulta la tasa en fecha 01/01/2020
- **AND** el registro más antiguo para ese par tiene fecha 15/09/2026
- **THEN** el sistema retorna la tasa del 15/09/2026 con `esExtrapolada=true`

#### Scenario: Sin tasa disponible para el par

- **WHEN** se consulta la tasa de ARS→CHF para un usuario que nunca registró ese par
- **THEN** el sistema retorna null

#### Scenario: Identidad (origen == destino)

- **WHEN** se consulta la tasa de USD→USD en cualquier fecha
- **THEN** el sistema retorna 1.0 con `esExtrapolada=false` sin consultar la BD

### Requirement: Autorización estricta

El sistema SHALL restringir edición y borrado de tipos de cambio a su dueño.

#### Scenario: Intento de editar tipo de cambio ajeno

- **WHEN** un usuario intenta PUT `/tipos-cambio/{id}` sobre un registro de otro usuario
- **THEN** el sistema retorna 403 Forbidden

#### Scenario: Intento de borrar tipo de cambio ajeno

- **WHEN** un usuario intenta DELETE `/tipos-cambio/{id}` sobre un registro de otro usuario
- **THEN** el sistema retorna 403 Forbidden

### Requirement: Inmutabilidad de origen y destino

El sistema SHALL rechazar cambios de moneda origen o destino en operaciones de update, ya que estos campos son la identidad del registro.

#### Scenario: Update ignora cambio de moneda origen

- **WHEN** un usuario envía PUT `/tipos-cambio/{id}` con moneda_origen distinta a la actual
- **THEN** el sistema actualiza solo tasa y fecha, ignorando moneda_origen y moneda_destino