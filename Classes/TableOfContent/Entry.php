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
use Kitodo\Dlf\Common\MetsDocument;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Entry entity class for plugin 'Table Of Contents'.
 *
 * @author Beatrycze Volk <beatrycze.volk@slub-dresden.de>
 * @package TYPO3
 * @subpackage dlf
 * @access public
 */
class Entry {

    private $activeEntries = [];

    private $doc;

    private $document;

    private $settings;

    public function __construct($activeEntries, $document, $settings) {
        $this->activeEntries = $activeEntries;
        $this->doc = $document->getDoc();
        $this->document = $document;
        $this->settings = $settings;
    }

    /**
     * This builds an array for one menu entry
     *
     * @access public
     *
     * @param array $entry : The entry's array from \Kitodo\Dlf\Common\Doc->getLogicalStructure
     * @param bool $recursive : Whether to include the child entries
     *
     * @return array HMENU array for menu entry
     */
    public function getEntry(array $entry, $recursive = false)
    {
        $entry = $this->resolveEntry($entry);

        $entryData = [];
        // Set "title", "volume", "type" and "pagination" from $entry array.
        $entryData['title'] = $this->setTitle($entry);
        $entryData['volume'] = $entry['volume'];
        $entryData['orderlabel'] = $entry['orderlabel'];
        $entryData['type'] = Helper::translate($entry['type'], 'tx_dlf_structures', $this->settings['storagePid']);
        $entryData['pagination'] = htmlspecialchars($entry['pagination']);
        $entryData['_OVERRIDE_HREF'] = '';
        $entryData['doNotLinkIt'] = 1;
        $entryData['ITEM_STATE'] = 'NO';

        // Build menu links based on the $entry['points'] array.
        if (
            !empty($entry['points'])
            && MathUtility::canBeInterpretedAsInteger($entry['points'])
        ) {
            $entryData['page'] = $entry['points'];

            $entryData['doNotLinkIt'] = 0;
            if ($this->settings['basketButton']) {
                $entryData['basketButton'] = [
                    'logId' => $entry['id'],
                    'startpage' => $entry['points']
                ];
            }
        } elseif (
            !empty($entry['points'])
            && is_string($entry['points'])
        ) {
            $entryData['id'] = $entry['points'];
            $entryData['page'] = 1;
            $entryData['doNotLinkIt'] = 0;
            if ($this->settings['basketButton']) {
                $entryData['basketButton'] = [
                    'logId' => $entry['id'],
                    'startpage' => $entry['points']
                ];
            }
        } elseif (!empty($entry['targetUid'])) {
            $entryData['id'] = $entry['targetUid'];
            $entryData['page'] = 1;
            $entryData['doNotLinkIt'] = 0;
            if ($this->settings['basketButton']) {
                $entryData['basketButton'] = [
                    'logId' => $entry['id'],
                    'startpage' => $entry['targetUid']
                ];
            }
        }
        // Set "ITEM_STATE" to "CUR" if this entry points to current page.
        if (in_array($entry['id'], $this->activeEntries)) {
            $entryData['ITEM_STATE'] = 'CUR';
        }
        // Build sub-menu if available and called recursively.
        if (
            $recursive === true
            && !empty($entry['children'])
        ) {
            // Build sub-menu only if one of the following conditions apply:
            // 1. Current menu node is in rootline
            // 2. Current menu node points to another file
            // 3. Current menu node has no corresponding images
            if (
                $entryData['ITEM_STATE'] == 'CUR'
                || is_string($entry['points'])
                || empty($this->doc->smLinks['l2p'][$entry['id']])
            ) {
                $entryData['_SUB_MENU'] = [];
                foreach ($entry['children'] as $child) {
                    // Set "ITEM_STATE" to "ACT" if this entry points to current page and has sub-entries pointing to the same page.
                    if (in_array($child['id'], $this->activeEntries)) {
                        $entryData['ITEM_STATE'] = 'ACT';
                    }
                    $entryData['_SUB_MENU'][] = $this->getEntry($child, true);
                }
            }
            // Append "IFSUB" to "ITEM_STATE" if this entry has sub-entries.
            $entryData['ITEM_STATE'] = ($entryData['ITEM_STATE'] == 'NO' ? 'IFSUB' : $entryData['ITEM_STATE'] . 'IFSUB');
        }
        return $entryData;
    }

    /**
     * If $entry references an external METS file (as mptr),
     * try to resolve its database UID and return an updated $entry.
     *
     * This is so that when linking from a child document back to its parent,
     * that link is via UID, so that subsequently the parent's TOC is built from database.
     *
     * @param array $entry
     * @return array
     */
    protected function resolveEntry($entry)
    {
        // If the menu entry points to the parent document,
        // resolve to the parent UID set on indexation.
        $doc = $this->document->getDoc();
        if (
            $doc instanceof MetsDocument
            && $entry['points'] === $doc->parentHref
            && !empty($this->document->getPartof())
        ) {
            unset($entry['points']);
            $entry['targetUid'] = $this->document->getPartof();
        }

        return $entry;
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
     * Set title from entry.
     *
     * @param array $entry
     * @return string
     */
    private function setTitle($entry) {
        if (($entry['type'] == 'volume' || $entry['type'] == 'issue') && empty($entry['label'])) {
            if (empty($entry['volume'])) {
                return $this->getTranslatedType($entry['type']) . ' ' . $entry['year'];
            }
            return $this->getTranslatedType($entry['type']) . ' ' . $entry['volume'];
        }

        return !empty($entry['label']) ? $entry['label'] : $entry['orderlabel'];
    }
}
