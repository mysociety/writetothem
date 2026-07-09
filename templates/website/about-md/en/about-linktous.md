Wouldn’t it be great if you could put a little box on your website which would let your users get in touch with their elected representatives? Well, you can!

[TOC]

Before you do so, make sure you read the [guidelines for campaigners](about-guidelines).  You must not tell your users to send pre-written, identical letters &mdash; they are against our conditions of use and we will block them.

## Envelope-style box {#envelope}

<div>
<!-- WriteToThem envelope box, start -->
<script type="text/javascript">
   function clearDefault(el) {
     if (el.defaultValue==el.value) el.value = "";
   }
   function fillDefault(el) {
     if (el.value=="") el.value = "Your Postcode";
   }
</script>
<div style="width:167px;height:120px;
   background: url(https://www.writetothem.com/envelope_bg.gif) no-repeat 0 0;
   font:9pt/11pt arial,helvetica,sans-serif;position:relative;">
   <form method="get" action="https://www.writetothem.com/">
       <div style="padding:55px 0 0 20px;">
           <label for="pc" style="display:none;">
               Contact Your Politician
           </label>
           <input type="text" value="Your Postcode" name="pc" size="13"
               style="width:90px;vertical-align:bottom;
                   font:8pt/11pt arial,helvetica,sans-serif;color:#666;"
               onfocus="clearDefault(this)" onblur="fillDefault(this)" />
           <input type="image" value="Go" style="vertical-align:bottom;"
               src="https://www.writetothem.com/envelope_arrow.gif" />
       </div>
   </form>
   <a href="https://www.writetothem.com/" title="Visit writetothem.com"
   style="display:block;position:absolute;bottom:0;width:100%;overflow:hidden;
   text-indent:-1234em;height:30px;bottom:0;">writetothem.com</a>
</div>
<!-- WriteToThem envelope box, end-->
</div>

To add the envelope-style postcode box to your site, just copy the HTML code below into your own webpage.

<pre><code>&lt;!-- WriteToThem envelope box, start --&gt;
&lt;script type=&quot;text/javascript&quot;&gt;
   function clearDefault(el) {
     if (el.defaultValue==el.value) el.value = &quot;&quot;;
   }
   function fillDefault(el) {
     if (el.value==&quot;&quot;) el.value = &quot;Your Postcode&quot;;
   }
&lt;/script&gt;
&lt;div style=&quot;width:167px;height:120px;
   background: url(https://www.writetothem.com/envelope_bg.gif) no-repeat 0 0;
   font:9pt/11pt arial,helvetica,sans-serif;position:relative;&quot;&gt;
   &lt;form method=&quot;get&quot; action=&quot;https://www.writetothem.com/&quot;&gt;
       &lt;div style=&quot;padding:55px 0 0 20px;&quot;&gt;
           &lt;label for=&quot;pc&quot; style=&quot;display:none;&quot;&gt;
               Contact Your Politician
           &lt;/label&gt;
           &lt;input type=&quot;text&quot; value=&quot;Your Postcode&quot; name=&quot;pc&quot; size=&quot;13&quot;
               style=&quot;width:90px;vertical-align:bottom;
                   font:8pt/11pt arial,helvetica,sans-serif;color:#666;&quot;
               onfocus=&quot;clearDefault(this)&quot; onblur=&quot;fillDefault(this)&quot; /&gt;
           &lt;input type=&quot;image&quot; value=&quot;Go&quot; style=&quot;vertical-align:bottom;&quot;
               src=&quot;https://www.writetothem.com/envelope_arrow.gif&quot; /&gt;
       &lt;/div&gt;
   &lt;/form&gt;
   &lt;a href=&quot;https://www.writetothem.com/&quot; title=&quot;Visit writetothem.com&quot;
   style=&quot;display:block;position:absolute;bottom:0;width:100%;overflow:hidden;
   text-indent:-1234em;height:30px;bottom:0;&quot;&gt;writetothem.com&lt;/a&gt;
&lt;/div&gt;
&lt;!-- WriteToThem envelope box, end--&gt;</code></pre>

## Conventional style box {#conventional}

<div>
<!-- WriteToThem conventional box, start -->
<div style="padding:0; border:1px solid #999999; width:14em; margin: 0;
           background-color:#FFE88C; font: 83% Helvetica, Arial, sans-serif;">
   <form method="get" action="https://www.writetothem.com/" style="text-align:center">
       <div style="background-color:#D0BF69;padding:3px; color:#2B3260;">
               <strong>Contact Your Politician</strong>
       </div>
       <div style="margin:0.5em; color:#2B3260; background-color: #ffe88c;">
           <div style="margin-bottom:0.25em;">Enter your Postcode below:</div>
           <input type="text" name="pc" size="13">
           <input type="submit" value="Go">
       </div>
   </form>
</div>
<!-- WriteToThem conventional box, end-->
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

If you only want to encourage people to write to one kind of representative - for example, only to their MP, link to WriteToThem using an address like this:

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

So, for example, the link below would only show representatives of district type councils.  For people entering postcodes in unitary areas, it would say there were no representatives of that type and offer to show them all their representatives:

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
