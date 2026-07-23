Want to help people contact their elected representatives from your own website? Add a WriteToThem postcode box with the builder below, or — if you already know someone’s details — link them straight into a pre-filled form from your own code. For example, you could:

- add a “write to your MP” box to a campaign page;
- put a “find your local councillor” box on an MP’s own website, to help people reach the right person;
- send newsletter readers straight to a form to write to their MP.

[TOC]

If you’re running a campaign, please read the [guidelines for campaigners](about-guidelines) first: you must not ask people to send pre-written, identical letters, as they are against our conditions of use and we will block them.

## Add a form to your site {#form}

Add a WriteToThem postcode box to your own page. Choose who it should reach and what it should say, then copy the snippet below.

{{FORM_BUILDER}}

## Link straight to WriteToThem {#link}

The simplest way to send people to WriteToThem is a plain link. Pick a representative type below to build one:

{{LINK_BUILDER}}

If you already know something about the person — their postcode, or their name and email, perhaps because they are signed in or you are sending them a newsletter — you can pre-fill the form and skip steps. Every option is just a query-string parameter on `https://www.writetothem.com/`, and you can combine as many as you like.

**Skip the postcode step** by passing a postcode you already hold (URL-encode the space as `+` or `%20`):

<div class="wtt-code">
    <div class="wtt-code__bar"><span class="wtt-code__label">Straight to the results for a postcode</span><button type="button" class="wtt-code__copy" data-target="dg-pc">Copy</button></div>
    <pre><code id="dg-pc">https://www.writetothem.com/?pc=SW1A+1AA</code></pre>
</div>

**Pre-fill their name and contact details, and note where the link came from.** People always see and can edit everything before anything is sent:

<div class="wtt-code">
    <div class="wtt-code__bar"><span class="wtt-code__label">Fully pre-filled</span><button type="button" class="wtt-code__copy" data-target="dg-full">Copy</button></div>
    <pre><code id="dg-full">https://www.writetothem.com/?pc=SW1A+1AA&amp;a=westminstermp&amp;writer_name=Ann+Example&amp;writer_email=ann@example.org&amp;fyr_extref=https://www.example.org/</code></pre>
</div>

**Skip the “What is your message about?” question** by saying up front what kind of message it is with `message_type`. Its values are `casework` (help with a personal problem), `campaigning` (about a policy or campaign) or `other`:

<div class="wtt-code">
    <div class="wtt-code__bar"><span class="wtt-code__label">Straight to writing a campaign message</span><button type="button" class="wtt-code__copy" data-target="dg-mtype">Copy</button></div>
    <pre><code id="dg-mtype">https://www.writetothem.com/?pc=SW1A+1AA&amp;a=westminstermp&amp;message_type=campaigning</code></pre>
</div>

The parameters you can pass:

- `pc` — postcode; sends people straight past the postcode search
- `a` — representative type filter (see the dropdown above for values)
- `fyr_extref` — the web address of the page you’re linking from, so we can see where messages came from
- `message_type` — `casework`, `campaigning` or `other`; skips the “What is your message about?” question
- `writer_name`
- `writer_address1`
- `writer_address2`
- `writer_town`
- `writer_county`
- `writer_email`
- `writer_phone`

Remember to URL-encode each value. For the Welsh-language site, use `cy.writetothem.com` in place of `www.writetothem.com`.
