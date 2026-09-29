<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import MonedaSelector from '@/Components/MonedaSelector.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    tipoCambio: Object,
});

const form = useForm({
    tasa: props.tipoCambio.tasa,
    fecha: props.tipoCambio.fecha,
});

const submit = () => {
    form.put(route('tipos-cambio.update', props.tipoCambio.id));
};
</script>

<template>
    <Head :title="`Editar ${tipoCambio.moneda_origen} → ${tipoCambio.moneda_destino}`" />

    <AppLayout :title="`Editar ${tipoCambio.moneda_origen} → ${tipoCambio.moneda_destino}`">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                Editar tipo de cambio: {{ tipoCambio.moneda_origen }} → {{ tipoCambio.moneda_destino }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <form
                    class="bg-white shadow-xl sm:rounded-lg p-6 lg:p-8 space-y-6 dark:bg-surface dark:border dark:border-border dark:shadow-none"
                    @submit.prevent="submit"
                >
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <InputLabel value="Moneda de origen" />
                            <MonedaSelector :model-value="tipoCambio.moneda_origen" disabled class="mt-1 block w-full" />
                        </div>

                        <div>
                            <InputLabel value="Moneda de destino" />
                            <MonedaSelector :model-value="tipoCambio.moneda_destino" disabled class="mt-1 block w-full" />
                        </div>
                    </div>
                    <p class="text-xs text-text-secondary -mt-4">
                        El par de monedas no se puede cambiar después de crear el tipo de cambio.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <InputLabel for="tasa" value="Tasa" />
                            <TextInput
                                id="tasa"
                                v-model="form.tasa"
                                type="number"
                                step="0.00000001"
                                min="0.00000001"
                                class="mt-1 block w-full font-mono"
                                required
                                autofocus
                            />
                            <InputError class="mt-2" :message="form.errors.tasa" />
                        </div>

                        <div>
                            <InputLabel for="fecha" value="Fecha" />
                            <TextInput
                                id="fecha"
                                v-model="form.fecha"
                                type="date"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.fecha" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-border">
                        <Link :href="route('tipos-cambio.index')">
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
