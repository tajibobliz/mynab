## 1. Migraciones

- [x] 1.1 Migración `create_grupos_categorias_table`: id, presupuesto_id (FK cascadeOnDelete), nombre (string 100), color (string 7), icono (string 50), timestamps, softDeletes.
- [x] 1.2 Migración `create_categorias_table`: id, grupo_categoria_id (FK a `grupos_categorias` — nombre de tabla explícito en `constrained('grupos_categorias')`, la inferencia por defecto de `constrained()` habría buscado `grupo_categorias`, plural inglés incorrecto), nombre (string 100), timestamps, softDeletes.
- [x] 1.3 Índices `grupos_categorias(presupuesto_id)` y `categorias(grupo_categoria_id)` agregados explícitamente (Postgres no indexa automáticamente la columna FK como sí hace MySQL).

  Sin unique a nivel DB para `nombre` en ninguna de las dos tablas: la unicidad case-insensitive vive en el FormRequest (Grupo 5), mismo patrón que presupuestos/cuentas. `migrate:fresh --seed` corrido limpio. `php artisan test`: 101/101 (95 passed + 6 skipped, 0 failed) — sin regresiones antes de tocar nada más.

## 2. Modelos

- [x] 2.1 `app/Models/GrupoCategoria.php`: `$table = 'grupos_categorias'` **verificado con tinker** (`Str::snake('GrupoCategoria')` = `grupo_categoria` → plural `grupo_categorias`, incorrecto — no asumido, confirmado empíricamente antes de escribir el código). Fillable, `presupuesto()` belongsTo, `categorias()` hasMany, hook `deleting()` en `booted()` que soft-elimina las categorías hijas cuando no es force delete.
- [x] 2.2 `app/Models/Categoria.php`: **sin** `$table` explícito — verificado con tinker que `Str::snake('Categoria')` = `categoria` → plural `categorias` coincide con la tabla real (a diferencia de GrupoCategoria, acá la inferencia por defecto es correcta). Fillable, `grupoCategoria()` belongsTo. Sin hook.
- [x] (cruzado) `Presupuesto::gruposCategorias()` hasMany agregado.

  **Verificación del hook `deleting()`** con datos ad-hoc reales (grupo + 2 categorías): antes del delete, los 3 con `deleted_at=NULL`; tras `$grupo->delete()`, los 3 con `deleted_at` seteado; con `withTrashed()` los 3 siguen existiendo, sin `withTrashed()` ninguno aparece. **Hallazgo al limpiar** (no bug): `forceDelete()` del grupo disparó el `cascadeOnDelete` de la FK a nivel de Postgres, que borró también las categorías directamente en la base de datos (fuera de Eloquent) — mis siguientes `forceDelete()` sobre esas categorías fallaron con "on null" porque ya no existían. Confirma que la FK cascade real funciona; el error era de mi script de limpieza, no de la app. Verificado que no quedó ningún residuo (`withTrashed()->count()` = 0 en ambas tablas).

  `php artisan test`: 101/101 (95 passed + 6 skipped, 0 failed).

## 3. Config y whitelist de iconos

- [x] 3.1 `iconos_grupo_categoria` en `config/mynab.php`: 20 iconos exactos (AlertCircle, Home, Car, ShoppingCart, Shirt —no ShirtIcon—, Scissors, Gift, Sparkles, Music, Tv, PiggyBank, TrendingUp, Target, Umbrella, Coffee, Utensils, Wifi, Fuel, Book, Dumbbell). Verificado con tinker: `count()` = 20, sin duplicados.
- [x] 3.2 Compartido en `HandleInertiaRequests`: `iconosGrupoCategoria => config('mynab.iconos_grupo_categoria')`.

  **Corrección sobre la instrucción original:** se pidió extender `lucideIconMap.js` (kebab-case, ej. `'alert-circle'`) con los iconos nuevos. Pero tanto design.md (su propio ejemplo JSON usa `'icono' => 'AlertCircle'`) como la lista de iconos dada usan **PascalCase** — el nombre exacto del export de Lucide, no kebab-case. Mezclar ambos habría roto la resolución del icono en el frontend (`lucideIconMap['AlertCircle']` sería `undefined` en un mapa con claves kebab-case). En vez de modificar `lucideIconMap.js`, creé `resources/js/grupoCategoriaIconMap.js` con claves PascalCase — mismo patrón ya establecido por `tipoCuentaIconMap.js` en Change 4 (un pool de iconos nuevo, con su propia convención, no reutiliza el mapa de `iconos_presupuesto`).

  `npm run build` limpio. `php artisan test`: 101/101 (95 passed + 6 skipped, 0 failed).

