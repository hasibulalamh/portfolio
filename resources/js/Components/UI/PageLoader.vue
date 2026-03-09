<script setup lang="ts">
import { ref, onMounted } from 'vue'

const isLoading = ref(true)

onMounted(() => {
  setTimeout(() => {
    isLoading.value = false
  }, 800)
})
</script>

<template>
  <Transition name="loader">
    <div
      v-if="isLoading"
      class="fixed inset-0 z-[9999] bg-black flex items-center justify-center"
    >
      <div class="flex flex-col items-center gap-6">
        <div class="text-4xl font-light tracking-widest text-white">
          H<span class="text-amber-500">A</span>
        </div>
        <div class="w-40 h-px bg-gray-800 relative overflow-hidden">
          <div class="absolute inset-0 bg-gradient-to-r from-transparent via-amber-500 to-transparent animate-slide"></div>
        </div>
        <span class="text-xs text-gray-600 tracking-widest uppercase">Loading</span>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.loader-leave-active {
  transition: opacity 0.5s ease;
}
.loader-leave-to {
  opacity: 0;
}
@keyframes slide {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}
.animate-slide {
  animation: slide 1s ease-in-out infinite;
}
</style>
