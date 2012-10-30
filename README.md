#OpenTrack#

A simple script to help you track who opens your emails.

Designed to be used with messages sent via custom auto-mailing scripts.

##Versions##

This is the very first stable, fully-working version of OpenTrack. It only has Categorizr as a fallback if browscap.ini is unusable, but does not have a way to force skipping browscap.ini. This version also has no error logging so it will silently fail (hopefully - worst case scenario is users see the broken image symbol) if anything goes wrong.

If you want to use this script, please look at either the [Master](https://github.com/Ultrabenosaurus/OpenTrack/) (stable class-based) or [V1.0](https://github.com/Ultrabenosaurus/OpenTrack/tree/V1.0) (stable, non-class-based) branches.

##Requirements##

* [browscap.ini](http://php.net/manual/en/function.get-browser.php) and/or [Categorizr](https://github.com/bjankord/Categorizr) - unless you don't mind about device detection
* browscap.ini may require you to be able to edit your php.ini file, directly or indirectly doesn't matter
* I can't find any documented requirements/dependencies for Categorizr - I've had no problems on my Apache 2.2 / PHP 5.3.8 system
