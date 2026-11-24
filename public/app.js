document.querySelectorAll('[data-image-preview]').forEach((input) => {
  const preview = document.getElementById(input.dataset.imagePreview);
  if (!preview) return;

  let previousUrl = null;
  input.addEventListener('change', () => {
    const file = input.files && input.files[0];
    if (!file) return;

    if (previousUrl) URL.revokeObjectURL(previousUrl);
    previousUrl = URL.createObjectURL(file);
    preview.src = previousUrl;
    preview.hidden = false;
  });
});

document.querySelectorAll('form[data-auto-submit] select').forEach((select) => {
  select.addEventListener('change', () => select.form.requestSubmit());
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
  button.addEventListener('click', () => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    if (!input) return;
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    button.textContent = visible ? 'Hide' : 'Show';
    button.setAttribute('aria-label', `${visible ? 'Hide' : 'Show'} ${input.name.replace(/_/g, ' ')}`);
    button.setAttribute('aria-pressed', String(visible));
    input.focus({ preventScroll: true });
  });
});

// Responsive hamburger navigation
(() => {
  const header = document.querySelector('.site-header');
  const toggle = header && header.querySelector('.nav-toggle');
  if (!header || !toggle) return;

  const desktop = window.matchMedia('(min-width: 901px)');

  const closeMenu = (returnFocus = false) => {
    if (!header.classList.contains('is-open')) return;
    header.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Open menu');
    if (returnFocus) toggle.focus();
  };

  const openMenu = () => {
    header.classList.add('is-open');
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', 'Close menu');
  };

  toggle.addEventListener('click', () => {
    if (header.classList.contains('is-open')) closeMenu();
    else openMenu();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeMenu(true);
  });

  document.addEventListener('click', (event) => {
    if (!header.contains(event.target)) closeMenu();
  });

  header.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => closeMenu());
  });

  const handleDesktop = (event) => {
    if (event.matches) closeMenu();
  };
  if (typeof desktop.addEventListener === 'function') desktop.addEventListener('change', handleDesktop);
  else if (typeof desktop.addListener === 'function') desktop.addListener(handleDesktop);
})();

// Delivery-only fields are required only while delivery is selected.
document.querySelectorAll('[data-fulfilment-form]').forEach((form) => {
  const radios = form.querySelectorAll('input[name="fulfillment"]');
  const fields = form.querySelectorAll('[data-delivery-field]');
  if (!radios.length || !fields.length) return;

  const sync = () => {
    const delivery = Array.from(radios).some((radio) => radio.checked && radio.value === 'delivery');
    fields.forEach((field) => {
      if (delivery) field.setAttribute('required', '');
      else field.removeAttribute('required');
      field.setAttribute('aria-required', String(delivery));
      const group = field.closest('[data-delivery-only]');
      if (group) group.hidden = !delivery;
    });
    form.querySelectorAll('[data-delivery-only]').forEach((group) => { group.hidden = !delivery; });
  };

  radios.forEach((radio) => radio.addEventListener('change', sync));
  sync();
});

// Ask before a destructive action such as cancelling an order.
document.querySelectorAll('form[data-confirm]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
});

// Filter the vendor catalogue without reloading the product management page.
document.querySelectorAll('[data-product-search]').forEach((input) => {
  input.addEventListener('input', () => {
    const query = input.value.trim().toLocaleLowerCase();
    document.querySelectorAll('[data-product-row]').forEach((row) => {
      row.hidden = !row.textContent.toLocaleLowerCase().includes(query);
    });
  });
});

// Expand the requested product panel when a catalogue action is selected.
const openProductPanel = () => {
  if (!location.hash) return;
  const target = document.querySelector(location.hash);
  if (target instanceof HTMLDetailsElement) target.open = true;
};
window.addEventListener('hashchange', openProductPanel);
openProductPanel();
