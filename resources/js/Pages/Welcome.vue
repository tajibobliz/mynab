<script setup>
import { Head } from '@inertiajs/vue3';
import FeaturesSection from '@/Components/Landing/FeaturesSection.vue';
import FinalCtaSection from '@/Components/Landing/FinalCtaSection.vue';
import HeroSection from '@/Components/Landing/HeroSection.vue';
import HowItWorksSection from '@/Components/Landing/HowItWorksSection.vue';
import LandingFooter from '@/Components/Landing/LandingFooter.vue';
import LandingNav from '@/Components/Landing/LandingNav.vue';
import ProblemSolutionSection from '@/Components/Landing/ProblemSolutionSection.vue';
import TechStackSection from '@/Components/Landing/TechStackSection.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});

// Scroll suave hacia una sección por id, sin tocar el <html> global (que
// afectaría al resto de la app, no solo a esta página). null = volver arriba
// (logo del nav).
const navegarASeccion = (id) => {
    if (! id) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};
</script>

<template>
    <Head title="MyNAB — Zero-Based Budgeting" />

    <div class="bg-base text-text">
        <LandingNav @navigate="navegarASeccion" />

        <main>
            <HeroSection :can-register="canRegister" @navigate="navegarASeccion" />
            <ProblemSolutionSection />
            <FeaturesSection />
            <HowItWorksSection />
            <TechStackSection />
            <FinalCtaSection :can-register="canRegister" />
        </main>

        <LandingFooter @navigate="navegarASeccion" />
    </div>
</template>
