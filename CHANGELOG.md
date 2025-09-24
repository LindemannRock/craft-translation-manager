# Changelog

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