## 4. Policies

- [x] 4.1 `GrupoCategoriaPolicy` (`--model=GrupoCategoria`): viewAny/create=true, view/update/delete=`$user->id === $grupo->presupuesto->user_id`, restore/forceDelete=false (scaffold). Auto-discovery confirmado.
- [x] 4.2 `CategoriaPolicy` (`--model=Categoria`): viewAny/create=true, view/update/delete=`$user->id === $categoria->grupoCategoria->presupuesto->user_id` (2 saltos de relación), restore/forceDelete=false. Auto-discovery confirmado. Anotado en el código que el controller (Grupo 6) debe eager-loadear `grupoCategoria.presupuesto` antes de `authorize()` si ya las va a necesitar, para no duplicar las 2 queries lazy del check.

  **Matriz de verificación** con datos reales (María vs. usuario nuevo, grupo + categoría ad-hoc): los 16 casos (8 GrupoCategoria + 8 Categoria: view/update/delete propio=true, ajeno=false; viewAny/create=true) correctos. Datos de prueba limpiados.

  `php artisan test`: 101/101 (95 passed + 6 skipped, 0 failed).

## 5. Requests

- [x] 5.1 `StoreGrupoCategoriaRequest`: nombre (required, max:100, `nombreUnicoRule()` closure scoped a presupuesto — no `Rule::unique()->where()`, mismo motivo de siempre), color (regex hex), icono (`Rule::in(config('mynab.iconos_grupo_categoria'))`), `presupuesto_id` inyectado en `prepareForValidation()`. `authorize()`: solo `can('create', ...)`, sin chequear presupuesto activo (precedente ya resuelto en Change 4).
- [x] 5.2 `UpdateGrupoCategoriaRequest`: igual sin `presupuesto_id` (fijo tras crear), `nombreUnicoRule()` con `whereKeyNot()`. `authorize()`: `can('update', $this->route('grupo_categoria'))` — **nombre del parámetro corregido a singular-singular** (`grupo_categoria`, no `grupos_categoria` como decía tasks.md originalmente — ver hallazgo del plan, Grupo 7 lo confirma empíricamente).
- [x] 5.3 `StoreCategoriaRequest`: nombre (`nombreUnicoRule()` scoped a `grupo_categoria_id`), `grupo_categoria_id` (required, integer, `Rule::exists('grupos_categorias','id')->where('presupuesto_id', activo)` — **previene IDOR**: sin el `where`, un usuario podría crear categorías en grupos ajenos con solo un ID válido de otro usuario). `authorize()`: `can('create', Categoria::class)`.
- [x] 5.4 `UpdateCategoriaRequest`: nombre con `nombreUnicoRule()` + `whereKeyNot()`, `grupo_categoria_id` **no está en las reglas** (no editable en Fase 1 — mover de grupo requiere borrar y recrear). `authorize()`: `can('update', $this->route('categoria'))`.
- [x] 5.5 Mensajes en español en los 4 Requests, específicos por regla.

  **Verificación con `FormRequest::create()` + `setUserResolver`/`setRouteResolver`** (mismo patrón de Changes 3/4): los 12 casos pedidos pasaron — store grupo válido, duplicado mismo presupuesto (falla), mismo nombre otro presupuesto (pasa), color no hex (falla), icono fuera de whitelist (falla), update nombre igual al propio (pasa), store categoría válida, duplicado mismo grupo (falla), mismo nombre otro grupo (pasa), `grupo_categoria_id` de otro usuario (falla — IDOR bloqueado), `grupo_categoria_id` inexistente (falla), update ignora `grupo_categoria_id` (pasa). **Encontré y corregí un falso positivo en mi propio script de verificación**: el caso 2 (duplicado) inicialmente "pasaba" porque el payload de prueba no incluía `presupuesto_id` (necesario para que `prepareForValidation()` simule su inyección real), así que fallaba por el campo equivocado (`presupuesto_id` ausente) en vez de por el closure de duplicado — el mensaje de error vacío en el primer intento fue la señal de alerta. Corregido incluyendo `presupuesto_id` explícito en el payload de prueba; re-verificado con el mensaje real del closure.

  `php artisan test`: 101/101 (95 passed + 6 skipped, 0 failed).

