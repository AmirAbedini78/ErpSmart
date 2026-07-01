import BuilderDefinitionsIndex from './views/BuilderDefinitionsIndex.vue'
import BuilderDefinitionView from './views/BuilderDefinitionView.vue'

export default [
  {
    path: '/builder',
    redirect: { name: 'builder-definitions-index' },
  },
  {
    path: '/builder/definitions',
    name: 'builder-definitions-index',
    component: BuilderDefinitionsIndex,
    meta: {
      title: 'Builder Studio',
      superAdmin: true,
    },
  },
  {
    path: '/builder/definitions/:id',
    name: 'builder-definition-view',
    component: BuilderDefinitionView,
    meta: {
      title: 'Builder Definition',
      superAdmin: true,
    },
  },
]
