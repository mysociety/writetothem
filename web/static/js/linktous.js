/*
 * linktous.js:
 * Powers the two embed builders (a link builder and the <wtt-postcode-box>
 * form builder) and the "Copy" buttons for the code samples on
 * about-linktous. See linktous_builder_context() in
 * templates/website/about-markdown.php for how the builder markup is
 * assembled from templates/website/about-md/fragments/, and the 'extra_js'
 * entry for about-linktous in templates/website/about-page.php for how this
 * file gets loaded only on that page.
 */
(function () {
    "use strict";
    var el = function (id) { return document.getElementById(id); };

    function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function selectNode(node) {
        var r = document.createRange();
        r.selectNodeContents(node);
        var s = window.getSelection();
        s.removeAllRanges();
        s.addRange(r);
    }
    // URLs are built from the host this page is served on, so copied code
    // points at the right environment. The component derives the cy. host
    // itself from the lang attribute, so only the base host is needed here.
    var loc = window.location;
    var host = { en: loc.protocol + '//' + loc.host };
    function langHost(lang) {
        return lang === 'cy' ? loc.protocol + '//cy.' + loc.host.replace(/^www\./, '') : host.en;
    }
    // Constant labels for the no-JS fallback form, per language. Keep in
    // sync with STRINGS in web/static/js/postcode-embed.js.
    var LABELS = {
        en: { pc: 'Your postcode', go: 'Go' },
        cy: { pc: 'Eich cod post', go: 'Ewch' }
    };
    // --- Link builder ---
    function updateLink() {
        var type = el('lk-type').value;
        var url = host.en + '/' + (type ? '?a=' + encodeURIComponent(type) : '');
        el('lk-out').textContent = url;
        el('lk-go').href = url;
    }
    el('lk-type').addEventListener('change', updateLink);
    // --- Form builder (web component) ---
    var fmMode = 'en', fmPurpose = 'write';
    // The configuration for the <wtt-postcode-box> component, derived from the builder's current options.
    function boxConfig() {
        var defLang = fmMode === 'toggle-cy' ? 'cy' : 'en';
        var attrs = {};
        if (fmMode === 'toggle-en') { attrs['lang-toggle'] = 'en'; }
        else if (fmMode === 'toggle-cy') { attrs['lang-toggle'] = 'cy'; }
        var rep = el('fm-type').selectedOptions[0].getAttribute('data-rep');
        if (rep) { attrs['rep-type'] = rep; }
        if (fmPurpose === 'find') { attrs['purpose'] = 'find'; }
        return {
            attrs: attrs,
            action: langHost(defLang) + '/',
            labels: LABELS[defLang],
            typeFilter: el('fm-type').value
        };
    }
    // The <wtt-postcode-box> markup for the chosen options: a plain no-JS
    // fallback form in the mode's default language, pointing at the matching
    // host. The component adds branding, the toggle and the heading, and
    // records the embed page's URL.
    function boxMarkup() {
        var cfg = boxConfig();
        var attrStr = Object.keys(cfg.attrs).map(function (k) {
            return k + '="' + esc(cfg.attrs[k]) + '"';
        }).join(' ');
        var hidden = '';
        if (cfg.typeFilter) { hidden += `\n    <input type="hidden" name="a" value="${esc(cfg.typeFilter)}">`; }
        if (fmPurpose === 'find') { hidden += '\n    <input type="hidden" name="purpose" value="find">'; }
        return `<wtt-postcode-box${attrStr ? ' ' + attrStr : ''}>
  <form action="${esc(cfg.action)}" method="get">
    <input name="pc" placeholder="${esc(cfg.labels.pc)}" autocomplete="postal-code" required>${hidden}
    <button type="submit">${esc(cfg.labels.go)}</button>
  </form>
</wtt-postcode-box>`;
    }
    function formSnippet() {
        return boxMarkup() + '\n<script async src="' + esc(host.en + '/static/js/postcode-embed.js') + '"><\/script>';
    }
    // The preview is a live instance of the same markup; once postcode-embed.js has
    // defined the element (loaded below), the browser upgrades it in place.
    function renderPreview() {
        el('fm-preview').innerHTML = boxMarkup();
    }
    function updateForm() {
        el('fm-out').textContent = formSnippet();
        renderPreview();
    }
    function toggle(activeId, otherId) {
        el(activeId).classList.add('is-active');
        el(activeId).setAttribute('aria-pressed', 'true');
        el(otherId).classList.remove('is-active');
        el(otherId).setAttribute('aria-pressed', 'false');
    }
    function setPurpose(p) {
        fmPurpose = p;
        if (p === 'find') { toggle('fm-purpose-find', 'fm-purpose-write'); }
        else { toggle('fm-purpose-write', 'fm-purpose-find'); }
        updateForm();
    }
    el('fm-type').addEventListener('change', updateForm);
    el('fm-purpose-write').addEventListener('click', function () { setPurpose('write'); });
    el('fm-purpose-find').addEventListener('click', function () { setPurpose('find'); });
    el('fm-mode').addEventListener('change', function () { fmMode = el('fm-mode').value; updateForm(); });
    // --- Copy buttons (both builders and the dev-guide examples) ---
    Array.prototype.forEach.call(document.querySelectorAll('.wtt-code__copy'), function (btn) {
        btn.addEventListener('click', function () {
            var code = el(btn.getAttribute('data-target')), text = code.textContent;
            var done = function () { var o = btn.textContent; btn.textContent = 'Copied!'; setTimeout(function () { btn.textContent = o; }, 2000); };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done, function () { selectNode(code); });
            } else { selectNode(code); }
        });
    });
    // Load the component so the live preview upgrades (same origin as this page).
    var s = document.createElement('script');
    s.async = true;
    s.src = '/static/js/postcode-embed.js';
    document.head.appendChild(s);
    updateLink();
    updateForm();
})();
