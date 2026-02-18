<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

interface Experience {
  id: number
  type: string
  title: string
  company: string
  company_logo?: string
  location?: string
  start_date: string
  end_date?: string
  is_current: boolean
  description?: string
  responsibilities?: string[]
  technologies?: string[]
  achievements?: string[]
  website_url?: string
  formatted_date_range: string
  duration: string
}

interface PageProps {
  auth: {
    user: any
  }
  experiences?: Experience[]
  [key: string]: any
}

const page = usePage<PageProps>()
const allExperiences = computed(() => page.props.experiences || [])

// Filter by type
const types = ['all', 'work', 'education', 'certification']
const selectedType = ref('all')

const filteredExperiences = computed(() => {
  if (selectedType.value === 'all') {
    return allExperiences.value
  }
  return allExperiences.value.filter(exp => exp.type === selectedType.value)
})

// Type labels
const getTypeLabel = (type: string) => {
  const labels: Record<string, string> = {
    'all': 'All',
    'work': 'Work Experience',
    'education': 'Education',
    'certification': 'Certifications'
  }
  return labels[type] || type
}

// Type icon
const getTypeIcon = (type: string) => {
  const icons: Record<string, string> = {
    'work': 'mdi:briefcase',
    'education': 'mdi:school',
    'certification': 'mdi:certificate'
  }
  return icons[type] || 'mdi:circle'
}
</script>

