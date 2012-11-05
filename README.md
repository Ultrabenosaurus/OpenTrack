#WARNING#

PHPBrowscap is currently set to use a local copy of `browscap.ini` as the project is down while ownership is transferred. I will be keeping an eye out for the project's revival and change back to PHPBrowscap's auto-update feature when it happens.

#OpenTrack#

A simple script to help you track who opens your emails.

Designed to be used with messages sent via custom auto-mailing scripts.

##Versions##

This is the original version of OpenTrack. This is not class-based and is (hopefully) the most awkward version to maintain and expand upon. However, it is perfectly stable and works very well so it is fine for use in small projects where minimal changes will be made to the data collected by the script.

##Requirements##

* [browscap.ini](http://php.net/manual/en/function.get-browser.php) and/or [PHPBrowscap](https://github.com/GaretJax/phpbrowscap) and/or [Categorizr](https://github.com/bjankord/Categorizr) - unless you don't mind about device detection
  * Confirmed compatible versions of both PHPBrowscap and Categorizr are included in the `lib/` directory. I haven't modified either file.
* browscap.ini may require you to be able to edit your php.ini file, directly or indirectly doesn't matter
*  PHPBrowscap requires PHP >= 5.3
  * Can automatically download latest browscap.ini - this requires one of fopen, fsockopen, curl
* I can't find any documented requirements/dependencies for Categorizr - I've had no problems on my Apache 2.2 / PHP 5.3.8 system

##Usage##

###Basic###

Edit the `$db_*` variables near the top of `track.php` to match your database settings. Don't worry if the table you intend to use doesn't exist yet, the script will create a table using default settings if it can't find one matching the name you provide.

Upload `track.php` and the entire `lib/` directory to your server - the location doesn't matter so long as the relative paths between files are maintained.

Test the script by either:

1. going directly to the file in your web browser, providing a query string such as this: `track.php?email=test@email.com&campaign=test`
2. making a simple HTML page with an image element like this: `<img src='./track.php?email=test@email.com&campaign=test' />`

Once you've made sure its working, empty the database table and reset the `AUTO_INCREMENT` value ready for tracking real data!

###Advanced###

If you want to track more information than this script collects by default, simply add code to collect the extra information you want and store it in the `$data` array in the format `$data[<field_name>] = <value_to_store>`. The script will automatically add it to the `INSERT` query.

The script checks `$data` for fieldnames not in the table. If it finds any, it uses the values from `$data` to try and guess the field type and size, then creates new fields. This behaviour can be reveresed by changing `$db_fiel` near the top of the script to `false` - this will cause any erroneous fieldnames and their values to be removed from `$data` before the final `INSERT` query is generated.

**WARNING:** the code to dynamically create new fields should be fine as it gives a size margin of twice the input value, but it is certainly better to create the fields properly to your needs either directly in the database or in the `CREATE` query in this script.

##Debugging##

If the script doesn't seem to be working, open the script directly in your browser and add `&test` to the end of the query string. This will prevent the 1x1 pixel image from being displayed and will instead print out the device `$agent`, the fields `$fields` and data `$values` to be inserted, and the result `$response` of the insert.
