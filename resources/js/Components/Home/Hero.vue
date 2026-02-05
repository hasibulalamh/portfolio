<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

interface HeroSettings {
  name: string
  tagline: string
  description?: string
  photo?: string
  resume_url?: string
  primary_cta_text: string
  primary_cta_url: string
  secondary_cta_text: string
  secondary_cta_url: string
  show_photo: boolean
  show_social_links: boolean
}

interface FooterItem {
  name: string
  value: string
  icon?: string
}

interface PageProps {
  auth: {
    user: any
  }
  heroSettings?: HeroSettings
  footerItems?: {
    social: FooterItem[]
  }
  [key: string]: any
}

const page = usePage<PageProps>()
const hero = computed(() => page.props.heroSettings)
const socialLinks = computed(() => page.props.footerItems?.social || [])
</script>

<template>
  <section v-if="hero" class="relative min-h-[85vh] flex items-center justify-center overflow-hidden py-12">
    <!-- Animated Background -->
    <div class="absolute inset-0 -z-10">
      <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
      <div class="absolute top-1/3 right-1/4 w-96 h-96 bg-pink-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>
      <div class="absolute bottom-1/4 left-1/3 w-96 h-96 bg-orange-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000"></div>
    </div>

    <div class="container mx-auto px-4">
      <div class="flex flex-col items-center text-center">

        <!-- Profile Photo -->
        <div v-if="hero.show_photo && hero.photo" class="mb-6 relative group">
          <div class="w-40 h-40 md:w-48 md:h-48 rounded-full bg-gradient-to-r from-purple-500 via-pink-500 to-orange-500 p-1 group-hover:scale-105 transition-transform duration-300">
            <img
              :src="`/storage/${hero.photo}`"
              :alt="hero.name"
              class="w-full h-full rounded-full object-cover"
            />
          </div>
          <div class="absolute inset-0 rounded-full border-4 border-purple-500/30 animate-ping"></div>
        </div>

        <!-- Name -->
        <h1 class="text-4xl md:text-6xl lg:text-7xl font-bold mb-4 animate-fade-up">
          <span class="bg-gradient-to-r from-purple-500 via-pink-500 to-orange-500 bg-clip-text text-transparent hover:from-orange-500 hover:via-pink-500 hover:to-purple-500 transition-all duration-500">
            {{ hero.name }}
          </span>
        </h1>

        <!-- Tagline -->
        <p class="text-xl md:text-2xl lg:text-3xl text-gray-300 mb-4 animate-fade-up animation-delay-200">
          {{ hero.tagline }}
        </p>

        <!-- Description -->
        <p v-if="hero.description" class="text-base text-gray-400 max-w-2xl mx-auto mb-8 leading-relaxed animate-fade-up animation-delay-400">
          {{ hero.description }}
        </p>

        <!-- CTA Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center mb-8 animate-fade-up animation-delay-600">
          <!-- Primary Button (Hire Me) -->
            <a
            :href="hero.primary_cta_url"
            class="group px-8 py-4 bg-gradient-to-r from-purple-500 to-pink-500 rounded-lg text-lg font-semibold hover:shadow-lg hover:shadow-purple-500/50 transition-all duration-300 hover:scale-105 flex items-center justify-center"
          >
            {{ hero.primary_cta_text }}
            <Icon icon="mdi:arrow-right" class="ml-2 w-5 h-5 group-hover:translate-x-1 transition-transform" />
          </a>

          <!-- Secondary Button (View Resume) - ALWAYS SHOW -->
          <a
            :href="hero.resume_url ? `/storage/${hero.resume_url}` : hero.secondary_cta_url"
            :target="hero.resume_url ? '_blank' : '_self'"
            :download="hero.resume_url ? true : false"
            class="group px-8 py-4 border-2 border-purple-500 rounded-lg text-lg font-semibold hover:bg-purple-500/10 transition-all duration-300 hover:scale-105 flex items-center justify-center"
          >
            <Icon icon="mdi:file-document" class="mr-2 w-5 h-5" />
            {{ hero.secondary_cta_text }}
          </a>
        </div>

        <!-- Social Links -->
        <div v-if="hero.show_social_links && socialLinks.length > 0" class="flex gap-4 animate-fade-up animation-delay-800">

           <a v-for="social in socialLinks"
            :key="social.name"
            :href="social.value"
            target="_blank"
            rel="noopener noreferrer"
            class="group w-12 h-12 bg-gray-800/50 backdrop-blur-sm rounded-lg flex items-center justify-center border border-gray-700 hover:border-purple-500 hover:bg-gradient-to-r hover:from-purple-500 hover:to-pink-500 transition-all duration-300 hover:scale-110"
            :title="social.name"
          >
            <Icon
              :icon="social.icon || 'mdi:link'"
              class="w-5 h-5 text-gray-400 group-hover:text-white transition-colors"
            />
          </a>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
@keyframes blob {
  0% { transform: translate(0px, 0px) scale(1); }
  33% { transform: translate(30px, -50px) scale(1.1); }
  66% { transform: translate(-20px, 20px) scale(0.9); }
  100% { transform: translate(0px, 0px) scale(1); }
}

.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

@keyframes fade-up {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-up {
  animation: fade-up 0.6s ease-out forwards;
  opacity: 0;
}

.animation-delay-200 {
  animation-delay: 0.2s;
}

.animation-delay-400 {
  animation-delay: 0.4s;
}

.animation-delay-600 {
  animation-delay: 0.6s;
}

.animation-delay-800 {
  animation-delay: 0.8s;
}
</style>
