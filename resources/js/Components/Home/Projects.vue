<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { Link } from '@inertiajs/vue3'
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
  auth: { user: any }
  projects?: Project[]
  [key: string]: any
}

const page = usePage<PageProps>()
const allProjects = computed(() => page.props.projects || [])

// Filter mode: 'category' or 'tech'
const filterMode = ref<'category' | 'tech'>('category')
const selectedCategory = ref('all')
const selectedTech = ref('all')

// Categories
const categories = computed(() => {
  const cats = new Set(allProjects.value.map(p => p.category))
  return ['all', ...Array.from(cats)]
})

// Technologies — unique from all projects
const technologies = computed(() => {
  const techs = new Set<string>()
  allProjects.value.forEach(p => {
    if (p.technologies) p.technologies.forEach(t => techs.add(t))
  })
  return ['all', ...Array.from(techs)]
})

// Filtered projects
const filteredProjects = computed(() => {
  if (filterMode.value === 'category') {
    if (selectedCategory.value === 'all') return allProjects.value
    return allProjects.value.filter(p => p.category === selectedCategory.value)
  } else {
    if (selectedTech.value === 'all') return allProjects.value
    return allProjects.value.filter(p =>
      p.technologies && p.technologies.includes(selectedTech.value)
    )
  }
})

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

function setCategory(cat: string) {
  filterMode.value = 'category'
  selectedCategory.value = cat
}

function setTech(tech: string) {
  filterMode.value = 'tech'
  selectedTech.value = tech
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

      <div v-if="allProjects.length === 0" class="text-center py-20">
        <Icon icon="mdi:folder-open-outline" class="w-16 h-16 mx-auto mb-4 text-gray-700" />
        <p class="text-gray-500">No projects yet. Add projects in the admin panel.</p>
      </div>

      <div v-else>

        <!-- Filter Toggle -->
        <div class="flex items-center gap-4 mb-6">
          <button
            @click="filterMode = 'category'; selectedCategory = 'all'"
            :class="filterMode === 'category'
              ? 'text-amber-500 border-b border-amber-500'
              : 'text-gray-600 hover:text-gray-400'"
            class="text-xs uppercase tracking-widest pb-1 transition-all duration-300"
          >
            By Category
          </button>
          <span class="text-gray-800">|</span>
          <button
            @click="filterMode = 'tech'; selectedTech = 'all'"
            :class="filterMode === 'tech'
              ? 'text-amber-500 border-b border-amber-500'
              : 'text-gray-600 hover:text-gray-400'"
            class="text-xs uppercase tracking-widest pb-1 transition-all duration-300"
          >
            By Technology
          </button>
        </div>

        <!-- Category Filters -->
        <div v-if="filterMode === 'category'" class="flex flex-wrap gap-3 mb-12">
          <button
            v-for="category in categories"
            :key="category"
            @click="setCategory(category)"
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

        <!-- Technology Filters -->
        <div v-if="filterMode === 'tech'" class="flex flex-wrap gap-3 mb-12">
          <button
            v-for="tech in technologies"
            :key="tech"
            @click="setTech(tech)"
            :class="[
              'px-4 py-2 text-sm uppercase tracking-wider transition-all duration-300',
              selectedTech === tech
                ? 'bg-amber-500/10 text-amber-500 border border-amber-500/30'
                : 'border border-gray-800 text-gray-600 hover:border-gray-700 hover:text-gray-400'
            ]"
          >
            {{ tech === 'all' ? 'All Technologies' : tech }}
          </button>
        </div>

        <!-- Projects Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div
            v-for="project in filteredProjects"
            :key="project.id"
            class="group relative hover:-translate-y-2 transition-transform duration-300"
          >
            <div class="relative overflow-hidden border border-gray-900 hover:border-amber-500/30 transition-all duration-500 bg-zinc-950 hover:shadow-xl hover:shadow-amber-500/10">

              <div v-if="project.is_featured" class="absolute top-4 right-4 z-10">
                <div class="px-3 py-1 bg-amber-500/20 border border-amber-500/50 text-amber-500 text-xs uppercase tracking-wider">
                  Featured
                </div>
              </div>

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
                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/50 to-transparent opacity-60 group-hover:opacity-80 transition-opacity"></div>
              </div>

              <div class="p-6">
                <p class="text-xs uppercase tracking-widest text-amber-500 mb-3">
                  {{ getCategoryLabel(project.category) }}
                </p>

           <Link :href="`/projects/${project.slug}`" class="text-xl font-light text-white mb-3 group-hover:text-amber-500 transition-colors block">
                {{ project.title }}
                </Link>

                <p class="text-sm text-gray-500 leading-relaxed mb-4 line-clamp-3">
                  {{ project.description }}
                </p>

                <!-- Technologies — clickable to filter -->
                <div v-if="project.technologies && project.technologies.length > 0" class="flex flex-wrap gap-2 mb-4">
                  <button
                    v-for="tech in project.technologies.slice(0, 4)"
                    :key="tech"
                    @click="setTech(tech)"
                    class="px-2 py-1 text-xs border border-gray-800 text-gray-600 hover:border-amber-500/50 hover:text-amber-500 transition-all duration-200"
                  >
                    {{ tech }}
                  </button>
                  <span v-if="project.technologies.length > 4" class="px-2 py-1 text-xs text-gray-600">
                    +{{ project.technologies.length - 4 }}
                  </span>
                </div>

                <div class="flex gap-3 pt-4 border-t border-gray-900">
                  <a v-if="project.live_url"
                    :href="project.live_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex items-center gap-2 text-sm text-gray-500 hover:text-amber-500 transition-colors"
                  >
                    <Icon icon="mdi:open-in-new" class="w-4 h-4" />
                    <span>Live Demo</span>
                  </a>
                  <a v-if="project.github_url"
                    :href="project.github_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex items-center gap-2 text-sm text-gray-500 hover:text-amber-500 transition-colors"
                  >
                    <Icon icon="mdi:github" class="w-4 h-4" />
                    <span>Source</span>
                  </a>
                </div>
              </div>

              <div class="absolute bottom-0 left-0 w-8 h-8 border-b border-l border-amber-500/0 group-hover:border-amber-500 transition-colors"></div>
            </div>
          </div>
        </div>

        <div v-if="filteredProjects.length === 0" class="text-center py-12">
          <p class="text-gray-500">No projects found.</p>
        </div>

      </div>
    </div>
  </section>
</template>
