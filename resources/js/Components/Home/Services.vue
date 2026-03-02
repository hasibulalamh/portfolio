<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const services = computed(() => page.props.services as any[])

const scopeColor = (scope: string) => ({
  'Small':  'text-green-500 border-green-500/30 bg-green-500/5',
  'Medium': 'text-amber-500 border-amber-500/30 bg-amber-500/5',
  'Large':  'text-red-400 border-red-400/30 bg-red-400/5',
}[scope] || 'text-gray-400 border-gray-700')

const duration = (min: string, max: string) => {
  if (min && max) return `${min} – ${max}`
  return min || max || null
}
</script>

<template>
  <section v-if="services?.length" id="services" class="relative py-24 bg-black">
    <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-amber-500/30 to-transparent"></div>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-6xl">

      <!-- Header -->
      <div class="mb-16">
        <p class="text-sm uppercase tracking-widest text-amber-500 mb-4">What I Offer</p>
        <h2 class="text-4xl md:text-5xl font-light text-white mb-4">
          My <span class="text-gradient-gold">Services</span>
        </h2>
        <div class="h-px w-24 bg-gradient-to-r from-amber-500 to-transparent"></div>
      </div>

      <!-- Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
                v-for="(service, index) in services"
                :key="service.id"
                :class="[
                'group relative flex flex-col border bg-zinc-950 p-8 transition-all duration-300 hover:-translate-y-1',
                service.is_featured ? 'border-amber-500/40' : 'border-gray-900 hover:border-amber-500/20',
                // Last item alone → center it
                index === services.length - 1 && services.length % 3 !== 0
                    ? 'lg:col-start-2'
                    : ''
                ]"
  >
                <!-- Featured badge -->
                <div v-if="service.is_featured"
                    class="absolute top-0 right-0 text-xs text-black bg-amber-500 px-3 py-1 uppercase tracking-wider font-medium">
                    Popular
                </div>

          <!-- Icon -->
          <div class="mb-6">
            <div :class="[
              'w-14 h-14 flex items-center justify-center border transition-colors duration-300',
              service.is_featured
                ? 'border-amber-500/40 bg-amber-500/5'
                : 'border-gray-800 group-hover:border-amber-500/30'
            ]">
              <Icon :icon="service.icon || 'mdi:code-braces'" class="w-7 h-7 text-amber-500" />
            </div>
          </div>

          <!-- Title -->
          <h3 class="text-xl font-light text-white mb-3 tracking-wide">
            {{ service.title }}
          </h3>

          <!-- Short Description -->
          <p class="text-gray-300 text-sm leading-relaxed mb-6 flex-1">
            {{ service.short_description }}
          </p>

          <!-- Technologies -->
          <div v-if="service.technologies?.length" class="flex flex-wrap gap-2 mb-6">
            <span
              v-for="tech in service.technologies.slice(0, 4)"
              :key="tech"
              class="text-xs text-gray-300 border border-gray-800 px-2 py-1"
            >
              {{ tech }}
            </span>
            <span v-if="service.technologies.length > 4" class="text-xs text-gray-600 px-2 py-1">
              +{{ service.technologies.length - 4 }} more
            </span>
          </div>

                <!-- Bottom -->
                <div class="pt-5 border-t border-gray-900 space-y-3">

                <!-- Scope -->
                <div v-if="service.scope_type">
                    <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-gray-600 uppercase tracking-wider">Scope</span>
                    <span :class="['text-xs border px-2 py-1 uppercase tracking-wider', scopeColor(service.scope_type)]">
                        {{ service.scope_type }}
                    </span>
                    </div>
                    <p v-if="service.scope_description" class="text-xs text-gray-400 leading-relaxed">
                    {{ service.scope_description }}
                    </p>
                </div>

                <!-- Pricing -->
                <div v-if="service.pricing_note">
                    <span class="text-xs text-gray-400 uppercase tracking-wider block mb-1">Investment</span>
                    <span class="text-amber-500 text-sm font-medium">{{ service.pricing_note }}</span>
                </div>

                <!-- Duration -->
                <div v-if="duration(service.min_duration, service.max_duration)" class="flex items-center justify-between">
                    <span class="text-xs text-gray-400 uppercase tracking-wider">Timeline</span>
                    <span class="text-gray-400 text-sm">{{ duration(service.min_duration, service.max_duration) }}</span>
                </div>

                </div>

        </div>
      </div>

      <!-- CTA -->
      <div class="text-center mt-16">
        <p class="text-gray-400 mb-6 text-sm uppercase tracking-widest">Have a project in mind?</p>
        <a href="#contact"
          class="inline-block px-10 py-4 bg-gradient-to-r from-amber-600 to-yellow-600 text-black font-medium uppercase tracking-widest text-sm hover:from-amber-500 hover:to-yellow-500 transition-all duration-300">
          Let's Discuss →
        </a>
      </div>

    </div>
  </section>
</template>
