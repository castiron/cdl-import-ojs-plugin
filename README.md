# CDL Import Plugin

This plugin currently has one import function. Provided with a properly formatted text file, it will set a license 
value for articles associated with specified issues.

## Use

The tool is run from the command line. Run it like this:

`php tools/importExport.php CdlImportPlugin cc [[FILENAME]]`

Optional flags include `--dry-run` and `--verbose`.

## Import File Format

The import file is tab-delimited CSV with the following header row:

`id  unit_id volume  issue   published       attrs`

`id` is arbitrary and ignored. `unit_id` is the journal path. `volume` is the volume number, an integer. `issue` is an
integer or float value. `published` is a date, formatted `YYYY-MM-DD`. `attrs` is JSON which may or may not contain
a Creative Commons license (identified by matching a string beginning with `https://creativecommons.org`). 

Only the `unit_id`, `volume` and `issue` fields are used to match issues. Articles associated with the specified issue
are given a `eschol_license_url` setting in the `article_settings` table if they match. If not, they are skipped.

See the sample file `sample_import_file.csv`.