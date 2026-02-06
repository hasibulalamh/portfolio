<script setup>
import Navbar from './Navbar.vue'
import Footer from './Footer.vue'
</script>

<template>
  <div class="min-h-screen bg-black text-white">
    <Navbar />

    <main class="pt-20">
      <slot />
      
    </main>

    <Footer />
  </div>
</template>
