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
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('كبار الممولين طرابلس'))->toBe('large_taxpayers_tripoli');
});

it('returns null for unknown workplaces', function () {
    expect(WorkplaceOptions::keyForLabel('مكان غير موجود'))->toBeNull();
});
