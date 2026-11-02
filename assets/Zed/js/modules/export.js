/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

'use strict';

const init = () => {
    const exportButton = document.getElementById('exportButton');
    const exportJobReference = document.getElementById('exportJobReference');

    if (!exportButton || !exportJobReference) {
        return;
    }

    exportButton.addEventListener('click', (e) => {
        e.preventDefault();
        const ref = exportJobReference.value;
        window.location.href = '/data-exchange/export/download?importJobReference=' + encodeURIComponent(ref);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
