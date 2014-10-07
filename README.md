# ADT - Advanced Dump Tool

This Skript is meant to ease the web development process using local repositories by automatically creating database backups and migrating databases from local to remote hosts and vice versa.

## Usage

```
./adt.phps [options]
./adt.phps [options] <command> [command_options] [args]
```

## Options

| 				  | 						  							|
| --------------- | --------------------------------------------------- |
| `-v, --verbose` | turn on verbose output    							|
| `--debug`       | turn on debug output      							|
| `-h, --help`    | show help and exit, also available for sub-commands |
| `--version`     | show version and exit     							|

## Commands

* `dump` : Create a gzipped database dump  
  - `- o, --output <file>` : Specify output file  
* `import <file>` : Import SQL into a database  
* `localize` : Replace every occurence of the remote host with the local host.
  - `-o <file>, --output <file>` : Specify output file  
* `remotize` : Replace every occurence of the local host with the remote host.
  - `-o <file>, --output <file>` : Specify output file  
* `replace` : Replace every occurence of a pattern with a replacement.
  - `-o <file>, --output <file>` : Specify output file
  - `-s, --serialize` : Whether the pattern is enclosed in a serialized string that should be properly modified

## Examples

| 						  	  |														  |
| --------------------------- | ----------------------------------------------------- |
| `adt -v dump` 		  	  | Create a database dump and output verbose information |
| `adt import ./dump.sql` 	  | Import './dump.sql' into the database 				  |
| `adt localize -h`		  	  | Get help on the 'localize'-Command					  |
| `adt replace -s -o out.sql` | Replace a serialized string and save to out.sql.	  |