#OpenTrack#

A simple script to help you track who opens your emails.

Designed to be used with messages sent via custom auto-mailing scripts.

##Requirements##

* browscap.ini OR [PHPBrowscap](https://github.com/GaretJax/phpbrowscap) OR [Categorizr](https://github.com/bjankord/Categorizr) - unless you don't mind about device detection
  * Confirmed compatible versions of both PHPBrowscap and Categorizr are included in the `lib/` directory. I haven't modified either file.
* browscap.ini may require you to be able to edit your php.ini file, directly or indirectly doesn't matter
*  PHPBrowscap requires PHP >= 5.3
  * Can automatically download latest browscap.ini - this requires one of fopen, fsockopen, curl
* I can't find any documented requirements/dependencies for Categorizr - I've had no problems on my Apache 2.2 / PHP 5.3.8 system

##Usage##

###Basic###

Edit the `$db_*` variables near the top of `track.php` to match your database settings. Don't worry if the table you intend to use doesn't exist yet, the script will create a table using default settings if it can't find one matching the name you provide.

Upload `track.php` and the entire `lib/` directory to your server - the location doesn't matter so long as the relative paths between files is maintained.

Test the script by either:

1. going directly to the file in your web browser, providing a query string such as this: `track.php?email=test@email.com&campaign=test`
2. making a simple HTML page with an image element like this: `<img src='./track.php?email=test@email.com&campaign=test' />`

Once you've made sure its working, empty the database table and reset the `AUTO_INCREMENT` value ready for tracking real data!

###Advanced###

If you want to track more information than this script collects by default, simply add code to collect the extra information you want and store it in the `$data` array in the format `$data[<field_name>] = <value_to_store>`. The script will automatically add it to the `INSERT` query.

You will need to ensure that any extra fields you wish to use are created in the table *before* you try to use them in the script. I have not yet implemented a fallback for non-existent fields. This can either be done manually, or by editing the `CREATE` query at the bottom of `track.php` and running the script.

##Debugging##

If the script doesn't seem to be working, open the script directly in your browser and add `&test` to the end of the query string. This will prevent the 1x1 pixel image from being displayed and will instead print out the device `$agent`, the fields `$fields` and data `$values` to be inserted, and the result `$response` of the insert.

##To Do##

* Add a check for the existence of table fields before attempting the `INSERT` query
  * Provide a fallback (such as removing that data from the `INSERT`) or possibly create the field?
