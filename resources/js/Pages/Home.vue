<script setup>
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Hero from '@/Components/Home/Hero.vue'
import About from '@/Components/Home/About.vue'
import Skills from '@/Components/Home/Skills.vue'
import Projects from '@/Components/Home/Projects.vue'
</script>

<template>
  <AppLayout>
    <Hero />
      <About />
       <Skills />
         <Projects />
  </AppLayout>
</template>
