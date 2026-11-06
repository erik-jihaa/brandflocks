export function isPublishFailureNotice(notice: string): boolean {
  const lower = notice.toLowerCase()
  return (
    lower.includes('mislukt') ||
    notice.includes('niet geconfigureerd') ||
    notice.includes('Integrations') ||
    notice.includes('WordPress-weigering') ||
    notice.includes('Alleen goedgekeurde') ||
    notice.includes('nog niet op igisnext')
  )
}

export function showPublishResultNotice(
  notice: string,
  showSuccess: (text: string) => void,
  showError: (text: string) => void,
): void {
  if (isPublishFailureNotice(notice)) {
    showError(notice)
  } else {
    showSuccess(notice)
  }
}
