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

const props = defineProps({
    presupuesto: Object,
});

const form = useForm({
    nombre: props.presupuesto.nombre,
    descripcion: props.presupuesto.descripcion ?? '',
    color: props.presupuesto.color,
    icono: props.presupuesto.icono,
});

const submit = () => {
    form.put(route('presupuestos.update', props.presupuesto.id));
};
</script>

<template>
    <Head :title="`Editar ${presupuesto.nombre}`" />

    <AppLayout :title="`Editar ${presupuesto.nombre}`">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                Editar {{ presupuesto.nombre }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
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
                        <InputLabel for="descripcion" value="Descripción (opcional)" />
                        <textarea
                            id="descripcion"
                            v-model="form.descripcion"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 focus:border-accent-primary focus:ring-accent-primary dark:bg-base dark:border-border dark:text-text dark:placeholder-text-secondary transition duration-150 ease-in-out"
                        />
                        <InputError class="mt-2" :message="form.errors.descripcion" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <InputLabel value="Color" />
                            <div class="mt-2">
                                <ColorPicker v-model="form.color" />
                            </div>
                            <InputError class="mt-2" :message="form.errors.color" />
                        </div>

                        <div>
                            <InputLabel value="Icono" />
                            <div class="mt-2">
                                <IconoSelector v-model="form.icono" />
                            </div>
                            <InputError class="mt-2" :message="form.errors.icono" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-border">
                        <Link :href="route('presupuestos.index')">
                            <SecondaryButton type="button" :disabled="form.processing">
                                Cancelar
                            </SecondaryButton>
                        </Link>

                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Guardar cambios
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
