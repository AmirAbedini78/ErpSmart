const endpoint = '/builder/definitions'

export function listDefinitions() {
  return Innoclapps.request(endpoint)
}

export function getDefinition(id) {
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

export function analyzePublishReadiness(id) {
  return Innoclapps.request().post(`${endpoint}/${id}/publish-readiness`)
}

export function archiveDefinition(id) {
  return Innoclapps.request().post(`${endpoint}/${id}/archive`)
}

export function restoreDefinition(id) {
  return Innoclapps.request().post(`${endpoint}/${id}/restore`)
}

export function deleteDefinition(id) {
  return Innoclapps.request().delete(`${endpoint}/${id}`)
}

export const fetchDefinitions = listDefinitions
export const fetchDefinition = getDefinition
