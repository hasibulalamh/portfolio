<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'

const page = usePage()
const isOpen = ref(false)
const menuItems = page.props.navbarItems || []
const resumeUrl = page.props.heroSettings?.resume_url || null

const isHomePage = computed(() => window.location.pathname === '/')

function handleNavClick(e, href) {
    if (!href.startsWith('#')) return
    e.preventDefault()
    if (isHomePage.value) {
        const target = document.querySelector(href)
        if (target) {
            const offset = 80
            const targetPosition = target.getBoundingClientRect().top + window.scrollY - offset
            window.scrollTo({ top: targetPosition, behavior: 'smooth' })
        }
    } else {
        router.visit('/' + href, {
            onSuccess: () => {
                setTimeout(() => {
                    const target = document.querySelector(href)
                    if (target) {
                        const offset = 80
                        const targetPosition = target.getBoundingClientRect().top + window.scrollY - offset
                        window.scrollTo({ top: targetPosition, behavior: 'smooth' })
                    }
                }, 500)
            }
        })
    }
    isOpen.value = false
}
</script>

<template>
  <nav class="fixed top-0 w-full bg-black/90 backdrop-blur-xl border-b border-gray-900 z-50">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-20">
        <Link href="/" class="relative group">
          <span class="text-2xl font-light tracking-tight text-gradient-elegant">
            {{ page.props.heroSettings?.name?.split(' ')[0] || 'Portfolio' }}
          </span>
          <div class="absolute -bottom-1 left-0 w-0 h-px bg-gradient-to-r from-amber-500 to-transparent group-hover:w-full transition-all duration-500"></div>
        </Link>
        <div class="hidden md:flex items-center space-x-12">

         <a   v-for="item in menuItems"
            :key="item.name"
            :href="item.href"
            @click="handleNavClick($event, item.href)"
            class="text-sm uppercase tracking-widest text-gray-500 hover:text-amber-500 transition-colors duration-300 relative group cursor-pointer"
          >
            {{ item.name }}
            <span class="absolute -bottom-2 left-0 w-0 h-px bg-amber-500 group-hover:w-full transition-all duration-300"></span>
          </a>

          <a  :href="resumeUrl ? `/storage/${resumeUrl}` : '#resume'"
            :target="resumeUrl ? '_blank' : '_self'"
            :download="resumeUrl ? true : false"
            class="px-6 py-2 border border-amber-500/30 text-amber-500 hover:bg-amber-500/10 text-sm uppercase tracking-widest transition-all duration-300"
          >
            Resume
          </a>
        </div>
        <button @click="isOpen = !isOpen" class="md:hidden text-gray-500 hover:text-white transition-colors">
          <div class="w-6 h-5 flex flex-col justify-between">
            <span :class="['block h-0.5 bg-current transition-all', isOpen ? 'rotate-45 translate-y-2' : '']"></span>
            <span :class="['block h-0.5 bg-current transition-all', isOpen ? 'opacity-0' : '']"></span>
            <span :class="['block h-0.5 bg-current transition-all', isOpen ? '-rotate-45 -translate-y-2' : '']"></span>
          </div>
        </button>
      </div>
      <div v-show="isOpen" class="md:hidden py-6 border-t border-gray-900">

        <a  v-for="item in menuItems"
          :key="item.name"
          :href="item.href"
          @click="handleNavClick($event, item.href)"
          class="block py-3 text-sm uppercase tracking-widest text-gray-500 hover:text-amber-500 transition-colors cursor-pointer"
        >
          {{ item.name }}
        </a>

        <a  :href="resumeUrl ? `/storage/${resumeUrl}` : '#resume'"
          :target="resumeUrl ? '_blank' : '_self'"
          :download="resumeUrl ? true : false"
          class="block mt-4 py-3 px-6 text-center border border-amber-500/30 text-amber-500 text-sm uppercase tracking-widest"
          @click="isOpen = false"
        >
          Resume
        </a>
      </div>
    </div>
  </nav>
</template>
