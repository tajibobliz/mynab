<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus, Receipt } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import FiltrosTransacciones from '@/Components/FiltrosTransacciones.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { tipoTransaccionIconMap } from '@/tipoTransaccionIconMap';
import { formatearFechaHora } from '@/utils/fechaHora';
import { formatearMonto } from '@/utils/formatoMoneda';

const props = defineProps({
    transacciones: Object,
    filtros: Object,
    tiposTransaccion: Array,
    presupuestoActivo: Object,
});

// Tokens literales (no interpolados) -- ver nota en Create.vue sobre por qué
// una clase Tailwind construida con `${color}` en runtime no sobrevive el
// build de producción.
const CLASES_TIPO = {
    'status-danger': 'bg-status-danger/15 text-status-danger',
    'status-success': 'bg-status-success/15 text-status-success',
    'status-info': 'bg-status-info/15 text-status-info',
};

const opcionPorTipo = (tipo) => props.tiposTransaccion.find((o) => o.value === tipo);

const filtrosIniciales = {
    tipo: props.filtros.tipo ?? '',
    cuenta: props.filtros.cuenta ?? '',
    categoria: props.filtros.categoria ?? '',
    beneficiario: props.filtros.beneficiario ?? null,
    desde: props.filtros.desde ?? '',
    hasta: props.filtros.hasta ?? '',
};

const aplicarFiltros = (nuevos) => {
    router.get(route('transacciones.index'), nuevos, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const irAPagina = (url) => {
    if (! url) {
        return;
    }
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

/**
 * Segunda línea descriptiva por tipo (design.md / mensaje de kickoff):
 * outflow -> Beneficiario → Categoría (o "Dividida" si es_split)
 * inflow -> "Ingreso de" + Beneficiario
 * transfer -> Cuenta → Cuenta destino
 */
const descripcion = (transaccion) => {
    if (transaccion.tipo === 'inflow') {
        return `Ingreso de ${transaccion.beneficiario?.nombre ?? '—'}`;
    }

    if (transaccion.tipo === 'transfer') {
        return `${transaccion.cuenta.nombre} → ${transaccion.cuenta_destino?.nombre ?? '—'}`;
    }

    const destino = transaccion.es_split ? 'Dividida' : (transaccion.categoria?.nombre ?? '—');

    return transaccion.beneficiario ? `${transaccion.beneficiario.nombre} → ${destino}` : destino;
};

const borrando = ref(null);
const confirmarBorrado = (transaccion) => { borrando.value = transaccion; };
const cerrarModal = () => { borrando.value = null; };
const eliminar = () => {
    router.delete(route('transacciones.destroy', borrando.value.id), {
        preserveScroll: true,
        onFinish: () => { borrando.value = null; },
    });
};
</script>

<template>
    <Head title="Transacciones" />

    <AppLayout title="Transacciones">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                    Transacciones
                </h2>

                <Link
                    :href="route('transacciones.create')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent-primary border border-transparent rounded-md font-semibold text-xs text-gray-950 uppercase tracking-widest hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
                >
                    <Plus class="size-4" />
                    Nueva transacción
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <FiltrosTransacciones :model-value="filtrosIniciales" @update:model-value="aplicarFiltros" />

                <div v-if="transacciones.data.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                    <Receipt class="size-16 text-gray-300 dark:text-text-secondary mb-4" />
                    <p class="text-gray-600 dark:text-text-secondary mb-4">Aún no tienes transacciones.</p>
                    <Link
                        :href="route('transacciones.create')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent-primary border border-transparent rounded-md font-semibold text-xs text-gray-950 uppercase tracking-widest hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
                    >
                        <Plus class="size-4" />
                        Nueva transacción
                    </Link>
                </div>

                <template v-else>
                    <ul class="divide-y divide-gray-100 dark:divide-border rounded-lg border border-gray-200 dark:border-border bg-white dark:bg-surface overflow-hidden">
                        <li
                            v-for="transaccion in transacciones.data"
                            :key="transaccion.id"
                            class="flex items-center gap-4 px-4 py-3 hover:bg-gray-50 dark:hover:bg-surface-elevated transition duration-150 cursor-pointer"
                            @click="router.visit(route('transacciones.edit', transaccion.id))"
                        >
                            <span
                                class="flex items-center justify-center size-9 rounded-lg shrink-0"
                                :class="CLASES_TIPO[opcionPorTipo(transaccion.tipo).color]"
                            >
                                <component :is="tipoTransaccionIconMap[opcionPorTipo(transaccion.tipo).icono]" class="size-5" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-text truncate">{{ descripcion(transaccion) }}</p>
                                <p class="text-sm text-text-secondary truncate">
                                    {{ formatearFechaHora(transaccion.fecha_hora) }} · {{ transaccion.cuenta.nombre }}
                                    <span v-if="transaccion.notas"> · {{ transaccion.notas }}</span>
                                </p>
                            </div>

                            <span class="font-mono text-sm font-semibold text-text shrink-0">
                                {{ formatearMonto(transaccion.monto_centavos, transaccion.cuenta.moneda) }}
                            </span>

                            <button
                                type="button"
                                class="text-status-danger hover:text-red-400 transition duration-150 shrink-0"
                                @click.stop="confirmarBorrado(transaccion)"
                            >
                                Eliminar
                            </button>
                        </li>
                    </ul>

                    <div class="flex items-center justify-between">
                        <SecondaryButton :disabled="! transacciones.prev_page_url" @click="irAPagina(transacciones.prev_page_url)">
                            <ChevronLeft class="size-4" />
                            Anterior
                        </SecondaryButton>

                        <SecondaryButton :disabled="! transacciones.next_page_url" @click="irAPagina(transacciones.next_page_url)">
                            Siguiente
                            <ChevronRight class="size-4" />
                        </SecondaryButton>
                    </div>
                </template>
            </div>
        </div>

        <ConfirmationModal :show="borrando !== null" @close="cerrarModal">
            <template #title>
                Eliminar transacción
            </template>

            <template #content>
                ¿Eliminar esta transacción? El histórico se conserva en la base de datos.
            </template>

            <template #footer>
                <SecondaryButton @click="cerrarModal">
                    Cancelar
                </SecondaryButton>

                <DangerButton class="ms-3" @click="eliminar">
                    Eliminar
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
