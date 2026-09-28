## Why

MyNAB necesita permitir que cada usuario gestione **múltiples presupuestos independientes** (personal, freelance, negocio, etc.), tal como hace YNAB. Sin esta capacidad, todas las cuentas, categorías, transacciones y reportes viven en un único espacio compartido, imposibilitando separar contextos financieros.

Este change es la base sobre la que descansan TODOS los siguientes changes de dominio: cuentas, categorías, beneficiarios y transacciones dependerán de un `presupuesto_id` como foreign key raíz.

## What Changes

- Modelo `Presupuesto` con campos: nombre, descripción, color, icono, moneda base, soft delete
- Migración de tabla `presupuestos` en snake_case español
- CRUD completo: index, create, edit, update, destroy (soft delete)
- Concepto de "presupuesto activo": `users.presupuesto_activo_id` que indica cuál está viendo el usuario
- Selector de presupuesto activo en el navbar (dropdown con nombre + color + moneda base)
- Página de detalle del presupuesto (placeholder, se llena en changes posteriores)
- Autorización estricta: solo el dueño ve/edita/borra sus presupuestos
- Validación: nombre único por usuario (case-insensitive), longitud 2-100 chars, color hex válido
- Seeder: María con 2 presupuestos ("Personal" BOB y "Freelance USD")

## Capabilities

### New Capabilities
- `presupuestos`: Gestión completa de presupuestos multi-usuario con selector de presupuesto activo y personalización visual.

### Modified Capabilities
<!-- Ninguna. -->

## Impact

- Cambios en la tabla `users`: nueva columna `presupuesto_activo_id` (foreign key nullable a `presupuestos`)
- Todos los changes posteriores que agreguen tablas de dominio (cuentas, categorias, beneficiarios, transacciones) deberán agregar `presupuesto_id` como foreign key raíz
- El navbar de `AppLayout.vue` gana un selector — cambio menor pero visible en toda la app
- Dependencia técnica pendiente: `moneda_base_id` referencia una tabla `monedas` que aún no existe. Se crea como nullable en este change y se hace NOT NULL en Change 3 (`gestion-monedas-tipos-cambio`).
- Autorización basada en `PresupuestoPolicy` que valida `user_id == auth()->id()` para todas las operaciones