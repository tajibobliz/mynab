## 1. Migración y modelo

- [x] 1.1 Crear migración `create_beneficiarios_table`:
      - id
      - presupuesto_id (FK presupuestos.id, cascadeOnDelete)
      - nombre (string 100)
      - notas (text, nullable)
      - timestamps
      - softDeletes
- [x] 1.2 Índice en `presupuesto_id` para queries del index
- [x] 1.3 Crear modelo `app/Models/Beneficiario.php`:
      - use SoftDeletes, use HasFactory
      - fillable: presupuesto_id, nombre, notas
      - relación: presupuesto() belongsTo
      - Sin `$table` explícito: verificado con tinker que `Str::snake('Beneficiario')='beneficiario'` pluraliza a `'beneficiarios'`, coincide con la tabla (mismo caso que `Categoria`)
- [x] 1.4 Modificación cruzada: Presupuesto::beneficiarios() hasMany

**Verificación tinker**: tabla inferida correcta sin `$table`; `$fillable` completo; `Presupuesto::beneficiarios()` resuelve a `Beneficiario`; creación + relación inversa (`$b->presupuesto->nombre`) + soft delete (desaparece de query normal, visible con `withTrashed()`) confirmados end-to-end antes de seguir al Grupo 2.

## 2. Policy

- [x] 2.1 BeneficiarioPolicy:
      - viewAny/create = true
      - view/update/delete = $user->id === $beneficiario->presupuesto->user_id
      - restore/forceDelete = false (scaffold default)

**Verificación tinker (matriz cruzada, 12 checks, todos OK):** María (dueña) → view/update/delete/viewAny/create todos `true`, restore/forceDelete `false`. Usuario ajeno → view/update/delete/restore/forceDelete todos `false`. Auto-discovery confirmado: `Gate::getPolicyFor(Beneficiario::class)` resuelve a `App\Policies\BeneficiarioPolicy` sin registrar nada en `AuthServiceProvider` (convención de nombres `Beneficiario`→`BeneficiarioPolicy` ya cubierta por Laravel). `php artisan test`: 128/128 (122 passed + 6 skipped, 0 failed) — sin regresiones.

## 3. Requests

- [x] 3.1 StoreBeneficiarioRequest:
      - nombre required, string, max 100, unique case-insensitive por presupuesto
      - notas nullable, string, max 1000
      - prepareForValidation auto-inyecta presupuesto_id del activo
      - authorize can('create', Beneficiario::class)
- [x] 3.2 UpdateBeneficiarioRequest:
      - Igual pero unique con whereKeyNot (ignore del propio registro)
      - NO incluye presupuesto_id (fija tras crear)
      - authorize can('update', $this->route('beneficiario'))
- [x] 3.3 Mensajes en español

**Nota de implementación:** el closure `nombreUnicoRule()` sigue el patrón ya
consolidado en `Cuenta`/`GrupoCategoria`/`Categoria` (`Closure` tipado,
`Str::lower()`, `whereKeyNot()` en vez de `where('id','!=',...)` manual, sin
`whereNull('deleted_at')` explícito porque el scope global de `SoftDeletes`
ya lo excluye) en vez de un `callable` genérico, por consistencia con los 3
Requests anteriores del proyecto.

**Verificación con FormRequest::create() + setUserResolver + setRouteResolver (10/10 OK):**

| # | Caso | Resultado |
|---|------|-----------|
| 1 | Store válido pasa | OK |
| 2 | Store sin nombre falla | OK — "El nombre es requerido." |
| 3 | Store nombre >100 chars falla | OK — "El nombre no puede tener más de 100 caracteres." |
| 4 | Store duplicado case-insensitive mismo presupuesto falla | OK — "Ya existe un beneficiario con ese nombre en este presupuesto." |
| 5 | Store mismo nombre OTRO presupuesto pasa | OK |
| 6 | Store notas null pasa | OK |
| 7 | Store notas >1000 chars falla | OK — "Las notas no pueden tener más de 1000 caracteres." |
| 8 | Update válido pasa | OK |
| 9 | Update nombre igual al propio pasa (whereKeyNot) | OK |
| 10 | Update colisiona con otro beneficiario del mismo presupuesto falla | OK — mismo mensaje que el caso 4 |

