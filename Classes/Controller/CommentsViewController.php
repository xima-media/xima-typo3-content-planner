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

namespace Xima\XimaTypo3ContentPlanner\Controller;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\{AssetUtility, ViewUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function htmlspecialchars;
use function implode;
use function in_array;
use function sprintf;

/**
 * CommentsViewController.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class CommentsViewController
{
    private const SORT_DIRECTIONS = ['ASC', 'DESC'];

    public function __construct(
        private readonly RecordRepository $recordRepository,
        private readonly CommentRepository $commentRepository,
    ) {}

    /**
     * The route is only registered while the embeddable comments view is enabled, so
     * reaching this action already implies the feature flag, a backend session and a valid
     * route token. The content planner's own permission checks still run here — they are
     * the same ones RecordController::commentsAction() applies.
     */
    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $table = (string) ($request->getQueryParams()['table'] ?? '');
        $uid = (int) ($request->getQueryParams()['uid'] ?? 0);

        if ('' === $table || $uid <= 0) {
            return $this->errorResponse('Both a table and a uid are required', 400);
        }

        if (!PermissionUtility::checkContentStatusVisibility()) {
            return $this->errorResponse('Content planner is not visible for this user', 403);
        }

        // Truthiness, not a null check: findByUid() returns null for a table the content
        // planner does not track and false for a missing row.
        $record = $this->recordRepository->findByUid($table, $uid, ignoreVisibilityRestriction: true);
        if (!$record) {
            return $this->errorResponse('Record not found', 404);
        }

        if (!PermissionUtility::checkAccessForRecord($table, $record)) {
            return $this->errorResponse('Access denied', 403);
        }

