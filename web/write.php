<?
/*
 * Page where they enter details, write their message, and preview it
 * 
 * Copyright (c) 2004 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: write.php,v 1.12 2004-10-18 18:34:38 francis Exp $
 * 
 */

$fyr_title = "Now Write Your Fax To X MP for X";

require_once "../phplib/forms.php";
require_once 'HTML/QuickForm/Controller.php';
require_once 'HTML/QuickForm/Action.php';
require_once 'HTML/QuickForm/Action/Next.php';
require_once 'HTML/QuickForm/Action/Display.php';

include_once "../conf/config.php";
include_once "../../phplib/mapit.php";
include_once "../../phplib/votingarea.php";
include_once "../../phplib/dadem.php";
include_once "../../phplib/utility.php";

// Start the session
session_start();

// Class representing form they enter message of letter in
class PageWrite extends HTML_QuickForm_Page
{
    function buildForm()
    {
        $this->_formBuilt = true;

        // $write_form = new HTML_QuickForm('writeForm', 'post', 'write.php');

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

        $this->addElement('text', 'writer_email', "email:", array('size' => 20, 'maxlength' => 255));
        $this->addRule('writer_email', 'Please enter your email', 'required', null, null);
        $this->addRule('writer_email', 'Choose a valid email address', 'email', null, null);
        $this->applyFilter('writer_email', 'trim');

        $this->addElement('text', 'writer_phone', "phone:", array('size' => 20, 'maxlength' => 255));
        $this->applyFilter('writer_phone', 'trim');

        $this->addElement('textarea', 'body', 'write your Fax:', array('rows' => 15, 'cols' => 62, 'maxlenght' => 5000));
        $this->addRule('body', 'Please enter your message', 'required', null, null);

        global $fyr_postcode, $fyr_who;
        $this->addElement('hidden', 'pc', $fyr_postcode);
        $this->addElement('hidden', 'who', $fyr_who);

        $buttons[0] =& HTML_QuickForm::createElement('static', 'static1', null, 
                "<b>Ready? Press the \"Preview\" button to continue --></b>"); // TODO: remove <b>  from here
        $buttons[1] =& HTML_QuickForm::createElement('submit', $this->getButtonName('next'), 'preview your Fax >>');
        $this->addGroup($buttons, 'previewstuff', '', '&nbsp', false);
    }
}

class PagePreview extends HTML_QuickForm_Page
{
    function buildForm()
    {
        $this->_formBuilt = true;

        $buttons[0] =& HTML_QuickForm::createElement('submit', $this->getButtonName('back'), '<< edit this Fax');
        $buttons[1] =& HTML_QuickForm::createElement('submit', $this->getButtonName('next'), 'Continue >>');
        $this->addGroup($buttons, 'buttons', '', '&nbsp', false);
    }
}

class ActionDisplayFancy extends HTML_QuickForm_Action_Display
{
    function _renderForm(&$page)
    {
        // $renderer =& $page->defaultRenderer();
        $renderer = new HTML_QuickForm_Renderer_mySociety();
        $page->accept($renderer);

        global $fyr_form, $fyr_values;
        $fyr_values =  $page->controller->exportValues();
        $fyr_values['signature'] = sha1($fyr_values['email']);
        debug("FRONTEND", "Form values:", $fyr_values);

        // Find out which representative 
        $rep_id = $fyr_values['who'];
        debug("FRONTEND", "Representative $rep_id");

        // Information specific to this representative
        global $fyr_representative;
        $fyr_representative = dadem_get_representative_info($rep_id);
        if ($fyr_error_message = dadem_get_error($fyr_representative)) {
            include "templates/generalerror.html";
            exit;
        }

        // The voting area is the ward/division. e.g. West Chesterton Electoral Division
        global $fyr_voting_area;
        $fyr_voting_area = mapit_get_voting_area_info($fyr_representative['voting_area']);
        if ($fyr_error_message = mapit_get_error($fyr_voting_area)) {
            include "templates/generalerror.html";
            exit;
        }

        $fyr_form = $renderer->toHtml();

        $pageName =  $page->getAttribute('id');
        if ($pageName == "writeForm") {
            include "templates/write-write.html";
        } else { // previewForm
            // Generate preview
            global $fyr_preview;
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
        echo "Submit successful!  This bit will queue the fax<br>\n<pre>\n";
        var_dump($page->controller->exportValues());
        echo "\n</pre>\n";
    }
}

$pageWrite =& new PageWrite('writeForm');
$pagePreview =& new PagePreview('previewForm');

$controller =& new HTML_QuickForm_Controller('StateMachine');
$controller->addPage($pageWrite);
$controller->addPage($pagePreview);

// transfer data from previous forms
$fyr_postcode = get_http_var('pc');
$fyr_who = get_http_var('who');
$data =& $controller->container();
if ($fyr_postcode)
    $data['values']['writeForm']['pc'] = $fyr_postcode;
if ($fyr_who)
    $data['values']['writeForm']['who'] = $fyr_who;

$controller->addAction('process', new ActionProcess());
$controller->addAction('display', new ActionDisplayFancy());

$controller->run();

exit;

// The elected body is the overall entity. e.g. Cambridgeshire County Council.
// $eb_type = $va_inside[$va_type];
// $eb_typename = $va_name[$eb_type];
// $eb_specificname = $voting_areas[$eb_type][1];



?>