**Bug encontrado y corregido en el SCRIPT de verificación, no en el Request**
(mismo tipo de hallazgo que el Grupo 5 de Change 5): el caso 5 falló en el
primer intento porque el script pasaba `presupuesto_id` directo en el
payload para simular "otro presupuesto" — pero `prepareForValidation()`
SIEMPRE sobreescribe ese campo con `$this->user()->presupuesto_activo_id`
real (por diseño, nunca es client-controlled), así que el payload se
ignoraba silenciosamente y la prueba corría contra el presupuesto
equivocado. Corregido mutando en memoria (sin persistir)
`$maria->presupuesto_activo_id` antes de la llamada — confirma que el
Request funciona exactamente como debe, el defecto estaba en cómo se
simulaba el escenario.

`php artisan test`: 128/128 (122 passed + 6 skipped, 0 failed). Sin datos
residuales: `Beneficiario::count()=0`, `presupuesto_activo_id` de María
intacto en BD (la mutación del caso 5 fue solo en memoria).

## 4. Controller

- [x] 4.1 BeneficiarioController con index/create/store/edit/update/destroy
- [x] 4.2 Guard presupuestoActivoOrRedirect() en index/create/store
- [x] 4.3 index: Beneficiario::where('presupuesto_id', activo)->orderBy('nombre')->get()
- [x] 4.4 update: array_diff_key defensivo contra presupuesto_id

**Nota de implementación:** el guard `presupuestoActivoOrRedirect()` devuelve
`Presupuesto|RedirectResponse` (no `?Presupuesto` + construcción manual del
redirect en cada método) — es el patrón real ya consolidado en
`CuentaController`/`GrupoCategoriaController`, más DRY que reconstruir el
mismo `redirect()->route('dashboard')->with(...)` en 3 sitios. `edit()` no
pasa por el guard (coincide con `CuentaController::edit()`): solo necesita
`authorize('update', ...)`, el presupuesto activo es irrelevante ahí porque
la autorización ya resuelve ownership vía el beneficiario mismo.

**Verificación con controller instanciado directo + FormRequest::validateResolved() (9/9 OK):**

| # | Caso | Resultado |
|---|------|-----------|
| 1 | index sin presupuesto activo → RedirectResponse + flash.danger exacto | OK |
| 2 | store válido crea con presupuesto_id correcto + redirect con flash.success | OK |
| 3 | store con presupuesto_id manipulado en payload → ignorado, queda en el activo real | OK |
| 4 | update con presupuesto_id distinto en payload → ignorado (array_diff_key) | OK |
| 5 | destroy → soft delete (invisible en query normal, visible con withTrashed()) | OK |
| 6 | destroy ajeno → lanza AuthorizationException (403), registro intacto | OK |

**Nota técnica del harness de verificación:** para probar el controller
aislado antes de que existan las rutas reales (Grupo 5), hubo que registrar
una ruta `beneficiarios.index` efímera en memoria vía `Route::get(...)->name(...)`
dentro del propio script de tinker. Esto solo no bastó: `route()`/`Route::has()`
seguían fallando con `RouteNotFoundException` hasta llamar explícitamente a
`app('router')->getRoutes()->refreshNameLookups()` — el índice de nombres de
rutas no se recalcula solo al registrar una ruta fuera del boot normal de
`RouteServiceProvider`. Anotado como aprendizaje reutilizable para cualquier
verificación futura de un controller antes de que su archivo de rutas exista.

