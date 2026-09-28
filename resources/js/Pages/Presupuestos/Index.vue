<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Wallet } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PresupuestoCard from '@/Components/PresupuestoCard.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    presupuestos: Array,
});

const page = usePage();
const activoId = computed(() => page.props.auth.user.presupuesto_activo_id);

const borrando = ref(null);

const confirmarBorrado = (presupuesto) => {
    borrando.value = presupuesto;
};

const cerrarModal = () => {
    borrando.value = null;
};

const eliminar = () => {
    router.delete(route('presupuestos.destroy', borrando.value.id), {
        onFinish: () => {
            borrando.value = null;
        },
    });
};

const seleccionar = (presupuesto) => {
    router.post(route('presupuestos.seleccionar', presupuesto.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Presupuestos" />

    <AppLayout title="Presupuestos">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                    Presupuestos
                </h2>

                <Link
                    :href="route('presupuestos.create')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent-primary border border-transparent rounded-md font-semibold text-xs text-gray-950 uppercase tracking-widest hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
                >
                    <Plus class="size-4" />
                    Crear presupuesto
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-if="presupuestos.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                    <Wallet class="size-16 text-gray-300 dark:text-text-secondary mb-4" />
                    <p class="text-gray-600 dark:text-text-secondary mb-4">Aún no tienes presupuestos</p>
                    <Link
                        :href="route('presupuestos.create')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent-primary border border-transparent rounded-md font-semibold text-xs text-gray-950 uppercase tracking-widest hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
                    >
                        <Plus class="size-4" />
                        Crear presupuesto
                    </Link>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <PresupuestoCard
                        v-for="presupuesto in presupuestos"
                        :key="presupuesto.id"
                        :presupuesto="presupuesto"
                        :href="route('presupuestos.show', presupuesto.id)"
                    >
                        <template #actions>
                            <Link :href="route('presupuestos.edit', presupuesto.id)" class="text-text-secondary hover:text-text transition duration-150">
                                Editar
                            </Link>

                            <button
                                type="button"
                                class="text-status-danger hover:text-red-400 transition duration-150"
                                @click="confirmarBorrado(presupuesto)"
                            >
                                Eliminar
                            </button>

                            <button
                                v-if="presupuesto.id !== activoId"
                                type="button"
                                class="ms-auto text-accent-primary hover:text-green-400 font-medium transition duration-150"
                                @click="seleccionar(presupuesto)"
                            >
                                Usar como activo
                            </button>
                            <span v-else class="ms-auto text-xs text-text-secondary">
                                Activo
                            </span>
                        </template>
                    </PresupuestoCard>
                </div>
            </div>
        </div>

        <ConfirmationModal :show="borrando !== null" @close="cerrarModal">
            <template #title>
                Eliminar presupuesto
            </template>

            <template #content>
                ¿Seguro que quieres eliminar "{{ borrando?.nombre }}"? Las cuentas y transacciones asociadas se conservan en la base de datos, pero el presupuesto dejará de estar disponible.
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
