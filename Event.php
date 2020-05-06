<?php

namespace Plugin\EccubePluginNotice4;

use Eccube\Event\TemplateEvent;
use Eccube\Request\Context;
use Eccube\Service\PluginApiService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class Event implements EventSubscriberInterface
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var PluginApiService
     */
    private $pluginApiService;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER_ARGUMENTS => [
                ['onKernelControllerArguments', 1000000]
            ]
        ];
    }

    public function __construct(
        Context $context,
        EventDispatcherInterface $eventDispatcher,
        PluginApiService $pluginApiService
    )
    {
        $this->context = $context;
        $this->eventDispatcher = $eventDispatcher;
        $this->pluginApiService = $pluginApiService;
    }

    public function onKernelControllerArguments(FilterControllerArgumentsEvent $event)
    {
        if(!$this->context->isAdmin()) {
            return;
        }

        if ($event->getRequest()->attributes->has('_template')) {
            $template = $event->getRequest()->attributes->get('_template');
            $this->eventDispatcher->addListener($template->getTemplate(), function (TemplateEvent $templateEvent) {

                // 毎回Plugin APIにアクセスしないよう1分間キャッシュする
                $cache = new FilesystemAdapter('', 60);
                $data = $cache->getItem('eccube_plugin_notice');

                if(!$data->isHit()) {
                    $data->set($this->pluginApiService->getPurchased());
                    $cache->save($data);
                }

                foreach($data->get('eccube_plugin_notice') as $item) {
                    if($item["update_status"] === 3) {
                        $templateEvent->addAsset('@EccubePluginNotice4/admin/assets/style.twig');
                        $templateEvent->addSnippet('@EccubePluginNotice4/admin/assets/script.twig');
                        break;
                    }
                }
            });
        }
    }
}
