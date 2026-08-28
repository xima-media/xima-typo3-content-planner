import { defineTypo3PlaywrightConfig } from '@konradmichalik/ptu';

export default defineTypo3PlaywrightConfig({
  hostname: 'xima-typo3-content-planner.ddev.site',
  defaultVersion: '14',
  testDir: './Tests/Playwright',
});
