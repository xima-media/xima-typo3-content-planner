<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Form\FormDataProvider;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Form\FormDataProvider\ContentPlannerFieldsReadOnly;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * ContentPlannerFieldsReadOnlyTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentPlannerFieldsReadOnlyTest extends AbstractFunctionalTestCase
{
    private ContentPlannerFieldsReadOnly $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ContentPlannerFieldsReadOnly();
    }

    #[Test]
    public function addDataReturnsResultUnchangedForUnregisteredTable(): void
    {
        $result = $this->buildResult('tt_content');

        $processed = $this->subject->addData($result);

        self::assertSame($result, $processed);
    }

    #[Test]
    public function addDataDoesNotMarkFieldsReadOnlyForUserWithFullAccess(): void
    {
        $this->loginBackendUser(1);

        $result = $this->subject->addData($this->buildResult('pages'));

        $columns = $result['processedTca']['columns'];
        self::assertArrayNotHasKey('readOnly', $columns[Configuration::FIELD_STATUS]['config']);
        self::assertArrayNotHasKey('readOnly', $columns[Configuration::FIELD_ASSIGNEE]['config']);
        self::assertArrayNotHasKey('readOnly', $columns[Configuration::FIELD_COMMENTS]['config']);
    }

    #[Test]
    public function addDataMarksAllContentPlannerFieldsReadOnlyForUserWithoutPermissions(): void
    {
        $this->loginBackendUser(2);

        $result = $this->subject->addData($this->buildResult('pages'));

        $columns = $result['processedTca']['columns'];
        self::assertTrue($columns[Configuration::FIELD_STATUS]['config']['readOnly']);
        self::assertTrue($columns[Configuration::FIELD_ASSIGNEE]['config']['readOnly']);
        self::assertTrue($columns[Configuration::FIELD_COMMENTS]['config']['readOnly']);
        self::assertArrayNotHasKey('readOnly', $columns['title']['config']);
    }

    #[Test]
    public function addDataSkipsFieldsNotPresentInProcessedTca(): void
    {
        $this->loginBackendUser(2);

        $result = [
            'tableName' => 'pages',
            'processedTca' => [
                'columns' => [
                    'title' => ['config' => ['type' => 'input']],
                ],
            ],
        ];

        $processed = $this->subject->addData($result);

        self::assertSame($result, $processed);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResult(string $tableName): array
    {
        return [
            'tableName' => $tableName,
            'processedTca' => [
                'columns' => [
                    Configuration::FIELD_STATUS => ['config' => ['type' => 'select']],
                    Configuration::FIELD_ASSIGNEE => ['config' => ['type' => 'select']],
                    Configuration::FIELD_COMMENTS => ['config' => ['type' => 'inline']],
                    'title' => ['config' => ['type' => 'input']],
                ],
            ],
        ];
    }
}
