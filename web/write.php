<?
/*
 * Page where they enter details, write their message, and preview it
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: write.php,v 1.15 2004-10-20 10:44:40 francis Exp $
 * 
 */

$fyr_title = "Now Write Your Fax To X MP for X";

require_once "../phplib/forms.php";
require_once 'HTML/QuickForm/Controller.php';
require_once 'HTML/QuickForm/Action.php';
require_once 'HTML/QuickForm/Rule.php';
require_once 'HTML/QuickForm/Action/Next.php';
require_once 'HTML/QuickForm/Action/Display.php';

include_once "../conf/config.php";
include_once "../phplib/queue.php";
include_once "../../phplib/mapit.php";
include_once "../../phplib/votingarea.php";
include_once "../../phplib/dadem.php";
include_once "../../phplib/utility.php";

// Start the session
session_start();
/*if (get_http_var('session_fyr')) {
    $_SESSION = unserialize(get_http_var('session_fyr'));
}*/

function default_body_text() {
        global $fyr_representative;
        return 'Dear ' .  $fyr_representative['name'] . ',';
}

class RuleAlteredBodyText extends HTML_QuickForm_Rule {
    function validate($value, $options) {
        return $value != default_body_text();
    }
}

// Class representing form they enter message of letter in
class PageWrite extends HTML_QuickForm_Page
{
    function buildForm()
    {
        global $fyr_postcode, $fyr_who;

        $this->_formBuilt = true;

        global $fyr_representative, $fyr_voting_area, $fyr_date;
        // TODO: CSS this:
        $stuff_on_left = <<<END
                <B>Now Write Your Fax:</B><BR><BR>
                <B>${fyr_voting_area['rep_prefix']}
                ${fyr_representative['name']}
                ${fyr_voting_area['rep_suffix']}
                <BR>${fyr_voting_area['name']}
                <BR><BR>$fyr_date
END;

        // special formatting for letter-like code, TODO: how do this // properly with QuickHtml?
        $this->addElement("html", "<tr><td valign=top>$stuff_on_left</td><td align=right>\n<table>"); // CSSify

        $this->addElement('text', 'writer_name', "your name:", array('size' => 20, 'maxlength' => 255));
        $this->addRule('writer_name', 'Please enter your name', 'required', null, null);
        $this->applyFilter('writer_name', 'trim');

        $this->addElement('text', 'writer_address1', "address 1:", array('size' => 20, 'maxlength' => 255));
        $this->addRule('writer_address1', 'Please enter your address', 'required', null, null);
        $this->applyFilter('writer_address1', 'trim');

        $this->addElement('text', 'writer_address2', "address 2:", array('size' => 20, 'maxlength' => 255));
        $this->applyFilter('writer_address2', 'trim');

        $this->addElement('text', 'writer_town', "town:", array('size' => 20, 'maxlength' => 255));
        $this->addRule('writer_town', 'Please enter your town', 'required', null, null);
        $this->applyFilter('writer_town', 'trim');

        $this->addElement('static', 'staticpc', 'postcode:', $fyr_postcode);

        $this->addElement('text', 'writer_email', "email:", array('size' => 20, 'maxlength' => 255));
        $this->addRule('writer_email', 'Please enter your email', 'required', null, null);
        $this->addRule('writer_email', 'Choose a valid email address', 'email', null, null);
        $this->applyFilter('writer_email', 'trim');

        $this->addElement('text', 'writer_phone', "phone:", array('size' => 20, 'maxlength' => 255));
        $this->applyFilter('writer_phone', 'trim');

        // special formatting for letter-like code, TODO: how do this // properly with QuickHtml?
        $this->addElement("html", "</table>\n</td></tr>"); // CSSify

        $this->addElement('textarea', 'body', null, array('rows' => 15, 'cols' => 62, 'maxlength' => 5000));
        $this->addRule('body', 'Please enter your message', 'required', null, null);
        $this->addRule('body', 'Please enter your message', new RuleAlteredBodyText(), null, null);
        $this->applyFilter('body', 'convert_to_unix_newlines');

        $this->addElement('hidden', 'pc', $fyr_postcode);
        $this->addElement('hidden', 'who', $fyr_who);

        $buttons[0] =& HTML_QuickForm::createElement('static', 'static1', null, 
                "<b>Ready? Press the \"Preview\" button to continue --></b>"); // TODO: remove <b>  from here
        $buttons[1] =& HTML_QuickForm::createElement('submit', $this->getButtonName('next'), 'preview your Fax >>');
        $this->addGroup($buttons, 'previewStuff', '', '&nbsp;', false);

    }
}

