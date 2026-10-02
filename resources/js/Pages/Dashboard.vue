<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Plus, Receipt, Wallet } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatearMonto } from '@/utils/formatoMoneda';

const props = defineProps({
    tienePresupuestoActivo: Boolean,
    readyToAssign: {
        type: Number,
        default: null,
    },
    sobresAtentos: {
        type: Array,
        default: () => [],
    },
    mesAño: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const moneda = computed(() => page.props.auth.user.presupuesto_activo?.moneda_base);

// Mismos tokens Tailwind literales que PresupuestoMensual/Index.vue (nunca
// interpolados `text-${x}`, PurgeCSS necesita ver la clase completa).
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
    if (props.readyToAssign > 0) return 'positivo';
    if (props.readyToAssign === 0) return 'cero';
    return 'negativo';
});
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <template v-if="tienePresupuestoActivo">
                    <div class="rounded-lg border border-border bg-surface p-8 text-center">
                        <p class="text-sm text-text-secondary mb-1">Ready to Assign</p>
                        <p class="text-4xl font-mono font-bold" :class="CLASES_RTA[estadoRTA]">
                            {{ formatearMonto(readyToAssign, moneda) }}
                        </p>
                        <p class="text-sm text-text-secondary mt-1">{{ MENSAJES_RTA[estadoRTA] }}</p>
                        <p class="text-xs text-text-secondary mt-3 capitalize">{{ mesAño.nombreMes }} {{ mesAño.año }}</p>
                    </div>

                    <div v-if="sobresAtentos.length > 0">
                        <h3 class="text-sm font-semibold text-text-secondary uppercase tracking-wide mb-3">
                            Sobres atentos
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                            <Link
                                v-for="sobre in sobresAtentos"
                                :key="sobre.id"
                                :href="route('presupuesto-mensual.index')"
                                class="rounded-lg border border-border bg-surface p-4 hover:bg-surface-elevated transition duration-150"
                                style="border-left-width: 4px"
                                :style="{ borderLeftColor: sobre.grupoColor }"
                            >
                                <p class="text-sm text-text truncate">{{ sobre.nombre }}</p>
                                <p
                                    class="mt-1 font-mono font-semibold"
                                    :class="sobre.available < 0 ? 'text-status-danger' : 'text-text'"
                                >
                                    {{ formatearMonto(sobre.available, moneda) }}
                                </p>
                            </Link>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            :href="route('presupuesto-mensual.index')"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md border border-border bg-surface text-sm text-text hover:bg-surface-elevated transition duration-150"
                        >
                            Ver presupuesto completo
                            <ArrowRight class="size-4" />
                        </Link>

                        <Link
                            :href="route('transacciones.create')"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent-primary border border-transparent rounded-md font-semibold text-xs text-gray-950 uppercase tracking-widest hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
                        >
                            <Receipt class="size-4" />
                            Nueva transacción
                        </Link>
                    </div>
                </template>

                <div
                    v-else
                    class="flex flex-col items-center justify-center py-20 text-center bg-white shadow-xl sm:rounded-lg dark:bg-surface dark:border dark:border-border dark:shadow-none"
                >
                    <Wallet class="size-16 text-gray-300 dark:text-text-secondary mb-4" />

                    <h1 class="text-xl font-semibold text-gray-900 dark:text-text">
                        Crea tu primer presupuesto
                    </h1>

                    <p class="mt-2 max-w-md text-sm text-gray-600 dark:text-text-secondary">
                        Un presupuesto es donde viven todas tus cuentas, categorías y transacciones. Crea el tuyo para empezar.
                    </p>

                    <Link
                        :href="route('presupuestos.create')"
                        class="mt-6 inline-flex items-center gap-1.5 px-4 py-2 bg-accent-primary border border-transparent rounded-md font-semibold text-xs text-gray-950 uppercase tracking-widest hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
                    >
                        <Plus class="size-4" />
                        Crear presupuesto
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
