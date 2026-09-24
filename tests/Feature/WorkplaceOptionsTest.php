<?php

use App\Support\WorkplaceOptions;

it('resolves tax authority workplaces and spreadsheet admin labels', function () {
    expect(WorkplaceOptions::keyForLabel('الإدارة العامة'))->toBe('general_admin')
        ->and(WorkplaceOptions::keyForLabel('سبها'))->toBe('sebha')
        ->and(WorkplaceOptions::keyForLabel('صبراته'))->toBe('sabratha')
        ->and(WorkplaceOptions::keyForLabel('بني وليد'))->toBe('bani_walid')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('_ العامة'))->toBe('general_admin')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('_ طرابلس'))->toBe('tripoli')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('_ ترهونة ومسـلاته'))->toBe('tarhuna_msallata')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('_جنزور'))->toBe('janzour')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('كبار الممولين طرابلس'))->toBe('large_taxpayers_tripoli')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('كبار الممولين'))->toBe('large_taxpayers_tripoli')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('مرقب'))->toBe('al_murqub')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('ترهونة مسلاته'))->toBe('tarhuna_msallata')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('الشويرف'))->toBe('al_shuwayrif')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('العامة/الصندوق'))->toBe('general_admin')
        ->and(WorkplaceOptions::parseSpreadsheetAdmin('العامة /الحركة ونقل'))->toBe([
            'workplace' => 'general_admin',
            'office' => 'الحركة ونقل',
        ])
        ->and(WorkplaceOptions::parseSpreadsheetAdmin('صبراته / صرمان'))->toBe([
            'workplace' => 'sabratha',
            'office' => null,
        ]);
});

it('returns null for unknown workplaces', function () {
    expect(WorkplaceOptions::keyForLabel('مكان غير موجود'))->toBeNull()
        ->and(WorkplaceOptions::isKnownKey('tripoli'))->toBeTrue()
        ->and(WorkplaceOptions::isKnownKey('unknown_office'))->toBeFalse()
        ->and(WorkplaceOptions::isKnownKey(null))->toBeFalse()
        ->and(WorkplaceOptions::isKnownKey(''))->toBeFalse();
});

it('treats slash spreadsheet offices as empty', function () {
    expect(WorkplaceOptions::cleanSpreadsheetOffice('/'))->toBeNull()
        ->and(WorkplaceOptions::cleanSpreadsheetOffice('—'))->toBeNull()
        ->and(WorkplaceOptions::cleanSpreadsheetOffice(' مكتب نائب المدير العام '))->toBe('مكتب نائب المدير العام');
});
