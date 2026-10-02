<?php

namespace App\Services;

use Carbon\Carbon;
use RuntimeException;

/**
 * Resuelve los montos convertidos que una Transaccion necesita almacenar:
 * el equivalente en la moneda base del presupuesto (toda transacción) y,
 * para transfers multi-moneda, el equivalente en la moneda de la cuenta
 * destino. Delega toda la resolución de tasas en TasaCambioService — este
 * service solo decide CUÁNDO conviene usarla (identity vs. caso real) y
 * qué hacer si falta ($userId explícito: las tasas son por usuario, no por
 * presupuesto, ver TasaCambioService).
 */
class CalculadoraTransaccionService
{
    public function __construct(private readonly TasaCambioService $tasaCambioService)
    {
    }

    /**
     * @return array{0: int, 1: string|null} [monto_moneda_base_centavos, tasa_cambio_aplicada]
     */
    public function resolverMontoMonedaBase(
        int $montoCentavos,
        string $monedaCuenta,
        string $monedaBase,
        int $userId,
        Carbon $fecha,
    ): array {
        if ($monedaCuenta === $monedaBase) {
            return [$montoCentavos, null];
        }

        $resuelto = $this->tasaCambioService->resolver($userId, $monedaCuenta, $monedaBase, $fecha);

        if ($resuelto === null) {
            // No debería ocurrir en la práctica: StoreTransaccionRequest ya
            // rechaza en validación si no hay tasa disponible (withValidator).
            // Si esto se dispara, hay una inconsistencia entre el momento de
            // validar y el de persistir, no un caso normal de negocio.
            throw new RuntimeException("No hay tipo de cambio disponible para convertir de {$monedaCuenta} a {$monedaBase}.");
        }

        $montoConvertido = $this->tasaCambioService->convertir($userId, $montoCentavos, $monedaCuenta, $monedaBase, $fecha);

        return [$montoConvertido, $resuelto['tasa']];
    }

    /**
     * Para transfer multi-moneda: convierte el monto a la moneda de la
     * cuenta destino. TasaCambioService::convertir() ya maneja el caso
     * identity internamente, pero se verifica aquí también para evitar la
     * query de resolver() cuando es evidente que no hace falta.
     */
    public function resolverMontoDestino(
        int $montoCentavos,
        string $monedaOrigen,
        string $monedaDestino,
        int $userId,
        Carbon $fecha,
    ): int {
        if ($monedaOrigen === $monedaDestino) {
            return $montoCentavos;
        }

        $montoConvertido = $this->tasaCambioService->convertir($userId, $montoCentavos, $monedaOrigen, $monedaDestino, $fecha);

        if ($montoConvertido === null) {
            throw new RuntimeException("No hay tipo de cambio disponible para convertir de {$monedaOrigen} a {$monedaDestino}.");
        }

        return $montoConvertido;
    }
}
