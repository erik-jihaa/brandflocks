<?php

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    http_response_code(404);
    exit;
}

/**
 * ContentFlow Pulldown — zelfde patroon als frontend Pulldown.tsx.
 *
 * @param list<array{value: string, label: string}> $options
 * @param array{id?: string, class?: string, aria-label?: string} $attrs
 */
function li_render_pulldown(
    string $name,
    array $options,
    ?string $selectedValue = null,
    string $placeholder = '',
    array $attrs = [],
): string {
    $selectedValue = (string) ($selectedValue ?? '');
    $displayLabel = $placeholder;
    foreach ($options as $option) {
        if ((string) ($option['value'] ?? '') === $selectedValue) {
            $displayLabel = (string) ($option['label'] ?? '');
            break;
        }
    }
    if ($displayLabel === '' && $options !== []) {
        $displayLabel = (string) ($options[0]['label'] ?? '');
    }

    $id = trim((string) ($attrs['id'] ?? $name));
    $extraClass = trim((string) ($attrs['class'] ?? ''));
    $ariaLabel = trim((string) ($attrs['aria-label'] ?? ($placeholder !== '' ? $placeholder : $name)));
    $rootClass = trim('cf-pulldown pulldown-box' . ($extraClass !== '' ? ' ' . $extraClass : ''));

    $html = '<div class="' . li_h($rootClass) . '" data-cf-pulldown>';
    $html .= '<input type="hidden" name="' . li_h($name) . '" id="' . li_h($id) . '" value="' . li_h($selectedValue) . '">';
    $html .= '<button type="button" class="cf-pulldown-toggle" aria-haspopup="listbox" aria-expanded="false" aria-label="' . li_h($ariaLabel) . '">';
    $html .= '<span class="cf-pulldown-label">' . li_h($displayLabel) . '</span>';
    $html .= '<img src="assets/arrow.svg" alt="" class="cf-pulldown-arrow" width="11" height="11">';
    $html .= '</button>';
    $html .= '<ul class="cf-pulldown-options" role="listbox" hidden>';

    foreach ($options as $option) {
        $value = (string) ($option['value'] ?? '');
        $label = (string) ($option['label'] ?? '');
        $isSelected = $value === $selectedValue;
        $itemClass = 'cf-pulldown-option' . ($isSelected ? ' is-selected' : '');
        $html .= '<li role="none">';
        $html .= '<button type="button" class="' . li_h($itemClass) . '" role="option" aria-selected="' . ($isSelected ? 'true' : 'false') . '" data-value="' . li_h($value) . '" data-label="' . li_h($label) . '">';
        $html .= li_h($label);
        $html .= '</button></li>';
    }

    $html .= '</ul></div>';

    return $html;
}

/** @return list<array{value: string, label: string}> */
function li_branche_pulldown_options(): array
{
    $options = [
        ['value' => '', 'label' => 'Branche (optioneel)'],
    ];
    foreach (li_branche_form_options() as $option) {
        $options[] = $option;
    }
    $options[] = ['value' => 'overig', 'label' => 'Overig / geen goede match'];

    return $options;
}