## 6. Controllers

- [x] 6.1 `GrupoCategoriaController`: `index` con guard + `with(['categorias' => fn($q) => $q->orderBy('nombre')])->orderBy('nombre')` (grupos y categorías alfabéticos, sin N+1). `create`/`edit`/`store`/`update`/`destroy` estándar, `authorize()` explícito en `edit`/`update`/`destroy` (defensa en profundidad).
- [x] 6.2 `CategoriaController` (solo store/update/destroy): `update`/`destroy` cargan `grupoCategoria.presupuesto` antes de `authorize()` para evitar 2 queries lazy adicionales por request (policy de 2 saltos).
- [x] 6.3 Guard privado `presupuestoActivoOrRedirect()` — mismo patrón que `CuentaController` (Change 4).

  **Verificación de 8 edge cases** (tinker, controllers invocados directo con `Auth::login()` + `FormRequest::create()+setRedirector()`, mismo patrón de Change 4 Grupo 5): index sin activo → redirect+flash ✓; store grupo válido → `presupuesto_id` correcto ✓; update nombre igual al propio → pasa, campos actualizados ✓; store categoría válida ✓; destroy grupo con 2 categorías → cascada soft delete en los 3 (`deleted_at` seteado) ✓; destroy categoría ajena → 403 ✓; store categoría con `grupo_categoria_id` ajeno → `ValidationException` (IDOR bloqueado a nivel Request, antes de llegar al controller) ✓; destroy grupo ajeno → 403 ✓.

  **CHECKPOINT CRÍTICO cumplido**: `php artisan test`: 101/101 (95 passed + 6 skipped, 0 failed) antes de pasar al Grupo 7.

## 7. Rutas

- [x] 7.1 `Route::resource('grupos-categorias', GrupoCategoriaController::class)->except('show')->parameters(['grupos-categorias' => 'grupo_categoria'])` — **singular-singular**, corregido respecto al valor original de tasks.md (`grupos_categoria`, que no habría arreglado nada — ver hallazgo del plan).
- [x] 7.2 `Route::resource('categorias', CategoriaController::class)->only(['store', 'update', 'destroy'])` — sin `->parameters()`, "categorias" no tiene guión.
- [x] 7.3 Verificado con `route:list -v`: 6 rutas para `grupos-categorias` (wildcard `{grupo_categoria}`), 3 para `categorias` (wildcard `{categoria}`), middleware `auth:sanctum`+`AuthenticateSession`+`verified` idéntico en todas. **Binding verificado empíricamente** (no asumido): requests reales contra `SubstituteBindings` resuelven `{grupo_categoria}` y `{categoria}` a las instancias correctas por ID. Curl real sin sesión: GET `/grupos-categorias` → 302 a `/login`, POST `/grupos-categorias` y POST `/categorias` → 419.

  `php artisan test`: 101/101 (95 passed + 6 skipped, 0 failed).

## 8. HandleInertiaRequests

- [x] 8.1 `gruposCategoriasDelPresupuestoActivo` agregado al share como closure (mismo patrón que `cuentasDelPresupuestoActivo`), `loadMissing('presupuestoActivo.gruposCategorias.categorias')`, retorna `[]` si no hay usuario o presupuesto activo.
- [x] 8.2 `iconosGrupoCategoria` ya estaba en el share desde el Grupo 3.

  **Verificación de N+1** (`DB::listen()`, 2 grupos de prueba con 2 categorías cada uno — el seeder real es Grupo 11): exactamente **2 queries** al resolver el closure (`grupos_categorias WHERE presupuesto_id IN (...)`, `categorias WHERE grupo_categoria_id IN (...)`) — no 3, porque `presupuestoActivo` ya estaba cargado por el `loadMissing` previo en el mismo `share()`. Estructura anidada confirmada (grupo con `categorias` como colección). `iconosGrupoCategoria`: 20 elementos. Datos de prueba limpiados.

  `php artisan test`: 101/101 (95 passed + 6 skipped, 0 failed).

