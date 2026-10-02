<?php

namespace App\Enums;

enum TipoTransaccion: string
{
    case Outflow = 'outflow';
    case Inflow = 'inflow';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Outflow => 'Gasto',
            self::Inflow => 'Ingreso',
            self::Transfer => 'Transferencia',
        };
    }

    /**
     * Nombre del icono Lucide, verificado contra lucide-vue-next antes de
     * usarse (mismo rigor que iconos_grupo_categoria en Change 5).
     */
    public function icono(): string
    {
        return match ($this) {
            self::Outflow => 'ArrowUpRight',
            self::Inflow => 'ArrowDownLeft',
            self::Transfer => 'ArrowLeftRight',
        };
    }

    /**
     * Token de color de la paleta Neo-YNAB dark (tailwind.config.js: colors.status.*).
     */
    public function color(): string
    {
        return match ($this) {
            self::Outflow => 'status-danger',
            self::Inflow => 'status-success',
            self::Transfer => 'status-info',
        };
    }

    /**
     * Opciones para dropdowns del frontend, mismo patrón que TipoCuenta::opciones().
     *
     * @return array<int, array{value: string, label: string, icono: string, color: string}>
     */
    public static function opciones(): array
    {
        return array_map(
            fn (self $caso) => [
                'value' => $caso->value,
                'label' => $caso->label(),
                'icono' => $caso->icono(),
                'color' => $caso->color(),
            ],
            self::cases(),
        );
    }
}
