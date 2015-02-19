<?php

function vii_mysql_real_unescape_string($str){
    
    $search = array(
        '\0',
        '\\\'',// all the variations of
        '\\"',// php/regex backslash escaping
	'\b', // backspace character
        '\n',
        '\r',
	'\t', // tabulator
        '\Z',
        '\\\\',// yeah, here we have
	'\%',
	'\_',
    ); 
        
    $replace = array(
        chr(0),// \0,
        chr(39),// ',
        chr(34),// ",
	chr(8), // \b
        chr(10),// \n,
        chr(13),// \r,
	chr(9), // \t
        chr(26),// \Z,
        chr(92),// \,
	chr(37), // %
	chr(95), // _
    );   
    
    return str_replace($search, $replace, $str);
    
}