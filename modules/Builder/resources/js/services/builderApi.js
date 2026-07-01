const endpoint = '/builder/definitions'

export function fetchDefinitions() {
  return Innoclapps.request(endpoint)
}

export function fetchDefinition(id) {
  return Innoclapps.request(`${endpoint}/${id}`)
}

export function createDefinition(payload) {
  return Innoclapps.request().post(endpoint, payload)
}

export function updateDefinition(id, payload) {
  return Innoclapps.request().put(`${endpoint}/${id}`, payload)
}

export function validateDefinition(id) {
  return Innoclapps.request().post(`${endpoint}/${id}/validate`)
}

export function previewDefinition(id) {
  return Innoclapps.request().post(`${endpoint}/${id}/preview`)
}
