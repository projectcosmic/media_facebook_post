CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration


INTRODUCTION
------------

[![Build status]][build]

The Media Facebook Posts module integrates Facebook posts as a media source.
Currently, it only supports a pulling posts from a single page.


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

Install as you would normally install a contributed Drupal module. Visit
https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
------------

    1. Navigate to Administration > Extend and enable the module.
    2. Navigate to Administration > People > Permissions and configure
       permissions of the module
    3. A user that has a Facebook account with Moderator access (or above) for
       the Facebook page where posts should be pulled from should navigate to
       Administration > Web services > Link Facebook and follow the login
       procedure (their Drupal user must have the "Link Facebook account"
       permission to access this page).
    4. Navigate to Administration > Structure > Media types and add a new media
       type using the "Facebook posts" source. One can also set periodic
       automatic fetching of posts via cron.


[Build status]: https://github.com/projectcosmic/media_facebook_post/actions/workflows/ci.yml/badge.svg
[build]: https://github.com/projectcosmic/media_facebook_post/actions/workflows/ci.yml
