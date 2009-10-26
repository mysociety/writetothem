<?php
/*
 * Functions for Louder cobrand
 * For function notes see cobrand.php
 * 
 * Copyright (c) 2009 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: utils.php,v 1.1 2009-10-26 11:28:00 matthew Exp $
 * 
 */

Class Louder {

    function factory() {
        return new Louder();
    }

    function post_letter_send($values) {
        return false; # Currently unknown but will be here
        header("Location: http://example.org/foobar?id=" . $values['cocode']);
        exit;
    }

}

