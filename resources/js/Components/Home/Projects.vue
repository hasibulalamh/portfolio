<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

interface Project {
  id: number
  title: string
  slug: string
  description: string
  category: string
  technologies: string[]
  featured_image?: string
  live_url?: string
  github_url?: string
  is_featured: boolean
  completed_at?: string
}

interface PageProps {
  auth: {
    user: any
  }
  projects?: Project[]
  [key: string]: any
}

const page = usePage<PageProps>()
const allProjects = computed(() => page.props.projects || [])

// Categories
const categories = computed(() => {
  const cats = new Set(allProjects.value.map(p => p.category))
  return ['all', ...Array.from(cats)]
})

const selectedCategory = ref('all')

// Filtered projects
const filteredProjects = computed(() => {
  if (selectedCategory.value === 'all') {
    return allProjects.value
  }
  return allProjects.value.filter(p => p.category === selectedCategory.value)
})

// Featured projects
const featuredProjects = computed(() =>
  allProjects.value.filter(p => p.is_featured).slice(0, 3)
)

// Category labels
const getCategoryLabel = (cat: string) => {
  const labels: Record<string, string> = {
    'all': 'All Projects',
    'web': 'Web Applications',
    'mobile': 'Mobile Apps',
    'api': 'API/Backend',
    'ecommerce': 'E-Commerce',
    'portfolio': 'Portfolio/Website',
    'other': 'Other'
  }
  return labels[cat] || cat
}
</script>

<template>
  <section id="projects" class="relative py-24 bg-zinc-950">
    <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-amber-500/30 to-transparent"></div>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-7xl">

      <!-- Header -->
      <div class="mb-16">
        <p class="text-sm uppercase tracking-widest text-amber-500 mb-4">Portfolio</p>
        <h2 class="text-4xl md:text-5xl font-light text-white mb-4">
          Selected <span class="text-gradient-gold">Projects</span>
        </h2>
        <p class="text-gray-500 mb-6">Showcasing my work across different technologies and domains</p>
        <div class="h-px w-24 bg-gradient-to-r from-amber-500 to-transparent"></div>
      </div>

      <!-- No Projects Message -->
      <div v-if="allProjects.length === 0" class="text-center py-20">
        <Icon icon="mdi:folder-open-outline" class="w-16 h-16 mx-auto mb-4 text-gray-700" />
        <p class="text-gray-500">No projects yet. Add projects in the admin panel.</p>
      </div>

      <div v-else>
        <!-- Category Filters -->
        <div class="flex flex-wrap gap-3 mb-12">
          <button
            v-for="category in categories"
            :key="category"
            @click="selectedCategory = category"
            :class="[
              'px-6 py-2 text-sm uppercase tracking-wider transition-all duration-300',
              selectedCategory === category
                ? 'bg-amber-500/10 text-amber-500 border border-amber-500/30'
                : 'border border-gray-800 text-gray-600 hover:border-gray-700 hover:text-gray-400'
            ]"
          >
            {{ getCategoryLabel(category) }}
          </button>
        </div>

        <!-- Projects Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div
            v-for="project in filteredProjects"
            :key="project.id"
            class="group relative"
          >
            <!-- Project Card -->
            <div class="relative overflow-hidden border border-gray-900 hover:border-amber-500/30 transition-all duration-500 bg-black">

              <!-- Featured Badge -->
              <div v-if="project.is_featured" class="absolute top-4 right-4 z-10">
                <div class="px-3 py-1 bg-amber-500/20 border border-amber-500/50 text-amber-500 text-xs uppercase tracking-wider">
                  Featured
                </div>
              </div>

              <!-- Image -->
              <div class="relative aspect-video overflow-hidden">
                <img
                  v-if="project.featured_image"
                  :src="`/storage/${project.featured_image}`"
                  :alt="project.title"
                  class="w-full h-full object-cover grayscale group-hover:grayscale-0 group-hover:scale-110 transition-all duration-700"
                />
                <div v-else class="w-full h-full bg-gradient-to-br from-gray-900 to-gray-800 flex items-center justify-center">
                  <Icon icon="mdi:image-outline" class="w-16 h-16 text-gray-700" />
                </div>

                <!-- Overlay -->
                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/50 to-transparent opacity-60 group-hover:opacity-80 transition-opacity"></div>
              </div>

              <!-- Content -->
              <div class="p-6">
                <!-- Category -->
                <p class="text-xs uppercase tracking-widest text-amber-500 mb-3">
                  {{ getCategoryLabel(project.category) }}
                </p>

                <!-- Title -->
                <h3 class="text-xl font-light text-white mb-3 group-hover:text-gradient-gold transition-colors">
                  {{ project.title }}
                </h3>

                <!-- Description -->
                <p class="text-sm text-gray-500 leading-relaxed mb-4 line-clamp-3">
                  {{ project.description }}
                </p>

                <!-- Technologies -->
                <div v-if="project.technologies && project.technologies.length > 0" class="flex flex-wrap gap-2 mb-4">
                  <span
                    v-for="tech in project.technologies.slice(0, 4)"
                    :key="tech"
                    class="px-2 py-1 text-xs border border-gray-800 text-gray-600"
                  >
                    {{ tech }}
                  </span>
                  <span v-if="project.technologies.length > 4" class="px-2 py-1 text-xs text-gray-600">
                    +{{ project.technologies.length - 4 }}
                  </span>
                </div>

                <!-- Links -->
                <div class="flex gap-3 pt-4 border-t border-gray-900">

                   <a v-if="project.live_url"
                    :href="project.live_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="group/link flex items-center gap-2 text-sm text-gray-500 hover:text-amber-500 transition-colors"
                  >
                    <Icon icon="mdi:open-in-new" class="w-4 h-4" />
                    <span>Live Demo</span>
                  </a>


                  <a  v-if="project.github_url"
                    :href="project.github_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="group/link flex items-center gap-2 text-sm text-gray-500 hover:text-amber-500 transition-colors"
                  >
                    <Icon icon="mdi:github" class="w-4 h-4" />
                    <span>Source</span>
                  </a>
                </div>
              </div>

              <!-- Corner Accent -->
              <div class="absolute bottom-0 left-0 w-8 h-8 border-b border-l border-amber-500/0 group-hover:border-amber-500 transition-colors"></div>
            </div>
          </div>
        </div>

        <!-- Empty State for Filtered -->
        <div v-if="filteredProjects.length === 0" class="text-center py-12">
          <p class="text-gray-500">No projects found in this category.</p>
        </div>
      </div>

    </div>
  </section>
</template>