## 9. Vistas Inertia

- [x] 9.1 `Pages/GruposCategorias/Index.vue`: vista maestra con `GrupoCategoriaCard` por grupo, orden alfabético defensivo en frontend (`localeCompare`, grupos y categorías), categorías con Editar/Eliminar + "Nueva categoría" al final, mensaje "Este grupo no tiene categorías. Agregar una →" (clickeable, abre el modal) cuando el grupo está vacío. Estado local para `CategoriaModal` (modo/grupo/categoría en edición) y para `ConfirmationModal` reutilizado entre grupo y categoría (`confirmandoBorradoDe: {tipo, item}`), con mensaje dinámico que indica cuántas categorías se eliminarán en cascada si el grupo no está vacío. Empty state global, botón "Nuevo grupo".

  **Verificación visual end-to-end** (Playwright, grupo creado vía UI real, sin seeder — Grupo 11 pendiente): empty state → crear grupo → mensaje de grupo vacío → abrir modal (nombre vacío) → crear categoría (aparece, modal cierra) → editar categoría (prellenado correcto) → eliminar categoría (mensaje de confirmación exacto, sin plural de grupo) → grupo vuelve a empty state → crear categoría de nuevo → eliminar grupo con 1 categoría (mensaje "también se eliminarán sus 1 categorías") → vuelve al empty state global. 12/12 checks, sin errores de consola. Confirmado con tinker que el borrado real es soft delete (no hard) en ambos niveles. Datos de prueba limpiados (`forceDelete`).

  **Verificación cruzada final del Grupo 9**: `php artisan test` 101/101, `--filter=PresupuestoTest` 22/22, Dashboard/Monedas/Cuentas/Grupos y categorías visitados sin errores de consola, navbar "Personal · BOB" intacto.
- [x] 9.2 `Pages/GruposCategorias/Create.vue`: nombre, `IconoSelector` (`:iconos="$page.props.iconosGrupoCategoria"` + `:icon-map="grupoCategoriaIconMap"`), `ColorPicker`. Nota "se creará en {presupuestoActivo.nombre}".
- [x] 9.3 `Pages/GruposCategorias/Edit.vue`: mismo layout, prellenado con el grupo actual, título dinámico `Editar grupo: {grupo.nombre}`.
- [x] 9.4 `Components/CategoriaModal.vue`: sobre `Modal.vue` de Jetstream (contenido propio, sin los slots title/content/footer de `ConfirmationModal` — formulario real, no un mensaje de confirmación). `watch(() => props.show)` repuebla el form cada vez que se abre (mismo componente sirve para create vacío y edit con datos). POST a `categorias.store` o PUT a `categorias.update` según `modo`; `onSuccess` resetea y cierra.

  **Verificación visual** (Playwright, grupo ad-hoc vía tinker — sin seeder todavía, Grupo 11): Create renderiza los 20 iconos de `iconosGrupoCategoria` + la nota del presupuesto; Edit prellena nombre, icono premarcado correctamente (`grupoCategoriaIconMap` resuelve 'Music' sin problema, confirma que el fix del Sub-grupo A funciona en la práctica) y título dinámico — sin errores de consola. Dato de prueba limpiado.

  `npm run build` limpio. `php artisan test`: 101/101 (95 passed + 6 skipped, 0 failed).
- [x] 9.5 `Components/GrupoCategoriaCard.vue`: header con `border-left` del color del grupo + fondo suave (~10% opacity), icono (`grupoCategoriaIconMap`) + nombre, slot `#actions` en el header, slot `#categorias` en el body.

  **Modificación cruzada (Sub-grupo A):** `IconoSelector.vue` (Change 2) generalizado con dos props nuevas: `iconos` (array, antes hardcodeado a `usePage().props.iconosPresupuesto`) e `iconMap` (objeto, default `lucideIconMap` para no romper los callers existentes). **Encontrado antes de que rompiera algo:** generalizar solo `iconos` sin generalizar también el mapa de resolución habría repetido el bug del Grupo 3 — `iconosGrupoCategoria` es PascalCase (`'AlertCircle'`) y `lucideIconMap` es kebab-case, así que `lucideIconMap['AlertCircle']` da `undefined`. `Presupuestos/Create.vue`/`Edit.vue` actualizados para pasar `:iconos="$page.props.iconosPresupuesto"` explícito (sin tocar `iconMap`, usa el default). Verificado visualmente con Playwright: Create y Edit renderizan los 20 iconos, selección funciona, el icono actual del presupuesto llega premarcado en Edit — sin errores de consola. `php artisan test --filter=PresupuestoTest`: 22/22.

