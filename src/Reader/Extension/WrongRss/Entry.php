<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Feed\Reader\Extension\WrongRss;

use Zend\Feed\Reader\Extension;
use DateTime;
use Zend\Feed\Reader\Entry\EntryInterface;

class Entry extends Extension\AbstractEntry
{
    protected function registerNamespaces()
    {
    }

    public function getLinks()
    {
        $links = $this->entry->getElementsByTagName('link');
        if (!$links->length) {
            return [];
        }

        $result = [];
        foreach ($links as $link) {
            $result[] = $link->nodeValue;
        }
        return $result;
    }

    /**
     * Get the "in-reply-to" value
     *
     * @return string
     */
    public function getDateModified()
    {
        $date = $this->entry->getElementsByTagName('pubDate');
        if (!$date->length) {
            return;
        }
        $date = $date->item(0);
        $date = $date->nodeValue;
        $dateModifiedParsed = strtotime($date);
        if ($dateModifiedParsed) {
            $dateModifiedParsed = new DateTime('@' . $dateModifiedParsed);
            return $dateModifiedParsed;
        }
    }

    public function getDescription()
    {
        $description = $this->entry->getElementsByTagName('description');
        if (!$description->length) {
            return;
        }
        if (!$description->item(0)->nodeValue) {
            return;
        }
        return $description->item(0)->nodeValue;
    }

    public function getTitle()
    {
        $title = $this->entry->getElementsByTagName('title');
        if (!$title->length) {
            return;
        }
        if (!$title->item(0)->nodeValue) {
            return;
        }
        return $title->item(0)->nodeValue;
    }

    public function getContent(EntryInterface $entry = null)
    {
        $content = $this->entry->getElementsByTagNameNS("http://news.yandex.ru", 'full-text');
        
        if ($content->length) {
            return $content->item(0)->nodeValue;
        }
    }
}
