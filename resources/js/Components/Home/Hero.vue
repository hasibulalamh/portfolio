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
  auth: { user: any }
  heroSettings?: HeroSettings
  footerItems?: { social: FooterItem[] }
  [key: string]: any
}

const page = usePage<PageProps>()
const hero = computed(() => page.props.heroSettings)
const socialLinks = computed(() => page.props.footerItems?.social || [])
</script>

<template>
  <section v-if="hero" class="relative min-h-screen flex items-center justify-center overflow-hidden bg-black">

    <!-- Premium Background Pattern -->
    <div class="absolute inset-0">
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_50%,rgba(212,175,55,0.05),transparent_50%)]"></div>
      <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-amber-500/20 to-transparent"></div>
    </div>

    <div class="container mx-auto px-4 py-20 relative z-10">
      <div class="max-w-5xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

          <!-- Left: Content -->
          <div class="text-left order-2 lg:order-1">
            <!-- Name -->
            <h1 class="text-5xl md:text-6xl lg:text-7xl font-light mb-6 tracking-tight">
              <span class="text-gradient-elegant font-extralight">{{ hero.name }}</span>
            </h1>

            <!-- Tagline -->
            <div class="mb-6">
              <div class="h-px w-16 bg-gradient-to-r from-amber-500 to-transparent mb-4"></div>
              <p class="text-xl md:text-2xl text-gray-400 font-light">
                {{ hero.tagline }}
              </p>
            </div>

            <!-- Description -->
            <p v-if="hero.description" class="text-gray-500 leading-relaxed mb-8 text-lg font-light">
              {{ hero.description }}
            </p>

            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 mb-8">

              <a  :href="hero.primary_cta_url"
                class="group px-8 py-4 bg-gradient-to-r from-amber-600 to-yellow-600 text-black font-medium rounded-sm hover:from-amber-500 hover:to-yellow-500 transition-all duration-300 flex items-center justify-center"
              >
                {{ hero.primary_cta_text }}
                <Icon icon="mdi:arrow-right" class="ml-2 w-5 h-5 group-hover:translate-x-1 transition-transform" />
              </a>


              <a :href="hero.resume_url ? `/storage/${hero.resume_url}` : hero.secondary_cta_url"
                :target="hero.resume_url ? '_blank' : '_self'"
                class="px-8 py-4 border border-gray-700 text-gray-300 hover:border-amber-500/50 hover:text-white transition-all duration-300 flex items-center justify-center rounded-sm"
              >
                <Icon icon="mdi:file-document-outline" class="mr-2 w-5 h-5" />
                {{ hero.secondary_cta_text }}
              </a>
            </div>

            <!-- Social Links -->
            <div v-if="hero.show_social_links && socialLinks.length > 0" class="flex gap-4">

             <a   v-for="social in socialLinks"
                :key="social.name"
                :href="social.value"
                target="_blank"
                rel="noopener noreferrer"
                class="w-10 h-10 border border-gray-800 hover:border-amber-500/50 flex items-center justify-center transition-all duration-300 group rounded-sm"
                :title="social.name"
              >
                <Icon
                  :icon="social.icon || 'mdi:link'"
                  class="w-5 h-5 text-gray-600 group-hover:text-amber-500 transition-colors"
                />
              </a>
            </div>
          </div>

          <!-- Right: Photo -->
          <div v-if="hero.show_photo && hero.photo" class="order-1 lg:order-2">
            <div class="relative">
              <!-- Decorative border -->
              <div class="absolute -inset-4 border border-amber-500/20 rounded-sm"></div>
              <div class="absolute -inset-2 border border-gray-800 rounded-sm"></div>

              <!-- Image -->
              <div class="relative overflow-hidden rounded-sm">
                <img
                  :src="`/storage/${hero.photo}`"
                  :alt="hero.name"
                  class="w-full h-auto grayscale hover:grayscale-0 transition-all duration-700"
                />
              </div>

              <!-- Corner accents -->
              <div class="absolute top-0 left-0 w-8 h-8 border-t-2 border-l-2 border-amber-500"></div>
              <div class="absolute bottom-0 right-0 w-8 h-8 border-b-2 border-r-2 border-amber-500"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Scroll Indicator -->
    <div class="absolute bottom-10 left-1/2 transform -translate-x-1/2">
      <a href="#about" class="flex flex-col items-center text-gray-600 hover:text-amber-500 transition-colors">
        <span class="text-xs uppercase tracking-widest mb-2">Scroll</span>
        <div class="w-px h-12 bg-gradient-to-b from-gray-600 to-transparent"></div>
      </a>
    </div>
  </section>
</template>
