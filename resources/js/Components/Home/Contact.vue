<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

interface ContactSettings {
  id: number
  title: string
  subtitle?: string
  description?: string
  email: string
  phone?: string
  address?: string
  availability_status: string
  availability_message?: string
  show_form: boolean
}

interface PageProps {
  auth: {
    user: any
  }
  contactSetting?: ContactSettings[]
  [key: string]: any
}

const page = usePage<PageProps>()
const contact = computed(() => page.props.contactSettings)

// Form handling
const form = useForm({
  name: '',
  email: '',
  subject: '',
  message: '',
})

const submitForm = () => {
  form.post('/contact', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
    },
  })
}

// Availability badge color
const getAvailabilityColor = (status: string) => {
  const colors: Record<string, string> = {
    'available': 'text-green-500 border-green-500/30 bg-green-500/10',
    'busy': 'text-yellow-500 border-yellow-500/30 bg-yellow-500/10',
    'unavailable': 'text-red-500 border-red-500/30 bg-red-500/10'
  }
  return colors[status] || colors.available
}

// Availability icon
const getAvailabilityIcon = (status: string) => {
  const icons: Record<string, string> = {
    'available': 'mdi:check-circle',
    'busy': 'mdi:clock-outline',
    'unavailable': 'mdi:close-circle'
  }
  return icons[status] || icons.available
}
</script>

<template>
  <section v-if="contact" id="contact" class="relative py-24 bg-zinc-950">
    <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-amber-500/30 to-transparent"></div>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-6xl">

      <!-- Header -->
      <div class="mb-16 text-center">
        <p class="text-sm uppercase tracking-widest text-amber-500 mb-4">Contact</p>
        <h2 class="text-4xl md:text-5xl font-light text-white mb-4">
          {{ contact.title }}
        </h2>
        <p v-if="contact.subtitle" class="text-xl text-gray-400 mb-6">
          {{ contact.subtitle }}
        </p>
        <div class="h-px w-24 bg-gradient-to-r from-amber-500 to-transparent mx-auto"></div>
      </div>

      <!-- Description -->
      <p v-if="contact.description" class="text-center text-gray-500 max-w-3xl mx-auto mb-12">
        {{ contact.description }}
      </p>

      <!-- Two Column Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">

        <!-- Left: Contact Info -->
        <div class="lg:col-span-1 space-y-8">

          <!-- Availability Status -->
          <div :class="['p-6 border', getAvailabilityColor(contact.availability_status)]">
            <div class="flex items-center gap-3 mb-3">
              <Icon :icon="getAvailabilityIcon(contact.availability_status)" class="w-6 h-6" />
              <h3 class="text-lg font-light uppercase tracking-wider">
                {{ contact.availability_status === 'available' ? 'Available' : contact.availability_status === 'busy' ? 'Busy' : 'Unavailable' }}
              </h3>
            </div>
            <p v-if="contact.availability_message" class="text-sm text-gray-400">
              {{ contact.availability_message }}
            </p>
          </div>

          <!-- Contact Details -->
          <div class="space-y-6">
            <!-- Email -->
            <a :href="`mailto:${contact.email}`" class="group block">
              <div class="flex items-start gap-4 p-4 border border-gray-900 hover:border-amber-500/30 transition-all">
                <Icon icon="mdi:email-outline" class="w-6 h-6 text-amber-500 flex-shrink-0 mt-1" />
                <div>
                  <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Email</p>
                  <p class="text-gray-300 group-hover:text-amber-500 transition-colors break-all">
                    {{ contact.email }}
                  </p>
                </div>
              </div>
            </a>

            <!-- Phone -->
            <a v-if="contact.phone" :href="`tel:${contact.phone}`" class="group block">
              <div class="flex items-start gap-4 p-4 border border-gray-900 hover:border-amber-500/30 transition-all">
                <Icon icon="mdi:phone-outline" class="w-6 h-6 text-amber-500 flex-shrink-0 mt-1" />
                <div>
                  <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Phone</p>
                  <p class="text-gray-300 group-hover:text-amber-500 transition-colors">
                    {{ contact.phone }}
                  </p>
                </div>
              </div>
            </a>

            <!-- Address -->
            <div v-if="contact.address" class="flex items-start gap-4 p-4 border border-gray-900">
              <Icon icon="mdi:map-marker-outline" class="w-6 h-6 text-amber-500 flex-shrink-0 mt-1" />
              <div>
                <p class="text-xs text-gray-600 uppercase tracking-wider mb-1">Location</p>
                <p class="text-gray-300">{{ contact.address }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Contact Form -->
        <div v-if="contact.show_form" class="lg:col-span-2">
          <form @submit.prevent="submitForm" class="space-y-6">

            <!-- Name & Email -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Name -->
              <div>
                <label class="block text-sm text-gray-400 mb-2 uppercase tracking-wider">
                  Your Name <span class="text-amber-500">*</span>
                </label>
                <input
                  v-model="form.name"
                  type="text"
                  required
                  class="w-full px-4 py-3 bg-black border border-gray-800 text-white focus:border-amber-500 focus:outline-none transition-colors"
                  placeholder="John Doe"
                />
                <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
              </div>

              <!-- Email -->
              <div>
                <label class="block text-sm text-gray-400 mb-2 uppercase tracking-wider">
                  Your Email <span class="text-amber-500">*</span>
                </label>
                <input
                  v-model="form.email"
                  type="email"
                  required
                  class="w-full px-4 py-3 bg-black border border-gray-800 text-white focus:border-amber-500 focus:outline-none transition-colors"
                  placeholder="john@example.com"
                />
                <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
              </div>
            </div>

            <!-- Subject -->
            <div>
              <label class="block text-sm text-gray-400 mb-2 uppercase tracking-wider">
                Subject
              </label>
              <input
                v-model="form.subject"
                type="text"
                class="w-full px-4 py-3 bg-black border border-gray-800 text-white focus:border-amber-500 focus:outline-none transition-colors"
                placeholder="Project Inquiry"
              />
              <p v-if="form.errors.subject" class="text-red-500 text-xs mt-1">{{ form.errors.subject }}</p>
            </div>

            <!-- Message -->
            <div>
              <label class="block text-sm text-gray-400 mb-2 uppercase tracking-wider">
                Message <span class="text-amber-500">*</span>
              </label>
              <textarea
                v-model="form.message"
                required
                rows="6"
                class="w-full px-4 py-3 bg-black border border-gray-800 text-white focus:border-amber-500 focus:outline-none transition-colors resize-none"
                placeholder="Tell me about your project..."
              ></textarea>
              <p v-if="form.errors.message" class="text-red-500 text-xs mt-1">{{ form.errors.message }}</p>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center gap-4">
              <button
                type="submit"
                :disabled="form.processing"
                class="group px-8 py-4 bg-gradient-to-r from-amber-600 to-yellow-600 text-black font-medium uppercase tracking-wider hover:from-amber-500 hover:to-yellow-500 transition-all duration-300 flex items-center disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="!form.processing">Send Message</span>
                <span v-else>Sending...</span>
                <Icon
                  icon="mdi:arrow-right"
                  class="ml-2 w-5 h-5 group-hover:translate-x-1 transition-transform"
                  v-if="!form.processing"
                />
              </button>

              <p v-if="form.recentlySuccessful" class="text-green-500 text-sm">
                ✓ Message sent successfully!
              </p>
            </div>
          </form>
        </div>
      </div>

    </div>
  </section>
</template>
