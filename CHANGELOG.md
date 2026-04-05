# Changelog

## [5.0.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.23.0...v5.0.1) (2026-04-05)


### Features

* Add 12-language translation support with 637 keys across EN, DE, FR, NL, ES, AR, IT, PT, JA, SV, DA, NO ([a3e3ee7](https://github.com/LindemannRock/craft-translation-manager/commit/a3e3ee76f3eb0a820edb658a4ea01a095a26a5fb))
* Add backup support for PHP import and fix formula injection false positive ([41a685d](https://github.com/LindemannRock/craft-translation-manager/commit/41a685d1c8ae22a38bb2dbd156b73f9cdc273b20))
* add checksum validation for backup integrity and improve logging ([cce90c9](https://github.com/LindemannRock/craft-translation-manager/commit/cce90c90811c493305d7422ca6eb2469a04ba4aa))
* Add form exclusion patterns and script-based filtering for translations ([bbc9adc](https://github.com/LindemannRock/craft-translation-manager/commit/bbc9adc6fa61e5d4330686c491b81ad5609bd538))
* Add Formie default translation strings capture with automatic usage marking ([4c0b8f7](https://github.com/LindemannRock/craft-translation-manager/commit/4c0b8f7f47567b4c0cb3ebbe6609d2c5c3fbf488))
* Add granular user permissions system ([80133c6](https://github.com/LindemannRock/craft-translation-manager/commit/80133c67675a4d83ff1d462450d2cdf7f6b2d435))
* add Info Box component for displaying informational notices ([56c8210](https://github.com/LindemannRock/craft-translation-manager/commit/56c821057e897a4e41e038a126ff4003b3e24819))
* Add instructions for importing translations from third-party plugins in README ([199c830](https://github.com/LindemannRock/craft-translation-manager/commit/199c8303de01bc56b74dca02afdccd431587658a))
* Add locale mapping, integrations, and auto-capture settings pages; update routes and templates ([90f3c89](https://github.com/LindemannRock/craft-translation-manager/commit/90f3c899c064ebe24eb5fe0947fbb50a686a1255))
* add MIT License file to the project ([31493ee](https://github.com/LindemannRock/craft-translation-manager/commit/31493ee50703c082bc76c7d943dcc98111b6edc6))
* add PHP file import with multi-language record creation ([a6757fc](https://github.com/LindemannRock/craft-translation-manager/commit/a6757fcf5a33cef5549898b1ca1138ad1de1bff8))
* add plugin credit component to settings templates ([8d29f7d](https://github.com/LindemannRock/craft-translation-manager/commit/8d29f7db60bf62ce1e20fa8543de0496a0a8ebcc))
* add runtime auto-capture for missing translations ([412edaa](https://github.com/LindemannRock/craft-translation-manager/commit/412edaaebb6c7cbf4209cc440d0e17a5b042b61d))
* add source language configuration and update translation handling ([1c9e4f1](https://github.com/LindemannRock/craft-translation-manager/commit/1c9e4f193460e999ccb57753c00e9875054c1fb8))
* add source language configuration to Translation Manager settings ([ebda2f4](https://github.com/LindemannRock/craft-translation-manager/commit/ebda2f41b3e2cc9bbe31dde287e5f6f214e58ece))
* add source language selection to Translation Sources settings ([ca92431](https://github.com/LindemannRock/craft-translation-manager/commit/ca924311ebc90fadfb5af9e48d2e2a0a7274a2b8))
* add support for capturing Google Review integration messages ([6f1bb09](https://github.com/LindemannRock/craft-translation-manager/commit/6f1bb09fa48315d7ce2611d0518ee27211b588ac))
* Add validation for logLevel setting to prevent debug in production ([daba6ed](https://github.com/LindemannRock/craft-translation-manager/commit/daba6ed9a2e519efc960b5806c4b4111be5a987e))
* add viewLogs permission to Translation Manager ([8b835ca](https://github.com/LindemannRock/craft-translation-manager/commit/8b835cad6f21b4469b84cc33fb72411bb6dad401))
* **ai:** add provider test workflow, model selectors, and mock provider ([d38c6c6](https://github.com/LindemannRock/craft-translation-manager/commit/d38c6c6d922c7da3ce24f4bf3f1d61baf62029e3))
* **backup:** add asset volume selector for backup storage ([1b56355](https://github.com/LindemannRock/craft-translation-manager/commit/1b56355d7ec98f714d6fa42ad968582d8df24051))
* **backup:** add loading states and UI improvements for volume operations ([cc800be](https://github.com/LindemannRock/craft-translation-manager/commit/cc800be7def2e3736c8b06ef88e8357a296614d9))
* **CreateBackupJob:** implement retry logic for backup job execution ([423e4c0](https://github.com/LindemannRock/craft-translation-manager/commit/423e4c0a98cfc878029267abf831184ec48669a8))
* critical security validation bugs and logging issues ([7eddc39](https://github.com/LindemannRock/craft-translation-manager/commit/7eddc3924e53be056a07e33c37553f6cbe42264a))
* Enhance backup path validation with localized error messages and prevent web-accessible backups ([1b2277f](https://github.com/LindemannRock/craft-translation-manager/commit/1b2277f3c41886ff8bdcf93e8a25ffbd584969ed))
* enhance Formie integration to capture additional button labels and messages ([a60dc4d](https://github.com/LindemannRock/craft-translation-manager/commit/a60dc4d5e52cdb4d011ba4c68517893fb067f8d9))
* enhance Google Review integration with default messages and button label ([c240ff3](https://github.com/LindemannRock/craft-translation-manager/commit/c240ff3a8d140210ee4aee90618b3ff17e3e7d01))
* enhance handling of field descriptions in TranslationsService ([400dd95](https://github.com/LindemannRock/craft-translation-manager/commit/400dd95179c4c8f7321783ba377bf186bc246270))
* Enhance log level instructions to clarify devMode requirement ([5730792](https://github.com/LindemannRock/craft-translation-manager/commit/57307927540850a407a46b8397f1bea974c77373))
* Enhance plugin initialization and streamline configuration handling ([3ad83fe](https://github.com/LindemannRock/craft-translation-manager/commit/3ad83fe062ce0c12a48e285d528465f400d5463e))
* Enhance Quick Actions with user permission checks for viewing translations ([2890571](https://github.com/LindemannRock/craft-translation-manager/commit/28905717f125fdd34688c124301b1844dd05b6ab))
* enhance translation category settings with tips and warnings ([b23cb14](https://github.com/LindemannRock/craft-translation-manager/commit/b23cb1492347e414814ff96fe8a44716fb5c5822))
* enhance TranslationManager and TranslationElement with additional properties and documentation ([e4df281](https://github.com/LindemannRock/craft-translation-manager/commit/e4df281a0c4c5ae54be4562021d315cf26775a56))
* enhance TranslationManager with plugin name helpers and improve filename generation in exports ([21d4c11](https://github.com/LindemannRock/craft-translation-manager/commit/21d4c11952802df49e40c34af3379a88524efc84))
* Enhance user permissions handling and redirection in TranslationsController ([c655d1a](https://github.com/LindemannRock/craft-translation-manager/commit/c655d1a7b4e62e056ac3bbb3f22f2c5371d0a5e5))
* **export, import:** add origin field handling in translation processes ([032a9ef](https://github.com/LindemannRock/craft-translation-manager/commit/032a9efd2bd7cbcebb34464e25bd7cd6952b64d0))
* **export:** enhance CSV export with additional metadata fields ([24114c3](https://github.com/LindemannRock/craft-translation-manager/commit/24114c39879d6ce05ae25a4f0e982f8db20bd812))
* **FormieIntegration, TranslationsService:** update Agree field handling to use getDescriptionHtml() for improved translation capture ([f4f1742](https://github.com/LindemannRock/craft-translation-manager/commit/f4f1742ddee44f75a213920b1ffd2bdcfeb4a315))
* **FormieIntegration:** enhance translation capture for Agree field descriptions ([619e34d](https://github.com/LindemannRock/craft-translation-manager/commit/619e34d8eae616f49e236a17ee7ff3f5fa414ace))
* implement a robust CSV parser to handle multiline quoted values in import/export functionality ([1efad45](https://github.com/LindemannRock/craft-translation-manager/commit/1efad452292060027a35fed48694898f742857c3))
* implement AST-based template scanning for translation detection ([59d153f](https://github.com/LindemannRock/craft-translation-manager/commit/59d153f48887ad026722c9531835bef482b53cd8))
* implement generic integration architecture and refactor Formie integration ([2f2ab43](https://github.com/LindemannRock/craft-translation-manager/commit/2f2ab4312e57749ec9cb1b07e19eb7fe3631f766))
* implement locale mapping for translations to reduce duplication and enhance export functionality ([b42edef](https://github.com/LindemannRock/craft-translation-manager/commit/b42edef65a279f432996b66cd195a60ba0865a10))
* Implement user permission checks for Quick Actions visibility ([d05f486](https://github.com/LindemannRock/craft-translation-manager/commit/d05f486720fd3bbb5f7cb60780f20d2ceac7ace9))
* **import-export:** add CSV column mapping and import preview functionality ([5a82dc7](https://github.com/LindemannRock/craft-translation-manager/commit/5a82dc766404e637f8c1796aed7c444cfcdaf3b1))
* improve backup operation UX with immediate loading feedback ([e77913f](https://github.com/LindemannRock/craft-translation-manager/commit/e77913fefa2138b2010a65c959f3dfbf1bbbe8d8))
* Improve log level validation to prevent repeated warnings in production ([e6bfa96](https://github.com/LindemannRock/craft-translation-manager/commit/e6bfa96e5207c2cf36aeb5d72aad9c15aecdc751))
* initial Translation Manager plugin implementation ([8eb2d76](https://github.com/LindemannRock/craft-translation-manager/commit/8eb2d7613702508d6ddad92d3b237a8eb67d1176))
* **maintenance:** add cleanup tools for unused translations, categories, and languages ([c4cfd19](https://github.com/LindemannRock/craft-translation-manager/commit/c4cfd19902aeb76c9c086212d671808a00de5535))
* migrate to shared base plugin ([3732107](https://github.com/LindemannRock/craft-translation-manager/commit/37321072814efd030ce43f52ba55dded944c5ef7))
* Refactor displayName method to use getFullName for plugin name ([aebd93e](https://github.com/LindemannRock/craft-translation-manager/commit/aebd93e3977096051e33258160f2b415089dec33))
* refactor settings management and improve validation in SettingsController ([0e4e036](https://github.com/LindemannRock/craft-translation-manager/commit/0e4e036fbc8b79d8d4b0483bc3d943c07494b594))
* Refine config loading logic to only override settings with explicitly set values ([3e746a7](https://github.com/LindemannRock/craft-translation-manager/commit/3e746a7b97f19ffb1cd22fc5c7d52872b2e961a3))
* Remove PluginNameExtension and PluginNameHelper classes ([0f1c51c](https://github.com/LindemannRock/craft-translation-manager/commit/0f1c51cfd5254cb49129f9c7a8e6cb781317c43a))
* **settings:** add AI translation settings and configuration options ([d892725](https://github.com/LindemannRock/craft-translation-manager/commit/d892725fd8b6ac0a9d790ea26b9e9ba1014011bc))
* simplify config loading by using Craft's native multi-environment handling ([1f3682c](https://github.com/LindemannRock/craft-translation-manager/commit/1f3682c0b4477f06698170ca18774732dbe1e6f2))
* simplify import preview to show Language only ([fc1d859](https://github.com/LindemannRock/craft-translation-manager/commit/fc1d8595c19ede5bbff3b40f274344543268bd22))
* standardize date handling in ImportController and BackupService using Db helper ([26afee1](https://github.com/LindemannRock/craft-translation-manager/commit/26afee127f65704dc7a1d2c18b010d241211f605))
* streamline Formie plugin checks using PluginHelper ([ca325b5](https://github.com/LindemannRock/craft-translation-manager/commit/ca325b5c89ecabe9c6d6a487465cf1bd7881068a))
* Switch from site-based to language-based translations ([ae74e94](https://github.com/LindemannRock/craft-translation-manager/commit/ae74e94b8e0ecbaa20c883bc0ed8dfef118466cf))
* **TranslationManager, MaintenanceController, SettingsController:** add language cleanup functionality and improve settings validation ([011f662](https://github.com/LindemannRock/craft-translation-manager/commit/011f662e84d4731322d90fcba2b4b0c857f9055a))
* **translations:** add AI draft translation functionality and update status handling ([1cf4688](https://github.com/LindemannRock/craft-translation-manager/commit/1cf468858d68862925115febf531fdcb7fa07844))
* **translations:** add bulk status update functionality for translations ([0e1abe1](https://github.com/LindemannRock/craft-translation-manager/commit/0e1abe1b145bf32748535b69d3b8f590ae2d12a7))
* **translations:** add origin filter to export functionality ([49a2853](https://github.com/LindemannRock/craft-translation-manager/commit/49a2853899c1d79302d73910eb6609481a5430e7))
* **translations:** add origin filter to translation queries ([edcb312](https://github.com/LindemannRock/craft-translation-manager/commit/edcb312fb51a29b4828c107f520ede86cae0f4ba))
* **TranslationsController, index.twig:** add audit fields to translation rows ([c0b7a62](https://github.com/LindemannRock/craft-translation-manager/commit/c0b7a6256dd5eb55169d980de04b72b5318ee2df))
* **TranslationsController:** add audit fields to translation rows ([ce6b923](https://github.com/LindemannRock/craft-translation-manager/commit/ce6b92352de2bf457ee825ae554ff4eb8498cdf7))
* **translations:** implement translation approval workflow and status handling ([0683aee](https://github.com/LindemannRock/craft-translation-manager/commit/0683aeea5ba58ac226edad4b345de21779a3708a))
* **TranslationStatsUtility:** update to retrieve statistics for enabled sites only ([cf123cc](https://github.com/LindemannRock/craft-translation-manager/commit/cf123cca0c5676e6a4b14ea1b655d0a942886bc8))
* Update backup job description to include plugin name ([ec1eee0](https://github.com/LindemannRock/craft-translation-manager/commit/ec1eee007d8dd57a95fc753ff9f53086326b0a58))
* Update backup storage volume instructions for clarity ([cf7367d](https://github.com/LindemannRock/craft-translation-manager/commit/cf7367d9ae49817fab467a3faf1baf3cf25f5d10))
* Update button text to 'Save Settings' for clarity in settings templates ([20ba20e](https://github.com/LindemannRock/craft-translation-manager/commit/20ba20ee104161ce341d71e1b1e942c0684049a3))
* update export form and PHP import handling for improved language and category selection ([37a95d1](https://github.com/LindemannRock/craft-translation-manager/commit/37a95d18ad43548f1eb24f0d268e4f86ff7f0025))
* update header to include plugin name in Translation Manager overview ([a8bf3a2](https://github.com/LindemannRock/craft-translation-manager/commit/a8bf3a23f8fa909f8280b3ff5dd98856b459c49f))
* update titles and improve layout for settings and backup pages ([68a23b8](https://github.com/LindemannRock/craft-translation-manager/commit/68a23b83ba7b5a7964f73f88a002603ef2484466))
* update translation manager utility templates and enhance backup settings documentation ([93e971f](https://github.com/LindemannRock/craft-translation-manager/commit/93e971f32ecad6204c1b3971ccfbf35a9831d2c4))
* Update user permissions labels to use dynamic settings values ([e68874d](https://github.com/LindemannRock/craft-translation-manager/commit/e68874d60d111a650ee1188498313651feed4348))


### Bug Fixes

* Add debug to check why settings logLevel is not being read ([3ebedd6](https://github.com/LindemannRock/craft-translation-manager/commit/3ebedd6c3a7fa3fbe359e2fadcc20bb8f0e7bcc3))
* add margin-top style to Backup Settings header for consistent spacing ([700f94c](https://github.com/LindemannRock/craft-translation-manager/commit/700f94c05d623916bb4a73593feaf961f1240b43))
* add margin-top style to File Generation Settings header for consistent spacing ([cb2481a](https://github.com/LindemannRock/craft-translation-manager/commit/cb2481a62cf30e71404e741f967b5c2eda7abea2))
* add missing backup reason translations and fix VolumeBackupService API ([23cd483](https://github.com/LindemannRock/craft-translation-manager/commit/23cd4839328bc66874f4352fc33dcc8e8a47737f))
* add missing cleanup backup reason translations and simplify template ([0e489b4](https://github.com/LindemannRock/craft-translation-manager/commit/0e489b4386b39721b02e082d5c6c2d63383b9550))
* add missing cleanup reason cases and set scheduled backups to system user ([b61031b](https://github.com/LindemannRock/craft-translation-manager/commit/b61031b49509050e8efb22d89a1d2e04f5fb3c1c))
* add missing cleanup reason cases and set scheduled backups to system user ([d5629eb](https://github.com/LindemannRock/craft-translation-manager/commit/d5629eb577578abdedd352d8a04b58fd2bb68b18))
* Add test log messages to verify all log levels ([a00e844](https://github.com/LindemannRock/craft-translation-manager/commit/a00e844ca41b16c70dcb3a2fe24f3a5b4dfbf932))
* **backup:** adjust backup job scheduling delay based on user settings ([d5b8664](https://github.com/LindemannRock/craft-translation-manager/commit/d5b86644c5a742643d579b398c376f5a2b844381))
* **BackupController, ExportController, ImportController, MaintenanceController, PhpImportController, TranslationsController:** update error and success messages to use translation strings ([643a279](https://github.com/LindemannRock/craft-translation-manager/commit/643a2797f9c8826393b15f0c5e17fc9d19dec242))
* **backup:** convert generator to array for file listing in BackupService ([4a9a87b](https://github.com/LindemannRock/craft-translation-manager/commit/4a9a87bc71c3a4d4bcdd311d1e86213bf336dc9d))
* **backup:** handle FsListing objects from getFileList() properly ([a9be508](https://github.com/LindemannRock/craft-translation-manager/commit/a9be508d4dfa2f323e83f276b704fcb57ba5994f))
* **backup:** implement volume backup operations and listing ([8b5ae37](https://github.com/LindemannRock/craft-translation-manager/commit/8b5ae37ae1ade5a9fa41729c97270a943644f041))
* **backup:** improve backup functionality for volume and local storage ([4472f5d](https://github.com/LindemannRock/craft-translation-manager/commit/4472f5d4b13cc410d4c15b79caefc2389d5b2139))
* **backup:** use correct Craft CMS v5 FsInterface methods for volumes ([363717a](https://github.com/LindemannRock/craft-translation-manager/commit/363717a714b607e021e16901ded3cdfa06f49e3f))
* **backup:** use Flysystem API for Servd volume operations ([72868b8](https://github.com/LindemannRock/craft-translation-manager/commit/72868b81513c4829f5897a65ee2a9c37b4d7e504))
* category selection in import/export template and update context handling in PHP import controller ([03d815f](https://github.com/LindemannRock/craft-translation-manager/commit/03d815f136b5e7f7262300d170f8bca275060227))
* Check for web request before calling getIsAjax() in volume backup listing ([d824ce8](https://github.com/LindemannRock/craft-translation-manager/commit/d824ce8abc1669ed4b319d1eecb6ec552ccffba1))
* clean up whitespace and improve markup in translations index template ([7080e0e](https://github.com/LindemannRock/craft-translation-manager/commit/7080e0e2d58c789ff0dd2f8a095382bd81988892))
* clear current site value in import/export template ([b3bc308](https://github.com/LindemannRock/craft-translation-manager/commit/b3bc30838f77fe2821646ec89421a2c73fa466b3))
* **controllers:** replace DateTimeHelper with DateFormatHelper for date formatting ([22b5878](https://github.com/LindemannRock/craft-translation-manager/commit/22b5878a699839deed006aa280eec82cac5405ee))
* correct license header formatting in LICENSE file ([0ac32cc](https://github.com/LindemannRock/craft-translation-manager/commit/0ac32cc879db1e2c04437d9fe78d3a983634afcd))
* correct query parameter in backup download link ([7af29ff](https://github.com/LindemannRock/craft-translation-manager/commit/7af29ffcbe8a47473e483e34d518d3ede7933add))
* critical security vulnerability in settings validation ([389a54f](https://github.com/LindemannRock/craft-translation-manager/commit/389a54f25437d8233377290efa5e638c1787ceba))
* customLabels handling for Formie rating fields ([7fa48b6](https://github.com/LindemannRock/craft-translation-manager/commit/7fa48b6eaedb1f328bc977dab6de4f73c7726a8e))
* Disable log viewer on Servd edge servers ([427defe](https://github.com/LindemannRock/craft-translation-manager/commit/427defec67ab68790cfce53bf8b442574502245d))
* Enable log viewer for Translation Manager on Servd integration ([9259f74](https://github.com/LindemannRock/craft-translation-manager/commit/9259f7438458c275ead7eb9ee696a785023ba0e9))
* enhance button styling for log level settings link and update subnav item for general settings ([d2e669a](https://github.com/LindemannRock/craft-translation-manager/commit/d2e669a6bf018385cac0d21757b70079038c2f25))
* enhance CreateBackupJob to calculate and display next run time for backups ([15e38ae](https://github.com/LindemannRock/craft-translation-manager/commit/15e38ae033de7e61074a69aeda7a906b05b6620d))
* enhance documentation and configuration structure for Translation Manager ([8c8bff3](https://github.com/LindemannRock/craft-translation-manager/commit/8c8bff3eea066550359a269dc2d6c342674d85dc))
* enhance logging configuration and conditionally add logs section in Translation Manager ([fc3f552](https://github.com/LindemannRock/craft-translation-manager/commit/fc3f5529129955287afd51f540c3a507414e12f9))
* enhance logging in TranslationManager and related classes ([42ee1cd](https://github.com/LindemannRock/craft-translation-manager/commit/42ee1cd5b7c23c25ef082cbe0cf46a76d6059b3b))
* enhance security measures in README and SECURITY documentation ([ad4b6b8](https://github.com/LindemannRock/craft-translation-manager/commit/ad4b6b83102937a5abaed57f8c7fceb08edc1858))
* ensure newline at end of file in BackupService.php ([ac64f90](https://github.com/LindemannRock/craft-translation-manager/commit/ac64f90d83ea835b5deeba6d6d8366e940f724ea))
* implement async loading for backups page to prevent blocking on remote volumes ([5089e4c](https://github.com/LindemannRock/craft-translation-manager/commit/5089e4c70f2648281c88e6b72ef0aa956662a3fd))
* **import-export, maintenance:** update error messages to use translation strings ([9ed8d38](https://github.com/LindemannRock/craft-translation-manager/commit/9ed8d388a8c3cd77a95ecf5e788394d5e93403a3))
* improve config override check in Settings model ([ce19d46](https://github.com/LindemannRock/craft-translation-manager/commit/ce19d46b902c628a3af2d06f215ba0ac44df27ff))
* Improve log level warning handling for console requests ([56a13da](https://github.com/LindemannRock/craft-translation-manager/commit/56a13da974e58093da5afeaa91be139a6950f272))
* improve validation for translation category to include reserved categories ([30c5d21](https://github.com/LindemannRock/craft-translation-manager/commit/30c5d21528e789c431f6e693edb40bd790d46355))
* increase log entries limit and enhance filter behavior in logs view ([7069a16](https://github.com/LindemannRock/craft-translation-manager/commit/7069a169f236cd64250b94eb429bd01bacfb1b1e))
* **index.twig:** add devMode check for PHP file import functionality ([1b2f801](https://github.com/LindemannRock/craft-translation-manager/commit/1b2f801943f6216e37cfba1a2a9874ad9eb633fd))
* **index.twig:** remove unnecessary permission check for viewing translations ([dcc4a6e](https://github.com/LindemannRock/craft-translation-manager/commit/dcc4a6eca89f7ba0f85af51054643a28aed1164c))
* **jobs:** prevent duplicate scheduling of backup jobs ([2862051](https://github.com/LindemannRock/craft-translation-manager/commit/2862051e370b49809d8a6012213464b65cd3acb1))
* **logs:** update log labels and redirect paths for clarity ([c703cf9](https://github.com/LindemannRock/craft-translation-manager/commit/c703cf947693c60859812d5ff2916b8382ecb034))
* **logs:** update permission checks and log labels for consistency ([a6c1431](https://github.com/LindemannRock/craft-translation-manager/commit/a6c1431fefa7c420861708d91263a37c88544beb))
* numeric translation keys being treated as integers ([73ca2e7](https://github.com/LindemannRock/craft-translation-manager/commit/73ca2e7634d1773aad574941afaf8b0077d08fc1))
* refine backup job query conditions in TranslationManager ([bac9cb3](https://github.com/LindemannRock/craft-translation-manager/commit/bac9cb345df73576b12fffbf82c2787beba3270f))
* Remove debug logging code ([20c9265](https://github.com/LindemannRock/craft-translation-manager/commit/20c9265cd3af46021d3c7ebe9d3b09006346bb36))
* remove emoji from Google Review integration default message ([5c380b2](https://github.com/LindemannRock/craft-translation-manager/commit/5c380b229219b2ae094eb53f164262bd5dcfc714))
* Remove icon from Rescan Templates button in maintenance settings ([76e87c7](https://github.com/LindemannRock/craft-translation-manager/commit/76e87c78c9b44f433eefdc44437c6082da9dfe9f))
* remove inline styles for table headers in translations index template ([7691f35](https://github.com/LindemannRock/craft-translation-manager/commit/7691f35a85100c604ac007a32b9fbddea21820e3))
* Remove log viewer enablement for Servd edge servers ([0e3ba98](https://github.com/LindemannRock/craft-translation-manager/commit/0e3ba9895a6d38c4e2ac565af2e77740d3867be5))
* remove logging-library repository configuration from composer.json ([0623ff6](https://github.com/LindemannRock/craft-translation-manager/commit/0623ff624d9cbcf8915391ab7d66011142e4dbde))
* Remove test log messages ([61a3621](https://github.com/LindemannRock/craft-translation-manager/commit/61a362152f9b723043645a68e2bc190793435d41))
* remove unnecessary menu header and separator from status list ([e336656](https://github.com/LindemannRock/craft-translation-manager/commit/e33665612cdb79df8dcfff7274371f58a36265fc))
* respect pluginName setting in template titles ([d912cce](https://github.com/LindemannRock/craft-translation-manager/commit/d912cce2f24178ad551d824a0b292f4ddc0cbc7a))
* **security:** address multiple security vulnerabilities ([5fc8093](https://github.com/LindemannRock/craft-translation-manager/commit/5fc8093a49022e5e5e97a242b7d8fa9150c23296))
* **security:** token-based PHP parser and legacy field cleanup ([a7b049b](https://github.com/LindemannRock/craft-translation-manager/commit/a7b049b568a8f871b04c6406ceb481fc2fcfdaf1))
* settings page and remove maintenance settings ([74f08e2](https://github.com/LindemannRock/craft-translation-manager/commit/74f08e243b65d63bc1989c0c6514110634d87df9))
* **SettingsController:** validate and sanitize settings section parameter ([427cac3](https://github.com/LindemannRock/craft-translation-manager/commit/427cac31d966c396a64f5cd88245ef23ad8948f2))
* **settings:** remove redundant save buttons from settings forms ([e103e0f](https://github.com/LindemannRock/craft-translation-manager/commit/e103e0f24221f828bf41c671ae9ee736cf61fe93))
* standardize logging format and improve initialization performance ([a365ffe](https://github.com/LindemannRock/craft-translation-manager/commit/a365ffe67f5caca6392435282a5615ee4e553778))
* streamline logging configuration in Translation Manager initialization ([479df2f](https://github.com/LindemannRock/craft-translation-manager/commit/479df2f45d4ffb378fabad508c3b83a3ecf81f6b))
* Translation Manager database schema to match working installation ([7624dbb](https://github.com/LindemannRock/craft-translation-manager/commit/7624dbb24fd8facf942fa94af7e478128a3cf926))
* **TranslationManager:** improve Twig variable registration process ([21417b7](https://github.com/LindemannRock/craft-translation-manager/commit/21417b784183063536a963349b1096447009481f))
* **TranslationManager:** read-only settings page accessibility ([b589f55](https://github.com/LindemannRock/craft-translation-manager/commit/b589f557ba6f39eb9a77bdcbfe8dd3c3b48e8d04))
* **TranslationManager:** update icon handling to use SVG file ([13b6ae2](https://github.com/LindemannRock/craft-translation-manager/commit/13b6ae29ec60bbfd947ff7ce6bdc675ea3c0d94d))
* **TranslationManager:** update labels to use translation strings ([d2c2fb5](https://github.com/LindemannRock/craft-translation-manager/commit/d2c2fb569968b933bbc8a0a577a99cfdad39e58c))
* **TranslationManager:** update version in docblock for getCpSections method to 5.21.0 ([5a1aa02](https://github.com/LindemannRock/craft-translation-manager/commit/5a1aa0279d1db56a41e51973de018bfb5346eef3))
* **TranslationsController, SettingsController:** update permissions and settings handling ([6e40abd](https://github.com/LindemannRock/craft-translation-manager/commit/6e40abd4a07afc3aa8312c6089114ee6c9baaec7))
* TypeError in FormieIntegration when handling TipTap content as array ([263a973](https://github.com/LindemannRock/craft-translation-manager/commit/263a9732edc538a117a0f76b9dc9dd2d7039b3c5))
* update author details and enhance logging documentation ([f0f5568](https://github.com/LindemannRock/craft-translation-manager/commit/f0f556854722f028dc05e10c708e2a4e4f7d29b6))
* update backup link to point to the backups page ([062fb21](https://github.com/LindemannRock/craft-translation-manager/commit/062fb21c8fee402998142759f5efb820496c5e2e))
* update form message handling to use raw properties and add TipTap to HTML conversion ([1d19ac3](https://github.com/LindemannRock/craft-translation-manager/commit/1d19ac3fb255262035465a8f899b56d7b4ddb8ba))
* update installation experience text to use translation strings ([1a03923](https://github.com/LindemannRock/craft-translation-manager/commit/1a0392399300fd213c3dcda1e8dcfa168d205470))
* update installation instructions for Composer and DDEV ([83ec00e](https://github.com/LindemannRock/craft-translation-manager/commit/83ec00ececd38e90d8905732729cbcb923316f4f))
* update license from proprietary to MIT in composer.json ([cb76760](https://github.com/LindemannRock/craft-translation-manager/commit/cb767604e60c9dda7c840cd6eefd332c050c6abb))
* update log level from 'trace' to 'debug' for Craft 5 compatibility ([53091bc](https://github.com/LindemannRock/craft-translation-manager/commit/53091bcd8861cb548bd28a639b381222209246bc))
* Update log viewer enablement condition for Servd integration ([d10e1f8](https://github.com/LindemannRock/craft-translation-manager/commit/d10e1f8f32bcf3d3a03329c0ce8dccea7f24c043))
* update logging configuration and clean up whitespace in TranslationManager ([0be50e8](https://github.com/LindemannRock/craft-translation-manager/commit/0be50e83cf9a112129ec8fa5f509086d6610d0bf))
* update README with detailed problem statements and installation instructions ([d7e6e29](https://github.com/LindemannRock/craft-translation-manager/commit/d7e6e2904bcb8399ecec8b0b1d150b8c4f026701))
* Update repository name and URLs in composer.json and README.md ([9420cee](https://github.com/LindemannRock/craft-translation-manager/commit/9420cee7a73548207c68839765751256f7e6bb06))
* Update settings navigation to use selectedSettingsItem for consistent highlighting ([a88c88f](https://github.com/LindemannRock/craft-translation-manager/commit/a88c88fdc7dce28c288d55932a2bed8ebd9fb589))
* update site translation category instructions and warnings for reserved categories ([55504da](https://github.com/LindemannRock/craft-translation-manager/commit/55504dab0c65d3f16ff79af297ee5c2708343bb7))
* update source language configuration details in README ([5f86bad](https://github.com/LindemannRock/craft-translation-manager/commit/5f86bad70353aca92730d22aa3e8ebc7d4a469cf))
* update success message for saved settings ([4862488](https://github.com/LindemannRock/craft-translation-manager/commit/48624881cfaf0dc04458453abcc138d5df8ef3e5))
* update translation category in example CSV for consistency ([dad1673](https://github.com/LindemannRock/craft-translation-manager/commit/dad167386d3c7f4eb66bef5be1c0a0d3348d3bf4))
* Update translation display to use currentLanguage for improved localization ([ada6819](https://github.com/LindemannRock/craft-translation-manager/commit/ada6819388679c22eb64e3b63fabfc4189696434))
* Update translation record queries to match unique constraint by sourceHash and siteId ([956647f](https://github.com/LindemannRock/craft-translation-manager/commit/956647ff51a9f7c39673ee28b847c21ef7450174))
* update troubleshooting guide and translation strings for clarity on category usage ([0174708](https://github.com/LindemannRock/craft-translation-manager/commit/0174708d6d21078ca7e0d3809fcb592fa467834a))
* use settings for plugin name in logging configuration ([0a02914](https://github.com/LindemannRock/craft-translation-manager/commit/0a029142e924fa9c47b23b8357ef0100dbdb7af0))
* validation bypass allowing insecure [@webroot](https://github.com/webroot) alias ([9b66943](https://github.com/LindemannRock/craft-translation-manager/commit/9b66943e1e89922b6eb09ea8ea7b387d824be428))


### Miscellaneous Chores

* **.gitignore:** reorganize entries and update file exclusions ([e345f5d](https://github.com/LindemannRock/craft-translation-manager/commit/e345f5de10cd023d67679be111737ab80cc8981f))
* add .gitattributes with export-ignore for Packagist distribution ([9f1f1a5](https://github.com/LindemannRock/craft-translation-manager/commit/9f1f1a55208fcc20a7f1985fb5358a3eb07af052))
* bump version scheme to match Craft 5 ([2f0ca33](https://github.com/LindemannRock/craft-translation-manager/commit/2f0ca334290f0a6087fd39f7c3afc79bcae185d3))
* format composer.json for consistency ([498b333](https://github.com/LindemannRock/craft-translation-manager/commit/498b333b5c30427b58389d6caab48899387f2c03))
* **main:** release 1.10.0 ([a575f3c](https://github.com/LindemannRock/craft-translation-manager/commit/a575f3ca66b6b969451aad0932db17ad336f1919))
* **main:** release 1.10.0 ([7a4e5e6](https://github.com/LindemannRock/craft-translation-manager/commit/7a4e5e6f8826c2c906a9c684e385426e42d06bac))
* **main:** release 1.11.0 ([1ae4172](https://github.com/LindemannRock/craft-translation-manager/commit/1ae41726f4e2e14c00ab4f6bbd9281b0f93b7a08))
* **main:** release 1.11.0 ([5b3f6fa](https://github.com/LindemannRock/craft-translation-manager/commit/5b3f6fab872c54edb276d7355a46e74fe910fa46))
* **main:** release 1.12.0 ([a63b237](https://github.com/LindemannRock/craft-translation-manager/commit/a63b2370d9c962cf553b6c0e7c7406bd23170145))
* **main:** release 1.12.0 ([bc0a21f](https://github.com/LindemannRock/craft-translation-manager/commit/bc0a21f7ee663ba8d73ed7fdbfab421ef80c9c5f))
* **main:** release 1.12.1 ([d922e74](https://github.com/LindemannRock/craft-translation-manager/commit/d922e740f5eef209464baf3f6c315cbdb8d8fea1))
* **main:** release 1.12.1 ([c647aaf](https://github.com/LindemannRock/craft-translation-manager/commit/c647aafc2f40de34c3f6da510065acecc6550ccc))
* **main:** release 1.12.2 ([febde2f](https://github.com/LindemannRock/craft-translation-manager/commit/febde2fd69bcce26b2d0359dec5b5cfda95904e9))
* **main:** release 1.12.2 ([7cbe299](https://github.com/LindemannRock/craft-translation-manager/commit/7cbe299701473683d8796cc9802b2a98c4fc9f72))
* **main:** release 1.12.3 ([e2348c9](https://github.com/LindemannRock/craft-translation-manager/commit/e2348c9582738cd2b060afee80b9f8365c828e81))
* **main:** release 1.12.3 ([4eda385](https://github.com/LindemannRock/craft-translation-manager/commit/4eda38572b653a97f725f251393e83104fc28d06))
* **main:** release 1.12.4 ([13cfd21](https://github.com/LindemannRock/craft-translation-manager/commit/13cfd21904c7331da595e0026e555938aeb18bc3))
* **main:** release 1.12.4 ([d7c5430](https://github.com/LindemannRock/craft-translation-manager/commit/d7c5430b8baea98709f5cff73007b0ca276143dc))
* **main:** release 1.12.5 ([785fb96](https://github.com/LindemannRock/craft-translation-manager/commit/785fb96882d51811bf7f319640880e9517138960))
* **main:** release 1.12.5 ([ce91e2e](https://github.com/LindemannRock/craft-translation-manager/commit/ce91e2e28b2661191620a01183165d7a0ae25215))
* **main:** release 1.12.6 ([30e6da5](https://github.com/LindemannRock/craft-translation-manager/commit/30e6da5de5f0446659307af483d426ecbe821c27))
* **main:** release 1.12.6 ([023eb33](https://github.com/LindemannRock/craft-translation-manager/commit/023eb334f9191a8c0e31ab6cb44a6c223cc1e15c))
* **main:** release 1.13.0 ([ff9800a](https://github.com/LindemannRock/craft-translation-manager/commit/ff9800adfd00f20ddb09bf98b8cc5648f84a842a))
* **main:** release 1.13.0 ([1b1963f](https://github.com/LindemannRock/craft-translation-manager/commit/1b1963fe83a85bb44cc1a95bd84fa52d462dcbb4))
* **main:** release 1.14.0 ([ec6a841](https://github.com/LindemannRock/craft-translation-manager/commit/ec6a841349b9f66b80056c4b2e8b654f65e681c5))
* **main:** release 1.14.0 ([4746079](https://github.com/LindemannRock/craft-translation-manager/commit/4746079815192bd47eb3749e8dba635349525114))
* **main:** release 1.14.1 ([7c18375](https://github.com/LindemannRock/craft-translation-manager/commit/7c1837500b52613778c99cac6764d0c9982be602))
* **main:** release 1.14.1 ([427d40d](https://github.com/LindemannRock/craft-translation-manager/commit/427d40d45e3f671e96b33670537b5af99c944906))
* **main:** release 1.14.2 ([7ab5953](https://github.com/LindemannRock/craft-translation-manager/commit/7ab5953b8225f7588703d813a243761666b54820))
* **main:** release 1.14.2 ([7d81eb5](https://github.com/LindemannRock/craft-translation-manager/commit/7d81eb59b5fd0efe3a7ab7572e2bee9bcbd2005a))
* **main:** release 1.14.3 ([997c81e](https://github.com/LindemannRock/craft-translation-manager/commit/997c81e71ca247700dc300e1f87f7ebb23b5e40a))
* **main:** release 1.14.3 ([f831873](https://github.com/LindemannRock/craft-translation-manager/commit/f83187345d1f05a8d0679377a3f0a53b68dd5d90))
* **main:** release 1.14.4 ([1f0c02a](https://github.com/LindemannRock/craft-translation-manager/commit/1f0c02a1d0681c145dd734fceac16bd4874b4ca3))
* **main:** release 1.14.4 ([8f9f34e](https://github.com/LindemannRock/craft-translation-manager/commit/8f9f34e5e4fa95cc74ef1da982742fdccac617fe))
* **main:** release 1.14.5 ([7a62d34](https://github.com/LindemannRock/craft-translation-manager/commit/7a62d3444668fd3e8630ae5b3a20cf5b1301f2af))
* **main:** release 1.14.5 ([4525161](https://github.com/LindemannRock/craft-translation-manager/commit/45251612016aacf2cafc99f1db33eaf3ca89306e))
* **main:** release 1.15.0 ([28077b3](https://github.com/LindemannRock/craft-translation-manager/commit/28077b3ce3e39ea78663bae920c48c0aeec5b586))
* **main:** release 1.15.0 ([d747878](https://github.com/LindemannRock/craft-translation-manager/commit/d7478784f2540a2a6acaca8a3da9a191b32a5250))
* **main:** release 1.15.1 ([aa9e128](https://github.com/LindemannRock/craft-translation-manager/commit/aa9e1285f64823e8578444ce106fb019bace4500))
* **main:** release 1.15.1 ([2344485](https://github.com/LindemannRock/craft-translation-manager/commit/2344485e60fe74b8687545a23903b27f1d985871))
* **main:** release 1.15.2 ([2b2f386](https://github.com/LindemannRock/craft-translation-manager/commit/2b2f3865ecccb014d668492a868bfa02851534fd))
* **main:** release 1.15.2 ([ac7433f](https://github.com/LindemannRock/craft-translation-manager/commit/ac7433f303ea91f3a8137453ec91ac7d6e483f6d))
* **main:** release 1.15.3 ([3bf69c7](https://github.com/LindemannRock/craft-translation-manager/commit/3bf69c7fadd1463a4be6a4da466b45d9aebf2af8))
* **main:** release 1.15.3 ([923e512](https://github.com/LindemannRock/craft-translation-manager/commit/923e512e5a15883f9e3b4cc239838bea62adc756))
* **main:** release 1.15.4 ([e948eca](https://github.com/LindemannRock/craft-translation-manager/commit/e948ecaf7bad705c68da903767321c8fff6b059b))
* **main:** release 1.15.4 ([ffd57ee](https://github.com/LindemannRock/craft-translation-manager/commit/ffd57ee09a67aa789f886b0e74d9d8115617ca29))
* **main:** release 1.16.0 ([3cb3633](https://github.com/LindemannRock/craft-translation-manager/commit/3cb363383d53ae286e4fb4a199823ccdfbddfd15))
* **main:** release 1.16.0 ([c11af17](https://github.com/LindemannRock/craft-translation-manager/commit/c11af1790102fcd67fd7f44e222725da83bab396))
* **main:** release 1.17.0 ([3999d69](https://github.com/LindemannRock/craft-translation-manager/commit/3999d6948e18182a1aa9cf206ec4b07b2253ab66))
* **main:** release 1.17.0 ([05f0bab](https://github.com/LindemannRock/craft-translation-manager/commit/05f0bab54b6068584762f6e9a1cd17e98730bc40))
* **main:** release 1.18.0 ([165506d](https://github.com/LindemannRock/craft-translation-manager/commit/165506dff9414e9cdd3714be50b740adc0139caf))
* **main:** release 1.18.0 ([59802df](https://github.com/LindemannRock/craft-translation-manager/commit/59802df80383145107454c772588d2bd9e1960be))
* **main:** release 1.19.0 ([74d2113](https://github.com/LindemannRock/craft-translation-manager/commit/74d21137d969dce91abbb41a603531d5f7c1da30))
* **main:** release 1.19.0 ([2c15937](https://github.com/LindemannRock/craft-translation-manager/commit/2c159379540a6c8b36d1d47c597041c011ae8146))
* **main:** release 1.19.1 ([c4bb02c](https://github.com/LindemannRock/craft-translation-manager/commit/c4bb02c64653d9d086a2a7aad1aa490947024109))
* **main:** release 1.19.1 ([5afd01b](https://github.com/LindemannRock/craft-translation-manager/commit/5afd01b583acf013c8d151b887fa3bf9b0a3b1fb))
* **main:** release 1.19.2 ([437b0e7](https://github.com/LindemannRock/craft-translation-manager/commit/437b0e7356d80c3f36b6d40060fb865d08b781be))
* **main:** release 1.19.2 ([f4d4e2a](https://github.com/LindemannRock/craft-translation-manager/commit/f4d4e2a3ff2e10cc66279d13e12fd85cd2d85195))
* **main:** release 1.19.3 ([3249613](https://github.com/LindemannRock/craft-translation-manager/commit/32496131334aef3e8fae6996225470e259871861))
* **main:** release 1.19.3 ([2f544e1](https://github.com/LindemannRock/craft-translation-manager/commit/2f544e17f8a5b4d7d9aa1a31bba5d3fb799d7baf))
* **main:** release 1.19.4 ([218633b](https://github.com/LindemannRock/craft-translation-manager/commit/218633b7a67344c167f4ff4002f8ffc2ced54492))
* **main:** release 1.19.4 ([32353ba](https://github.com/LindemannRock/craft-translation-manager/commit/32353ba4a7b60b41e29a3adcc4a5b9e160984f7f))
* **main:** release 1.19.5 ([9869c35](https://github.com/LindemannRock/craft-translation-manager/commit/9869c359b2c0b10cc1fb2b3eaaa5e0a83e7dcfa2))
* **main:** release 1.19.5 ([a9d3618](https://github.com/LindemannRock/craft-translation-manager/commit/a9d3618e6ee17ffd16de353b772267b074254cd4))
* **main:** release 1.19.6 ([5cc6bbd](https://github.com/LindemannRock/craft-translation-manager/commit/5cc6bbdb114a7ef5714faf7ab77cb4174e66d2c7))
* **main:** release 1.19.6 ([1055597](https://github.com/LindemannRock/craft-translation-manager/commit/1055597c619ec11bcad7957ae948cc3d2a02c52a))
* **main:** release 1.19.7 ([abdb6f8](https://github.com/LindemannRock/craft-translation-manager/commit/abdb6f850dd553a4d18d571da98bfcc8045e8156))
* **main:** release 1.19.7 ([9090b05](https://github.com/LindemannRock/craft-translation-manager/commit/9090b05d5d1e9ea2a9632103cb5ccfed313624e4))
* **main:** release 1.19.8 ([b9d67b2](https://github.com/LindemannRock/craft-translation-manager/commit/b9d67b2ff60c4315b930f56dc89f8cef5549ab31))
* **main:** release 1.19.8 ([2e06585](https://github.com/LindemannRock/craft-translation-manager/commit/2e06585a19ce3b8e4f448fcdc60a8842ad702b1f))
* **main:** release 1.20.0 ([c7d4bb8](https://github.com/LindemannRock/craft-translation-manager/commit/c7d4bb8688e694a0289699bf2c04dd7c953cc40c))
* **main:** release 1.20.0 ([8d53520](https://github.com/LindemannRock/craft-translation-manager/commit/8d535206cdd3038471578b60fcc3c75e4d846840))
* **main:** release 1.21.0 ([695b1f7](https://github.com/LindemannRock/craft-translation-manager/commit/695b1f7bfff0351cae3d0620c145b8d19e4cb35f))
* **main:** release 1.21.0 ([9643377](https://github.com/LindemannRock/craft-translation-manager/commit/9643377e0fae5421acb12f0757c2919459090661))
* **main:** release 1.21.1 ([46ecdb0](https://github.com/LindemannRock/craft-translation-manager/commit/46ecdb0cdbdf1cfe1ed91798bc97af14d09a0d92))
* **main:** release 1.21.1 ([71260b4](https://github.com/LindemannRock/craft-translation-manager/commit/71260b403f9aa89df4c17789f5ff46a8444941d8))
* **main:** release 1.21.2 ([5c520d4](https://github.com/LindemannRock/craft-translation-manager/commit/5c520d4a4de5235b7cccf6cb5e5d59638ed7f82c))
* **main:** release 1.21.2 ([d18de9c](https://github.com/LindemannRock/craft-translation-manager/commit/d18de9cbcac6f5a06cf07ccf99ab867759ba8016))
* **main:** release 1.21.3 ([81fb8b4](https://github.com/LindemannRock/craft-translation-manager/commit/81fb8b48c2d92158296775222a8547fd5f4b5c35))
* **main:** release 1.21.3 ([d1a2bf6](https://github.com/LindemannRock/craft-translation-manager/commit/d1a2bf624644664a3f1b70fc48f49ab7692d8e7b))
* **main:** release 1.21.4 ([1e73ae3](https://github.com/LindemannRock/craft-translation-manager/commit/1e73ae3a64de0b6289784aa809c3e83b5f96611f))
* **main:** release 1.21.4 ([da2e813](https://github.com/LindemannRock/craft-translation-manager/commit/da2e813e0817be478dcd9870c30111431f581bd7))
* **main:** release 1.21.5 ([b87f37d](https://github.com/LindemannRock/craft-translation-manager/commit/b87f37d99468ba501517f0d9f2819e9a17c9d5c4))
* **main:** release 1.21.5 ([7dea882](https://github.com/LindemannRock/craft-translation-manager/commit/7dea882a88a63e9e3786fd1c1935cd2fc1dfa591))
* **main:** release 1.21.6 ([cb8df3c](https://github.com/LindemannRock/craft-translation-manager/commit/cb8df3ca2632c61ab1f455fe2514ed3221fb1545))
* **main:** release 1.21.6 ([d174338](https://github.com/LindemannRock/craft-translation-manager/commit/d17433823869a74150fca36de0a998eab0e517fd))
* **main:** release 1.21.7 ([9b6adc4](https://github.com/LindemannRock/craft-translation-manager/commit/9b6adc48dc28fa2833c67ffa4117e1098911bc90))
* **main:** release 1.21.7 ([303229b](https://github.com/LindemannRock/craft-translation-manager/commit/303229b0286635eba11d670402dc317075492dda))
* **main:** release 1.21.8 ([5602ed0](https://github.com/LindemannRock/craft-translation-manager/commit/5602ed050d4f43da42e8b86b439e548f4289b6b4))
* **main:** release 1.21.8 ([104d2c8](https://github.com/LindemannRock/craft-translation-manager/commit/104d2c84262986c7482b5e6fde6ef148c7a884ec))
* **main:** release 1.21.9 ([2c2fd61](https://github.com/LindemannRock/craft-translation-manager/commit/2c2fd61a3cfba910912a8e3803e90488b5979e2f))
* **main:** release 1.21.9 ([9b7e186](https://github.com/LindemannRock/craft-translation-manager/commit/9b7e186874bea990684b8d8b18d883157831beb3))
* **main:** release 1.3.2 ([e611081](https://github.com/LindemannRock/craft-translation-manager/commit/e611081cef3ece5b472cc0029dfaf1ef366e5ace))
* **main:** release 1.3.2 ([fc65a1d](https://github.com/LindemannRock/craft-translation-manager/commit/fc65a1db656ed467f2ca2909c06de95192de1dad))
* **main:** release 1.3.3 ([f138b74](https://github.com/LindemannRock/craft-translation-manager/commit/f138b74671e20415bb91f79be69f58f128ea4ab8))
* **main:** release 1.3.3 ([0b8208b](https://github.com/LindemannRock/craft-translation-manager/commit/0b8208bfd205bccd901fe042d03a6a7917ff945f))
* **main:** release 1.3.4 ([61c8e1e](https://github.com/LindemannRock/craft-translation-manager/commit/61c8e1e80bbc92a802ad8a777fe9ab9f58dd5011))
* **main:** release 1.3.4 ([e2a5990](https://github.com/LindemannRock/craft-translation-manager/commit/e2a59905445ae3969b0081c531de6c7c5a568c4e))
* **main:** release 1.3.5 ([805e634](https://github.com/LindemannRock/craft-translation-manager/commit/805e634b34861d8021170231bf5f2186fbe4a7a1))
* **main:** release 1.3.5 ([80f9aa1](https://github.com/LindemannRock/craft-translation-manager/commit/80f9aa1cdd7bc94dbfdf22dc006444c1320ca65e))
* **main:** release 1.4.0 ([e6f8620](https://github.com/LindemannRock/craft-translation-manager/commit/e6f8620faefbf7f0a28dde6d55f585c91e52e157))
* **main:** release 1.4.0 ([2c5c9c3](https://github.com/LindemannRock/craft-translation-manager/commit/2c5c9c3f802c8af14255cf81b1e8ef4e02b36b75))
* **main:** release 1.4.1 ([601a264](https://github.com/LindemannRock/craft-translation-manager/commit/601a26489308ab01e0370b2607fa4897d0da6ba7))
* **main:** release 1.4.1 ([e178191](https://github.com/LindemannRock/craft-translation-manager/commit/e1781915c3ee13f5f4890a0624b86d11bf3c2be5))
* **main:** release 1.4.2 ([1834eab](https://github.com/LindemannRock/craft-translation-manager/commit/1834eabd75cb48fa5f67c62f03f2be560285eb3e))
* **main:** release 1.4.2 ([d9c3fe9](https://github.com/LindemannRock/craft-translation-manager/commit/d9c3fe9a43c9ccf6fd70a1a4c643a5cef2b36e8d))
* **main:** release 1.5.0 ([432502f](https://github.com/LindemannRock/craft-translation-manager/commit/432502f80b87fa87f45f3c66fd12a206efde1dec))
* **main:** release 1.5.0 ([fce3d93](https://github.com/LindemannRock/craft-translation-manager/commit/fce3d93faa35078d94fd57e37ccc69d1dae5d53c))
* **main:** release 1.6.0 ([2462c28](https://github.com/LindemannRock/craft-translation-manager/commit/2462c28a2fdd6450e674b71244c40a69c6c87927))
* **main:** release 1.6.0 ([fba73eb](https://github.com/LindemannRock/craft-translation-manager/commit/fba73eb3236f05153fea3572d8e355c47ab3afb0))
* **main:** release 1.7.0 ([9d25faa](https://github.com/LindemannRock/craft-translation-manager/commit/9d25faa3b17ba752b9683ff22cac79180f5ab29a))
* **main:** release 1.7.0 ([c271fc9](https://github.com/LindemannRock/craft-translation-manager/commit/c271fc976719b9bd913db242e57a97599a709475))
* **main:** release 1.7.1 ([5343de4](https://github.com/LindemannRock/craft-translation-manager/commit/5343de46d37d9fc04508dcb5388beeab062b5421))
* **main:** release 1.7.1 ([2c4e900](https://github.com/LindemannRock/craft-translation-manager/commit/2c4e900caa7b009de7f88ce1864e27eeda688158))
* **main:** release 1.8.0 ([7730dae](https://github.com/LindemannRock/craft-translation-manager/commit/7730daea02d4f83612cf1b74a7335eaf107a81b9))
* **main:** release 1.8.0 ([79b02e1](https://github.com/LindemannRock/craft-translation-manager/commit/79b02e15b53fb33594b93bb0203244713adf219f))
* **main:** release 1.9.0 ([6da703f](https://github.com/LindemannRock/craft-translation-manager/commit/6da703f97a61969c76d7b6aee4cdeb7950e10bc8))
* **main:** release 1.9.0 ([6c2b115](https://github.com/LindemannRock/craft-translation-manager/commit/6c2b1158c381f63e29b48016d651ba65a1d00664))
* **main:** release 5.0.0 ([d8082c5](https://github.com/LindemannRock/craft-translation-manager/commit/d8082c5a3ffd76282ed4352a0db003efb3af368c))
* **main:** release 5.0.0 ([a17cd6e](https://github.com/LindemannRock/craft-translation-manager/commit/a17cd6ebe9620524491e7e6e31a90fc8f6bf70de))
* **main:** release 5.0.1 ([1228d69](https://github.com/LindemannRock/craft-translation-manager/commit/1228d693edb7ae8bea9387c3b88cc1d60caf55bd))
* **main:** release 5.0.1 ([8bc8b68](https://github.com/LindemannRock/craft-translation-manager/commit/8bc8b6882134a54d0375056147858815ac4d1ba7))
* **main:** release 5.0.2 ([db562b4](https://github.com/LindemannRock/craft-translation-manager/commit/db562b4a5fd35a86be58621ed453618d02676db1))
* **main:** release 5.0.2 ([a5f8cf8](https://github.com/LindemannRock/craft-translation-manager/commit/a5f8cf8fbd6c289fc981ecbd9fd20a7e6e6e9c07))
* **main:** release 5.0.3 ([2962e16](https://github.com/LindemannRock/craft-translation-manager/commit/2962e16fa6fcee5cddd7a551cce94e905390f30c))
* **main:** release 5.0.3 ([fd43ad2](https://github.com/LindemannRock/craft-translation-manager/commit/fd43ad2b37d23c630a4ae2b20a15798d7cf98477))
* **main:** release 5.0.4 ([eaca8f1](https://github.com/LindemannRock/craft-translation-manager/commit/eaca8f1af8695e57c1b476e7664c771bb1723149))
* **main:** release 5.0.4 ([8f25b2f](https://github.com/LindemannRock/craft-translation-manager/commit/8f25b2f0008d54806ed40c9f892317094c3cae9a))
* **main:** release 5.0.5 ([ce979a2](https://github.com/LindemannRock/craft-translation-manager/commit/ce979a21678822ecd3e2f143333c7899d7f4b07d))
* **main:** release 5.0.5 ([fec8e01](https://github.com/LindemannRock/craft-translation-manager/commit/fec8e01986f232e66b04aeef5d273dcba2cb6251))
* **main:** release 5.0.6 ([83da21d](https://github.com/LindemannRock/craft-translation-manager/commit/83da21da8e7064ca987cd6eefc7e0a6c664ddd28))
* **main:** release 5.0.6 ([34f4359](https://github.com/LindemannRock/craft-translation-manager/commit/34f4359b23f9cc4a02cdf9f134fcc151ee40302e))
* **main:** release 5.0.7 ([657907b](https://github.com/LindemannRock/craft-translation-manager/commit/657907b25cf7f3ae4f7a60b7281225a050bbfc46))
* **main:** release 5.0.7 ([4076313](https://github.com/LindemannRock/craft-translation-manager/commit/4076313dfca00bcbd369c7c4c8607263126e15bd))
* **main:** release 5.0.8 ([f599f1c](https://github.com/LindemannRock/craft-translation-manager/commit/f599f1c28b59bb0ce81aff9881be623466d41b10))
* **main:** release 5.0.8 ([17ec582](https://github.com/LindemannRock/craft-translation-manager/commit/17ec582fff02168c310a2aad02c3e24367ef57e6))
* **main:** release 5.0.9 ([dcb964b](https://github.com/LindemannRock/craft-translation-manager/commit/dcb964b93a35095c7b6359d240c1e8e75119475c))
* **main:** release 5.0.9 ([bbbf718](https://github.com/LindemannRock/craft-translation-manager/commit/bbbf7183e3056bf191c38ac7f87fff6ac73e8820))
* **main:** release 5.1.0 ([bb3352e](https://github.com/LindemannRock/craft-translation-manager/commit/bb3352ec735007870fb06b3eba52c67b0026dbf1))
* **main:** release 5.1.0 ([189082a](https://github.com/LindemannRock/craft-translation-manager/commit/189082aa21156e59a99ea31ae16a892b68298e2e))
* **main:** release 5.10.0 ([a5bcd5e](https://github.com/LindemannRock/craft-translation-manager/commit/a5bcd5e55837bb5b1bd20fa6ee22f9e0023bc55e))
* **main:** release 5.10.0 ([83e7ca8](https://github.com/LindemannRock/craft-translation-manager/commit/83e7ca8d9a342f513587c867f92385badeaddb85))
* **main:** release 5.11.0 ([ca7fa1f](https://github.com/LindemannRock/craft-translation-manager/commit/ca7fa1fb582f4b137ae67e40c39830fbbb893d2b))
* **main:** release 5.11.0 ([782b54a](https://github.com/LindemannRock/craft-translation-manager/commit/782b54af2f1ce4a5aa0a97d28943d9aae24e1dd4))
* **main:** release 5.12.0 ([89c89bf](https://github.com/LindemannRock/craft-translation-manager/commit/89c89bf074cb7335504c9c23d10902ef43d3e376))
* **main:** release 5.12.0 ([7184cbf](https://github.com/LindemannRock/craft-translation-manager/commit/7184cbf903e80a210b349115c61562504100595c))
* **main:** release 5.13.0 ([c460e3e](https://github.com/LindemannRock/craft-translation-manager/commit/c460e3ee18bece7ef7313304893b40053f9213ec))
* **main:** release 5.13.0 ([0fd1a38](https://github.com/LindemannRock/craft-translation-manager/commit/0fd1a3876c7d41652be798ef8bf70c6e8fa26557))
* **main:** release 5.14.0 ([ecfc58f](https://github.com/LindemannRock/craft-translation-manager/commit/ecfc58f4bfdfb9efd4505513c7e2935adad63af7))
* **main:** release 5.14.0 ([d3afe2b](https://github.com/LindemannRock/craft-translation-manager/commit/d3afe2b5ad7d8e98f12fa948c9c00741e3b87baf))
* **main:** release 5.15.0 ([9415842](https://github.com/LindemannRock/craft-translation-manager/commit/941584262a3b31be65ab770b8ca256080c6e22b3))
* **main:** release 5.15.0 ([c60ef0c](https://github.com/LindemannRock/craft-translation-manager/commit/c60ef0c7d0f728f51fdb1d457e0f1c731d31f389))
* **main:** release 5.15.1 ([ed79ef3](https://github.com/LindemannRock/craft-translation-manager/commit/ed79ef3ddce9304fa43a2f0c29cc624b435b78b2))
* **main:** release 5.15.1 ([6c360d4](https://github.com/LindemannRock/craft-translation-manager/commit/6c360d4fdf3594fa395eb6ebe45c4e9f006d310d))
* **main:** release 5.16.0 ([e5f47ac](https://github.com/LindemannRock/craft-translation-manager/commit/e5f47ace8c5b69a3a7f5ae6b5453a2ef02295c87))
* **main:** release 5.16.0 ([936ad4b](https://github.com/LindemannRock/craft-translation-manager/commit/936ad4b68de51d610bde3e64593bf42c6c631603))
* **main:** release 5.17.0 ([5668a93](https://github.com/LindemannRock/craft-translation-manager/commit/5668a93d70abee1f08e1e9a20a1cf1bc73012417))
* **main:** release 5.17.0 ([dfac9bc](https://github.com/LindemannRock/craft-translation-manager/commit/dfac9bc45c874c6bb7e2afbd71e3d6fde874404d))
* **main:** release 5.17.1 ([bf61c0f](https://github.com/LindemannRock/craft-translation-manager/commit/bf61c0ff452b57e072a663a9e21a062789d406f8))
* **main:** release 5.17.1 ([d325ce1](https://github.com/LindemannRock/craft-translation-manager/commit/d325ce1eeca60a243a93363244e9ed7d1d324b49))
* **main:** release 5.18.0 ([2450ec0](https://github.com/LindemannRock/craft-translation-manager/commit/2450ec08909143c613097602e2cc0ec68f6bff1f))
* **main:** release 5.18.0 ([09871bb](https://github.com/LindemannRock/craft-translation-manager/commit/09871bb84825cee7333e5c370cabb5ce9af1293c))
* **main:** release 5.19.0 ([2f31f95](https://github.com/LindemannRock/craft-translation-manager/commit/2f31f95f803cb9a21243710e23f93f2f3aa972c9))
* **main:** release 5.19.0 ([da56980](https://github.com/LindemannRock/craft-translation-manager/commit/da5698094f08cafc1d25629715c1ab33d32c605f))
* **main:** release 5.19.1 ([af7e653](https://github.com/LindemannRock/craft-translation-manager/commit/af7e653d0fa2f2f12e591fd7cf95c1a63ac38b54))
* **main:** release 5.19.1 ([2cc5fbf](https://github.com/LindemannRock/craft-translation-manager/commit/2cc5fbfe95f82198a4ef36e5ba83e556eccc10bb))
* **main:** release 5.2.0 ([36994b9](https://github.com/LindemannRock/craft-translation-manager/commit/36994b9b784859e6923193b6dffd881cb9c13ed4))
* **main:** release 5.2.0 ([24f812e](https://github.com/LindemannRock/craft-translation-manager/commit/24f812e25ae5f3d21a9c6e091765186c856af5c7))
* **main:** release 5.20.0 ([358403f](https://github.com/LindemannRock/craft-translation-manager/commit/358403f32116d03474b71c04ee5660e849813f8c))
* **main:** release 5.20.0 ([be80a41](https://github.com/LindemannRock/craft-translation-manager/commit/be80a41bc1cf234afd54f900e0ca50d8746182a7))
* **main:** release 5.20.1 ([62eaa2f](https://github.com/LindemannRock/craft-translation-manager/commit/62eaa2f28a7080affc0de34ed318108ad1a7ba2e))
* **main:** release 5.20.1 ([f02b773](https://github.com/LindemannRock/craft-translation-manager/commit/f02b773a69f470fa33e558ffd63c07d1b222d1dc))
* **main:** release 5.21.0 ([d162b56](https://github.com/LindemannRock/craft-translation-manager/commit/d162b56408f7bf5aabef7c631d227ef3b0fc76c1))
* **main:** release 5.21.0 ([a63999c](https://github.com/LindemannRock/craft-translation-manager/commit/a63999cdc968cd5e88e0892f40d594f16cc25347))
* **main:** release 5.21.1 ([b8d24f2](https://github.com/LindemannRock/craft-translation-manager/commit/b8d24f24554e4bcd8ff6e0619a6a6630d67b6c9d))
* **main:** release 5.21.1 ([74574e2](https://github.com/LindemannRock/craft-translation-manager/commit/74574e21d7c2d0d6a56b26297e7262c906b672f7))
* **main:** release 5.21.2 ([f227f8d](https://github.com/LindemannRock/craft-translation-manager/commit/f227f8dce48f66d8a7c5d7a8e272522259d62ef4))
* **main:** release 5.21.2 ([5d337aa](https://github.com/LindemannRock/craft-translation-manager/commit/5d337aa7204465e46bd43d74e84ad8d0808ae4e2))
* **main:** release 5.21.3 ([af353a5](https://github.com/LindemannRock/craft-translation-manager/commit/af353a5b396e8ce44e35a424e70651d63c902c9a))
* **main:** release 5.21.3 ([5d9851f](https://github.com/LindemannRock/craft-translation-manager/commit/5d9851f79145002a27fd0181f9ca2e0582469365))
* **main:** release 5.22.0 ([9872484](https://github.com/LindemannRock/craft-translation-manager/commit/9872484ccb8da30869e305094c40ebf5fb5a813d))
* **main:** release 5.22.0 ([261e655](https://github.com/LindemannRock/craft-translation-manager/commit/261e65597989e6a7890c68efd11a3bcea32fe468))
* **main:** release 5.22.1 ([4dbec08](https://github.com/LindemannRock/craft-translation-manager/commit/4dbec0826d0f2ffc66b47e6eb8b3fb27b71dd86e))
* **main:** release 5.22.1 ([d1c1eff](https://github.com/LindemannRock/craft-translation-manager/commit/d1c1effd2db46e3eff35c10eca73294f4be16faf))
* **main:** release 5.22.2 ([f2b01eb](https://github.com/LindemannRock/craft-translation-manager/commit/f2b01eb0ec21aa4cbdffa3189dce9027fb64383e))
* **main:** release 5.22.2 ([b01e3f7](https://github.com/LindemannRock/craft-translation-manager/commit/b01e3f7507fdd47fe1bd86d1cfb6acbabe8e82c5))
* **main:** release 5.23.0 ([eff14e0](https://github.com/LindemannRock/craft-translation-manager/commit/eff14e0de723594ad18d0f756f7c97e3b0bd2c07))
* **main:** release 5.23.0 ([3d86149](https://github.com/LindemannRock/craft-translation-manager/commit/3d8614919793a0eca77270bfb0985a381d491690))
* **main:** release 5.3.0 ([a029dac](https://github.com/LindemannRock/craft-translation-manager/commit/a029dac4800a45ab5845caa2cc556ede1ca65917))
* **main:** release 5.3.0 ([5610e3f](https://github.com/LindemannRock/craft-translation-manager/commit/5610e3f68ceceb8895a7f6c36c8adb2934a33351))
* **main:** release 5.3.1 ([f185fd5](https://github.com/LindemannRock/craft-translation-manager/commit/f185fd571685d4ff0b25ef5483276bb9b0ad1a36))
* **main:** release 5.3.1 ([be03609](https://github.com/LindemannRock/craft-translation-manager/commit/be036097f7b0479e40a633fae2155c7cc880cdbd))
* **main:** release 5.4.0 ([a19802f](https://github.com/LindemannRock/craft-translation-manager/commit/a19802fe2d8c40325d2bffd3aecd141b2d883681))
* **main:** release 5.4.0 ([93d6fbc](https://github.com/LindemannRock/craft-translation-manager/commit/93d6fbc41c4da9f853d969a3a9ef4ebd5fa691e4))
* **main:** release 5.5.0 ([1849ab4](https://github.com/LindemannRock/craft-translation-manager/commit/1849ab4814ae4e28f6c14571554fe82a10edecda))
* **main:** release 5.5.0 ([b0c15bc](https://github.com/LindemannRock/craft-translation-manager/commit/b0c15bccb171cd3f807acaefd41257249836de14))
* **main:** release 5.6.0 ([a18fd02](https://github.com/LindemannRock/craft-translation-manager/commit/a18fd021779290bd4310cc1721d6093784aeded3))
* **main:** release 5.6.0 ([718fa9c](https://github.com/LindemannRock/craft-translation-manager/commit/718fa9c3aeb466a7bf3d63a35bdedb304bfe50e6))
* **main:** release 5.7.0 ([0147df8](https://github.com/LindemannRock/craft-translation-manager/commit/0147df89304296856d7bb03a4a4236abdb9306fa))
* **main:** release 5.7.0 ([26f9794](https://github.com/LindemannRock/craft-translation-manager/commit/26f9794132a38017317d67e47a5eba2b490de546))
* **main:** release 5.8.0 ([711c162](https://github.com/LindemannRock/craft-translation-manager/commit/711c162d8eca315cf76b77c92bb0b84f55987fd7))
* **main:** release 5.8.0 ([98ec4a9](https://github.com/LindemannRock/craft-translation-manager/commit/98ec4a9748d5398da85bf6cd2bd681993f4566e0))
* **main:** release 5.9.0 ([edc59aa](https://github.com/LindemannRock/craft-translation-manager/commit/edc59aab82ac05d8acc02f2b0f01077582a2ff0c))
* **main:** release 5.9.0 ([05bf1be](https://github.com/LindemannRock/craft-translation-manager/commit/05bf1be5cb997d82f2c2aa99d807417c4d260253))
* **main:** release 5.9.1 ([9d97bed](https://github.com/LindemannRock/craft-translation-manager/commit/9d97bedeec521b81b68982819d8c01c85afa017a))
* **main:** release 5.9.1 ([3064981](https://github.com/LindemannRock/craft-translation-manager/commit/3064981bf00f5c0e55000d1d33cf29c1a69ac599))
* **main:** release 5.9.2 ([1511642](https://github.com/LindemannRock/craft-translation-manager/commit/1511642f9e430e7278168f2fda579ef24aacd68e))
* **main:** release 5.9.2 ([3984648](https://github.com/LindemannRock/craft-translation-manager/commit/3984648f30fafd4c250f44dcb42b0b4483364c8f))
* **main:** release 5.9.3 ([109124f](https://github.com/LindemannRock/craft-translation-manager/commit/109124f27deb911b71191e29fdae4cc0d5054a19))
* **main:** release 5.9.3 ([2f76804](https://github.com/LindemannRock/craft-translation-manager/commit/2f768049b155dbb5fd409ddd9671efd10339eac8))
* **main:** release 5.9.4 ([3aa5da5](https://github.com/LindemannRock/craft-translation-manager/commit/3aa5da595d0ee33e2d58910b7df86a2e3486e142))
* **main:** release 5.9.4 ([0a15a52](https://github.com/LindemannRock/craft-translation-manager/commit/0a15a525759e210edec08a17c2c9c4b4725861d9))
* switch to Craft License for commercial release ([06ea38b](https://github.com/LindemannRock/craft-translation-manager/commit/06ea38b502da052fd88485bfc1f5c774c73bae3e))
* update logging library dependency to version 5.0 and enhance README with additional badges ([7774980](https://github.com/LindemannRock/craft-translation-manager/commit/7774980278cdf40d024fdbfa5b8a2306ccc1819e))


### Code Refactoring

* reorganize plugin navigation to separate operations from configuration ([b041f08](https://github.com/LindemannRock/craft-translation-manager/commit/b041f081832e60308d57ed50d89f0540e644021d))

## [5.23.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.22.2...v5.23.0) (2026-04-05)


### Features

* Add 12-language translation support with 637 keys across EN, DE, FR, NL, ES, AR, IT, PT, JA, SV, DA, NO ([a3e3ee7](https://github.com/LindemannRock/craft-translation-manager/commit/a3e3ee76f3eb0a820edb658a4ea01a095a26a5fb))


### Bug Fixes

* **BackupController, ExportController, ImportController, MaintenanceController, PhpImportController, TranslationsController:** update error and success messages to use translation strings ([643a279](https://github.com/LindemannRock/craft-translation-manager/commit/643a2797f9c8826393b15f0c5e17fc9d19dec242))
* **import-export, maintenance:** update error messages to use translation strings ([9ed8d38](https://github.com/LindemannRock/craft-translation-manager/commit/9ed8d388a8c3cd77a95ecf5e788394d5e93403a3))
* **TranslationManager:** read-only settings page accessibility ([b589f55](https://github.com/LindemannRock/craft-translation-manager/commit/b589f557ba6f39eb9a77bdcbfe8dd3c3b48e8d04))
* **TranslationManager:** update labels to use translation strings ([d2c2fb5](https://github.com/LindemannRock/craft-translation-manager/commit/d2c2fb569968b933bbc8a0a577a99cfdad39e58c))
* update installation experience text to use translation strings ([1a03923](https://github.com/LindemannRock/craft-translation-manager/commit/1a0392399300fd213c3dcda1e8dcfa168d205470))

## [5.22.2](https://github.com/LindemannRock/craft-translation-manager/compare/v5.22.1...v5.22.2) (2026-03-17)


### Bug Fixes

* **index.twig:** add devMode check for PHP file import functionality ([1b2f801](https://github.com/LindemannRock/craft-translation-manager/commit/1b2f801943f6216e37cfba1a2a9874ad9eb633fd))

## [5.22.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.22.0...v5.22.1) (2026-03-17)


### Bug Fixes

* **TranslationManager:** improve Twig variable registration process ([21417b7](https://github.com/LindemannRock/craft-translation-manager/commit/21417b784183063536a963349b1096447009481f))

## [5.22.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.21.3...v5.22.0) (2026-03-17)


### Features

* **ai:** add provider test workflow, model selectors, and mock provider ([d38c6c6](https://github.com/LindemannRock/craft-translation-manager/commit/d38c6c6d922c7da3ce24f4bf3f1d61baf62029e3))
* **CreateBackupJob:** implement retry logic for backup job execution ([423e4c0](https://github.com/LindemannRock/craft-translation-manager/commit/423e4c0a98cfc878029267abf831184ec48669a8))
* **export, import:** add origin field handling in translation processes ([032a9ef](https://github.com/LindemannRock/craft-translation-manager/commit/032a9efd2bd7cbcebb34464e25bd7cd6952b64d0))
* **export:** enhance CSV export with additional metadata fields ([24114c3](https://github.com/LindemannRock/craft-translation-manager/commit/24114c39879d6ce05ae25a4f0e982f8db20bd812))
* **maintenance:** add cleanup tools for unused translations, categories, and languages ([c4cfd19](https://github.com/LindemannRock/craft-translation-manager/commit/c4cfd19902aeb76c9c086212d671808a00de5535))
* **settings:** add AI translation settings and configuration options ([d892725](https://github.com/LindemannRock/craft-translation-manager/commit/d892725fd8b6ac0a9d790ea26b9e9ba1014011bc))
* **TranslationManager, MaintenanceController, SettingsController:** add language cleanup functionality and improve settings validation ([011f662](https://github.com/LindemannRock/craft-translation-manager/commit/011f662e84d4731322d90fcba2b4b0c857f9055a))
* **translations:** add AI draft translation functionality and update status handling ([1cf4688](https://github.com/LindemannRock/craft-translation-manager/commit/1cf468858d68862925115febf531fdcb7fa07844))
* **translations:** add bulk status update functionality for translations ([0e1abe1](https://github.com/LindemannRock/craft-translation-manager/commit/0e1abe1b145bf32748535b69d3b8f590ae2d12a7))
* **translations:** add origin filter to export functionality ([49a2853](https://github.com/LindemannRock/craft-translation-manager/commit/49a2853899c1d79302d73910eb6609481a5430e7))
* **translations:** add origin filter to translation queries ([edcb312](https://github.com/LindemannRock/craft-translation-manager/commit/edcb312fb51a29b4828c107f520ede86cae0f4ba))
* **TranslationsController, index.twig:** add audit fields to translation rows ([c0b7a62](https://github.com/LindemannRock/craft-translation-manager/commit/c0b7a6256dd5eb55169d980de04b72b5318ee2df))
* **TranslationsController:** add audit fields to translation rows ([ce6b923](https://github.com/LindemannRock/craft-translation-manager/commit/ce6b92352de2bf457ee825ae554ff4eb8498cdf7))
* **translations:** implement translation approval workflow and status handling ([0683aee](https://github.com/LindemannRock/craft-translation-manager/commit/0683aeea5ba58ac226edad4b345de21779a3708a))


### Bug Fixes

* **settings:** remove redundant save buttons from settings forms ([e103e0f](https://github.com/LindemannRock/craft-translation-manager/commit/e103e0f24221f828bf41c671ae9ee736cf61fe93))
* **TranslationManager:** update icon handling to use SVG file ([13b6ae2](https://github.com/LindemannRock/craft-translation-manager/commit/13b6ae29ec60bbfd947ff7ce6bdc675ea3c0d94d))

## [5.21.3](https://github.com/LindemannRock/craft-translation-manager/compare/v5.21.2...v5.21.3) (2026-02-23)


### Bug Fixes

* **SettingsController:** validate and sanitize settings section parameter ([427cac3](https://github.com/LindemannRock/craft-translation-manager/commit/427cac31d966c396a64f5cd88245ef23ad8948f2))

## [5.21.2](https://github.com/LindemannRock/craft-translation-manager/compare/v5.21.1...v5.21.2) (2026-02-22)


### Bug Fixes

* **index.twig:** remove unnecessary permission check for viewing translations ([dcc4a6e](https://github.com/LindemannRock/craft-translation-manager/commit/dcc4a6eca89f7ba0f85af51054643a28aed1164c))
* **TranslationsController, SettingsController:** update permissions and settings handling ([6e40abd](https://github.com/LindemannRock/craft-translation-manager/commit/6e40abd4a07afc3aa8312c6089114ee6c9baaec7))


### Miscellaneous Chores

* **.gitignore:** reorganize entries and update file exclusions ([e345f5d](https://github.com/LindemannRock/craft-translation-manager/commit/e345f5de10cd023d67679be111737ab80cc8981f))
* add .gitattributes with export-ignore for Packagist distribution ([9f1f1a5](https://github.com/LindemannRock/craft-translation-manager/commit/9f1f1a55208fcc20a7f1985fb5358a3eb07af052))
* switch to Craft License for commercial release ([06ea38b](https://github.com/LindemannRock/craft-translation-manager/commit/06ea38b502da052fd88485bfc1f5c774c73bae3e))

## [5.21.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.21.0...v5.21.1) (2026-02-07)


### Bug Fixes

* **controllers:** replace DateTimeHelper with DateFormatHelper for date formatting ([22b5878](https://github.com/LindemannRock/craft-translation-manager/commit/22b5878a699839deed006aa280eec82cac5405ee))

## [5.21.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.20.1...v5.21.0) (2026-02-05)


### Features

* **import-export:** add CSV column mapping and import preview functionality ([5a82dc7](https://github.com/LindemannRock/craft-translation-manager/commit/5a82dc766404e637f8c1796aed7c444cfcdaf3b1))


### Bug Fixes

* **logs:** update log labels and redirect paths for clarity ([c703cf9](https://github.com/LindemannRock/craft-translation-manager/commit/c703cf947693c60859812d5ff2916b8382ecb034))
* **logs:** update permission checks and log labels for consistency ([a6c1431](https://github.com/LindemannRock/craft-translation-manager/commit/a6c1431fefa7c420861708d91263a37c88544beb))
* **TranslationManager:** update version in docblock for getCpSections method to 5.21.0 ([5a1aa02](https://github.com/LindemannRock/craft-translation-manager/commit/5a1aa0279d1db56a41e51973de018bfb5346eef3))

## [5.20.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.20.0...v5.20.1) (2026-01-27)


### Bug Fixes

* **backup:** adjust backup job scheduling delay based on user settings ([d5b8664](https://github.com/LindemannRock/craft-translation-manager/commit/d5b86644c5a742643d579b398c376f5a2b844381))

## [5.20.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.19.1...v5.20.0) (2026-01-26)


### Features

* streamline Formie plugin checks using PluginHelper ([ca325b5](https://github.com/LindemannRock/craft-translation-manager/commit/ca325b5c89ecabe9c6d6a487465cf1bd7881068a))


### Bug Fixes

* **jobs:** prevent duplicate scheduling of backup jobs ([2862051](https://github.com/LindemannRock/craft-translation-manager/commit/2862051e370b49809d8a6012213464b65cd3acb1))
* **security:** token-based PHP parser and legacy field cleanup ([a7b049b](https://github.com/LindemannRock/craft-translation-manager/commit/a7b049b568a8f871b04c6406ceb481fc2fcfdaf1))

## [5.19.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.19.0...v5.19.1) (2026-01-22)


### Bug Fixes

* remove unnecessary menu header and separator from status list ([e336656](https://github.com/LindemannRock/craft-translation-manager/commit/e33665612cdb79df8dcfff7274371f58a36265fc))

## [5.19.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.18.0...v5.19.0) (2026-01-21)


### Features

* Add instructions for importing translations from third-party plugins in README ([199c830](https://github.com/LindemannRock/craft-translation-manager/commit/199c8303de01bc56b74dca02afdccd431587658a))
* Add locale mapping, integrations, and auto-capture settings pages; update routes and templates ([90f3c89](https://github.com/LindemannRock/craft-translation-manager/commit/90f3c899c064ebe24eb5fe0947fbb50a686a1255))


### Bug Fixes

* **security:** address multiple security vulnerabilities ([5fc8093](https://github.com/LindemannRock/craft-translation-manager/commit/5fc8093a49022e5e5e97a242b7d8fa9150c23296))

## [5.18.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.17.1...v5.18.0) (2026-01-20)


### Features

* Add backup support for PHP import and fix formula injection false positive ([41a685d](https://github.com/LindemannRock/craft-translation-manager/commit/41a685d1c8ae22a38bb2dbd156b73f9cdc273b20))

## [5.17.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.17.0...v5.17.1) (2026-01-20)


### Bug Fixes

* category selection in import/export template and update context handling in PHP import controller ([03d815f](https://github.com/LindemannRock/craft-translation-manager/commit/03d815f136b5e7f7262300d170f8bca275060227))

## [5.17.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.16.0...v5.17.0) (2026-01-20)


### Features

* add PHP file import with multi-language record creation ([a6757fc](https://github.com/LindemannRock/craft-translation-manager/commit/a6757fcf5a33cef5549898b1ca1138ad1de1bff8))
* add runtime auto-capture for missing translations ([412edaa](https://github.com/LindemannRock/craft-translation-manager/commit/412edaaebb6c7cbf4209cc440d0e17a5b042b61d))
* implement AST-based template scanning for translation detection ([59d153f](https://github.com/LindemannRock/craft-translation-manager/commit/59d153f48887ad026722c9531835bef482b53cd8))
* implement locale mapping for translations to reduce duplication and enhance export functionality ([b42edef](https://github.com/LindemannRock/craft-translation-manager/commit/b42edef65a279f432996b66cd195a60ba0865a10))
* update export form and PHP import handling for improved language and category selection ([37a95d1](https://github.com/LindemannRock/craft-translation-manager/commit/37a95d18ad43548f1eb24f0d268e4f86ff7f0025))


### Bug Fixes

* update translation category in example CSV for consistency ([dad1673](https://github.com/LindemannRock/craft-translation-manager/commit/dad167386d3c7f4eb66bef5be1c0a0d3348d3bf4))

## [5.16.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.15.1...v5.16.0) (2026-01-16)


### Features

* simplify import preview to show Language only ([fc1d859](https://github.com/LindemannRock/craft-translation-manager/commit/fc1d8595c19ede5bbff3b40f274344543268bd22))

## [5.15.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.15.0...v5.15.1) (2026-01-16)


### Bug Fixes

* Update translation display to use currentLanguage for improved localization ([ada6819](https://github.com/LindemannRock/craft-translation-manager/commit/ada6819388679c22eb64e3b63fabfc4189696434))

## [5.15.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.14.0...v5.15.0) (2026-01-16)


### Features

* Enhance backup path validation with localized error messages and prevent web-accessible backups ([1b2277f](https://github.com/LindemannRock/craft-translation-manager/commit/1b2277f3c41886ff8bdcf93e8a25ffbd584969ed))
* Switch from site-based to language-based translations ([ae74e94](https://github.com/LindemannRock/craft-translation-manager/commit/ae74e94b8e0ecbaa20c883bc0ed8dfef118466cf))
* Update button text to 'Save Settings' for clarity in settings templates ([20ba20e](https://github.com/LindemannRock/craft-translation-manager/commit/20ba20ee104161ce341d71e1b1e942c0684049a3))

## [5.14.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.13.0...v5.14.0) (2026-01-13)


### Features

* Add form exclusion patterns and script-based filtering for translations ([bbc9adc](https://github.com/LindemannRock/craft-translation-manager/commit/bbc9adc6fa61e5d4330686c491b81ad5609bd538))

## [5.13.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.12.0...v5.13.0) (2026-01-11)


### Features

* Enhance plugin initialization and streamline configuration handling ([3ad83fe](https://github.com/LindemannRock/craft-translation-manager/commit/3ad83fe062ce0c12a48e285d528465f400d5463e))
* Refactor displayName method to use getFullName for plugin name ([aebd93e](https://github.com/LindemannRock/craft-translation-manager/commit/aebd93e3977096051e33258160f2b415089dec33))
* Remove PluginNameExtension and PluginNameHelper classes ([0f1c51c](https://github.com/LindemannRock/craft-translation-manager/commit/0f1c51cfd5254cb49129f9c7a8e6cb781317c43a))

## [5.12.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.11.0...v5.12.0) (2026-01-09)


### Features

* Update backup storage volume instructions for clarity ([cf7367d](https://github.com/LindemannRock/craft-translation-manager/commit/cf7367d9ae49817fab467a3faf1baf3cf25f5d10))

## [5.11.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.10.0...v5.11.0) (2026-01-08)


### Features

* Add granular user permissions system ([80133c6](https://github.com/LindemannRock/craft-translation-manager/commit/80133c67675a4d83ff1d462450d2cdf7f6b2d435))
* Enhance Quick Actions with user permission checks for viewing translations ([2890571](https://github.com/LindemannRock/craft-translation-manager/commit/28905717f125fdd34688c124301b1844dd05b6ab))
* Enhance user permissions handling and redirection in TranslationsController ([c655d1a](https://github.com/LindemannRock/craft-translation-manager/commit/c655d1a7b4e62e056ac3bbb3f22f2c5371d0a5e5))
* Implement user permission checks for Quick Actions visibility ([d05f486](https://github.com/LindemannRock/craft-translation-manager/commit/d05f486720fd3bbb5f7cb60780f20d2ceac7ace9))
* Update user permissions labels to use dynamic settings values ([e68874d](https://github.com/LindemannRock/craft-translation-manager/commit/e68874d60d111a650ee1188498313651feed4348))


### Bug Fixes

* update success message for saved settings ([4862488](https://github.com/LindemannRock/craft-translation-manager/commit/48624881cfaf0dc04458453abcc138d5df8ef3e5))

## [5.10.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.9.4...v5.10.0) (2026-01-06)


### Features

* migrate to shared base plugin ([3732107](https://github.com/LindemannRock/craft-translation-manager/commit/37321072814efd030ce43f52ba55dded944c5ef7))

## [5.9.4](https://github.com/LindemannRock/craft-translation-manager/compare/v5.9.3...v5.9.4) (2026-01-06)


### Miscellaneous Chores

* format composer.json for consistency ([498b333](https://github.com/LindemannRock/craft-translation-manager/commit/498b333b5c30427b58389d6caab48899387f2c03))

## [5.9.3](https://github.com/LindemannRock/craft-translation-manager/compare/v5.9.2...v5.9.3) (2025-12-11)


### Bug Fixes

* update source language configuration details in README ([5f86bad](https://github.com/LindemannRock/craft-translation-manager/commit/5f86bad70353aca92730d22aa3e8ebc7d4a469cf))

## [5.9.2](https://github.com/LindemannRock/craft-translation-manager/compare/v5.9.1...v5.9.2) (2025-12-11)


### Bug Fixes

* clear current site value in import/export template ([b3bc308](https://github.com/LindemannRock/craft-translation-manager/commit/b3bc30838f77fe2821646ec89421a2c73fa466b3))
* update form message handling to use raw properties and add TipTap to HTML conversion ([1d19ac3](https://github.com/LindemannRock/craft-translation-manager/commit/1d19ac3fb255262035465a8f899b56d7b4ddb8ba))

## [5.9.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.9.0...v5.9.1) (2025-12-09)


### Bug Fixes

* remove emoji from Google Review integration default message ([5c380b2](https://github.com/LindemannRock/craft-translation-manager/commit/5c380b229219b2ae094eb53f164262bd5dcfc714))

## [5.9.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.8.0...v5.9.0) (2025-12-09)


### Features

* enhance Google Review integration with default messages and button label ([c240ff3](https://github.com/LindemannRock/craft-translation-manager/commit/c240ff3a8d140210ee4aee90618b3ff17e3e7d01))

## [5.8.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.7.0...v5.8.0) (2025-12-09)


### Features

* add support for capturing Google Review integration messages ([6f1bb09](https://github.com/LindemannRock/craft-translation-manager/commit/6f1bb09fa48315d7ce2611d0518ee27211b588ac))

## [5.7.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.6.0...v5.7.0) (2025-12-03)


### Features

* add source language configuration to Translation Manager settings ([ebda2f4](https://github.com/LindemannRock/craft-translation-manager/commit/ebda2f41b3e2cc9bbe31dde287e5f6f214e58ece))
* add source language selection to Translation Sources settings ([ca92431](https://github.com/LindemannRock/craft-translation-manager/commit/ca924311ebc90fadfb5af9e48d2e2a0a7274a2b8))
* simplify config loading by using Craft's native multi-environment handling ([1f3682c](https://github.com/LindemannRock/craft-translation-manager/commit/1f3682c0b4477f06698170ca18774732dbe1e6f2))
* update titles and improve layout for settings and backup pages ([68a23b8](https://github.com/LindemannRock/craft-translation-manager/commit/68a23b83ba7b5a7964f73f88a002603ef2484466))

## [5.6.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.5.0...v5.6.0) (2025-11-25)


### Features

* add Info Box component for displaying informational notices ([56c8210](https://github.com/LindemannRock/craft-translation-manager/commit/56c821057e897a4e41e038a126ff4003b3e24819))
* add source language configuration and update translation handling ([1c9e4f1](https://github.com/LindemannRock/craft-translation-manager/commit/1c9e4f193460e999ccb57753c00e9875054c1fb8))
* enhance TranslationManager and TranslationElement with additional properties and documentation ([e4df281](https://github.com/LindemannRock/craft-translation-manager/commit/e4df281a0c4c5ae54be4562021d315cf26775a56))
* standardize date handling in ImportController and BackupService using Db helper ([26afee1](https://github.com/LindemannRock/craft-translation-manager/commit/26afee127f65704dc7a1d2c18b010d241211f605))

## [5.5.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.4.0...v5.5.0) (2025-11-15)


### Features

* add MIT License file to the project ([31493ee](https://github.com/LindemannRock/craft-translation-manager/commit/31493ee50703c082bc76c7d943dcc98111b6edc6))


### Bug Fixes

* add margin-top style to Backup Settings header for consistent spacing ([700f94c](https://github.com/LindemannRock/craft-translation-manager/commit/700f94c05d623916bb4a73593feaf961f1240b43))
* add margin-top style to File Generation Settings header for consistent spacing ([cb2481a](https://github.com/LindemannRock/craft-translation-manager/commit/cb2481a62cf30e71404e741f967b5c2eda7abea2))

## [5.4.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.3.1...v5.4.0) (2025-11-14)


### Features

* enhance TranslationManager with plugin name helpers and improve filename generation in exports ([21d4c11](https://github.com/LindemannRock/craft-translation-manager/commit/21d4c11952802df49e40c34af3379a88524efc84))
* update header to include plugin name in Translation Manager overview ([a8bf3a2](https://github.com/LindemannRock/craft-translation-manager/commit/a8bf3a23f8fa909f8280b3ff5dd98856b459c49f))

## [5.3.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.3.0...v5.3.1) (2025-11-07)


### Bug Fixes

* enhance CreateBackupJob to calculate and display next run time for backups ([15e38ae](https://github.com/LindemannRock/craft-translation-manager/commit/15e38ae033de7e61074a69aeda7a906b05b6620d))

## [5.3.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.2.0...v5.3.0) (2025-11-07)


### Features

* add checksum validation for backup integrity and improve logging ([cce90c9](https://github.com/LindemannRock/craft-translation-manager/commit/cce90c90811c493305d7422ca6eb2469a04ba4aa))

## [5.2.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.1.0...v5.2.0) (2025-11-07)


### Features

* update translation manager utility templates and enhance backup settings documentation ([93e971f](https://github.com/LindemannRock/craft-translation-manager/commit/93e971f32ecad6204c1b3971ccfbf35a9831d2c4))

## [5.1.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.9...v5.1.0) (2025-11-06)


### Features

* implement a robust CSV parser to handle multiline quoted values in import/export functionality ([1efad45](https://github.com/LindemannRock/craft-translation-manager/commit/1efad452292060027a35fed48694898f742857c3))
* refactor settings management and improve validation in SettingsController ([0e4e036](https://github.com/LindemannRock/craft-translation-manager/commit/0e4e036fbc8b79d8d4b0483bc3d943c07494b594))


### Bug Fixes

* enhance documentation and configuration structure for Translation Manager ([8c8bff3](https://github.com/LindemannRock/craft-translation-manager/commit/8c8bff3eea066550359a269dc2d6c342674d85dc))

## [5.0.9](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.8...v5.0.9) (2025-10-26)


### Bug Fixes

* improve config override check in Settings model ([ce19d46](https://github.com/LindemannRock/craft-translation-manager/commit/ce19d46b902c628a3af2d06f215ba0ac44df27ff))

## [5.0.8](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.7...v5.0.8) (2025-10-26)


### Bug Fixes

* settings page and remove maintenance settings ([74f08e2](https://github.com/LindemannRock/craft-translation-manager/commit/74f08e243b65d63bc1989c0c6514110634d87df9))

## [5.0.7](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.6...v5.0.7) (2025-10-26)


### Bug Fixes

* clean up whitespace and improve markup in translations index template ([7080e0e](https://github.com/LindemannRock/craft-translation-manager/commit/7080e0e2d58c789ff0dd2f8a095382bd81988892))
* enhance logging in TranslationManager and related classes ([42ee1cd](https://github.com/LindemannRock/craft-translation-manager/commit/42ee1cd5b7c23c25ef082cbe0cf46a76d6059b3b))
* refine backup job query conditions in TranslationManager ([bac9cb3](https://github.com/LindemannRock/craft-translation-manager/commit/bac9cb345df73576b12fffbf82c2787beba3270f))

## [5.0.6](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.5...v5.0.6) (2025-10-22)


### Bug Fixes

* remove inline styles for table headers in translations index template ([7691f35](https://github.com/LindemannRock/craft-translation-manager/commit/7691f35a85100c604ac007a32b9fbddea21820e3))
* update logging configuration and clean up whitespace in TranslationManager ([0be50e8](https://github.com/LindemannRock/craft-translation-manager/commit/0be50e83cf9a112129ec8fa5f509086d6610d0bf))

## [5.0.5](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.4...v5.0.5) (2025-10-20)


### Bug Fixes

* customLabels handling for Formie rating fields ([7fa48b6](https://github.com/LindemannRock/craft-translation-manager/commit/7fa48b6eaedb1f328bc977dab6de4f73c7726a8e))

## [5.0.4](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.3...v5.0.4) (2025-10-20)


### Bug Fixes

* correct query parameter in backup download link ([7af29ff](https://github.com/LindemannRock/craft-translation-manager/commit/7af29ffcbe8a47473e483e34d518d3ede7933add))

## [5.0.3](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.2...v5.0.3) (2025-10-20)


### Bug Fixes

* update backup link to point to the backups page ([062fb21](https://github.com/LindemannRock/craft-translation-manager/commit/062fb21c8fee402998142759f5efb820496c5e2e))

## [5.0.2](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.1...v5.0.2) (2025-10-20)


### Bug Fixes

* implement async loading for backups page to prevent blocking on remote volumes ([5089e4c](https://github.com/LindemannRock/craft-translation-manager/commit/5089e4c70f2648281c88e6b72ef0aa956662a3fd))

## [5.0.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.0...v5.0.1) (2025-10-20)


### Miscellaneous Chores

* update logging library dependency to version 5.0 and enhance README with additional badges ([7774980](https://github.com/LindemannRock/craft-translation-manager/commit/7774980278cdf40d024fdbfa5b8a2306ccc1819e))

## [5.0.0](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.9...v5.0.0) (2025-10-20)


### Miscellaneous Chores

* bump version scheme to match Craft 5 ([2f0ca33](https://github.com/LindemannRock/craft-translation-manager/commit/2f0ca334290f0a6087fd39f7c3afc79bcae185d3))

## [1.21.9](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.8...v1.21.9) (2025-10-20)


### Code Refactoring

* reorganize plugin navigation to separate operations from configuration ([b041f08](https://github.com/LindemannRock/craft-translation-manager/commit/b041f081832e60308d57ed50d89f0540e644021d))

## [1.21.8](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.7...v1.21.8) (2025-10-17)


### Bug Fixes

* use settings for plugin name in logging configuration ([0a02914](https://github.com/LindemannRock/craft-translation-manager/commit/0a029142e924fa9c47b23b8357ef0100dbdb7af0))

## [1.21.7](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.6...v1.21.7) (2025-10-16)


### Bug Fixes

* numeric translation keys being treated as integers ([73ca2e7](https://github.com/LindemannRock/craft-translation-manager/commit/73ca2e7634d1773aad574941afaf8b0077d08fc1))

## [1.21.6](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.5...v1.21.6) (2025-10-16)


### Bug Fixes

* update installation instructions for Composer and DDEV ([83ec00e](https://github.com/LindemannRock/craft-translation-manager/commit/83ec00ececd38e90d8905732729cbcb923316f4f))

## [1.21.5](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.4...v1.21.5) (2025-10-16)


### Bug Fixes

* update license from proprietary to MIT in composer.json ([cb76760](https://github.com/LindemannRock/craft-translation-manager/commit/cb767604e60c9dda7c840cd6eefd332c050c6abb))

## [1.21.4](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.3...v1.21.4) (2025-10-16)


### Bug Fixes

* remove logging-library repository configuration from composer.json ([0623ff6](https://github.com/LindemannRock/craft-translation-manager/commit/0623ff624d9cbcf8915391ab7d66011142e4dbde))

## [1.21.3](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.2...v1.21.3) (2025-10-16)


### Bug Fixes

* update author details and enhance logging documentation ([f0f5568](https://github.com/LindemannRock/craft-translation-manager/commit/f0f556854722f028dc05e10c708e2a4e4f7d29b6))

## [1.21.2](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.1...v1.21.2) (2025-10-15)


### Bug Fixes

* ensure newline at end of file in BackupService.php ([ac64f90](https://github.com/LindemannRock/craft-translation-manager/commit/ac64f90d83ea835b5deeba6d6d8366e940f724ea))

## [1.21.1](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.0...v1.21.1) (2025-10-10)


### Bug Fixes

* add missing backup reason translations and fix VolumeBackupService API ([23cd483](https://github.com/LindemannRock/craft-translation-manager/commit/23cd4839328bc66874f4352fc33dcc8e8a47737f))
* add missing cleanup backup reason translations and simplify template ([0e489b4](https://github.com/LindemannRock/craft-translation-manager/commit/0e489b4386b39721b02e082d5c6c2d63383b9550))
* add missing cleanup reason cases and set scheduled backups to system user ([b61031b](https://github.com/LindemannRock/craft-translation-manager/commit/b61031b49509050e8efb22d89a1d2e04f5fb3c1c))
* add missing cleanup reason cases and set scheduled backups to system user ([d5629eb](https://github.com/LindemannRock/craft-translation-manager/commit/d5629eb577578abdedd352d8a04b58fd2bb68b18))

## [1.21.0](https://github.com/LindemannRock/craft-translation-manager/compare/v1.20.0...v1.21.0) (2025-10-09)


### Features

* add viewLogs permission to Translation Manager ([8b835ca](https://github.com/LindemannRock/craft-translation-manager/commit/8b835cad6f21b4469b84cc33fb72411bb6dad401))

## [1.20.0](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.8...v1.20.0) (2025-10-07)


### Features

* Add Formie default translation strings capture with automatic usage marking ([4c0b8f7](https://github.com/LindemannRock/craft-translation-manager/commit/4c0b8f7f47567b4c0cb3ebbe6609d2c5c3fbf488))

## [1.19.8](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.7...v1.19.8) (2025-10-06)


### Bug Fixes

* TypeError in FormieIntegration when handling TipTap content as array ([263a973](https://github.com/LindemannRock/craft-translation-manager/commit/263a9732edc538a117a0f76b9dc9dd2d7039b3c5))

## [1.19.7](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.6...v1.19.7) (2025-09-26)


### Bug Fixes

* Check for web request before calling getIsAjax() in volume backup listing ([d824ce8](https://github.com/LindemannRock/craft-translation-manager/commit/d824ce8abc1669ed4b319d1eecb6ec552ccffba1))

## [1.19.6](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.5...v1.19.6) (2025-09-25)


### Bug Fixes

* Remove log viewer enablement for Servd edge servers ([0e3ba98](https://github.com/LindemannRock/craft-translation-manager/commit/0e3ba9895a6d38c4e2ac565af2e77740d3867be5))

## [1.19.5](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.4...v1.19.5) (2025-09-24)


### Bug Fixes

* Disable log viewer on Servd edge servers ([427defe](https://github.com/LindemannRock/craft-translation-manager/commit/427defec67ab68790cfce53bf8b442574502245d))
* Enable log viewer for Translation Manager on Servd integration ([9259f74](https://github.com/LindemannRock/craft-translation-manager/commit/9259f7438458c275ead7eb9ee696a785023ba0e9))

## [1.19.4](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.3...v1.19.4) (2025-09-24)


### Bug Fixes

* Update log viewer enablement condition for Servd integration ([d10e1f8](https://github.com/LindemannRock/craft-translation-manager/commit/d10e1f8f32bcf3d3a03329c0ce8dccea7f24c043))

## [1.19.3](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.2...v1.19.3) (2025-09-24)


### Bug Fixes

* Remove icon from Rescan Templates button in maintenance settings ([76e87c7](https://github.com/LindemannRock/craft-translation-manager/commit/76e87c78c9b44f433eefdc44437c6082da9dfe9f))
* Update settings navigation to use selectedSettingsItem for consistent highlighting ([a88c88f](https://github.com/LindemannRock/craft-translation-manager/commit/a88c88fdc7dce28c288d55932a2bed8ebd9fb589))

## [1.19.2](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.1...v1.19.2) (2025-09-24)


### Bug Fixes

* Improve log level warning handling for console requests ([56a13da](https://github.com/LindemannRock/craft-translation-manager/commit/56a13da974e58093da5afeaa91be139a6950f272))

## [1.19.1](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.0...v1.19.1) (2025-09-24)


### Bug Fixes

* Update repository name and URLs in composer.json and README.md ([9420cee](https://github.com/LindemannRock/craft-translation-manager/commit/9420cee7a73548207c68839765751256f7e6bb06))

## [1.19.0](https://github.com/LindemannRock/translation-manager/compare/v1.18.0...v1.19.0) (2025-09-24)


### Features

* Enhance log level instructions to clarify devMode requirement ([5730792](https://github.com/LindemannRock/translation-manager/commit/57307927540850a407a46b8397f1bea974c77373))

## [1.18.0](https://github.com/LindemannRock/translation-manager/compare/v1.17.0...v1.18.0) (2025-09-24)


### Features

* Refine config loading logic to only override settings with explicitly set values ([3e746a7](https://github.com/LindemannRock/translation-manager/commit/3e746a7b97f19ffb1cd22fc5c7d52872b2e961a3))
* Update backup job description to include plugin name ([ec1eee0](https://github.com/LindemannRock/translation-manager/commit/ec1eee007d8dd57a95fc753ff9f53086326b0a58))

## [1.17.0](https://github.com/LindemannRock/translation-manager/compare/v1.16.0...v1.17.0) (2025-09-23)


### Features

* Improve log level validation to prevent repeated warnings in production ([e6bfa96](https://github.com/LindemannRock/translation-manager/commit/e6bfa96e5207c2cf36aeb5d72aad9c15aecdc751))

## [1.16.0](https://github.com/LindemannRock/translation-manager/compare/v1.15.4...v1.16.0) (2025-09-23)


### Features

* Add validation for logLevel setting to prevent debug in production ([daba6ed](https://github.com/LindemannRock/translation-manager/commit/daba6ed9a2e519efc960b5806c4b4111be5a987e))

## [1.15.4](https://github.com/LindemannRock/translation-manager/compare/v1.15.3...v1.15.4) (2025-09-23)


### Bug Fixes

* Remove test log messages ([61a3621](https://github.com/LindemannRock/translation-manager/commit/61a362152f9b723043645a68e2bc190793435d41))

## [1.15.3](https://github.com/LindemannRock/translation-manager/compare/v1.15.2...v1.15.3) (2025-09-23)


### Bug Fixes

* Add test log messages to verify all log levels ([a00e844](https://github.com/LindemannRock/translation-manager/commit/a00e844ca41b16c70dcb3a2fe24f3a5b4dfbf932))

## [1.15.2](https://github.com/LindemannRock/translation-manager/compare/v1.15.1...v1.15.2) (2025-09-23)


### Bug Fixes

* Remove debug logging code ([20c9265](https://github.com/LindemannRock/translation-manager/commit/20c9265cd3af46021d3c7ebe9d3b09006346bb36))

## [1.15.1](https://github.com/LindemannRock/translation-manager/compare/v1.15.0...v1.15.1) (2025-09-23)


### Bug Fixes

* Add debug to check why settings logLevel is not being read ([3ebedd6](https://github.com/LindemannRock/translation-manager/commit/3ebedd6c3a7fa3fbe359e2fadcc20bb8f0e7bcc3))

## [1.15.0](https://github.com/LindemannRock/translation-manager/compare/v1.14.5...v1.15.0) (2025-09-23)


### Features

* enhance translation category settings with tips and warnings ([b23cb14](https://github.com/LindemannRock/translation-manager/commit/b23cb1492347e414814ff96fe8a44716fb5c5822))


### Bug Fixes

* improve validation for translation category to include reserved categories ([30c5d21](https://github.com/LindemannRock/translation-manager/commit/30c5d21528e789c431f6e693edb40bd790d46355))
* update site translation category instructions and warnings for reserved categories ([55504da](https://github.com/LindemannRock/translation-manager/commit/55504dab0c65d3f16ff79af297ee5c2708343bb7))
* update troubleshooting guide and translation strings for clarity on category usage ([0174708](https://github.com/LindemannRock/translation-manager/commit/0174708d6d21078ca7e0d3809fcb592fa467834a))

## [1.14.5](https://github.com/LindemannRock/translation-manager/compare/v1.14.4...v1.14.5) (2025-09-22)


### Bug Fixes

* streamline logging configuration in Translation Manager initialization ([479df2f](https://github.com/LindemannRock/translation-manager/commit/479df2f45d4ffb378fabad508c3b83a3ecf81f6b))

## [1.14.4](https://github.com/LindemannRock/translation-manager/compare/v1.14.3...v1.14.4) (2025-09-22)


### Bug Fixes

* enhance logging configuration and conditionally add logs section in Translation Manager ([fc3f552](https://github.com/LindemannRock/translation-manager/commit/fc3f5529129955287afd51f540c3a507414e12f9))

## [1.14.3](https://github.com/LindemannRock/translation-manager/compare/v1.14.2...v1.14.3) (2025-09-22)


### Bug Fixes

* update log level from 'trace' to 'debug' for Craft 5 compatibility ([53091bc](https://github.com/LindemannRock/translation-manager/commit/53091bcd8861cb548bd28a639b381222209246bc))

## [1.14.2](https://github.com/LindemannRock/translation-manager/compare/v1.14.1...v1.14.2) (2025-09-22)


### Bug Fixes

* enhance button styling for log level settings link and update subnav item for general settings ([d2e669a](https://github.com/LindemannRock/translation-manager/commit/d2e669a6bf018385cac0d21757b70079038c2f25))
* increase log entries limit and enhance filter behavior in logs view ([7069a16](https://github.com/LindemannRock/translation-manager/commit/7069a169f236cd64250b94eb429bd01bacfb1b1e))
* respect pluginName setting in template titles ([d912cce](https://github.com/LindemannRock/translation-manager/commit/d912cce2f24178ad551d824a0b292f4ddc0cbc7a))

## [1.14.1](https://github.com/LindemannRock/translation-manager/compare/v1.14.0...v1.14.1) (2025-09-20)


### Bug Fixes

* standardize logging format and improve initialization performance ([a365ffe](https://github.com/LindemannRock/translation-manager/commit/a365ffe67f5caca6392435282a5615ee4e553778))

## [1.14.0](https://github.com/LindemannRock/translation-manager/compare/v1.13.0...v1.14.0) (2025-09-19)


### Features

* improve backup operation UX with immediate loading feedback ([e77913f](https://github.com/LindemannRock/translation-manager/commit/e77913fefa2138b2010a65c959f3dfbf1bbbe8d8))

## [1.13.0](https://github.com/LindemannRock/translation-manager/compare/v1.12.6...v1.13.0) (2025-09-19)


### Features

* **backup:** add loading states and UI improvements for volume operations ([cc800be](https://github.com/LindemannRock/translation-manager/commit/cc800be7def2e3736c8b06ef88e8357a296614d9))

## [1.12.6](https://github.com/LindemannRock/translation-manager/compare/v1.12.5...v1.12.6) (2025-09-19)


### Bug Fixes

* **backup:** improve backup functionality for volume and local storage ([4472f5d](https://github.com/LindemannRock/translation-manager/commit/4472f5d4b13cc410d4c15b79caefc2389d5b2139))

## [1.12.5](https://github.com/LindemannRock/translation-manager/compare/v1.12.4...v1.12.5) (2025-09-19)


### Bug Fixes

* **backup:** handle FsListing objects from getFileList() properly ([a9be508](https://github.com/LindemannRock/translation-manager/commit/a9be508d4dfa2f323e83f276b704fcb57ba5994f))

## [1.12.4](https://github.com/LindemannRock/translation-manager/compare/v1.12.3...v1.12.4) (2025-09-19)


### Bug Fixes

* **backup:** convert generator to array for file listing in BackupService ([4a9a87b](https://github.com/LindemannRock/translation-manager/commit/4a9a87bc71c3a4d4bcdd311d1e86213bf336dc9d))

## [1.12.3](https://github.com/LindemannRock/translation-manager/compare/v1.12.2...v1.12.3) (2025-09-19)


### Bug Fixes

* **backup:** use correct Craft CMS v5 FsInterface methods for volumes ([363717a](https://github.com/LindemannRock/translation-manager/commit/363717a714b607e021e16901ded3cdfa06f49e3f))

## [1.12.2](https://github.com/LindemannRock/translation-manager/compare/v1.12.1...v1.12.2) (2025-09-19)


### Bug Fixes

* **backup:** use Flysystem API for Servd volume operations ([72868b8](https://github.com/LindemannRock/translation-manager/commit/72868b81513c4829f5897a65ee2a9c37b4d7e504))

## [1.12.1](https://github.com/LindemannRock/translation-manager/compare/v1.12.0...v1.12.1) (2025-09-19)


### Bug Fixes

* **backup:** implement volume backup operations and listing ([8b5ae37](https://github.com/LindemannRock/translation-manager/commit/8b5ae37ae1ade5a9fa41729c97270a943644f041))

## [1.12.0](https://github.com/LindemannRock/translation-manager/compare/v1.11.0...v1.12.0) (2025-09-18)


### Features

* **backup:** add asset volume selector for backup storage ([1b56355](https://github.com/LindemannRock/translation-manager/commit/1b56355d7ec98f714d6fa42ad968582d8df24051))

## [1.11.0](https://github.com/LindemannRock/translation-manager/compare/v1.10.0...v1.11.0) (2025-09-15)


### Features

* **TranslationStatsUtility:** update to retrieve statistics for enabled sites only ([cf123cc](https://github.com/LindemannRock/translation-manager/commit/cf123cca0c5676e6a4b14ea1b655d0a942886bc8))

## [1.10.0](https://github.com/LindemannRock/translation-manager/compare/v1.9.0...v1.10.0) (2025-09-15)


### Features

* **FormieIntegration, TranslationsService:** update Agree field handling to use getDescriptionHtml() for improved translation capture ([f4f1742](https://github.com/LindemannRock/translation-manager/commit/f4f1742ddee44f75a213920b1ffd2bdcfeb4a315))

## [1.9.0](https://github.com/LindemannRock/translation-manager/compare/v1.8.0...v1.9.0) (2025-09-15)


### Features

* **FormieIntegration:** enhance translation capture for Agree field descriptions ([619e34d](https://github.com/LindemannRock/translation-manager/commit/619e34d8eae616f49e236a17ee7ff3f5fa414ace))

## [1.8.0](https://github.com/LindemannRock/translation-manager/compare/v1.7.1...v1.8.0) (2025-09-15)


### Features

* enhance Formie integration to capture additional button labels and messages ([a60dc4d](https://github.com/LindemannRock/translation-manager/commit/a60dc4d5e52cdb4d011ba4c68517893fb067f8d9))

## [1.7.1](https://github.com/LindemannRock/translation-manager/compare/v1.7.0...v1.7.1) (2025-09-15)


### Bug Fixes

* correct license header formatting in LICENSE file ([0ac32cc](https://github.com/LindemannRock/translation-manager/commit/0ac32cc879db1e2c04437d9fe78d3a983634afcd))

## [1.7.0](https://github.com/LindemannRock/translation-manager/compare/v1.6.0...v1.7.0) (2025-09-14)


### Features

* add plugin credit component to settings templates ([8d29f7d](https://github.com/LindemannRock/translation-manager/commit/8d29f7db60bf62ce1e20fa8543de0496a0a8ebcc))

## [1.6.0](https://github.com/LindemannRock/translation-manager/compare/v1.5.0...v1.6.0) (2025-09-13)


### Features

* enhance handling of field descriptions in TranslationsService ([400dd95](https://github.com/LindemannRock/translation-manager/commit/400dd95179c4c8f7321783ba377bf186bc246270))

## [1.5.0](https://github.com/LindemannRock/translation-manager/compare/v1.4.2...v1.5.0) (2025-09-12)


### Features

* implement generic integration architecture and refactor Formie integration ([2f2ab43](https://github.com/LindemannRock/translation-manager/commit/2f2ab4312e57749ec9cb1b07e19eb7fe3631f766))

## [1.4.2](https://github.com/LindemannRock/translation-manager/compare/v1.4.1...v1.4.2) (2025-09-11)


### Bug Fixes

* Update translation record queries to match unique constraint by sourceHash and siteId ([956647f](https://github.com/LindemannRock/translation-manager/commit/956647ff51a9f7c39673ee28b847c21ef7450174))

## [1.4.1](https://github.com/LindemannRock/translation-manager/compare/v1.4.0...v1.4.1) (2025-09-11)


### Bug Fixes

* Translation Manager database schema to match working installation ([7624dbb](https://github.com/LindemannRock/translation-manager/commit/7624dbb24fd8facf942fa94af7e478128a3cf926))

## [1.4.0](https://github.com/LindemannRock/translation-manager/compare/v1.3.5...v1.4.0) (2025-09-11)


### Features

* critical security validation bugs and logging issues ([7eddc39](https://github.com/LindemannRock/translation-manager/commit/7eddc3924e53be056a07e33c37553f6cbe42264a))

## [1.3.5](https://github.com/LindemannRock/translation-manager/compare/v1.3.4...v1.3.5) (2025-09-11)


### Bug Fixes

* validation bypass allowing insecure [@webroot](https://github.com/webroot) alias ([9b66943](https://github.com/LindemannRock/translation-manager/commit/9b66943e1e89922b6eb09ea8ea7b387d824be428))

## [1.3.4](https://github.com/LindemannRock/translation-manager/compare/v1.3.3...v1.3.4) (2025-09-11)


### Bug Fixes

* critical security vulnerability in settings validation ([389a54f](https://github.com/LindemannRock/translation-manager/commit/389a54f25437d8233377290efa5e638c1787ceba))
* enhance security measures in README and SECURITY documentation ([ad4b6b8](https://github.com/LindemannRock/translation-manager/commit/ad4b6b83102937a5abaed57f8c7fceb08edc1858))

## [1.3.3](https://github.com/LindemannRock/translation-manager/compare/v1.3.2...v1.3.3) (2025-09-10)


### Bug Fixes

* update README with detailed problem statements and installation instructions ([d7e6e29](https://github.com/LindemannRock/translation-manager/commit/d7e6e2904bcb8399ecec8b0b1d150b8c4f026701))

## 1.3.2 (2025-09-10)


### Features

* initial Translation Manager plugin implementation ([8eb2d76](https://github.com/LindemannRock/translation-manager/commit/8eb2d7613702508d6ddad92d3b237a8eb67d1176))
