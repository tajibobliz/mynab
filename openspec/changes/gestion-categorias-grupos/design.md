# Design — Gestión de Categorías y Grupos

## Decisión 1: Dos entidades separadas (GrupoCategoria + Categoria)

**Contexto:** podríamos modelar todo como una tabla `categorias` con `parent_id` self-referencial (categoría padre = grupo, categoría hija = ítem). Es más flexible pero:
- Complica queries (JOIN recursivo o CTE para armar la jerarquía)
- Permite anidamiento infinito (no queremos sub-sub-categorías)
- La UI tiene que distinguir "es grupo o es categoría?" cada vez

**Decisión:** dos tablas separadas: `grupos_categorias` (padres) y `categorias` (hijos). Relación clara:
- `grupos_categorias` belongsTo `presupuestos`, hasMany `categorias`
- `categorias` belongsTo `grupos_categorias`
- Sin anidamiento adicional

**Consecuencia:** queries simples, UI clara, sin ambigüedad. El costo de tener 2 tablas es marginal.

**Alternativa descartada:** self-referencial con `parent_id`. Descartada por complejidad de query y riesgo de anidamiento indeseado.

## Decisión 2: Color e icono viven en el grupo, no en la categoría

**Contexto:** YNAB no pone iconos ni colores en categorías individuales — usa el color del grupo como identidad visual. Poner iconos por categoría requiere:
- UI de picker por cada creación (fricción)
- 20 categorías = 20 iconos que elegir vs 4 grupos = 4 iconos que elegir
- Los usuarios rara vez usan más de 3-4 iconos distintos, terminan repitiendo

**Decisión:** `grupos_categorias` tiene `color` (hex) y `icono` (string de whitelist Lucide). `categorias` no tiene ninguno de los dos — hereda visualmente del grupo.

**UI:** en Index, cada grupo se muestra con su color como acento (border-left, header background suave) y su icono al lado del nombre. Las categorías dentro se listan con un guión bullet o similar.

**Reutilización:** `IconoSelector` y `ColorPicker` (Change 2) se reutilizan tal cual para el formulario de Grupo. `HandleInertiaRequests` ya comparte `iconosPresupuesto` — extendemos con `iconosGrupoCategoria` (mismo pool o distinto, tu decisión al implementar).

**Alternativa descartada:** iconos por categoría individual. Descartada por complejidad de UX sin valor real.

## Decisión 3: Grupos y categorías pertenecen a UN presupuesto (vía el grupo)

**Contexto:** mismo patrón que Cuentas (Change 4). Cada presupuesto es un contexto aislado.

**Decisión:**
- `grupos_categorias.presupuesto_id` FK obligatoria con `cascadeOnDelete`
- `categorias.grupo_categoria_id` FK obligatoria con `cascadeOnDelete`
- Categoría NO tiene `presupuesto_id` directo — se resuelve vía `categoria.grupoCategoria.presupuesto`

**Consecuencia:** al eliminar un presupuesto, cascada elimina grupos y estos cascada eliminan categorías. Coherente con el modelo mental del usuario ("borré el presupuesto entero").

**Autorización de categoría:** `$user->id === $categoria->grupoCategoria->presupuesto->user_id` (2 saltos de relación). Requiere `eager loading` con `->load('grupoCategoria.presupuesto')` en el controller antes de autorizar.

## Decisión 4: Nombres únicos case-insensitive dentro del scope apropiado

**Contexto:** consistencia con Presupuestos, Cuentas, Tipos de cambio.

**Decisión:**
- Grupo: nombre único case-insensitive dentro del presupuesto activo
- Categoría: nombre único case-insensitive dentro del grupo (dos grupos pueden tener categorías con el mismo nombre — ej: "Emergencia" en "Ahorros" y "Emergencia" en "Salud")

**Implementación:** closure `whereRaw('LOWER(nombre) = ?', ...)` en el request, scoped al parent apropiado. Mismo patrón que Cuentas.

## Decisión 5: Soft delete para grupos y categorías

**Contexto:** Change 7 vinculará transacciones a categorías. Si se hace hard delete, o rompe FK, o cascada y se pierde histórico.

**Decisión:** ambas tablas usan `SoftDeletes`. Al eliminar grupo, sus categorías también soft-deletadas (con `deleted_at` seteado, no cascada hard). Restore es Fase 2.

