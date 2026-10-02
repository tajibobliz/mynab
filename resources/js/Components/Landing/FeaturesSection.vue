<script setup>
import { Calculator, Globe, RotateCw, Shield, Split, Target } from 'lucide-vue-next';
import FeatureCard from '@/Components/Landing/FeatureCard.vue';

const features = [
    {
        icon: Target,
        titulo: 'Zero-Based Budgeting',
        descripcion: 'Asigna cada peso de tu ingreso a una categoría. Ready to Assign te muestra cuánto falta por asignar.',
    },
    {
        icon: Globe,
        titulo: 'Multi-moneda nativa',
        descripcion: 'Maneja BOB, USD y USDT en el mismo presupuesto. Conversión automática con tasas que tú controlas.',
    },
    {
        icon: Split,
        titulo: 'Dividir transacciones',
        descripcion: 'Una compra en Hipermaxi puede ser comida y ropa. Divídela en categorías con un solo clic.',
    },
    {
        icon: Calculator,
        titulo: 'Calculadora en el monto',
        descripcion: "Tipea '50+30*2' y MyNAB evalúa a 110. Sin salir del formulario, sin librerías inseguras.",
    },
    {
        icon: RotateCw,
        titulo: 'Rollover entre meses',
        descripcion: 'Lo que sobró este mes queda disponible el próximo. Sin reiniciar, sin perder progreso.',
    },
    {
        icon: Shield,
        titulo: 'Tus datos son tuyos',
        descripcion: 'Instalación local. Tu información financiera nunca sale de tu computadora.',
    },
];
</script>

<template>
    <section id="caracteristicas" class="bg-base py-24 md:py-32 scroll-mt-16">
        <div class="max-w-7xl mx-auto px-6 md:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-text mb-4">Todo lo que necesitas para presupuestar</h2>
                <p class="text-lg text-text-secondary">Las herramientas correctas, sin complejidad innecesaria</p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 md:gap-12">
                <FeatureCard
                    v-for="feature in features"
                    :key="feature.titulo"
                    :icon="feature.icon"
                    :titulo="feature.titulo"
                    :descripcion="feature.descripcion"
                />
            </div>
        </div>
    </section>
</template>
