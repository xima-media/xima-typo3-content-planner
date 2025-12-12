..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  _requirements:

Requirements
============

-   PHP 8.2 - 8.5
-   TYPO3 13.4 LTS & 14.0+

Version Matrix
--------------

=========  =========  =========
Version    TYPO3      PHP
=========  =========  =========
2.x        13-14      8.2-8.5
1.x        12-13      8.1-8.5
=========  =========  =========

..  _install:

Install
============

Require the extension via Composer (recommended):

..  code-block:: bash

    composer require xima/xima-typo3-content-planner

Or download it from the
`TYPO3 extension repository <https://extensions.typo3.org/extension/xima_typo3_content_planner>`__.

..  _setup:

Setup
============

After the installation, update the database schema and setup the extension:

..  code-block:: bash

    vendor/bin/typo3 database:updateschema
    vendor/bin/typo3 extension:setup --extension=xima_typo3_content_planner
