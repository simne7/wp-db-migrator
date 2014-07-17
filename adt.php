<?php
//FIXME: Bug auflösen, der auftritt, wenn man das Skript aus einem anderen Ordner aus aufruft.
// Determine if the script is run from command line (cli) or web interface (web)
$fields['INTERFACE'] = php_sapi_name();
$PROGRAM_NAME = basename(__FILE__);

// we are on cli, therefore use argv[] for params
if ($fields['INTERFACE'] == 'cli') {

	// set possible short options
	$shortopts = "p:" . "u:" . "h:" . "n:" . "f:" . "v" . "t" . "i:" . "l:" . "r:";
	// set possible long options
	$longopts = array("help", "password:", "user:", "host:", "name:", "file:", "verbose::", "test", "import:", "remotize", "localize");
	// get all the options from the cli
	$options = getopt($shortopts, $longopts);

	// If no parameters were given, give a heads up
	if ($argc == 1) {
		echo "Warning: With no arguments specified, default behaviour is invoked. This may not lead to the\n desired effects.\n";
	}
	// TODO: Throw error on invalid arguments/options
	// do stuff for every option set
	foreach ($options as $opt => $arg) {
		switch ($opt) {
			// display help and exit
			case 'help' :
				print_help();
				exit ;
			// take the path to wp_config.php as an argument
			case 'f' :
			case 'file' :
				$fields['WP_CONFIG_PATH'] = $arg;
				break;
			// take the database hostname as an argument
			case 'h' :
			case 'host' :
				$fields['DB_HOST'] = $arg;
				break;
			// take the database name as an argument
			case 'n' :
			case 'name' :
				$fields['DB_NAME'] = $arg;
				break;
			// take the database password as an argument
			case 'p' :
			case 'password' :
				$fields['DB_PASSWORD'] = $arg;
				break;
			// take the database user as an argument
			case 'u' :
			case 'user' :
				$fields['DB_USER'] = $arg;
				break;
			// set the verbose level
			case 'v' :
				$fields['VERBOSE_LVL'] += count($arg);
				break;
			case 'verbose' :
				if (!$arg) {
					$fields['VERBOSE_LVL'] += 1;
				} else {
					$fields['VERBOSE_LVL'] += $arg;
				}
				break;
			// Test mode
			case 't' :
			case 'test' :
				echo "Entering test mode...\n";
				$fields['TEST_MODE'] = true;
				break;
			// Import mode, create backup then import specified file into database
			case 'i' :
			case 'import' :
				$fields['MODE'] = 'import';
				$fields['IMPORT_PATH'] = $arg;
				break;
			case 'l' :
			case 'localize' :
				$fields['FILE_PATH'] = $arg;
				$fields['MODE'] = 'localize';
				break;
			case 'r' :
			case 'remotize' :
				$fields['FILE_PATH'] = $arg;
				$fields['MODE'] = 'remotize';
				break;
			// if we get this, we failed to catch a valid option
			default :
				echo "Failed to catch that option.\n";
				exit ;
		}
	}
}
// we are not on cli, use $_GET for params
else {
	// TODO: implement
}
// set default values where none have been obtained
set_defaults();
date_default_timezone_set("Europe/Berlin");
// try to parse wp_config.php
if (is_file($fields['WP_CONFIG_PATH'])) {
	// find all lines where DB fields are defined
	$pattern = "/.+(DB_.+)\'.*\'(.*)\'/";
	preg_match_all($pattern, file_get_contents($fields['WP_CONFIG_PATH']), $matches);
	// transform them into nice key => value pairs like DB_NAME => 'wordpress', add to fields
	$fields = array_merge($fields, array_combine($matches[1], $matches[2]));
}

// Main Program
//TODO set up all the default options in the .ini-file (?)
$fields = array_merge($fields, parse_ini_file('./config.ini'));
switch ($fields['MODE']) {
	case 'import' :
		import_dump();
		break;
	case 'localize' :
		if ($fields['VERBOSE_LVL'] > 0) {
			echo "Entering localize mode...\n";
		}
		localize();
		break;
	case 'remotize' :
		remotize();
		break;
	// default mode: dump
	default :
        create_dump();
}
// echo newline after programm output
echo "\n";

/** Create wordpress database dump
 *
 */
function create_dump($extension = '') {
	global $fields;
	if ($fields['VERBOSE_LVL'] > 0) {
		echo "Taking a dump to dump_" . date("y-m-d-H-i") . ".mysql.gz" . $extension . "\n";
	}
	// Data manipulation, only do something, if not in test mode
	if (!$fields['TEST_MODE']) {
	    // --single-transaction solves error if user may not LOCK TABLES
		exec("mysqldump --single-transaction --user=" . $fields['DB_USER'] . " --password=" . $fields['DB_PASSWORD'] . " --host=" . $fields['DB_HOST'] . " " . $fields['DB_NAME'] . " | gzip > dump_`date +%y-%m-%d_%H-%M`.mysql.gz" . $extension);
		if ($fields['VERBOSE_LVL'] > 0) {
			echo "Done.\n";
		}
	} else {// test mode!
		//TODO: Let test mode describe what would happen instead
	}
}

/**
 * Import sql dump into wordpress database
 */
function import_dump() {
	global $fields;
	if ($fields['VERBOSE_LVL'] > 0) {
		echo "Creating Backup...\n";
	}
	create_dump('.bak');
	// insert dump in db
	if ($fields['VERBOSE_LVL'] > 0) {
		echo "Importing SQL...\n";
	}
	// expect a .sql file
	// TODO add automatic extract for compressed files
	$command = "mysql -h " . $fields['DB_HOST'] . " -D " . $fields['DB_NAME'] . " -u " . $fields['DB_USER'] . " -p" . $fields['DB_PASSWORD'] . " < " . $fields['IMPORT_PATH'];
	exec($command);
	if ($fields['VERBOSE_LVL'] > 0) {
		echo "Done.\n";
	}
}

