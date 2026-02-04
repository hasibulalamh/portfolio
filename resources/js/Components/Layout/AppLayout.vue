<script setup>
import Navbar from './Navbar.vue'
import Footer from './Footer.vue'
</script>

<template>
  <div class="min-h-screen bg-gray-900 text-white">
    <!-- Navbar -->
    <Navbar />

    <!-- Main Content (reduced padding) -->
    <main class="pt-16">
      <slot />
    </main>

    <!-- Footer -->
    <Footer />
  </div>
</template>
