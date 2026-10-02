<?php

namespace Database\Seeders;

use App\Models\Beneficiario;
use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\User;
use App\Services\CalculadoraTransaccionService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeder separado de PresupuestoSeeder (a diferencia de lo que sugería
 * tasks.md 11.3) por una razón de orden: 2 de las 18 transacciones del
 * escenario oficial están en USDT y necesitan una tasa de cambio real para
 * calcular monto_moneda_base_centavos. DatabaseSeeder corre
 * PresupuestoSeeder -> TipoCambioSeeder -> este seeder, en ese orden
 * (TipoCambioSeeder depende de que María ya exista, creada por
 * PresupuestoSeeder, así que no se puede invertir ese orden). Metiendo las
 * transacciones directamente en PresupuestoSeeder, las tasas de cambio
 * todavía no existirían al momento de calcularlas. Un seeder nuevo que
 * corre al final resuelve esto sin tocar los 2 seeders ya archivados de
 * Changes 3 y 4/5/6, y de paso permite usar el servicio real
 * (CalculadoraTransaccionService) para los montos convertidos, en vez de
 * hardcodear una tasa a mano.
 */
class TransaccionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $maria = User::where('email', 'maria@example.com')->firstOrFail();
        $personal = $maria->presupuestos()->where('nombre', 'Personal')->firstOrFail();

        $bnb = $personal->cuentas()->where('nombre', 'BNB Checking')->firstOrFail();
        $efectivo = $personal->cuentas()->where('nombre', 'Efectivo')->firstOrFail();
        $binance = $personal->cuentas()->where('nombre', 'Binance USDT')->firstOrFail();

        $categoriaPorNombre = fn (string $nombre) => Categoria::whereHas(
            'grupoCategoria',
            fn ($q) => $q->where('presupuesto_id', $personal->id)
        )->where('nombre', $nombre)->firstOrFail();

        $beneficiarioPorNombre = fn (string $nombre) => Beneficiario::where('presupuesto_id', $personal->id)
            ->where('nombre', $nombre)
            ->firstOrFail();

        $alquiler = $categoriaPorNombre('Alquiler');
        $transporte = $categoriaPorNombre('Transporte');
        $comidaBasica = $categoriaPorNombre('Comida básica');
        $ropa = $categoriaPorNombre('Ropa');
        $cortesDePelo = $categoriaPorNombre('Cortes de pelo');
        $regalos = $categoriaPorNombre('Regalos');
        $salidas = $categoriaPorNombre('Salidas');
        $suscripciones = $categoriaPorNombre('Suscripciones');

        $duenoDelAlquiler = $beneficiarioPorNombre('Dueño del alquiler');
        $simEntel = $beneficiarioPorNombre('SIM Entel');
        $netflix = $beneficiarioPorNombre('Netflix');
        $spotify = $beneficiarioPorNombre('Spotify');
        $miBarbero = $beneficiarioPorNombre('Mi barbero');
        $hipermaxi = $beneficiarioPorNombre('Supermercado Hipermaxi');
        $empresaX = $beneficiarioPorNombre('Empresa X (sueldo)');
        $clienteFreelanceA = $beneficiarioPorNombre('Cliente freelance A');

        $service = app(CalculadoraTransaccionService::class);
        $monedaBase = $personal->moneda_base_codigo;

        // Ancla fija (no now()): el escenario representa octubre 2026
        // específicamente (design.md de Change 8 asume Activity real en ese
        // mes exacto para 10 categorías). Con now(), el escenario entero se
        // corre hacia atrás cada día que pasa desde que se escribió este
        // seeder, y en cuanto "hoy" avanza más allá del 1-2 de octubre, los
        // offsets de hasta 20 días caen en septiembre en vez de octubre —
        // exactamente lo que pasó al verificar el Grupo 11 de Change 8 (17
        // de 18 transacciones aparecían en septiembre).
        //
        // La ancla NO puede ser 2026-10-01: el offset máximo usado abajo es
        // 20 días, y restar días a un ancla de 1ro de octubre solo puede
        // retroceder hacia SEPTIEMBRE (nunca hacia adelante) — el problema
        // habría quedado igual de roto, solo que fijo en vez de a la
        // deriva. Para que los 20 días de offset caigan DENTRO de octubre
        // (1-21), el ancla necesita margen: se fija en 2026-10-21, de forma
        // que fecha(20) = 1ro de octubre (límite) y fecha(1) = 20 de
        // octubre. Modificación cruzada mínima a Change 7, aprobada y
        // documentada en el commit de Change 8.
        $ancla = Carbon::parse('2026-10-21 12:00:00');
        $fecha = fn (int $diasAtras, string $hora = '12:00') => Carbon::parse(
            $ancla->copy()->subDays($diasAtras)->toDateString().' '.$hora
        );

        /**
         * Crea un outflow/inflow simple, calculando monto_moneda_base_centavos
         * y tasa_cambio_aplicada con el service real (igual que el controller).
         */
        $crear = function (array $datos) use ($service, $monedaBase, $maria) {
            $cuenta = $datos['cuenta'];
            $fechaHora = $datos['fecha_hora'];

            [$montoBase, $tasa] = $service->resolverMontoMonedaBase(
                $datos['monto_centavos'],
                $cuenta->moneda_codigo,
                $monedaBase,
                $maria->id,
                $fechaHora,
            );

            return $cuenta->transacciones()->create([
                'categoria_id' => $datos['categoria_id'] ?? null,
                'beneficiario_id' => $datos['beneficiario_id'] ?? null,
                'tipo' => $datos['tipo'],
                'monto_centavos' => $datos['monto_centavos'],
                'monto_moneda_base_centavos' => $montoBase,
                'tasa_cambio_aplicada' => $tasa,
                'fecha_hora' => $fechaHora,
                'notas' => $datos['notas'] ?? null,
                'es_split' => false,
            ]);
        };

        // --- Inflows (2) ---
        $crear([
            'cuenta' => $bnb, 'tipo' => 'inflow', 'beneficiario_id' => $empresaX->id,
            'monto_centavos' => 350000, 'fecha_hora' => $fecha(20, '08:30'),
        ]);
        $crear([
            'cuenta' => $binance, 'tipo' => 'inflow', 'beneficiario_id' => $clienteFreelanceA->id,
            'monto_centavos' => 10000, 'fecha_hora' => $fecha(1, '19:00'), // USDT 100.00
        ]);

        // --- Outflows fijos mensuales (4) ---
        $crear([
            'cuenta' => $bnb, 'tipo' => 'outflow', 'categoria_id' => $alquiler->id, 'beneficiario_id' => $duenoDelAlquiler->id,
            'monto_centavos' => 150000, 'fecha_hora' => $fecha(19, '09:00'),
        ]);
        $crear([
            'cuenta' => $bnb, 'tipo' => 'outflow', 'categoria_id' => $suscripciones->id, 'beneficiario_id' => $simEntel->id,
            'monto_centavos' => 5000, 'fecha_hora' => $fecha(15, '10:15'),
        ]);
        $crear([
            'cuenta' => $bnb, 'tipo' => 'outflow', 'categoria_id' => $suscripciones->id, 'beneficiario_id' => $netflix->id,
            'monto_centavos' => 4500, 'fecha_hora' => $fecha(12, '07:00'),
        ]);
        $crear([
            'cuenta' => $bnb, 'tipo' => 'outflow', 'categoria_id' => $suscripciones->id, 'beneficiario_id' => $spotify->id,
            'monto_centavos' => 2500, 'fecha_hora' => $fecha(12, '07:05'),
        ]);

        // --- Outflows variables (8) ---
        $crear([
            'cuenta' => $efectivo, 'tipo' => 'outflow', 'categoria_id' => $cortesDePelo->id, 'beneficiario_id' => $miBarbero->id,
            'monto_centavos' => 4000, 'fecha_hora' => $fecha(10, '16:00'),
        ]);
        $crear([
            'cuenta' => $bnb, 'tipo' => 'outflow', 'categoria_id' => $regalos->id,
            'monto_centavos' => 15000, 'fecha_hora' => $fecha(8, '18:30'),
        ]);
        $crear([
            'cuenta' => $bnb, 'tipo' => 'outflow', 'categoria_id' => $ropa->id,
            'monto_centavos' => 18000, 'fecha_hora' => $fecha(6, '17:00'),
        ]);
        $crear([
            'cuenta' => $efectivo, 'tipo' => 'outflow', 'categoria_id' => $transporte->id,
            'monto_centavos' => 1500, 'fecha_hora' => $fecha(18, '07:45'),
        ]);
        $crear([
            'cuenta' => $efectivo, 'tipo' => 'outflow', 'categoria_id' => $transporte->id,
            'monto_centavos' => 2000, 'fecha_hora' => $fecha(14, '08:00'),
        ]);
        $crear([
            'cuenta' => $efectivo, 'tipo' => 'outflow', 'categoria_id' => $transporte->id,
            'monto_centavos' => 1800, 'fecha_hora' => $fecha(9, '08:10'),
        ]);
        $crear([
            'cuenta' => $efectivo, 'tipo' => 'outflow', 'categoria_id' => $salidas->id,
            'monto_centavos' => 9000, 'fecha_hora' => $fecha(7, '21:00'),
        ]);
        $crear([
            'cuenta' => $efectivo, 'tipo' => 'outflow', 'categoria_id' => $salidas->id,
            'monto_centavos' => 6000, 'fecha_hora' => $fecha(2, '20:30'),
        ]);

        // --- Outflow simple adicional (1) ---
        $crear([
            'cuenta' => $efectivo, 'tipo' => 'outflow', 'categoria_id' => $comidaBasica->id,
            'monto_centavos' => 11000, 'fecha_hora' => $fecha(16, '13:00'),
        ]);

        // --- Split (1): Hipermaxi Bs 320 = Comida básica 250 + Ropa 70 ---
        $montoSplit = 32000;
        $fechaSplit = $fecha(5, '11:30');
        [$montoBaseSplit, $tasaSplit] = $service->resolverMontoMonedaBase($montoSplit, $bnb->moneda_codigo, $monedaBase, $maria->id, $fechaSplit);
        $transaccionSplit = $bnb->transacciones()->create([
            'beneficiario_id' => $hipermaxi->id,
            'tipo' => 'outflow',
            'monto_centavos' => $montoSplit,
            'monto_moneda_base_centavos' => $montoBaseSplit,
            'tasa_cambio_aplicada' => $tasaSplit,
            'fecha_hora' => $fechaSplit,
            'es_split' => true,
        ]);
        // monto_moneda_base_centavos por línea calculado con el service real
        // (no copiado del padre): modificación cruzada mínima a Change 7
        // (Change 8 design.md Decisión 2). En este escenario es caso
        // identidad (BOB en cuenta BOB), pero se usa el service igual, nunca
        // un valor hardcodeado.
        $transaccionSplit->splits()->createMany([
            [
                'categoria_id' => $comidaBasica->id,
                'monto_centavos' => 25000,
                'monto_moneda_base_centavos' => $service->resolverMontoMonedaBase(25000, $bnb->moneda_codigo, $monedaBase, $maria->id, $fechaSplit)[0],
            ],
            [
                'categoria_id' => $ropa->id,
                'monto_centavos' => 7000,
                'monto_moneda_base_centavos' => $service->resolverMontoMonedaBase(7000, $bnb->moneda_codigo, $monedaBase, $maria->id, $fechaSplit)[0],
            ],
        ]);

        // --- Transfers (2) ---
        // BNB -> Efectivo, misma moneda (BOB): identity, sin conversion.
        $montoRetiro = 30000;
        $fechaRetiro = $fecha(11, '12:00');
        [$montoBaseRetiro, $tasaRetiro] = $service->resolverMontoMonedaBase($montoRetiro, $bnb->moneda_codigo, $monedaBase, $maria->id, $fechaRetiro);
        $bnb->transacciones()->create([
            'cuenta_destino_id' => $efectivo->id,
            'tipo' => 'transfer',
            'monto_centavos' => $montoRetiro,
            'monto_moneda_base_centavos' => $montoBaseRetiro,
            'monto_centavos_destino' => $service->resolverMontoDestino($montoRetiro, $bnb->moneda_codigo, $efectivo->moneda_codigo, $maria->id, $fechaRetiro),
            'tasa_cambio_aplicada' => $tasaRetiro,
            'fecha_hora' => $fechaRetiro,
            'es_split' => false,
        ]);

        // Binance (USDT) -> BNB (BOB): transfer multi-moneda real, usa la
        // tasa USDT->BOB sembrada por TipoCambioSeeder (7.10).
        $montoConversion = 2000; // USDT 20.00
        $fechaConversion = $fecha(4, '15:00');
        [$montoBaseConversion, $tasaConversion] = $service->resolverMontoMonedaBase($montoConversion, $binance->moneda_codigo, $monedaBase, $maria->id, $fechaConversion);
        $binance->transacciones()->create([
            'cuenta_destino_id' => $bnb->id,
            'tipo' => 'transfer',
            'monto_centavos' => $montoConversion,
            'monto_moneda_base_centavos' => $montoBaseConversion,
            'monto_centavos_destino' => $service->resolverMontoDestino($montoConversion, $binance->moneda_codigo, $bnb->moneda_codigo, $maria->id, $fechaConversion),
            'tasa_cambio_aplicada' => $tasaConversion,
            'fecha_hora' => $fechaConversion,
            'es_split' => false,
        ]);
    }
}
