#OpenTrack#

A simple script to help you track who opens your emails.

Designed to be used with messages sent via custom auto-mailing scripts.

##Versions##

Stable and mostly finished class-based version available in the [Master branch](https://github.com/Ultrabenosaurus/OpenTrack/) (your current location).

Stable but not finished class-based version available in the [V1.4 branch](https://github.com/Ultrabenosaurus/OpenTrack/tree/V1.4).

Stable and completely working non-class-based version available in the [V1.0 branch](https://github.com/Ultrabenosaurus/OpenTrack/tree/V1.0).

Unstable version available in the [Dev branch](https://github.com/Ultrabenosaurus/OpenTrack/tree/dev).

##Requirements##

* [browscap.ini](http://php.net/manual/en/function.get-browser.php) and/or [PHPBrowscap](https://github.com/GaretJax/phpbrowscap) and/or [Categorizr](https://github.com/bjankord/Categorizr) - unless you don't mind about device detection
  * Confirmed compatible versions of both PHPBrowscap and Categorizr are included in the `lib/` directory. I haven't modified either file.
* browscap.ini may require you to be able to edit your php.ini file, directly or indirectly doesn't matter
*  PHPBrowscap requires PHP >= 5.3
  * Can automatically download latest browscap.ini - this requires one of fopen, fsockopen, curl
* I can't find any documented requirements/dependencies for Categorizr - I've had no problems on my Apache 2.2 / PHP 5.3.8 system

##To Do##

* Write Usage and Debugging instructions
* PHPDocumentor DocBlocks