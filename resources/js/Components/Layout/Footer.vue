<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

interface FooterItem {
  name: string
  value: string
  icon?: string
}

interface FooterItems {
  quickLinks: FooterItem[]
  social: FooterItem[]
  contact: FooterItem[]
}

interface PageProps {
  auth: {
    user: any
  }
  footerItems?: FooterItems
  [key: string]: any
}

const page = usePage<PageProps>()
const currentYear = new Date().getFullYear()

const quickLinks = computed(() => page.props.footerItems?.quickLinks || [])
const socialLinks = computed(() => page.props.footerItems?.social || [])
const contactInfo = computed(() => page.props.footerItems?.contact || [])

const scrollToTop = () => {
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<template>
  <footer class="relative bg-gradient-to-b from-gray-900 to-black border-t border-gray-800">
    <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-purple-500 to-transparent"></div>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <div class="py-12 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">

        <!-- Brand Column -->
        <div class="lg:col-span-2">
          <h3 class="text-3xl font-bold mb-4">
            <span class="bg-gradient-to-r from-purple-500 via-pink-500 to-orange-500 bg-clip-text text-transparent">
              Hasibul Alam
            </span>
          </h3>
          <p class="text-gray-400 leading-relaxed mb-6 max-w-md">
            Full-Stack Web Developer specializing in Laravel, Vue.js, and modern web technologies.
          </p>

          <div class="flex flex-wrap gap-2">
            <span class="px-3 py-1 bg-gray-800 rounded-full text-xs text-purple-400 border border-gray-700">Laravel</span>
            <span class="px-3 py-1 bg-gray-800 rounded-full text-xs text-green-400 border border-gray-700">Vue.js</span>
            <span class="px-3 py-1 bg-gray-800 rounded-full text-xs text-blue-400 border border-gray-700">Inertia.js</span>
            <span class="px-3 py-1 bg-gray-800 rounded-full text-xs text-cyan-400 border border-gray-700">Tailwind</span>
          </div>
        </div>

        <!-- Quick Links -->
        <div>
          <h3 class="text-lg font-semibold mb-6 text-white flex items-center">
            <span class="w-1 h-6 bg-gradient-to-b from-purple-500 to-pink-500 rounded-full mr-3"></span>
            Quick Links
          </h3>
          <ul class="space-y-3">
            <li v-for="link in quickLinks" :key="link.name">
              <a :href="link.value" class="text-gray-400 hover:text-white transition-colors inline-flex items-center group">
                <Icon icon="mdi:chevron-right" class="w-4 h-4 mr-2 text-purple-500 opacity-0 group-hover:opacity-100 -ml-6 group-hover:ml-0 transition-all" />
                {{ link.name }}
              </a>
            </li>
          </ul>
        </div>

        <!-- Connect -->
        <div>
          <h3 class="text-lg font-semibold mb-6 text-white flex items-center">
            <span class="w-1 h-6 bg-gradient-to-b from-purple-500 to-pink-500 rounded-full mr-3"></span>
            Connect
          </h3>

          <!-- Social Icons -->
          <div v-if="socialLinks.length > 0" class="flex flex-wrap gap-3 mb-6">
            <a
              v-for="socialLink in socialLinks"
              :key="socialLink.name"
              :href="socialLink.value"
              target="_blank"
              rel="noopener noreferrer"
              class="group relative w-11 h-11 bg-gray-800 rounded-xl flex items-center justify-center hover:bg-gradient-to-br hover:from-purple-500 hover:to-pink-500 transition-all duration-300 border border-gray-700 hover:border-transparent hover:scale-110 hover:shadow-lg hover:shadow-purple-500/50"
              :title="socialLink.name"
            >
              <Icon
                :icon="socialLink.icon || 'mdi:link'"
                class="w-5 h-5 text-gray-400 group-hover:text-white transition-colors relative z-10"
              />
            </a>
          </div>

          <!-- Contact Info -->
          <div v-if="contactInfo.length > 0" class="space-y-3">
            <div v-for="info in contactInfo" :key="info.name">
              <p class="text-xs text-gray-500 mb-1 uppercase tracking-wider">{{ info.name }}</p>

              <a
                v-if="info.name.toLowerCase().includes('email')"
                :href="'mailto:' + info.value"
                class="text-purple-400 hover:text-purple-300 transition-colors flex items-center"
              >
                <Icon icon="mdi:email" class="w-4 h-4 mr-2" />
                <span class="text-sm">{{ info.value }}</span>
              </a>

              <a
                v-else-if="info.name.toLowerCase().includes('phone')"
                :href="'tel:' + info.value"
                class="text-purple-400 hover:text-purple-300 transition-colors flex items-center"
              >
                <Icon icon="mdi:phone" class="w-4 h-4 mr-2" />
                <span class="text-sm">{{ info.value }}</span>
              </a>

              <div v-else class="text-purple-400 flex items-center">
                <Icon icon="mdi:map-marker" class="w-4 h-4 mr-2" />
                <span class="text-sm">{{ info.value }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Divider -->
      <div class="border-t border-gray-800"></div>

      <!-- Bottom Bar -->
      <div class="py-6 flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
        <div class="text-gray-400 text-sm">
          <p>© {{ currentYear }} <span class="text-white font-semibold">Hasibul Alam</span>. All rights reserved.</p>
        </div>

        <div class="text-gray-400 text-sm flex items-center space-x-2">
          <span>Built with</span>
          <span class="text-red-500 animate-pulse">❤️</span>
          <span>using</span>
          <span class="text-purple-400 font-semibold">Laravel</span>
          <span>&</span>
          <span class="text-green-400 font-semibold">Vue.js</span>
        </div>

        <button
          @click="scrollToTop"
          class="group flex items-center space-x-2 text-gray-400 hover:text-white transition-colors"
        >
          <span class="text-sm">Back to top</span>
          <Icon icon="mdi:arrow-up" class="w-4 h-4 group-hover:-translate-y-1 transition-transform" />
        </button>
      </div>
    </div>

    <div class="h-1 bg-gradient-to-r from-purple-500 via-pink-500 to-orange-500"></div>
  </footer>
</template>
