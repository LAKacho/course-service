// Delegation survives AJAX navigation. Availability remains server-side.
document.addEventListener('click', event => {
  const link = event.target.closest?.('[data-quest-jump]');
  if (!link) return;
  const target = document.getElementById(link.hash.slice(1));
  const details = target?.querySelector('details');
  if (details) details.open = true;
  target?.querySelector('h3')?.focus({preventScroll:true});
});