**CHECKPOINT CRÍTICO**: `php artisan test` → 128/128 (122 passed + 6 skipped,
0 failed) confirmado ANTES de pasar al Grupo 5. Sin regresiones en Changes 1-5.
BD de desarrollo verificada limpia tras la verificación (0 beneficiarios,
2 presupuestos, 1 usuario, `presupuesto_activo_id` de María intacto).

## 5. Rutas

- [x] 5.1 Route::resource('beneficiarios', BeneficiarioController::class)->except('show')
- [x] 5.2 "beneficiarios" no tiene guión → wildcard {beneficiario} resuelve 
      naturalmente, no necesita ->parameters()
- [x] 5.3 Verificación empírica del binding igual que Change 4 Grupo 6

**`route:list --name=beneficiarios`** (6 rutas, como se esperaba):
```
GET|HEAD beneficiarios .......................... beneficiarios.index
POST beneficiarios ............................... beneficiarios.store
GET|HEAD beneficiarios/create .................... beneficiarios.create
PUT|PATCH beneficiarios/{beneficiario} ........... beneficiarios.update
DELETE beneficiarios/{beneficiario} .............. beneficiarios.destroy
GET|HEAD beneficiarios/{beneficiario}/edit ....... beneficiarios.edit
```
Wildcard `{beneficiario}` confirmado singular, sin `->parameters()`.

**Verificación empírica del binding real (no solo route:list):** match +
`ImplicitRouteBinding::resolveForRoute()` (el mismo método estático que usa
el middleware `SubstituteBindings` en producción) sobre un `PUT
/beneficiarios/{id}` real → resuelve la instancia correcta de `Beneficiario`
por ID y nombre. **Primer intento dio FAIL falso**: `$route->bind($request)`
por sí solo solo parsea la URI en parámetros raw (string), no resuelve el
modelo — confundí eso con el binding real. Corregido invocando
`ImplicitRouteBinding::resolveForRoute()` explícitamente, que es el paso que
realmente ejecuta el middleware. Mismo tipo de hallazgo que los Grupos 3 y 4:
verificar la verificación misma antes de concluir.

**Curl real sin sesión:**
- `GET /beneficiarios` → `302` → `Location: /login` ✅
- `POST /beneficiarios` sin token CSRF → `419` ✅ (CSRF se evalúa antes que `auth:sanctum` en el stack del grupo `web`)

`php artisan test`: 128/128 (122 passed + 6 skipped, 0 failed). Sin regresiones.

## 6. HandleInertiaRequests

- [x] 6.1 Agregar al share: `beneficiariosDelPresupuestoActivo` closure 
      con loadMissing('presupuestoActivo.beneficiarios')

**Verificación N+1 con DB::listen (instancia fresca de usuario, sin relaciones cacheadas):**

| Escenario | Queries de `beneficiariosDelPresupuestoActivo()` | Resultado |
|---|---|---|
| 0 beneficiarios (estado actual, antes del seeder) | 1 (`select * from beneficiarios where presupuesto_id in (1) and deleted_at is null`) | 0 filas |
| 8 beneficiarios ad-hoc (simulando el Grupo 9) | 1 (misma query) | 8 filas |

Confirmado: 1 sola query sin importar la cantidad de filas (no es O(N) por
fila) — `loadMissing` resuelve `beneficiarios` en una sola consulta porque
`presupuestoActivo` ya viene cargado de la línea inicial del `share()`
(`loadMissing(['presupuestos', 'presupuestoActivo.monedaBase'])`), igual que
se verificó para `cuentasDelPresupuestoActivo` en Change 4 y
`gruposCategoriasDelPresupuestoActivo` en Change 5.

`php artisan test`: 128/128 (122 passed + 6 skipped, 0 failed). BD limpia tras la verificación (0 beneficiarios residuales).

## 7. Vistas Inertia

