#!/usr/bin/env php

<?php
// pear packages
// cli options parsing
require_once 'Console/CommandLine.php';

if (!class_exists('Console_CommandLine')) {
    throw new Exception('Console_CommandLine is not installed. Please call `pear install PHP_UML` from the command line.', 1);
}
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

    /** get database credentials from a wp-config file
     *
     */
    function parse_wp_config() {
        // TODO generalize this function -> add parser class that returns standardized array
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
    function gzCompressFile($source, $level = 9) {
        $dest = $source . '.gz';
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
        $date = date("y-m-d_H-i");

        if ($output == false) {
            // no filename given, name it after current date
            $output = "dump_" . $date . ".sql";
        }
        // check if output file does not exist yet
        $output = $this->get_free_filename($output);
        if ($this -> options['verbose']) {
            echo "Taking a dump to " . $output . ".gz ...\n";
            echo "Dumping ...\n";
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
			. $this -> fields['DB_USER'] . " --password='" . $this -> fields['DB_PASSWORD'] . "' < " . $tmpfile;
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
            // one or both arguments unset
            return false;
        }

        if (!$output) {
            // no output file given -> edit in place
            $output = $this -> command -> args['file'];

            // do backup
            if ($this -> options['verbose']) {
                echo "Creating Backup...\n";
            }
            $filename = $output . '.bak';
            $filename = $this -> get_free_filename($filename);
            copy($output, $filename);
        }
        // get dump
        $haystack = $this -> gzfile_get_contents($output);
        // set up pattern
        $regex = '#' . $pattern . '#im';
        if ($this -> options['verbose']) {
            echo "Replacing '" . $pattern . "' with '" . $replacement . ' using preg_replace($pattern, $replacement)' . "\n";
        }
        // replace strings
        $haystack = preg_replace($regex, $replacement, $haystack);
        if ($serialized) {
            // update serialized strlen
            $haystack = preg_replace_callback('#s:(\\d+)(:\\\\?")(.*?)(\\\\?";)#is', function($matches) {
                $num_newlines = preg_match_all("#\\\\n#", $matches[3], $m);
                return 's:' . (strlen($matches[3]) - $num_newlines) . $matches[2] . $matches[3] . $matches[4];
            }, $haystack);
        }
        // write result to file
        if ($this -> options['verbose']) {
            echo "Writing output to '" . $output . "'\n";
        }
        file_put_contents($output, $haystack);
        $this -> gzCompressFile($output);
        // free up space
        unset($haystack);
        if ($this -> options['verbose']) {
            echo "Done." . "\n";
        }
    }

    /**check if a file exists and append counter until it doesn't using glob
     *
     */
    function get_free_filename($filepath) {
        $ext = pathinfo($filepath, PATHINFO_EXTENSION);
        // filename without base path or extension
        $filename = pathinfo($filepath, PATHINFO_FILENAME);
        // find all files starting with filename
        $pattern = $filename.'*';
        $files = glob($pattern);
        // return new filepath
        $n = count($files);
        if (count($files) != 0) {
            return $filename.'_('.count($files).').'.$ext;    
        } else {
            return $filepath;   
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
                return;
        }
        $this -> replace($pattern, $replacement, $output, true);
    }

}

new ADT();
?>
