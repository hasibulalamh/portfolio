<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import { ref } from 'vue'

interface Project {
  id: number
  title: string
  slug: string
  description: string
  long_description?: string
  problem_description?: string
  problem_image?: string
  solution_description?: string
  category: string
  technologies: string[]
  featured_image?: string
  gallery_images?: string[]
  live_url?: string
  github_url?: string
  client_name?: string
  is_featured: boolean
  completed_at?: string
}

const props = defineProps<{
  project: Project
  related: Project[]
}>()

const activeImage = ref(props.project.featured_image || null)

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
      <div class="relative h-[55vh] min-h-[420px] overflow-hidden">
        <img
          v-if="activeImage"
          :src="`/storage/${activeImage}`"
          :alt="project.title"
          class="w-full h-full object-cover opacity-40"
        />
        <div v-else class="w-full h-full bg-gradient-to-br from-gray-900 to-black"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/60 to-transparent"></div>

        <div class="absolute inset-0 flex items-end">
          <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-6xl pb-12">
            <Link href="/#projects" class="inline-flex items-center gap-2 text-gray-500 hover:text-amber-500 transition-colors text-sm mb-6">
              <Icon icon="mdi:arrow-left" class="w-4 h-4" />
              Back to Projects
            </Link>

            <div v-if="project.is_featured" class="inline-block px-3 py-1 bg-amber-500/20 border border-amber-500/50 text-amber-500 text-xs uppercase tracking-wider mb-3">
              Featured
            </div>

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
      <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-6xl py-16">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">

          <!-- Left: Main Content -->
          <div class="lg:col-span-2 space-y-12">

            <!-- Overview -->
            <div>
              <h2 class="text-xs uppercase tracking-widest text-amber-500 mb-3">Overview</h2>
              <div class="h-px w-12 bg-amber-500/30 mb-6"></div>
              <p class="text-gray-400 leading-relaxed text-lg">{{ project.description }}</p>
              <div v-if="project.long_description" class="mt-4 text-gray-500 leading-relaxed prose prose-invert max-w-none" v-html="project.long_description"></div>
            </div>

            <!-- Problem & Solution -->
            <div v-if="project.problem_description || project.solution_description" class="space-y-8">
              <h2 class="text-xs uppercase tracking-widest text-amber-500 mb-3">Case Study</h2>
              <div class="h-px w-12 bg-amber-500/30 mb-6"></div>

              <!-- Problem -->
              <div v-if="project.problem_description" class="relative p-8 bg-zinc-950 border-l-4 border-red-500/50 group overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                  <Icon icon="mdi:alert-circle-outline" class="w-24 h-24 text-red-500" />
                </div>
                <div class="relative z-10">
                  <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-red-500/10 border border-red-500/30 flex items-center justify-center">
                      <Icon icon="mdi:alert-circle-outline" class="w-5 h-5 text-red-400" />
                    </div>
                    <h3 class="text-white font-medium uppercase tracking-wider text-sm">The Challenge</h3>
                  </div>
                  <p class="text-gray-400 leading-relaxed text-lg italic">{{ project.problem_description }}</p>
                </div>
                
                <div v-if="project.problem_image" class="mt-6 border border-gray-800 overflow-hidden rounded-sm">
                  <img
                    :src="`/storage/${project.problem_image}`"
                    alt="Problem illustration"
                    class="w-full object-cover opacity-80 hover:opacity-100 transition-opacity"
                  />
                </div>
              </div>

              <!-- Solution -->
              <div v-if="project.solution_description" class="relative p-8 bg-zinc-950 border-l-4 border-green-500/50 group overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                  <Icon icon="mdi:check-circle-outline" class="w-24 h-24 text-green-500" />
                </div>
                <div class="relative z-10">
                  <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-green-500/10 border border-green-500/30 flex items-center justify-center">
                      <Icon icon="mdi:check-circle-outline" class="w-5 h-5 text-green-400" />
                    </div>
                    <h3 class="text-white font-medium uppercase tracking-wider text-sm">The Solution & Execution</h3>
                  </div>
                  <p class="text-gray-300 leading-relaxed text-lg">{{ project.solution_description }}</p>
                </div>
              </div>
            </div>

            <!-- Gallery -->
            <div v-if="project.gallery_images && project.gallery_images.length > 0">
              <h2 class="text-xs uppercase tracking-widest text-amber-500 mb-3">Gallery</h2>
              <div class="h-px w-12 bg-amber-500/30 mb-6"></div>

              <!-- Main Image Preview -->
              <div class="border border-gray-800 overflow-hidden mb-4">
                <img
                  :src="`/storage/${activeImage}`"
                  alt="Project screenshot"
                  class="w-full object-cover"
                />
              </div>

              <!-- Thumbnails -->
              <div class="grid grid-cols-4 gap-2">
                <button
                  v-if="project.featured_image"
                  @click="activeImage = project.featured_image"
                  :class="['overflow-hidden border transition-all', activeImage === project.featured_image ? 'border-amber-500' : 'border-gray-800 hover:border-gray-600']"
                >
                  <img :src="`/storage/${project.featured_image}`" class="w-full aspect-video object-cover" />
                </button>
                <button
                  v-for="img in project.gallery_images"
                  :key="img"
                  @click="activeImage = img"
                  :class="['overflow-hidden border transition-all', activeImage === img ? 'border-amber-500' : 'border-gray-800 hover:border-gray-600']"
                >
                  <img :src="`/storage/${img}`" class="w-full aspect-video object-cover" />
                </button>
              </div>
            </div>

          </div>

          <!-- Right: Info Sidebar -->
          <div class="space-y-6">

            <!-- Project Info -->
            <div class="border border-gray-900 p-6">
              <h3 class="text-xs uppercase tracking-widest text-amber-500 mb-6">Project Info</h3>
              <div class="space-y-4">
                <div>
                  <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Category</p>
                  <p class="text-gray-300">{{ getCategoryLabel(project.category) }}</p>
                </div>
                <div v-if="project.client_name">
                  <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Client</p>
                  <p class="text-gray-300">{{ project.client_name }}</p>
                </div>
                <div v-if="project.completed_at">
                  <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Completed</p>
                  <p class="text-gray-300">{{ new Date(project.completed_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long' }) }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-600 uppercase tracking-wider mb-2">Tech Stack</p>
                  <div class="flex flex-wrap gap-2">
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

              <a  v-if="project.github_url"
                :href="project.github_url"
                target="_blank"
                rel="noopener noreferrer"
                class="w-full flex items-center justify-center gap-2 px-6 py-3 border border-gray-700 text-gray-300 hover:border-amber-500/50 hover:text-white transition-all duration-300"
              >
                <Icon icon="mdi:github" class="w-5 h-5" />
                View Source Code
              </a>
            </div>

            <!-- Share -->
            <div class="border border-gray-900 p-4">
              <p class="text-xs text-gray-600 uppercase tracking-wider mb-3">Share</p>
              <div class="flex gap-3">

                <a  :href="`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent('https://hasibul.dev/projects/' + project.slug)}`"
                  target="_blank"
                  class="flex items-center gap-2 text-sm text-gray-600 hover:text-amber-500 transition-colors"
                >
                  <Icon icon="mdi:linkedin" class="w-5 h-5" />
                </a>

               <a   :href="`https://twitter.com/intent/tweet?url=${encodeURIComponent('https://hasibul.dev/projects/' + project.slug)}&text=${encodeURIComponent(project.title)}`"
                  target="_blank"
                  class="flex items-center gap-2 text-sm text-gray-600 hover:text-amber-500 transition-colors"
                >
                  <Icon icon="mdi:twitter" class="w-5 h-5" />
                </a>
              </div>
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
                <p class="text-xs text-amber-500/70 uppercase tracking-wider mb-1">{{ getCategoryLabel(rel.category) }}</p>
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