- [x] 7.1 Pages/Beneficiarios/Index.vue:
      - Lista alfabética estilizada (NO grid — sin color/icono por item, una lista limpia es más legible)
      - Cada item: nombre semibold + notas (si hay, `line-clamp-1`) + botones Editar/Eliminar
      - Empty state con icono `Users` (verificado export real en lucide-vue-next) + CTA
      - Botón "Nuevo beneficiario" arriba con icono `Plus`
      - `ConfirmationModal` reutilizado para eliminar (mensaje con nombre exacto entre comillas)
      - Título "Beneficiarios"
- [x] 7.2 Pages/Beneficiarios/Create.vue:
      - Formulario centrado max-w-2xl
      - Campos: nombre (TextInput) + notas (Textarea nuevo, rows=4, maxlength=1000)
      - Contador de caracteres con 3 umbrales de color
      - Nota: "Este beneficiario se creará en el presupuesto {activo.nombre}"
      - Botones: Cancelar + Crear beneficiario
- [x] 7.3 Pages/Beneficiarios/Edit.vue:
      - Igual a Create, sin nota de "se creará en X", título dinámico, botón "Guardar cambios"
- [x] 7.4 Componente BeneficiarioCard.vue: **decisión confirmada en el plan — NO se crea.** Lista inline en Index.vue (nombre + notas + 2 botones no justifica la indirección de un componente separado, a diferencia de CuentaCard/GrupoCategoriaCard que llevan color/icono/tipo).

**Componente nuevo:** `resources/js/Components/Textarea.vue` — calcado de `TextInput.vue` (mismo set de clases, mismo patrón de `ref` + `onMounted` para autofocus, `defineExpose({ focus })`). `rows` es la única prop declarada (default 3); `maxlength`/`placeholder`/`disabled` caen por fallthrough automático de Vue al `<textarea>` raíz, igual que `TextInput.vue` no declara `required`/`autofocus`/`type` como props.

**Corrección de clases de color:** el recordatorio mencionaba `text-warning`/`text-danger`, pero el `tailwind.config.js` real anida esos tokens bajo `status` (`colors.status.warning`, `colors.status.danger` — igual que `ConfirmationModal.vue` ya usa `text-status-danger`). Usé `text-status-warning`/`text-status-danger`, las clases que realmente existen en este proyecto.

**Verificación con Playwright (9/9 PASS, 0 errores de consola)** — fui más allá de la verificación mínima pedida (solo mirar empty state + formulario) y corrí el flujo CRUD completo vía URL directa (sin depender del NavLink del Grupo 8 ni del seeder del Grupo 9, creando/editando/borrando un beneficiario ad-hoc desde la UI):

| Check | Resultado |
|---|---|
| Index: empty state visible | PASS |
| Create: nota de presupuesto activo visible | PASS |
| Create: contador de caracteres refleja longitud en vivo | PASS |
| Create: submit crea y redirige a index con el item visible | PASS |
| Edit: nombre y notas precargados correctamente | PASS (x2) |
| Edit: submit actualiza y redirige a index | PASS |
| Delete: mensaje de confirmación con nombre exacto entre comillas | PASS |
| Delete: soft delete confirmado, vuelve al empty state | PASS |

`npm run build`: limpio, 0 warnings relevantes. `php artisan test`: 128/128
(122 passed + 6 skipped, 0 failed) — las vistas no afectan tests backend.
BD de desarrollo limpiada tras la verificación (el soft-delete del
Playwright dejó 1 fila trashed, forzada a `forceDelete()` para no ensuciar
el Grupo 9).

## 8. NavLink

- [x] 8.1 Agregar "Beneficiarios" al navbar entre "Categorías" y "Monedas"
      - Orden final: Dashboard | Presupuestos | Cuentas | Categorías | 
        Beneficiarios | Monedas | Tipos de cambio

**Archivo modificado:** `resources/js/Layouts/AppLayout.vue` — `NavLink` (desktop) y `ResponsiveNavLink` (móvil) agregados en el mismo punto, `:active="route().current('beneficiarios.*')"` (patrón único, sin variantes muertas).

**Verificación Playwright (8/8 PASS, 0 errores de consola):**