**Consideración crítica:** el `cascadeOnDelete` de la FK aplica en HARD delete. Con soft delete de Eloquent, la cascada se hace vía eventos del modelo. Implementación: hook `deleting()` en modelo `GrupoCategoria` que soft-deletea sus categorías.

## Decisión 6: Orden alfabético automático (no manual)

**Contexto:** drag & drop es complejo de implementar bien (persistencia de índice, reordenamiento, UX en móvil). Los usuarios de MyNAB Fase 1 tendrán 4-8 grupos y 10-30 categorías totales — alfabético es suficiente.

**Decisión:**
- Grupos ordenados alfabéticamente por nombre en Index
- Categorías dentro del grupo ordenadas alfabéticamente por nombre
- Sin campo `orden` en las tablas

**Alternativa descartada:** campo `orden` con drag & drop. Fase 2.

## Decisión 7: Vista maestra en `/grupos-categorias`, no `/categorias`

**Contexto:** las categorías se ven siempre en el contexto de su grupo. Una URL `/categorias` independiente sería redundante y confusa.

**Decisión:**
- URL principal: `/grupos-categorias` (Index)
- URLs de acción de grupos: `/grupos-categorias/create`, `/grupos-categorias/{id}/edit`
- URLs de acción de categorías: `/categorias/create` (con `grupo_categoria_id` en query o form), `/categorias/{id}/edit`, `/categorias/{id}` (DELETE)
- NO hay `/categorias/index` ni `/categorias/{id}/show`

**Layout de Index (`/grupos-categorias`):**
- Header con botón "Nuevo grupo"
- Lista de grupos, cada grupo como una "card" con:
  - Icono + nombre del grupo (color de acento)
  - Botón "Editar grupo" + "Eliminar grupo"
  - Lista de categorías dentro (nombre + botón "Editar" + "Eliminar" por cada una)
  - Botón "Nueva categoría" al final de la lista
- Empty state global si no hay grupos

## Decisión 8: Modales para categorías, páginas para grupos

**Contexto:** los grupos tienen icono, color y nombre — formulario mediano que merece página propia. Las categorías solo tienen nombre — formulario mínimo que en página propia se ve vacío.

**Decisión:**
- Grupo: Create/Edit en páginas propias (`Pages/GruposCategorias/Create.vue` y `Edit.vue`)
- Categoría: Create/Edit en modales (dentro de la Index de grupos, sin cambiar de URL)

**Beneficios:**
- Menos navegación para lo simple (categoría)
- Contexto visual al crear categoría (ves el grupo padre)
- Sin sacrificio de UX para lo complejo (grupo)

**Alternativa descartada:** todo en modales o todo en páginas. Cada elección tiene tradeoffs; esta es la mezcla que mejor sirve el caso de uso.

## Decisión 9: HandleInertiaRequests comparte estructura anidada

**Contexto:** Change 7 necesitará un dropdown "Elegir categoría" al crear transacción. Ese dropdown debe agrupar por grupo (Óptico UX).

**Decisión:** share `gruposCategoriasDelPresupuestoActivo` con estructura:
```php
[
  ['id' => 1, 'nombre' => 'Obligaciones inmediatas', 'color' => '#ef4444', 'icono' => 'AlertCircle',
   'categorias' => [
     ['id' => 1, 'nombre' => 'Alquiler'],
     ['id' => 2, 'nombre' => 'Transporte'],
     ...
   ]],
  ...
]
```

Cargado con `loadMissing('presupuestoActivo.gruposCategorias.categorias')`.

**Consideración N+1:** con eager loading anidado, son 3 queries: presupuestos → grupos → categorías. Aceptable para volumen esperado.

## Decisión 10: Sin restricción de "grupo debe tener al menos 1 categoría"

**Contexto:** un grupo vacío es un estado legítimo — el usuario acaba de crearlo y va a agregar categorías después.

**Decisión:** grupo puede existir sin categorías. Al mostrar en Index, se muestra igual con mensaje "Este grupo no tiene categorías. Agregar una →" con CTA inline.

**Al eliminar un grupo con categorías:** cascada soft delete de sus categorías (Decisión 5). No preguntar al usuario "¿estás seguro? tiene 3 categorías" en Fase 1 — el ConfirmationModal genérico basta.