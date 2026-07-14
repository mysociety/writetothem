/*
 * postcode-embed.js:
 * Defines the <wtt-postcode-box> web component, which enhances a plain
 * postcode <form> (see /about-linktous) into a self-contained, branded box
 * using the Shadow DOM. The plain form still works if this script never runs.
 *
 * Modes set on the element:
 *   (no lang-toggle)   English only, no toggle.
 *   lang-toggle="en"   Show a language toggle, default English.
 *   lang-toggle="cy"   Show a language toggle, default Welsh.
 *
 * The heading is described per embed by two attributes set by the builder:
 * rep-type (the English representative noun, e.g. "MSPs"; absent for "all
 * representatives") and purpose ("write" or "find", defaulting to "write").
 * The component frames these into a heading and, for Welsh, translates the noun
 * itself.
 */
(function () {
    "use strict";
    if (!('customElements' in window) || customElements.get('wtt-postcode-box')) {
        return;
    }

    // Constant UI text and heading frames, by language.
    var STRINGS = {
        en: {
            postcode: 'Your postcode',
            go: 'Go',
            help: 'What postcode should I use?',
            name: 'English',
            langLabel: 'Language',
            // %s is the rep noun; the "All" frames are used when no rep-type is set.
            writeTo: 'Write to your %s',
            findYour: 'Find your %s',
            writeAll: 'Write to your representatives',
            findAll: 'Find out who represents you'
        },
        cy: {
            postcode: 'Nodwch eich cod post',
            go: 'Amdani',
            help: 'Pa god post ddylwn i ei ddefnyddio?',
            name: 'Cymraeg',
            langLabel: 'Iaith',
            writeTo: 'Ysgrifennwch at eich %s',
            findYour: 'Find your %s', // to update
            writeAll: 'Write to your representatives', // to update
            findAll: 'Find out who represents you' // to update
        }
    };

    // Welsh for each English rep noun the builder can set as rep-type.
    var REP_CY = {
        'MP': 'Aelod Seneddol',
        'councillors': 'cynghorwyr',
        'district councillors': 'cynghorwyr dosbarth',
        'county councillors': 'cynghorwyr sir',
        'MSPs': 'ASAau',
        'MSs': "Aelodau'r Senedd",
        'MLAs': 'ACDau',
        'London Assembly Members': 'Aelodau Cynulliad Llundain',
        'devolved representative': 'devolved representative' // to update
    };

    var CSS = `
        :host { display: block; all: initial; }
        .box { box-sizing: border-box; max-width: 360px; padding: 14px 16px; border-radius: 8px;
            background: #11769d; color: #fff; line-height: 1.4;
            font-family: "Source Sans Pro", "Helvetica Neue", Helvetica, Arial, sans-serif; }
        .box * { box-sizing: border-box; font-family: inherit; }
        .langs { margin: 8px 0 0; font-size: 0.8em; text-align: right; }
        .langs button { background: none; border: 0; padding: 0 4px; font: inherit; cursor: pointer;
            color: #dbeef5; text-decoration: underline; }
        .langs button[aria-pressed="true"] { color: #fff; font-weight: bold; text-decoration: none; cursor: default; }
        .logo { display: inline-block; }
        .logo img { display: block; width: 125px; height: 35px; border: 0; }
        .heading { display: block; margin: 10px 0 8px; font-size: 1.1em; font-weight: bold; color: #fff; }
        .row { display: flex; gap: 6px; }
        .pc { flex: 1 1 auto; min-width: 0; padding: 8px 10px; font-size: 1em; color: #333;
            background: #fff; border: 1px solid #0d5d7d; border-radius: 3px; }
        .go { flex: 0 0 auto; padding: 8px 16px; font-size: 1em; font-weight: bold; color: #fff;
            background: #5f9f57; border: 0; border-radius: 3px; cursor: pointer; }
        .go:hover { background: #4f8a48; }
        .help { margin: 8px 0 0; font-size: 0.8em; }
        .help a { color: #dbeef5; text-decoration: underline; }
        .help a:hover { color: #fff; }`;

    // Where this script was served from (adjusts paths for test servers, etc.)
    var origin = 'https://www.writetothem.com';
    try {
        if (document.currentScript && document.currentScript.src) {
            origin = new URL(document.currentScript.src).origin;
        }
    } catch (e) {}
    var LOGO = origin + '/static/img/logo.png';

    function escAttr(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function escText(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Point the action at the host for `lang`: cy.<domain> for Welsh, the plain
    // (non-cy) host otherwise. Normalises either way so the toggle can switch
    // back and forth.
    function localiseAction(action, lang) {
        try {
            var u = new URL(action, location.href);
            var bare = u.hostname.replace(/^cy\./i, '');
            u.hostname = lang === 'cy' ? 'cy.' + bare.replace(/^www\./i, '') : bare;
            return u.href;
        } catch (e) {
            return action;
        }
    }

    class WttPostcodeBox extends HTMLElement {
        // Build the heading for `lang` from the rep-type (English noun) and
        // purpose attributes. With a rep-type, translate the noun to Welsh (or
        // keep the English if it isn't in REP_CY) and frame it; without one,
        // fall back to the generic "all representatives" frame.
        heading(lang) {
            var s = STRINGS[lang] || STRINGS.en;
            var find = this.getAttribute('purpose') === 'find';
            var rep = this.getAttribute('rep-type');
            if (rep) {
                if (lang === 'cy') { rep = REP_CY[rep] || rep; }
                return (find ? s.findYour : s.writeTo).replace('%s', rep);
            }
            return find ? s.findAll : s.writeAll;
        }

        connectedCallback() {
            if (!this.shadowRoot) {
                this.attachShadow({ mode: 'open' });
                var toggle = this.getAttribute('lang-toggle');
                this.toggleEnabled = (toggle === 'en' || toggle === 'cy');
                this.currentLang = this.toggleEnabled ? toggle : 'en';
                // One delegated listener survives re-renders.
                var self = this;
                this.shadowRoot.addEventListener('click', function (e) {
                    var btn = e.target && e.target.closest ? e.target.closest('[data-lang]') : null;
                    if (btn) {
                        var l = btn.getAttribute('data-lang');
                        if (l !== self.currentLang) {
                            self.currentLang = l;
                            self.render();
                        }
                    }
                });
            }
            this.render();
        }

        render() {
            // The author's plain <form> is the source of truth for the action
            // and hidden fields, and works on its own if this script never runs.
            var srcForm = this.querySelector('form');
            if (!srcForm) {
                return;
            }

            var lang = this.currentLang;
            var s = STRINGS[lang] || STRINGS.en;
            var heading = this.heading(lang);
            var method = srcForm.getAttribute('method') || 'get';
            var action = localiseAction(srcForm.getAttribute('action') || (origin + '/'), lang);

            // Keep the form's hidden fields (a, ...), and record the page this
            // box is embedded on as the referrer unless one was supplied.
            var hiddenFields = {};
            var inputs = srcForm.querySelectorAll('input[type=hidden]');
            for (var i = 0; i < inputs.length; i++) {
                if (inputs[i].value) {
                    hiddenFields[inputs[i].name] = inputs[i].value;
                }
            }
            if (!hiddenFields.fyr_extref) {
                hiddenFields.fyr_extref = this.getAttribute('fyr_extref') || (location.origin + location.pathname);
            }
            var hidden = '';
            for (var name in hiddenFields) {
                hidden += `<input type="hidden" name="${escAttr(name)}" value="${escAttr(hiddenFields[name])}">`;
            }

            var helpUrl;
            try {
                helpUrl = new URL('about-constituency', action).href;
            } catch (e) {
                helpUrl = origin + '/about-constituency';
            }

            var toggleHtml = '';
            if (this.toggleEnabled) {
                toggleHtml =
                    `<div class="langs" role="group" aria-label="${escAttr(s.langLabel)}">` +
                        `<button type="button" data-lang="en" aria-pressed="${lang === 'en' ? 'true' : 'false'}">${escText(STRINGS.en.name)}</button>` +
                        `<button type="button" data-lang="cy" aria-pressed="${lang === 'cy' ? 'true' : 'false'}">${escText(STRINGS.cy.name)}</button>` +
                    `</div>`;
            }
            var headingHtml = heading
                ? `<p class="heading" id="wtt-heading" role="heading" aria-level="2">${escText(heading)}</p>`
                : '';

            // Keep anything the visitor already typed when the language changes.
            var prev = this.shadowRoot.querySelector('#wtt-pc');
            var pcValue = prev ? prev.value : '';

            this.shadowRoot.innerHTML = `
                <style>${CSS}</style>
                <div class="box" lang="${escAttr(lang)}">
                    <a class="logo" href="${escAttr(action)}" target="_blank" rel="noopener"><img src="${escAttr(LOGO)}" alt="WriteToThem"></a>
                    <form action="${escAttr(action)}" method="${escAttr(method)}"${heading ? ' aria-labelledby="wtt-heading"' : ''}>
                        ${headingHtml}
                        <div class="row">
                            <input class="pc" id="wtt-pc" type="text" name="pc" placeholder="${escAttr(s.postcode)}" aria-label="${escAttr(s.postcode)}" autocomplete="postal-code" required>
                            <button class="go" type="submit">${escText(s.go)}</button>
                        </div>
                        ${hidden}
                    </form>
                    <p class="help"><a href="${escAttr(helpUrl)}" target="_blank" rel="noopener">${escText(s.help)}</a></p>
                    ${toggleHtml}
                </div>`;

            if (pcValue) {
                var pc = this.shadowRoot.querySelector('#wtt-pc');
                if (pc) { pc.value = pcValue; }
            }
        }
    }

    customElements.define('wtt-postcode-box', WttPostcodeBox);
})();