class PagePreview extends HTML_QuickForm_Page
{
    function buildForm()
    {
        $this->_formBuilt = true;

        $buttons[0] =& HTML_QuickForm::createElement('submit', $this->getButtonName('back'), '<< edit this Fax');
        $buttons[1] =& HTML_QuickForm::createElement('submit', $this->getButtonName('next'), 'Continue >>');
        $this->addGroup($buttons, 'buttons', '', '&nbsp;', false);
    }
}

class ActionDisplayFancy extends HTML_QuickForm_Action_Display
{
    function _renderForm(&$page)
    {
        // $renderer =& $page->defaultRenderer();
        $renderer = new HTML_QuickForm_Renderer_mySociety();
        $renderer->setGroupTemplate('<TR><TD ALIGN=right colspan=2> {content} </TD></TR>', 'previewStuff'); // TODO CSS this
        $renderer->setElementTemplate('{element}', 'previewStuff');
        $renderer->setElementTemplate('<TD colspan=2> 
        {element} 
        <!-- BEGIN error --><span style="color: #ff0000"><br>{error}</span><!-- END error --> 
        </TD>', 'body');
/*        $page->addElement('hidden', 'session_fyr', serialize($_SESSION));
        print "<p>Redir forward in hidden form:<pre>";
        var_dump($_SESSION);
        print "</pre>";*/
        $page->accept($renderer);

        global $fyr_form, $fyr_values;
        $fyr_values = $page->controller->exportValues();
        $fyr_values['signature'] = sha1($fyr_values['email']);
        debug("FRONTEND", "Form values:", $fyr_values);

       // Make HTML
        $fyr_form = $renderer->toHtml();

        $pageName =  $page->getAttribute('id');
        if ($pageName == "writeForm") {
            include "templates/write-write.html";
        } else { // previewForm
            // Generate preview
            global $fyr_preview, $fyr_representative, $fyr_voting_area, $fyr_date;
            ob_start();
            include "templates/fax-content.html";
            $fyr_preview = ob_get_contents();
            ob_end_clean();
            include "templates/write-preview.html";
        }
    }
}

class ActionProcess extends HTML_QuickForm_Action {
    function perform(&$page, $actionName) {
        global $fyr_values;
        $fyr_values = $page->controller->exportValues();
        $msgid = msg_create();

        $address = 
            $fyr_values['writer_address1'] . "\n" .
            $fyr_values['writer_address2'] . "\n" .
            $fyr_values['writer_town'] . "\n" .
            $fyr_values['pc'] . "\n";
        $address = str_replace("\n\n", "\n", $address);
        $success = msg_write($msgid, 
                array( 
                'name' => $fyr_values['writer_name'],
                'email' => $fyr_values['writer_email'], 
                'address' => $address,
                'phone' => $fyr_values['writer_phone'], 
                ),
                $fyr_values['who'], $fyr_values['body']);
    
        global $fyr_representative, $fyr_voting_area, $fyr_date;
        if ($success) {
            include "templates/write-checkemail.html";
        } else {
            $fyr_error_message = "Failed to queue the message";  // TODO improve this error message
            include "templates/generalerror.html";
        }
    }
}

$pageWrite =& new PageWrite('writeForm');
$pagePreview =& new PagePreview('previewForm');

$controller =& new HTML_QuickForm_Controller('WriteFax');
$controller->addPage($pageWrite);
$controller->addPage($pagePreview);
$controller->addAction('process', new ActionProcess());
$controller->addAction('display', new ActionDisplayFancy());

// transfer data from previous forms
$fyr_postcode = strtoupper(trim(get_http_var('pc')));
$fyr_who = get_http_var('who');
$data =& $controller->container();
if ($fyr_postcode)
    $data['values']['writeForm']['pc'] = $fyr_postcode;
else
    $fyr_postcode = $controller->exportValue('writeForm', 'pc');
if ($fyr_who)
    $data['values']['writeForm']['who'] = $fyr_who;
else
    $fyr_who = $controller->exportValue('writeForm', 'who');

// Information specific to this representative
debug("FRONTEND", "Representative $fyr_who");
$fyr_representative = dadem_get_representative_info($fyr_who);
if ($fyr_error_message = dadem_get_error($fyr_representative)) {
    include "templates/generalerror.html";
    exit;
}
// The voting area is the ward/division. e.g. West Chesterton Electoral Division
$fyr_voting_area = mapit_get_voting_area_info($fyr_representative['voting_area']);
if ($fyr_error_message = mapit_get_error($fyr_voting_area)) {
    include "templates/generalerror.html";
    exit;
}

$fyr_date = strftime('%A %e %B %Y');

$controller->setDefaults(array('body' => default_body_text()));

$controller->run();

?>
