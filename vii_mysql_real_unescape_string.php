<?php

function vii_count_mysql_real_escaped_chars($str){

    $search = array(
        '\0',
        '\\\'',// all the variations of
        '\\"',// php/regex backslash escaping
        '\n',
        '\r',
        '\Z',
        '\\\\',// yeah, here we have
    );

    $replace = array(
        chr(0),// \0,
        chr(39),// ',
        chr(34),// ",
        chr(10),// \n,
        chr(13),// \r,
        chr(26),// \Z,
        chr(92),// \,
    );

    str_replace($search, $replace, $str, $cnt);

    return $cnt;

}

function vii_mysql_real_unescape_string($str, &$cnt = null){

    $search = array(
        '\0',
        '\\\'',// all the variations of
        '\\"',// php/regex backslash escaping
        '\n',
        '\r',
        '\Z',
        '\\\\',// yeah, here we have
    );

    $replace = array(
        chr(0),// \0,
        chr(39),// ',
        chr(34),// ",
        chr(10),// \n,
        chr(13),// \r,
        chr(26),// \Z,
        chr(92),// \,
    );

    return str_replace($search, $replace, $str, $cnt);

}

function vii_mysql_real_escape_string($str){

    $replace = array(
        '\0',
        '\\\'',// all the variations of
        '\\"',// php/regex backslash escaping
        '\n',
        '\r',
        '\Z',
        '\\\\',// yeah, here we have
    );

    $search = array(
        chr(0),// \0,
        chr(39),// ',
        chr(34),// ",
        chr(10),// \n,
        chr(13),// \r,
        chr(26),// \Z,
        chr(92),// \,
    );

    return str_replace($search, $replace, $str, $cnt);

}
