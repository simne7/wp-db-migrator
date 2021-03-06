#!/usr/bin/env php

<?php
// composer dependency management
require_once './vendor/autoload.php';

use PEAR2\Console\CommandLine as Console_CommandLine;

class ADT {

    /************************************************************************************************
     * CLASS FIELDS																				    *
     ************************************************************************************************/

    protected $xmlfile;
    protected $parser;
    protected $options;
    protected $args;
    protected $command;
    protected $command_name;

    protected $inifile;
    protected $fields;

    /************************************************************************************************
     * METHODS																						*
     ************************************************************************************************/

    /** constructor
     *
     */
    function __construct() {
        // set path to xml options file
        $this -> xmlfile = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'adt.xml';
        // set path to config file
        $this -> inifile = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.ini';
        // parse ini file into properties
        $this -> fields = parse_ini_file($this -> inifile);
        // parse wp_config.php
        $this -> parse_wp_config();
        // construct parser from xml
        $this -> parser = Console_CommandLine::fromXmlFile($this -> xmlfile);

        // run parser
        try {
            // $result[] = array(options[], args[], command_name, command)
            $result = $this -> parser -> parse();
            $this -> options = $result -> options;
            $this -> args = $result -> args;
            $this -> command_name = $result -> command_name;
            $this -> command = $result -> command;
        } catch (Exception $exc) {
            $this -> parser -> displayError($exc -> getMessage());
        }
        // DEBUG
        if ($result -> options['debug'] == TRUE) {
            var_dump('RESULT: ', $result);
            var_dump('FIELDS: ', $this -> fields);
        }
        $this -> select_mode();
    }

    /** determines which part of the script to run
     *
     */
    function select_mode() {
        switch ($this->command_name) {
            case 'dump' :
                $this -> create_dump($this -> command -> options['output']);
                return;
            case 'import' :
                $this -> import_dump();
                return;
            case 'localize' :
                $this -> toggle_remote('local', $this -> command -> options['output']);
                return;
            case 'remotize' :
                $this -> toggle_remote('remote', $this -> command -> options['output']);
                return;
            case 'replace' :
                $this -> replace($this -> fields['pattern'], $this -> fields['replacement'], false, true);
            default :
                echo 'This is the ADT Tool. Type ' . $this -> parser -> name . ' -h for usage information.';
                return;
        }
    }

