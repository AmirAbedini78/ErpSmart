<template>
    <div v-for="item in sidebarItems" :key="item.dataset.itemId" class="sidebar-dropdown-wrapper">
        <div
            class="sidebar-lg-item-saas"
            @click.prevent="() => toggleDropdown(item.dataset.itemId)"
        >
            {{ item.textContent }}
            <span class="ml-auto">
        <svg
            class="transition-transform duration-200 ease-in-out h-5 w-5"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
            :style="{
            transform: dropdownStates[item.dataset.itemId] ? 'rotate(180deg)' : 'rotate(0deg)'
          }"
        >
          <path
              fill-rule="evenodd"
              d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
              clip-rule="evenodd"
          />
        </svg>
      </span>
        </div>
        <SideBarDropDown
            v-if="dropdownStates[item.dataset.itemId]"
            :isOpen="dropdownStates[item.dataset.itemId]"
            :items="getDropdownItems(item.dataset.itemId)"
            @update:isOpen="updateDropdownState(item.dataset.itemId, $event)"
            @itemClick="closeDropdown(item.dataset.itemId)"
        />
    </div>
</template>

<script>
import { ref, reactive, onMounted, nextTick } from "vue";
import SideBarDropDown from './SideBarDropDown.vue';
import Link from './UI/Link.vue';

export default {
    name: 'SidebarDropdownManager',
    components: {
        SideBarDropDown,
        Link,
    },
    setup() {
        const sidebarItems = ref([]);
        const dropdownStates = reactive({});

        const getDropdownItems = (itemId) => [
            { id: 'sub-item-1', name: 'Sub Item 1', route: '/saas/tenants' },
            { id: 'sub-item-2', name: 'Sub Item 2', route: '/saas/packages' },
        ];

        const toggleDropdown = (itemId) => {
            dropdownStates[itemId] = !dropdownStates[itemId];
        };

        const updateDropdownState = (itemId, isOpen) => {
            dropdownStates[itemId] = isOpen;
        };

        const closeDropdown = (itemId) => {
            dropdownStates[itemId] = false;
        };

        onMounted(() => {

        });

        return {
            sidebarItems,
            dropdownStates,
            getDropdownItems,
            toggleDropdown,
            updateDropdownState,
            closeDropdown,
        };
    },
};
</script>

<style>
.sidebar-dropdown-wrapper {
    margin-bottom: 1rem;
}

.sidebar-lg-item-saas {
    display: flex;
    align-items: center;
    cursor: pointer;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #f9f9f9;
    transition: background-color 0.2s ease-in-out;
}

.sidebar-lg-item-saas:hover {
    background-color: #f1f1f1;
}

.ml-auto {
    margin-left: auto;
}
</style>
