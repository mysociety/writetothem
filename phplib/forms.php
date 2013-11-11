<?php
/*
 * HTML forms stuff.
 *
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: forms.php,v 1.15 2009-10-15 13:03:10 louise Exp $
 *
 */

require_once "../commonlib/phplib/HTML/QuickForm.php";
require_once "../commonlib/phplib/HTML/QuickForm/Rule.php";
require_once "../commonlib/phplib/HTML/QuickForm/Renderer/Default.php";

// Returns an array with all POST and GET variables, and any hidden form
// variables which were saved with function add_all_variables_hidden
// below.
function get_all_variables() {
    debug("SERIALIZE", "_GET", $_GET);
    debug("SERIALIZE", "_POST", $_POST);

    if (isset($_GET['body'])) unset($_GET['body']);

    // All post and get variables, get have priority
    $variables = array_merge($_POST, $_GET);

    // Look for hidden serialised ones
    if (array_key_exists('mysociety_serialized_variables', $variables)) {
        $set_vars = unserialize(base64_decode($variables['mysociety_serialized_variables']));
        debug("SERIALIZE", "mysociety_serialized_variables", $set_vars);
        $variables = array_merge($set_vars, $variables);
    }

    unset($variables['mysociety_serialized_variables']);
    return $variables;
}

// Saves all variables in one simple form variable.
function add_all_variables_hidden(&$form, $variables, $options) {
    debug("SERIALIZE", "Writing hidden vars:", $variables);
    $ser_vars = base64_encode(serialize($variables));
    $html_hidden = "<input name=\"mysociety_serialized_variables\" type=\"hidden\" value=\"$ser_vars\">";
    // I tried using a 'hidden' element here, but it refuses to change
    // the value of the contents, just uses the one in _POST rather
    // than the new one.
    $element = $html_hidden;
    $form->addElement('html', $element);
}

# Matthew's non HTML_QuickForm version of the above
function add_all_variables_hidden_nonQF($variables) {
    debug("SERIALIZE", "Writing hidden vars:", $variables);
    $ser_vars = base64_encode(serialize($variables));
    $html_hidden = '<input name="mysociety_serialized_variables" type="hidden" value="' . $ser_vars . '">';
    return $html_hidden;
}

class HTML_QuickForm_Renderer_mySociety extends HTML_QuickForm_Renderer_Default {

    function factory() {
        return new HTML_QuickFormRenderer_mySociety();
    }

    function HTML_QuickForm_Renderer_mySociety() {
        $this->HTML_QuickForm_Renderer_Default();
        $this->setFormTemplate('
            <form{attributes}>
            {content}
            </form>');
        $this->setElementTemplate('
                <!-- BEGIN error -->
                <div class="error-message">{error}</div>
                <div class="error-element">
                <!-- END error -->

                <p><label>{label} {element}</label></p>

                <!-- BEGIN error -->
                </div>
                <!-- END error -->
        ');

        $this->setRequiredNoteTemplate("");
        // Not sure what this is for - just set to default for now:
        $this->setHeaderTemplate('{header}');
    }
}

?>
