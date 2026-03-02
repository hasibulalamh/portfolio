<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'

// Import all possible components
import Hero from '@/Components/Home/Hero.vue'
import About from '@/Components/Home/About.vue'
import Skills from '@/Components/Home/Skills.vue'
import Projects from '@/Components/Home/Projects.vue'
import Experience from '@/Components/Home/Experience.vue'
import Contact from '@/Components/Home/Contact.vue'
import Testimonials from '@/Components/Home/Testimonials.vue'
import Services from '@/Components/Home/Services.vue'

const page = usePage()

// Map component names to actual components
const componentMap = {
  Hero,
  About,
  Skills,
  Projects,
  Experience,
  Contact,
  Testimonials,
  Services,

}

// Get active sections from database
const sections = computed(() => page.props.homepageSections || [])
</script>

<template>
  <AppLayout>
    <!-- Dynamically render sections based on database -->
    <component
      v-for="section in sections"
      :key="section.section_id"
      :is="componentMap[section.component]"
    />
  </AppLayout>
</template>
