(function () {
  function closePulldown(root) {
    root.classList.remove('is-open');
    var toggle = root.querySelector('.cf-pulldown-toggle');
    var list = root.querySelector('.cf-pulldown-options');
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'false');
    }
    if (list) {
      list.hidden = true;
    }
  }

  function openPulldown(root) {
    document.querySelectorAll('[data-cf-pulldown].is-open').forEach(function (other) {
      if (other !== root) {
        closePulldown(other);
      }
    });
    root.classList.add('is-open');
    var toggle = root.querySelector('.cf-pulldown-toggle');
    var list = root.querySelector('.cf-pulldown-options');
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'true');
    }
    if (list) {
      list.hidden = false;
    }
  }

  function selectOption(root, button) {
    var input = root.querySelector('input[type="hidden"]');
    var label = root.querySelector('.cf-pulldown-label');
    if (!input || !label) {
      return;
    }

    input.value = button.getAttribute('data-value') || '';
    label.textContent = button.getAttribute('data-label') || '';

    root.querySelectorAll('.cf-pulldown-option').forEach(function (opt) {
      var selected = opt === button;
      opt.classList.toggle('is-selected', selected);
      opt.setAttribute('aria-selected', selected ? 'true' : 'false');
    });

    closePulldown(root);
  }

  document.querySelectorAll('[data-cf-pulldown]').forEach(function (root) {
    var toggle = root.querySelector('.cf-pulldown-toggle');
    if (!toggle) {
      return;
    }

    toggle.addEventListener('click', function () {
      if (root.classList.contains('is-open')) {
        closePulldown(root);
      } else {
        openPulldown(root);
      }
    });

    root.querySelectorAll('.cf-pulldown-option').forEach(function (button) {
      button.addEventListener('click', function () {
        selectOption(root, button);
      });
    });
  });

  document.addEventListener('mousedown', function (event) {
    document.querySelectorAll('[data-cf-pulldown].is-open').forEach(function (root) {
      if (!root.contains(event.target)) {
        closePulldown(root);
      }
    });
  });
})();