    /**
     * count escaped chars
     * @param  string $str The haystack
     * @return int         Number of mysql real escaped strings
     */
    function count_mysql_real_escaped_chars($str){

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

    /** get database credentials from a wp-config file
     *
     */
    function parse_wp_config() {
        // TODO generalize this function -> add parser class that returns
        // standardized array
        // find all lines where DB fields are defined
        $pattern = "/.+(DB_.+)\'.*\'(.*)\'/";
        preg_match_all($pattern, file_get_contents($this -> fields['wp_config_path']), $matches);
        // transform them into nice key => value pairs like DB_NAME =>
        // 'wordpress', add
        // to fields
        $this -> fields = array_merge($this -> fields, array_combine($matches[1], $matches[2]));
    }

    /** get contents of a .gz file as string
     *
     */
    function gzfile_get_contents($filename, $use_include_path = 0) {
        //File does not exist
        if (!@file_exists($filename)) {
            return false;
        }

        //Read and implode array to produce a one line string
        $data = gzfile($filename, $use_include_path);
        $data = implode($data);
        return $data;
    }

    /** compress file to file.gz
     *
     */
    function gzCompressFile($source, $dest = '', $level = 9) {
        if(empty($dest)) {
            $dest = $source . '.gz';
        }
        $mode = 'wb' . $level;
        $error = false;
        if ($fp_out = gzopen($dest, $mode)) {
            if ($fp_in = fopen($source, 'rb')) {
                while (!feof($fp_in))
                    gzwrite($fp_out, fread($fp_in, 1024 * 512));
                fclose($fp_in);
            } else {
                $error = true;
            }
            gzclose($fp_out);
        } else {
            $error = true;
        }
        if ($error)
            return false;
        else
            return $dest;
    }

    /** inflate a gzipped file
     *
     */
    function gzDecompressFile($srcName, $dstName) {
        $sfp = gzopen($srcName, "rb");
        $fp = fopen($dstName, "w");

        while (!gzeof($sfp)) {
            $string = gzread($sfp, 4096);
            fwrite($fp, $string, strlen($string));
        }
        gzclose($sfp);
        fclose($fp);
    }

    /** Create wordpress database dump
     *
     */
    function create_dump($output = false) {
        // get current date and time
        $date = date("y-m-d_H-i-s");

        if ($output == false) {
            // no filename given, name it after current date
            $output = "dump_" . $date . ".sql";
        }
        // check if output file already exists
        if(file_exists($output)) {
            echo "Error: File '$output' already exists. Please move it or specify a different filename.";
            return false;
        }
        if ($this -> options['verbose']) {
            echo "Taking a dump to " . $output . ".gz ...\n";
        }
        // @formatter:off
		// dump database
		// --single-transaction prevents error if user may not LOCK TABLES
		exec("mysqldump --add-locks=false --default-character-set=utf8 --single-transaction --user="
			. $this -> fields['DB_USER'] . " --password='"
			. $this -> fields['DB_PASSWORD']
			. "' --host=" . $this -> fields['DB_HOST'] . " "
			. $this -> fields['DB_NAME'] . " > \"" . $output.'"');
		// @formatter:on
        if ($this -> options['verbose']) {
            echo "Done.\n";
            echo "Compressing ...\n";
        }
        $this -> gzCompressFile($output);
        // gzip the dump
        if ($this -> options['verbose']) {
            echo "Done.\n";
        }
        // delete unzipped file
        unlink($output);
    }

    /** Import sql dump into database
     *
     */
    function import_dump() {

        // do a backup
        if ($this -> options['verbose']) {
            echo "Creating Backup...\n";
        }
        // TODO make the result here *.gz.bak, not *.bak.gz
        $filename = 'dump_' . date("y-m-d_H-i") . '.sql.bak';
        $this -> create_dump($filename);

        // import dump
        if ($this -> options['verbose']) {
            echo "Done.\n";
            echo "Importing SQL...\n";
        }

        $query = gzfile($this -> command -> args['file']);
        $tmpfile = tempnam('./', 'tmp');
        file_put_contents($tmpfile, $query);
        unset($query);

        // @formatter:off
		$command = "mysql --default-character-set=utf8 -h " . $this -> fields['DB_HOST'] . " -D " . $this -> fields['DB_NAME'] . " -u "
			. $this -> fields['DB_USER'] . " --password='" . $this -> fields['DB_PASSWORD'] . "' < \"" . $tmpfile . "\"";
		// @formatter:on
        exec($command);
        unlink($tmpfile);

        if ($this -> options['verbose']) {
            echo "Done.\n";
        }
    }

    /**Replace a string in the database file with another
     *
     */
    function replace($pattern, $replacement, $output, $serialized = false) {
        // TODO Test with simple and complex patterns
        if (!(isset($pattern) && isset($replacement))) {
            // TODO throw error
            // one or both arguments unset
            return false;
        }

        if (!$output) {
            // no output file given -> edit in place
            $output = $this -> command -> args['file'];
        }
        if (file_exists($output)) {
            // do backup
            $backup = $this -> command -> args['file'] . '.bak';
            if ($this -> options['verbose']) {
                echo "Writing backup to '$backup'...\n";
            }
            if(file_exists($backup)) {
                echo "Error: Backup file '$backup' already exists. Please move it first.";
                return false;
            }
            copy($this -> command -> args['file'], $backup);
            if ($this -> options['verbose']) {
                echo "Done.\n";
            }
        }
        $info = pathinfo($output);
        if($info['extension'] == 'gz') {
            // set output to uncompressed filename
            $uncompressed = $info['filename'];
            $compressed = $output;
        } else {
            $uncompressed = $output;
            $compressed = $output.'.gz';
        }
        // get dump
        $haystack = $this -> gzfile_get_contents($this -> command -> args['file']);
        // set up pattern /i - ignore case, /m - multiline
        $regex = '#' . $pattern . '#im';
        if ($this -> options['verbose']) {
            echo "Replacing '$pattern' with '$replacement'...\n";
        }

        if ($serialized) {
            // first find all serialized string occurences and store the capture offset of all matches
            preg_match_all('#s:([0-9]+):(\\\\?")#is', $haystack, $matches, PREG_OFFSET_CAPTURE);

            $delta = 0;
            foreach($matches[2] as $idx => $m) {

                // serialized string info
                $s_length = $matches[1][$idx][0];//first regex group
                $s_offset = $matches[1][$idx][1] - 2;// capture offset minus "s:"

                // serialized value info
                $v_offset = $m[1] + strlen($m[0]);// capture offset + strlen(\" | ") => just after the "
                $v_length = intval($s_length);//may be too small due to escaped chars
                $v_value = substr($haystack, $v_offset + $delta, $v_length);

                // let's see how many escaped chars we find inside the value
                $v_escaped_chars_cnt = $this->count_mysql_real_escaped_chars($v_value);

                // if escaped chars are found iteratively add chars to $value
                // until no more escaped chars are found (i.e. end of serialized
                // value is found). unfortunately we don't know the number of
                // escaped chars before and all other means like regexes won't
                // work here
                $cnt = $v_escaped_chars_cnt;
                while ($cnt > 0) {
                    // go to end of value and extract as many chars as escaped chars
                    $part = substr($haystack, $v_offset + $delta + $v_length, $cnt);
                    // now add those extracted chars to value
                    $v_value = $v_value . $part;
                    // increase length of value
                    $v_length += $cnt;
                    // count escaped chars in the newly extracted part
                    $cnt = $this->count_mysql_real_escaped_chars($part);
                    // sum up the escaped chars
                    $v_escaped_chars_cnt += $cnt;
                    // and start over until no more escaped chars are found
                    // echo $serialized . " | cnt: $cnt" . PHP_EOL;
                }

                // echo $v_escaped_chars_cnt . " escaped chars found" . PHP_EOL;

                // construct serialized string
                $s = 's:' . $s_length . ':' . $m[0] . $v_value . $m[0] . ';';
                // echo $s . PHP_EOL;

                // construct temp serialiazed string taking into account the increased length
                $s_tmp = 's:' . $v_length . ':"' . $v_value . '";';
                // echo $s_tmp . PHP_EOL;

                // test if unserialize() works
                $us_tmp = unserialize($s_tmp);
                if (false === $us_tmp) {
                    echo $s_tmp . PHP_EOL;
                    continue;
                }

                // search/replace
                // TODO replace with escaped string
                $v2_value = preg_replace($regex, $replacement, $v_value, -1, $cnt);
                // echo $v2_value. " | cnt: $cnt" . PHP_EOL;

                // if we replaced at least one we need to update the haystack
                if ($cnt > 0) {
                    $v2_length = strlen($v2_value);
                    $v2_escaped_chars_cnt = $this->count_mysql_real_escaped_chars($v2_value);

                    // construct a new serialized string using the 'old' quotes
                    $s2_length = $v2_length - $v2_escaped_chars_cnt;
                    $s2 = 's:' . $s2_length . ':' . $m[0] .
                            $v2_value . $m[0] . ';';
                    // echo "new: $s2" . PHP_EOL;

                    $haystack = substr($haystack, 0, $s_offset + $delta) .
                        $s2 .
                        substr($haystack, $s_offset + $delta + strlen($s));

                    $d = strlen($s2) - strlen($s);
                    // echo $d . " delta" . PHP_EOL;
                    $delta += $d;
                }
            }
        }

        // replace strings
        $haystack = preg_replace($regex, $replacement, $haystack);

        // write result to file
        if ($this -> options['verbose']) {
            echo "Writing output to '$compressed'...\n";
        }
        file_put_contents($uncompressed, $haystack);
        if ($this -> options['verbose']) {
            echo "Compressing...\n";
        }
        $this -> gzCompressFile($uncompressed, $compressed);
        unlink($uncompressed);
        // free up space
        unset($haystack);
        if ($this -> options['verbose']) {
            echo "Done.\n";
        }
    }

    /** swap one serialized url for another
     *
     */
    function toggle_remote($target, $output = false) {
        // determine target
        switch ($target) {
            case 'local' :
                $pattern = $this -> fields['remote_host'];
                $replacement = $this -> fields['local_host'];
                break;
            case 'remote' :
                $replacement = $this -> fields['remote_host'];
                $pattern = $this -> fields['local_host'];
                break;
            default :
                //TODO throw error
                return;
        }
        $this -> replace($pattern, $replacement, $output, true);
    }

}

new ADT();
?>
