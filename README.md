#WARNING#

PHPBrowscap is currently set to use temporary URIs for its auto-update feature due to the Browser Capabilities Project being in the process of changing ownership. It is highly likely these URIs will change soon, so I have left in the previous URIs and the local file pointer as comments (lines 98 to 115) so simply switch the commented lines as and when you need to depending on the state of the BCP and your needs.

Once the project's ownership has stabilized and permanent URIs are released, I shall update `Browscap.php` appropriately and remove this warning.

For more information on the change of ownership, please look here: https://groups.google.com/forum/#!topic/browscap/pk_dkkqdXzg

#OpenTrack#

A simple script to help you track who opens your emails.

Designed to be used with messages sent via custom auto-mailing scripts.

##Versions##

This is the unstable development version. It is not recommended to use this version for anything other than your own development/proof-of-concepts. If you want to use this script, please look at either the [Master](https://github.com/Ultrabenosaurus/OpenTrack/) or [V1.4](https://github.com/Ultrabenosaurus/OpenTrack/tree/V1.4) branches.

A barebones, stable non-class-based version is also available in the [V1.0 branch](https://github.com/Ultrabenosaurus/OpenTrack/tree/V1.0) if you only need minimal tracking abilities.

##Working On##

* Look into adding support for popular ORM ([Kohana](https://github.com/kohana/orm), [Doctrine](http://www.doctrine-project.org), [RedBean](http://redbeanphp.com/), etc)

##To Do##

Things to do in this dev version.

* Testing D:
* Merge with Master branch

Things to do in next dev version once the current version is stablised and moved to the Master branch.

* Think of more improvements/additions