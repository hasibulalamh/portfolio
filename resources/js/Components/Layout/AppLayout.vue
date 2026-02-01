<script setup>
import Navbar from './Navbar.vue'
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
        <div class="py-12 bg-gray-800 text-center">
            <p class="text-gray-400">Footer coming next...</p>
        </div>
    </div>
</template>
