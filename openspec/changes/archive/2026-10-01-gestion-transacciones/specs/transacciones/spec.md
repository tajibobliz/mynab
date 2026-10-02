## Purpose

Permite al usuario registrar transacciones (gastos, ingresos y transferencias) asociadas a sus cuentas, categorías y beneficiarios dentro de cada presupuesto. Las transacciones son el núcleo transaccional del sistema: toda otra entidad existe para dar contexto a este registro. Incluye soporte para split por categorías, campo monto tipo calculadora, timestamp con hora y multi-moneda con conversión automática a la moneda base del presupuesto.

## ADDED Requirements

### Requirement: Tres tipos de transacción

El sistema SHALL soportar tres tipos: outflow (gasto), inflow (ingreso) y transfer (transferencia entre cuentas del mismo presupuesto).

#### Scenario: Outflow registra un gasto

- **WHEN** el usuario crea una transacción tipo outflow con cuenta y categoría
- **THEN** el saldo de la cuenta disminuye
- **AND** el activity de la categoría aumenta

#### Scenario: Inflow registra un ingreso

- **WHEN** el usuario crea una transacción tipo inflow con cuenta y beneficiario (fuente)
- **THEN** el saldo de la cuenta aumenta
- **AND** el monto va a Ready to Assign (Change 8)
- **AND** la transacción no tiene categoría

#### Scenario: Transfer mueve dinero entre cuentas

- **WHEN** el usuario crea una transacción tipo transfer con cuenta origen y cuenta destino
- **THEN** el saldo de la cuenta origen disminuye
- **AND** el saldo de la cuenta destino aumenta
- **AND** no afecta ninguna categoría

### Requirement: Validación condicional por tipo

El sistema SHALL validar las FKs según el tipo de transacción.

#### Scenario: Outflow sin categoría rechazado (si no es split)

- **WHEN** un usuario intenta crear outflow sin categoria_id ni es_split
- **THEN** el sistema rechaza con mensaje de validación

#### Scenario: Inflow con categoría rechazado

- **WHEN** un usuario intenta crear inflow con categoria_id
- **THEN** el sistema rechaza (inflows van a Ready to Assign, no a categoría)

#### Scenario: Transfer con misma cuenta origen y destino rechazado

- **WHEN** un usuario intenta crear transfer con cuenta_id = cuenta_destino_id
- **THEN** el sistema rechaza con mensaje "La cuenta destino debe ser distinta a la origen"

### Requirement: Split de transacción

El sistema SHALL permitir dividir una transacción outflow en múltiples categorías.

#### Scenario: Split suma correcta

- **WHEN** un usuario crea outflow Bs 320 con split [Comida básica Bs 250, Ropa Bs 70]
- **THEN** el sistema acepta y crea 1 transacción padre + 2 registros en transacciones_split

#### Scenario: Split suma incorrecta rechazado

- **WHEN** un usuario envía split cuya suma no coincide con el monto del padre
- **THEN** el sistema rechaza con mensaje "La suma de las líneas (X) no coincide con el total (Y)"

#### Scenario: Split con una sola línea rechazado

- **WHEN** un usuario envía split con menos de 2 líneas
- **THEN** el sistema rechaza (split requiere mínimo 2 categorías)

### Requirement: Multi-moneda con conversión implícita

El sistema SHALL registrar cada transacción en la moneda de la cuenta y almacenar también su equivalente en la moneda base del presupuesto.

#### Scenario: Transacción en moneda distinta a la base

- **WHEN** un usuario crea outflow USD 100 en cuenta Binance (USDT) con tasa 1 USDT = 6.96 BOB vigente a la fecha
- **THEN** monto_centavos = 10000 (centavos USDT)
- **AND** monto_moneda_base_centavos = 69600 (centavos BOB)
- **AND** tasa_cambio_aplicada = 6.96

#### Scenario: Transacción en moneda base

- **WHEN** un usuario crea outflow BOB 100 en cuenta BNB (BOB), moneda base BOB
- **THEN** monto_centavos = 10000
- **AND** monto_moneda_base_centavos = 10000
- **AND** tasa_cambio_aplicada = null (identity)

### Requirement: Campo monto tipo calculadora

El sistema SHALL aceptar expresiones aritméticas en el campo monto de la UI y evaluarlas antes de persistir.

#### Scenario: Expresión válida evaluada

- **WHEN** un usuario tipea "50+30*2" en el campo monto
- **THEN** la UI evalúa a 110 al perder foco
- **AND** muestra "= Bs 110.00"
- **AND** envía 11000 centavos al backend

#### Scenario: Expresión inválida bloqueada

- **WHEN** un usuario tipea "50+" (expresión incompleta)
- **THEN** la UI muestra borde rojo + mensaje "Expresión inválida"
- **AND** el botón de guardar queda deshabilitado

### Requirement: Timestamp con hora

El sistema SHALL registrar fecha y hora exactas de cada transacción.

#### Scenario: Fecha + hora capturada

- **WHEN** un usuario crea transacción con fecha 2026-10-01 y hora 14:30
- **THEN** fecha_hora se guarda como timestamp 2026-10-01T14:30:00-04:00

#### Scenario: Default now()

- **WHEN** un usuario abre el formulario de nueva transacción
- **THEN** fecha_hora se precarga con el timestamp actual

#### Scenario: Fecha muy futura rechazada

- **WHEN** un usuario envía fecha_hora > now() + 1 día
- **THEN** el sistema rechaza (probable error de tipeo)

### Requirement: Autorización por dueño del presupuesto

El sistema SHALL restringir todas las operaciones al dueño del presupuesto.

#### Scenario: Intento ajeno rechazado

- **WHEN** un usuario intenta editar o eliminar una transacción de otro usuario
- **THEN** el sistema retorna 403

#### Scenario: FKs cruzadas bloqueadas

- **WHEN** un usuario intenta crear transacción con cuenta_id/categoria_id/beneficiario_id de otro presupuesto
- **THEN** el sistema rechaza en validación (previene IDOR)

### Requirement: Soft delete con cascada a splits

El sistema SHALL preservar el historial via soft delete.

#### Scenario: Eliminación de transacción con splits

- **WHEN** el usuario elimina transacción padre con 2 splits
- **THEN** deleted_at se setea en el padre y en los 2 splits
- **AND** ninguno aparece en listados activos

### Requirement: Index con filtros

El sistema SHALL permitir filtrar el listado de transacciones.

#### Scenario: Filtro por tipo

- **WHEN** un usuario navega a /transacciones?tipo=outflow
- **THEN** solo ve outflows

#### Scenario: Filtro por rango de fechas

- **WHEN** un usuario filtra por desde=2026-10-01&hasta=2026-10-15
- **THEN** solo ve transacciones en ese rango

#### Scenario: Filtros combinables

- **WHEN** un usuario filtra por tipo=outflow&categoria=5
- **THEN** solo ve outflows de categoría 5

### Requirement: Saldo de cuenta refleja transacciones

El sistema SHALL calcular el saldo actual de cada cuenta sumando saldo inicial + inflows - outflows - transfers salientes + transfers entrantes.

#### Scenario: Saldo actualizado tras outflow

- **GIVEN** cuenta BNB con saldo inicial Bs 2000
- **WHEN** se crea outflow de Bs 500
- **THEN** saldo_actual = Bs 1500

#### Scenario: Transfer actualiza ambas cuentas

- **GIVEN** cuenta BNB Bs 2000, cuenta Efectivo Bs 300
- **WHEN** se crea transfer Bs 100 de BNB a Efectivo
- **THEN** BNB saldo = Bs 1900, Efectivo saldo = Bs 400