<div align="center">

![Extension icon](Resources/Public/Icons/Extension.svg)

# TYPO3 extension `xima_typo3_content_planner`

[![Latest Stable Version](https://typo3-badges.dev/badge/xima_typo3_content_planner/version/shields.svg)](https://extensions.typo3.org/extension/xima_typo3_content_planner)
[![Supported TYPO3 versions](https://typo3-badges.dev/badge/xima_typo3_content_planner/typo3/shields.svg)](https://extensions.typo3.org/extension/xima_typo3_content_planner)
[![Maintainability](https://img.shields.io/codeclimate/maintainability/xima-media/xima-typo3-content-planner?logo=codeclimate)](https://codeclimate.com/github/xima-media/xima-typo3-content-planner/maintainability)
[![License](https://poser.pugx.org/xima/xima-typo3-content-planner/license)](LICENSE.md)


</div>

This extension provides a page* status functionality to support the planning of
content work, e.g. a migration process.

(* also supports other records as well)

![Page](./Documentation/Images/page.png)

## Features

* Extended page properties for content status, assignee and additional comments
* Colorful representation of the status within the backend
* Easy change of status
* User assignment for distribution of content work
* Comments for additional information
* Extensive dashboard for detailed content planning
* Recent updates widget for quick access to the latest changes
* Filterable content planner record overview
* Extend additional database records with status behavior

## Requirements

* TYPO3 >= 12.4 & PHP 8.1+

## Installation

### Composer

[![Packagist](https://img.shields.io/packagist/v/xima/xima-typo3-content-planner?label=version&logo=packagist)](https://packagist.org/packages/xima/xima-typo3-content-planner)
[![Packagist Downloads](https://img.shields.io/packagist/dt/xima/xima-typo3-content-planner?color=brightgreen)](https://packagist.org/packages/xima/xima-typo3-content-planner)

``` bash
composer require xima/xima-typo3-content-planner
```

### TER

[![TER version](https://typo3-badges.dev/badge/xima_typo3_content_planner/version/shields.svg)](https://extensions.typo3.org/extension/xima_typo3_content_planner)
[![TER downloads](https://typo3-badges.dev/badge/xima_typo3_content_planner/downloads/shields.svg)](https://extensions.typo3.org/extension/xima_typo3_content_planner)

Download the zip file from [TYPO3 extension repository (TER)](https://extensions.typo3.org/extension/xima_typo3_content_planner).

## Setup

After the installation, update the database schema and setup the extension:

``` bash
vendor/bin/typo3 database:updateschema
vendor/bin/typo3 extension:setup --extension=xima_typo3_content_planner
```

## Documentation

Please have a look at the
[official extension documentation](https://docs.typo3.org/p/xima/xima-typo3-content-planner/main/en-us/Index.html).

## Development

Use the following ddev command to easily install all support TYPO3 versions.

```bash
ddev install all
```

## License

This project is licensed
under [GNU General Public License 2.0 (or later)](LICENSE.md).

## Credits

Relax icons by Chattapat
from <a href="https://thenounproject.com/browse/icons/term/relax/" target="_blank" title="relax Icons">
Noun Project</a> (CC BY 3.0)
