<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Users } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    beneficiarios: Array,
    presupuestoActivo: Object,
});

// Orden alfabético defensivo en el frontend: el backend (Grupo 4) ya lo
// entrega ordenado, pero no se asume (mismo patrón que GruposCategorias/Index).
const beneficiariosOrdenados = computed(() => [...props.beneficiarios].sort((a, b) => a.nombre.localeCompare(b.nombre)));

const confirmandoBorradoDe = ref(null);
const confirmarBorrado = (beneficiario) => { confirmandoBorradoDe.value = beneficiario; };
const cerrarConfirmacion = () => { confirmandoBorradoDe.value = null; };
const ejecutarBorrado = () => {
    router.delete(route('beneficiarios.destroy', confirmandoBorradoDe.value.id), {
        onSuccess: () => { confirmandoBorradoDe.value = null; },
    });
};
</script>

<template>
    <Head title="Beneficiarios" />

    <AppLayout title="Beneficiarios">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                    Beneficiarios
                </h2>

                <Link
                    :href="route('beneficiarios.create')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent-primary border border-transparent rounded-md font-semibold text-xs text-gray-950 uppercase tracking-widest hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
                >
                    <Plus class="size-4" />
                    Nuevo beneficiario
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div v-if="beneficiariosOrdenados.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                    <Users class="size-16 text-gray-300 dark:text-text-secondary mb-4" />
                    <p class="text-gray-600 dark:text-text-secondary mb-4">Aún no tienes beneficiarios en este presupuesto</p>
                    <Link
                        :href="route('beneficiarios.create')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent-primary border border-transparent rounded-md font-semibold text-xs text-gray-950 uppercase tracking-widest hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
                    >
                        <Plus class="size-4" />
                        Nuevo beneficiario
                    </Link>
                </div>

                <ul v-else class="divide-y divide-gray-100 dark:divide-border rounded-lg border border-gray-200 dark:border-border bg-white dark:bg-surface overflow-hidden">
                    <li
                        v-for="beneficiario in beneficiariosOrdenados"
                        :key="beneficiario.id"
                        class="flex items-center justify-between gap-4 px-4 py-3"
                    >
                        <div class="min-w-0">
                            <p class="font-semibold text-text truncate">{{ beneficiario.nombre }}</p>
                            <p v-if="beneficiario.notas" class="text-sm text-text-secondary line-clamp-1">
                                {{ beneficiario.notas }}
                            </p>
                        </div>

                        <div class="flex items-center gap-3 text-sm shrink-0">
                            <Link :href="route('beneficiarios.edit', beneficiario.id)" class="text-text-secondary hover:text-text transition duration-150">
                                Editar
                            </Link>
                            <button
                                type="button"
                                class="text-status-danger hover:text-red-400 transition duration-150"
                                @click="confirmarBorrado(beneficiario)"
                            >
                                Eliminar
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <ConfirmationModal :show="confirmandoBorradoDe !== null" @close="cerrarConfirmacion">
            <template #title>
                Eliminar beneficiario
            </template>

            <template #content>
                ¿Eliminar el beneficiario "{{ confirmandoBorradoDe?.nombre }}"?
            </template>

            <template #footer>
                <SecondaryButton @click="cerrarConfirmacion">
                    Cancelar
                </SecondaryButton>

                <DangerButton class="ms-3" @click="ejecutarBorrado">
                    Eliminar
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
