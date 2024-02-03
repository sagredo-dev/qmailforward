Roundcube Webmail qmailforward plugin
==============================
This plugin adds the ability for qmail users to edit their forward from within
Roundcube with no need to ask their administrators for doing that via qmailadmin.
qmailforwards saves the forwards to mysql database.

Unlike the managesieve plugin, from which this plugin is inspired but which only
apparently behaves in the same way, it does not use the sieve rules but it saves
the forwards on the database, also preserving the possibility of saving a record
that enables the copy of messages on the mailbox. In this case the execution of
your favorite delivery agent is launched, which can also be set from the
configuration file. 

Using this method instead of sieve rules allows qmail users to keep the SPF
policies in effect.

Inspiration and part of the code for this plugin was taken from the sieverules
and managesieve plugins. The latter one provides an identical html form.

Requirements
------------
* vpopmail version 5.6.0 or later https://notes.sagredo.eu/en/qmail-notes-185/installing-and-configuring-vpopmail-81.html
* vpopmail configured with virtual aliases `--enable-valias` and patched to
  modify the colums according to the already mentioned patch.
* you may want to upgrade qmailadmin as well (https://notes.sagredo.eu/en/qmail-notes-185/qmailadmin-23.html)

Install
-------
* Place this plugin folder into plugins directory of Roundcube
* Add qmailforward to `$config['plugins']` in your Roundcube config
* If you are switching to valiases the table will be created for you at first
  access. I you already have the valias table but it's still empty, just erase
  it and let vpopmail create for you.
* If your valias table already exist and it contains records that you don't
  want to loose, then execute the following query. Drop any `PRIMARY KEY` if you
  already have one.

```sql
USE vpopmail;
ALTER TABLE `valias` ADD `valias_type` TINYINT(1) NOT NULL DEFAULT '1' COMMENT '1=forwarder 0=lda' FIRST;
ALTER TABLE `valias` ADD `copy` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '0=redirect 1=copy&redirect' AFTER `valias_line`;
ALTER TABLE `valias` ADD PRIMARY KEY (`valias_type`, `alias`, `domain`);
```

Configuration
-------------
* The default config file is plugins/qmailforward/config.inc.php.dist
* Copy the options you have to modify to plugins/qmailforward/config.inc.php
* You must set at least the database connection string

Supported languages
-------------------
* en_US - English (US)
* en_GB - English (GB)
* it_IT - Italian
* ru_RU - Russian

Send new translations to the e-mail below.

Author
------
Roberto Puzzanghera [roberto dot puzzanghera at sagredo dot eu https://notes.sagredo.eu]

License
-------
This plugin is released under the [GNU General Public License Version 3+][gpl].

Support
-------
To ask for support post a comment in my blog at https://notes.sagredo.eu/en/qmail-notes-185/roundcube-plugins-35.html

[gpl]: https://www.gnu.org/licenses/gpl.html
