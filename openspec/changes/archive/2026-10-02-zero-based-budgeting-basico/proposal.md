## Why

Changes 1-7 construyeron toda la infraestructura transaccional del sistema:
- Change 2: presupuestos
- Change 4: cuentas con saldos
- Change 5: categorías (los "sobres")
- Change 7: transacciones con split, multi-moneda, timestamp

Pero sin este change, el sistema solo **registra** movimientos. YNAB es **presupuestar hacia adelante**: cada peso tiene un trabajo asignado antes de gastarse.

Este change implementa el núcleo filosófico de YNAB — el método del sobre digital — permitiendo:
- Asignar dinero a cada categoría mes por mes
- Ver Ready to Assign (cuánto falta por asignar)
- Ver Activity (cuánto se gastó de cada sobre este mes)
- Ver Available (cuánto queda disponible en cada sobre)
- Rollover automático al siguiente mes

Sin este change, el proyecto está incompleto como YNAB-clone.

## What Changes

- Tabla `asignaciones` con `presupuesto_id`, `categoria_id`, `año`, `mes`, `monto_centavos` (en moneda base del presupuesto)
- Columna agregada a `transacciones_split`: `monto_moneda_base_centavos` (modificación cruzada mínima a Change 7, documentada)
- Backfill: migración para calcular `monto_moneda_base_centavos` de los splits existentes del seeder usando `CalculadoraTransaccionService`
- Service `PresupuestoMensualService` con cálculos:
  * `calcularReadyToAssign(presupuesto, año, mes)`: inflows acumulados - asignaciones acumuladas
  * `calcularActivity(categoria, año, mes)`: outflows + splits del mes y categoría
  * `calcularAvailable(categoria, año, mes)`: assignments acumulados - activity acumulada
- CRUD de asignaciones con update inline (click → tipea → blur guarda)
- Vista `/presupuesto-mensual` con:
  * Selector de mes con navegación anterior/siguiente
  * Ready to Assign arriba grande (verde si = 0, amarillo si > 0, rojo si < 0)
  * Tabla de categorías agrupadas por grupo con 3 columnas Assigned/Activity/Available
  * Edit inline en Assigned
- Dashboard actualizado:
  * Ready to Assign del mes actual
  * Top 5 categorías con menor Available
  * Links a /presupuesto-mensual y /transacciones
- NavLink "Presupuesto mensual" prominente en navbar
- Seeder: asignaciones de María para octubre 2026 (demuestra flujo zero-based en demo)

## Capabilities

### New Capabilities
- `presupuesto-mensual`: zero-based budgeting con asignaciones por categoría/mes, Ready to Assign, Activity y Available con rollover.

### Modified Capabilities
- `transacciones`: agregada columna `monto_moneda_base_centavos` en `transacciones_split` para queries agregadas en moneda base.

## Impact

- Modificación cruzada mínima a Change 7 (una columna agregada, documentada)
- Backfill de datos existentes del seeder
- Dashboard rediseñado parcialmente
- Fin de Fase 1: proyecto completo como YNAB-clone funcional