## 10. NavLink + navbar

- [x] 10.1 Link "Categorías" agregado en `AppLayout.vue` entre "Cuentas" y "Monedas" (desktop `NavLink` + móvil `ResponsiveNavLink`), `href="route('grupos-categorias.index')"`, `:active="route().current('grupos-categorias.*')"`. **No agregué `|| route().current('categorias.*')`** como se sugirió "por simetría": ese patrón nunca sería verdadero en la práctica hoy (no hay ninguna ruta GET con nombre `categorias.*` a la que se navegue — el recurso `categorias` es solo store/update/destroy, acciones que redirigen de vuelta a `grupos-categorias.index`), así que sería código muerto para un escenario de Fase 2 que no existe todavía.

  **Verificación visual con Playwright** (desktop 1280px + móvil 375px): orden completo del navbar (Dashboard\|Presupuestos\|Cuentas\|Categorías\|Monedas\|Tipos de cambio) correcto; resaltado (`border-accent-primary`) en `/grupos-categorias` y también en `/grupos-categorias/create` (confirma el wildcard `grupos-categorias.*`); en móvil el top nav permanece oculto y el link aparece tras abrir el menú hamburguesa.

  `php artisan test`: 101/101 (95 passed + 6 skipped, 0 failed).

## 11. Factories y seeder

- [x] 11.1 `GrupoCategoriaFactory`: nombre (`fake()->unique()->words(2, true)`), color (random de la paleta de 8 colores ya establecida), icono (random de `config('mynab.iconos_grupo_categoria')`), `presupuesto_id` vía factory.
- [x] 11.2 `CategoriaFactory`: nombre random, `grupo_categoria_id` vía factory.
- [x] 11.3 `PresupuestoSeeder` actualizado (sin `GrupoCategoriaSeeder` separado): 4 grupos + 10 categorías del escenario oficial en "Personal" — Obligaciones inmediatas (`#ef4444`, AlertCircle): Alquiler, Transporte, Comida básica; Gastos reales (`#f59e0b`, Sparkles): Ropa, Cortes de pelo, Regalos; Calidad de vida (`#8b5cf6`, Music): Salidas, Suscripciones; Ahorros (`#22c55e`, PiggyBank): Emergencia, Viajes. **Nota de discrepancia:** tasks.md originalmente decía `#f97316` para Gastos reales; usé `#f59e0b` (el valor dado explícitamente en el mensaje de este grupo, más reciente). Freelance USD queda sin grupos a propósito (test 12.17/13.3 verifican el empty state).

  **Verificación con `migrate:fresh --seed` + tinker**: Personal → 4 grupos (`["Ahorros","Calidad de vida","Gastos reales","Obligaciones inmediatas"]`), 10 categorías totales, Freelance USD → 0 grupos. Verificación visual (Playwright): los 4 grupos y 10 categorías visibles, grupos en orden alfabético en el DOM, cambio a Freelance USD → empty state, sin errores de consola.

  `php artisan test`: 101/101 (95 passed + 6 skipped, 0 failed).

## 12. Tests (Pest)

Dos archivos nuevos: `tests/Feature/GrupoCategoriaTest.php` y
`tests/Feature/CategoriaTest.php` (separados, no consolidados — mismo
precedente que `MonedaTest.php`/`TipoCambioTest.php` en Change 3 para dos
entidades relacionadas pero con su propio ciclo CRUD/Policy). Ambos
reutilizan el helper global `usuarioConPresupuestoActivo()` ya declarado en
`CuentaTest.php` (Change 4) **sin redeclararlo**: Pest incluye todos los
archivos de test del run en el mismo proceso PHP antes de ejecutar nada, así
que una función de nivel superior solo puede declararse una vez en toda la
suite o PHP lanza un fatal "Cannot redeclare function".

