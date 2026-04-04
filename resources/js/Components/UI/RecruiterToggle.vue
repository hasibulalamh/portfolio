<script setup lang="ts">
import { useRecruiterMode } from '@/Composables/useRecruiterMode'
import { Icon } from '@iconify/vue'

const { isRecruiterMode } = useRecruiterMode()
</script>

<template>
  <div class="fixed top-24 right-8 z-50 flex items-center gap-3 bg-black/80 backdrop-blur-md border border-gray-800 p-2 rounded-full shadow-lg transition-all duration-500 hover:border-amber-500/50 group">
    <div 
      class="flex items-center gap-2 px-3 py-1.5 rounded-full transition-all duration-300"
      :class="isRecruiterMode ? 'bg-amber-500 text-black' : 'text-gray-500'"
    >
      <Icon icon="mdi:briefcase-account" class="w-4 h-4" />
      <span class="text-[10px] uppercase tracking-widest font-bold">Recruiter Mode</span>
    </div>
    
    <button 
      @click="isRecruiterMode = !isRecruiterMode"
      class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
      :class="isRecruiterMode ? 'bg-amber-600' : 'bg-gray-800'"
    >
      <span
        aria-hidden="true"
        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
        :class="isRecruiterMode ? 'translate-x-5' : 'translate-x-0'"
      />
    </button>
  </div>
</template>
