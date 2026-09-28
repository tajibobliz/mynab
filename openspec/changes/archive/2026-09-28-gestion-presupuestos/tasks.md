## 1. Modelo y migración

- [x] 1.1 Crear migración `create_presupuestos_table` con columnas: `id`, `user_id` (FK a users, cascadeOnDelete), `nombre` (string 100, not null), `descripcion` (text, nullable), `color` (char 7, default '#22c55e'), `icono` (string 30, default 'wallet'), `moneda_base_id` (unsignedBigInteger, nullable — FK se agrega en Change 3), `timestamps`, `softDeletes`. **Decisión confirmada:** sin columna `activo` — ningún requirement/escenario del spec la usa; solo `deleted_at` oculta presupuestos.
- [x] 1.2 Crear migración `add_presupuesto_activo_id_to_users_table` con `presupuesto_activo_id` (unsignedBigInteger, nullable, FK a presupuestos con nullOnDelete)
- [x] 1.3 Crear modelo `Presupuesto` en `app/Models/Presupuesto.php` con: fillable (nombre, descripcion, color, icono, moneda_base_id), SoftDeletes trait, relación `user()` belongsTo. Sin `activo`/scope `activos()` (ver 1.1).
- [x] 1.4 Agregar relación `presupuestos()` hasMany en modelo `User`
- [x] 1.5 Agregar relación `presupuestoActivo()` belongsTo en modelo `User`
- [x] 1.6 Correr migraciones: `php artisan migrate`

## 2. Factory y seeder

- [x] 2.1 Crear `PresupuestoFactory` en `database/factories/PresupuestoFactory.php` con valores realistas
- [x] 2.2 Crear `PresupuestoSeeder` en `database/seeders/PresupuestoSeeder.php` que cree para María (buscar por email): "Personal" (color verde, icono wallet) + "Freelance USD" (color violeta, icono briefcase). María no existía todavía en `DatabaseSeeder`, así que el seeder la crea con `User::firstOrCreate(['email' => 'maria@example.com'], [...])` si no existe.
- [x] 2.3 Al crear los presupuestos, marcar "Personal" como `presupuesto_activo_id` de María
- [x] 2.4 Registrar `PresupuestoSeeder` en `DatabaseSeeder`
- [x] 2.5 Correr: `php artisan migrate:fresh --seed` y verificar que María queda con 2 presupuestos y "Personal" como activo

## 3. Policy y autorización

- [x] 3.1 Crear `PresupuestoPolicy` en `app/Policies/PresupuestoPolicy.php` con métodos: viewAny (true), view (user_id match), create (true para usuarios auth), update (user_id match), delete (user_id match)
- [x] 3.2 Registrar la Policy: este proyecto (Laravel 12) no trae `AuthServiceProvider`; se confirmó por auto-discovery de convención de nombres (`Gate::getPolicyFor(Presupuesto::class)` resuelve a `PresupuestoPolicy` sin registro manual). Verificado también con checks reales `Gate::forUser()` cruzando dos usuarios.

## 4. Requests de validación

- [x] 4.1 Crear `StorePresupuestoRequest` con reglas: nombre (required, string, 2-100 chars, unique por user_id case-insensitive), descripcion (nullable, string, max 500), color (required, regex hex), icono (required, in: whitelist de 20 iconos Lucide en `config('mynab.iconos_presupuesto')`)
- [x] 4.2 Crear `UpdatePresupuestoRequest` con las mismas reglas + excluye el propio ID (`whereKeyNot`) en el chequeo de unicidad
- [x] 4.3 Mensajes de validación en español en método `messages()` de cada request

## 5. Controller

- [x] 5.1 Crear `PresupuestoController` en `app/Http/Controllers/PresupuestoController.php` con resource routes. También se agregó `show()` (no listado explícitamente aquí, pero `Route::resource` del Grupo 6 lo genera y 7.4 pide un `Show.vue` placeholder — sin `show()` esa ruta rompería).
- [x] 5.2 Método `index`: `Inertia::render('Presupuestos/Index', ['presupuestos' => auth()->user()->presupuestos()->get()])`. Sin `->activos()`: esa scope no existe (ver Grupo 1, decisión de eliminar el campo `activo`).
- [x] 5.3 Método `create`: `Inertia::render('Presupuestos/Create')`
- [x] 5.4 Método `store`: crear presupuesto, si es el primero → asignar como activo del usuario, redirect a index con flash success
- [x] 5.5 Método `edit`: authorize + Inertia::render('Presupuestos/Edit', [presupuesto])
- [x] 5.6 Método `update`: authorize + actualizar + redirect a index con flash success
- [x] 5.7 Método `destroy`: authorize + softDelete + si era el activo, reasignar al siguiente disponible (el más antiguo restante, `oldest()->first()`) o null. Redirect a `/dashboard` con flash info si queda sin presupuesto activo; si no, a `/presupuestos` con flash success.
- [x] 5.8 Método `seleccionar` (POST /presupuestos/{presupuesto}/seleccionar): authorize('view') + actualizar users.presupuesto_activo_id + redirect back con flash

## 6. Rutas

- [x] 6.1 En `routes/web.php` dentro del grupo `auth:sanctum,jetstream,verified`, agregar `Route::resource('presupuestos', PresupuestoController::class)`
- [x] 6.2 Agregar `Route::post('presupuestos/{presupuesto}/seleccionar', [PresupuestoController::class, 'seleccionar'])->name('presupuestos.seleccionar')`
- [x] 6.3 Verificar rutas con `php artisan route:list --name=presupuestos`

## 7. Vistas Inertia (Vue)

