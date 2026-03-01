<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const testimonials = computed(() => page.props.testimonials as any[])

const getStars = (rating: number) => Array.from({ length: 5 }, (_, i) => i < rating)

const getInitials = (name: string) => {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

// Masonry columns — split into 3 columns
const columns = computed(() => {
  const cols: any[][] = [[], [], []]
  testimonials.value?.forEach((t, i) => {
    cols[i % 3].push(t)
  })
  return cols
})
</script>

<template>
  <section v-if="testimonials?.length" id="testimonials" class="relative py-24 bg-black">
    <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-amber-500/30 to-transparent"></div>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-6xl">

      <!-- Header -->
      <div class="mb-16 text-center">
        <p class="text-sm uppercase tracking-widest text-amber-500 mb-4">Testimonials</p>
        <h2 class="text-4xl md:text-5xl font-light text-white mb-4">
          What Clients Say
        </h2>
        <div class="h-px w-24 bg-gradient-to-r from-amber-500 to-transparent mx-auto"></div>
      </div>

      <!-- Masonry Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-start">
        <div v-for="(column, colIndex) in columns" :key="colIndex" class="flex flex-col gap-6">
          <div
            v-for="testimonial in column"
            :key="testimonial.id"
            class="group border border-gray-900 hover:border-amber-500/30 bg-zinc-950 p-6 transition-all duration-300 hover:-translate-y-1"
          >
            <!-- Star Rating -->
            <div class="flex gap-1 mb-4">
              <template v-for="(filled, i) in getStars(testimonial.rating)" :key="i">
                <Icon
                  :icon="filled ? 'mdi:star' : 'mdi:star-outline'"
                  :class="filled ? 'text-amber-500' : 'text-gray-700'"
                  class="w-4 h-4"
                />
              </template>
            </div>

            <!-- Content -->
            <p class="text-gray-400 text-sm leading-relaxed mb-6 italic">
              "{{ testimonial.content }}"
            </p>

            <!-- Project Type Badge -->
            <div v-if="testimonial.project_type" class="mb-4">
              <span class="text-xs text-amber-500 border border-amber-500/30 bg-amber-500/5 px-3 py-1 uppercase tracking-wider">
                {{ testimonial.project_type }}
              </span>
            </div>

            <!-- Client Info -->
            <div class="flex items-center gap-3 pt-4 border-t border-gray-900">
              <!-- Photo or Initials -->
              <div class="flex-shrink-0">
                <img
                  v-if="testimonial.client_photo"
                  :src="`/storage/${testimonial.client_photo}`"
                  :alt="testimonial.client_name"
                  class="w-10 h-10 rounded-full object-cover border border-gray-800"
                />
                <div
                  v-else
                  class="w-10 h-10 rounded-full bg-amber-500/20 border border-amber-500/30 flex items-center justify-center"
                >
                  <span class="text-amber-500 text-xs font-bold">
                    {{ getInitials(testimonial.client_name) }}
                  </span>
                </div>
              </div>

              <!-- Name & Role -->
              <div class="flex-1 min-w-0">
                <p class="text-white text-sm font-medium truncate">{{ testimonial.client_name }}</p>
                <p class="text-gray-600 text-xs truncate">
                  <span v-if="testimonial.client_role">{{ testimonial.client_role }}</span>
                  <span v-if="testimonial.client_role && testimonial.client_company"> · </span>
                  <span v-if="testimonial.client_company">{{ testimonial.client_company }}</span>
                </p>
              </div>

              <!-- Date -->
              <div v-if="testimonial.date" class="flex-shrink-0">
                <p class="text-gray-700 text-xs">
                  {{ new Date(testimonial.date).toLocaleDateString('en-US', { month: 'short', year: 'numeric' }) }}
                </p>
              </div>
            </div>

          </div>
        </div>
      </div>

    </div>
  </section>
</template>
