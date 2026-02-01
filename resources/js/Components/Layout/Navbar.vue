<script setup>
import { ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const isOpen = ref(false)

// Get navbar items from shared data
const { props } = usePage()
const menuItems = props.navbarItems || []
</script>

<template>
    <nav class="fixed top-0 w-full bg-gray-900/90 backdrop-blur-lg border-b border-gray-800 z-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">

                <!-- Logo -->
                <Link href="/" class="text-2xl font-bold group">
                    <span
                        class="bg-gradient-to-r from-purple-500 via-pink-500 to-orange-500 bg-clip-text text-transparent group-hover:from-orange-500 group-hover:via-pink-500 group-hover:to-purple-500 transition-all duration-500">
                        Hasibul Alam
                    </span>
                </Link>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-8">
                    <Link v-for="item in menuItems" :key="item.name" :href="item.href"
                        class="text-gray-300 hover:text-white transition-colors relative group">
                        {{ item.name }}
                        <span
                            class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-purple-500 to-pink-500 group-hover:w-full transition-all duration-300"></span>
                    </Link>

                    <a href="#resume"
                        class="px-6 py-2.5 bg-gradient-to-r from-purple-500 to-pink-500 rounded-lg font-medium hover:shadow-lg hover:shadow-purple-500/50 transition-all duration-300 hover:scale-105">
                        Resume
                    </a>
                </div>

                <!-- Mobile Menu Button -->
                <button @click="isOpen = !isOpen"
                    class="md:hidden text-white p-2 hover:bg-gray-800 rounded-lg transition">
                    <svg v-if="!isOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Mobile Menu -->
            <div v-show="isOpen" class="md:hidden pb-4 space-y-2">
                <Link v-for="item in menuItems" :key="item.name" :href="item.href"
                    class="block py-2 px-4 text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg transition"
                    @click="isOpen = false">
                    {{ item.name }}
                </Link>
                <a href="#resume"
                    class="block py-2 px-4 bg-gradient-to-r from-purple-500 to-pink-500 rounded-lg font-medium text-center hover:shadow-lg transition">
                    Resume
                </a>
            </div>
        </div>
    </nav>
</template>
