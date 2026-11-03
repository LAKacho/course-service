(() => {
  const tools = document.querySelector('[data-catalog-tools]');
  const grid = document.querySelector('[data-catalog-grid]');
  if (!tools || !grid) return;

  const search = tools.querySelector('[data-catalog-search]');
  const status = tools.querySelector('[data-catalog-status]');
  const result = tools.querySelector('[data-catalog-result]');
  const cards = [...grid.querySelectorAll('[data-catalog-item]')];
  const empty = grid.querySelector('[data-catalog-empty]');
  const formatCount = (count) => `${count} ${count === 1 ? 'материал' : count > 1 && count < 5 ? 'материала' : 'материалов'}`;

  const update = () => {
    const query = search.value.trim().toLocaleLowerCase('ru');
    const selectedStatus = status.value;
    let visible = 0;

    for (const card of cards) {
      const matchesText = !query || card.textContent.toLocaleLowerCase('ru').includes(query);
      const matchesStatus = selectedStatus === 'all' || card.dataset.catalogStatus === selectedStatus;
      card.hidden = !(matchesText && matchesStatus);
      if (!card.hidden) visible++;
    }

    if (result) result.textContent = `${formatCount(visible)} из ${cards.length}`;
    if (empty) empty.hidden = cards.length === 0 || visible > 0;
  };

  search.addEventListener('input', update);
  status.addEventListener('change', update);
  document.addEventListener('keydown', (event) => {
    if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
    event.preventDefault();
    search.focus();
  });

  update();
})();
