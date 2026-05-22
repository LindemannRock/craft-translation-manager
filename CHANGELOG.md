# Changelog

## [5.24.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.23.3...v5.24.0) - 2026-05-22


### ⚠ BREAKING CHANGES

* rename ExportService → GenerationService and split file generation from CSV downloads

### Added

* add backup option for PHP imports with user feedback ([c6900ff](https://github.com/LindemannRock/craft-translation-manager/commit/c6900fff18bfe3110879f9771c0f4e7f48293211))
* add margin to backup option field in import/export template ([b03b80d](https://github.com/LindemannRock/craft-translation-manager/commit/b03b80de79a02d7755bc5804b358d2cbed6c13bf))
* add scheduled usage recheck functionality and settings ([dbb9cd0](https://github.com/LindemannRock/craft-translation-manager/commit/dbb9cd06b46fbb2aea08be1546a4942e9ba2ee10))
* add SiteLanguageHelper for resolving Craft site IDs from language codes ([cba5dcd](https://github.com/LindemannRock/craft-translation-manager/commit/cba5dcdb20d452fd25100061fa0aca64cbbab979))
* add test for saving import history records in PHP import ([4bc856a](https://github.com/LindemannRock/craft-translation-manager/commit/4bc856a272b850ba5fe2a6cd99b11dc2366e6ee8))
* enhance skip patterns instructions with info box component ([1a0db76](https://github.com/LindemannRock/craft-translation-manager/commit/1a0db7695298c49abc5ca940504a4e8a71d2cb0a))
* **i18n:** add "Select PHP file" translations for multiple languages ([85a28a4](https://github.com/LindemannRock/craft-translation-manager/commit/85a28a478013c1e718cde51accaf67f63b856a00))
* **i18n:** add category sorting and improve translation validation ([44db73c](https://github.com/LindemannRock/craft-translation-manager/commit/44db73ce89b0b85733e0381878749d34f24a86a7))
* **i18n:** add category validation messages in multiple languages ([1bb7310](https://github.com/LindemannRock/craft-translation-manager/commit/1bb7310bf3a8f370022e54e69f8696ffbe85f16c))
* **i18n:** add color sets for translation types and origins ([64d78a8](https://github.com/LindemannRock/craft-translation-manager/commit/64d78a813612ab0bb80ef208baa17891c168d229))
* **i18n:** add migration and deletion messages for mapped-source languages ([c4719cc](https://github.com/LindemannRock/craft-translation-manager/commit/c4719cc7056d8d79a679faa730a52d179d99e0f9))
* **i18n:** add migration and deletion messages for multiple languages ([faead1d](https://github.com/LindemannRock/craft-translation-manager/commit/faead1d11b28f9ae793a7c77bcffada43f9b79f3))
* **i18n:** add new cleanup messages for languages and categories in multiple translations ([605000e](https://github.com/LindemannRock/craft-translation-manager/commit/605000e9efae7740722dd6e62a7a2a167d563f51))
* **i18n:** add new translation keys for approval and backup settings ([abee5c6](https://github.com/LindemannRock/craft-translation-manager/commit/abee5c60b730e6ed911d802400bd5fb3257941fc))
* **i18n:** add translation issue template for reporting problems ([351537c](https://github.com/LindemannRock/craft-translation-manager/commit/351537c85b80ee6398bf159901e3ae1533848d19))
* **i18n:** add translation keys for capture missing translations settings ([f7d7dcb](https://github.com/LindemannRock/craft-translation-manager/commit/f7d7dcb70bef5aa301906155992e04dedec08125))
* **i18n:** update German, Spanish, Italian, Dutch, and Portuguese translations ([0e614b0](https://github.com/LindemannRock/craft-translation-manager/commit/0e614b02f948e5a58b94c48bc319cb0f3f8420bb))
* **i18n:** update translation types and origins with dynamic colors ([d0e58df](https://github.com/LindemannRock/craft-translation-manager/commit/d0e58df3995e22e853b0c68ecaec810652beff77))
* **i18n:** update translations for Dutch, Norwegian, Portuguese, and Swedish ([072de7c](https://github.com/LindemannRock/craft-translation-manager/commit/072de7ceaa218c103177bb35a53b0ae451698775))
* implement import history logging for PHP imports ([c6346e5](https://github.com/LindemannRock/craft-translation-manager/commit/c6346e558daff111137e6627f6e4c19a94c353d9))
* implement migration and deletion for mapped-source languages ([7471dda](https://github.com/LindemannRock/craft-translation-manager/commit/7471dda65f2642342857d8de110f87454e83c6ee))
* **import:** add warning info box for category import status ([9356cfe](https://github.com/LindemannRock/craft-translation-manager/commit/9356cfe4f3f88a18d94f5211477efb38629be2f2))
* **import:** enhance category import validation and registration process ([0b2e9e4](https://github.com/LindemannRock/craft-translation-manager/commit/0b2e9e42628f1a44e42cc007b78fe9909d97ab0d))
* **import:** streamline translation import process and update data handling ([f90b789](https://github.com/LindemannRock/craft-translation-manager/commit/f90b7897478884a9a76056bbd09f0f1e3109f89c))
* **import:** update file selection labels for clarity ([45214d2](https://github.com/LindemannRock/craft-translation-manager/commit/45214d2bbbf9505603622ddd9bd6e25ba956e8f7))
* **languages:** update unique language retrieval to use canonical mappings ([25a6d67](https://github.com/LindemannRock/craft-translation-manager/commit/25a6d67acdebb4aab7cf3a8cb13d5c1cd6e09a86))
* **tests:** add integration tests for translation management functionality ([7065f4b](https://github.com/LindemannRock/craft-translation-manager/commit/7065f4b4cd27ab316c183ed3183262ef3fbd4e88))
* **tests:** add manual CSV fixtures for multi-site import/export testing ([94176d3](https://github.com/LindemannRock/craft-translation-manager/commit/94176d3747ee4bdf9c476f9428bf58ca30cdce6e))
* **translations:** add usage recheck messages for multiple languages ([13a981f](https://github.com/LindemannRock/craft-translation-manager/commit/13a981fc20c7608ab5d20a120e0ab2b5355126dd))
* **translations:** enhance parameter validation and permission handling in translations index ([efdd573](https://github.com/LindemannRock/craft-translation-manager/commit/efdd5734bf5824e0a3776dada0339aeb8a732496))
* **translations:** optimize multi-site translation creation and updates ([33d3bae](https://github.com/LindemannRock/craft-translation-manager/commit/33d3bae9b1a588683bd550903824cf4d1cc0111f))


### Fixed

* **i18n:** correct translation placeholders for user prompts ([48fa0fa](https://github.com/LindemannRock/craft-translation-manager/commit/48fa0fa11f02974afd4b3a28cea9d4f851b10d52))
* **i18n:** correct translation strings for skip patterns in multiple languages ([dcd1ef9](https://github.com/LindemannRock/craft-translation-manager/commit/dcd1ef915c37b010fc79ee8e6281f2a0930b1838))
* **phpstan:** replace relative path with rootDir for PHPStan config ([1df1f5c](https://github.com/LindemannRock/craft-translation-manager/commit/1df1f5c533b428bacfb6d0110b4b1b35d24033fb))
* return boolean values for translation save function ([28d25a7](https://github.com/LindemannRock/craft-translation-manager/commit/28d25a7366ca6ef616447d40871f9b87998dda59))


### Miscellaneous Chores

* **release:** force next release version ([01a108c](https://github.com/LindemannRock/craft-translation-manager/commit/01a108c914dad9015ec968fd72c1b3cd335138d0))


### Code Refactoring

* rename ExportService → GenerationService and split file generation from CSV downloads ([4b28f08](https://github.com/LindemannRock/craft-translation-manager/commit/4b28f08e7e9970684aa5ad12e97a52e872225325))

## [5.23.3](https://github.com/LindemannRock/craft-translation-manager/compare/v5.23.2...v5.23.3) - 2026-05-06


### Bug Fixes

* apply config overrides through shared settings helper ([0ab7d81](https://github.com/LindemannRock/craft-translation-manager/commit/0ab7d817837f05e255d560ada4f600a40d423259))
* drop PAT requirement for release-please — use built-in GITHUB_TOKEN ([c6249a2](https://github.com/LindemannRock/craft-translation-manager/commit/c6249a2cccc6e36a22571936f77b5b5cb3830c50))
* **translations:** correct translation strings in multiple languages ([35c6465](https://github.com/LindemannRock/craft-translation-manager/commit/35c646589b9c0675ae7336dde1d7069a3fb4166d))
* update copyright year in translation-manager.php ([578052f](https://github.com/LindemannRock/craft-translation-manager/commit/578052f5d1be91010d3fb4ec1ff5252a64d3da10))
* update version annotation for Generate and ImportExport controllers ([ba37d88](https://github.com/LindemannRock/craft-translation-manager/commit/ba37d88bdcff569410ab04707430dbff29b57b87))

## [5.23.2](https://github.com/LindemannRock/craft-translation-manager/compare/v5.23.1...v5.23.2) - 2026-04-14


### Code Refactoring

* **settings:** rename exportPath to generationPath and update related logic ([fa3efa4](https://github.com/LindemannRock/craft-translation-manager/commit/fa3efa49b5d53ecc399315ea7160045bcafcb883))

## [5.23.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.23.0...v5.23.1) - 2026-04-14


### Bug Fixes

* **locale-mapping:** info message display for locale mapping availability ([570bd15](https://github.com/LindemannRock/craft-translation-manager/commit/570bd159cfa4ea372065acda46967694ad4d160c))

## [5.23.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.22.2...v5.23.0) - 2026-04-05


### Features

* Add 12-language translation support with 637 keys across EN, DE, FR, NL, ES, AR, IT, PT, JA, SV, DA, NO ([a3e3ee7](https://github.com/LindemannRock/craft-translation-manager/commit/a3e3ee76f3eb0a820edb658a4ea01a095a26a5fb))


### Bug Fixes

* **BackupController, ExportController, ImportController, MaintenanceController, PhpImportController, TranslationsController:** update error and success messages to use translation strings ([643a279](https://github.com/LindemannRock/craft-translation-manager/commit/643a2797f9c8826393b15f0c5e17fc9d19dec242))
* **import-export, maintenance:** update error messages to use translation strings ([9ed8d38](https://github.com/LindemannRock/craft-translation-manager/commit/9ed8d388a8c3cd77a95ecf5e788394d5e93403a3))
* **TranslationManager:** read-only settings page accessibility ([b589f55](https://github.com/LindemannRock/craft-translation-manager/commit/b589f557ba6f39eb9a77bdcbfe8dd3c3b48e8d04))
* **TranslationManager:** update labels to use translation strings ([d2c2fb5](https://github.com/LindemannRock/craft-translation-manager/commit/d2c2fb569968b933bbc8a0a577a99cfdad39e58c))
* update installation experience text to use translation strings ([1a03923](https://github.com/LindemannRock/craft-translation-manager/commit/1a0392399300fd213c3dcda1e8dcfa168d205470))

## [5.22.2](https://github.com/LindemannRock/craft-translation-manager/compare/v5.22.1...v5.22.2) - 2026-03-17


### Bug Fixes

* **index.twig:** add devMode check for PHP file import functionality ([1b2f801](https://github.com/LindemannRock/craft-translation-manager/commit/1b2f801943f6216e37cfba1a2a9874ad9eb633fd))

## [5.22.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.22.0...v5.22.1) - 2026-03-17


### Bug Fixes

* **TranslationManager:** improve Twig variable registration process ([21417b7](https://github.com/LindemannRock/craft-translation-manager/commit/21417b784183063536a963349b1096447009481f))

## [5.22.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.21.3...v5.22.0) - 2026-03-17


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

## [5.21.3](https://github.com/LindemannRock/craft-translation-manager/compare/v5.21.2...v5.21.3) - 2026-02-23


### Bug Fixes

* **SettingsController:** validate and sanitize settings section parameter ([427cac3](https://github.com/LindemannRock/craft-translation-manager/commit/427cac31d966c396a64f5cd88245ef23ad8948f2))

## [5.21.2](https://github.com/LindemannRock/craft-translation-manager/compare/v5.21.1...v5.21.2) - 2026-02-22


### Bug Fixes

* **index.twig:** remove unnecessary permission check for viewing translations ([dcc4a6e](https://github.com/LindemannRock/craft-translation-manager/commit/dcc4a6eca89f7ba0f85af51054643a28aed1164c))
* **TranslationsController, SettingsController:** update permissions and settings handling ([6e40abd](https://github.com/LindemannRock/craft-translation-manager/commit/6e40abd4a07afc3aa8312c6089114ee6c9baaec7))


### Miscellaneous Chores

* **.gitignore:** reorganize entries and update file exclusions ([e345f5d](https://github.com/LindemannRock/craft-translation-manager/commit/e345f5de10cd023d67679be111737ab80cc8981f))
* add .gitattributes with export-ignore for Packagist distribution ([9f1f1a5](https://github.com/LindemannRock/craft-translation-manager/commit/9f1f1a55208fcc20a7f1985fb5358a3eb07af052))
* switch to Craft License for commercial release ([06ea38b](https://github.com/LindemannRock/craft-translation-manager/commit/06ea38b502da052fd88485bfc1f5c774c73bae3e))

## [5.21.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.21.0...v5.21.1) - 2026-02-07


### Bug Fixes

* **controllers:** replace DateTimeHelper with DateFormatHelper for date formatting ([22b5878](https://github.com/LindemannRock/craft-translation-manager/commit/22b5878a699839deed006aa280eec82cac5405ee))

## [5.21.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.20.1...v5.21.0) - 2026-02-05


### Features

* **import-export:** add CSV column mapping and import preview functionality ([5a82dc7](https://github.com/LindemannRock/craft-translation-manager/commit/5a82dc766404e637f8c1796aed7c444cfcdaf3b1))


### Bug Fixes

* **logs:** update log labels and redirect paths for clarity ([c703cf9](https://github.com/LindemannRock/craft-translation-manager/commit/c703cf947693c60859812d5ff2916b8382ecb034))
* **logs:** update permission checks and log labels for consistency ([a6c1431](https://github.com/LindemannRock/craft-translation-manager/commit/a6c1431fefa7c420861708d91263a37c88544beb))
* **TranslationManager:** update version in docblock for getCpSections method to 5.21.0 ([5a1aa02](https://github.com/LindemannRock/craft-translation-manager/commit/5a1aa0279d1db56a41e51973de018bfb5346eef3))

## [5.20.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.20.0...v5.20.1) - 2026-01-27


### Bug Fixes

* **backup:** adjust backup job scheduling delay based on user settings ([d5b8664](https://github.com/LindemannRock/craft-translation-manager/commit/d5b86644c5a742643d579b398c376f5a2b844381))

## [5.20.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.19.1...v5.20.0) - 2026-01-26


### Features

* streamline Formie plugin checks using PluginHelper ([ca325b5](https://github.com/LindemannRock/craft-translation-manager/commit/ca325b5c89ecabe9c6d6a487465cf1bd7881068a))


### Bug Fixes

* **jobs:** prevent duplicate scheduling of backup jobs ([2862051](https://github.com/LindemannRock/craft-translation-manager/commit/2862051e370b49809d8a6012213464b65cd3acb1))
* **security:** token-based PHP parser and legacy field cleanup ([a7b049b](https://github.com/LindemannRock/craft-translation-manager/commit/a7b049b568a8f871b04c6406ceb481fc2fcfdaf1))

## [5.19.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.19.0...v5.19.1) - 2026-01-22


### Bug Fixes

* remove unnecessary menu header and separator from status list ([e336656](https://github.com/LindemannRock/craft-translation-manager/commit/e33665612cdb79df8dcfff7274371f58a36265fc))

## [5.19.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.18.0...v5.19.0) - 2026-01-21


### Features

* Add instructions for importing translations from third-party plugins in README ([199c830](https://github.com/LindemannRock/craft-translation-manager/commit/199c8303de01bc56b74dca02afdccd431587658a))
* Add locale mapping, integrations, and auto-capture settings pages; update routes and templates ([90f3c89](https://github.com/LindemannRock/craft-translation-manager/commit/90f3c899c064ebe24eb5fe0947fbb50a686a1255))


### Bug Fixes

* **security:** address multiple security vulnerabilities ([5fc8093](https://github.com/LindemannRock/craft-translation-manager/commit/5fc8093a49022e5e5e97a242b7d8fa9150c23296))

## [5.18.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.17.1...v5.18.0) - 2026-01-20


### Features

* Add backup support for PHP import and fix formula injection false positive ([41a685d](https://github.com/LindemannRock/craft-translation-manager/commit/41a685d1c8ae22a38bb2dbd156b73f9cdc273b20))

## [5.17.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.17.0...v5.17.1) - 2026-01-20


### Bug Fixes

* category selection in import/export template and update context handling in PHP import controller ([03d815f](https://github.com/LindemannRock/craft-translation-manager/commit/03d815f136b5e7f7262300d170f8bca275060227))

## [5.17.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.16.0...v5.17.0) - 2026-01-20


### Features

* add PHP file import with multi-language record creation ([a6757fc](https://github.com/LindemannRock/craft-translation-manager/commit/a6757fcf5a33cef5549898b1ca1138ad1de1bff8))
* add runtime auto-capture for missing translations ([412edaa](https://github.com/LindemannRock/craft-translation-manager/commit/412edaaebb6c7cbf4209cc440d0e17a5b042b61d))
* implement AST-based template scanning for translation detection ([59d153f](https://github.com/LindemannRock/craft-translation-manager/commit/59d153f48887ad026722c9531835bef482b53cd8))
* implement locale mapping for translations to reduce duplication and enhance export functionality ([b42edef](https://github.com/LindemannRock/craft-translation-manager/commit/b42edef65a279f432996b66cd195a60ba0865a10))
* update export form and PHP import handling for improved language and category selection ([37a95d1](https://github.com/LindemannRock/craft-translation-manager/commit/37a95d18ad43548f1eb24f0d268e4f86ff7f0025))


### Bug Fixes

* update translation category in example CSV for consistency ([dad1673](https://github.com/LindemannRock/craft-translation-manager/commit/dad167386d3c7f4eb66bef5be1c0a0d3348d3bf4))

## [5.16.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.15.1...v5.16.0) - 2026-01-16


### Features

* simplify import preview to show Language only ([fc1d859](https://github.com/LindemannRock/craft-translation-manager/commit/fc1d8595c19ede5bbff3b40f274344543268bd22))

## [5.15.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.15.0...v5.15.1) - 2026-01-16


### Bug Fixes

* Update translation display to use currentLanguage for improved localization ([ada6819](https://github.com/LindemannRock/craft-translation-manager/commit/ada6819388679c22eb64e3b63fabfc4189696434))

## [5.15.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.14.0...v5.15.0) - 2026-01-16


### Features

* Enhance backup path validation with localized error messages and prevent web-accessible backups ([1b2277f](https://github.com/LindemannRock/craft-translation-manager/commit/1b2277f3c41886ff8bdcf93e8a25ffbd584969ed))
* Switch from site-based to language-based translations ([ae74e94](https://github.com/LindemannRock/craft-translation-manager/commit/ae74e94b8e0ecbaa20c883bc0ed8dfef118466cf))
* Update button text to 'Save Settings' for clarity in settings templates ([20ba20e](https://github.com/LindemannRock/craft-translation-manager/commit/20ba20ee104161ce341d71e1b1e942c0684049a3))

## [5.14.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.13.0...v5.14.0) - 2026-01-13


### Features

* Add form exclusion patterns and script-based filtering for translations ([bbc9adc](https://github.com/LindemannRock/craft-translation-manager/commit/bbc9adc6fa61e5d4330686c491b81ad5609bd538))

## [5.13.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.12.0...v5.13.0) - 2026-01-11


### Features

* Enhance plugin initialization and streamline configuration handling ([3ad83fe](https://github.com/LindemannRock/craft-translation-manager/commit/3ad83fe062ce0c12a48e285d528465f400d5463e))
* Refactor displayName method to use getFullName for plugin name ([aebd93e](https://github.com/LindemannRock/craft-translation-manager/commit/aebd93e3977096051e33258160f2b415089dec33))
* Remove PluginNameExtension and PluginNameHelper classes ([0f1c51c](https://github.com/LindemannRock/craft-translation-manager/commit/0f1c51cfd5254cb49129f9c7a8e6cb781317c43a))

## [5.12.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.11.0...v5.12.0) - 2026-01-09


### Features

* Update backup storage volume instructions for clarity ([cf7367d](https://github.com/LindemannRock/craft-translation-manager/commit/cf7367d9ae49817fab467a3faf1baf3cf25f5d10))

## [5.11.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.10.0...v5.11.0) - 2026-01-08


### Features

* Add granular user permissions system ([80133c6](https://github.com/LindemannRock/craft-translation-manager/commit/80133c67675a4d83ff1d462450d2cdf7f6b2d435))
* Enhance Quick Actions with user permission checks for viewing translations ([2890571](https://github.com/LindemannRock/craft-translation-manager/commit/28905717f125fdd34688c124301b1844dd05b6ab))
* Enhance user permissions handling and redirection in TranslationsController ([c655d1a](https://github.com/LindemannRock/craft-translation-manager/commit/c655d1a7b4e62e056ac3bbb3f22f2c5371d0a5e5))
* Implement user permission checks for Quick Actions visibility ([d05f486](https://github.com/LindemannRock/craft-translation-manager/commit/d05f486720fd3bbb5f7cb60780f20d2ceac7ace9))
* Update user permissions labels to use dynamic settings values ([e68874d](https://github.com/LindemannRock/craft-translation-manager/commit/e68874d60d111a650ee1188498313651feed4348))


### Bug Fixes

* update success message for saved settings ([4862488](https://github.com/LindemannRock/craft-translation-manager/commit/48624881cfaf0dc04458453abcc138d5df8ef3e5))

## [5.10.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.9.4...v5.10.0) - 2026-01-06


### Features

* migrate to shared base plugin ([3732107](https://github.com/LindemannRock/craft-translation-manager/commit/37321072814efd030ce43f52ba55dded944c5ef7))

## [5.9.4](https://github.com/LindemannRock/craft-translation-manager/compare/v5.9.3...v5.9.4) - 2026-01-06


### Miscellaneous Chores

* format composer.json for consistency ([498b333](https://github.com/LindemannRock/craft-translation-manager/commit/498b333b5c30427b58389d6caab48899387f2c03))

## [5.9.3](https://github.com/LindemannRock/craft-translation-manager/compare/v5.9.2...v5.9.3) - 2025-12-11


### Bug Fixes

* update source language configuration details in README ([5f86bad](https://github.com/LindemannRock/craft-translation-manager/commit/5f86bad70353aca92730d22aa3e8ebc7d4a469cf))

## [5.9.2](https://github.com/LindemannRock/craft-translation-manager/compare/v5.9.1...v5.9.2) - 2025-12-11


### Bug Fixes

* clear current site value in import/export template ([b3bc308](https://github.com/LindemannRock/craft-translation-manager/commit/b3bc30838f77fe2821646ec89421a2c73fa466b3))
* update form message handling to use raw properties and add TipTap to HTML conversion ([1d19ac3](https://github.com/LindemannRock/craft-translation-manager/commit/1d19ac3fb255262035465a8f899b56d7b4ddb8ba))

## [5.9.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.9.0...v5.9.1) - 2025-12-09


### Bug Fixes

* remove emoji from Google Review integration default message ([5c380b2](https://github.com/LindemannRock/craft-translation-manager/commit/5c380b229219b2ae094eb53f164262bd5dcfc714))

## [5.9.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.8.0...v5.9.0) - 2025-12-09


### Features

* enhance Google Review integration with default messages and button label ([c240ff3](https://github.com/LindemannRock/craft-translation-manager/commit/c240ff3a8d140210ee4aee90618b3ff17e3e7d01))

## [5.8.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.7.0...v5.8.0) - 2025-12-09


### Features

* add support for capturing Google Review integration messages ([6f1bb09](https://github.com/LindemannRock/craft-translation-manager/commit/6f1bb09fa48315d7ce2611d0518ee27211b588ac))

## [5.7.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.6.0...v5.7.0) - 2025-12-03


### Features

* add source language configuration to Translation Manager settings ([ebda2f4](https://github.com/LindemannRock/craft-translation-manager/commit/ebda2f41b3e2cc9bbe31dde287e5f6f214e58ece))
* add source language selection to Translation Sources settings ([ca92431](https://github.com/LindemannRock/craft-translation-manager/commit/ca924311ebc90fadfb5af9e48d2e2a0a7274a2b8))
* simplify config loading by using Craft's native multi-environment handling ([1f3682c](https://github.com/LindemannRock/craft-translation-manager/commit/1f3682c0b4477f06698170ca18774732dbe1e6f2))
* update titles and improve layout for settings and backup pages ([68a23b8](https://github.com/LindemannRock/craft-translation-manager/commit/68a23b83ba7b5a7964f73f88a002603ef2484466))

## [5.6.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.5.0...v5.6.0) - 2025-11-25


### Features

* add Info Box component for displaying informational notices ([56c8210](https://github.com/LindemannRock/craft-translation-manager/commit/56c821057e897a4e41e038a126ff4003b3e24819))
* add source language configuration and update translation handling ([1c9e4f1](https://github.com/LindemannRock/craft-translation-manager/commit/1c9e4f193460e999ccb57753c00e9875054c1fb8))
* enhance TranslationManager and TranslationElement with additional properties and documentation ([e4df281](https://github.com/LindemannRock/craft-translation-manager/commit/e4df281a0c4c5ae54be4562021d315cf26775a56))
* standardize date handling in ImportController and BackupService using Db helper ([26afee1](https://github.com/LindemannRock/craft-translation-manager/commit/26afee127f65704dc7a1d2c18b010d241211f605))

## [5.5.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.4.0...v5.5.0) - 2025-11-15


### Features

* add MIT License file to the project ([31493ee](https://github.com/LindemannRock/craft-translation-manager/commit/31493ee50703c082bc76c7d943dcc98111b6edc6))


### Bug Fixes

* add margin-top style to Backup Settings header for consistent spacing ([700f94c](https://github.com/LindemannRock/craft-translation-manager/commit/700f94c05d623916bb4a73593feaf961f1240b43))
* add margin-top style to File Generation Settings header for consistent spacing ([cb2481a](https://github.com/LindemannRock/craft-translation-manager/commit/cb2481a62cf30e71404e741f967b5c2eda7abea2))

## [5.4.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.3.1...v5.4.0) - 2025-11-14


### Features

* enhance TranslationManager with plugin name helpers and improve filename generation in exports ([21d4c11](https://github.com/LindemannRock/craft-translation-manager/commit/21d4c11952802df49e40c34af3379a88524efc84))
* update header to include plugin name in Translation Manager overview ([a8bf3a2](https://github.com/LindemannRock/craft-translation-manager/commit/a8bf3a23f8fa909f8280b3ff5dd98856b459c49f))

## [5.3.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.3.0...v5.3.1) - 2025-11-07


### Bug Fixes

* enhance CreateBackupJob to calculate and display next run time for backups ([15e38ae](https://github.com/LindemannRock/craft-translation-manager/commit/15e38ae033de7e61074a69aeda7a906b05b6620d))

## [5.3.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.2.0...v5.3.0) - 2025-11-07


### Features

* add checksum validation for backup integrity and improve logging ([cce90c9](https://github.com/LindemannRock/craft-translation-manager/commit/cce90c90811c493305d7422ca6eb2469a04ba4aa))

## [5.2.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.1.0...v5.2.0) - 2025-11-07


### Features

* update translation manager utility templates and enhance backup settings documentation ([93e971f](https://github.com/LindemannRock/craft-translation-manager/commit/93e971f32ecad6204c1b3971ccfbf35a9831d2c4))

## [5.1.0](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.9...v5.1.0) - 2025-11-06


### Features

* implement a robust CSV parser to handle multiline quoted values in import/export functionality ([1efad45](https://github.com/LindemannRock/craft-translation-manager/commit/1efad452292060027a35fed48694898f742857c3))
* refactor settings management and improve validation in SettingsController ([0e4e036](https://github.com/LindemannRock/craft-translation-manager/commit/0e4e036fbc8b79d8d4b0483bc3d943c07494b594))


### Bug Fixes

* enhance documentation and configuration structure for Translation Manager ([8c8bff3](https://github.com/LindemannRock/craft-translation-manager/commit/8c8bff3eea066550359a269dc2d6c342674d85dc))

## [5.0.9](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.8...v5.0.9) - 2025-10-26


### Bug Fixes

* improve config override check in Settings model ([ce19d46](https://github.com/LindemannRock/craft-translation-manager/commit/ce19d46b902c628a3af2d06f215ba0ac44df27ff))

## [5.0.8](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.7...v5.0.8) - 2025-10-26


### Bug Fixes

* settings page and remove maintenance settings ([74f08e2](https://github.com/LindemannRock/craft-translation-manager/commit/74f08e243b65d63bc1989c0c6514110634d87df9))

## [5.0.7](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.6...v5.0.7) - 2025-10-26


### Bug Fixes

* clean up whitespace and improve markup in translations index template ([7080e0e](https://github.com/LindemannRock/craft-translation-manager/commit/7080e0e2d58c789ff0dd2f8a095382bd81988892))
* enhance logging in TranslationManager and related classes ([42ee1cd](https://github.com/LindemannRock/craft-translation-manager/commit/42ee1cd5b7c23c25ef082cbe0cf46a76d6059b3b))
* refine backup job query conditions in TranslationManager ([bac9cb3](https://github.com/LindemannRock/craft-translation-manager/commit/bac9cb345df73576b12fffbf82c2787beba3270f))

## [5.0.6](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.5...v5.0.6) - 2025-10-22


### Bug Fixes

* remove inline styles for table headers in translations index template ([7691f35](https://github.com/LindemannRock/craft-translation-manager/commit/7691f35a85100c604ac007a32b9fbddea21820e3))
* update logging configuration and clean up whitespace in TranslationManager ([0be50e8](https://github.com/LindemannRock/craft-translation-manager/commit/0be50e83cf9a112129ec8fa5f509086d6610d0bf))

## [5.0.5](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.4...v5.0.5) - 2025-10-20


### Bug Fixes

* customLabels handling for Formie rating fields ([7fa48b6](https://github.com/LindemannRock/craft-translation-manager/commit/7fa48b6eaedb1f328bc977dab6de4f73c7726a8e))

## [5.0.4](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.3...v5.0.4) - 2025-10-20


### Bug Fixes

* correct query parameter in backup download link ([7af29ff](https://github.com/LindemannRock/craft-translation-manager/commit/7af29ffcbe8a47473e483e34d518d3ede7933add))

## [5.0.3](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.2...v5.0.3) - 2025-10-20


### Bug Fixes

* update backup link to point to the backups page ([062fb21](https://github.com/LindemannRock/craft-translation-manager/commit/062fb21c8fee402998142759f5efb820496c5e2e))

## [5.0.2](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.1...v5.0.2) - 2025-10-20


### Bug Fixes

* implement async loading for backups page to prevent blocking on remote volumes ([5089e4c](https://github.com/LindemannRock/craft-translation-manager/commit/5089e4c70f2648281c88e6b72ef0aa956662a3fd))

## [5.0.1](https://github.com/LindemannRock/craft-translation-manager/compare/v5.0.0...v5.0.1) - 2025-10-20


### Miscellaneous Chores

* update logging library dependency to version 5.0 and enhance README with additional badges ([7774980](https://github.com/LindemannRock/craft-translation-manager/commit/7774980278cdf40d024fdbfa5b8a2306ccc1819e))

## [5.0.0](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.9...v5.0.0) - 2025-10-20


### Miscellaneous Chores

* bump version scheme to match Craft 5 ([2f0ca33](https://github.com/LindemannRock/craft-translation-manager/commit/2f0ca334290f0a6087fd39f7c3afc79bcae185d3))

## [1.21.9](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.8...v1.21.9) - 2025-10-20


### Code Refactoring

* reorganize plugin navigation to separate operations from configuration ([b041f08](https://github.com/LindemannRock/craft-translation-manager/commit/b041f081832e60308d57ed50d89f0540e644021d))

## [1.21.8](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.7...v1.21.8) - 2025-10-17


### Bug Fixes

* use settings for plugin name in logging configuration ([0a02914](https://github.com/LindemannRock/craft-translation-manager/commit/0a029142e924fa9c47b23b8357ef0100dbdb7af0))

## [1.21.7](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.6...v1.21.7) - 2025-10-16


### Bug Fixes

* numeric translation keys being treated as integers ([73ca2e7](https://github.com/LindemannRock/craft-translation-manager/commit/73ca2e7634d1773aad574941afaf8b0077d08fc1))

## [1.21.6](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.5...v1.21.6) - 2025-10-16


### Bug Fixes

* update installation instructions for Composer and DDEV ([83ec00e](https://github.com/LindemannRock/craft-translation-manager/commit/83ec00ececd38e90d8905732729cbcb923316f4f))

## [1.21.5](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.4...v1.21.5) - 2025-10-16


### Bug Fixes

* update license from proprietary to MIT in composer.json ([cb76760](https://github.com/LindemannRock/craft-translation-manager/commit/cb767604e60c9dda7c840cd6eefd332c050c6abb))

## [1.21.4](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.3...v1.21.4) - 2025-10-16


### Bug Fixes

* remove logging-library repository configuration from composer.json ([0623ff6](https://github.com/LindemannRock/craft-translation-manager/commit/0623ff624d9cbcf8915391ab7d66011142e4dbde))

## [1.21.3](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.2...v1.21.3) - 2025-10-16


### Bug Fixes

* update author details and enhance logging documentation ([f0f5568](https://github.com/LindemannRock/craft-translation-manager/commit/f0f556854722f028dc05e10c708e2a4e4f7d29b6))

## [1.21.2](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.1...v1.21.2) - 2025-10-15


### Bug Fixes

* ensure newline at end of file in BackupService.php ([ac64f90](https://github.com/LindemannRock/craft-translation-manager/commit/ac64f90d83ea835b5deeba6d6d8366e940f724ea))

## [1.21.1](https://github.com/LindemannRock/craft-translation-manager/compare/v1.21.0...v1.21.1) - 2025-10-10


### Bug Fixes

* add missing backup reason translations and fix VolumeBackupService API ([23cd483](https://github.com/LindemannRock/craft-translation-manager/commit/23cd4839328bc66874f4352fc33dcc8e8a47737f))
* add missing cleanup backup reason translations and simplify template ([0e489b4](https://github.com/LindemannRock/craft-translation-manager/commit/0e489b4386b39721b02e082d5c6c2d63383b9550))
* add missing cleanup reason cases and set scheduled backups to system user ([b61031b](https://github.com/LindemannRock/craft-translation-manager/commit/b61031b49509050e8efb22d89a1d2e04f5fb3c1c))
* add missing cleanup reason cases and set scheduled backups to system user ([d5629eb](https://github.com/LindemannRock/craft-translation-manager/commit/d5629eb577578abdedd352d8a04b58fd2bb68b18))

## [1.21.0](https://github.com/LindemannRock/craft-translation-manager/compare/v1.20.0...v1.21.0) - 2025-10-09


### Features

* add viewLogs permission to Translation Manager ([8b835ca](https://github.com/LindemannRock/craft-translation-manager/commit/8b835cad6f21b4469b84cc33fb72411bb6dad401))

## [1.20.0](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.8...v1.20.0) - 2025-10-07


### Features

* Add Formie default translation strings capture with automatic usage marking ([4c0b8f7](https://github.com/LindemannRock/craft-translation-manager/commit/4c0b8f7f47567b4c0cb3ebbe6609d2c5c3fbf488))

## [1.19.8](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.7...v1.19.8) - 2025-10-06


### Bug Fixes

* TypeError in FormieIntegration when handling TipTap content as array ([263a973](https://github.com/LindemannRock/craft-translation-manager/commit/263a9732edc538a117a0f76b9dc9dd2d7039b3c5))

## [1.19.7](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.6...v1.19.7) - 2025-09-26


### Bug Fixes

* Check for web request before calling getIsAjax() in volume backup listing ([d824ce8](https://github.com/LindemannRock/craft-translation-manager/commit/d824ce8abc1669ed4b319d1eecb6ec552ccffba1))

## [1.19.6](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.5...v1.19.6) - 2025-09-25


### Bug Fixes

* Remove log viewer enablement for Servd edge servers ([0e3ba98](https://github.com/LindemannRock/craft-translation-manager/commit/0e3ba9895a6d38c4e2ac565af2e77740d3867be5))

## [1.19.5](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.4...v1.19.5) - 2025-09-24


### Bug Fixes

* Disable log viewer on Servd edge servers ([427defe](https://github.com/LindemannRock/craft-translation-manager/commit/427defec67ab68790cfce53bf8b442574502245d))
* Enable log viewer for Translation Manager on Servd integration ([9259f74](https://github.com/LindemannRock/craft-translation-manager/commit/9259f7438458c275ead7eb9ee696a785023ba0e9))

## [1.19.4](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.3...v1.19.4) - 2025-09-24


### Bug Fixes

* Update log viewer enablement condition for Servd integration ([d10e1f8](https://github.com/LindemannRock/craft-translation-manager/commit/d10e1f8f32bcf3d3a03329c0ce8dccea7f24c043))

## [1.19.3](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.2...v1.19.3) - 2025-09-24


### Bug Fixes

* Remove icon from Rescan Templates button in maintenance settings ([76e87c7](https://github.com/LindemannRock/craft-translation-manager/commit/76e87c78c9b44f433eefdc44437c6082da9dfe9f))
* Update settings navigation to use selectedSettingsItem for consistent highlighting ([a88c88f](https://github.com/LindemannRock/craft-translation-manager/commit/a88c88fdc7dce28c288d55932a2bed8ebd9fb589))

## [1.19.2](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.1...v1.19.2) - 2025-09-24


### Bug Fixes

* Improve log level warning handling for console requests ([56a13da](https://github.com/LindemannRock/craft-translation-manager/commit/56a13da974e58093da5afeaa91be139a6950f272))

## [1.19.1](https://github.com/LindemannRock/craft-translation-manager/compare/v1.19.0...v1.19.1) - 2025-09-24


### Bug Fixes

* Update repository name and URLs in composer.json and README.md ([9420cee](https://github.com/LindemannRock/craft-translation-manager/commit/9420cee7a73548207c68839765751256f7e6bb06))

## [1.19.0](https://github.com/LindemannRock/translation-manager/compare/v1.18.0...v1.19.0) - 2025-09-24


### Features

* Enhance log level instructions to clarify devMode requirement ([5730792](https://github.com/LindemannRock/translation-manager/commit/57307927540850a407a46b8397f1bea974c77373))

## [1.18.0](https://github.com/LindemannRock/translation-manager/compare/v1.17.0...v1.18.0) - 2025-09-24


### Features

* Refine config loading logic to only override settings with explicitly set values ([3e746a7](https://github.com/LindemannRock/translation-manager/commit/3e746a7b97f19ffb1cd22fc5c7d52872b2e961a3))
* Update backup job description to include plugin name ([ec1eee0](https://github.com/LindemannRock/translation-manager/commit/ec1eee007d8dd57a95fc753ff9f53086326b0a58))

## [1.17.0](https://github.com/LindemannRock/translation-manager/compare/v1.16.0...v1.17.0) - 2025-09-23


### Features

* Improve log level validation to prevent repeated warnings in production ([e6bfa96](https://github.com/LindemannRock/translation-manager/commit/e6bfa96e5207c2cf36aeb5d72aad9c15aecdc751))

## [1.16.0](https://github.com/LindemannRock/translation-manager/compare/v1.15.4...v1.16.0) - 2025-09-23


### Features

* Add validation for logLevel setting to prevent debug in production ([daba6ed](https://github.com/LindemannRock/translation-manager/commit/daba6ed9a2e519efc960b5806c4b4111be5a987e))

## [1.15.4](https://github.com/LindemannRock/translation-manager/compare/v1.15.3...v1.15.4) - 2025-09-23


### Bug Fixes

* Remove test log messages ([61a3621](https://github.com/LindemannRock/translation-manager/commit/61a362152f9b723043645a68e2bc190793435d41))

## [1.15.3](https://github.com/LindemannRock/translation-manager/compare/v1.15.2...v1.15.3) - 2025-09-23


### Bug Fixes

* Add test log messages to verify all log levels ([a00e844](https://github.com/LindemannRock/translation-manager/commit/a00e844ca41b16c70dcb3a2fe24f3a5b4dfbf932))

## [1.15.2](https://github.com/LindemannRock/translation-manager/compare/v1.15.1...v1.15.2) - 2025-09-23


### Bug Fixes

* Remove debug logging code ([20c9265](https://github.com/LindemannRock/translation-manager/commit/20c9265cd3af46021d3c7ebe9d3b09006346bb36))

## [1.15.1](https://github.com/LindemannRock/translation-manager/compare/v1.15.0...v1.15.1) - 2025-09-23


### Bug Fixes

* Add debug to check why settings logLevel is not being read ([3ebedd6](https://github.com/LindemannRock/translation-manager/commit/3ebedd6c3a7fa3fbe359e2fadcc20bb8f0e7bcc3))

## [1.15.0](https://github.com/LindemannRock/translation-manager/compare/v1.14.5...v1.15.0) - 2025-09-23


### Features

* enhance translation category settings with tips and warnings ([b23cb14](https://github.com/LindemannRock/translation-manager/commit/b23cb1492347e414814ff96fe8a44716fb5c5822))


### Bug Fixes

* improve validation for translation category to include reserved categories ([30c5d21](https://github.com/LindemannRock/translation-manager/commit/30c5d21528e789c431f6e693edb40bd790d46355))
* update site translation category instructions and warnings for reserved categories ([55504da](https://github.com/LindemannRock/translation-manager/commit/55504dab0c65d3f16ff79af297ee5c2708343bb7))
* update troubleshooting guide and translation strings for clarity on category usage ([0174708](https://github.com/LindemannRock/translation-manager/commit/0174708d6d21078ca7e0d3809fcb592fa467834a))

## [1.14.5](https://github.com/LindemannRock/translation-manager/compare/v1.14.4...v1.14.5) - 2025-09-22


### Bug Fixes

* streamline logging configuration in Translation Manager initialization ([479df2f](https://github.com/LindemannRock/translation-manager/commit/479df2f45d4ffb378fabad508c3b83a3ecf81f6b))

## [1.14.4](https://github.com/LindemannRock/translation-manager/compare/v1.14.3...v1.14.4) - 2025-09-22


### Bug Fixes

* enhance logging configuration and conditionally add logs section in Translation Manager ([fc3f552](https://github.com/LindemannRock/translation-manager/commit/fc3f5529129955287afd51f540c3a507414e12f9))

## [1.14.3](https://github.com/LindemannRock/translation-manager/compare/v1.14.2...v1.14.3) - 2025-09-22


### Bug Fixes

* update log level from 'trace' to 'debug' for Craft 5 compatibility ([53091bc](https://github.com/LindemannRock/translation-manager/commit/53091bcd8861cb548bd28a639b381222209246bc))

## [1.14.2](https://github.com/LindemannRock/translation-manager/compare/v1.14.1...v1.14.2) - 2025-09-22


### Bug Fixes

* enhance button styling for log level settings link and update subnav item for general settings ([d2e669a](https://github.com/LindemannRock/translation-manager/commit/d2e669a6bf018385cac0d21757b70079038c2f25))
* increase log entries limit and enhance filter behavior in logs view ([7069a16](https://github.com/LindemannRock/translation-manager/commit/7069a169f236cd64250b94eb429bd01bacfb1b1e))
* respect pluginName setting in template titles ([d912cce](https://github.com/LindemannRock/translation-manager/commit/d912cce2f24178ad551d824a0b292f4ddc0cbc7a))

## [1.14.1](https://github.com/LindemannRock/translation-manager/compare/v1.14.0...v1.14.1) - 2025-09-20


### Bug Fixes

* standardize logging format and improve initialization performance ([a365ffe](https://github.com/LindemannRock/translation-manager/commit/a365ffe67f5caca6392435282a5615ee4e553778))

## [1.14.0](https://github.com/LindemannRock/translation-manager/compare/v1.13.0...v1.14.0) - 2025-09-19


### Features

* improve backup operation UX with immediate loading feedback ([e77913f](https://github.com/LindemannRock/translation-manager/commit/e77913fefa2138b2010a65c959f3dfbf1bbbe8d8))

## [1.13.0](https://github.com/LindemannRock/translation-manager/compare/v1.12.6...v1.13.0) - 2025-09-19


### Features

* **backup:** add loading states and UI improvements for volume operations ([cc800be](https://github.com/LindemannRock/translation-manager/commit/cc800be7def2e3736c8b06ef88e8357a296614d9))

## [1.12.6](https://github.com/LindemannRock/translation-manager/compare/v1.12.5...v1.12.6) - 2025-09-19


### Bug Fixes

* **backup:** improve backup functionality for volume and local storage ([4472f5d](https://github.com/LindemannRock/translation-manager/commit/4472f5d4b13cc410d4c15b79caefc2389d5b2139))

## [1.12.5](https://github.com/LindemannRock/translation-manager/compare/v1.12.4...v1.12.5) - 2025-09-19


### Bug Fixes

* **backup:** handle FsListing objects from getFileList() properly ([a9be508](https://github.com/LindemannRock/translation-manager/commit/a9be508d4dfa2f323e83f276b704fcb57ba5994f))

## [1.12.4](https://github.com/LindemannRock/translation-manager/compare/v1.12.3...v1.12.4) - 2025-09-19


### Bug Fixes

* **backup:** convert generator to array for file listing in BackupService ([4a9a87b](https://github.com/LindemannRock/translation-manager/commit/4a9a87bc71c3a4d4bcdd311d1e86213bf336dc9d))

## [1.12.3](https://github.com/LindemannRock/translation-manager/compare/v1.12.2...v1.12.3) - 2025-09-19


### Bug Fixes

* **backup:** use correct Craft CMS v5 FsInterface methods for volumes ([363717a](https://github.com/LindemannRock/translation-manager/commit/363717a714b607e021e16901ded3cdfa06f49e3f))

## [1.12.2](https://github.com/LindemannRock/translation-manager/compare/v1.12.1...v1.12.2) - 2025-09-19


### Bug Fixes

* **backup:** use Flysystem API for Servd volume operations ([72868b8](https://github.com/LindemannRock/translation-manager/commit/72868b81513c4829f5897a65ee2a9c37b4d7e504))

## [1.12.1](https://github.com/LindemannRock/translation-manager/compare/v1.12.0...v1.12.1) - 2025-09-19


### Bug Fixes

* **backup:** implement volume backup operations and listing ([8b5ae37](https://github.com/LindemannRock/translation-manager/commit/8b5ae37ae1ade5a9fa41729c97270a943644f041))

## [1.12.0](https://github.com/LindemannRock/translation-manager/compare/v1.11.0...v1.12.0) - 2025-09-18


### Features

* **backup:** add asset volume selector for backup storage ([1b56355](https://github.com/LindemannRock/translation-manager/commit/1b56355d7ec98f714d6fa42ad968582d8df24051))

## [1.11.0](https://github.com/LindemannRock/translation-manager/compare/v1.10.0...v1.11.0) - 2025-09-15


### Features

* **TranslationStatsUtility:** update to retrieve statistics for enabled sites only ([cf123cc](https://github.com/LindemannRock/translation-manager/commit/cf123cca0c5676e6a4b14ea1b655d0a942886bc8))

## [1.10.0](https://github.com/LindemannRock/translation-manager/compare/v1.9.0...v1.10.0) - 2025-09-15


### Features

* **FormieIntegration, TranslationsService:** update Agree field handling to use getDescriptionHtml() for improved translation capture ([f4f1742](https://github.com/LindemannRock/translation-manager/commit/f4f1742ddee44f75a213920b1ffd2bdcfeb4a315))

## [1.9.0](https://github.com/LindemannRock/translation-manager/compare/v1.8.0...v1.9.0) - 2025-09-15


### Features

* **FormieIntegration:** enhance translation capture for Agree field descriptions ([619e34d](https://github.com/LindemannRock/translation-manager/commit/619e34d8eae616f49e236a17ee7ff3f5fa414ace))

## [1.8.0](https://github.com/LindemannRock/translation-manager/compare/v1.7.1...v1.8.0) - 2025-09-15


### Features

* enhance Formie integration to capture additional button labels and messages ([a60dc4d](https://github.com/LindemannRock/translation-manager/commit/a60dc4d5e52cdb4d011ba4c68517893fb067f8d9))

## [1.7.1](https://github.com/LindemannRock/translation-manager/compare/v1.7.0...v1.7.1) - 2025-09-15


### Bug Fixes

* correct license header formatting in LICENSE file ([0ac32cc](https://github.com/LindemannRock/translation-manager/commit/0ac32cc879db1e2c04437d9fe78d3a983634afcd))

## [1.7.0](https://github.com/LindemannRock/translation-manager/compare/v1.6.0...v1.7.0) - 2025-09-14


### Features

* add plugin credit component to settings templates ([8d29f7d](https://github.com/LindemannRock/translation-manager/commit/8d29f7db60bf62ce1e20fa8543de0496a0a8ebcc))

## [1.6.0](https://github.com/LindemannRock/translation-manager/compare/v1.5.0...v1.6.0) - 2025-09-13


### Features

* enhance handling of field descriptions in TranslationsService ([400dd95](https://github.com/LindemannRock/translation-manager/commit/400dd95179c4c8f7321783ba377bf186bc246270))

## [1.5.0](https://github.com/LindemannRock/translation-manager/compare/v1.4.2...v1.5.0) - 2025-09-12


### Features

* implement generic integration architecture and refactor Formie integration ([2f2ab43](https://github.com/LindemannRock/translation-manager/commit/2f2ab4312e57749ec9cb1b07e19eb7fe3631f766))

## [1.4.2](https://github.com/LindemannRock/translation-manager/compare/v1.4.1...v1.4.2) - 2025-09-11


### Bug Fixes

* Update translation record queries to match unique constraint by sourceHash and siteId ([956647f](https://github.com/LindemannRock/translation-manager/commit/956647ff51a9f7c39673ee28b847c21ef7450174))

## [1.4.1](https://github.com/LindemannRock/translation-manager/compare/v1.4.0...v1.4.1) - 2025-09-11


### Bug Fixes

* Translation Manager database schema to match working installation ([7624dbb](https://github.com/LindemannRock/translation-manager/commit/7624dbb24fd8facf942fa94af7e478128a3cf926))

## [1.4.0](https://github.com/LindemannRock/translation-manager/compare/v1.3.5...v1.4.0) - 2025-09-11


### Features

* critical security validation bugs and logging issues ([7eddc39](https://github.com/LindemannRock/translation-manager/commit/7eddc3924e53be056a07e33c37553f6cbe42264a))

## [1.3.5](https://github.com/LindemannRock/translation-manager/compare/v1.3.4...v1.3.5) - 2025-09-11


### Bug Fixes

* validation bypass allowing insecure [@webroot](https://github.com/webroot) alias ([9b66943](https://github.com/LindemannRock/translation-manager/commit/9b66943e1e89922b6eb09ea8ea7b387d824be428))

## [1.3.4](https://github.com/LindemannRock/translation-manager/compare/v1.3.3...v1.3.4) - 2025-09-11


### Bug Fixes

* critical security vulnerability in settings validation ([389a54f](https://github.com/LindemannRock/translation-manager/commit/389a54f25437d8233377290efa5e638c1787ceba))
* enhance security measures in README and SECURITY documentation ([ad4b6b8](https://github.com/LindemannRock/translation-manager/commit/ad4b6b83102937a5abaed57f8c7fceb08edc1858))

## [1.3.3](https://github.com/LindemannRock/translation-manager/compare/v1.3.2...v1.3.3) - 2025-09-10


### Bug Fixes

* update README with detailed problem statements and installation instructions ([d7e6e29](https://github.com/LindemannRock/translation-manager/commit/d7e6e2904bcb8399ecec8b0b1d150b8c4f026701))

## 1.3.2 - 2025-09-10


### Features

* initial Translation Manager plugin implementation ([8eb2d76](https://github.com/LindemannRock/translation-manager/commit/8eb2d7613702508d6ddad92d3b237a8eb67d1176))
