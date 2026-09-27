<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';

defineProps({
    terms: String,
});
</script>

<template>
    <Head title="Terms of Service" />

    <div class="font-sans text-gray-900 dark:text-text antialiased">
        <div class="pt-4 bg-gray-100 dark:bg-base">
            <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0">
                <div>
                    <AuthenticationCardLogo />
                </div>

                <div class="w-full sm:max-w-2xl mt-6 p-6 bg-white shadow-md overflow-hidden sm:rounded-lg prose dark:bg-surface dark:border dark:border-border dark:shadow-none dark:prose-invert" v-html="terms" />
            </div>
        </div>
    </div>
</template>
