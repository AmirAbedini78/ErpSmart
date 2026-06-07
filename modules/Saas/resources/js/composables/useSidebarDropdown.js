import { ref, watch } from 'vue'

export const useSidebarDropdown = () => {
  const isOpen = ref(false)
  const arrowRotated = ref(false)

  // Sync arrow rotation with dropdown state
  watch(isOpen, newValue => {
    arrowRotated.value = newValue
  })

  const toggleDropdown = () => {
    isOpen.value = !isOpen.value
  }

  return {
    isOpen,
    arrowRotated,
    toggleDropdown,
  }
}