        return new HtmlResponse($this->renderDocument($request, $table, $uid, $record));
    }

    /**
     * @param array<string, mixed> $record
     */
    private function renderDocument(ServerRequestInterface $request, string $table, int $uid, array $record): string
    {
        $sortComments = (string) ($request->getQueryParams()['sortComments'] ?? 'DESC');
        if (!in_array($sortComments, self::SORT_DIRECTIONS, true)) {
            $sortComments = 'DESC';
        }
        $showResolvedComments = (bool) ($request->getQueryParams()['showResolvedComments'] ?? false);

        $comments = $this->commentRepository->findAllByRecord(
            $uid,
            $table,
            sortDirection: $sortComments,
            showResolved: $showResolvedComments,
        );

        $content = ViewUtility::render(
            'Default/CommentsView.html',
            [
                'comments' => $comments,
                'id' => $uid,
                'table' => $table,
                'newCommentUri' => PermissionUtility::canCreateComment() ? UrlUtility::getNewCommentUrl($table, $uid) : '',
                'shareUrl' => UrlUtility::getShareUrl($table, $uid),
                'recordUri' => UrlUtility::getRecordLink(
                    $table,
                    $uid,
                    isset($record['folder_identifier']) ? (string) $record['folder_identifier'] : null,
                ),
                'filter' => [
                    'sortComments' => $sortComments,
                    'showResolvedComments' => $showResolvedComments,
                    'resolvedCount' => $this->commentRepository->countAllByRecord($uid, $table, onlyResolved: true),
                    // The view carries no JavaScript, so the filter toggles are links that
                    // reload it with the flipped value instead of a submitting form.
                    'sortUri' => UrlUtility::getCommentsViewUrl($table, $uid, [
                        'sortComments' => 'DESC' === $sortComments ? 'ASC' : 'DESC',
                        'showResolvedComments' => $showResolvedComments ? 1 : 0,
                    ]),
                    'toggleResolvedUri' => UrlUtility::getCommentsViewUrl($table, $uid, [
                        'sortComments' => $sortComments,
                        'showResolvedComments' => $showResolvedComments ? 0 : 1,
                    ]),
                ],
            ],
        );

        return $this->renderPage($request, $content);
    }

    /**
     * Assembles the document by hand instead of going through the PageRenderer.
     *
     * PageRenderer::render() unconditionally inlines TYPO3.settings for backend requests,
     * and that object holds a URL *including its CSRF token* for every registered backend
     * AJAX route. Handing that to a document whose whole purpose is to be embedded by
     * another page is not acceptable: where the embedder is same-origin — a frontend on the
     * same host, which is the motivating case for this view — it can simply read
     * frame.contentWindow.TYPO3.settings and walk away with every backend token.
     *
     * What we give up is PageRenderer's <html> attribute handling, so the theme and colour
     * scheme resolution below deliberately mirrors PageRenderer::setDefaultHtmlTag().
     */
    private function renderPage(ServerRequestInterface $request, string $content): string
    {
        $languageService = $this->getLanguageService();
        $title = $languageService->sL(
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:comment.view.title',
        );

        return implode("\n", [
            '<!DOCTYPE html>',
            '<html '.GeneralUtility::implodeAttributes($this->htmlTagAttributes($languageService), true).'>',
            '<head>',
            '<meta charset="utf-8">',
            '<meta name="viewport" content="width=device-width, initial-scale=1">',
            // Embedded or not, this is backend content and has no business in an index.
            '<meta name="robots" content="noindex, nofollow">',
            '<title>'.htmlspecialchars($title, \ENT_QUOTES).'</title>',
            $this->cssTags($request),
            '</head>',
            '<body>',
            $content,
            '</body>',
            '</html>',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function htmlTagAttributes(LanguageService $languageService): array
    {
        $locale = $languageService->getLocale();
        $attributes = ['lang' => $locale->getName()];
        if ($locale->isRightToLeftLanguageDirection()) {
            $attributes['dir'] = 'rtl';
        }

        $backendUser = $this->getBackendUser();
        if (null === $backendUser) {
            return $attributes;
        }

        // backend.css keys its dark variables off these attributes, so the embedded view
        // follows the same user preference the backend itself does.
        $userTsConfig = $backendUser->getTSConfig();

        $theme = (string) ($backendUser->uc['theme'] ?? $userTsConfig['setup.']['fields.']['theme'] ?? 'auto');
        if ('1' === ($userTsConfig['setup.']['fields.']['theme.']['disabled'] ?? '0')) {
            $theme = (string) ($userTsConfig['setup.']['fields.']['theme'] ?? 'modern');
        }
        if ('modern' !== $theme) {
            $attributes['data-theme'] = $theme;
        }

        $colorScheme = (string) ($backendUser->uc['colorScheme'] ?? $userTsConfig['setup.']['fields.']['colorScheme'] ?? 'auto');
        if ('1' === ($userTsConfig['setup.']['fields.']['colorScheme.']['disabled'] ?? '0')) {
            $colorScheme = (string) ($userTsConfig['setup.']['fields.']['colorScheme'] ?? 'light');
        }
        if ('auto' !== $colorScheme) {
            $attributes['data-color-scheme'] = $colorScheme;
        }

        return $attributes;
    }

    /**
     * backend.css carries the Bootstrap layer the comment markup and Comments.css are
     * written against (.btn, .badge, --bs-* custom properties). Without it the thread
     * renders unstyled, which is precisely what the embedding page must not have to fix.
     */
    private function cssTags(ServerRequestInterface $request): string
    {
        $nonce = $request->getAttribute('nonce');
        $attributes = null === $nonce ? [] : ['nonce' => (string) $nonce];

        $tags = [];
        foreach ([
            'EXT:backend/Resources/Public/Css/backend.css',
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Comments.css',
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/CommentsView.css',
        ] as $file) {
            $tags[] = AssetUtility::getCssTag($file, $attributes);
        }

        return implode("\n", $tags);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        return $backendUser instanceof BackendUserAuthentication ? $backendUser : null;
    }

    /**
     * A document route answers with a document, including when it refuses. The message is
     * intentionally terse — it is shown inside somebody else's iframe.
     */
    private function errorResponse(string $message, int $status): HtmlResponse
    {
        $body = sprintf(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>%1$s</title></head><body><p>%1$s</p></body></html>',
            htmlspecialchars($message, \ENT_QUOTES),
        );

        return new HtmlResponse($body, $status);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
