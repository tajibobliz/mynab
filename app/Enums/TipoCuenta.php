<?php

namespace App\Enums;

enum TipoCuenta: string
{
    case Banco = 'banco';
    case Efectivo = 'efectivo';
    case Wallet = 'wallet';

    /**
     * Opciones para dropdowns del frontend: value + label en español +
     * nombre del icono Lucide correspondiente (resuelto en el frontend
     * contra su propio mapa, igual que `iconosPresupuesto`).
     *
     * @return array<int, array{value: string, label: string, icono: string}>
     */
    public static function opciones(): array
    {
        return [
            ['value' => self::Banco->value, 'label' => 'Cuenta bancaria', 'icono' => 'Landmark'],
            ['value' => self::Efectivo->value, 'label' => 'Efectivo', 'icono' => 'Banknote'],
            ['value' => self::Wallet->value, 'label' => 'Wallet cripto', 'icono' => 'Wallet'],
        ];
    }
}
