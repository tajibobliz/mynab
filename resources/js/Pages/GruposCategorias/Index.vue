<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Boxes, Plus } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import CategoriaModal from '@/Components/CategoriaModal.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import GrupoCategoriaCard from '@/Components/GrupoCategoriaCard.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    grupos: Array,
    presupuestoActivo: Object,
});

// Orden alfabético defensivo en el frontend: el backend (Grupo 6) ya lo
// entrega ordenado, pero no se asume — una query futura que lo rompa no
// debería silenciosamente desordenar la UI.
const gruposOrdenados = computed(() => [...props.grupos].sort((a, b) => a.nombre.localeCompare(b.nombre)));

const categoriasOrdenadas = (grupo) => [...grupo.categorias].sort((a, b) => a.nombre.localeCompare(b.nombre));

// --- Modal de categoría (crear/editar) ---
const showCategoriaModal = ref(false);
const modoModal = ref('create');
const grupoCategoriaIdActual = ref(null);
const categoriaEnEdicion = ref(null);

const abrirCrearCategoria = (grupoId) => {
    modoModal.value = 'create';
    grupoCategoriaIdActual.value = grupoId;
    categoriaEnEdicion.value = null;
    showCategoriaModal.value = true;
};

const abrirEditarCategoria = (categoria) => {
    modoModal.value = 'edit';
    grupoCategoriaIdActual.value = categoria.grupo_categoria_id;
    categoriaEnEdicion.value = categoria;
    showCategoriaModal.value = true;
};

// --- Confirmación de borrado (grupo o categoría) ---
const confirmandoBorradoDe = ref(null); // { tipo: 'grupo'|'categoria', item }

const confirmarBorrado = (tipo, item) => {
    confirmandoBorradoDe.value = { tipo, item };
};

const cerrarConfirmacion = () => {
    confirmandoBorradoDe.value = null;
};

const ejecutarBorrado = () => {
    const { tipo, item } = confirmandoBorradoDe.value;
    const url = tipo === 'grupo'
        ? route('grupos-categorias.destroy', item.id)
        : route('categorias.destroy', item.id);

    router.delete(url, {
        onSuccess: () => { confirmandoBorradoDe.value = null; },
    });
};

const tituloConfirmacion = computed(() => (confirmandoBorradoDe.value?.tipo === 'grupo' ? 'Eliminar grupo' : 'Eliminar categoría'));

const mensajeConfirmacion = computed(() => {
    if (!confirmandoBorradoDe.value) return '';

    const { tipo, item } = confirmandoBorradoDe.value;

    if (tipo === 'categoria') {
        return `¿Eliminar la categoría "${item.nombre}"?`;
    }

    const cantidad = item.categorias?.length ?? 0;

    return cantidad > 0
        ? `¿Eliminar "${item.nombre}"? Al eliminar este grupo, también se eliminarán sus ${cantidad} categorías.`
        : `¿Eliminar "${item.nombre}"?`;
});
</script>

<template>
    <Head title="Grupos y categorías" />

    <AppLayout title="Grupos y categorías">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                    Grupos y categorías
                </h2>

                <Link
                    :href="route('grupos-categorias.create')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent-primary border border-transparent rounded-md font-semibold text-xs text-gray-950 uppercase tracking-widest hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
                >
                    <Plus class="size-4" />
                    Nuevo grupo
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div v-if="gruposOrdenados.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                    <Boxes class="size-16 text-gray-300 dark:text-text-secondary mb-4" />
                    <p class="text-gray-600 dark:text-text-secondary mb-4">Aún no tienes grupos de categorías</p>
                    <Link
                        :href="route('grupos-categorias.create')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent-primary border border-transparent rounded-md font-semibold text-xs text-gray-950 uppercase tracking-widest hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
                    >
                        <Plus class="size-4" />
                        Crear primer grupo
                    </Link>
                </div>

                <div v-else class="space-y-4">
                    <GrupoCategoriaCard v-for="grupo in gruposOrdenados" :key="grupo.id" :grupo="grupo">
                        <template #actions>
                            <Link :href="route('grupos-categorias.edit', grupo.id)" class="text-text-secondary hover:text-text transition duration-150">
                                Editar grupo
                            </Link>
                            <button
                                type="button"
                                class="text-status-danger hover:text-red-400 transition duration-150"
                                @click="confirmarBorrado('grupo', grupo)"
                            >
                                Eliminar grupo
                            </button>
                        </template>

                        <template #categorias>
                            <button
                                v-if="grupo.categorias.length === 0"
                                type="button"
                                class="text-sm text-text-secondary hover:text-text transition duration-150"
                                @click="abrirCrearCategoria(grupo.id)"
                            >
                                Este grupo no tiene categorías. Agregar una →
                            </button>

                            <ul v-else class="divide-y divide-gray-100 dark:divide-border mb-3">
                                <li
                                    v-for="categoria in categoriasOrdenadas(grupo)"
                                    :key="categoria.id"
                                    class="py-2 flex items-center justify-between gap-3 text-sm"
                                >
                                    <span class="text-text truncate">{{ categoria.nombre }}</span>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <button
                                            type="button"
                                            class="text-text-secondary hover:text-text transition duration-150"
                                            @click="abrirEditarCategoria(categoria)"
                                        >
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            class="text-status-danger hover:text-red-400 transition duration-150"
                                            @click="confirmarBorrado('categoria', categoria)"
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                </li>
                            </ul>

                            <button
                                v-if="grupo.categorias.length > 0"
                                type="button"
                                class="inline-flex items-center gap-1 text-sm text-accent-primary hover:text-green-400 font-medium transition duration-150"
                                @click="abrirCrearCategoria(grupo.id)"
                            >
                                <Plus class="size-3.5" />
                                Nueva categoría
                            </button>
                        </template>
                    </GrupoCategoriaCard>
                </div>
            </div>
        </div>

        <CategoriaModal
            :show="showCategoriaModal"
            :modo="modoModal"
            :grupo-categoria-id="grupoCategoriaIdActual"
            :categoria="categoriaEnEdicion"
            @close="showCategoriaModal = false"
        />

        <ConfirmationModal :show="confirmandoBorradoDe !== null" @close="cerrarConfirmacion">
            <template #title>
                {{ tituloConfirmacion }}
            </template>

            <template #content>
                {{ mensajeConfirmacion }}
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
