Want to help people contact their elected representatives from your own website? Add a WriteToThem postcode box with the builder below, or — if you already know someone’s details — link them straight into a pre-filled form from your own code. For example, you could:

- add a “write to your MP” box to a campaign page;
- put a “find your local councillor” box on an MP’s own website, to help people reach the right person;
- send newsletter readers straight to a form to write to their MP.

[TOC]

If you’re running a campaign, please read the [guidelines for campaigners](/about-guidelines) first: you must not ask people to send pre-written, identical letters, as they are against our conditions of use and we will block them.

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

To add the conventional postcode box to your site, copy the HTML code below into your own webpage.

<pre><code>&lt;!-- WriteToThem conventional box, start --&gt;
&lt;div style=&quot;padding:0; border:1px solid #999999; width:14em; margin: 0;
           background-color:#FFE88C; font: 83% Helvetica, Arial, sans-serif;&quot;&gt;
   &lt;form method=&quot;get&quot; action=&quot;https://www.writetothem.com/&quot; style=&quot;text-align:center&quot;&gt;
       &lt;div style=&quot;background-color:#D0BF69;padding:3px; color:#2B3260;&quot;&gt;
               &lt;strong&gt;Contact Your Politician&lt;/strong&gt;
       &lt;/div&gt;
       &lt;div style=&quot;margin:0.5em; color:#2B3260; background-color: #ffe88c;&quot;&gt;
           &lt;div style=&quot;margin-bottom:0.25em;&quot;&gt;Enter your Postcode below:&lt;/div&gt;
           &lt;input type=&quot;text&quot; name=&quot;pc&quot; size=&quot;13&quot;&gt;
           &lt;input type=&quot;submit&quot; value=&quot;Go&quot;&gt;
       &lt;/div&gt;
   &lt;/form&gt;
&lt;/div&gt;
&lt;!-- WriteToThem conventional box, end--&gt;</code></pre>

Feel free to hack around with the design as much as you like. If you have any questions, just [drop us a line](/about-contact).

## Limiting to specific types of representative {#types}

If you only want to encourage people to write to one kind of representative — for example, only to their MP — link to WriteToThem using an address like this:

<pre><code>https://www.writetothem.com/?a=westminstermp</code></pre>

Or if you are using one of the boxes, above, to link to us, add this code to your form. It goes just after the postcode `<input>` field, and before the submit/go button one.

<pre><code>&lt;input type=&quot;hidden&quot; name=&quot;a&quot; value=&quot;westminstermp&quot;&gt;</code></pre>

Possible values for "a" in both cases are:

- `westminstermp` &mdash; just your MP
- `council` &mdash; all local government councillors (in some areas this will return district and county councillors, in others unitary authority councillors)
- `regionalmp` &mdash; members of the Scottish Parliament, Senedd, Northern Ireland Assembly, or London Assembly

There are some more detailed three letter codes that you can use instead. Put these in a list as the value of "a", separated by commas ",".

- District type councils: `DIW` &ndash; district council, `LBW` &ndash; London Borough
- Unitary type councils: `MTW` &ndash; metropolitan district, `UTW` and `UTE` &ndash; Unitary authority, `LGE` &ndash; Local government district (Northern Ireland), `COP` &ndash; Council of the Isles (Scilly)
- County councils: `CED`
- London Assembly: `LAC` &ndash; constituency members, `LAE` &ndash; list members
- The Senedd: `WAC`
- Scottish Parliament: `SPC` &ndash; constituency members, `SPE` &ndash; list members
- Northern Ireland Assembly: `NIE`
- House of Commons: `WMC`

So, for example, the link below would only show representatives of district type councils. For people entering postcodes in unitary areas, it would say there were no representatives of that type and offer to show them all their representatives:

<pre><code>&lt;input type=&quot;hidden&quot; name=&quot;a&quot; value=&quot;DIW,LBW&quot;&gt;</code></pre>

## Pre-filling name and address {#prefill}

If you already know the name, address or email of the person using your website (ie if they are logged in) or reading your newsletter, you can send the values to WriteToThem.

Your supporters will be able to check or alter them before sending.

<pre><code>&lt;input type=&quot;hidden&quot; name=&quot;writer_email&quot; value=&quot;name@email.address&quot;&gt;</code></pre>

The fields available are:

- `writer_name`
- `writer_address1`
- `writer_address2`
- `writer_town`
- `writer_county`
- `writer_email`
- `writer_phone`

## Plugins for WordPress {#plugins}

Philip John (not affiliated to WriteToThem, but working voluntarily) has made a [WordPress plugin](https://wordpress.org/plugins/writetothem/).

It lets you add a WriteToThem form to your WordPress site as a standard WordPress widget - so it’s just drag-and-drop to install.

There is also a much older [WordPress plugin](/WriteToThem.com.zip), written by Richard Pope. To use that, you will need to alter your PHP template.

Please address support queries about these plug-ins to their authors, on the plug-ins’ pages.
