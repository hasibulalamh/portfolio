<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'

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
  responsibilities?: string[]
  achievements?: string[]
}

const props = defineProps<{
  project: Project
  related: Project[]
}>()

const getCategoryLabel = (cat: string) => {
  const labels: Record<string, string> = {
    'web': 'Web Application',
    'mobile': 'Mobile App',
    'api': 'API/Backend',
    'ecommerce': 'E-Commerce',
    'portfolio': 'Portfolio/Website',
    'other': 'Other'
  }
  return labels[cat] || cat
}
</script>

<template>
  <AppLayout>
    <div class="min-h-screen bg-black text-white">

      <!-- Hero -->
      <div class="relative h-[50vh] min-h-[400px] overflow-hidden">
        <img
          v-if="project.featured_image"
          :src="`/storage/${project.featured_image}`"
          :alt="project.title"
          class="w-full h-full object-cover grayscale opacity-40"
        />
        <div v-else class="w-full h-full bg-gradient-to-br from-gray-900 to-black"></div>

        <!-- Overlay -->
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/60 to-transparent"></div>

        <!-- Content -->
        <div class="absolute inset-0 flex items-end">
          <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-5xl pb-12">

            <!-- Back -->
            <Link href="/#projects" class="inline-flex items-center gap-2 text-gray-500 hover:text-amber-500 transition-colors text-sm mb-6">
              <Icon icon="mdi:arrow-left" class="w-4 h-4" />
              Back to Projects
            </Link>

            <p class="text-xs uppercase tracking-widest text-amber-500 mb-3">
              {{ getCategoryLabel(project.category) }}
            </p>

            <h1 class="text-4xl md:text-5xl font-light text-white mb-4">
              {{ project.title }}
            </h1>

            <div class="flex flex-wrap gap-2">
              <span
                v-for="tech in project.technologies"
                :key="tech"
                class="px-3 py-1 text-xs border border-gray-700 text-gray-400"
              >
                {{ tech }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-5xl py-16">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">

          <!-- Left: Details -->
          <div class="lg:col-span-2 space-y-12">

            <!-- Overview -->
            <div>
              <h2 class="text-xs uppercase tracking-widest text-amber-500 mb-4">Overview</h2>
              <div class="h-px w-12 bg-amber-500/30 mb-6"></div>
              <p class="text-gray-400 leading-relaxed text-lg">
                {{ project.description }}
              </p>
            </div>

            <!-- Responsibilities -->
            <div v-if="project.responsibilities && project.responsibilities.length > 0">
              <h2 class="text-xs uppercase tracking-widest text-amber-500 mb-4">What I Did</h2>
              <div class="h-px w-12 bg-amber-500/30 mb-6"></div>
              <ul class="space-y-3">
                <li
                  v-for="item in project.responsibilities"
                  :key="item"
                  class="flex items-start gap-3 text-gray-400"
                >
                  <Icon icon="mdi:chevron-right" class="w-5 h-5 text-amber-500 mt-0.5 shrink-0" />
                  <span>{{ item }}</span>
                </li>
              </ul>
            </div>

            <!-- Achievements -->
            <div v-if="project.achievements && project.achievements.length > 0">
              <h2 class="text-xs uppercase tracking-widest text-amber-500 mb-4">Key Achievements</h2>
              <div class="h-px w-12 bg-amber-500/30 mb-6"></div>
              <ul class="space-y-3">
                <li
                  v-for="item in project.achievements"
                  :key="item"
                  class="flex items-start gap-3 text-gray-400"
                >
                  <Icon icon="mdi:check-circle-outline" class="w-5 h-5 text-amber-500 mt-0.5 shrink-0" />
                  <span>{{ item }}</span>
                </li>
              </ul>
            </div>

          </div>

          <!-- Right: Info -->
          <div class="space-y-8">

            <!-- Project Info -->
            <div class="border border-gray-900 p-6">
              <h3 class="text-xs uppercase tracking-widest text-amber-500 mb-6">Project Info</h3>

              <div class="space-y-4">
                <div>
                  <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Category</p>
                  <p class="text-gray-300">{{ getCategoryLabel(project.category) }}</p>
                </div>

                <div v-if="project.completed_at">
                  <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Completed</p>
                  <p class="text-gray-300">{{ new Date(project.completed_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long' }) }}</p>
                </div>

                <div>
                  <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Technologies</p>
                  <div class="flex flex-wrap gap-2 mt-2">
                    <span
                      v-for="tech in project.technologies"
                      :key="tech"
                      class="px-2 py-1 text-xs border border-gray-800 text-gray-500"
                    >
                      {{ tech }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Links -->
            <div class="space-y-3">

              <a  v-if="project.live_url"
                :href="project.live_url"
                target="_blank"
                rel="noopener noreferrer"
                class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-amber-600 hover:bg-amber-500 text-black font-medium transition-all duration-300"
              >
                <Icon icon="mdi:open-in-new" class="w-5 h-5" />
                View Live Demo
              </a>


             <a   v-if="project.github_url"
                :href="project.github_url"
                target="_blank"
                rel="noopener noreferrer"
                class="w-full flex items-center justify-center gap-2 px-6 py-3 border border-gray-700 text-gray-300 hover:border-amber-500/50 hover:text-white transition-all duration-300"
              >
                <Icon icon="mdi:github" class="w-5 h-5" />
                View Source Code
              </a>
            </div>

          </div>
        </div>

        <!-- Related Projects -->
        <div v-if="related.length > 0" class="mt-20">
          <div class="h-px bg-gray-900 mb-12"></div>
          <h2 class="text-xs uppercase tracking-widest text-amber-500 mb-8">Related Projects</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <Link
              v-for="rel in related"
              :key="rel.id"
              :href="`/projects/${rel.slug}`"
              class="group block border border-gray-900 hover:border-amber-500/30 transition-all duration-300"
            >
              <div class="aspect-video overflow-hidden">
                <img
                  v-if="rel.featured_image"
                  :src="`/storage/${rel.featured_image}`"
                  :alt="rel.title"
                  class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-500"
                />
                <div v-else class="w-full h-full bg-gray-900 flex items-center justify-center">
                  <Icon icon="mdi:image-outline" class="w-8 h-8 text-gray-700" />
                </div>
              </div>
              <div class="p-4">
                <h3 class="text-sm font-light text-gray-300 group-hover:text-amber-500 transition-colors">
                  {{ rel.title }}
                </h3>
              </div>
            </Link>
          </div>
        </div>

      </div>
    </div>
  </AppLayout>
</template>
