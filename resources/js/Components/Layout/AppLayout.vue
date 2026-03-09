<script setup>
import Navbar from './Navbar.vue'
import Footer from './Footer.vue'
import BackToTop from '../UI/BackToTop.vue'
import PageLoader from '../UI/PageLoader.vue'
import WhatsAppButton from '../UI/WhatsAppButton.vue'
</script>

<template>
  <div class="min-h-screen bg-black text-white">
    <PageLoader />
    <Navbar />
    <main class="pt-20">
      <slot />
    </main>
    <Footer />
    <BackToTop />
    <WhatsAppButton />
  </div>
</template>