/** Print usage information
 *
 */
function print_help() {
	global $PROGRAM_NAME;
	//TODO: Get help on specific options a la "git help push"
	//TODO: Give different advice when invoked on web interface
	//TODO: Add examples
	$output = "";
	$output .= "Usage:\n" . "php " . $PROGRAM_NAME . " [options]\n\n" . "Options:\n" . "--help\n" . "\t\tPrint this help\n" . "-t, --test\n" . "\t\tDo nothing, just display what would.\n" . "-f FILE, --file FILE\n" . "\t\tSpecify the path to a wp_config file\n" . "-h HOSTNAME, --host HOSTNAME\n" . "\t\tSpecify the hostname of a target database directly\n" . "-n NAME, --name NAME\n" . "\t\tSpecify the name of the target database directly\n" . "-p PASSWORD, --password PASSWORD\n" . "\t\tSpecify the password of the target database\n" . "-u USERNAME, --user USERNAME\n" . "\t\tSpecify the username to the target database\n" . "-v*, --verbose[=n] \n" . "\t\tIncrease/specify the verbose level of the script. -vv results in a level of 2.\n" . "-i FILE, --import FILE\n" . "\t\tImport selected file into database.";
	$output .= "Examples:\n\nDump database with default options:\nphp $PROGRAM_NAME\n\nRemotize SQL:\nphp $PROGRAM_NAME -r DUMP_FILE.sql\n\nImport SQL into DB, specify host and password:\nphp $PROGRAM_NAME -i DUMP_FILE.sql -h http://www.remote-host.com -p PASSWORD";
	echo $output;
}

/** Print all variable fields (mainly for debugging purposes)
 *
 */
function print_fields() {
	global $fields;
	echo "Fields:\n";
	foreach ($fields as $key => $value) {
		echo "$key: '$value'\n";
	}
}

/** Localize db dump from server
 *
 */
function localize() {
	global $fields;
	$dump = file_get_contents($fields['FILE_PATH']);

	$pattern = '#' . $fields['remote'] . '#mi';
	echo $pattern."\n";
	$dump=preg_replace($pattern, $fields['local'], $dump);
	file_put_contents($fields['FILE_PATH'], $dump);
    //TODO \\\\ inserted for eventually escaped quotes, test on MAC
    exec('perl -pi -e \'s{s:([0-9]+):(\\\\?"(.*?)\\\\?")}{s:@{[ length($3) ]}:$2}gis\' ' . $fields['FILE_PATH']);
}

/**
 * Remotize db dump from local machine
 */
function remotize() {
	global $fields;

	$dump = file_get_contents($fields['FILE_PATH']);
	$pattern = '#' . $fields['local'] . '#im';
	echo $pattern."\n";
	$dump=preg_replace($pattern, $fields['remote'], $dump);
	file_put_contents($fields['FILE_PATH'], $dump);
    //TODO \\\\ inserted for eventually escaped quotes, test on MAC
    $cmd = 'perl -pi -e \'s{s:([0-9]+):(\\\\?"(.*?)\\\\?")}{s:@{[ length($3) ]}:$2}gis\' ' . $fields['FILE_PATH'];
    echo $cmd;
    exec($cmd);
}

/** fill the fields with default values if neither params nor wp_config can provide
 *
 */
function set_defaults() {
	global $fields;
	// set default path to wp_config
	if (!isset($fields['WP_CONFIG_PATH'])) {
		$fields['WP_CONFIG_PATH'] = 'wordpress/wp-config.php';
	}
	// set default mode
	if (!isset($fields['MODE'])) {
		$fields['MODE'] = 'dump';
	}
	// set default verbose level
	if (!isset($fields['VERBOSE_LVL'])) {
		$fields['VERBOSE_LVL'] = 0;
	}
	// set default database name
	if (!isset($fields['DB_NAME'])) {
		if ($fields['VERBOSE_LVL'] > 1) {
			echo "Setting DB_NAME to default value.\n";
		}
		$fields['DB_NAME'] = 'wordpress';
	}
	// set default database user
	if (!isset($fields['DB_USER'])) {
		if ($fields['VERBOSE_LVL'] > 1) {
			echo "Setting DB_USER to default value.\n";
		}
		$fields['DB_USER'] = 'admin';
	}
	// set default database hostname
	if (!isset($fields['DB_HOST'])) {
		if ($fields['VERBOSE_LVL'] > 1) {
			echo "Setting DB_HOST to default value.\n";
		}
		$fields['DB_HOST'] = 'localhost';
	}
	// set default database password
	if (!isset($fields['DB_PASSWORD'])) {
		if ($fields['VERBOSE_LVL'] > 1) {
			echo "Setting DB_PASSWORD to default value.\n";
		}
		$fields['DB_PASSWORD'] = 'sixreasons';
	}
	// set default database charset
	if (!isset($fields['DB_CHARSET'])) {
		if ($fields['VERBOSE_LVL'] > 1) {
			echo "Setting DB_CHARSET to default value.\n";
		}
		$fields['DB_CHARSET'] = 'utf8';
	}
	// set default database collation
	if (!isset($fields['DB_COLLATE'])) {
		if ($fields['VERBOSE_LVL'] > 1) {
			echo "Setting DB_COLLATE to default value.\n";
		}
		$fields['DB_COLLATE'] = '';
	}
}
