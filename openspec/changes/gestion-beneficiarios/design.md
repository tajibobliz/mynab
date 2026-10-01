# Design — Gestión de Beneficiarios

## Decisión 1: Beneficiarios por presupuesto (no por usuario)

**Contexto:** se consideraron dos modelos:
- Por usuario: "Entel" es la misma empresa, no duplicar entre presupuestos
- Por presupuesto: cada contexto tiene su propio set

**Decisión:** por presupuesto. `beneficiarios.presupuesto_id` FK obligatoria.

**Razones:**
1. **Consistencia con Cuentas y Categorías**: toda entidad transaccional vive dentro del presupuesto activo. Beneficiarios siguen el mismo patrón, sin excepciones.
2. **Aislamiento de contextos**: María en "Freelance USD" puede tener "Cliente A", "Cliente B", "Cliente C" sin que aparezcan en "Personal". Caso real: beneficiarios de contexto laboral vs personal se mantienen separados visualmente.
3. **Simplicidad de autorización**: policy via `beneficiario->presupuesto->user_id` sigue el mismo patrón ya establecido.
4. **YNAB real**: también los tiene por presupuesto (budget-scoped).

**Alternativa descartada:** por usuario. Habría ahorrado duplicación de "Entel" si María la tiene en dos presupuestos, pero rompe el aislamiento del contexto activo y complica queries en Change 7.

## Decisión 2: Solo nombre + notas opcionales (sin tipos, sin categorías defaults)

**Contexto:** YNAB real tiene además:
- Categoría por defecto (al elegir "Netflix" preselecciona "Suscripciones")
- Memoria del monto típico (al elegir "Alquiler" sugiere Bs 1500)

**Decisión:** Fase 1 modela beneficiario con solo:
- `nombre` (string 100, requerido)
- `notas` (text, opcional) — útil para contexto humano

Sin tipos, sin categoría default, sin monto sugerido. Fase 2 puede agregar lo que valga la pena.

**Razones:**
1. **Entrega del jueves**: no sobre-ingenierar.
2. **UX simple**: menos campos = menos fricción al crear
3. **Las features avanzadas requieren Change 7 terminado**: la categoría default y monto sugerido son útiles al crear transacción, no al crear beneficiario. Las agregamos cuando sean necesarias.

## Decisión 3: Nombre único case-insensitive dentro del presupuesto

**Contexto:** consistencia con Presupuestos, Cuentas, Categorías, Grupos.

**Decisión:** closure `whereRaw('LOWER(nombre) = ?', ...)` scoped a `presupuesto_id`. Previene duplicados como "Entel" / "entel" / "ENTEL" en el mismo presupuesto.

**Permitido:** mismo nombre en distinto presupuesto. María puede tener "Dueño del alquiler" en Personal y en Freelance USD como entidades separadas (aunque sea la misma persona real).

## Decisión 4: Soft delete

**Contexto:** las transacciones del Change 7 tendrán `beneficiario_id` como FK. Si María borra "Netflix" y luego quiere ver sus gastos históricos del año pasado, las transacciones no deben romperse.

**Decisión:** `SoftDeletes` trait. `deleted_at` setea al eliminar. Transacciones históricas siguen apuntando al beneficiario soft-deleted (visibles con `withTrashed()` desde el modelo Transacción).

**Restore:** Fase 2.

**Dropdown de beneficiarios en Change 7**: filtra solo activos (sin `withTrashed()`), consistente con el patrón de Cuentas y Categorías.

## Decisión 5: Vista integrada al presupuesto activo

**Contexto:** mismo patrón que Cuentas y Categorías.

**Decisión:**
- `/beneficiarios` muestra solo beneficiarios del presupuesto activo
- `Create` auto-asigna `presupuesto_id` desde el activo
- `Edit` no permite cambiar `presupuesto_id` (fija tras crear)
- Sin presupuesto activo → redirect a `/dashboard` con flash

## Decisión 6: Orden alfabético

**Contexto:** María va a tener 10-30 beneficiarios. Alfabético es intuitivo para buscar visualmente ("¿tengo a Netflix? busco en la N").

**Decisión:** orden alfabético automático por nombre. Sin ordenamiento manual en Fase 1.

## Decisión 7: No cascada especial al borrar presupuesto

**Contexto:** Change 2 ya definió `cascadeOnDelete` en FK de Cuentas/Grupos. Hard delete del presupuesto cascada a beneficiarios automáticamente.

**Decisión:** FK `cascadeOnDelete` estándar. Igual que Cuentas y Grupos.

Soft delete del presupuesto NO cascadea a beneficiarios (misma deuda técnica aceptada en Changes 4 y 5). Change dedicado en Fase 2.