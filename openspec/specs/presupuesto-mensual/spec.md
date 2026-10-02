# presupuesto-mensual Specification

## Purpose
Permite al usuario presupuestar hacia adelante al estilo zero-based budgeting (YNAB). Cada mes, el usuario asigna todos sus ingresos a categorías específicas (sobres). El sistema calcula Ready to Assign (ingresos sin asignar), Activity (gastos del mes) y Available (saldo disponible por sobre) con rollover automático al mes siguiente.

## Requirements

### Requirement: Asignaciones por categoría y mes

El sistema SHALL permitir asignar un monto específico a cada categoría para un mes dado dentro de un presupuesto.

#### Scenario: Asignación nueva

- **WHEN** el usuario asigna Bs 1500 a Alquiler para octubre 2026
- **THEN** se crea una fila en `asignaciones` con esos valores

#### Scenario: Actualización de asignación existente

- **WHEN** el usuario cambia una asignación existente de Bs 1500 a Bs 1600
- **THEN** la fila existente se actualiza (no se crea una nueva)

### Requirement: Ready to Assign acumulado histórico

El sistema SHALL calcular Ready to Assign como la diferencia entre inflows acumulados y asignaciones acumuladas hasta el fin del mes consultado.

#### Scenario: RTA positivo

- **GIVEN** María con Bs 4210 de inflows en octubre y Bs 3610 asignados en octubre
- **WHEN** consulta /presupuesto-mensual?año=2026&mes=10
- **THEN** Ready to Assign = Bs 600

#### Scenario: RTA cero (zero-based alcanzado)

- **GIVEN** inflows totales == asignaciones totales
- **WHEN** consulta la vista mensual
- **THEN** RTA = 0 y se muestra en estado neutral

#### Scenario: RTA negativo (sobre-asignado)

- **GIVEN** María asigna más de lo que tiene de inflows
- **WHEN** consulta la vista
- **THEN** RTA < 0 y se muestra en rojo con mensaje "Has asignado más de lo disponible"

### Requirement: Activity por categoría y mes

El sistema SHALL calcular Activity como la suma de outflows y splits de esa categoría en el mes consultado.

#### Scenario: Activity incluye splits

- **GIVEN** una transacción split con Bs 250 a Comida básica + Bs 70 a Ropa
- **WHEN** se consulta Activity de Comida básica para ese mes
- **THEN** incluye los Bs 250 del split, no los Bs 320 del padre

### Requirement: Available con rollover

El sistema SHALL calcular Available como asignaciones acumuladas menos activity acumulada, incluyendo meses anteriores.

#### Scenario: Rollover de mes anterior

- **GIVEN** septiembre: Alquiler assigned Bs 1500, activity Bs 1500 → available Bs 0
- **GIVEN** octubre: Alquiler assigned Bs 1500, activity Bs 1500
- **WHEN** se consulta Available octubre
- **THEN** = Bs 0 (0 anterior + 1500 - 1500)

#### Scenario: Sobró del mes anterior

- **GIVEN** septiembre: Comida assigned Bs 800, activity Bs 600 → available Bs 200
- **GIVEN** octubre: Comida assigned Bs 800, activity Bs 250
- **WHEN** se consulta Available octubre
- **THEN** = Bs 750 (200 anterior + 800 - 250)

### Requirement: Overspent muestra Available negativo

El sistema SHALL mostrar Available negativo si Activity excede Assigned acumulado.

#### Scenario: Sobregasto

- **GIVEN** categoría con assigned Bs 500 y activity Bs 750 en el mes
- **WHEN** se consulta Available
- **THEN** = -Bs 250 y se muestra en rojo

### Requirement: Edit inline de Assigned

El sistema SHALL permitir editar Assigned con un click en la celda.

#### Scenario: Click y edit

- **WHEN** el usuario hace click en una celda Assigned
- **THEN** la celda se convierte en input numérico con autofocus
- **AND** al blur, el valor se guarda vía POST /asignaciones

### Requirement: Vista mensual con navegación

El sistema SHALL permitir navegar entre meses.

#### Scenario: Navegación anterior

- **GIVEN** vista mensual mostrando octubre 2026
- **WHEN** usuario clickea "Mes anterior"
- **THEN** la URL cambia a ?año=2026&mes=9 y la vista refleja septiembre

### Requirement: Autorización por dueño

El sistema SHALL restringir asignaciones al dueño del presupuesto.

#### Scenario: Intento ajeno

- **WHEN** usuario intenta crear asignación en categoría de otro presupuesto
- **THEN** validación falla (IDOR bloqueado por whereHas en Request)
