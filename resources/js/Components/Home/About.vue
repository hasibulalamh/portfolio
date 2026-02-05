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
  <section v-if="about" id="about" class="relative py-16 bg-gradient-to-b from-gray-900 to-black">
    <!-- Decorative line -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-px bg-gradient-to-r from-transparent via-purple-500 to-transparent"></div>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-6xl">

      <!-- Section Header (Compact) -->
      <div class="text-center mb-12">
        <h2 class="text-3xl md:text-4xl font-bold mb-2">
          <span class="bg-gradient-to-r from-purple-500 via-pink-500 to-orange-500 bg-clip-text text-transparent">
            {{ about.title }}
          </span>
        </h2>
        <div class="w-16 h-1 bg-gradient-to-r from-purple-500 to-pink-500 mx-auto rounded-full"></div>
      </div>

      <!-- Content (Tighter) -->
      <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 items-start mb-12">

        <!-- Image (Smaller) -->
        <div v-if="about.show_image && about.image" class="lg:col-span-2">
          <div class="relative max-w-xs mx-auto">
            <div class="absolute -inset-0.5 bg-gradient-to-r from-purple-500 to-pink-500 rounded-2xl blur opacity-30"></div>
            <img
              :src="`/storage/${about.image}`"
              :alt="about.title"
              class="relative w-full rounded-2xl shadow-2xl"
            />
          </div>
        </div>

        <!-- Bio (Compact) -->
        <div :class="about.show_image && about.image ? 'lg:col-span-3' : 'lg:col-span-5'">
          <p class="text-base text-gray-300 leading-relaxed mb-6">
            {{ about.bio }}
          </p>

          <!-- Highlights (Tighter grid) -->
          <div v-if="about.highlights && about.highlights.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <div
              v-for="(highlight, index) in about.highlights"
              :key="index"
              class="flex items-start"
            >
              <Icon
                icon="mdi:check-circle"
                class="w-5 h-5 text-purple-500 mr-2 flex-shrink-0 mt-0.5"
              />
              <p class="text-sm text-gray-400">{{ highlight.text }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats (Compact) -->
      <div v-if="about.show_stats" class="grid grid-cols-2 md:grid-cols-4 gap-4">

        <div class="relative group">
          <div class="absolute inset-0 bg-gradient-to-r from-purple-500/10 to-pink-500/10 rounded-xl blur"></div>
          <div class="relative bg-gray-800/60 backdrop-blur-sm border border-gray-700/50 rounded-xl p-5 hover:border-purple-500/50 transition-all">
            <div class="text-3xl md:text-4xl font-bold mb-1">
              <span class="bg-gradient-to-r from-purple-500 to-pink-500 bg-clip-text text-transparent">
                {{ animatedYears }}+
              </span>
            </div>
            <p class="text-gray-400 text-xs">Years Experience</p>
          </div>
        </div>

        <div class="relative group">
          <div class="absolute inset-0 bg-gradient-to-r from-purple-500/10 to-pink-500/10 rounded-xl blur"></div>
          <div class="relative bg-gray-800/60 backdrop-blur-sm border border-gray-700/50 rounded-xl p-5 hover:border-purple-500/50 transition-all">
            <div class="text-3xl md:text-4xl font-bold mb-1">
              <span class="bg-gradient-to-r from-purple-500 to-pink-500 bg-clip-text text-transparent">
                {{ animatedProjects }}+
              </span>
            </div>
            <p class="text-gray-400 text-xs">Projects Done</p>
          </div>
        </div>

        <div class="relative group">
          <div class="absolute inset-0 bg-gradient-to-r from-purple-500/10 to-pink-500/10 rounded-xl blur"></div>
          <div class="relative bg-gray-800/60 backdrop-blur-sm border border-gray-700/50 rounded-xl p-5 hover:border-purple-500/50 transition-all">
            <div class="text-3xl md:text-4xl font-bold mb-1">
              <span class="bg-gradient-to-r from-purple-500 to-pink-500 bg-clip-text text-transparent">
                {{ animatedClients }}+
              </span>
            </div>
            <p class="text-gray-400 text-xs">Happy Clients</p>
          </div>
        </div>

        <div class="relative group">
          <div class="absolute inset-0 bg-gradient-to-r from-purple-500/10 to-pink-500/10 rounded-xl blur"></div>
          <div class="relative bg-gray-800/60 backdrop-blur-sm border border-gray-700/50 rounded-xl p-5 hover:border-purple-500/50 transition-all">
            <div class="text-3xl md:text-4xl font-bold mb-1">
              <span class="bg-gradient-to-r from-purple-500 to-pink-500 bg-clip-text text-transparent">
                {{ animatedTechs }}+
              </span>
            </div>
            <p class="text-gray-400 text-xs">Technologies</p>
          </div>
        </div>
      </div>

    </div>
  </section>
</template>
