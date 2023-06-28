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

namespace Kitodo\Dlf\TableOfContent;

use Kitodo\Dlf\Common\Helper;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Table entity class for plugin 'Table Of Contents'.
 *
 * @author Beatrycze Volk <beatrycze.volk@slub-dresden.de>
 * @package TYPO3
 * @subpackage dlf
 * @access public
 */
class Table {

    /**
     * This holds the active entries according to the currently selected page
     *
     * @var array
     * @access private
     */
    private $activeEntries = [];

    private $doc;

    private $document;

    private $documentRepository;

    private $id;

    private $page;

    private $double;

    private $settings;

    /**
     * The constructor for region.
     *
     * @access public
     *
     * @param Document $document document entity
     * @param DocumentRepository $documentRepository document repository
     * @param array $requestData Array of request data
     * @param array $settings Array of settings
     *
     * @return void
     */
    public function __construct($document, $documentRepository, $requestData, $settings) {
        $this->doc = $document->getDoc();
        $this->document = $document;
        $this->documentRepository = $documentRepository;
        $this->id = $requestData['id'];
        $this->page = $requestData['page'];
        $this->double = $requestData['double'];
        $this->settings = $settings;
    }

    /**
     * This builds a menu array for HMENU
     *
     * @access public
     * @return array HMENU array
     */
    public function getTable() {
        $table = [];
        $entry = new Entry($this->activeEntries, $this->document, $this->settings);
        // Does the document have physical elements or is it an external file?
        if (
            !empty($this->doc->physicalStructure)
            || !MathUtility::canBeInterpretedAsInteger($this->id)
        ) {
            $this->setAllLogicalUnits();

            // Go through table of contents and create all menu entries.
            foreach ($this->doc->tableOfContents as $content) {
                var_dump($entry);
                $table = $entry->getEntry($content, true);
            }
        } else {
            // Go through table of contents and create top-level menu entries.
            foreach ($this->document->getDoc()->tableOfContents as $content) {
                var_dump($entry);
                $menuArray[] = $entry->getEntry($content, false);
            }
            // Build table of contents from database.
            $result = $this->documentRepository->getTableOfContentsFromDb($this->document->getUid(), $this->document->getPid(), $this->settings);

            $allResults = $result->fetchAll();

            if (count($allResults) > 0) {
                $table[0]['ITEM_STATE'] = 'CURIFSUB';
                $table[0]['_SUB_MENU'] = [];
                foreach ($allResults as $resArray) {
                    $content = [
                        'label' => !empty($resArray['mets_label']) ? $resArray['mets_label'] : $resArray['title'],
                        'type' => $resArray['type'],
                        'volume' => $resArray['volume'],
                        'orderlabel' => $resArray['mets_orderlabel'],
                        'pagination' => '',
                        'targetUid' => $resArray['uid']
                    ];
                    $table[0]['_SUB_MENU'][] = $entry->getEntry($content, false);
                }
            }
        }
        $this->sortContent($table);
        return $table;
    }

    /**
     * Set all logical units the current page or track is a part of.
     */
    private function setAllLogicalUnits() {
        if (
            !empty($this->page)
            && !empty($this->doc->physicalStructure)
        ) {
            $this->activeEntries = array_merge((array) $this->doc->smLinks['p2l'][$this->doc->physicalStructure[0]],
                (array) $this->document->getDoc()->smLinks['p2l'][$this->doc->physicalStructure[$this->page]]);
            if (
                !empty($this->double)
                && $this->page < $this->document->getDoc()->numPages
            ) {
                $this->activeEntries = array_merge($this->activeEntries,
                    (array) $this->doc->smLinks['p2l'][$this->doc->physicalStructure[$this->page + 1]]);
            }
        }
    }

    /**
     * Get translated type of entry.
     *
     * @param array $type
     * @return string
     */
    private function getTranslatedType($type) {
        return Helper::translate($type, 'tx_dlf_structures', $this->settings['storagePid']);
    }

    /**
     * Sort menu by orderlabel.
     *
     * @param array &$menu
     * @return void
     */
    private function sortContent(&$menu) {
        if ($menu[0]['type'] == $this->getTranslatedType("newspaper")) {
            $this->sortSubMenu($menu);
        }
        if ($menu[0]['type'] == $this->getTranslatedType("year")) {
            $this->sortSubMenu($menu);
        }
    }

    /**
     * Sort sub menu e.g. years of the newspaper by orderlabel.
     *
     * @param array &$menu
     * @return void
     */
    private function sortSubMenu(&$menu) {
        usort($menu[0]['_SUB_MENU'], function ($firstElement, $secondElement) {
            return $firstElement['orderlabel'] <=> $secondElement['orderlabel'];
        });
    }
}
