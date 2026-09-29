/**
 * Formatea un monto en centavos según la precisión y símbolo de una moneda.
 * `moneda` es cualquier objeto con `{ simbolo, decimales }` (el modelo Moneda
 * completo, o un literal recortado — no importa cuál, solo esas dos claves).
 *
 * formatearMonto(200000, { simbolo: 'Bs.', decimales: 2 }) -> "Bs. 2,000.00"
 * formatearMonto(5000, { simbolo: '₮', decimales: 2 }) -> "₮ 50.00"
 */
export function formatearMonto(centavos, moneda) {
    const valor = centavos / 10 ** moneda.decimales;

    return `${moneda.simbolo} ${valor.toLocaleString('en-US', {
        minimumFractionDigits: moneda.decimales,
        maximumFractionDigits: moneda.decimales,
    })}`;
}

/**
 * Inverso de formatearMonto: convierte lo que el usuario escribe en un input
 * (unidades enteras, ej. "2000.50") a centavos para enviar al backend.
 * Math.round evita el clásico error de coma flotante (2000.1 * 100 = 200009.999...).
 */
export function centavosDesdeMonto(valorUnidades, decimales) {
    return Math.round(Number(valorUnidades) * 10 ** decimales);
}
