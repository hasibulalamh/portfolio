import { ref } from 'vue'

const isRecruiterMode = ref(true)

export function useRecruiterMode() {
  return {
    isRecruiterMode
  }
}
