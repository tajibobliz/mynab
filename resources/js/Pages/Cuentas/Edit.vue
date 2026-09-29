<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import MonedaSelector from '@/Components/MonedaSelector.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TipoCuentaSelector from '@/Components/TipoCuentaSelector.vue';
import { centavosDesdeMonto } from '@/utils/formatoMoneda';

const props = defineProps({
    cuenta: Object,
    tiposCuenta: Array,
});

const decimales = props.cuenta.moneda.decimales;

const form = useForm({
    nombre: props.cuenta.nombre,
    tipo: props.cuenta.tipo,
    // saldo_inicial_centavos (fijo, no saldo_actual): es el campo editable de
    // verdad, saldo_actual es un accessor computed que en Change 7 sumará
    // transacciones — no tiene sentido "editar" ese.
    monto: (props.cuenta.saldo_inicial_centavos / 10 ** decimales).toFixed(decimales),
    fecha_apertura: props.cuenta.fecha_apertura.slice(0, 10),
    numero_referencia: props.cuenta.numero_referencia ?? '',
});

const submit = () => {
    form.transform(({ monto, ...resto }) => ({
        ...resto,
        saldo_inicial_centavos: centavosDesdeMonto(monto, decimales),
    })).put(route('cuentas.update', props.cuenta.id));
};
</script>

<template>
    <Head :title="`Editar cuenta: ${cuenta.nombre}`" />

    <AppLayout :title="`Editar cuenta: ${cuenta.nombre}`">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                Editar cuenta: {{ cuenta.nombre }}
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
                            <InputLabel value="Tipo de cuenta" />
                            <TipoCuentaSelector v-model="form.tipo" :tipos-cuenta="tiposCuenta" class="mt-1" />
                            <InputError class="mt-2" :message="form.errors.tipo" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <InputLabel for="moneda_codigo" value="Moneda" />
                            <MonedaSelector
                                id="moneda_codigo"
                                :model-value="cuenta.moneda_codigo"
                                disabled
                                title="No se puede cambiar la moneda después de crear la cuenta"
                                class="mt-1 block w-full"
                            />
                        </div>

                        <div>
                            <InputLabel for="monto" value="Saldo inicial" />
                            <div class="mt-1 flex items-center gap-2">
                                <span class="font-mono text-text-secondary w-10 shrink-0">{{ cuenta.moneda.simbolo }}</span>
                                <TextInput
                                    id="monto"
                                    v-model="form.monto"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="block w-full font-mono"
                                    required
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.saldo_inicial_centavos" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <InputLabel for="fecha_apertura" value="Fecha de apertura" />
                            <TextInput
                                id="fecha_apertura"
                                v-model="form.fecha_apertura"
                                type="date"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.fecha_apertura" />
                        </div>

                        <div>
                            <InputLabel for="numero_referencia" value="Número de referencia (opcional)" />
                            <TextInput
                                id="numero_referencia"
                                v-model="form.numero_referencia"
                                type="text"
                                placeholder="**4417"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-2" :message="form.errors.numero_referencia" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-border">
                        <Link :href="route('cuentas.index')">
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
