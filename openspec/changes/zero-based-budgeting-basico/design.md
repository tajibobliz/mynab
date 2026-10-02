# Design — Zero-Based Budgeting Básico

## Decisión 1: Tabla asignaciones

**Estructura:**
id
presupuesto_id (FK presupuestos.id, cascadeOnDelete)
categoria_id (FK categorias.id, restrictOnDelete)
año (unsignedSmallInteger)
mes (unsignedTinyInteger, 1-12)
monto_centavos (bigInteger)
timestamps
UNIQUE(presupuesto_id, categoria_id, año, mes)

**Decisiones:**
- `monto_centavos` siempre en moneda base del presupuesto. Categorías no tienen moneda.
- UNIQUE constraint evita filas duplicadas. Update directo (no append).
- NO soft delete: borrar significa "no asigné nada" (equivalente a monto 0).
- Sin FK a users: el ownership se resuelve via presupuesto.

**Inflector trap:** `Str::plural('asignacion') = 'asignacions'` y `Str::singular('asignaciones') = 'asignacione'`. **Modelo requiere `$table='asignaciones'`** y **ruta requiere `->parameters(['asignaciones' => 'asignacion'])`**. Patrón consolidado desde Change 7.

## Decisión 2: Columna agregada a transacciones_split

**Contexto:** Change 7 guardó `transacciones_split.monto_centavos` en moneda de la cuenta del padre. Para calcular Activity por categoría en moneda base, hay que convertir.

**Decisión:** agregar columna `monto_moneda_base_centavos` a `transacciones_split`, análoga a la de `transacciones`. Migración + backfill del seeder.

**Modificación cruzada mínima a Change 7:**
- `TransaccionController::store()` y `::update()`: al crear splits, calcular `monto_moneda_base_centavos` usando `CalculadoraTransaccionService::resolverMontoMonedaBase()`
- `TransaccionSplit::$fillable`: agregar `monto_moneda_base_centavos`
- Documentar modificación en el commit

**Alternativa descartada:** convertir en runtime en `PresupuestoMensualService`. Habría requerido query por split + llamada al servicio por cada uno. N+1 severo para ~50-200 transacciones/mes con splits.

## Decisión 3: Ready to Assign acumulado histórico

**Fórmula correcta YNAB:**
RTA (presupuesto P, mes M) =
SUM(inflow.monto_moneda_base_centavos) WHERE presupuesto=P AND fecha_hora <= fin_mes_M

SUM(asignacion.monto_centavos) WHERE presupuesto=P AND (año,mes) <= M

**Por qué acumulado:** si María asignó mal en septiembre y sobró Bs 500, esos 500 están disponibles en octubre. Rollover automático.

## Decisión 4: Available por categoría acumulado

**Fórmula:**
Available (categoria C, mes M) =
SUM(asignacion.monto WHERE categoria=C AND (año,mes) <= M)

SUM(activity de C hasta M)

Donde Activity acumulada incluye outflows y splits convertidos a moneda base.

**Signo:** Available puede ser negativo (overspent). UI lo muestra en rojo.

## Decisión 5: PresupuestoMensualService

**Responsabilidades:**
- `calcularReadyToAssign(Presupuesto $p, int $año, int $mes): int`
- `calcularActivity(Categoria $c, int $año, int $mes): int`
- `calcularAvailable(Categoria $c, int $año, int $mes): int`
- `obtenerVistaMensual(Presupuesto $p, int $año, int $mes): array` → retorna estructura completa para la vista

**Rendimiento:**
- `obtenerVistaMensual` optimizado: 3-4 queries max usando agregaciones
- Zero N+1 incluso con 50 categorías
- Postgres `SUM(bigint) → numeric → string PHP`: cast `(int)` siempre

**Casting PHP:** aprendizaje consolidado de Change 7. Todos los `->sum('monto_centavos')` se castean a `(int)` antes de operaciones aritméticas.

## Decisión 6: Edit inline de Assigned

**UX:**
- Celda muestra Available formateado
- Click: celda se convierte en input numérico con autofocus
- Tab o blur: PUT /asignaciones (upsert) + optimistic update de la vista
- Error: revierte y muestra mensaje

**Endpoint:**
- POST `/asignaciones` con `{categoria_id, año, mes, monto_centavos}` (crea o actualiza)
- `AsignacionController::store` hace upsert: busca por `(presupuesto, categoria, año, mes)`, actualiza si existe, crea si no

**Inertia:**
- Response: redirect back con flash + prop actualizada
- UI: Inertia reemplaza datos sin recarga

## Decisión 7: Vista mensual

**Layout:**
┌────────────────────────────────────────────────────────────┐
│ ◄ Septiembre 2026 | Octubre 2026 | Noviembre 2026 ► │
├────────────────────────────────────────────────────────────┤
│ Ready to Assign: Bs 2.100,00 │
│ (verde si > 0, amarillo si = 0, rojo si < 0) │
├────────────────────────────────────────────────────────────┤
│ OBLIGACIONES INMEDIATAS │
│ Assigned Activity Available │
│ Alquiler Bs 1500 Bs 1500 Bs 0 │
│ Transporte Bs 300 Bs 120 Bs 180 │
│ Comida básica Bs 800 Bs 250 Bs 550 │
├────────────────────────────────────────────────────────────┤
│ (resto de grupos) │
└────────────────────────────────────────────────────────────┘

**Comportamiento:**
- Mes actual por defecto al cargar
- Navegación: cambia URL `/presupuesto-mensual?año=2026&mes=10`
- Click en Assigned: input inline
- Click en Activity: navega a `/transacciones?categoria=X&desde=inicio_mes&hasta=fin_mes`
- Available en rojo si negativo

## Decisión 8: Dashboard update

**Cambios:**
- Hero: Ready to Assign del mes actual (si hay presupuesto activo)
- Grid de "Sobres atentos": top 5 categorías con menor Available (incluyendo negativos primero)
- Links CTA: "Asignar presupuesto" (→ /presupuesto-mensual), "Nueva transacción" (→ /transacciones/create)

**Sin cambios profundos:** mantiene identidad visual. Es un dashboard, no reemplaza /presupuesto-mensual.

## Decisión 9: Seeder de octubre 2026

**Asignaciones iniciales de María:**
Alquiler Bs 1500
Transporte Bs 300
Comida básica Bs 800
Ropa Bs 100
Cortes de pelo Bs 60
Regalos Bs 50
Salidas Bs 200
Suscripciones Bs 150
Emergencia Bs 300
Viajes Bs 150
Total asignado: Bs 3610

Inflows acumulados en octubre: Bs 3500 (sueldo) + Bs 710 (freelance USDT→BOB) = Bs 4210.

Ready to Assign = 4210 - 3610 = **Bs 600**.

Esto demuestra:
- RTA no en cero (realista — María aún tiene por asignar)
- Available calculable con rollover inicial
- Overspent potencial si Activity > Assigned en alguna categoría
