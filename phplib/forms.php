<?php
/*
 * HTML forms stuff.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: chris@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: forms.php,v 1.1 2004-10-15 16:35:47 francis Exp $
 * 
 */

require_once "HTML/QuickForm.php";
require_once "HTML/QuickForm/Renderer/Default.php";
// require_once "HTML/QuickForm/Renderer/QuickHtml.php";

function forms_custom_renderer() {
    $renderer =& new HTML_QuickForm_Renderer_Default();
    $renderer->setElementTemplate("
            <TD CLASS=\"small<!-- BEGIN required -->b<!-- END required -->\">{label}</TD>
            <TD><FONT CLASS=\"form\"> 
                {element} 
                <!-- BEGIN error --><span style=\"color: #ff0000\">{error}</span><!-- END error --> 
            </FONT> </TD>
    ");
    $renderer->setFormTemplate("
        <form{attributes}>
        {content}
        </form>");
    $renderer->setRequiredNoteTemplate("");
    // Not sure what this is for - just set to default for now:
    $renderer->setHeaderTemplate("
        <tr>
            <td style=\"white-space: nowrap; background-color: #CCCCCC;\" align=\"left\" valign=\"top\" colspan=\"2\"><b>{header}</b></td>
        </tr>");
    return $renderer;
}

?>
