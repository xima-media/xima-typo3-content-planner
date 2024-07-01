<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Form\Element;

use TYPO3\CMS\Backend\Backend\Avatar\Avatar;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class CurrentUser extends AbstractFormElement
{
    public function render(): array
    {
        $parameterArray = $this->data['parameterArray'];

        $fieldInformationResult = $this->renderFieldInformation();
        $fieldInformationHtml = $fieldInformationResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($this->initializeResultArray(), $fieldInformationResult, false);

        $fieldId = StringUtility::getUniqueId('formengine-textarea-');

        $attributes = [
            'id' => $fieldId,
            'name' => htmlspecialchars($parameterArray['itemFormElName']),
            'size' => 22,
            'data-formengine-input-name' => htmlspecialchars($parameterArray['itemFormElName']),
        ];

        $classes = [
            'form-control',
            't3js-formengine-textarea',
            'formengine-textarea',
        ];
        $backendUser = $GLOBALS['BE_USER'];
        $itemValue = $backendUser->getUserId();
        $itemDisplay = $backendUser->getUserName();
        $attributes['class'] = implode(' ', $classes);
        $avatar = GeneralUtility::makeInstance(Avatar::class);

        $html = [];
        $html[] = '<div class="formengine-field-item t3js-formengine-field-item">';
        $html[] = $fieldInformationHtml;
        $html[] =   '<div class="form-wizards-wrap">';
        $html[] =      '<div class="form-wizards-element">';
        $html[] =         '<div class="form-control-wrap">';
        $html[] =            '<input type="hidden" readonly value="' . $itemValue . '" ';
        $html[]=               GeneralUtility::implodeAttributes($attributes, true);
        $html[]=            ' />';
        $html[]=            '<div class="d-flex align-items-center mx-3">' . $avatar->render($backendUser->user) . $itemDisplay . '</div>';
        $html[] =         '</div>';
        $html[] =      '</div>';
        $html[] =   '</div>';
        $html[] = '</div>';
        $resultArray['html'] = implode(LF, $html);

        return $resultArray;
    }
}
