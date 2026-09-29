<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Wallet } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import CuentaCard from '@/Components/CuentaCard.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { formatearMonto } from '@/utils/formatoMoneda';

const props = defineProps({
    cuentas: Array,
    presupuestoActivo: Object,
});

const SECCIONES = [
    { tipo: 'banco', label: 'Bancarias' },
    { tipo: 'efectivo', label: 'Efectivo' },
    { tipo: 'wallet', label: 'Wallets' },
];

const grupos = computed(() => SECCIONES
    .map((seccion) => ({
        ...seccion,
        cuentas: props.cuentas.filter((c) => c.tipo === seccion.tipo),
    }))
    .filter((seccion) => seccion.cuentas.length > 0));

// Totales por moneda por separado (sin consolidar a la moneda base del
// presupuesto — eso es Change 8 con TasaCambioService).
const totalesPorMoneda = computed(() => {
    const totales = new Map();

    for (const cuenta of props.cuentas) {
        if (!totales.has(cuenta.moneda_codigo)) {
            totales.set(cuenta.moneda_codigo, { moneda: cuenta.moneda, total: 0 });
        }
        totales.get(cuenta.moneda_codigo).total += cuenta.saldo_actual_centavos;
    }

    return Array.from(totales.values());
});

const borrando = ref(null);
const confirmarBorrado = (cuenta) => { borrando.value = cuenta; };
const cerrarModal = () => { borrando.value = null; };
const eliminar = () => {
    router.delete(route('cuentas.destroy', borrando.value.id), {
        onFinish: () => { borrando.value = null; },
    });
};
</script>

<template>
    <Head title="Cuentas" />

    <AppLayout title="Cuentas">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                    Cuentas
                </h2>

                <Link
                    :href="route('cuentas.create')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent-primary border border-transparent rounded-md font-semibold text-xs text-gray-950 uppercase tracking-widest hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
                >
                    <Plus class="size-4" />
                    Nueva cuenta
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-if="cuentas.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                    <Wallet class="size-16 text-gray-300 dark:text-text-secondary mb-4" />
                    <p class="text-gray-600 dark:text-text-secondary mb-4">Aún no tienes cuentas. Crea la primera.</p>
                    <Link
                        :href="route('cuentas.create')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent-primary border border-transparent rounded-md font-semibold text-xs text-gray-950 uppercase tracking-widest hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
                    >
                        <Plus class="size-4" />
                        Nueva cuenta
                    </Link>
                </div>

                <div v-else class="space-y-8">
                    <div v-for="grupo in grupos" :key="grupo.tipo">
                        <h3 class="text-sm font-medium text-text-secondary uppercase tracking-wide mb-3">
                            {{ grupo.label }}
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <CuentaCard
                                v-for="cuenta in grupo.cuentas"
                                :key="cuenta.id"
                                :cuenta="cuenta"
                            >
                                <template #actions>
                                    <Link :href="route('cuentas.edit', cuenta.id)" class="text-text-secondary hover:text-text transition duration-150">
                                        Editar
                                    </Link>

                                    <button
                                        type="button"
                                        class="text-status-danger hover:text-red-400 transition duration-150"
                                        @click="confirmarBorrado(cuenta)"
                                    >
                                        Eliminar
                                    </button>
                                </template>
                            </CuentaCard>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 pt-4 border-t border-gray-200 dark:border-border text-sm">
                        <span
                            v-for="t in totalesPorMoneda"
                            :key="t.moneda.codigo"
                            class="text-text-secondary"
                        >
                            Total {{ t.moneda.codigo }}:
                            <span class="font-mono font-semibold text-text">{{ formatearMonto(t.total, t.moneda) }}</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmationModal :show="borrando !== null" @close="cerrarModal">
            <template #title>
                Eliminar cuenta
            </template>

            <template #content>
                ¿Seguro que quieres eliminar "{{ borrando?.nombre }}"? El histórico de transacciones asociadas se conserva en la base de datos.
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
