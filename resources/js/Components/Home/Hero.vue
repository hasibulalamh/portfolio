<script setup lang="ts">
import { usePage, Link } from '@inertiajs/vue3'
import { Icon } from '@iconify/vue'
import { computed, ref, onMounted, onUnmounted } from 'vue'


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
  show_availability: boolean
  is_available: boolean
  availability_text: string
}

interface SocialLink {
  platform: string
  url: string
  icon?: string
  color?: string
  show_in_hero: boolean
}

interface PageProps {
  auth: { user: any }
  heroSettings?: HeroSettings
  social_links?: SocialLink[]
  [key: string]: any
}

const page = usePage<PageProps>()
const hero = computed(() => page.props.heroSettings)
const socialLinks = computed(() =>
  (page.props.social_links || []).filter(s => s.show_in_hero)
)

// Dynamic icon based on URL
function getCtaIcon(url: string): string {
  if (!url) return 'mdi:arrow-right'
  const u = url.toLowerCase()
  if (u.includes('contact') || u.includes('#contact')) return 'mdi:email-outline'
  if (u.includes('project') || u.includes('#project')) return 'mdi:briefcase-outline'
  if (u.includes('about') || u.includes('#about')) return 'mdi:account-outline'
  if (u.includes('skill') || u.includes('#skill')) return 'mdi:code-tags'
  if (u.includes('resume') || u.includes('cv') || u.includes('.pdf')) return 'mdi:file-document-outline'
  if (u.includes('github')) return 'mdi:github'
  if (u.includes('linkedin')) return 'mdi:linkedin'
  if (u.includes('service') || u.includes('#service')) return 'mdi:cog-outline'
  if (u.includes('blog') || u.includes('#blog')) return 'mdi:post-outline'
  if (u.includes('hire') || u.includes('work')) return 'mdi:handshake-outline'
  return 'mdi:arrow-right'
}

function isInternal(url: string): boolean {
  if (!url) return false
  return url.startsWith('/') || url.startsWith('#') || url.includes(window.location.host)
}

const projectsCount = computed(() => (page.props.projects as any[])?.length || 0)
const experienceYears = computed(() => {
  const experiences = (page.props.experiences as any[]) || []
  if (experiences.length === 0) return 1 // Fallback

  const startDates = experiences.map(e => e.start_date ? new Date(e.start_date).getTime() : Date.now())
  const minDate = new Date(Math.min(...startDates))
  const diff = new Date().getTime() - minDate.getTime()
  const years = diff / (1000 * 60 * 60 * 24 * 365.25)
  return years < 1 ? 1 : Math.floor(years)
})

// Typing animation
const typingTexts = [
  'Full Stack Web Developer',
  'Laravel Specialist',
  'Vue.js Developer',
  'Inertia.js Expert',
  'API Developer',
]

const displayText = ref('')
const currentIndex = ref(0)
const isDeleting = ref(false)
let typingTimer: ReturnType<typeof setTimeout> | null = null
let isComponentActive = true

function type() {

  if (!isComponentActive) return
  const current = typingTexts[currentIndex.value]

  if (isDeleting.value) {
    displayText.value = current.substring(0, displayText.value.length - 1)
  } else {
    displayText.value = current.substring(0, displayText.value.length + 1)
  }

  let speed = isDeleting.value ? 60 : 100

  if (!isDeleting.value && displayText.value === current) {
    speed = 2000
    isDeleting.value = true
  } else if (isDeleting.value && displayText.value === '') {
    isDeleting.value = false
    currentIndex.value = (currentIndex.value + 1) % typingTexts.length
    speed = 400
  }

  typingTimer = setTimeout(type, speed)
}

onMounted(() => {
  typingTimer = setTimeout(type, 800)
})
onUnmounted(() => {
  isComponentActive = false
  if (typingTimer) clearTimeout(typingTimer)
})
</script>

