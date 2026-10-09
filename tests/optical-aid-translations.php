<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$language = $argv[1] ?? 'en';
if (!in_array($language, ['en', 'si', 'ta'], true)) { throw new InvalidArgumentException('Choose en, si or ta.'); }
$_SESSION['widms_language'] = $language;
require_once __DIR__ . '/../includes/i18n.php';

$expected = [
    'en' => ['Contact Lens', 'Spectacles', 'Contact Lens Orders'],
    'si' => ['ස්පර්ශ කාච', 'ඇස් කණ්ණාඩි', 'ස්පර්ශ කාච ඇණවුම්'],
    'ta' => ['தொடு வில்லை', 'மூக்குக் கண்ணாடி', 'தொடு வில்லை ஆணைகள்'],
][$language];
if (widmsAidItemName('Contact Lens') !== $expected[0]
    || widmsAidItemName('Spectacles') !== $expected[1]
    || t('Contact Lens Orders') !== $expected[2]
    || widmsAidItemName('Custom Hearing Aid') !== 'Custom Hearing Aid'
    || widmsAidItemName('contact lens') !== $expected[0]
) {
    throw new RuntimeException('Built-in aid translation failed for ' . $language);
}
if ($language === 'ta' && t('Contact lens handover') !== 'தொடு வில்லை ஒப்படைப்பு') {
    throw new RuntimeException('Tamil contact-lens handover terminology is inconsistent.');
}
if ($language !== 'en' && (widmsUiTranslationPayload()['Contact Lens Orders'] ?? null) !== $expected[2]) {
    throw new RuntimeException('Client-side contact-lens translation is inconsistent.');
}
$divisionMessage = 'This beneficiary is registered in %s District, %s DS Division and cannot be assigned to another DS Division.';
if ($language !== 'en' && t($divisionMessage) === $divisionMessage) {
    throw new RuntimeException('The cross-division beneficiary error is not translated for ' . $language);
}
$campPeriods = [
    'en' => ['AM','PM'],
    'si' => ['පෙ.ව.','ප.ව.'],
    'ta' => ['மு.ப.','பி.ப.'],
][$language];
if (t('AM') !== $campPeriods[0] || t('PM') !== $campPeriods[1]
    || ($language !== 'en' && (t('Hour') === 'Hour' || t('Minute') === 'Minute'))) {
    throw new RuntimeException('Vision Camp time labels are not translated for ' . $language);
}
echo "Built-in optical aid translations passed for $language.\n";
