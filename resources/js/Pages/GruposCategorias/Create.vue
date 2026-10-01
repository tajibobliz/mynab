<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ColorPicker from '@/Components/ColorPicker.vue';
import IconoSelector from '@/Components/IconoSelector.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { grupoCategoriaIconMap } from '@/grupoCategoriaIconMap';

defineProps({
    presupuestoActivo: Object,
});

const form = useForm({
    nombre: '',
    color: '#ef4444',
    icono: 'AlertCircle',
});

const submit = () => {
    form.post(route('grupos-categorias.store'));
};
</script>

<template>
    <Head title="Nuevo grupo" />

    <AppLayout title="Nuevo grupo">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                Nuevo grupo
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <p class="mb-4 text-sm text-text-secondary">
                    Este grupo se creará en el presupuesto <span class="font-medium text-text">{{ presupuestoActivo.nombre }}</span>.
                </p>

                <form
                    class="bg-white shadow-xl sm:rounded-lg p-6 lg:p-8 space-y-6 dark:bg-surface dark:border dark:border-border dark:shadow-none"
                    @submit.prevent="submit"
                >
                    <div>
                        <InputLabel for="nombre" value="Nombre" />
                        <TextInput
                            id="nombre"
                            v-model="form.nombre"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autofocus
                        />
                        <InputError class="mt-2" :message="form.errors.nombre" />
                    </div>

                    <div>
                        <InputLabel value="Icono" />
                        <div class="mt-2">
                            <IconoSelector
                                v-model="form.icono"
                                :iconos="$page.props.iconosGrupoCategoria"
                                :icon-map="grupoCategoriaIconMap"
                            />
                        </div>
                        <InputError class="mt-2" :message="form.errors.icono" />
                    </div>

                    <div>
                        <InputLabel value="Color" />
                        <div class="mt-2">
                            <ColorPicker v-model="form.color" />
                        </div>
                        <InputError class="mt-2" :message="form.errors.color" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-border">
                        <Link :href="route('grupos-categorias.index')">
                            <SecondaryButton type="button" :disabled="form.processing">
                                Cancelar
                            </SecondaryButton>
                        </Link>

                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Crear grupo
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
