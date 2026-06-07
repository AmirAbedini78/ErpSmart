import { ref } from 'vue'
import { createGlobalState } from '@vueuse/core'

import { useLoader } from '@/Core/composables/useLoader'

export const usePackages = createGlobalState(() => {
    const {
        setLoading: setLimitedLoading,
        isLoading: limitedNumberOfPackagesLoading,
    } = useLoader()

    const limitedNumberOfActivePackages = ref([])
    const limitedNumberOfActivePackagesRetrieved = ref(false)

    async function fetchPackage(id, config) {
        const { data } = await Innoclapps.request(`/saas/packages/${id}`, config)

        return data
    }

    async function fetchPackageByName(name) {
        const { data } = await Innoclapps.request('/packages/search', {
            params: {
                q: name,
                search_fields: 'name:=',
            },
        })

        return data.length > 0 ? data[0] : null
    }

    async function retrieveLimitedNumberOfActivePackages(limit = 100) {
        if (limitedNumberOfActivePackagesRetrieved.value) {
            return limitedNumberOfActivePackages.value
        }

        setLimitedLoading(true)

        try {
            const { data } = await fetchActivePackages({ params: { take: limit } })

            limitedNumberOfActivePackages.value = data

            return data
        } finally {
            setLimitedLoading(false)
            limitedNumberOfActivePackagesRetrieved.value = true
        }
    }

    function fetchActivePackages(config) {
        return Innoclapps.request('/packages/active', config)
    }

    return {
        limitedNumberOfActivePackages,
        limitedNumberOfActivePackagesRetrieved,
        limitedNumberOfPackagesLoading,

        fetchPackage,
        fetchPackageByName,
        retrieveLimitedNumberOfActivePackages,
        fetchActivePackages,
    }
})
