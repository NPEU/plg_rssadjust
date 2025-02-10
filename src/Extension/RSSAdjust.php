<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.RSSAdjust
 *
 * @copyright   Copyright (C) NPEU 2025.
 * @license     MIT License; see LICENSE.md
 */

namespace NPEU\Plugin\System\RSSAdjust\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * Make changes to RSS feeds.
 */
class RSSAdjust extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    /**
     * An internal flag whether plugin should listen any event.
     *
     * @var bool
     *
     * @since   4.3.0
     */
    protected static $enabled = false;

    /**
     * Constructor
     *
     */
    public function __construct($subject, array $config = [], bool $enabled = true)
    {
        // The above enabled parameter was taken from the Guided Tour plugin but it always seems
        // to be false so I'm not sure where this param is passed from. Overriding it for now.
        $enabled = true;


        #$this->loadLanguage();
        $this->autoloadLanguage = $enabled;
        self::$enabled          = $enabled;

        parent::__construct($subject, $config);
    }

    /**
     * function for getSubscribedEvents : new Joomla 4 feature
     *
     * @return array
     *
     * @since   4.3.0
     */
    public static function getSubscribedEvents(): array
    {
        return self::$enabled ? [
            'onAfterRender' => 'onAfterRender',
        ] : [];
    }

    /**
     * @param   array  $options  Array holding options
     *
     * @return  boolean  True on success
     */
    public function onAfterRender($options)
    {
        #return true;

        $app = Factory::getApplication();
        if ($app->isClient('administrator')) {
            return; // Don't run in admin
        }
        $uri = Uri::getInstance();
        $format = $uri->getVar('format');
        if ($format != 'feed') {
            return; // Don't run unless we're dealing with a feed.
        }

        $menu = Factory::getApplication()->getMenu();
        $menu_item = $menu->getActive();

        $title = $menu_item->title;

        $mi = $menu_item;
        while ($mi->parent_id > 1) {
            $mi = $menu->getItem($mi->parent_id);
        }

        $title .= ' | ' . $mi->title . ' | NPEU';

        #echo '<pre>'; var_dump($title); echo '</pre>'; exit;

        $media_namesspace = 'http://search.yahoo.com/mrss/';

        $body = $app->getBody();
        $body = str_replace('<rss', '<rss xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:media="' . $media_namesspace . '"', $body);
        $xml  = new \SimpleXMLElement($body);

        $xml->channel->title = $title;
        $article_model = $app->bootComponent('com_content')->getMVCFactory()->createModel('Article', 'Administrator', ['ignore_request' => true]);
        $logo = $xml->channel->addChild('image');
        $logo->addChild('url', URI::root() . 'assets/images/brand-logos/unit/npeu-device-logo.png');
        $logo->addChild('title', 'NPEU');
        $logo->addChild('link', 'https://www.npeu.ox.ac.uk/');
        foreach($xml->channel->item as $item) {
            $url_parts = explode('/', $item->link);
            $url_last = array_pop($url_parts);
            // Article ID is the first set of digits:
            $link_parts = explode('-', $url_last);
            $id = array_shift($link_parts);

            if (!is_numeric($id)) {
                continue;
            }

            $article_model = $app->bootComponent('com_content')->getMVCFactory()->createModel('Article', 'Administrator', ['ignore_request' => true]);
            $article = $article_model->getItem($id);

            $fields = FieldsHelper::getFields('com_content.article',  $article, true);

            if (empty($fields)) {
                continue;
            }

            if ($fields[0]->name == 'headline-image' && !empty($fields[0]->rawvalue)) {
                $img_data = json_decode($fields[0]->rawvalue);

                // Older version of Joomla stored the custom field image differently:
                if ($img_data) {
                    $img_url = URI::root() . preg_replace('/#.*/', '', $img_data->imagefile) . '?s=300';
                } else {
                    $img_url = URI::root() . $fields[0]->rawvalue;
                }
                $image = $item->addChild('media:thumbnail', null, $media_namesspace);
                $image->addAttribute('url', $img_url);
            }
        }
        $app->setBody($xml->asXML());
        return true;
    }
}