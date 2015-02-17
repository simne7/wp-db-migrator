<?php

function vii_mysql_real_unescape_string($str){
    
    $search = array(
        '\0',
        '\n',
        '\r',
        '\\\\',// yeah, here we have
        '\\\'',// all the variations of
        '\\"',// php/regex backslash escaping
        '\Z'
    ); 
        
    $replace = array(
        chr(0),// \0,
        chr(10),// \n,
        chr(13),// \r,
        chr(92),// \,
        chr(39),// ',
        chr(34),// ",
        chr(26),// \Z,
    );   
    
    return str_replace($search, $replace, $str);
    
}