// Delegation survives AJAX navigation. Availability remains server-side.
document.addEventListener('click', event => {
  const nav = event.target.closest?.('.path-nav[data-nav-target]');
  if (nav) {
    event.preventDefault();
    const targetName = nav.dataset.navTarget;
    const target = targetName === 'top' ? null : document.getElementById(targetName);
    const hash = targetName === 'top' ? '' : `#${targetName}`;
    history.pushState({}, '', `${location.pathname}${location.search}${hash}`);
    const behavior = matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
    if (target) target.scrollIntoView({behavior, block: 'start'});
    else scrollTo({top: 0, behavior});
    updateStudentNavigation();
    return;
  }
  const link = event.target.closest?.('[data-quest-jump]');
  if (!link) return;
  const target = document.getElementById(link.hash.slice(1));
  const details = target?.querySelector('details');
  if (details) details.open = true;
  target?.querySelector('h3')?.focus({preventScroll:true});
});

function updateStudentNavigation() {
  const links = [...document.querySelectorAll('.path-nav[data-nav-target]')];
  if (!links.length) return;
  const threshold = Math.min(innerHeight * 0.35, 260);
  let active = 'top';
  for (const section of ['atlas', 'journal']) {
    const node = document.getElementById(section);
    if (node && node.getBoundingClientRect().top <= threshold) active = section;
  }
  for (const link of links) {
    const selected = link.dataset.navTarget === active;
    link.classList.toggle('active', selected);
    if (selected) link.setAttribute('aria-current', 'location');
    else link.removeAttribute('aria-current');
  }
}

let studentNavigationFrame = 0;
addEventListener('scroll', () => {
  if (studentNavigationFrame) return;
  studentNavigationFrame = requestAnimationFrame(() => {
    studentNavigationFrame = 0;
    updateStudentNavigation();
  });
}, {passive:true});
addEventListener('resize', updateStudentNavigation, {passive:true});
document.addEventListener('app:page-load', updateStudentNavigation);
updateStudentNavigation();
