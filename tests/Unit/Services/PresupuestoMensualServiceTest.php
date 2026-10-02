<?php

use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\GrupoCategoria;
use App\Models\Presupuesto;
use App\Models\Transaccion;
use App\Models\TransaccionSplit;
use App\Services\PresupuestoMensualService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(PresupuestoMensualService::class);
    $this->presupuesto = Presupuesto::factory()->create(['moneda_base_codigo' => 'BOB']);
    $this->cuenta = Cuenta::factory()->for($this->presupuesto, 'presupuesto')->create();
    $this->grupo = GrupoCategoria::factory()->for($this->presupuesto, 'presupuesto')->create();
    $this->categoria = Categoria::factory()->for($this->grupo, 'grupoCategoria')->create();
});

test('RTA positivo con inflows e asignaciones conocidos', function () {
    Transaccion::factory()->inflow()->for($this->cuenta, 'cuenta')->create([
        'monto_moneda_base_centavos' => 421000, // Bs 4210
        'fecha_hora' => '2026-10-15 12:00:00',
    ]);

    Asignacion::factory()->for($this->presupuesto, 'presupuesto')->for($this->categoria, 'categoria')->create([
        'año' => 2026, 'mes' => 10, 'monto_centavos' => 361000, // Bs 3610
    ]);

    expect($this->service->calcularReadyToAssign($this->presupuesto, 2026, 10))->toBe(60000); // Bs 600
});

test('RTA cero cuando inflows y asignaciones coinciden exactamente', function () {
    Transaccion::factory()->inflow()->for($this->cuenta, 'cuenta')->create([
        'monto_moneda_base_centavos' => 100000,
        'fecha_hora' => '2026-10-05 12:00:00',
    ]);

    Asignacion::factory()->for($this->presupuesto, 'presupuesto')->for($this->categoria, 'categoria')->create([
        'año' => 2026, 'mes' => 10, 'monto_centavos' => 100000,
    ]);

    expect($this->service->calcularReadyToAssign($this->presupuesto, 2026, 10))->toBe(0);
});

test('RTA negativo cuando se asigna más de lo disponible', function () {
    Transaccion::factory()->inflow()->for($this->cuenta, 'cuenta')->create([
        'monto_moneda_base_centavos' => 50000,
        'fecha_hora' => '2026-10-05 12:00:00',
    ]);

    Asignacion::factory()->for($this->presupuesto, 'presupuesto')->for($this->categoria, 'categoria')->create([
        'año' => 2026, 'mes' => 10, 'monto_centavos' => 80000,
    ]);

    expect($this->service->calcularReadyToAssign($this->presupuesto, 2026, 10))->toBe(-30000);
});

test('Activity con outflow directo del mes', function () {
    Transaccion::factory()->outflow()->for($this->cuenta, 'cuenta')->create([
        'categoria_id' => $this->categoria->id,
        'monto_moneda_base_centavos' => 25000,
        'fecha_hora' => '2026-10-12 09:00:00',
    ]);

    // Outflow de OTRO mes: no debe contar (Activity es del mes exacto, no acumulado).
    Transaccion::factory()->outflow()->for($this->cuenta, 'cuenta')->create([
        'categoria_id' => $this->categoria->id,
        'monto_moneda_base_centavos' => 99999,
        'fecha_hora' => '2026-09-12 09:00:00',
    ]);

    expect($this->service->calcularActivity($this->categoria, 2026, 10))->toBe(25000);
});

test('Activity con split suma el monto del split en moneda base, no el del padre', function () {
    $padre = Transaccion::factory()->split()->for($this->cuenta, 'cuenta')->create([
        'monto_moneda_base_centavos' => 32000, // Bs 320 (Comida 250 + Ropa 70)
        'fecha_hora' => '2026-10-05 11:30:00',
    ]);

    $otraCategoria = Categoria::factory()->for($this->grupo, 'grupoCategoria')->create();

    TransaccionSplit::factory()->for($padre, 'transaccion')->create([
        'categoria_id' => $this->categoria->id,
        'monto_centavos' => 25000,
        'monto_moneda_base_centavos' => 25000,
    ]);
    TransaccionSplit::factory()->for($padre, 'transaccion')->create([
        'categoria_id' => $otraCategoria->id,
        'monto_centavos' => 7000,
        'monto_moneda_base_centavos' => 7000,
    ]);

    // Si el service sumara el monto del PADRE (32000) en vez de la línea del
    // split (25000), este test fallaría — es justo lo que verifica.
    expect($this->service->calcularActivity($this->categoria, 2026, 10))->toBe(25000)
        ->and($this->service->calcularActivity($otraCategoria, 2026, 10))->toBe(7000);
});

