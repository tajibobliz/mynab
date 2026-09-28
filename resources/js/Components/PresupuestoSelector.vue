<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ChevronDown, Plus } from 'lucide-vue-next';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import PresupuestoCard from '@/Components/PresupuestoCard.vue';

const page = usePage();

// Eloquent serializa la relación BelongsTo `presupuestoActivo()` en
// snake_case (`presupuesto_activo`) dentro de User::toArray() — no
// `presupuestoActivo` como sugiere el nombre del método PHP.
const activo = computed(() => page.props.auth.user.presupuesto_activo);
const otros = computed(() => {
    const todos = page.props.auth.user.presupuestos ?? [];

    return activo.value
        ? todos.filter((p) => p.id !== activo.value.id)
        : todos;
});

const seleccionar = (presupuesto) => {
    router.post(route('presupuestos.seleccionar', presupuesto.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Link
        v-if="!activo"
        :href="route('presupuestos.create')"
        class="inline-flex items-center gap-1.5 px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-accent-primary hover:text-green-400 focus:outline-none transition ease-in-out duration-150"
    >
        <Plus class="size-4" />
        Crear presupuesto
    </Link>

    <Dropdown v-else align="left" width="64">
        <template #trigger>
            <button
                type="button"
                class="inline-flex items-center gap-2 px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 hover:text-gray-700 focus:outline-none dark:text-text-secondary dark:hover:text-text transition ease-in-out duration-150"
            >
                <PresupuestoCard :presupuesto="activo" variant="selector" />
                <ChevronDown class="size-4 shrink-0" />
            </button>
        </template>

        <template #content>
            <div class="w-64">
                <template v-if="otros.length > 0">
                    <div class="block px-4 py-2 text-xs text-gray-400 dark:text-text-secondary">
                        Cambiar de presupuesto
                    </div>

                    <button
                        v-for="presupuesto in otros"
                        :key="presupuesto.id"
                        type="button"
                        class="w-full text-start px-4 py-2 hover:bg-gray-100 dark:hover:bg-border transition duration-150"
                        @click="seleccionar(presupuesto)"
                    >
                        <PresupuestoCard :presupuesto="presupuesto" variant="selector" />
                    </button>

                    <div class="border-t border-gray-200 dark:border-border" />
                </template>

                <DropdownLink :href="route('presupuestos.index')">
                    Gestionar presupuestos
                </DropdownLink>

                <DropdownLink :href="route('presupuestos.create')">
                    <span class="flex items-center gap-1.5">
                        <Plus class="size-4" />
                        Crear nuevo
                    </span>
                </DropdownLink>
            </div>
        </template>
    </Dropdown>
</template>
