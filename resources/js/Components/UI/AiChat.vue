<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { Icon } from '@iconify/vue'
import axios from 'axios'

interface Message {
  role: 'user' | 'ai'
  text: string
}

const isOpen = ref(false)
const isLoading = ref(false)
const input = ref('')
const messages = ref<Message[]>([
  {
    role: 'ai',
    text: "Hi! I'm Hasibul's AI assistant. Ask me anything about his skills, projects, or experience! 👋"
  }
])
const messagesEnd = ref<HTMLElement | null>(null)

function toggle() {
  isOpen.value = !isOpen.value
}

async function sendMessage() {
  const text = input.value.trim()
  if (!text || isLoading.value) return

  messages.value.push({ role: 'user', text })
  input.value = ''
  isLoading.value = true
  scrollToBottom()

  try {
    const res = await axios.post('/ai-chat', { message: text })
    messages.value.push({ role: 'ai', text: res.data.reply })
  } catch {
    messages.value.push({ role: 'ai', text: 'Sorry, something went wrong. Please try again.' })
  } finally {
    isLoading.value = false
    scrollToBottom()
  }
}

function scrollToBottom() {
  nextTick(() => {
    messagesEnd.value?.scrollIntoView({ behavior: 'smooth' })
  })
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    sendMessage()
  }
}
</script>

<template>
  <div class="fixed bottom-24 right-8 z-50">

    <!-- Chat Window -->
    <Transition name="chat">
      <div v-if="isOpen" class="mb-4 w-80 bg-zinc-950 border border-gray-800 shadow-2xl shadow-black/50 flex flex-col" style="height: 420px">

        <!-- Header -->
        <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-800 bg-black">
          <div class="relative">
            <div class="w-8 h-8 bg-amber-600 flex items-center justify-center text-black font-bold text-sm">
              HA
            </div>
            <div class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-green-500 rounded-full border border-zinc-950"></div>
          </div>
          <div>
            <p class="text-white text-sm font-medium">Hasibul's Assistant</p>
            <p class="text-green-500 text-xs">Online</p>
          </div>
          <button @click="toggle" class="ml-auto text-gray-600 hover:text-white transition-colors">
            <Icon icon="mdi:close" class="w-5 h-5" />
          </button>
        </div>

        <!-- Messages -->
        <div class="flex-1 overflow-y-auto p-4 space-y-3 scrollbar-thin">
          <div
            v-for="(msg, i) in messages"
            :key="i"
            :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'"
          >
            <div
              :class="[
                'max-w-[85%] px-3 py-2 text-sm leading-relaxed',
                msg.role === 'user'
                  ? 'bg-amber-600 text-black'
                  : 'bg-zinc-900 text-gray-300 border border-gray-800'
              ]"
            >
              {{ msg.text }}
            </div>
          </div>

          <!-- Typing indicator -->
          <div v-if="isLoading" class="flex justify-start">
            <div class="bg-zinc-900 border border-gray-800 px-4 py-3">
              <div class="flex gap-1">
                <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
              </div>
            </div>
          </div>

          <div ref="messagesEnd"></div>
        </div>

        <!-- Input -->
        <div class="p-3 border-t border-gray-800">
          <div class="flex gap-2">
            <input
              v-model="input"
              @keydown="onKeydown"
              type="text"
              placeholder="Ask me anything..."
              class="flex-1 bg-zinc-900 border border-gray-800 text-white text-sm px-3 py-2 focus:border-amber-500/50 focus:outline-none transition-colors placeholder-gray-600"
            />
            <button
              @click="sendMessage"
              :disabled="isLoading || !input.trim()"
              class="px-3 py-2 bg-amber-600 hover:bg-amber-500 text-black transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Icon icon="mdi:send" class="w-4 h-4" />
            </button>
          </div>
          <p class="text-gray-700 text-xs mt-2 text-center">Powered by Gemini AI</p>
        </div>
      </div>
    </Transition>

    <!-- Toggle Button -->
    <button
      @click="toggle"
      class="w-12 h-12 bg-amber-600 hover:bg-amber-500 text-black flex items-center justify-center shadow-lg shadow-amber-900/30 transition-all duration-300 hover:scale-110 rounded-sm relative"
    >
      <Icon v-if="!isOpen" icon="mdi:robot" class="w-6 h-6" />
      <Icon v-else icon="mdi:close" class="w-6 h-6" />

      <!-- Pulse dot -->
      <div v-if="!isOpen" class="absolute -top-1 -right-1 w-3 h-3">
        <div class="animate-ping absolute w-full h-full rounded-full bg-green-400 opacity-75"></div>
        <div class="relative w-3 h-3 rounded-full bg-green-500"></div>
      </div>
    </button>
  </div>
</template>

<style scoped>
.chat-enter-active, .chat-leave-active {
  transition: all 0.3s ease;
}
.chat-enter-from, .chat-leave-to {
  opacity: 0;
  transform: translateY(16px) scale(0.95);
}
</style>
