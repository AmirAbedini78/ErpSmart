import { createApp, h, watch } from 'vue'
import { useRouter } from 'vue-router'

import SideBarDropDown from './components/SideBarDropDown.vue'
import Link from './components/UI/Link.vue'
import { useSidebarDropdown } from './composables/useSidebarDropdown'

export class SidebarManager {
  constructor(router) {
    // Create router instance
    this.router = router
  }

  initializeDropdowns() {
    const sidebarItems = document.querySelectorAll(
      '.sidebar-lg-item-saas'
    )

    const button = Array.from(document.querySelectorAll('button')).find(
      (btn) => btn.textContent.trim() === "Open sidebar"
    )
    button.addEventListener("click", (e) => {
      const sidebarMobileItems = document.querySelectorAll(
        '.sidebar-sm-item-saas'
      )

      sidebarMobileItems.forEach(item => this.initializeDropdown(item))
    })
    sidebarItems.forEach(item => this.initializeDropdown(item))
  }

  initializeDropdown(sidebarItem) {
    const dropdownItems = this.getDropdownItems()

    const dropdownWrapper = document.createElement('div')
    dropdownWrapper.className = 'sidebar-dropdown-wrapper'

    sidebarItem.parentNode.insertBefore(
      dropdownWrapper,
      sidebarItem.nextSibling
    )

    const arrowIcon = this.addArrowIcon(sidebarItem)

    const DropdownAppComponent = {
      setup() {
        const { isOpen, arrowRotated, toggleDropdown } = useSidebarDropdown()
        const router = useRouter()

        if (router.currentRoute && router.currentRoute.value) {
          // check for matched routes
          if (router.currentRoute.value.path.startsWith('/saas')) {
            toggleDropdown()
          }
        }

        sidebarItem.addEventListener(
          'click',
          e => {
            e.preventDefault()
            e.stopPropagation()
            toggleDropdown()
          },
          true
        )

        watch(arrowRotated, newValue => {
          if (arrowIcon) {
            arrowIcon.querySelector('svg').style.transform = newValue
              ? 'rotate(180deg)'
              : 'rotate(0)'
          }
        })

        return () =>
          h(SideBarDropDown, {
            isOpen: isOpen.value,
            items: dropdownItems,
            'onUpdate:isOpen': value => {
              isOpen.value = value
            },
            onItemClick: () => {
              isOpen.value = false
            },
          })
      },
    }

    const dropdownApp = createApp(DropdownAppComponent)

    // Register router
    dropdownApp.use(this.router)

    // Register Link component globally
    dropdownApp.component('Link', Link)

    dropdownApp.mount(dropdownWrapper)

    sidebarItem.addEventListener('click', e => {
      e.preventDefault()
      const vm = dropdownApp._instance

      if (vm && vm.exposed && vm.exposed.toggleDropdown) {
        vm.exposed.toggleDropdown()
      }
    })

    return dropdownApp
  }

  addArrowIcon(sidebarItem) {
    const arrowIcon = document.createElement('span')
    arrowIcon.className = 'ml-auto'

    arrowIcon.innerHTML = `
            <svg class="transition-transform duration-200 ease-in-out h-5 w-5"
                 xmlns="http://www.w3.org/2000/svg"
                 viewBox="0 0 20 20"
                 fill="currentColor">
                <path fill-rule="evenodd"
                      d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                      clip-rule="evenodd" />
            </svg>`
    sidebarItem.appendChild(arrowIcon)

    return arrowIcon
  }

  getDropdownItems() {
    return [
      {
        id: 'saas-tenants',
        name: 'Tenants',
        route: `/saas/tenants`,
        icon: 'Users',
      },
      {
        id: 'saas-packages',
        name: 'Packages',
        route: `/saas/packages`,
        icon: 'Document',
      },
      {
        id: 'saas-quotas',
        name: 'Quotas',
        route: `/saas/quotas`,
        icon: 'Collection',
      },
      {
        id: 'saas-pages',
        name: 'Pages',
        route: `/saas/pages`,
        icon: 'Banknotes',
      },
    ]
  }
}
