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

export function generatePublishDryRun(id) {
  return Innoclapps.request().post(`${endpoint}/${id}/publish-dry-run`)
}

export function createPublishCandidateSnapshot(id) {
  return Innoclapps.request().post(`${endpoint}/${id}/publish-candidate-snapshot`)
}

export function listPublishApprovalRequests(definitionId) {
  return Innoclapps.request(`${endpoint}/${definitionId}/publish-approval-requests`)
}

export function requestPublishApproval(definitionId) {
  return Innoclapps.request().post(`${endpoint}/${definitionId}/publish-approval-requests`)
}

export function approvePublishApprovalRequest(requestId, note) {
  return Innoclapps.request().post(`/builder/publish-approval-requests/${requestId}/approve`, { note })
}

export function rejectPublishApprovalRequest(requestId, note) {
  return Innoclapps.request().post(`/builder/publish-approval-requests/${requestId}/reject`, { note })
}

export function revokePublishApprovalRequest(requestId, note) {
  return Innoclapps.request().post(`/builder/publish-approval-requests/${requestId}/revoke`, { note })
}

export function getApprovedCandidatePreflight(definitionId) {
  return Innoclapps.request(`${endpoint}/${definitionId}/approved-candidate-preflight`)
}

export function listPublishExecutions(definitionId) {
  return Innoclapps.request(`${endpoint}/${definitionId}/publish-executions`)
}

export function createPublishExecutionRecord(definitionId) {
  return Innoclapps.request().post(`${endpoint}/${definitionId}/publish-executions`)
}

export function validatePublishExecutionStagedFiles(executionId) {
  return Innoclapps.request().post(`/builder/publish-executions/${executionId}/validate-staged-files`)
}

export function createRuntimeWritePlan(executionId) {
  return Innoclapps.request().post(`/builder/publish-executions/${executionId}/runtime-write-plan`)
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
