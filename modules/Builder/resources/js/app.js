import routes from './routes'

if (window.Innoclapps) {
  Innoclapps.booting((app, router) => {
    routes.forEach(route => router.addRoute(route))

    router.addRoute('settings', {
      path: 'software-customization',
      name: 'settings-software-customization',
      redirect: { name: 'builder-definitions-index' },
      meta: {
        title: 'Software Customization',
        superAdmin: true,
      },
    })
  })
}