Patrón crítico del Grupo 5 aplicado en los 27 tests: ningún `assertInvalid`
genérico ni `fails()` suelto — todo error se verifica con el mensaje exacto
vía `assertSessionHasErrors(['campo' => 'mensaje exacto'])`, leyendo el
mensaje real desde el `messages()` del Request correspondiente antes de
escribir el assert (no se asumió ningún texto).

Grupos (grupos_categorias):
- [x] 12.1 usuario ve solo grupos de su presupuesto activo
- [x] 12.2 store crea grupo válido
- [x] 12.3 store rechaza nombre duplicado en el mismo presupuesto (mensaje exacto: "Ya existe un grupo con ese nombre en este presupuesto.")
- [x] 12.4 store permite mismo nombre en OTRO presupuesto
- [x] 12.5 store rechaza color no hex (mensaje exacto: "El color debe ser un hexadecimal válido (#RRGGBB).")
- [x] 12.6 store rechaza icono fuera de whitelist (mensaje exacto: "Selecciona un icono válido de la lista.")
- [x] 12.7 update permite editar campos (nombre, color e icono, los 3 verificados tras `refresh()`)
- [x] 12.8 destroy soft delete el grupo Y sus categorías (cascada soft) — verificado con `assertSoftDeleted` en ambas tablas
- [x] 12.9 policy: no puede editar/eliminar grupo ajeno (403) — implementado como 2 tests Pest separados (update ajeno, delete ajeno) en vez de 1, para que un fallo señale exactamente cuál de las 2 acciones rompió

Categorías:
- [x] 12.10 store crea categoría dentro del grupo
- [x] 12.11 store rechaza nombre duplicado en el MISMO grupo (mensaje exacto: "Ya existe una categoría con ese nombre en este grupo.")
- [x] 12.12 store permite mismo nombre en OTRO grupo del mismo presupuesto
- [x] 12.13 store rechaza si grupo_categoria_id no pertenece al presupuesto activo del usuario (IDOR, mensaje exacto: "El grupo seleccionado no existe o no pertenece a tu presupuesto activo.")
- [x] 12.14 update permite editar nombre
- [x] 12.15 destroy soft delete categoría
- [x] 12.16 policy: no puede editar/eliminar categoría ajena (403) — igual que 12.9, 2 tests Pest separados

Extra (19 requeridos + 8 adicionales, ver nota de conteo abajo):
- [x] 12.17 sin presupuesto activo → /grupos-categorias redirect a /dashboard (verificado flash.danger)
- [x] 12.18 usuario no autenticado no accede (redirect a /login)
- [x] 12.19 cascade delete: `$presupuesto->forceDelete()` elimina en cascada grupos Y categorías vía el `cascadeOnDelete` real de la FK (mismo precedente que el test 11.19/11.20 de `CuentaTest.php` en Change 4: solo un hard delete real dispara el constraint de Postgres, un soft delete normal de Presupuesto no lo toca — deuda técnica ya documentada y aceptada para este change)
- [x] Extra 1: nombre de grupo con exactamente 100 caracteres pasa
- [x] Extra 2: nombre de grupo con 101 caracteres falla (mensaje exacto: "El nombre no puede tener más de 100 caracteres.")
- [x] Extra 3: nombre de categoría con exactamente 100 caracteres pasa
- [x] Extra 4: nombre de categoría con 101 caracteres falla (mismo mensaje que Extra 2, Request distinto)
- [x] Extra 5: `GrupoCategoria::destroy([$id1, $id2])` en lote también dispara el hook `deleting()` en cascada para cada grupo (Eloquent llama a `->delete()` individualmente por cada modelo, no es un `DELETE` masivo — confirmado por el test, no asumido)
- [x] Extra 6: policy de `Categoria` resuelve el salto `grupoCategoria->presupuesto` por lazy load cuando la relación NO viene precargada (instancia obtenida con `Categoria::find()` fresca, se verifica `relationLoaded('grupoCategoria') === false` antes del `can()`)

