<template>
  <ICardHeader>
    <ICardHeading class="flex items-center gap-x-2">
      <Icon v-if="isConfigured && componentReady" icon="CheckCircleSolid" class="size-5 text-success-600" />
    </ICardHeading>
  </ICardHeader>
  <ITabGroup v-model="activeTabIndex">
    <ITabList>
      <ITab @activated="changeTab(0)">
        <Icon icon="User" />
        {{ $t('saas::saas.setting.general') }}
      </ITab>
      <ITab @activated="changeTab(1)">
        <Icon icon="ShieldExclamation" />
        {{ $t('saas::saas.setting.integration') }}
      </ITab>
    </ITabList>

    <ITabPanels>
      <ITabPanel>
        <General />
      </ITabPanel>
      <ITabPanel>
        <Integration />
      </ITabPanel>

    </ITabPanels>
  </ITabGroup>
</template>

<script>
import { ref } from 'vue'
import { onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router'
import General from '../views/Settings/General.vue'
import Integration from "../views/Settings/Integration.vue";
const route = useRoute()
const router = useRouter()
const activeTabIndex = ref(0)

export default {
  name: "SaasSettingManager",
  components: {
    General,
    Integration
  },
  data() {
    return {
      activeTabIndex: 0
    };
  },
  methods: {
    changeTab(index) {
      this.activeTabIndex = index;
    }
  }
};
</script>

<style scoped>
/* Add any styles if needed */
</style>
