## Why

Las transacciones son el núcleo transaccional de MyNAB. Todo lo construido en Changes 1-6 (presupuestos, cuentas, categorías, beneficiarios) existe para dar contexto al registro de dinero que entra y sale. Sin este change:
- Change 8 (zero-based budgeting) no tiene de dónde calcular "Ready to Assign", "Activity" o "Available"
- Los seeders muestran datos estáticos sin movimiento
- El usuario no puede responder "¿cuánto gasté este mes?" ni "¿cuánto me queda del presupuesto de alimentación?"

El docente aprobó explícitamente los siguientes puntos para Fase 1:
- **3 tipos de transacción**: outflow (gasto), inflow (ingreso), transfer (transferencia entre cuentas)
- **Split**: una transacción puede dividirse en N categorías (ej: compra en supermercado = Bs 500 comida + Bs 200 ropa)
- **Campo monto tipo calculadora**: acepta expresiones aritméticas (`50+30*2`)
- **Timestamp con hora** (no solo fecha): registrar cuándo exactamente ocurrió
- **UX importa**: pesa en la nota, forma parte del alcance

Fuera de alcance (confirmado por docente para esta entrega):
- Conciliación (cleared/reconciled)
- Tarjetas de crédito con lógica especial
- Targets/metas por categoría (Fase 2)
- Scheduled transactions (Fase 2)
- Reportes avanzados (Fase 2)
- Age of Money (Fase 2)

## What Changes

- Tabla `transacciones` con FKs a `cuentas`, `categorias` (nullable), `beneficiarios` (nullable), `cuenta_destino_id` (nullable para transfers), tipo enum, monto en moneda de la cuenta + monto convertido a moneda base del presupuesto, timestamp con hora, notas, flag `es_split`, soft delete
- Tabla `transacciones_split` con FK a `transacciones` (padre), `categoria_id`, `monto_centavos`, `notas`, soft delete
- Hook `deleting()` en `Transaccion` soft-elimina los splits en cascada
- CRUD completo `/transacciones` con Index (lista con filtros), Create (formulario denso con split toggle), Edit, Destroy
- Validación condicional por tipo:
  - `outflow`: requiere `categoria_id`, `beneficiario_id` opcional, prohíbe `cuenta_destino_id`
  - `inflow`: requiere `beneficiario_id`, prohíbe `categoria_id` y `cuenta_destino_id` (los inflows van a "Ready to Assign")
  - `transfer`: requiere `cuenta_destino_id` (distinta de `cuenta_id`), prohíbe `categoria_id` y `beneficiario_id`
  - split: `categoria_id` del padre nulo, suma de hijos debe igualar al padre
- Validación de FKs contra presupuesto activo (lección Changes 5-6): cada FK se valida con `Rule::exists('tabla', 'id')->where('presupuesto_id', $activo)`
- Campo monto con componente `MontoCalculadora.vue`: input que acepta expresiones aritméticas evaluadas onBlur, muestra el resultado
- Conversión implícita a moneda base del presupuesto: al crear, usa `TasaCambioService` con tasa de la `fecha_hora` para calcular `monto_moneda_base_centavos`
- Index con filtros: por tipo, por cuenta, por categoría, por beneficiario, rango de fechas
- NavLink "Transacciones" en AppLayout
- Nueva prop `transaccionesRecientesDelPresupuestoActivo` en `HandleInertiaRequests` (últimas 20) para Dashboard futuro
- Seeder de María con ~18 transacciones realistas del escenario oficial

## Capabilities

### New Capabilities
- `transacciones`: outflow/inflow/transfer por cuenta con split, calculadora, timestamp y multi-moneda.

### Modified Capabilities
- Ninguna.

## Impact

- Change 8 (`zero-based-budgeting-basico`) usará `transacciones` + `transacciones_split` para calcular Ready to Assign, Activity y Available por categoría/mes.
- Sin cambios de esquema en tablas existentes.
- `TasaCambioService` de Change 3 ya está listo para conversión, se consume aquí.
- `BeneficiarioController` del Change 6, `CategoriaController` del Change 5, `CuentaController` del Change 4 permanecen intactos.