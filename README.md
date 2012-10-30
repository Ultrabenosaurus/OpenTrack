#OpenTrack#

A simple script to help you track who opens your emails.

Designed to be used with messages sent via custom auto-mailing scripts.

##Versions##

This is the unstable development version. It is not recommended to use this version for anything other than your own development/proof-of-concepts. If you want to use this script, please look at either the [Master](https://github.com/Ultrabenosaurus/OpenTrack/) (stable class-based) or [V1.0](https://github.com/Ultrabenosaurus/OpenTrack/tree/V1.0) (stable, non-class-based) branches.

##Requirements##

* [browscap.ini](http://php.net/manual/en/function.get-browser.php) and/or [PHPBrowscap](https://github.com/GaretJax/phpbrowscap) and/or [Categorizr](https://github.com/bjankord/Categorizr) - unless you don't mind about device detection
  * Confirmed compatible versions of both PHPBrowscap and Categorizr are included in the `lib/` directory. I haven't modified either file.
* browscap.ini may require you to be able to edit your php.ini file, directly or indirectly doesn't matter
*  PHPBrowscap requires PHP >= 5.3
  * Can automatically download latest browscap.ini - this requires one of fopen, fsockopen, curl
* I can't find any documented requirements/dependencies for Categorizr - I've had no problems on my Apache 2.2 / PHP 5.3.8 system

##To Do##

* Enhance new field generation to be as reliable as possible
* Test error logging
* Document code in comments
