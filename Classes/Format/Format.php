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

namespace Kitodo\Dlf\Format;

enum Format : string
{
    case MODS = 'mods';
    case TEIHDR = 'teiheader';
    case ALTO = 'alto';
    case IIIF1 = 'iiif1';
    case IIIF2 = 'iiif2';
    case IIIF3 = 'iiif3';
    case SLUB = 'slub';
    case METS = 'mets';
    case OAI = 'oai-pmh';
    case XLINK = 'xlink';
    case AUDIOMD = 'audiomd';
    case VIDEOMD = 'videomd';
    case DVRIGHTS = 'rights';
    case DVLINKS = 'links';

    public function class(): string
    {
        return match($this)
        {
            Format::MODS => Mods::class,
            Format::TEIHDR => TeiHeader::class,
            Format::ALTO => Alto::class,
            Format::IIIF1 => '',
            Format::IIIF2 => '',
            Format::IIIF3 => '',
            Format::SLUB => '',
            Format::METS => '',
            Format::OAI => '',
            Format::XLINK => '',
            Format::AUDIOMD => AudioVideoMD::class,
            Format::VIDEOMD => AudioVideoMD::class,
            Format::DVRIGHTS => Mods::class,
            Format::DVLINKS => Mods::class
        };
    }

    public function encoded(): int
    {
        return match($this)
        {
            Format::MODS => 1,
            Format::TEIHDR => 2,
            Format::ALTO => 3,
            Format::IIIF1 => 4,
            Format::IIIF2 => 5,
            Format::IIIF3 => 6,
            Format::SLUB => 7,
            Format::METS => 8,
            Format::OAI => 9,
            Format::XLINK => 10,
            Format::AUDIOMD => 11,
            Format::VIDEOMD => 12,
            Format::DVRIGHTS => 13,
            Format::DVLINKS => 14
        };
    }

    //TODO: it can actually return array of strings to support many namespaces for format
    public function namespace(): string
    {
        return match($this)
        {
            Format::MODS => 'http://www.loc.gov/mods/v3',
            Format::TEIHDR => 'http://www.tei-c.org/ns/1.0',
            Format::ALTO => 'http://www.loc.gov/standards/alto/ns-v2#',
            Format::IIIF1 => 'http://www.shared-canvas.org/ns/context.json',
            Format::IIIF2 => 'http://iiif.io/api/presentation/2/context.json',
            Format::IIIF3 => 'http://iiif.io/api/presentation/3/context.json',
            Format::SLUB => 'http://slub-dresden.de',
            Format::METS => 'http://www.loc.gov/METS/',
            Format::OAI => 'http://www.openarchives.org/OAI/2.0/',
            Format::XLINK => 'http://www.w3.org/1999/xlink',
            Format::AUDIOMD => 'http://www.loc.gov/audioMD/',
            Format::VIDEOMD => 'http://www.loc.gov/videoMD/',
            Format::DVRIGHTS => 'http://dfg-viewer.de/',
            Format::DVLINKS => 'http://dfg-viewer.de/'
        };
    }

    public function root(): string
    {
        return match($this)
        {
            Format::MODS => 'mods',
            Format::TEIHDR => 'teiHeader',
            Format::ALTO => 'alto',
            Format::IIIF1 => 'IIIF1',
            Format::IIIF2 => 'IIIF2',
            Format::IIIF3 => 'IIIF3',
            Format::SLUB => 'slub',
            Format::METS => 'mets',
            Format::OAI => 'OAI-PMH',
            Format::XLINK => 'xlink',
            Format::AUDIOMD => 'audioMD',
            Format::VIDEOMD => 'videoMD',
            Format::DVRIGHTS => 'rights',
            Format::DVLINKS => 'links'
        };
    }
}