<template>
  <section id="hero" v-if="hero" class="relative min-h-screen flex items-center justify-center overflow-hidden bg-black">

    <!-- Background -->
    <div class="absolute inset-0">
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_50%,rgba(212,175,55,0.05),transparent_50%)]"></div>
      <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-amber-500/20 to-transparent"></div>
    </div>

    <div class="container mx-auto px-4 py-20 relative z-10">
      <div class="max-w-5xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

          <!-- Left: Content -->
          <div class="text-left order-2 lg:order-1">

            <!-- Availability Badge -->
            <div v-if="hero.show_availability" class="mb-6 inline-flex items-center gap-2">
              <div class="flex items-center gap-2 px-4 py-2 rounded-full border"
                :class="hero.is_available
                  ? 'border-green-500/30 bg-green-500/10'
                  : 'border-red-500/30 bg-red-500/10'"
              >
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                    :class="hero.is_available ? 'bg-green-400' : 'bg-red-400'"
                  ></span>
                  <span class="relative inline-flex rounded-full h-2 w-2"
                    :class="hero.is_available ? 'bg-green-500' : 'bg-red-500'"
                  ></span>
                </span>
                <span class="text-xs font-medium tracking-wider uppercase"
                  :class="hero.is_available ? 'text-green-400' : 'text-red-400'"
                >
                  {{ hero.availability_text }}
                </span>
              </div>
            </div>

            <!-- Name -->
            <h1 class="text-5xl md:text-6xl lg:text-7xl font-light mb-6 tracking-tight">
              <span class="text-gradient-elegant font-extralight">{{ hero.name }}</span>
            </h1>

            <!-- Typing Animation -->
            <div class="mb-6">
              <div class="h-px w-16 bg-gradient-to-r from-amber-500 to-transparent mb-4"></div>
              <p class="text-xl md:text-2xl text-gray-400 font-light min-h-[2rem]">
                {{ displayText }}<span class="inline-block w-0.5 h-6 bg-amber-500 ml-1 animate-pulse align-middle"></span>
              </p>
            </div>

            <!-- Description -->
            <p v-if="hero.description" class="text-gray-500 leading-relaxed mb-8 text-lg font-light transition-all duration-500"

            >
              {{ hero.description }}
            </p>

            <!-- Stats -->
            <div class="flex gap-8 mb-8">
              <div class="text-left">
                <div class="text-3xl font-light text-amber-500">{{ projectsCount }}+</div>
                <div class="text-xs text-gray-600 uppercase tracking-widest mt-1">Projects Built</div>
              </div>
              <div class="w-px bg-gray-800"></div>
              <div class="text-left">
                <div class="text-3xl font-light text-amber-500">{{ experienceYears }}+</div>
                <div class="text-xs text-gray-600 uppercase tracking-widest mt-1">Years Experience</div>
              </div>
              <div class="w-px bg-gray-800"></div>
              <div class="text-left">
                <div class="text-3xl font-light text-amber-500">15+</div>
                <div class="text-xs text-gray-600 uppercase tracking-widest mt-1">Bugs Resolved</div>
              </div>
            </div>


            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 mb-8">
              <!-- Primary Button -->
              <component
                :is="isInternal(hero.primary_cta_url) ? Link : 'a'"
                :href="hero.primary_cta_url"
                class="group px-8 py-4 bg-gradient-to-r from-amber-600 to-yellow-600 text-black font-medium rounded-sm hover:from-amber-500 hover:to-yellow-500 transition-all duration-300 flex items-center justify-center hover:scale-105 hover:shadow-lg hover:shadow-amber-500/20"
              >
                <Icon :icon="getCtaIcon(hero.primary_cta_url)" class="mr-2 w-5 h-5" />
                {{ hero.primary_cta_text }}
                <Icon icon="mdi:arrow-right" class="ml-2 w-5 h-5 group-hover:translate-x-1 transition-transform" />
              </component>

              <!-- Secondary Button -->
              <component
                :is="isInternal(hero.secondary_cta_url) ? Link : 'a'"
                :href="hero.secondary_cta_url"
                class="group px-8 py-4 border border-amber-600/30 text-amber-500 font-medium rounded-sm hover:bg-amber-600/10 transition-all duration-300 flex items-center justify-center hover:scale-105 hover:border-amber-500"
              >
                <Icon :icon="getCtaIcon(hero.secondary_cta_url)" class="mr-2 w-5 h-5" />
                {{ hero.secondary_cta_text }}
                <Icon icon="mdi:arrow-right" class="ml-2 w-5 h-5 group-hover:translate-x-1 transition-transform" />
              </component>

             
            </div>

            <!-- Social Links -->
            <div v-if="hero.show_social_links && socialLinks.length > 0" class="flex gap-4">
              <a v-for="social in socialLinks"
                :key="social.platform"
                :href="social.url"
                target="_blank"
                rel="noopener noreferrer"
                class="w-10 h-10 border border-gray-800 hover:border-amber-500/50 flex items-center justify-center transition-all duration-300 group rounded-sm"
                :title="social.platform"
              >
                <Icon
                  :icon="social.icon || 'mdi:link'"
                  class="w-5 h-5 text-gray-600 group-hover:text-amber-500 transition-colors"
                />
              </a>
            </div>
          </div>

          <!-- Right: Photo -->
          <div v-if="hero.show_photo && hero.photo" class="order-1 lg:order-2 flex justify-center">
            <div class="relative w-56 h-56 sm:w-72 sm:h-72 lg:w-96 lg:h-96">
              <div class="absolute -inset-4 border border-amber-500/20"></div>
              <div class="absolute -inset-2 border border-gray-800"></div>
              <div class="relative w-full h-full overflow-hidden">
                <img
                  :src="`/storage/${hero.photo}`"
                  :alt="hero.name"
                  class="w-full h-full object-cover object-top grayscale hover:grayscale-0 transition-all duration-700"
                />
              </div>
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
