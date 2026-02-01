<script setup>
import Navbar from './Navbar.vue';
import Footer from './Footer.vue';
</script>

<template>
    <div class="min-h-screen bg-gray-900 text-white">
        <!-- Navbar -->
        <Navbar />

        <!-- Main Content  -->
        <main class="pt-20">
            <slot />
        </main>

        <!-- Footer placeholder -->
        <Footer />
        </div>
</template>
