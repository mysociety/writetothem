<?php
/*
 * Functions for Louder cobrand
 * For function notes see cobrand.php
 * 
 * Copyright (c) 2009 UK Citizens Online Democracy. All rights reserved.
 * Email: matthew@mysociety.org; WWW: http://www.mysociety.org
 *
 * $Id: utils.php,v 1.2 2009-10-26 17:17:44 matthew Exp $
 * 
 */

Class Louder {

    function factory() {
        return new Louder();
    }

    function post_letter_send($values) {
        header("Location: http://www.louder.org.uk/wttsuccess.php?id=" . $values['cocode']);
        exit;
    }

}

