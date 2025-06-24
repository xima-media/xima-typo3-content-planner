..  include:: /Includes.rst.txt

..  _additional-records:

=======================
Additional Records
=======================

By default the Content Planner supports the status functionality only for pages. If you want to use the status functionality for other record types, you can extend the Content Planner to support additional record types.

..  figure:: /Images/additional-record.png
    :alt: Categories as additional records
    :class: with-shadow

    Categories as additional records

..  note::
    Keep in mind to consider the loading order of the Content Planner extension. If you want to use the Content Planner for additional records, you need to load the Content Planner extension **before** the extension that provides the additional records.

Follow the steps below to extend the Content Planner to support additional records, e.g. *news* or *tt_content* records:

1. Extend the additional record TCA (e.g. for news records):

..  code-block:: php
    :caption: Configuration/TCA/Overrides/tx_news_domain_model_news.php

    \Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility::addContentPlannerTabToTCA('tx_news_domain_model_news');


2. Extend the necessary database fields:

..  code-block:: sql
    :caption: ext_tables.sql

    CREATE TABLE tx_news_domain_model_news
    (
        tx_ximatypo3contentplanner_status   int(11) DEFAULT NULL,
        tx_ximatypo3contentplanner_assignee int(11) DEFAULT NULL,
        tx_ximatypo3contentplanner_comments int(11) unsigned default '0' not null,
    );

..  note::
    As of TYPO3 v13, the database fields are generated automatically, so you no longer need to define them yourself: `Feature: #101553 - Auto-create DB fields from TCA columns <https://docs.typo3.org/permalink/changelog:feature-101553-1691166389>`_

3. Register the additional record:

..  code-block:: php
    :caption: ext_localconf.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['xima_typo3_content_planner']['registerAdditionalRecordTables'][] = 'tx_news_domain_model_news';

..  note::
    The extension also support the content status functionality for content elements as well. Just add "tt_content" as described above as additional record table.

..  figure:: /Images/additional-content.png
    :alt: Content elements as additional records
    :class: with-shadow

    Content elements as additional records
