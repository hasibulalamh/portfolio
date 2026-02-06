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
  auth: { user: any }
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
  <footer class="relative bg-zinc-950 border-t border-gray-900">

    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <div class="py-16">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12">

          <!-- Brand -->
          <div class="lg:col-span-2">
            <h3 class="text-3xl font-light mb-4">
              <span class="text-gradient-elegant">Hasibul Alam</span>
            </h3>
            <p class="text-gray-600 leading-relaxed mb-6 max-w-md font-light">
              Full-Stack Web Developer specializing in Laravel, Vue.js, and modern web technologies.
            </p>

            <!-- Tech Stack -->
            <div class="flex flex-wrap gap-2">
              <span class="px-3 py-1 border border-gray-900 text-xs text-gray-700 uppercase tracking-wider">Laravel</span>
              <span class="px-3 py-1 border border-gray-900 text-xs text-gray-700 uppercase tracking-wider">Vue.js</span>
              <span class="px-3 py-1 border border-gray-900 text-xs text-gray-700 uppercase tracking-wider">Inertia</span>
              <span class="px-3 py-1 border border-gray-900 text-xs text-gray-700 uppercase tracking-wider">Tailwind</span>
            </div>
          </div>

          <!-- Quick Links -->
          <div>
            <p class="text-sm uppercase tracking-widest text-amber-500 mb-6">Navigate</p>
            <ul class="space-y-3">
              <li v-for="link in quickLinks" :key="link.name">
                <a
                  :href="link.value"
                  class="text-gray-600 hover:text-amber-500 transition-colors text-sm flex items-center group"
                >
                  <span class="w-0 group-hover:w-4 h-px bg-amber-500 mr-0 group-hover:mr-2 transition-all"></span>
                  {{ link.name }}
                </a>
              </li>
            </ul>
          </div>

          <!-- Connect -->
          <div>
            <p class="text-sm uppercase tracking-widest text-amber-500 mb-6">Connect</p>

            <!-- Social Links -->
            <div v-if="socialLinks.length > 0" class="flex gap-3 mb-6">
              <a
                v-for="social in socialLinks"
                :key="social.name"
                :href="social.value"
                target="_blank"
                rel="noopener noreferrer"
                class="w-10 h-10 border border-gray-900 hover:border-amber-500/50 flex items-center justify-center transition-all group"
                :title="social.name"
              >
                <Icon
                  :icon="social.icon || 'mdi:link'"
                  class="w-5 h-5 text-gray-700 group-hover:text-amber-500 transition-colors"
                />
              </a>
            </div>

            <!-- Contact Info -->
            <div v-if="contactInfo.length > 0" class="space-y-3">
              <div v-for="info in contactInfo" :key="info.name">
                <p class="text-xs text-gray-700 uppercase tracking-wider mb-1">{{ info.name }}</p>

                <a
                  v-if="info.name.toLowerCase().includes('email')"
                  :href="'mailto:' + info.value"
                  class="text-sm text-gray-600 hover:text-amber-500 transition-colors flex items-center"
                >
                  <Icon icon="mdi:email-outline" class="w-4 h-4 mr-2" />
                  {{ info.value }}
                </a>


                <a  v-else-if="info.name.toLowerCase().includes('phone')"
                  :href="'tel:' + info.value"
                  class="text-sm text-gray-600 hover:text-amber-500 transition-colors flex items-center"
                >
                  <Icon icon="mdi:phone-outline" class="w-4 h-4 mr-2" />
                  {{ info.value }}
                </a>

                <div v-else class="text-sm text-gray-600 flex items-center">
                  <Icon icon="mdi:map-marker-outline" class="w-4 h-4 mr-2" />
                  {{ info.value }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Bar -->
      <div class="border-t border-gray-900 py-8">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">

          <p class="text-sm text-gray-700">
            © {{ currentYear }} <span class="text-white">Hasibul Alam</span>. All rights reserved.
          </p>

          <div class="flex items-center gap-2 text-sm text-gray-700">
            <span>Crafted with</span>
            <Icon icon="mdi:heart" class="w-4 h-4 text-amber-500" />
            <span>using</span>
            <span class="text-amber-500">Laravel</span>
            <span>&</span>
            <span class="text-amber-500">Vue.js</span>
          </div>

          <button
            @click="scrollToTop"
            class="group flex items-center gap-2 text-sm text-gray-700 hover:text-amber-500 transition-colors"
          >
            <span class="uppercase tracking-wider">Back to top</span>
            <Icon icon="mdi:arrow-up" class="w-4 h-4 group-hover:-translate-y-1 transition-transform" />
          </button>
        </div>
      </div>
    </div>

    <!-- Bottom accent -->
    <div class="h-px bg-gradient-to-r from-transparent via-amber-500/30 to-transparent"></div>
  </footer>
</template>
