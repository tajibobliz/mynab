# Design — Gestión de Presupuestos

## Decisión 1: Multi-presupuesto por usuario

**Contexto:** YNAB permite que un usuario tenga múltiples budgets independientes (personal, business, side hustle). En el proyecto anterior "Cuentas Claras" cada viaje era un contexto aislado; aquí adoptamos el mismo patrón.

**Decisión:** Un usuario puede tener N presupuestos. Cada presupuesto es un contexto financiero completo (sus propias cuentas, categorías, transacciones).

**Alternativas descartadas:**
- Un solo presupuesto por usuario: más simple pero pierde el paralelismo con YNAB.
- Presupuestos compartidos entre usuarios: fuera del alcance de Fase 1; puede añadirse en Fase 2 con tabla pivote.

## Decisión 2: Presupuesto activo

**Contexto:** Cuando un usuario tiene 3 presupuestos, el sistema necesita saber cuál está viendo para filtrar cuentas, transacciones, reportes, etc. Hay dos enfoques:

**Enfoque A (adoptado):** guardar `presupuesto_activo_id` en la tabla `users`. Todas las páginas leen de ahí. El usuario cambia el activo desde un selector en el navbar.

**Enfoque B (descartado):** URL con `/presupuestos/{id}/cuentas`, `/presupuestos/{id}/transacciones`, etc. Más explícito pero requiere pasar el ID en todas las rutas y complica la navegación.

**Resultado:** cuando el usuario cambia de presupuesto activo, la app entera se recontextualiza sin necesidad de cambiar URLs.

**Casos edge:**
- Usuario sin presupuestos: `presupuesto_activo_id = null`. La app redirige a `/presupuestos/create` con mensaje "Crea tu primer presupuesto para empezar".
- Usuario borra su presupuesto activo: se actualiza `presupuesto_activo_id` al siguiente presupuesto disponible del usuario. Si no hay más, queda `null`.

## Decisión 3: Moneda base

**Contexto:** Cada presupuesto tiene una "moneda de reporte" para consolidar valores. YNAB obliga a elegirla al crear el budget y no se puede cambiar después.

**Decisión:** `moneda_base_id` es requerido lógicamente pero se implementa como `nullable` en este change porque la tabla `monedas` aún no existe (se crea en Change 3).

**Plan de migración:**
- Change 2: `moneda_base_id` nullable en `presupuestos`.
- Change 3: crea tabla `monedas`, seedea BOB/USD/USDT, y modifica `presupuestos.moneda_base_id` a NOT NULL con FK.
- Change 3 también incluye un data migration que asigna la moneda por defecto a los presupuestos creados en Change 2 (BOB por defecto).

## Decisión 4: Soft delete

**Contexto:** Un presupuesto tiene transacciones históricas asociadas. Borrar un presupuesto en cascada perdería el historial financiero completo del usuario.

**Decisión:** `SoftDeletes` (`deleted_at` timestamp). Cuando el usuario "borra" un presupuesto:
- El registro no se elimina físicamente.
- Se marca `deleted_at`.
- Se oculta de la lista y del selector.
- Sus cuentas/categorías/transacciones asociadas siguen en la base pero no se ven porque las queries filtran por presupuestos no borrados.

**Restauración:** fuera del alcance de Fase 1. Puede añadirse en Fase 2 con una vista "Papelera".

## Decisión 5: Personalización visual (color + icono)

**Contexto:** El selector del navbar debe permitir al usuario distinguir presupuestos de un vistazo.

**Decisión:** cada presupuesto tiene:
- `color`: string 7 chars hex (default `#22c55e`). Se muestra como pill/dot en el selector.
- `icono`: nombre de icono Lucide (default `wallet`). Ej: `wallet`, `briefcase`, `plane`, `gift`, `heart`. Se muestra a la izquierda del nombre.

**Validación de color:** regex `/^#[0-9a-fA-F]{6}$/`.
**Validación de icono:** whitelist de ~20 iconos Lucide predefinidos para evitar que el usuario ingrese nombres inválidos que rompan el render.

## Decisión 6: Nombre único por usuario, case-insensitive

**Contexto:** María no debería poder tener 2 presupuestos "Personal" (por más que uno tenga mayúsculas y otro no). Pero Juan sí puede tener el suyo llamado "Personal".

**Decisión:** unique constraint compuesto en `(user_id, LOWER(nombre))`. En Laravel esto se valida con Rule::unique con `where` scope.

## Decisión 7: Autorización

**Contexto:** Un usuario nunca debe ver ni tocar presupuestos de otro usuario.

**Decisión:** `PresupuestoPolicy` con checks estrictos en `viewAny`, `view`, `create`, `update`, `delete`:
- `viewAny`: siempre true (para listar los propios).
- `view` / `update` / `delete`: `$user->id === $presupuesto->user_id`.
- Todas las queries en el controller filtran por `where('user_id', auth()->id())` como capa adicional.

## Decisión 8: Redirecciones post-acción

Consistencia con `CLAUDE.md`: siempre Inertia + redirect, nunca JSON.
- `store`: → `/presupuestos` con flash success + selecciona automáticamente el nuevo como activo si es el primero.
- `update`: → `/presupuestos` con flash success.
- `destroy`: → `/presupuestos` con flash success + reasigna `presupuesto_activo_id` si el borrado era el activo.