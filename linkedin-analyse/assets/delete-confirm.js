(function () {
  document.querySelectorAll('[data-delete-confirm]').forEach(function (btn) {
    var form = btn.closest('form')
    if (!form) return

    var confirming = false

    btn.addEventListener('click', function () {
      if (!confirming) {
        confirming = true
        btn.textContent = 'Confirm delete'
        btn.classList.remove('action-link-delete')
        btn.classList.add('action-link-confirm-delete')
        btn.setAttribute('aria-label', 'Confirm delete')
        return
      }

      form.submit()
    })
  })
})()