**Conteo real**: 27 tests Pest nuevos (16 en `GrupoCategoriaTest.php` + 11 en
`CategoriaTest.php`). La diferencia frente a los "19-24" estimados se debe a
que 12.9 y 12.16 se implementaron como 2 tests cada uno (update ajeno +
delete ajeno) en vez de 1 combinado, y a que las 5 extras sugeridas
produjeron 6 tests reales (el boundary 100/101 se pidió para grupo Y para
categoría por separado, 2 bullets de la petición original pero 4 tests).

**Resultado**: `php artisan test` → 128 tests, 122 passed, 6 skipped (los
mismos 6 `markTestSkipped` pre-existentes de Jetstream: 2FA, registro, reset
de password, API tokens, verificación de email, eliminación de cuenta — ninguno
relacionado con este change, confirmado corriendo la suite completa
excluyendo los 2 archivos nuevos: 100 tests, 94 passed, 6 skipped, 0 failed,
mismos 6 skips). 0 failed, 0 regresiones. Delta: 101 → 128 (+27).

## 13. Verificación manual

Verificado con `migrate:fresh --seed` + tinker (13.1) y un script Playwright
end-to-end (13.2-13.11, `verify-grupo13-change5.js`) construido leyendo el
código fuente real de cada componente (`Index.vue`, `GrupoCategoriaCard.vue`,
`CategoriaModal.vue`, `ColorPicker.vue`, `Modal.vue`) antes de escribir
selectores — nunca se adivinó un texto de botón o un id de input. Hallazgo
clave de `Modal.vue`: `CategoriaModal` y `ConfirmationModal` están SIEMPRE
montados en el DOM (visibilidad controlada por `<dialog>` nativo +
`showModal()`), así que todas las interacciones con el modal activo se
escoparon con el selector `dialog[open]` para evitar ambigüedad con botones
homónimos de la página detrás (ambos modales comparten el texto "Eliminar"
en su botón de confirmación). 14/14 checks PASS, 0 errores de consola.

- [x] 13.1 migrate:fresh --seed → verificado con tinker: Personal 4 grupos (Ahorros 2, Calidad de vida 2, Gastos reales 3, Obligaciones inmediatas 3 = 10 categorías), Freelance USD 0 grupos
- [x] 13.2 Login María → /grupos-categorias → los 4 grupos con sus 10 categorías visibles
- [x] 13.3 Cambiar a Freelance USD → empty state ("Aún no tienes grupos de categorías")
- [x] 13.4 Crear grupo "Trabajo" con icono (Home) y color (#3b82f6) en Freelance USD
- [x] 13.5 Crear categoría "Coworking" dentro de "Trabajo" vía modal (botón "Este grupo no tiene categorías. Agregar una →")
- [x] 13.6 Editar categoría "Coworking" → "Coworking Premium" — verificado además que el modal precarga el nombre existente al abrir en modo edición
- [x] 13.7 Eliminar categoría "Coworking Premium" → vuelve al estado "sin categorías" del grupo
- [x] 13.8 Editar grupo "Trabajo" → color a #ec4899 — verificado releyendo el form de edición tras guardar (persistencia real, no solo el toast)
- [x] 13.9 Eliminar grupo "Trabajo" → desaparece de la vista; cascada soft delete ya probada exhaustivamente en Pest (12.8 y Extra 5), este paso confirma el flujo de UI completo (confirmación → redirect → re-render)
- [x] 13.10 Regresar a Personal → los 4 grupos y 10 categorías originales intactos (Freelance USD no los afectó)
- [x] 13.11 Duplicado en mismo grupo rechazado: crear "Emergencia" en grupo Ahorros (que ya la tiene) → mensaje "Ya existe una categoría con ese nombre en este grupo." visible dentro del modal, el cual permanece abierto (no se cierra en error)
- [x] 13.12 `php artisan test` → 128 tests, 122 passed, 6 skipped (pre-existentes de Jetstream), 0 failed

Tras la verificación Playwright (que crea/elimina datos reales en Freelance
USD), se corrió `migrate:fresh --seed` de nuevo para dejar la base de datos
de desarrollo en el estado pristino del escenario oficial, y se detuvo
`php artisan serve` + se limpió `public/hot`.