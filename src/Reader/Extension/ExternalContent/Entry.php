<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Feed\Reader\Extension\ExternalContent;

//use Zend\Feed\Reader;
use Zend\Feed\Reader\Extension;
use Zend\Feed\Reader\Entry\EntryInterface;
use Zend\Http\Response as HttpResponse;
use Zend\Dom\Query;

class Entry extends Extension\AbstractEntry
{
    /**
     * @var HttpResponse
     */
    protected $response;
    
    /**
     * @var Query
     */
    protected $query;

    protected $html;
    
    protected $content;

    protected $defaultCharset = 'UTF-8';

    protected $charset;

    protected $redirectLink;

    protected $contentStatusCode = 200;

    protected $config;

    protected function registerNamespaces()
    {    
    }

    public function getRedirectLink()
    {
        return $this->redirectLink;
    }

    public function getContentStatusCode()
    {
        return $this->contentStatusCode;
    }

    public function getContent(EntryInterface $entry = null)
    {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->loadContent(
            $entry->getLink(),
            $this->getConfig('http')
        );

        if (!$this->html) {
            return;
        }
        $articleKey = $this->getEntryContentKey($entry);
        if (!$articleKey) {
            $this->content = $this->html;
        } elseif (($content = $this->queryContent($articleKey))) {
            $this->content = $content;
        } else {
            $this->content = $this->html;
        }

        return $this->content;
    }

    protected function loadContent($url, $clientConfig)
    {
        if ($this->html !== null) {
            return $this;
        }

        $httpClient = new \Zend\Http\Client($url, $clientConfig);
        $this->response = $httpClient->send();

        if ($httpClient->getRedirectionsCount()) {
            $this->redirectUrl = $httpClient->getUri()->toString();
        }

        $this->contentStatusCode = $this->response->getStatusCode();

        $this->html = $this->getPreparedHtml();

        if (!$this->html) {
            return $this;
        }
        $charset = $this->getCharset();
        if ($charset != $this->defaultCharset) {
            //$this->html = $this->clearHtml();
            $this->html = mb_convert_encoding($this->html, $this->defaultCharset, $charset);
        }
        $this->query = new Query(
            mb_convert_encoding($this->html, 'HTML-ENTITIES', $this->defaultCharset),
            $this->defaultCharset
        );

        return $this;
    }
    
    protected function getPreparedHtml()
    {
        $html = trim($this->response->getBody());

        //Fix extra content in document
        if ('<' . '?xml' == substr($html, 0, 5)) {
            $html = substr($html, stripos($html, '>', 5) + 1);
        }
        return $html;
    }

    protected function queryContent($query, DOMNode $contextNode = null)
    {
        $nodes = $this->query->execute($query, $contextNode);
        if (!$nodes->count()) {
            return null;
        }
        $result = '';
        foreach($nodes as $node) {
            $result .= $node->textContent;
        }
        return $result;
    }
    
    protected function getCharset()
    {
        if ($this->charset) {
            return $this->charset;
        }
        $contentType = $this->response->getHeaders()->get('Content-Type');
        $charset = $contentType->getCharset();
        if (!$charset) {
            $query = new Query($this->html, $this->defaultCharset);
            $res = $query->execute('meta[http-equiv="Content-Type"]');
            if ($res->count()) {
                $content = $res[0]->attributes->getNamedItem('content');
                if ($content) {
                    $content = explode(';', $content->value);
                    foreach($content as $value) {
                        $value = trim($value);
                        if (stripos($value, 'charset=') === 0) {
                            $charset = substr($value, 8);
                            break;
                        }
                    }
                }
            }
            if (!$charset) {
                $charset = mb_detect_encoding($this->html);
            }
        }
        $this->charset = strtoupper($charset);
        return $this->charset;
    }
    