| Check | Viewport | Resultado |
|---|---|---|
| Orden del navbar correcto (7 links) | Desktop 1280px | PASS |
| Click en NavLink navega a /beneficiarios | Desktop | PASS |
| Resaltado activo (`border-accent-primary`) en index | Desktop | PASS |
| Resaltado activo persiste en /beneficiarios/create (pattern `beneficiarios.*`) | Desktop | PASS |
| Links horizontales ocultos (<768px) | Mobile 375px | PASS |
| Menú hamburguesa muestra "Beneficiarios" | Mobile | PASS |
| Orden del menú responsive correcto | Mobile | PASS |
| Click en ResponsiveNavLink navega | Mobile | PASS |

`php artisan test`: 128/128 (122 passed + 6 skipped, 0 failed).

## 9. Factory y seeder

- [x] 9.1 BeneficiarioFactory: nombre fake company, notas 50% null, presupuesto via factory
- [x] 9.2 Actualizar PresupuestoSeeder con 8 beneficiarios de María en Personal:
      - Dueño del alquiler (fijo mensual)
      - SIM Entel (recarga mensual)
      - Netflix (suscripción)
      - Spotify (suscripción)
      - Supermercado Hipermaxi
      - Mi barbero
      - Empresa X (sueldo mensual)
      - Cliente freelance A
- [x] 9.3 Freelance USD sin beneficiarios (empty state para test)

**Archivos:** `database/factories/BeneficiarioFactory.php` (nuevo), `database/seeders/PresupuestoSeeder.php` (+1 bloque `createMany`, insertado después de los 4 grupos/10 categorías y antes de crear "Freelance USD").

**Verificación `migrate:fresh --seed` + tinker (5/5 exacto):**
```
personal->beneficiarios->count()  = 8   (esperado 8)
nombres (alfabético): Cliente freelance A, Dueño del alquiler, Empresa X (sueldo),
  Mi barbero, Netflix, SIM Entel, Spotify, Supermercado Hipermaxi
freelance->beneficiarios->count() = 0   (esperado 0)
Mi barbero->notas = "Avenida Beni, cerca del semáforo"  (exacto)
Resto de beneficiarios con notas no-null = 0  (solo Mi barbero tiene nota)
```

**Verificación visual con Playwright (5/5 PASS, 0 errores de consola):** 8
beneficiarios en orden alfabético en `/beneficiarios`; "Mi barbero" muestra
la nota en una segunda línea bajo el nombre; "Netflix" (sin notas) no
renderiza esa segunda línea (`v-if="beneficiario.notas"` funciona);
Freelance USD → empty state con icono `Users` visible.

`php artisan test`: 128/128 (122 passed + 6 skipped, 0 failed).

## 10. Tests (Pest)

- [x] 10.1 usuario ve solo beneficiarios de su presupuesto activo
- [x] 10.2 sin presupuesto activo → redirect dashboard
- [x] 10.3 no autenticado → redirect login
- [x] 10.4 store crea válido asignado al presupuesto activo
- [x] 10.5 store rechaza nombre duplicado case-insensitive mismo presupuesto
- [x] 10.6 store permite mismo nombre en OTRO presupuesto del mismo usuario
- [x] 10.7 store rechaza nombre vacío
- [x] 10.8 store rechaza nombre > 100 chars
- [x] 10.9 store acepta notas null
- [x] 10.10 store acepta notas string
- [x] 10.11 store rechaza notas > 1000 chars
- [x] 10.12 update permite editar nombre y notas
- [x] 10.13 update ignora presupuesto_id (fija tras crear)
- [x] 10.14 destroy soft delete
- [x] 10.15 policy: no puede editar ajeno (403)
- [x] 10.16 policy: no puede eliminar ajeno (403)
- [x] 10.17 cascade delete: forceDelete presupuesto borra sus beneficiarios

