<?php

/**
 * (c) Kitodo. Key to digital objects e.V. <contact@kitodo.org>
 *
 * This file is part of the Kitodo and TYPO3 projects.
 *
 * @license GNU General Public License version 3 or later.
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Kitodo\Dlf\Common;

use Kitodo\Dlf\Common\Solr\Solr;
use Kitodo\Dlf\Domain\Model\Format;
use Kitodo\Dlf\Domain\Model\SolrCore;
use Kitodo\Dlf\Domain\Repository\FormatRepository;
use Kitodo\Dlf\Domain\Repository\MetadataRepository;
use Kitodo\Dlf\Domain\Repository\SolrCoreRepository;
use Kitodo\Dlf\Domain\Repository\StructureRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Localization\LocalizationFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Initializer class for the 'dlf' extension
 *
 * @package TYPO3
 * @subpackage dlf
 *
 * @access public
 */
class Initializer
{

    public static function countDefaults(string $dataFileName): int
    {
        return count(self::loadInitialData($dataFileName));
    }

    /**
     * @access public
     * @static
     * @var string The extension key
     */
    public static string $extKey = 'dlf';

    public static function insertFormats(FormatRepository $formatRepository, PersistenceManager $persistenceManager): bool
    {
        $formats = self::loadInitialData('FormatDefaults.php');

        $doPersist = false;

        foreach ($formats as $type => $values) {
            // if default format record is not found, add it to the repository
            if ($formatRepository->findOneByType($type) === null) {
                $newRecord = GeneralUtility::makeInstance(Format::class);
                $newRecord->setType($type);
                $newRecord->setRoot($values['root']);
                $newRecord->setNamespace($values['namespace']);
                $newRecord->setClass($values['class']);
                $formatRepository->add($newRecord);

                $doPersist = true;
            }
        }

        // We must persist here, if we changed anything.
        if ($doPersist === true) {
            $persistenceManager->persistAll();
        }

        return $formatRepository->countAll() >= count($formats);
    }

    public static function insertMetadata(int $pid, array $siteLanguages, FormatRepository $formatRepository, MetadataRepository $metadataRepository): bool
    {
        $metadata = self::loadInitialData('MetadataDefaults.php');
        $metadataLabels = self::loadLabels('locallang_metadata.xlf', $siteLanguages);

        $defaultWrap = BackendUtility::getTcaFieldConfiguration('tx_dlf_metadata', 'wrap')['default'];

        $insertedFormats = $formatRepository->findAll();

        $availableFormats = [];
        foreach ($insertedFormats as $insertedFormat) {
            $availableFormats[$insertedFormat->getRoot()] = $insertedFormat->getUid();
        }

        $data = [];
        foreach ($metadata as $indexName => $values) {
            $formatIds = [];

            foreach ($values['format'] as $format) {
                $format['encoded'] = $availableFormats[$format['format_root']];
                unset($format['format_root']);
                $formatIds[] = uniqid('NEW');
                $data['tx_dlf_metadataformat'][end($formatIds)] = $format;
                $data['tx_dlf_metadataformat'][end($formatIds)]['pid'] = $pid;
            }

            $data['tx_dlf_metadata'][uniqid('NEW')] = [
                'pid' => $pid,
                'label' => self::getLLL('metadata.' . $indexName, $siteLanguages[0]->getTypo3Language(), $metadataLabels),
                'index_name' => $indexName,
                'format' => implode(',', $formatIds),
                'default_value' => $values['default_value'],
                'wrap' => !empty($values['wrap']) ? $values['wrap'] : $defaultWrap,
                'index_tokenized' => $values['index_tokenized'],
                'index_stored' => $values['index_stored'],
                'index_indexed' => $values['index_indexed'],
                'index_boost' => $values['index_boost'],
                'is_sortable' => $values['is_sortable'],
                'is_facet' => $values['is_facet'],
                'is_listed' => $values['is_listed'],
                'index_autocomplete' => $values['index_autocomplete'],
            ];
        }

        $metadataIds = Helper::processDatabaseAsAdmin($data, [], true);

        $insertedMetadata = [];
        foreach ($metadataIds as $id => $uid) {
            $metadata = $metadataRepository->findByUid($uid);
            // id array contains also ids of formats
            if ($metadata != NULL) {
                $insertedMetadata[$uid] = $metadata->getIndexName();
            }
        }

        foreach ($siteLanguages as $siteLanguage) {
            if ($siteLanguage->getLanguageId() === 0) {
                // skip default language
                continue;
            }

            $translateData = [];
            foreach ($insertedMetadata as $id => $indexName) {
                $translateData['tx_dlf_metadata'][uniqid('NEW')] = [
                    'pid' => $pid,
                    'sys_language_uid' => $siteLanguage->getLanguageId(),
                    'l18n_parent' => $id,
                    'label' => self::getLLL('metadata.' . $indexName, $siteLanguage->getTypo3Language(), $metadataLabels),
                ];
            }

            Helper::processDatabaseAsAdmin($translateData);
        }

        return count($metadataIds) > 0;
    }