    protected function getEntryContentKey(EntryInterface $entry)
    {
        $articleConfig = $this->getConfig('feeds');

        $entryLink = $this->redirectLink ?: $entry->getLink();
        $articleKey = parse_url($entryLink, PHP_URL_HOST);
        if (!isset($articleConfig[$articleKey])) {
            return null;
        }
        $contentKey = $articleConfig[$articleKey];
        if (is_string($contentKey)) {
            return $contentKey;
        }
        $urlPath = trim(parse_url($entryLink, PHP_URL_PATH), '/');
        foreach($contentKey as $urlPrefix => $urlKey) {
            if (stripos($urlPath, $urlPrefix) === 0) {
                return $urlKey;
            }
        }
        if (isset($contentKey[0])) {
            return $contentKey[0];
        }
        return null;
    }
    
    public function setConfig($config)
    {
        $this->config = $config;
    }

    protected function getConfig($key = null, $default = false)
    {
        if ($this->config === null) {
            throw new \Exception('config not defined');
        }

        if ($key === null) {
            return $this->config;
        }
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }
        return $default;
    }
    
    protected function QQQclearHtml() {
        $this->html = str_replace("\r", " ", $this->html);
        $this->html = str_replace("\n", " ", $this->html);
        
        $this->html = $this->remove_noise($this->html, "'<!\[CDATA\[(.*?)\]\]>'is", true);
        // strip out comments
        $this->html = $this->remove_noise($this->html, "'<!--(.*?)-->'is");
        // Per sourceforge http://sourceforge.net/tracker/?func=detail&aid=2949097&group_id=218559&atid=1044037
        // Script tags removal now preceeds style tag removal.
        // strip out <script> tags
        $this->html = $this->remove_noise($this->html, "'<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>'is");
        $this->html = $this->remove_noise($this->html, "'<\s*script\s*>(.*?)<\s*/\s*script\s*>'is");
        // strip out <style> tags
        $this->html = $this->remove_noise($this->html, "'<\s*style[^>]*[^/]>(.*?)<\s*/\s*style\s*>'is");
        $this->html = $this->remove_noise($this->html, "'<\s*style\s*>(.*?)<\s*/\s*style\s*>'is");
        // strip out preformatted tags
        $this->html = $this->remove_noise($this->html, "'<\s*(?:code)[^>]*>(.*?)<\s*/\s*(?:code)\s*>'is");
        // strip out server side scripts
        $this->html = $this->remove_noise($this->html, "'(<\?)(.*?)(\?>)'s", true);
        // strip smarty scripts
        $this->html = $this->remove_noise($this->html, "'(\{\w)(.*?)(\})'s", true);
        
        
        /*while ($this->parse());
        // end
        $this->root->_[HDOM_INFO_END] = $this->cursor;*/

        return $this->html;
    }

    protected function QQQremove_noise($html, $pattern, $remove_tag=false)
    {
        $count = preg_match_all($pattern, $html, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE);

        for ($i=$count-1; $i>-1; --$i) {
            $key = '___noise___'.sprintf('% 5d', count($this->noise)+1000);
            $idx = ($remove_tag) ? 0 : 1;
            $this->noise[$key] = $matches[$i][$idx][0];
            $html = substr_replace($html, $key, $matches[$i][$idx][1], strlen($matches[$i][$idx][0]));
        }
        return $html;
    }
    
    protected function QQQrestore_noise($text)
    {
        while (($pos=strpos($text, '___noise___'))!==false) {
            // Sometimes there is a broken piece of markup, and we don't GET the pos+11 etc... token which indicates a problem outside of us...
            if (strlen($text) > $pos+15) {
                $key = '___noise___' . $text[$pos+11] . $text[$pos+12] . $text[$pos+13] . $text[$pos+14] . $text[$pos+15];
                if (isset($this->noise[$key])) {
                    $text = substr($text, 0, $pos) . $this->noise[$key] . substr($text, $pos+16);
                } else {
                    // do this to prevent an infinite loop.
                    $text = substr($text, 0, $pos) . 'UNDEFINED NOISE FOR KEY: ' . $key . substr($text, $pos+16);
                }
            } else {
                // There is no valid key being given back to us... We must get rid of the ___noise___ or we will have a problem.
                $text = substr($text, 0, $pos) . 'NO NUMERIC NOISE KEY' . substr($text, $pos+11);
            }
        }
        return $text;
    }
}