test('Available con rollover de mes anterior', function () {
    // Septiembre: assigned 800, activity 600 -> available 200 (no se afirma
    // directamente, se construye la data y se verifica el acumulado de octubre).
    Asignacion::factory()->for($this->presupuesto, 'presupuesto')->for($this->categoria, 'categoria')->create([
        'año' => 2026, 'mes' => 9, 'monto_centavos' => 80000,
    ]);
    Transaccion::factory()->outflow()->for($this->cuenta, 'cuenta')->create([
        'categoria_id' => $this->categoria->id,
        'monto_moneda_base_centavos' => 60000,
        'fecha_hora' => '2026-09-20 12:00:00',
    ]);

    // Octubre: assigned 800, activity 250.
    Asignacion::factory()->for($this->presupuesto, 'presupuesto')->for($this->categoria, 'categoria')->create([
        'año' => 2026, 'mes' => 10, 'monto_centavos' => 80000,
    ]);
    Transaccion::factory()->outflow()->for($this->cuenta, 'cuenta')->create([
        'categoria_id' => $this->categoria->id,
        'monto_moneda_base_centavos' => 25000,
        'fecha_hora' => '2026-10-10 12:00:00',
    ]);

    // Available octubre = 200 (rollover de sept) + 800 - 250 = 750.
    expect($this->service->calcularAvailable($this->categoria, 2026, 10))->toBe(75000);
});

test('Available negativo cuando activity excede lo asignado (overspent)', function () {
    Asignacion::factory()->for($this->presupuesto, 'presupuesto')->for($this->categoria, 'categoria')->create([
        'año' => 2026, 'mes' => 10, 'monto_centavos' => 50000,
    ]);
    Transaccion::factory()->outflow()->for($this->cuenta, 'cuenta')->create([
        'categoria_id' => $this->categoria->id,
        'monto_moneda_base_centavos' => 75000,
        'fecha_hora' => '2026-10-10 12:00:00',
    ]);

    expect($this->service->calcularAvailable($this->categoria, 2026, 10))->toBe(-25000);
});

test('obtenerVistaMensual retorna estructura completa y no escala en queries con más categorías', function () {
    Asignacion::factory()->for($this->presupuesto, 'presupuesto')->for($this->categoria, 'categoria')->create([
        'año' => 2026, 'mes' => 10, 'monto_centavos' => 150000,
    ]);
    Transaccion::factory()->outflow()->for($this->cuenta, 'cuenta')->create([
        'categoria_id' => $this->categoria->id,
        'monto_moneda_base_centavos' => 40000,
        'fecha_hora' => '2026-10-08 12:00:00',
    ]);
    Transaccion::factory()->inflow()->for($this->cuenta, 'cuenta')->create([
        'monto_moneda_base_centavos' => 421000,
        'fecha_hora' => '2026-10-02 08:00:00',
    ]);

    DB::enableQueryLog();
    $vista = $this->service->obtenerVistaMensual($this->presupuesto, 2026, 10);
    $queriesCon1Categoria = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($vista)->toHaveKeys(['readyToAssign', 'mesAño', 'grupos'])
        ->and($vista['readyToAssign'])->toBe(421000 - 150000)
        ->and($vista['mesAño'])->toBe(['año' => 2026, 'mes' => 10, 'nombreMes' => 'octubre'])
        ->and($vista['grupos'])->toHaveCount(1);

    $categoriaEnVista = $vista['grupos'][0]['categorias'][0];
    expect($categoriaEnVista['id'])->toBe($this->categoria->id)
        ->and($categoriaEnVista['assigned'])->toBe(150000)
        ->and($categoriaEnVista['activity'])->toBe(40000)
        ->and($categoriaEnVista['available'])->toBe(150000 - 40000);

    // Prueba de "sin N+1": agrego 7 categorías más (8 en total) y comparo la
    // cantidad de queries — debe ser IDÉNTICA, no proporcional a la cantidad
    // de categorías, o el patrón de agregación por lotes se rompió.
    // flushQueryLog() explícito: disableQueryLog() apaga el logging pero NO
    // vacía el log acumulado, así que sin este flush la segunda medición
    // arrastraría las queries de la primera (confirmado: sin el flush daba
    // 14 = 7+7, un falso N+1 causado por el propio script de verificación).
    Categoria::factory()->for($this->grupo, 'grupoCategoria')->count(7)->create();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->service->obtenerVistaMensual($this->presupuesto, 2026, 10);
    $queriesCon8Categorias = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queriesCon8Categorias)->toBe($queriesCon1Categoria);
});

