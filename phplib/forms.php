<?php
/*
 * HTML forms stuff.
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: forms.php,v 1.4 2004-10-19 16:46:08 francis Exp $
 * 
 */

require_once "HTML/QuickForm.php";
require_once "HTML/QuickForm/Renderer/Default.php";
// require_once "HTML/QuickForm/Renderer/QuickHtml.php";

class HTML_QuickForm_Renderer_mySociety extends HTML_QuickForm_Renderer_Default {

    function HTML_QuickForm_Renderer_mySociety() {
        // TODO: Properly CSS this
        $this->HTML_QuickForm_Renderer_Default();
        $this->setFormTemplate('
            <form{attributes}>
            <table border="0">
            {content}
            </table>
            </form>');
        $this->setElementTemplate('
                <TR>  
                <TD CLASS="small<!-- BEGIN required -->b<!-- END required -->">{label}</TD>
                <TD>
                    {element} 
                    <!-- BEGIN error --><span style="color: #ff0000"><br>{error}</span><!-- END error --> 
                </TD>
                </TR>
        ');
        $this->setRequiredNoteTemplate("");
        // Not sure what this is for - just set to default for now:
        $this->setHeaderTemplate('
            <tr>
                <td style="white-space: nowrap; background-color: #CCCCCC;" align="left" valign="top" colspan="2"><b>{header}</b></td>
            </tr>');
    }
}

?>
