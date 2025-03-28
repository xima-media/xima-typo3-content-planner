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

## Usage

### Status

By default, they are four different default status available:

- *Pending*: The page is not yet ready for editing.
- *In progress*: The page is currently being edited.
- *Needs review*: The page is ready for review.
- *Completed*: The page is ready to be published.

> **Hint**: The status are content generated on the root page. Add/remove/adjust them to fit your needs regarding custom title, predefined color and icon.

Change the page status easily:

- In the "Content Planner" tab within the page properties
- In the page tree context menu
- In the backend header

### Assignee and comments

Assign an user to the page to distribute the content work. Your own assignment is highlighted in the dashboard.

> **Hint**: By default the auto assignee feature is enabled. The assignee is automatically set to the current user when the status is changed from stateless to a new state.

Configure the auto assignee feature and more in the __extension settings__.

Use the comment feature to add some helpful messages within the records to support the content work.

![Screencast](./Documentation/Images/screencast.gif)

### Dashboard

The dashboard provides an overview of the content status of all related records.
Use the "Content Planner" preset to easily create a new dashboard.
Add custom notes to the dashboard to influence the content planning.

![Dashboard](./Documentation/Images/dashboard.png)

## Configuration

Feature toggles are available in the __extension settings__.

### User settings

The content planner abilities are part of a **custom permission** and needed to be granted to the dedicated user group/s (except admins).

Every user can easily disable the content planner features in the user settings to avoid colour overload.

## Command

Use the bulk update command to process multiple entities at once. See help for more information regarding the specific usage.

```bash
vendor/bin/typo3 content-planner:bulk-update --help
```

## Extend

### Additional record tables

![Categories](./Documentation/Images/categories.png)

If you want to extend the content planner to other record tables (e.g. news), follow the steps below:

1. Extend the TCA (e.g. `Configuration/TCA/Overrides/tx_news_domain_model_news.php`):

```php
\Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility::addContentPlannerTabToTCA('tx_news_domain_model_news');
```

2. Extend the database fields (`ext_tables.sql`):

```sql
CREATE TABLE tx_news_domain_model_news
(
    tx_ximatypo3contentplanner_status   int(11) DEFAULT NULL,
    tx_ximatypo3contentplanner_assignee int(11) DEFAULT NULL,
    tx_ximatypo3contentplanner_comments int(11) unsigned default '0' not null,
);
```

3. Register the table in the `ext_localconf.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['xima_typo3_content_planner']['registerAdditionalRecordTables'][] = 'tx_news_domain_model_news';
```

> **Hint**: The extension also support the content status functionality for content elements as well. Just add "tt_content" as additional record table.

![Content](./Documentation/Images/tt_content.png)

### Events

This extension provides several events to hook into the content planner functionality.

- [StatusChangeEvent](Classes/Event/StatusChangeEvent.php)
- [PrepareStatusSelectionEvent](Classes/Event/PrepareStatusSelectionEvent.php)

### Utility

Use the [PlannerUtility](Classes/Utility/PlannerUtility.php) to programmatically interact with the content planner.

## Development

Use the following ddev command to easily install all support TYPO3 versions.

```bash
ddev install all
```

## License

This project is licensed
under [GNU General Public License 2.0 (or later)](LICENSE.md).

Relax icons by Chattapat
from <a href="https://thenounproject.com/browse/icons/term/relax/" target="_blank" title="relax Icons">
Noun Project</a> (CC BY 3.0)
