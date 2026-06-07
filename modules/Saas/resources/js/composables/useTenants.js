import { ref } from 'vue'
import { createGlobalState } from '@vueuse/core'

import { useLoader } from '@/Core/composables/useLoader'

export const useTenants = createGlobalState(() => {
    const {
        setLoading: setLimitedLoading,
        isLoading: limitedNumberOfTenantsLoading,
    } = useLoader()

    const limitedNumberOfActiveTenants = ref([])
    const limitedNumberOfActiveTenantsRetrieved = ref(false)

    async function fetchTenant(id, config) {
        const { data } = await Innoclapps.request(`/saas/tenants/${id}`, config)

        return data
    }

    async function fetchTenantByName(name) {
        const { data } = await Innoclapps.request('/tenants/search', {
            params: {
                q: name,
                search_fields: 'name:=',
            },
        })

        return data.length > 0 ? data[0] : null
    }

    async function retrieveLimitedNumberOfActiveTenants(limit = 100) {
        if (limitedNumberOfActiveTenantsRetrieved.value) {
            return limitedNumberOfActiveTenants.value
        }

        setLimitedLoading(true)

        try {
            const { data } = await fetchActiveTenants({ params: { take: limit } })

            limitedNumberOfActiveTenants.value = data

            return data
        } finally {
            setLimitedLoading(false)
            limitedNumberOfActiveTenantsRetrieved.value = true
        }
    }

    function fetchActiveTenants(config) {
        return Innoclapps.request('/tenants/active', config)
    }

    return {
        limitedNumberOfActiveTenants,
        limitedNumberOfActiveTenantsRetrieved,
        limitedNumberOfTenantsLoading,

        fetchTenant,
        fetchTenantByName,
        retrieveLimitedNumberOfActiveTenants,
        fetchActiveTenants,
    }
})
