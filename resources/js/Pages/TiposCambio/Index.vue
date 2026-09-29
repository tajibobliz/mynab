<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeftRight, History, Plus } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    tiposCambio: Array,
    filtro: Object,
});

const esHistorial = computed(() => Boolean(props.filtro?.origen && props.filtro?.destino));

const formatearFecha = (fecha) => new Date(`${fecha}T00:00:00`).toLocaleDateString('es-BO', {
    day: '2-digit', month: 'short', year: 'numeric',
});

// Agrupa por par sin importar dirección (BOB->USDT y USDT->BOB caen en el
// mismo grupo "BOB↔USDT"). Dentro de cada dirección solo se muestra el
// registro vigente (el más reciente); el backend ya ordena por fecha desc,
// así que basta con tomar la primera ocurrencia de cada dirección.
const grupos = computed(() => {
    if (esHistorial.value) return [];

    const vigentePorDireccion = new Map();
    for (const tc of props.tiposCambio) {
        const clave = `${tc.moneda_origen}->${tc.moneda_destino}`;
        if (!vigentePorDireccion.has(clave)) {
            vigentePorDireccion.set(clave, tc);
        }
    }

    const porPar = new Map();
    for (const tc of vigentePorDireccion.values()) {
        const clave = [tc.moneda_origen, tc.moneda_destino].sort().join('↔');
        if (!porPar.has(clave)) porPar.set(clave, []);
        porPar.get(clave).push(tc);
    }

    return Array.from(porPar.entries()).map(([par, direcciones]) => ({ par, direcciones }));
});

const borrando = ref(null);
const confirmarBorrado = (tipoCambio) => { borrando.value = tipoCambio; };
const cerrarModal = () => { borrando.value = null; };
const eliminar = () => {
    router.delete(route('tipos-cambio.destroy', borrando.value.id), {
        onFinish: () => { borrando.value = null; },
    });
};
</script>

<template>
    <Head title="Tipos de cambio" />

    <AppLayout title="Tipos de cambio">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                    Tipos de cambio
                </h2>

                <Link
                    :href="route('tipos-cambio.create')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent-primary border border-transparent rounded-md font-semibold text-xs text-gray-950 uppercase tracking-widest hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
                >
                    <Plus class="size-4" />
                    Nuevo tipo de cambio
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Vista historial: un solo par/dirección, todos los registros -->
                <template v-if="esHistorial">
                    <Link :href="route('tipos-cambio.index')" class="inline-flex items-center gap-1.5 text-sm text-text-secondary hover:text-text transition duration-150">
                        ← Volver a todos los pares
                    </Link>

                    <div class="bg-white shadow-xl sm:rounded-lg dark:bg-surface dark:border dark:border-border dark:shadow-none p-5">
                        <h3 class="font-semibold text-gray-800 dark:text-text mb-4">
                            Historial {{ filtro.origen }} → {{ filtro.destino }}
                        </h3>

                        <p v-if="tiposCambio.length === 0" class="text-sm text-text-secondary">
                            No hay registros para este par.
                        </p>

                        <ul v-else class="divide-y divide-gray-100 dark:divide-border">
                            <li v-for="tc in tiposCambio" :key="tc.id" class="py-3 flex items-center justify-between gap-4">
                                <div>
                                    <span class="font-mono font-semibold text-gray-800 dark:text-text">{{ tc.tasa }}</span>
                                    <span class="text-sm text-text-secondary ms-2">{{ formatearFecha(tc.fecha) }}</span>
                                </div>
                                <div class="flex items-center gap-3 text-sm shrink-0">
                                    <Link :href="route('tipos-cambio.edit', tc.id)" class="text-text-secondary hover:text-text transition duration-150">
                                        Editar
                                    </Link>
                                    <button type="button" class="text-status-danger hover:text-red-400 transition duration-150" @click="confirmarBorrado(tc)">
                                        Eliminar
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </template>

                <!-- Vista overview: agrupado por par, solo vigentes -->
                <template v-else>
                    <div v-if="tiposCambio.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                        <ArrowLeftRight class="size-16 text-gray-300 dark:text-text-secondary mb-4" />
                        <p class="text-gray-600 dark:text-text-secondary mb-4">Aún no registraste tipos de cambio</p>
                        <Link
                            :href="route('tipos-cambio.create')"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent-primary border border-transparent rounded-md font-semibold text-xs text-gray-950 uppercase tracking-widest hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
                        >
                            <Plus class="size-4" />
                            Nuevo tipo de cambio
                        </Link>
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="grupo in grupos"
                            :key="grupo.par"
                            class="bg-white shadow-xl sm:rounded-lg dark:bg-surface dark:border dark:border-border dark:shadow-none p-5"
                        >
                            <h3 class="font-semibold text-gray-800 dark:text-text mb-3">{{ grupo.par }}</h3>

                            <ul class="divide-y divide-gray-100 dark:divide-border">
                                <li v-for="tc in grupo.direcciones" :key="tc.id" class="py-3 flex items-center justify-between gap-4 flex-wrap">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="text-sm text-text-secondary shrink-0">{{ tc.origen.codigo }} → {{ tc.destino.codigo }}</span>
                                        <span class="font-mono font-semibold text-gray-800 dark:text-text">{{ tc.tasa }}</span>
                                        <span class="text-xs text-text-secondary">{{ formatearFecha(tc.fecha) }}</span>
                                    </div>

                                    <div class="flex items-center gap-3 text-sm shrink-0">
                                        <Link
                                            :href="route('tipos-cambio.index', { origen: tc.moneda_origen, destino: tc.moneda_destino })"
                                            class="inline-flex items-center gap-1 text-text-secondary hover:text-text transition duration-150"
                                        >
                                            <History class="size-3.5" />
                                            Ver historial
                                        </Link>
                                        <Link :href="route('tipos-cambio.edit', tc.id)" class="text-text-secondary hover:text-text transition duration-150">
                                            Editar
                                        </Link>
                                        <button type="button" class="text-status-danger hover:text-red-400 transition duration-150" @click="confirmarBorrado(tc)">
                                            Eliminar
                                        </button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <ConfirmationModal :show="borrando !== null" @close="cerrarModal">
            <template #title>
                Eliminar tipo de cambio
            </template>

            <template #content>
                ¿Seguro que quieres eliminar el tipo de cambio {{ borrando?.moneda_origen }} → {{ borrando?.moneda_destino }} del {{ borrando ? formatearFecha(borrando.fecha) : '' }}?
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
