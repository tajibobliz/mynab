<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import MontoCalculadora from '@/Components/MontoCalculadora.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectorBeneficiario from '@/Components/SelectorBeneficiario.vue';
import SelectorCategoria from '@/Components/SelectorCategoria.vue';
import SelectorCuenta from '@/Components/SelectorCuenta.vue';
import SplitForm from '@/Components/SplitForm.vue';
import Textarea from '@/Components/Textarea.vue';
import { tipoTransaccionIconMap } from '@/tipoTransaccionIconMap';
import { combinarFechaHora, fechaYHoraLocal } from '@/utils/fechaHora';

const props = defineProps({
    transaccion: Object,
    tiposTransaccion: Array,
});

// Mismos tokens literales que Create.vue (ver esa nota) — repetidos porque
// el proyecto prefiere duplicación sobre una base compartida para estas
// 2 vistas (patrón ya consolidado desde Store/Update de Changes 2-7).
const CLASES_TIPO_ACTIVO = {
    'status-danger': 'border-status-danger bg-status-danger/10 text-status-danger',
    'status-success': 'border-status-success bg-status-success/10 text-status-success',
    'status-info': 'border-status-info bg-status-info/10 text-status-info',
};

const opcionTipoActual = computed(() => props.tiposTransaccion.find((o) => o.value === props.transaccion.tipo));

const { fecha, hora } = fechaYHoraLocal(props.transaccion.fecha_hora);

// tipo y es_split NO están en el form: son fijos tras crear (design.md
// Decisión 6). Se leen de `transaccion` (prop inmutable) para los v-if,
// nunca del form.
const form = useForm({
    cuenta_id: props.transaccion.cuenta_id,
    cuenta_destino_id: props.transaccion.cuenta_destino_id ?? '',
    categoria_id: props.transaccion.categoria_id ?? '',
    beneficiario_id: props.transaccion.beneficiario_id,
    monto_centavos: props.transaccion.monto_centavos,
    fecha,
    hora,
    notas: props.transaccion.notas ?? '',
    splits: (props.transaccion.splits ?? []).map((split) => ({
        categoria_id: split.categoria_id,
        monto_centavos: split.monto_centavos,
        notas: split.notas ?? '',
    })),
});

const cuentas = computed(() => usePage().props.cuentasDelPresupuestoActivo);

const monedaCodigoActual = computed(() => {
    const cuenta = cuentas.value.find((c) => c.id === Number(form.cuenta_id));
    return cuenta?.moneda_codigo ?? props.transaccion.cuenta.moneda_codigo;
});

const submit = () => {
    form.transform((datos) => {
        const { fecha, hora, ...resto } = datos;
        return { ...resto, fecha_hora: combinarFechaHora(fecha, hora) };
    }).put(route('transacciones.update', props.transaccion.id));
};
</script>

<template>
    <Head title="Editar transacción" />

    <AppLayout title="Editar transacción">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                Editar transacción
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <form
                    class="bg-white shadow-xl sm:rounded-lg p-6 lg:p-8 space-y-6 dark:bg-surface dark:border dark:border-border dark:shadow-none"
                    @submit.prevent="submit"
                >
                    <div>
                        <InputLabel value="Tipo de transacción" />
                        <!--
                            Badge no-interactivo en vez de los 3 botones de
                            Create.vue: el tipo es fijo tras crear, mostrar 3
                            botones (2 deshabilitados + 1 "activo" pero
                            igualmente sin click) sugeriría falsamente que
                            cambiar de tipo es posible. Un solo elemento
                            informativo es más honesto sobre el estado real.
                        -->
                        <div
                            class="mt-2 inline-flex items-center gap-2 px-4 py-3 rounded-lg border-2"
                            :class="CLASES_TIPO_ACTIVO[opcionTipoActual.color]"
                            title="El tipo no se puede cambiar después de crear la transacción"
                        >
                            <component :is="tipoTransaccionIconMap[opcionTipoActual.icono]" class="size-5" />
                            <span class="text-sm font-medium">{{ opcionTipoActual.label }}</span>
                        </div>
                    </div>

                    <div>
                        <InputLabel :value="transaccion.tipo === 'transfer' ? 'Cuenta origen' : 'Cuenta'" />
                        <SelectorCuenta v-model="form.cuenta_id" :cuentas="cuentas" class="mt-1" />
                        <InputError class="mt-2" :message="form.errors.cuenta_id" />
                    </div>

                    <div v-if="transaccion.tipo === 'transfer'">
                        <InputLabel value="Cuenta destino" />
                        <SelectorCuenta
                            v-model="form.cuenta_destino_id"
                            :cuentas="cuentas"
                            :excluir-id="Number(form.cuenta_id) || null"
                            class="mt-1"
                        />
                        <InputError class="mt-2" :message="form.errors.cuenta_destino_id" />
                    </div>

                    <div v-if="transaccion.tipo !== 'transfer'">
                        <InputLabel :value="transaccion.tipo === 'inflow' ? 'Beneficiario' : 'Beneficiario (opcional)'" />
                        <SelectorBeneficiario v-model="form.beneficiario_id" class="mt-1" />
                        <InputError class="mt-2" :message="form.errors.beneficiario_id" />
                    </div>

                    <div v-if="transaccion.tipo === 'outflow' && ! transaccion.es_split">
                        <InputLabel value="Categoría" />
                        <SelectorCategoria v-model="form.categoria_id" class="mt-1" />
                        <InputError class="mt-2" :message="form.errors.categoria_id" />
                    </div>

                    <div>
                        <InputLabel for="monto" value="Monto" />
                        <p class="text-xs text-text-secondary mb-1">Puedes usar expresiones: + - * /</p>
                        <MontoCalculadora id="monto" v-model="form.monto_centavos" :moneda-codigo="monedaCodigoActual" />
                        <InputError class="mt-2" :message="form.errors.monto_centavos" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="fecha" value="Fecha" />
                            <input
                                id="fecha"
                                v-model="form.fecha"
                                type="date"
                                class="mt-1 block w-full border-gray-300 focus:border-accent-primary focus:ring-accent-primary rounded-md shadow-sm dark:bg-base dark:border-border dark:text-text transition duration-150 ease-in-out"
                            >
                        </div>
                        <div>
                            <InputLabel for="hora" value="Hora" />
                            <input
                                id="hora"
                                v-model="form.hora"
                                type="time"
                                class="mt-1 block w-full border-gray-300 focus:border-accent-primary focus:ring-accent-primary rounded-md shadow-sm dark:bg-base dark:border-border dark:text-text transition duration-150 ease-in-out"
                            >
                        </div>
                    </div>
                    <InputError :message="form.errors.fecha_hora" />

                    <label v-if="transaccion.tipo === 'outflow'" class="flex items-center gap-2 w-fit" title="No se puede cambiar después de crear la transacción">
                        <Checkbox :checked="transaccion.es_split" disabled />
                        <span class="text-sm text-text-secondary">Dividir en categorías (fijo tras crear)</span>
                    </label>

                    <SplitForm
                        v-if="transaccion.es_split"
                        v-model="form.splits"
                        :monto-total-centavos="form.monto_centavos ?? 0"
                        :moneda-codigo="monedaCodigoActual"
                    />
                    <InputError :message="form.errors.splits" />

                    <div>
                        <InputLabel for="notas" value="Notas (opcional)" />
                        <Textarea id="notas" v-model="form.notas" :rows="3" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.notas" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-border">
                        <Link :href="route('transacciones.index')">
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
