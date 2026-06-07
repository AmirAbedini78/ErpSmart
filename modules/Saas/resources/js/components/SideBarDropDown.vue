<template>
  <Transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="transform scale-y-0 opacity-0"
    enter-to-class="transform scale-y-100 opacity-100"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="transform scale-y-100 opacity-100"
    leave-to-class="transform scale-y-0 opacity-0"
  >
    <div v-if="isOpen" class="sidebar-dropdown-menu mt-1 origin-top pl-4">
      <a
        v-for="item in items"
        :key="item.id"
        :class="[
          'group relative flex items-center rounded-md px-2 py-2 text-sm focus:outline-none mt-1',
          'sidebar-lg-item-' + item.id,
          router.currentRoute.value.path === item.route
            ? 'bg-white/10 text-white'
            : 'text-neutral-50 hover:bg-white/10',
        ]"
        :href="item.route"
        @click="e => handleItemClick(e, item)"
      >
        <Icon
          v-if="item.icon"
          class="mr-3 size-6 shrink-0 text-neutral-300"
          :icon="item.icon"
        />
        {{ item.name }}
      </a>
    </div>
  </Transition>
</template>

<script setup>
import { defineProps } from 'vue'
import { useRouter } from 'vue-router'

import Icon from '../../../../Core/resources/js/components/UI/Icon.vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true,
    default: false,
  },
  items: {
    type: Array,
    required: true,
    default: () => [],
  },
})

const router = useRouter()

const handleItemClick = (e, item) => {
  e.preventDefault()
  router.push(item.route)
}
</script>

<style scoped>
.sidebar-dropdown-menu {
  /* Add positioning and layout */
  position: relative;
  width: 100%;

  /* Ensure transform origin is set for scaling */
  transform-origin: top;
}

.sidebar-item {
  cursor: pointer;
  text-decoration: none;
  color: #374151;
  transition: all 0.2s ease;
}

.sidebar-item:hover {
  color: #1f2937;
  background-color: rgba(0, 0, 0, 0.05);
}

.sidebar-item-text {
  font-size: 0.875rem;
  line-height: 1.25rem;
}

/* Add Tailwind classes if not already included in your project */
.transition {
  transition-property: all;
}

.duration-200 {
  transition-duration: 200ms;
}

.ease-in {
  transition-timing-function: cubic-bezier(0.4, 0, 1, 1);
}

.ease-out {
  transition-timing-function: cubic-bezier(0, 0, 0.2, 1);
}

.transform {
  transform: translate(var(--tw-translate-x), var(--tw-translate-y))
    rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y))
    scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y));
}

.scale-y-0 {
  --tw-scale-y: 0;
  transform: scaleY(0);
}

.scale-y-100 {
  --tw-scale-y: 1;
  transform: scaleY(1);
}

.opacity-0 {
  opacity: 0;
}

.opacity-100 {
  opacity: 1;
}

.origin-top {
  transform-origin: top;
}
</style>