test('Available acumula correctamente a través de 3 meses consecutivos', function () {
    // Agosto: assigned 500, activity 300 -> sobra 200.
    Asignacion::factory()->for($this->presupuesto, 'presupuesto')->for($this->categoria, 'categoria')->create([
        'año' => 2026, 'mes' => 8, 'monto_centavos' => 50000,
    ]);
    Transaccion::factory()->outflow()->for($this->cuenta, 'cuenta')->create([
        'categoria_id' => $this->categoria->id, 'monto_moneda_base_centavos' => 30000, 'fecha_hora' => '2026-08-15 12:00:00',
    ]);

    // Septiembre: assigned 300, activity 300 -> ni suma ni resta, pero el
    // rollover de agosto (200) sigue arrastrándose.
    Asignacion::factory()->for($this->presupuesto, 'presupuesto')->for($this->categoria, 'categoria')->create([
        'año' => 2026, 'mes' => 9, 'monto_centavos' => 30000,
    ]);
    Transaccion::factory()->outflow()->for($this->cuenta, 'cuenta')->create([
        'categoria_id' => $this->categoria->id, 'monto_moneda_base_centavos' => 30000, 'fecha_hora' => '2026-09-15 12:00:00',
    ]);

    // Octubre: assigned 100, sin activity.
    Asignacion::factory()->for($this->presupuesto, 'presupuesto')->for($this->categoria, 'categoria')->create([
        'año' => 2026, 'mes' => 10, 'monto_centavos' => 10000,
    ]);

    // Available octubre = (500+300+100) asignado acumulado - (300+300) activity acumulada = 900 - 600 = 300.
    expect($this->service->calcularAvailable($this->categoria, 2026, 10))->toBe(30000);
});

test('Available cuando falta asignación en un mes intermedio (fila ausente, no cero explícito)', function () {
    // Septiembre: assigned 1000, sin activity -> sobra 1000.
    Asignacion::factory()->for($this->presupuesto, 'presupuesto')->for($this->categoria, 'categoria')->create([
        'año' => 2026, 'mes' => 9, 'monto_centavos' => 100000,
    ]);

    // Octubre: SIN fila de asignación (nunca se asignó nada ese mes, no es
    // un registro con monto=0) + activity 400.
    Transaccion::factory()->outflow()->for($this->cuenta, 'cuenta')->create([
        'categoria_id' => $this->categoria->id, 'monto_moneda_base_centavos' => 40000, 'fecha_hora' => '2026-10-15 12:00:00',
    ]);

    // Available octubre = 1000 (acumulado, la fila ausente de octubre no
    // resta nada, un SUM sobre filas inexistentes simplemente no las cuenta)
    // - 400 = 600.
    expect($this->service->calcularAvailable($this->categoria, 2026, 10))->toBe(60000);
});

test('RTA acumula asignaciones de múltiples meses anteriores, no solo del mes consultado', function () {
    Transaccion::factory()->inflow()->for($this->cuenta, 'cuenta')->create([
        'monto_moneda_base_centavos' => 500000, 'fecha_hora' => '2026-09-01 08:00:00',
    ]);

    // Asignado repartido en 2 meses distintos (septiembre y octubre).
    Asignacion::factory()->for($this->presupuesto, 'presupuesto')->for($this->categoria, 'categoria')->create([
        'año' => 2026, 'mes' => 9, 'monto_centavos' => 200000,
    ]);
    Asignacion::factory()->for($this->presupuesto, 'presupuesto')->for($this->categoria, 'categoria')->create([
        'año' => 2026, 'mes' => 10, 'monto_centavos' => 150000,
    ]);

    // RTA octubre debe restar AMBAS asignaciones (acumulado), no solo la de octubre.
    expect($this->service->calcularReadyToAssign($this->presupuesto, 2026, 10))->toBe(500000 - 200000 - 150000);
});