    public static function insertSolrCores(int $pid, array $siteLanguages, SolrCoreRepository $solrCoreRepository, PersistenceManager $persistenceManager): bool
    {
        $doPersist = false;

        // load language file in own array
        $beLabels = self::loadLabels('locallang_be.xlf', $siteLanguages);

        if ($solrCoreRepository->findOneByPid($pid) === null) {
            $newRecord = GeneralUtility::makeInstance(SolrCore::class);
            $newRecord->setLabel(self::getLLL('flexform.solrcore', $siteLanguages[0]->getTypo3Language(), $beLabels). ' (PID ' . $pid . ')');
            $indexName = Solr::createCore('');
            if (!empty($indexName)) {
                $newRecord->setIndexName($indexName);

                $solrCoreRepository->add($newRecord);

                $doPersist = true;
            }
        }

        // We must persist here, if we changed anything.
        if ($doPersist === true) {
            $persistenceManager->persistAll();
        }

        return $solrCoreRepository->findOneByPid($pid) !== null;
    }

    public static function insertStructures(int $pid, array $siteLanguages, StructureRepository $structureRepository): bool
    {
        $structures = self::loadInitialData('StructureDefaults.php');;
        $structureLabels = self::loadLabels('locallang_structure.xlf', $siteLanguages);

        $data = [];
        foreach ($structures as $indexName => $values) {
            $data['tx_dlf_structures'][uniqid('NEW')] = [
                'pid' => $pid,
                'toplevel' => $values['toplevel'],
                'label' => self::getLLL('structure.' . $indexName, $siteLanguages[0]->getTypo3Language(), $structureLabels),
                'index_name' => $indexName,
                'oai_name' => $values['oai_name'],
                'thumbnail' => 0,
            ];
        }
        $structureIds = Helper::processDatabaseAsAdmin($data, [], true);

        $insertedStructures = [];
        foreach ($structureIds as $id => $uid) {
            $insertedStructures[$uid] = $structureRepository->findByUid($uid)->getIndexName();
        }

        foreach ($siteLanguages as $siteLanguage) {
            if ($siteLanguage->getLanguageId() === 0) {
                // skip default language
                continue;
            }

            $translateData = [];
            foreach ($insertedStructures as $id => $indexName) {
                $translateData['tx_dlf_structures'][uniqid('NEW')] = [
                    'pid' => $pid,
                    'sys_language_uid' => $siteLanguage->getLanguageId(),
                    'l18n_parent' => $id,
                    'label' => self::getLLL('structure.' . $indexName, $siteLanguage->getTypo3Language(), $structureLabels),
                ];
            }

            Helper::processDatabaseAsAdmin($translateData);
        }

        return count($structureIds) > 0;
    }

    /**
     * Get language label for given key and language.
     * 
     * @access private
     *
     * @param string $index
     * @param string $lang
     * @param array $langArray
     *
     * @return string
     */
    private static function getLLL(string $index, string $lang, array $langArray): string
    {
        if (isset($langArray[$lang][$index][0]['target'])) {
            return $langArray[$lang][$index][0]['target'];
        } elseif (isset($langArray['default'][$index][0]['target'])) {
            return $langArray['default'][$index][0]['target'];
        } else {
            return 'Missing translation for ' . $index;
        }
    }

    private static function loadInitialData(string $dataFileName): array
    {
        return include(ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/Data/' . $dataFileName);
    }

    private static function loadLabels(string $labelsFileName, array $siteLanguages): array
    {
        $languageFactory = GeneralUtility::makeInstance(LocalizationFactory::class);
        return $languageFactory->getParsedData('EXT:dlf/Resources/Private/Language/' . $labelsFileName, $siteLanguages[0]->getTypo3Language());
    }
}