<script setup>
// Mockup estático (no datos reales ni conexión al backend): representa el
// dashboard tal como se ve con el escenario oficial de María, con montos
// coherentes con el seeder de Change 8 (RTA Bs 600, sobres reales).
const sobres = [
    { nombre: 'Alquiler', porcentaje: 100, color: 'bg-status-success' },
    { nombre: 'Comida básica', porcentaje: 45, color: 'bg-status-success' },
    { nombre: 'Suscripciones', porcentaje: 80, color: 'bg-status-warning' },
    { nombre: 'Netflix', porcentaje: 100, color: 'bg-status-success' },
];
</script>

<template>
    <div class="relative select-none">
        <!-- overflow-hidden vive en este wrapper interno, no en la raíz: los
        badges flotantes (más abajo) se posicionan FUERA de los bordes de la
        tarjeta a propósito (-top-4 -left-6, etc.) y necesitan que la raíz
        tenga overflow visible para no recortarse. -->
        <div class="rounded-2xl border border-border bg-surface shadow-2xl overflow-hidden">
            <!-- Mini nav -->
            <div class="flex items-center justify-between px-5 py-3 border-b border-border/70 bg-surface-elevated/50">
                <div class="flex items-center gap-1">
                    <span class="text-sm font-bold text-text">MyNAB</span>
                    <span class="size-1.5 rounded-full bg-status-success" />
                </div>
                <div class="size-7 rounded-full bg-accent-secondary/20 border border-accent-secondary/40" />
            </div>

            <div class="p-6 space-y-6">
                <!-- Hero mini: Ready to Assign -->
                <div class="rounded-xl border border-border bg-base/60 p-5 text-center">
                    <p class="text-xs text-text-secondary mb-1">Ready to Assign</p>
                    <p class="text-3xl font-mono font-bold text-status-success">Bs. 600,00</p>
                </div>

                <!-- Grid de sobres -->
                <div class="grid grid-cols-2 gap-3">
                    <div
                        v-for="sobre in sobres"
                        :key="sobre.nombre"
                        class="rounded-lg border border-border/70 bg-base/40 p-3"
                    >
                        <p class="text-xs text-text-secondary truncate mb-2">{{ sobre.nombre }}</p>
                        <div class="h-1.5 rounded-full bg-border/70 overflow-hidden">
                            <div class="h-full rounded-full" :class="sobre.color" :style="{ width: sobre.porcentaje + '%' }" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating badges -->
        <div
            class="hidden sm:flex absolute -top-4 -left-6 items-center gap-2 rounded-lg border border-status-success/30 bg-surface px-3 py-2 shadow-lg animate-pulse z-10"
            style="animation-duration: 3s"
        >
            <span class="font-mono text-sm font-semibold text-status-success">+Bs 3.500</span>
            <span class="text-xs text-text-secondary">Sueldo</span>
        </div>

        <div
            class="hidden sm:flex absolute -bottom-4 -right-6 items-center gap-2 rounded-lg border border-status-danger/30 bg-surface px-3 py-2 shadow-lg animate-pulse z-10"
            style="animation-duration: 3.5s; animation-delay: 0.5s"
        >
            <span class="font-mono text-sm font-semibold text-status-danger">-Bs 1.500</span>
            <span class="text-xs text-text-secondary">Alquiler</span>
        </div>
    </div>
</template>
