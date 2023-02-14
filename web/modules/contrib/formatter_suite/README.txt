                        Formatter Suite module for Drupal

                      by the San Diego Supercomputer Center
                   at the University of California at San Diego


CONTENTS OF THIS FILE
---------------------
 * Introduction
 * Requirements
 * Installation
 * Configuration for entity view pages
 * Configuration for views
 * Troubleshooting


INTRODUCTION
------------
The Formatter Suite module for Drupal provides multiple field formatters
to assist in presenting values within a web page. See the module's help
page for descriptions of the individual formatters.

This project has been sponsored by the National Science Foundation.
See NSF.txt.


REQUIREMENTS
------------
See RELEASENOTES.htm for the latest requirements.

The module only requires the Drupal core Field module, which is mandatory at
all sites. No third-party contributed modules or libraries are required.

Some formatters are only available if additional Drupal core modules are
enabled, such as formatters for Datetime, Email, File, Image, Link, and Text
fields.


INSTALLATION
------------
Install the module as you would normally install a contributed Drupal module.
Visit:
  https://www.drupal.org/docs/user_guide/en/config-install.html


CONFIGURATION FOR ENTITY VIEW PAGES
-----------------------------------
To configure field formatters for an entity view page, you must have the
Drupal core Field UI module enabled. Field UI provides a "Manage display"
page for each entity type. From this page you can select the field formatter
to use for each field, then click on the formatter's gear icon to the right
of the field row. This presents the formatter's configuration page. Click
"Update" to save that configuration, and "Save" to save the display.

Thereafter, each time the field is shown on an entity view page, Drupal will
invoke the chosen field formatter to present the value.


CONFIGURATION FOR VIEWS
-----------------------
To configure field formatters for a view, you must have the Drupal core
View UI module enabled. View UI provides a page for each view from which
you can select the fields to include in a table or list of entities. For
each field, you can configure how the field is presented by selecting a
field formatter and adjusting that formatter's settings.

Thereafter, each time the field is shown for an entity in a row of a view,
Drupal will invoke the chosen field formatter to present the value.


TROUBLESHOOTING
---------------
The module does not have a database schema and it has no configuration
settings of its own. There is nothing to change or reset if there is trouble.
