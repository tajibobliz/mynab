<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AsignacionInlineInput from '@/Components/AsignacionInlineInput.vue';
import GrupoCategoriaCard from '@/Components/GrupoCategoriaCard.vue';
import SelectorMes from '@/Components/SelectorMes.vue';
import { formatearMonto } from '@/utils/formatoMoneda';

const props = defineProps({
    vista: Object,
    año: Number,
    mes: Number,
});

const page = usePage();

// El controller (Grupo 6) ya garantiza presupuesto activo antes de renderizar
// esta página (presupuestoActivoOrRedirect()) — no hace falta un fallback
// defensivo acá, igual que en el resto de páginas del presupuesto activo.
const moneda = computed(() => page.props.auth.user.presupuesto_activo.moneda_base);

// Tokens Tailwind literales (nunca interpolados `text-${x}`): PurgeCSS
// escanea el código fuente en busca de clases completas, una clase
// construida en runtime es invisible para él y desaparece en producción.
const CLASES_RTA = {
    positivo: 'text-status-success',
    cero: 'text-status-warning',
    negativo: 'text-status-danger',
};

const MENSAJES_RTA = {
    positivo: 'Pendiente por asignar',
    cero: 'Todo asignado',
    negativo: 'Has asignado más de lo disponible',
};

const estadoRTA = computed(() => {
    if (props.vista.readyToAssign > 0) return 'positivo';
    if (props.vista.readyToAssign === 0) return 'cero';
    return 'negativo';
});

const urlActivity = (categoriaId) => {
    const mesStr = String(props.mes).padStart(2, '0');
    const inicioMes = `${props.año}-${mesStr}-01`;
    const ultimoDia = new Date(props.año, props.mes, 0).getDate();
    const finMes = `${props.año}-${mesStr}-${String(ultimoDia).padStart(2, '0')}`;

    return route('transacciones.index', { categoria: categoriaId, desde: inicioMes, hasta: finMes });
};
</script>

<template>
    <Head title="Presupuesto mensual" />

    <AppLayout title="Presupuesto mensual">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                Presupuesto mensual
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <SelectorMes :año="año" :mes="mes" />

                <div class="rounded-lg border border-border bg-surface p-8 text-center">
                    <p class="text-sm text-text-secondary mb-1">Ready to Assign</p>
                    <p class="text-4xl font-mono font-bold" :class="CLASES_RTA[estadoRTA]">
                        {{ formatearMonto(vista.readyToAssign, moneda) }}
                    </p>
                    <p class="text-sm text-text-secondary mt-1">{{ MENSAJES_RTA[estadoRTA] }}</p>
                </div>

                <div v-if="vista.grupos.length === 0" class="flex flex-col items-center justify-center py-20 text-center text-text-secondary">
                    Aún no tienes categorías. Crea grupos y categorías para empezar a asignar.
                </div>

                <div v-else class="space-y-4">
                    <GrupoCategoriaCard v-for="grupo in vista.grupos" :key="grupo.id" :grupo="grupo">
                        <template #categorias>
                            <div class="grid grid-cols-[1fr_7rem_7rem_7rem] gap-x-4 gap-y-2 items-center">
                                <span class="text-xs font-semibold text-text-secondary uppercase tracking-wide">Categoría</span>
                                <span class="text-xs font-semibold text-text-secondary uppercase tracking-wide text-right">Assigned</span>
                                <span class="text-xs font-semibold text-text-secondary uppercase tracking-wide text-right">Activity</span>
                                <span class="text-xs font-semibold text-text-secondary uppercase tracking-wide text-right">Available</span>

                                <template v-for="categoria in grupo.categorias" :key="categoria.id">
                                    <span class="text-sm text-text truncate">{{ categoria.nombre }}</span>

                                    <AsignacionInlineInput
                                        :categoria-id="categoria.id"
                                        :año="año"
                                        :mes="mes"
                                        :monto-centavos="categoria.assigned"
                                        :moneda="moneda"
                                    />

                                    <Link
                                        :href="urlActivity(categoria.id)"
                                        class="text-right font-mono text-sm text-text-secondary hover:text-accent-primary transition duration-150"
                                    >
                                        {{ formatearMonto(categoria.activity, moneda) }}
                                    </Link>

                                    <span
                                        class="text-right font-mono text-sm font-semibold"
                                        :class="categoria.available < 0 ? 'text-status-danger' : 'text-text'"
                                    >
                                        {{ formatearMonto(categoria.available, moneda) }}
                                    </span>
                                </template>
                            </div>
                        </template>
                    </GrupoCategoriaCard>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
