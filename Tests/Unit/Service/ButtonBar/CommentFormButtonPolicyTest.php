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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Service\ButtonBar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\Template\Components\Buttons\{InputButton, LinkButton};
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Service\ButtonBar\CommentFormButtonPolicy;

/**
 * CommentFormButtonPolicyTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentFormButtonPolicyTest extends TestCase
{
    private CommentFormButtonPolicy $subject;

    protected function setUp(): void
    {
        $languageService = $this->createMock(LanguageService::class);
        $languageService->method('sL')->willReturn('Save and Close');
        $GLOBALS['LANG'] = $languageService;

        $this->subject = new CommentFormButtonPolicy();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['LANG']);
    }

    #[Test]
    public function theModalKeepsOnlyTheSaveButtonAndRelabelsIt(): void
    {
        $result = $this->subject->apply($this->buttonBar(), keepCloseButton: false);

        self::assertSame(['_savedok'], $this->namesIn($result));
        self::assertSame('Save and Close', $this->saveButtonIn($result)->getTitle());
    }

    #[Test]
    public function theEmbeddedViewKeepsTheCloseButtonToo(): void
    {
        $result = $this->subject->apply($this->buttonBar(), keepCloseButton: true);

        // Without the close button there is no way back to the comment thread.
        self::assertTrue($this->hasCloseButton($result), 'the core close button must survive');
        self::assertSame(['_savedok'], $this->namesIn($result));
    }

    #[Test]
    public function theEmbeddedViewLeavesTheSaveLabelAlone(): void
    {
        // "Save and Close" is only true while the modal does the closing; here the button
        // really only saves, and claiming otherwise sends the user looking for a redirect
        // that never happens.
        $result = $this->subject->apply($this->buttonBar(), keepCloseButton: true);

        self::assertSame('Save', $this->saveButtonIn($result)->getTitle());
    }

    #[Test]
    public function unrelatedButtonsAreDroppedInBothModes(): void
    {
        foreach ([true, false] as $keepCloseButton) {
            $result = $this->subject->apply($this->buttonBar(), $keepCloseButton);

            self::assertArrayNotHasKey('right', $result, 'the right group is never kept');
            self::assertFalse(
                $this->containsTitle($result, 'Delete'),
                'buttons other than save and close must not survive',
            );
        }
    }

    /**
     * @return array<string, array<int, array<int, mixed>>>
     */
    private function buttonBar(): array
    {
        $save = (new InputButton())->setName('_savedok')->setValue('1')->setTitle('Save');
        $close = (new LinkButton())->setHref('#')->setClasses('t3js-editform-close')->setTitle('Close');
        $delete = (new LinkButton())->setHref('#')->setTitle('Delete');
        $viewInRight = (new LinkButton())->setHref('#')->setTitle('View');

        return [
            'left' => [[$save, 1], [$close, 2], [$delete, 3]],
            'right' => [[$viewInRight, 1]],
        ];
    }

    /**
     * @param array<string, array<int, mixed>> $result
     *
     * @return array<int, string>
     */
    private function namesIn(array $result): array
    {
        $names = [];
        foreach ($this->flatten($result) as $button) {
            if ($button instanceof InputButton) {
                $names[] = $button->getName();
            }
        }

        return $names;
    }

    /**
     * @param array<string, array<int, mixed>> $result
     */
    private function saveButtonIn(array $result): InputButton
    {
        foreach ($this->flatten($result) as $button) {
            if ($button instanceof InputButton) {
                return $button;
            }
        }

        self::fail('no save button left');
    }

    /**
     * @param array<string, array<int, mixed>> $result
     */
    private function hasCloseButton(array $result): bool
    {
        foreach ($this->flatten($result) as $button) {
            if ($button instanceof LinkButton && str_contains($button->getClasses(), 't3js-editform-close')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array<int, mixed>> $result
     */
    private function containsTitle(array $result, string $title): bool
    {
        foreach ($this->flatten($result) as $button) {
            if ($button->getTitle() === $title) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array<int, mixed>> $result
     *
     * @return array<int, InputButton|LinkButton>
     */
    private function flatten(array $result): array
    {
        $buttons = [];
        foreach ($result as $group) {
            foreach ($group as $entry) {
                $buttons[] = $entry[0];
            }
        }

        return $buttons;
    }
}