<template>
  <section id="experience" class="relative py-24 bg-black">
    <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-amber-500/30 to-transparent"></div>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-6xl">

      <!-- Header -->
      <div class="mb-16">
        <p class="text-sm uppercase tracking-widest text-amber-500 mb-4">Journey</p>
        <h2 class="text-4xl md:text-5xl font-light text-white mb-4">
          Experience & <span class="text-gradient-gold">Education</span>
        </h2>
        <p class="text-gray-500 mb-6">My professional journey and academic background</p>
        <div class="h-px w-24 bg-gradient-to-r from-amber-500 to-transparent"></div>
      </div>

      <!-- No Experiences -->
      <div v-if="allExperiences.length === 0" class="text-center py-20">
        <Icon icon="mdi:briefcase-outline" class="w-16 h-16 mx-auto mb-4 text-gray-700" />
        <p class="text-gray-500">No experiences yet. Add experiences in the admin panel.</p>
      </div>

      <div v-else>
        <!-- Type Filters -->
        <div class="flex flex-wrap gap-3 mb-12">
          <button
            v-for="type in types"
            :key="type"
            @click="selectedType = type"
            :class="[
              'px-6 py-2 text-sm uppercase tracking-wider transition-all duration-300',
              selectedType === type
                ? 'bg-amber-500/10 text-amber-500 border border-amber-500/30'
                : 'border border-gray-800 text-gray-600 hover:border-gray-700 hover:text-gray-400'
            ]"
          >
            {{ getTypeLabel(type) }}
          </button>
        </div>

        <!-- Timeline -->
        <div class="relative">
          <!-- Vertical Line -->
          <div class="absolute left-8 top-0 bottom-0 w-px bg-gradient-to-b from-amber-500/50 via-gray-800 to-transparent hidden md:block"></div>

          <!-- Timeline Items -->
          <div class="space-y-12">
            <div
              v-for="(exp, index) in filteredExperiences"
              :key="exp.id"
              class="relative group"
            >
              <!-- Timeline Dot -->
              <div class="absolute left-8 w-4 h-4 bg-black border-2 border-amber-500 rounded-full transform -translate-x-1/2 hidden md:block group-hover:scale-150 transition-transform"></div>

              <!-- Content Card -->
              <div class="md:ml-24">
                <div class="relative border border-gray-900 hover:border-amber-500/30 bg-zinc-950/50 p-6 md:p-8 transition-all duration-500">

                  <!-- Type Badge & Date -->
                  <div class="flex flex-wrap items-center gap-4 mb-4">
                    <span class="px-3 py-1 bg-amber-500/10 border border-amber-500/30 text-amber-500 text-xs uppercase tracking-wider">
                      {{ getTypeLabel(exp.type) }}
                    </span>

                    <div class="flex items-center text-sm text-gray-500">
                      <Icon icon="mdi:calendar-range" class="w-4 h-4 mr-2" />
                      {{ exp.formatted_date_range }}
                    </div>

                    <div class="flex items-center text-sm text-gray-600">
                      <Icon icon="mdi:clock-outline" class="w-4 h-4 mr-2" />
                      {{ exp.duration }}
                    </div>

                    <span v-if="exp.is_current" class="px-3 py-1 bg-green-500/10 border border-green-500/30 text-green-500 text-xs uppercase tracking-wider">
                      Current
                    </span>
                  </div>

                  <!-- Company Logo & Info -->
                  <div class="flex items-start gap-6 mb-6">
                    <!-- Logo -->
                    <div v-if="exp.company_logo" class="flex-shrink-0">
                      <div class="w-16 h-16 border border-gray-800 bg-gray-900 flex items-center justify-center p-2">
                        <img
                          :src="`/storage/${exp.company_logo}`"
                          :alt="exp.company"
                          class="w-full h-full object-contain grayscale group-hover:grayscale-0 transition-all duration-500"
                        />
                      </div>
                    </div>

                    <!-- Info -->
                    <div class="flex-1">
                      <!-- Title -->
                      <h3 class="text-xl md:text-2xl font-light text-white mb-2 group-hover:text-gradient-gold transition-colors">
                        {{ exp.title }}
                      </h3>

                      <!-- Company -->
                      <div class="flex flex-wrap items-center gap-4 text-gray-400 mb-2">
                        <a
                          v-if="exp.website_url"
                          :href="exp.website_url"
                          target="_blank"
                          class="flex items-center hover:text-amber-500 transition-colors"
                        >
                          <Icon :icon="getTypeIcon(exp.type)" class="w-5 h-5 mr-2" />
                          {{ exp.company }}
                          <Icon icon="mdi:open-in-new" class="w-4 h-4 ml-1" />
                        </a>
                        <div v-else class="flex items-center">
                          <Icon :icon="getTypeIcon(exp.type)" class="w-5 h-5 mr-2" />
                          {{ exp.company }}
                        </div>

                        <div v-if="exp.location" class="flex items-center text-sm">
                          <Icon icon="mdi:map-marker" class="w-4 h-4 mr-1" />
                          {{ exp.location }}
                        </div>
                      </div>

                      <!-- Description -->
                      <p v-if="exp.description" class="text-gray-500 leading-relaxed mb-4">
                        {{ exp.description }}
                      </p>
                    </div>
                  </div>

                  <!-- Responsibilities -->
                  <div v-if="exp.responsibilities && exp.responsibilities.length > 0" class="mb-4">
                    <p class="text-sm text-gray-600 uppercase tracking-wider mb-3">Key Responsibilities</p>
                    <ul class="space-y-2">
                      <li
                        v-for="(resp, idx) in exp.responsibilities"
                        :key="idx"
                        class="flex items-start text-sm text-gray-500"
                      >
                        <span class="w-1 h-1 bg-amber-500 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                        {{ resp }}
                      </li>
                    </ul>
                  </div>

                  <!-- Achievements -->
                  <div v-if="exp.achievements && exp.achievements.length > 0" class="mb-4">
                    <p class="text-sm text-gray-600 uppercase tracking-wider mb-3">Achievements</p>
                    <ul class="space-y-2">
                      <li
                        v-for="(achievement, idx) in exp.achievements"
                        :key="idx"
                        class="flex items-start text-sm text-gray-500"
                      >
                        <Icon icon="mdi:trophy" class="w-4 h-4 text-amber-500 mr-2 mt-0.5 flex-shrink-0" />
                        {{ achievement }}
                      </li>
                    </ul>
                  </div>

                  <!-- Technologies -->
                  <div v-if="exp.technologies && exp.technologies.length > 0" class="flex flex-wrap gap-2">
                    <span
                      v-for="tech in exp.technologies"
                      :key="tech"
                      class="px-3 py-1 text-xs border border-gray-800 text-gray-600"
                    >
                      {{ tech }}
                    </span>
                  </div>

                  <!-- Corner Accent -->
                  <div class="absolute top-0 right-0 w-8 h-8 border-t border-r border-amber-500/0 group-hover:border-amber-500 transition-colors"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State for Filtered -->
        <div v-if="filteredExperiences.length === 0" class="text-center py-12">
          <p class="text-gray-500">No {{ getTypeLabel(selectedType).toLowerCase() }} found.</p>
        </div>
      </div>

    </div>
  </section>
</template>
