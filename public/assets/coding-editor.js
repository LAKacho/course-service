/* Small display-only PHP/Go tokenizer. Never executes source or uses innerHTML. */
(() => {
function initializeEditors() {
document.querySelectorAll('[data-code-form]').forEach(form => {
  if (form.dataset.editorReady) return;
  form.dataset.editorReady = 'true';
  const input = form.querySelector('textarea'), language = form.querySelector('select');
  const box = form.querySelector('.code-editor'), output = box.querySelector('code');
  const starters = JSON.parse(form.dataset.starters), drafts = {};
  let active = language.value;
  const pattern = /(\/\*[\s\S]*?\*\/|\/\/[^\n]*|#[^\n]*|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|`[^`]*`|\b(?:function|return|if|else|foreach|for|while|class|public|private|static|new|namespace|use|declare|strict_types|func|package|import|var|const|type|struct|interface|range|go|defer|chan|select|case|switch|break|continue|nil|true|false|int|string|bool|float64|error)\b|\b\d+(?:\.\d+)?\b)/g;
  function highlight() {
    const source = input.value + '\n', fragment = document.createDocumentFragment();
    let end = 0;
    for (const match of source.matchAll(pattern)) {
      fragment.append(document.createTextNode(source.slice(end, match.index)));
      const span = document.createElement('span'), token = match[0];
      span.className = 'token-' + (/^(\/\/|\/\*|#)/.test(token) ? 'comment' : /^["'`]/.test(token) ? 'string' : /^\d/.test(token) ? 'number' : 'keyword');
      span.textContent = token; fragment.append(span); end = match.index + token.length;
    }
    fragment.append(document.createTextNode(source.slice(end))); output.replaceChildren(fragment);
    box.querySelector('pre').scrollTop = input.scrollTop; box.querySelector('pre').scrollLeft = input.scrollLeft;
  }
  input.addEventListener('input', highlight);
  input.addEventListener('scroll', highlight);
  input.addEventListener('keydown', event => {
    if (event.key === 'Tab') { event.preventDefault(); input.setRangeText('  ', input.selectionStart, input.selectionEnd, 'end'); highlight(); }
  });
  language.addEventListener('change', () => { drafts[active] = input.value; active = language.value; input.value = drafts[active] ?? starters[active]; highlight(); });
  highlight(); box.classList.add('enhanced');
});

}
initializeEditors();
document.addEventListener('app:page-load', initializeEditors);
})();
