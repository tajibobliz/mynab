<?php

namespace App\Services;

use App\Models\TipoCambio;
use Carbon\Carbon;

/**
 * Resuelve la tasa de cambio vigente entre dos monedas, para un usuario y
 * una fecha dados, y convierte montos aplicando esa tasa.
 *
 * Lógica de dominio pura: sin dependencias externas más allá del modelo
 * Eloquent, sin efectos secundarios (no escribe logs ni notifica), mismo
 * input siempre produce el mismo output. Consumida por los changes de
 * cuentas, transacciones y consolidación (Changes 4, 7 y 8).
 */
class TasaCambioService
{
    /** Escala (decimales) usada en todas las operaciones bcmath del service. */
    private const ESCALA_TASA = 8;

    /** Tasa del identity case (origen == destino), ya en formato bcmath. */
    private const TASA_IDENTITY = '1.00000000';

    /**
     * Resuelve la tasa vigente para un par de monedas en una fecha dada.
     *
     * Orden de resolución:
     * 1. Identity case (origen == destino): 1.0 sin consultar la BD.
     * 2. Tasa con fecha <= $fecha más reciente para ese par y usuario.
     * 3. Si no hay histórica, la tasa más reciente disponible (aunque sea
     *    posterior a $fecha), marcada como extrapolada.
     * 4. Si no hay ninguna tasa para el par, null.
     *
     * @return array{tasa: string, esExtrapolada: bool, fechaResuelta: Carbon}|null
     *         `tasa` se retorna como string, no float: el modelo ya la
     *         expone como string vía el cast `decimal:8`, y castear a float
     *         aquí reintroduciría el error de coma flotante que bcmath
     *         (usado en convertir()) existe precisamente para evitar.
     */
    public function resolver(int $userId, string $origen, string $destino, Carbon $fecha): ?array
    {
        if ($origen === $destino) {
            return [
                'tasa' => self::TASA_IDENTITY,
                'esExtrapolada' => false,
                'fechaResuelta' => $fecha,
            ];
        }

        $vigente = TipoCambio::query()
            ->where('user_id', $userId)
            ->where('moneda_origen', $origen)
            ->where('moneda_destino', $destino)
            ->where('fecha', '<=', $fecha->toDateString())
            ->orderByDesc('fecha')
            ->first();

        if ($vigente !== null) {
            return [
                'tasa' => (string) $vigente->tasa,
                'esExtrapolada' => false,
                'fechaResuelta' => $vigente->fecha,
            ];
        }

        $masReciente = TipoCambio::query()
            ->where('user_id', $userId)
            ->where('moneda_origen', $origen)
            ->where('moneda_destino', $destino)
            ->orderByDesc('fecha')
            ->first();

        if ($masReciente !== null) {
            return [
                'tasa' => (string) $masReciente->tasa,
                'esExtrapolada' => true,
                'fechaResuelta' => $masReciente->fecha,
            ];
        }

        return null;
    }

    /**
     * Convierte un monto en centavos de una moneda a otra usando la tasa
     * vigente resuelta por resolver(). Todo el cálculo es bcmath de punta a
     * punta (nunca pasa por float): el redondeo half-up se hace truncando
     * y comparando el resto decimal contra "0.5" con bccomp, en vez de
     * round(floatval(...)), que reintroduciría el error de coma flotante
     * que bcmath existe para evitar.
     *
     * @return int|null Monto convertido en centavos, o null si no hay tasa
     *                   para el par (mismo caso que resolver() retorna null).
     */
    public function convertir(int $userId, int $montoCentavos, string $origen, string $destino, Carbon $fecha): ?int
    {
        $resuelto = $this->resolver($userId, $origen, $destino, $fecha);

        if ($resuelto === null) {
            return null;
        }

        $producto = bcmul((string) $montoCentavos, $resuelto['tasa'], self::ESCALA_TASA);
        $truncado = bcadd($producto, '0', 0);
        $fraccion = bcsub($producto, $truncado, self::ESCALA_TASA);

        if (bccomp($fraccion, '0.5', self::ESCALA_TASA) >= 0) {
            $truncado = bcadd($truncado, '1', 0);
        }

        return (int) $truncado;
    }
}
