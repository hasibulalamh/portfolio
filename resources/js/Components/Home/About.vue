<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { usePage } from '@inertiajs/vue3'
import { computed, ref, onMounted } from 'vue'

interface AboutSettings {
  title: string
  subtitle?: string
  bio: string
  image?: string
  years_experience: number
  projects_completed: number
  happy_clients: number
  technologies_count: number
  highlights?: Array<{ text: string }>
  show_image: boolean
  show_stats: boolean
}

interface PageProps {
  auth: {
    user: any
  }
  aboutSettings?: AboutSettings
  [key: string]: any
}

const page = usePage<PageProps>()
const about = computed(() => page.props.aboutSettings)

// Animated counter
const animatedYears = ref(0)
const animatedProjects = ref(0)
const animatedClients = ref(0)
const animatedTechs = ref(0)

const animateValue = (start: number, end: number, duration: number, setter: (val: number) => void) => {
  const startTime = Date.now()
  const animate = () => {
    const now = Date.now()
    const elapsed = now - startTime
    const progress = Math.min(elapsed / duration, 1)

    const easeOut = 1 - Math.pow(1 - progress, 3)
    const current = Math.floor(start + (end - start) * easeOut)

    setter(current)

    if (progress < 1) {
      requestAnimationFrame(animate)
    }
  }
  animate()
}

onMounted(() => {
  if (about.value) {
    setTimeout(() => {
      animateValue(0, about.value.years_experience, 2000, (val) => animatedYears.value = val)
      animateValue(0, about.value.projects_completed, 2500, (val) => animatedProjects.value = val)
      animateValue(0, about.value.happy_clients, 2200, (val) => animatedClients.value = val)
      animateValue(0, about.value.technologies_count, 1800, (val) => animatedTechs.value = val)
    }, 300)
  }
})
</script>

<template>
  <section v-if="about" id="about" class="relative py-24 bg-zinc-950">
    <!-- Top border -->
    <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-amber-500/30 to-transparent"></div>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-6xl">

      <!-- Section Header -->
      <div class="mb-16">
        <p class="text-sm uppercase tracking-widest text-amber-500 mb-4">About Me</p>
        <h2 class="text-4xl md:text-5xl font-light text-white mb-4">
          Crafting Digital <span class="text-gradient-gold">Excellence</span>
        </h2>
        <div class="h-px w-24 bg-gradient-to-r from-amber-500 to-transparent"></div>
      </div>

      <!-- Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 mb-16">

        <!-- Bio -->
        <div class="lg:col-span-2">
          <p class="text-lg text-gray-400 leading-relaxed font-light mb-6">
            {{ about.bio }}
          </p>

          <!-- Highlights -->
          <div v-if="about.highlights && about.highlights.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="(highlight, index) in about.highlights"
              :key="index"
              class="flex items-start group"
            >
              <div class="w-1 h-1 bg-amber-500 mt-2 mr-3 flex-shrink-0"></div>
              <p class="text-sm text-gray-500 group-hover:text-gray-300 transition-colors">{{ highlight.text }}</p>
            </div>
          </div>
        </div>

       <!-- Image -->
        <div v-if="about.show_image && about.image" class="relative flex justify-center lg:justify-start">
        <div class="relative w-64 h-64 lg:w-full lg:h-80">
            <div class="absolute -inset-2 border border-amber-500/20"></div>
            <div class="absolute inset-0 border border-gray-800"></div>
            <img
            :src="`/storage/${about.image}`"
            :alt="about.title"
            class="w-full h-full object-cover object-top grayscale hover:grayscale-0 transition-all duration-700"
            />
            <!-- Corner accents -->
            <div class="absolute top-0 left-0 w-6 h-6 border-t-2 border-l-2 border-amber-500"></div>
            <div class="absolute bottom-0 right-0 w-6 h-6 border-b-2 border-r-2 border-amber-500"></div>
        </div>
        </div>

      </div>

      <!-- GitHub Activity -->
      <div class="mb-16">
        <div class="flex items-center gap-4 mb-6">
          <div class="h-px w-8 bg-amber-500/50"></div>
          <p class="text-xs uppercase tracking-widest text-amber-500">GitHub Activity</p>
        </div>
        <div class="border border-gray-900 p-4 overflow-hidden">
          <img
            src="https://ghchart.rshah.org/d97706/HASIBULALAMH"
            alt="GitHub Activity Graph"
            class="w-full opacity-80 hover:opacity-100 transition-opacity duration-300"
          />
        </div>
        <div class="flex gap-6 mt-4">
          <a
            href="https://github.com/HASIBULALAMH"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center gap-2 text-sm text-gray-600 hover:text-amber-500 transition-colors"
          >
            <Icon icon="mdi:github" class="w-4 h-4" />
            View GitHub Profile
          </a>
        </div>
      </div>

      <!-- Stats -->
      <div v-if="about.show_stats" class="grid grid-cols-2 md:grid-cols-4 gap-8">

        <div class="text-center group">
          <div class="text-5xl font-light mb-2 text-gradient-gold">
            {{ animatedYears }}+
          </div>
          <div class="h-px w-12 bg-amber-500/50 mx-auto mb-2"></div>
          <p class="text-sm text-gray-500 uppercase tracking-wider">Years Experience</p>
        </div>

        <div class="text-center group">
          <div class="text-5xl font-light mb-2 text-gradient-gold">
            {{ animatedProjects }}+
          </div>
          <div class="h-px w-12 bg-amber-500/50 mx-auto mb-2"></div>
          <p class="text-sm text-gray-500 uppercase tracking-wider">Projects Done</p>
        </div>

        <div class="text-center group">
          <div class="text-5xl font-light mb-2 text-gradient-gold">
            {{ animatedClients }}+
          </div>
          <div class="h-px w-12 bg-amber-500/50 mx-auto mb-2"></div>
          <p class="text-sm text-gray-500 uppercase tracking-wider">Happy Clients</p>
        </div>

        <div class="text-center group">
          <div class="text-5xl font-light mb-2 text-gradient-gold">
            {{ animatedTechs }}+
          </div>
          <div class="h-px w-12 bg-amber-500/50 mx-auto mb-2"></div>
          <p class="text-sm text-gray-500 uppercase tracking-wider">Technologies</p>
        </div>
      </div>

    </div>
  </section>
</template>
