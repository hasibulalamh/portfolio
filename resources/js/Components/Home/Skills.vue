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
  <section id="skills" class="relative py-24 bg-black">
    <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-amber-500/30 to-transparent"></div>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-6xl">

      <!-- Header -->
      <div class="mb-16">
        <p class="text-sm uppercase tracking-widest text-amber-500 mb-4">Expertise</p>
        <h2 class="text-4xl md:text-5xl font-light text-white mb-4">
          Technical <span class="text-gradient-gold">Skills</span>
        </h2>
        <p class="text-gray-500 mb-6">Technologies and tools I use to bring ideas to life</p>
        <div class="h-px w-24 bg-gradient-to-r from-amber-500 to-transparent"></div>
      </div>

      <!-- Debug: Show if skills loaded -->
      <div v-if="allSkills.length === 0" class="text-center py-12">
        <p class="text-gray-500">No skills found. Please add skills in admin panel.</p>
      </div>

      <div v-else>
        <!-- Filters -->
        <div class="flex flex-wrap gap-2 mb-8">
          <button
            v-for="category in categories"
            :key="category"
            @click="selectedCategory = category"
            :class="[
              'px-3 py-1.5 text-xs sm:px-6 sm:py-2 sm:text-sm uppercase tracking-wider transition-all duration-300',
              selectedCategory === category
                ? 'bg-amber-500/10 text-amber-500 border border-amber-500/30'
                : 'border border-gray-800 text-gray-600 hover:border-gray-700 hover:text-gray-400'
            ]"
          >
            {{ category }}
          </button>
        </div>

        <!-- Skills Grid -->
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-6">
          <div
            v-for="skill in filteredSkills"
            :key="skill.id"
            class="group relative aspect-square hover:-translate-y-2 transition-transform duration-300"
          >
            <!-- Card -->
            <div class="relative h-full border border-gray-900 hover:border-amber-500/30 bg-zinc-950/50 transition-all duration-300 flex flex-col items-center justify-center p-4 hover:shadow-lg hover:shadow-amber-500/10 hover:bg-zinc-900">

              <!-- Icon -->
              <Icon
                :icon="skill.icon"
                class="w-12 h-12 mb-3 transition-all duration-300 group-hover:scale-110"
                :style="{ color: skill.color }"
              />

              <!-- Name -->
              <p class="text-xs text-center text-gray-600 group-hover:text-gray-400 transition-colors">
                {{ skill.name }}
              </p>

              <!-- Hover overlay -->
              <div class="absolute inset-0 bg-gradient-to-t from-black/90 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-4">
                <div class="w-full">
                  <div class="h-1 bg-gray-800 rounded-full overflow-hidden">
                    <div
                      class="h-full bg-gradient-to-r from-amber-500 to-yellow-500 transition-all duration-500"
                      :style="{ width: skill.proficiency + '%' }"
                    ></div>
                  </div>
                  <p class="text-xs text-amber-500 mt-1">{{ skill.proficiency }}%</p>
                </div>
              </div>

              <!-- Corner accent -->
              <div class="absolute top-0 right-0 w-2 h-2 border-t border-r border-amber-500/0 group-hover:border-amber-500 transition-colors"></div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>
</template>
