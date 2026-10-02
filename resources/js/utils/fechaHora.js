const dosDigitos = (n) => String(n).padStart(2, '0');

/**
 * Descompone un timestamp (ISO string o undefined/null para "ahora") en los
 * componentes locales de fecha y hora que necesitan los inputs HTML5
 * `type="date"`/`type="time"`. Usa la zona horaria del NAVEGADOR (no UTC):
 * mismo criterio que usa Create.vue para precargar "ahora", así que Create y
 * Edit interpretan una fecha_hora de la misma forma, sin desfase entre sí.
 */
export function fechaYHoraLocal(fechaHora) {
    const d = fechaHora ? new Date(fechaHora) : new Date();

    return {
        fecha: `${d.getFullYear()}-${dosDigitos(d.getMonth() + 1)}-${dosDigitos(d.getDate())}`,
        hora: `${dosDigitos(d.getHours())}:${dosDigitos(d.getMinutes())}`,
    };
}

/** Inverso de fechaYHoraLocal: arma el string que el backend espera. */
export function combinarFechaHora(fecha, hora) {
    return `${fecha}T${hora}:00`;
}

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

/** "2026-10-01T14:30:00Z" -> "01 oct 14:30" (zona horaria del navegador). */
export function formatearFechaHora(fechaHora) {
    const d = new Date(fechaHora);

    return `${dosDigitos(d.getDate())} ${MESES[d.getMonth()]} ${dosDigitos(d.getHours())}:${dosDigitos(d.getMinutes())}`;
}
