..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  _requirements:

Requirements
============

-   PHP 8.1 - 8.4
-   TYPO3 12.4 LTS - 13.4 LTS

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