**Archivo:** `tests/Feature/BeneficiarioTest.php` (22 tests, un solo archivo —
entidad única sin jerarquía, a diferencia de Change 5). Reutiliza
`usuarioConPresupuestoActivo()` sin redeclarar. Todo mensaje de error se
verificó vía `assertSessionHasErrors(['campo' => 'mensaje exacto'])` leyendo
el `messages()` real del Request, nunca `assertInvalid` genérico (lección
del Grupo 5, Change 5).

**Extras (5 sugeridos → 5 tests reales, sin pares boundary esta vez):**
- [x] Extra 1: nombre con exactamente 100 caracteres es válido
- [x] Extra 2: notas con exactamente 1000 caracteres es válido
- [x] Extra 3: store con `presupuesto_id` manipulado en el payload es ignorado (IDOR — mismo patrón verificado en el Grupo 4)
- [x] Extra 4: policy resuelve `update` con lazy load, sin relación `presupuesto` precargada
- [x] Extra 5: beneficiario soft-deleted no aparece en el index

**Resultado:** `php artisan test` → 150 tests, 144 passed, 6 skipped
(los mismos pre-existentes de Jetstream), 0 failed. Delta exacto: 128 → 150
(+22). Sin regresiones — confirmado corriendo primero solo
`BeneficiarioTest.php` (22/22 verde) y luego la suite completa.

## 11. Verificación manual

Verificado con `migrate:fresh --seed` + tinker (11.1) y un script Playwright
end-to-end (11.2-11.8, `verify-grupo11-beneficiarios.js`), más los 4 checks
adicionales pedidos (contador de caracteres, maxlength nativo, mensaje del
ConfirmationModal, flash messages). 18/18 checks PASS, 0 errores de consola.

- [x] 11.1 migrate:fresh --seed → verificado con tinker: 8 beneficiarios en Personal, 0 en Freelance USD
- [x] 11.2 Login María → /beneficiarios → 8 beneficiarios en orden alfabético exacto
- [x] 11.3 Cambiar a Freelance USD → empty state ("Aún no tienes beneficiarios en este presupuesto")
- [x] 11.4 Crear "Cliente Nuevo" con notas en Freelance USD → aparece en la lista + flash "Beneficiario creado."
- [x] 11.5 Editar notas de "Cliente Nuevo" → precarga correcta + actualización visible + flash "Beneficiario actualizado."
- [x] 11.6 Eliminar "Cliente Nuevo" → vuelve al empty state + flash "Beneficiario eliminado." (soft delete, consistente con Pest 10.14/14)
- [x] 11.7 Duplicado rechazado: "NETFLIX" contra "Netflix" existente en Personal → mensaje exacto visible, formulario permanece abierto (no redirige)
- [x] 11.8 Volver a Personal → los 8 beneficiarios originales intactos, mismo orden
- [x] 11.9 `php artisan test` → 150 tests, 144 passed, 6 skipped (pre-existentes de Jetstream), 0 failed

**Checks adicionales pedidos:**
- Contador de caracteres: gris (`text-text-secondary`) <900, ámbar (`text-status-warning`) ≥900, rojo (`text-status-danger`) ≥1000 — los 3 umbrales verificados con el conteo real mostrado ("1000 / 1000 caracteres").
- `maxlength="1000"` nativo del `<textarea>` bloquea la escritura real más allá de 1000 caracteres — verificado con `pressSequentially()` (tecleo simulado real, no `fill()` que asigna `.value` directo y evita la restricción del navegador).
- `ConfirmationModal` muestra el mensaje exacto `¿Eliminar el beneficiario "Cliente Nuevo"?`.
- Flash messages (banner superior, `Banner.vue`) confirmados tras crear, editar y eliminar, con el texto exacto de cada controller.

Tras la verificación (que crea/edita/elimina datos reales en Freelance USD),
se corrió `migrate:fresh --seed` de nuevo para dejar la base de datos de
desarrollo en el estado pristino del escenario oficial, y se detuvo
`php artisan serve` + se limpió `public/hot`.