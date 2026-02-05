<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

interface Skill {
  id: number
  name: string
  icon: string
  category: string
  proficiency: number
  color: string
}

interface PageProps {
  auth: {
    user: any
  }
  skills?: Skill[]
  [key: string]: any
}

const page = usePage<PageProps>()
const allSkills = computed(() => page.props.skills || [])

const categories = computed(() => {
  const cats = new Set(allSkills.value.map(s => s.category))
  return ['All Skills', ...Array.from(cats)]
})

const selectedCategory = ref('All Skills')

const filteredSkills = computed(() => {
  if (selectedCategory.value === 'All Skills') {
    return allSkills.value
  }
  return allSkills.value.filter(s => s.category === selectedCategory.value)
})
</script>

<template>
  <section id="skills" class="relative py-12 md:py-16 bg-black">
    <!-- Decorative line -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-px bg-gradient-to-r from-transparent via-purple-500 to-transparent"></div>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-6xl">

      <!-- Section Header -->
      <div class="text-center mb-10">
        <h2 class="text-3xl md:text-4xl font-bold mb-2">
          <span class="bg-gradient-to-r from-purple-500 via-pink-500 to-orange-500 bg-clip-text text-transparent">
            My Skills
          </span>
        </h2>
        <p class="text-gray-400 mb-6">Technologies and tools I use to bring ideas to life</p>
        <div class="w-16 h-1 bg-gradient-to-r from-purple-500 to-pink-500 mx-auto rounded-full"></div>
      </div>

      <!-- Category Filters -->
      <div class="flex flex-wrap justify-center gap-3 mb-10">
        <button
          v-for="category in categories"
          :key="category"
          @click="selectedCategory = category"
          :class="[
            'px-5 py-2 rounded-lg text-sm font-medium transition-all duration-300',
            selectedCategory === category
              ? 'bg-gradient-to-r from-purple-500 to-pink-500 text-white shadow-lg shadow-purple-500/50'
              : 'bg-gray-800/50 text-gray-400 hover:text-white hover:bg-gray-800'
          ]"
        >
          {{ category }}
        </button>
      </div>

      <!-- Skills Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
        <div
          v-for="skill in filteredSkills"
          :key="skill.id"
          class="group relative"
        >
          <!-- Card - Hidden by default, visible on hover -->
          <div class="absolute inset-0 bg-gray-800/0 group-hover:bg-gray-800/90 backdrop-blur-sm border border-transparent group-hover:border-gray-700 rounded-xl transition-all duration-300 opacity-0 group-hover:opacity-100 z-10">
            <div class="h-full flex flex-col items-center justify-center p-4">
              <!-- Skill Name -->
              <p class="text-white font-semibold text-sm mb-2 text-center">{{ skill.name }}</p>

              <!-- Progress Bar -->
              <div class="w-full bg-gray-700 rounded-full h-1.5 mb-1">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :style="{
                    width: skill.proficiency + '%',
                    background: `linear-gradient(to right, ${skill.color}, #ec4899)`
                  }"
                ></div>
              </div>

              <!-- Proficiency % -->
              <p class="text-xs text-gray-400">{{ skill.proficiency }}%</p>
            </div>
          </div>

          <!-- Icon - Always visible -->
          <div class="relative bg-gray-900/50 backdrop-blur-sm border border-gray-800 rounded-xl p-6 hover:border-transparent transition-all duration-300">
            <div class="flex flex-col items-center">
              <!-- Icon -->
              <Icon
                :icon="skill.icon"
                class="w-12 h-12 mb-2 transition-transform duration-300 group-hover:scale-110"
                :style="{ color: skill.color }"
              />

              <!-- Skill Name (hidden on hover) -->
              <p class="text-xs text-gray-400 text-center group-hover:opacity-0 transition-opacity">
                {{ skill.name }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="filteredSkills.length === 0" class="text-center py-12">
        <Icon icon="mdi:code-tags" class="w-16 h-16 mx-auto mb-4 text-gray-600" />
        <p class="text-gray-400">No skills found in this category</p>
      </div>
    </div>
  </section>
</template>