- [x] 7.1 Crear `resources/js/Pages/Presupuestos/Index.vue`: lista de tarjetas con nombre + icono + color + descripción + acciones (editar, borrar, seleccionar como activo). Empty state si no hay presupuestos: mensaje "Aún no tienes presupuestos" + botón "Crear presupuesto" con icono Plus
- [x] 7.2 Crear `resources/js/Pages/Presupuestos/Create.vue`: formulario con inputs nombre, descripcion, color (picker), icono (selector visual de iconos Lucide)
- [x] 7.3 Crear `resources/js/Pages/Presupuestos/Edit.vue`: mismo formulario que Create pero prellenado
- [x] 7.4 Crear `resources/js/Pages/Presupuestos/Show.vue` (placeholder): mensaje "El detalle del presupuesto se implementa en el Change 8 (zero-based budgeting)"
- [x] 7.5 Crear componente reutilizable `resources/js/Components/PresupuestoCard.vue` con las variantes: `default` (index) y `selector` (dropdown navbar)
- [x] 7.6 Crear componente `resources/js/Components/IconoSelector.vue` para el picker de iconos Lucide en Create/Edit
- [x] 7.7 Crear componente `resources/js/Components/ColorPicker.vue` con paleta predefinida de ~8 colores + hex custom

## 8. Selector en el navbar

- [x] 8.1 En `resources/js/Layouts/AppLayout.vue`, agregar componente `PresupuestoSelector.vue` al navbar (entre el wordmark y los links Dashboard/Presupuestos; también reutilizado en el menú responsive)
- [x] 8.2 Crear `resources/js/Components/PresupuestoSelector.vue`: dropdown con presupuesto activo (nombre + color; moneda base queda para Change 3, ver notas de Grupo 7), lista de otros presupuestos disponibles, opción "Gestionar presupuestos" que va a /presupuestos, opción "Crear nuevo" que va a /presupuestos/create
- [x] 8.3 El selector consume `usePage().props.auth.user.presupuesto_activo` (snake_case — Eloquent serializa la relación `presupuestoActivo()` así, no camelCase como decía esta tarea) y `usePage().props.auth.user.presupuestos`
- [x] 8.4 Modificar `app/Http/Middleware/HandleInertiaRequests.php`: `$request->user()?->loadMissing(['presupuestos', 'presupuestoActivo'])` antes del share — Jetstream ya serializa `auth.user` vía `$user->toArray()` en su propio middleware, así que cargar las relaciones ahí basta sin tocar código vendor

## 9. Redirección inicial sin presupuestos

- [x] 9.1 **Decisión confirmada (spec.md gana sobre design.md):** el closure de `/dashboard` en `routes/web.php` NO se modifica — no hay redirect de servidor. El spec.md dice que el usuario llega a `/dashboard` y ahí ve el CTA inline (tarea 9.2), no que se lo redirige a otra ruta.
- [x] 9.2 En `Dashboard.vue`, si `presupuesto_activo` (snake_case — ver fix del Grupo 8) es null, mostrar CTA "Crea tu primer presupuesto" con icono Wallet grande, descripción y botón a create, en vez del saludo normal

## 10. Tests (Pest)

- [x] 10.1 Test: usuario auth puede ver su lista de presupuestos
- [x] 10.2 Test: usuario auth puede crear un presupuesto con datos válidos
- [x] 10.3 Test: crear presupuesto con nombre duplicado (case-insensitive) para el mismo usuario falla
- [x] 10.4 Test: crear presupuesto con nombre duplicado para OTRO usuario es permitido
- [x] 10.5 Test: primer presupuesto creado se asigna automáticamente como activo
- [x] 10.6 Test: usuario auth puede actualizar SU presupuesto
- [x] 10.7 Test: usuario auth NO puede actualizar presupuesto de otro usuario (403)
- [x] 10.8 Test: usuario auth puede eliminar (soft delete) SU presupuesto
- [x] 10.9 Test: al eliminar el presupuesto activo, se reasigna al siguiente disponible
- [x] 10.10 Test: al eliminar el último presupuesto, `presupuesto_activo_id` queda null
- [x] 10.11 Test: usuario puede cambiar el presupuesto activo con POST /presupuestos/{id}/seleccionar
- [x] 10.12 Test: usuario NO puede seleccionar como activo un presupuesto que no es suyo (403)
- [x] 10.13 Test: validación rechaza color con formato hex inválido
- [x] 10.14 Test: validación rechaza icono fuera de la whitelist
- [x] 10.15 (extra, pedido explícitamente) Test: validación rechaza nombre vacío
- [x] 10.16 (extra) Test: validación rechaza nombre de menos de 2 caracteres
- [x] 10.17 (extra) Test: validación rechaza nombre de más de 100 caracteres
- [x] 10.18 (extra) Test: validación rechaza descripción de más de 500 caracteres
- [x] 10.19 (extra) Test: usuario no autenticado no puede acceder a /presupuestos (redirige a login)

## 11. Verificación manual

- [x] 11.1 `php artisan migrate:fresh --seed`, verificar 2 presupuestos de María en BD
- [x] 11.2 Login como María, ver el selector en el navbar mostrando "Personal" (sin "· BOB": `moneda_base_id` sigue null hasta Change 3, según lo acordado)
- [x] 11.3 Cambiar al presupuesto "Freelance USD" desde el selector, verificar que persiste al recargar
- [x] 11.4 Ir a `/presupuestos`, ver la lista con 2 tarjetas coloridas
- [x] 11.5 Crear un tercer presupuesto "Ahorros", verificar que aparece en el selector
- [x] 11.6 Eliminar "Freelance USD", verificar que desaparece del selector y de la lista
- [x] 11.7 Editar "Personal" cambiando color e icono, verificar cambios visuales inmediatos
- [x] 11.8 Correr `php artisan test` completo, verificar todos los tests en verde