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

namespace Xima\XimaTypo3ContentPlanner\Event;

/**
 * ModifyCommentEditorConfigurationEvent.
 *
 * Dispatched after the comment composer's CKEditor5 configuration has been assembled and
 * before it is handed to the editor. Listeners can register additional plugins, toolbar
 * items or `importModules` entries without replacing the factory.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ModifyCommentEditorConfigurationEvent
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(private array $configuration, private readonly int $pid) {}

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function setConfiguration(array $configuration): void
    {
        $this->configuration = $configuration;
    }

    /**
     * The page the comment is being written on, for listeners that need to resolve
     * page-dependent configuration such as permissions or site settings.
     */
    public function getPid(): int
    {
        return $this->pid;
    }
}
